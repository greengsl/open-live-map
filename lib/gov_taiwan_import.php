<?php
declare(strict_types=1);

function govTaiwanFetch(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 25,
            'header' => implode("\r\n", [
                'User-Agent: Mozilla/5.0 OpenLiveMap/1.0',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]),
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || $body === '') {
        throw new RuntimeException('讀取 gov.tw 失敗：' . $url);
    }
    return $body;
}

function govTaiwanDecode(string $value): string
{
    return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function govTaiwanFirstMatch(string $html, string $pattern): string
{
    if (preg_match($pattern, $html, $matches)) {
        return govTaiwanDecode((string) ($matches[1] ?? ''));
    }
    return '';
}

function govTaiwanGeocodeCachePath(): string
{
    return dirname(__DIR__) . '/cache/gov-geocode.json';
}

function govTaiwanLoadGeocodeCache(): array
{
    $path = govTaiwanGeocodeCachePath();
    if (!is_file($path)) {
        return [];
    }
    $json = json_decode((string) file_get_contents($path), true);
    return is_array($json) ? $json : [];
}

function govTaiwanSaveGeocodeCache(array $cache): void
{
    $path = govTaiwanGeocodeCachePath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('gov.tw 座標快取寫入失敗。');
    }
}

function govTaiwanGeocode(string $title, array &$cache): ?array
{
    $cleanTitle = trim($title);
    if ($cleanTitle === '') {
        return null;
    }
    $placeOnly = $cleanTitle;
    if (str_contains($cleanTitle, ' - ')) {
        $parts = explode(' - ', $cleanTitle, 2);
        $placeOnly = trim((string) ($parts[1] ?? $cleanTitle));
    }
    $variants = [
        $placeOnly,
        str_replace('溼', '濕', $placeOnly),
        str_replace(' - ', ' ', $cleanTitle),
        str_replace('溼', '濕', str_replace(' - ', ' ', $cleanTitle)),
    ];
    foreach (array_values(array_unique(array_filter(array_map('trim', $variants)))) as $variant) {
        $query = preg_replace('/\s+/', ' ', $variant) . ' 台灣';
        $key = mb_strtolower($query, 'UTF-8');
        if (isset($cache[$key]) && is_array($cache[$key])) {
            return $cache[$key];
        }
        if (isset($cache[$key]) && $cache[$key] === null) {
            continue;
        }

        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=tw&q=' . rawurlencode($query);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => implode("\r\n", [
                    'User-Agent: OpenLiveMap/1.0 (https://info.green.myds.me/)',
                    'Accept: application/json',
                ]),
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $rows = is_string($body) ? json_decode($body, true) : null;
        usleep(1100000);
        if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) {
            $cache[$key] = null;
            continue;
        }
        $lat = (float) ($rows[0]['lat'] ?? 0);
        $lng = (float) ($rows[0]['lon'] ?? 0);
        if ($lat < 20 || $lat > 27 || $lng < 118 || $lng > 123) {
            $cache[$key] = null;
            continue;
        }
        $cache[$key] = [
            'lat' => $lat,
            'lng' => $lng,
            'display_name' => (string) ($rows[0]['display_name'] ?? ''),
            'updated_at' => gmdate('c'),
        ];
        return $cache[$key];
    }
    return null;
}

function govTaiwanImportVideos(string $listUrl, int $limit = 20, string $keyword = ''): array
{
    $listUrl = trim($listUrl);
    if (!preg_match('/^https:\/\/www\.gov\.tw\/taiwan\/?$/i', $listUrl)) {
        throw new RuntimeException('請輸入 https://www.gov.tw/taiwan/');
    }
    $limit = max(1, min(60, $limit));
    $keyword = trim($keyword);
    $html = govTaiwanFetch($listUrl);
    preg_match_all('/href="(index_(\d+)\.html)"[^>]*title="([^"]+)"/i', $html, $matches, PREG_SET_ORDER);
    $items = [];
    foreach ($matches as $match) {
        $href = govTaiwanDecode((string) $match[1]);
        $id = govTaiwanDecode((string) $match[2]);
        $title = govTaiwanDecode((string) $match[3]);
        if ($keyword !== '' && mb_stripos($title, $keyword, 0, 'UTF-8') === false) {
            continue;
        }
        $items[$href] = [
            'id' => $id,
            'title' => $title,
            'url' => 'https://www.gov.tw/taiwan/' . $href,
        ];
    }
    if ($items === []) {
        throw new RuntimeException('gov.tw 清單沒有掃到符合條件的影像。');
    }

    $rows = [];
    $errors = [];
    $cache = govTaiwanLoadGeocodeCache();
    foreach (array_slice(array_values($items), 0, $limit) as $item) {
        try {
            $detail = govTaiwanFetch((string) $item['url']);
            $streamUrl = govTaiwanFirstMatch($detail, '/<iframe[^>]+src="([^"]+)"/i');
            $caption = govTaiwanFirstMatch($detail, '/<h2 class="caption">([^<]+)<\/h2>/i') ?: (string) $item['title'];
            $provider = govTaiwanFirstMatch($detail, '/本影像由([^。<]+)提供/u');
            $geo = govTaiwanGeocode($caption, $cache);
            if ($streamUrl === '' || $geo === null) {
                $errors[] = $caption . ' 缺少影像網址或座標';
                continue;
            }
            $cameraId = 'GOVTW-' . $item['id'];
            $rows[] = [
                'id' => customCctvId($cameraId),
                'camera_id' => $cameraId,
                'name' => $caption,
                'direction' => trim('景點即時影像' . ($provider !== '' ? ' / ' . $provider : '')),
                'lat' => (float) $geo['lat'],
                'lng' => (float) $geo['lng'],
                'stream_url' => $streamUrl,
                'enabled' => true,
                'updated_at' => gmdate('c'),
            ];
        } catch (Throwable $e) {
            $errors[] = (string) $item['title'] . ' ' . $e->getMessage();
        }
    }
    govTaiwanSaveGeocodeCache($cache);

    return [
        'rows' => $rows,
        'scanned' => count($items),
        'processed' => min(count($items), $limit),
        'errors' => $errors,
    ];
}
