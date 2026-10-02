<?php
declare(strict_types=1);

function twLiveFetch(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                'User-Agent: Mozilla/5.0 OpenLiveMap/1.0',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ]),
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (is_string($body) && $body !== '') {
        return $body;
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => 'Mozilla/5.0 OpenLiveMap/1.0',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
        ]);
        $curlBody = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        if (is_string($curlBody) && $curlBody !== '') {
            return $curlBody;
        }
        if ($curlError !== '') {
            throw new RuntimeException('讀取 tw.live 失敗：' . $url . ' / ' . $curlError);
        }
    }
    $error = error_get_last();
    $reason = is_array($error) && isset($error['message']) ? ' / ' . (string) $error['message'] : '';
    throw new RuntimeException('讀取 tw.live 失敗：' . $url . $reason);
}

function twLiveHtmlDecode(string $value): string
{
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function twLiveFirstMatch(string $html, string $pattern): string
{
    if (preg_match($pattern, $html, $matches)) {
        return twLiveHtmlDecode(trim((string) ($matches[1] ?? '')));
    }
    return '';
}

function twLiveImportHighway(string $listUrl, int $limit = 20): array
{
    $listUrl = trim($listUrl);
    if (!preg_match('/^https:\/\/tw\.live\/national-highway\/\d+\/[A-Z]\/[A-Z]\/?$/i', $listUrl)) {
        throw new RuntimeException('請輸入 tw.live 國道路線頁，例如 https://tw.live/national-highway/1/N/N/');
    }
    $limit = max(1, min(40, $limit));
    $listHtml = twLiveFetch($listUrl);
    preg_match_all('/href="\/cam\/\?id=([^"]+)"/i', $listHtml, $matches);
    $cameraIds = array_values(array_unique(array_map('twLiveHtmlDecode', $matches[1] ?? [])));
    if ($cameraIds === []) {
        throw new RuntimeException('此頁沒有掃到監視器。');
    }

    $rows = [];
    $errors = [];
    foreach (array_slice($cameraIds, 0, $limit) as $cameraId) {
        try {
            $detailUrl = 'https://tw.live/cam/?id=' . rawurlencode($cameraId);
            $html = twLiveFetch($detailUrl);
            usleep(1200000);
            $lat = (float) twLiveFirstMatch($html, '/nearby\/\?lat=([0-9.\-]+)/i');
            $lng = (float) twLiveFirstMatch($html, '/nearby\/\?lat=[0-9.\-]+&amp;lng=([0-9.\-]+)/i');
            $name = twLiveFirstMatch($html, '/data-cam-name="([^"]+)"/i');
            $imageUrl = twLiveFirstMatch($html, '/data-original-src="([^"]+)"/i');
            if ($imageUrl === '') {
                $imageUrl = twLiveFirstMatch($html, '/data-cam-img="([^"]+)"/i');
            }
            $mileage = twLiveFirstMatch($html, '/<h2[^>]*>\s*([^<]*K[^<]*)\s*<\/h2>/iu');
            $city = twLiveFirstMatch($html, "/city:\s*'([^']+)'/i");
            $district = twLiveFirstMatch($html, "/district:\s*'([^']+)'/i");
            if ($lat < 20 || $lat > 27 || $lng < 118 || $lng > 123 || $name === '' || $imageUrl === '') {
                $errors[] = $cameraId . ' 缺少座標、名稱或影像網址';
                continue;
            }
            $rows[] = [
                'id' => customCctvId($cameraId),
                'camera_id' => $cameraId,
                'name' => $name,
                'direction' => trim(implode(' ', array_filter([$city, $district, $mileage]))),
                'lat' => $lat,
                'lng' => $lng,
                'stream_url' => $imageUrl,
                'enabled' => true,
                'updated_at' => gmdate('c'),
            ];
        } catch (Throwable $e) {
            $errors[] = $cameraId . ' ' . $e->getMessage();
        }
    }

    return [
        'rows' => $rows,
        'scanned' => count($cameraIds),
        'processed' => min(count($cameraIds), $limit),
        'errors' => $errors,
    ];
}
