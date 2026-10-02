<?php
declare(strict_types=1);

function freewayCctvFetch(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                'User-Agent: OpenLiveMap/1.0 (https://info.green.myds.me/)',
                'Accept: application/xml,text/xml,*/*;q=0.8',
            ]),
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || $body === '') {
        throw new RuntimeException('讀取高公局 CCTV XML 失敗。');
    }
    return $body;
}

function freewayCctvText(string $block, string $tag): string
{
    if (preg_match('#<' . preg_quote($tag, '#') . '>(.*?)</' . preg_quote($tag, '#') . '>#s', $block, $matches)) {
        return html_entity_decode(trim((string) ($matches[1] ?? '')), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
    return '';
}

function freewayCctvRows(string $roadId = '000010'): array
{
    $roadId = trim($roadId);
    $importAll = $roadId === '' || strtoupper($roadId) === 'ALL' || $roadId === '*';
    $xml = freewayCctvFetch('https://tisvcloud.freeway.gov.tw/history/motc20/CCTV.xml');
    preg_match_all('#<CCTV>(.*?)</CCTV>#s', $xml, $matches);
    $rows = [];
    $errors = [];
    foreach ($matches[1] ?? [] as $block) {
        $id = freewayCctvText($block, 'CCTVID');
        $rowRoadId = freewayCctvText($block, 'RoadID');
        if ($id === '' || (!$importAll && $rowRoadId !== $roadId)) {
            continue;
        }
        $roadName = freewayCctvText($block, 'RoadName') ?: $rowRoadId;
        $direction = freewayCctvText($block, 'RoadDirection');
        $directionLabel = [
            'N' => '北上',
            'S' => '南下',
            'E' => '東向',
            'W' => '西向',
        ][$direction] ?? $direction;
        $start = freewayCctvText($block, 'Start');
        $end = freewayCctvText($block, 'End');
        $mile = freewayCctvText($block, 'LocationMile');
        $lat = (float) freewayCctvText($block, 'PositionLat');
        $lng = (float) freewayCctvText($block, 'PositionLon');
        $streamUrl = freewayCctvText($block, 'VideoStreamURL');
        if ($lat < 20 || $lat > 27 || $lng < 118 || $lng > 123 || $streamUrl === '') {
            $errors[] = $id . ' 缺少座標或影像網址';
            continue;
        }
        $section = trim($start . ($end !== '' ? '到' . $end : ''));
        $rows[] = [
            'id' => customCctvId($id),
            'camera_id' => $id,
            'name' => $roadName . ($section !== '' ? '(' . $section . ')' : ''),
            'direction' => trim(implode(' ', array_filter([$directionLabel, $mile]))),
            'lat' => $lat,
            'lng' => $lng,
            'stream_url' => $streamUrl,
            'enabled' => true,
            'updated_at' => gmdate('c'),
        ];
    }

    return [
        'rows' => $rows,
        'scanned' => count($matches[1] ?? []),
        'errors' => $errors,
    ];
}
