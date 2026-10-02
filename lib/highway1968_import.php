<?php
declare(strict_types=1);

function highway1968Fetch(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 25,
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                'User-Agent: Mozilla/5.0 OpenLiveMap/1.0',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]),
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || $body === '') {
        throw new RuntimeException('讀取 1968services 失敗：' . $url);
    }
    return $body;
}

function highway1968Decode(string $value): string
{
    return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function highway1968First(string $html, string $pattern): string
{
    if (preg_match($pattern, $html, $matches)) {
        return highway1968Decode((string) ($matches[1] ?? ''));
    }
    return '';
}

function highway1968JsonValue(array $data, array $path): string
{
    $value = $data;
    foreach ($path as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return '';
        }
        $value = $value[$key];
    }
    return is_scalar($value) ? trim((string) $value) : '';
}

function highway1968LdJson(string $html): array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/is', $html, $matches);
    foreach ($matches[1] ?? [] as $json) {
        $data = json_decode(highway1968Decode((string) $json), true);
        if (is_array($data) && (string) ($data['@type'] ?? '') === 'VideoObject') {
            return $data;
        }
    }
    return [];
}

function highway1968ImportRoutes(array $routeIds): array
{
    $rows = [];
    $errors = [];
    $seen = [];
    foreach ($routeIds as $routeId) {
        $routeId = trim((string) $routeId);
        if ($routeId === '' || !preg_match('/^\d+$/', $routeId)) {
            continue;
        }
        $listUrl = 'https://www.1968services.tw/highway/' . rawurlencode($routeId);
        $listHtml = highway1968Fetch($listUrl);
        preg_match_all('/href="\/cam\/([^"]+)"/i', $listHtml, $matches);
        $slugs = array_values(array_unique(array_map('highway1968Decode', $matches[1] ?? [])));
        foreach ($slugs as $slug) {
            if (isset($seen[$slug])) {
                continue;
            }
            if (!preg_match('/^[a-z0-9+._-]+$/i', $slug)) {
                $errors[] = $slug . ' 格式不符';
                continue;
            }
            $seen[$slug] = true;
            try {
                $detailUrl = 'https://www.1968services.tw/cam/' . $slug;
                $detail = highway1968Fetch($detailUrl);
                $ld = highway1968LdJson($detail);
                $lat = (float) highway1968First($detail, '/[?&]lat=([0-9.\-]+)/i');
                $lng = (float) highway1968First($detail, '/[?&](?:lon|lng)=([0-9.\-]+)/i');
                if (($lat < 20 || $lng < 118) && $ld !== []) {
                    $lat = (float) highway1968JsonValue($ld, ['locationCreated', 'geo', 'latitude']);
                    $lng = (float) highway1968JsonValue($ld, ['locationCreated', 'geo', 'longitude']);
                }
                $streamUrl = highway1968First($detail, '/"contentUrl"\s*:\s*"([^"]+)"/i');
                if ($streamUrl === '' && $ld !== []) {
                    $streamUrl = highway1968JsonValue($ld, ['contentUrl']);
                }
                if ($streamUrl === '') {
                    $streamUrl = highway1968First($detail, '/class="video_obj"[^>]+(?:data-src|src)="([^"]+)"/i');
                }
                if ($streamUrl !== '' && substr($streamUrl, -9) !== '/snapshot') {
                    $streamUrl .= '/snapshot';
                }
                $name = highway1968First($detail, '/alt="([^"]+)"/i');
                if ($name === '' && $ld !== []) {
                    $name = highway1968JsonValue($ld, ['locationCreated', 'name'])
                        ?: preg_replace('/\s*即時影像\s*$/u', '', highway1968JsonValue($ld, ['name']));
                }
                if ($name === '') {
                    $name = highway1968First($detail, '/<h1[^>]*>(.*?)<\/h1>/is');
                    $name = trim(strip_tags($name));
                }
                if ($lat < 20 || $lat > 27 || $lng < 118 || $lng > 123 || $streamUrl === '' || $name === '') {
                    $errors[] = $slug . ' 缺少座標或影像網址';
                    continue;
                }
                $cameraId = '1968-' . $slug;
                $rows[] = [
                    'id' => customCctvId($cameraId),
                    'camera_id' => $cameraId,
                    'name' => $name,
                    'direction' => '快速道路 / 1968services / 公路局影像',
                    'lat' => $lat,
                    'lng' => $lng,
                    'stream_url' => $streamUrl,
                    'source_key' => 'thb',
                    'source_label' => '公路局省道',
                    'enabled' => true,
                    'updated_at' => gmdate('c'),
                ];
                usleep(180000);
            } catch (Throwable $e) {
                $errors[] = $slug . ' ' . $e->getMessage();
            }
        }
    }

    return [
        'rows' => $rows,
        'scanned' => count($seen),
        'errors' => $errors,
    ];
}
