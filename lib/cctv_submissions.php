<?php
declare(strict_types=1);

require_once __DIR__ . '/cctv_custom.php';

function cctvSubmissionPath(): string
{
    return dirname(__DIR__) . '/cache/cctv-submissions.json';
}

function loadCctvSubmissions(): array
{
    $path = cctvSubmissionPath();
    if (!is_file($path)) {
        return [];
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        return [];
    }
    return array_values(array_filter($json, static fn ($row): bool => is_array($row)));
}

function saveCctvSubmissions(array $rows): void
{
    $path = cctvSubmissionPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('監視器投稿寫入失敗。');
    }
}

function cctvSubmissionStatuses(): array
{
    return [
        'pending' => '待審核',
        'approved' => '已公開',
        'rejected' => '已退回',
    ];
}

function normalizeCctvSubmissionStatus(string $status): string
{
    return array_key_exists($status, cctvSubmissionStatuses()) ? $status : 'pending';
}

function createCctvSubmission(array $input, array $server = []): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $streamUrl = trim((string) ($input['stream_url'] ?? $input['url'] ?? ''));
    $direction = trim((string) ($input['direction'] ?? ''));
    $contact = trim((string) ($input['contact'] ?? ''));
    $note = trim((string) ($input['note'] ?? $input['description'] ?? ''));
    $coordinatePair = customCctvParseCoordinatePair((string) ($input['coordinates'] ?? ''))
        ?? customCctvParseCoordinatePair((string) ($input['lat'] ?? ''));
    $lat = $coordinatePair !== null ? $coordinatePair[0] : (float) ($input['lat'] ?? 0);
    $lng = $coordinatePair !== null ? $coordinatePair[1] : (float) ($input['lng'] ?? 0);

    if ($name === '') {
        throw new RuntimeException('請輸入監視器名稱。');
    }
    if ($streamUrl === '' || !preg_match('/^https?:\/\//i', $streamUrl)) {
        throw new RuntimeException('請輸入 http:// 或 https:// 開頭的影像網址。');
    }
    if (!customCctvValidCoordinate($lat, $lng)) {
        throw new RuntimeException('經緯度格式不正確。');
    }

    $limitText = static function (string $value, int $limit): string {
        $value = trim($value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit, 'UTF-8');
        }
        return substr($value, 0, $limit);
    };
    $source = inferCctvSource($streamUrl, 'community', '使用者投稿');
    $now = gmdate('c');
    $ip = trim((string) ($server['HTTP_CF_CONNECTING_IP'] ?? $server['HTTP_X_FORWARDED_FOR'] ?? $server['REMOTE_ADDR'] ?? ''));
    if (str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }

    return [
        'id' => 'cctv-submission-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)),
        'status' => 'pending',
        'name' => $limitText($name, 80),
        'direction' => $limitText($direction !== '' ? $direction : '使用者投稿', 120),
        'lat' => $lat,
        'lng' => $lng,
        'stream_url' => $streamUrl,
        'source_key' => (string) $source['key'],
        'source_label' => (string) $source['label'],
        'contact' => $limitText($contact, 160),
        'note' => $limitText($note, 800),
        'page_url' => $limitText((string) ($input['page_url'] ?? ''), 300),
        'user_agent' => $limitText((string) ($server['HTTP_USER_AGENT'] ?? ''), 300),
        'ip_hash' => $ip !== '' ? hash('sha256', $ip) : '',
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

function addCctvSubmission(array $input, array $server = []): array
{
    $rows = loadCctvSubmissions();
    $submission = createCctvSubmission($input, $server);
    if ($submission['ip_hash'] !== '') {
        $cutoff = time() - 3600;
        $recent = 0;
        foreach ($rows as $row) {
            if ((string) ($row['ip_hash'] ?? '') !== $submission['ip_hash']) {
                continue;
            }
            $created = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
            if ($created >= $cutoff) {
                $recent++;
            }
        }
        if ($recent >= 8) {
            throw new RuntimeException('投稿次數過多，請稍後再試。');
        }
    }
    array_unshift($rows, $submission);
    saveCctvSubmissions(array_slice($rows, 0, 1000));
    return $submission;
}

function approveCctvSubmission(string $id): array
{
    $rows = loadCctvSubmissions();
    foreach ($rows as &$row) {
        if ((string) ($row['id'] ?? '') !== $id) {
            continue;
        }
        if (normalizeCctvSubmissionStatus((string) ($row['status'] ?? 'pending')) !== 'pending') {
            throw new RuntimeException('這筆投稿已處理。');
        }
        $customRows = loadCustomCctvs();
        $custom = normalizeCustomCctvInput([
            'camera_id' => nextCustomCctvCameraId($customRows),
            'name' => (string) ($row['name'] ?? ''),
            'lat' => (string) ($row['lat'] ?? ''),
            'lng' => (string) ($row['lng'] ?? ''),
            'stream_url' => (string) ($row['stream_url'] ?? ''),
            'direction' => (string) ($row['direction'] ?? ''),
            'source_key' => (string) ($row['source_key'] ?? 'community'),
            'source_label' => (string) ($row['source_label'] ?? '使用者投稿'),
            'enabled' => '1',
        ], $customRows);
        $customRows[] = $custom;
        saveCustomCctvs($customRows);
        $row['status'] = 'approved';
        $row['approved_cctv_id'] = (string) ($custom['id'] ?? '');
        $row['updated_at'] = gmdate('c');
        unset($row);
        saveCctvSubmissions($rows);
        return $custom;
    }
    throw new RuntimeException('找不到指定的監視器投稿。');
}

function updateCctvSubmissionStatus(string $id, string $status): array
{
    $rows = loadCctvSubmissions();
    $status = normalizeCctvSubmissionStatus($status);
    foreach ($rows as &$row) {
        if ((string) ($row['id'] ?? '') === $id) {
            $row['status'] = $status;
            $row['updated_at'] = gmdate('c');
            unset($row);
            saveCctvSubmissions($rows);
            return $rows;
        }
    }
    throw new RuntimeException('找不到指定的監視器投稿。');
}

function cctvSubmissionSummary(array $rows): array
{
    $summary = array_fill_keys(array_keys(cctvSubmissionStatuses()), 0);
    foreach ($rows as $row) {
        $summary[normalizeCctvSubmissionStatus((string) ($row['status'] ?? 'pending'))]++;
    }
    return $summary;
}
