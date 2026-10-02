<?php
declare(strict_types=1);

function tainanCctvFetch(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 25,
            'header' => implode("\r\n", [
                'User-Agent: Mozilla/5.0 OpenLiveMap/1.0',
                'Accept: application/json,text/plain,*/*',
                'Referer: https://tntcc.tainan.gov.tw/',
            ]),
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || $body === '') {
        throw new RuntimeException('讀取臺南市 CCTV 失敗。');
    }
    return $body;
}

function tainanCctvRows(int $limit = 1000): array
{
    $limit = max(1, min(1200, $limit));
    $json = json_decode(tainanCctvFetch('https://tntcc.tainan.gov.tw/traffic-platform-itms/api/cctvs'), true);
    $items = is_array($json) && isset($json['data']) && is_array($json['data']) ? $json['data'] : $json;
    if (!is_array($items)) {
        throw new RuntimeException('臺南市 CCTV 回傳格式不是可用 JSON 陣列。');
    }

    $rows = [];
    $errors = [];
    foreach (array_slice($items, 0, $limit) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $cameraId = trim((string) ($row['cctvId'] ?? $row['id'] ?? ''));
        $name = trim((string) ($row['cctvName'] ?? $row['name'] ?? ''));
        $lng = (float) ($row['lng'] ?? $row['wgsx'] ?? 0);
        $lat = (float) ($row['lat'] ?? $row['wgsy'] ?? 0);
        $streamUrl = trim((string) ($row['url'] ?? ''));
        $host = strtolower((string) (parse_url($streamUrl, PHP_URL_HOST) ?: ''));
        $isPrivateHost = preg_match('/^(10\.|127\.|172\.(1[6-9]|2\d|3[0-1])\.|192\.168\.|localhost$)/', $host) === 1;
        if ($cameraId === '' || $name === '' || $lat < 20 || $lat > 27 || $lng < 118 || $lng > 123 || !preg_match('/^https?:\/\//i', $streamUrl) || $isPrivateHost) {
            $errors[] = $cameraId !== '' ? $cameraId : 'unknown';
            continue;
        }
        $rows[] = [
            'id' => customCctvId('TNN-' . $cameraId),
            'camera_id' => 'TNN-' . $cameraId,
            'name' => $name,
            'direction' => '臺南市路口影像',
            'lat' => $lat,
            'lng' => $lng,
            'stream_url' => $streamUrl,
            'source_key' => 'tainan',
            'source_label' => '臺南市',
            'enabled' => true,
            'updated_at' => (string) ($row['updateTime'] ?? gmdate('c')),
        ];
    }

    return [
        'rows' => $rows,
        'scanned' => count($items),
        'processed' => min(count($items), $limit),
        'errors' => $errors,
    ];
}
