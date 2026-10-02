<?php
declare(strict_types=1);

function customCctvPath(): string
{
    return dirname(__DIR__) . '/cache/custom-cctv.json';
}

function loadCustomCctvs(): array
{
    $path = customCctvPath();
    if (!is_file($path)) {
        return [];
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        return [];
    }
    return array_values(array_filter($json, static fn ($row): bool => is_array($row)));
}

function saveCustomCctvs(array $rows): void
{
    $path = customCctvPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('自訂監視器寫入失敗。');
    }
}

function customCctvId(string $cameraId): string
{
    $clean = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($cameraId));
    return 'custom-cctv-' . strtolower(trim((string) $clean, '-'));
}

function customCctvCameraIdExists(string $cameraId, array $rows, string $ignoreId = ''): bool
{
    foreach ($rows as $row) {
        if ($ignoreId !== '' && (string) ($row['id'] ?? '') === $ignoreId) {
            continue;
        }
        if (strcasecmp(trim((string) ($row['camera_id'] ?? '')), $cameraId) === 0) {
            return true;
        }
    }
    return false;
}

function nextCustomCctvCameraId(array $rows, string $ignoreId = ''): string
{
    $prefix = 'custom-' . gmdate('Ymd-His');
    for ($index = 1; $index <= 999; $index++) {
        $candidate = sprintf('%s-%03d', $prefix, $index);
        if (!customCctvCameraIdExists($candidate, $rows, $ignoreId)) {
            return $candidate;
        }
    }
    return $prefix . '-' . bin2hex(random_bytes(3));
}

function inferCctvSource(string $url, string $fallbackKey = 'custom', string $fallbackLabel = '自訂監視器'): array
{
    $url = trim($url);
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));

    if (preg_match('/^trafficvideo\d*\.tainan\.gov\.tw$/', $host) === 1) {
        return ['key' => 'tainan', 'label' => '臺南市'];
    }
    if ($host === 'trafficcctv.nantou.gov.tw') {
        return ['key' => 'nantou', 'label' => '南投縣'];
    }
    if ($host === 'cctvtraffic.tycg.gov.tw') {
        return ['key' => 'taoyuan', 'label' => '桃園市'];
    }
    if (preg_match('/^cctv[a-z0-9-]*\.freeway\.gov\.tw$/', $host) === 1 || $host === 'tisvcloud.freeway.gov.tw') {
        return ['key' => 'freeway', 'label' => '國道官方'];
    }
    if (preg_match('/^cctv-ss\d+\.thb\.gov\.tw$/', $host) === 1 || $host === 'www.1968services.tw') {
        return ['key' => 'thb', 'label' => '公路局省道'];
    }
    if ($host === 'atis.ntpc.gov.tw' || $host === 'apiatis.ntpc.gov.tw' || preg_match('/^cctvatis\d+\.ntpc\.gov\.tw$/', $host) === 1) {
        return ['key' => 'newtaipei', 'label' => '新北市'];
    }
    if ($host === 'tw.live') {
        return ['key' => 'twlive', 'label' => 'tw.live'];
    }
    if ($host === 'www.gov.tw' || $host === 'gov.tw') {
        return ['key' => 'govtw', 'label' => '我的E政府'];
    }
    if ($host === 'www.youtube.com' || $host === 'youtube.com' || $host === 'youtu.be' || $host === 'www.youtube-nocookie.com' || $host === 'youtube-nocookie.com') {
        return ['key' => 'youtube', 'label' => 'YouTube 影像'];
    }

    return ['key' => $fallbackKey, 'label' => $fallbackLabel];
}

function customCctvValidCoordinate(float $lat, float $lng): bool
{
    return is_finite($lat) && is_finite($lng)
        && $lat >= -90 && $lat <= 90
        && $lng >= -180 && $lng <= 180
        && !($lat === 0.0 && $lng === 0.0);
}

function customCctvParseCoordinatePair(string $text): ?array
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }
    if (preg_match('/(-?\d+(?:\.\d+)?)\s*[,，\s]\s*(-?\d+(?:\.\d+)?)/u', $text, $matches) !== 1) {
        return null;
    }
    return [(float) $matches[1], (float) $matches[2]];
}

function normalizeCustomCctvInput(array $input, ?array $existingRows = null): array
{
    $cameraId = trim((string) ($input['camera_id'] ?? ''));
    $rows = $existingRows ?? loadCustomCctvs();
    $originalId = trim((string) ($input['original_id'] ?? ''));
    $name = trim((string) ($input['name'] ?? ''));
    $coordinatePair = customCctvParseCoordinatePair((string) ($input['coordinates'] ?? ''))
        ?? customCctvParseCoordinatePair((string) ($input['lat'] ?? ''));
    $lat = $coordinatePair !== null ? $coordinatePair[0] : (float) ($input['lat'] ?? 0);
    $lng = $coordinatePair !== null ? $coordinatePair[1] : (float) ($input['lng'] ?? 0);
    $streamUrl = trim((string) ($input['stream_url'] ?? ''));
    $direction = trim((string) ($input['direction'] ?? ''));
    $sourceKey = trim((string) ($input['source_key'] ?? ''));
    $sourceLabel = trim((string) ($input['source_label'] ?? ''));

    if ($cameraId === '') {
        $cameraId = nextCustomCctvCameraId($rows, $originalId);
    }
    if ($name === '') {
        throw new RuntimeException('請輸入顯示名稱或道路名稱。');
    }
    if (!customCctvValidCoordinate($lat, $lng)) {
        throw new RuntimeException('經緯度格式不正確。');
    }
    if ($streamUrl !== '' && !preg_match('/^https?:\/\//i', $streamUrl)) {
        throw new RuntimeException('影像網址需以 http:// 或 https:// 開頭。');
    }
    $source = inferCctvSource($streamUrl);
    if ($sourceKey === '') {
        $sourceKey = $source['key'];
    }
    if ($sourceLabel === '') {
        $sourceLabel = $source['label'];
    }
    $sourceKey = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $sourceKey));
    if ($sourceKey === '') {
        $sourceKey = 'custom';
    }

    return [
        'id' => customCctvId($cameraId),
        'camera_id' => $cameraId,
        'name' => $name,
        'direction' => $direction,
        'lat' => $lat,
        'lng' => $lng,
        'stream_url' => $streamUrl,
        'source_key' => $sourceKey,
        'source_label' => $sourceLabel,
        'enabled' => isset($input['enabled']),
        'updated_at' => gmdate('c'),
    ];
}

function customCctvToVehicle(array $row): ?array
{
    if (empty($row['enabled'])) {
        return null;
    }
    $lat = (float) ($row['lat'] ?? 0);
    $lng = (float) ($row['lng'] ?? 0);
    if (!customCctvValidCoordinate($lat, $lng)) {
        return null;
    }
    $cameraId = trim((string) ($row['camera_id'] ?? ''));
    $name = trim((string) ($row['name'] ?? ''));
    $streamUrl = (string) ($row['stream_url'] ?? '');
    if ($cameraId === '' || $name === '') {
        return null;
    }
    $explicitSourceKey = trim((string) ($row['source_key'] ?? ''));
    $explicitSourceLabel = trim((string) ($row['source_label'] ?? ''));
    $source = $explicitSourceKey !== ''
        ? [
            'key' => strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $explicitSourceKey)),
            'label' => $explicitSourceLabel !== '' ? $explicitSourceLabel : '自訂監視器',
        ]
        : inferCctvSource($streamUrl, 'custom', '自訂監視器');

    return [
        'id' => (string) ($row['id'] ?? customCctvId($cameraId)),
        'kind' => 'cctv',
        'route' => $name,
        'headsign' => trim((string) ($row['direction'] ?? '')) ?: '自訂點位',
        'plate' => $cameraId,
        'operator' => $source['label'],
        'lat' => $lat,
        'lng' => $lng,
        'speed' => null,
        'bearing' => 0,
        'status' => '自訂',
        'updated_at' => (string) ($row['updated_at'] ?? gmdate('c')),
        'note' => $source['label'] . '點位',
        'stream_url' => $streamUrl,
        'image_url' => '',
        'source_key' => $source['key'],
        'source_label' => $source['label'],
    ];
}
