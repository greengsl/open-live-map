<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/lib/doorplate.php';
require_once dirname(__DIR__) . '/lib/tgos.php';

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function firstString(array $row, array $keys): string
{
    foreach ($keys as $key) {
        $value = $row[$key] ?? '';
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return '';
}

function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLng / 2) ** 2);
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
}

function fetchNominatim(string $query): array
{
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'jsonv2',
        'limit' => '5',
        'countrycodes' => 'tw',
        'accept-language' => 'zh-TW,zh,en',
        'addressdetails' => '1',
        'bounded' => '1',
        'viewbox' => '118.0,27.0,123.5,20.0',
        'q' => $query,
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 18,
            'header' => implode("\r\n", [
                'User-Agent: OpenLiveMap/1.0 (https://info.green.myds.me/)',
                'Accept: application/json',
            ]),
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || $body === '') {
        throw new RuntimeException('地名搜尋服務暫時無法使用。');
    }

    $rows = json_decode($body, true);
    if (!is_array($rows)) {
        throw new RuntimeException('地名搜尋回傳格式錯誤。');
    }
    return $rows;
}

function normalizePlaceQuery(string $value): string
{
    return realpriceNormalizeAddress($value);
}

function doorplateSearchCityKey(string $query): string
{
    $normalized = normalizePlaceQuery($query);
    foreach (doorplateSources() as $key => $source) {
        $aliases = array_merge([(string) ($source['label'] ?? '')], is_array($source['city_aliases'] ?? null) ? $source['city_aliases'] : []);
        foreach ($aliases as $alias) {
            $alias = normalizePlaceQuery((string) $alias);
            if ($alias !== '' && str_contains($normalized, $alias)) {
                return (string) $key;
            }
        }
    }
    return '';
}

function doorplateSearchSuffix(string $query, string $cityKey): string
{
    $normalized = normalizePlaceQuery($query);
    $sources = doorplateSources();
    $source = is_array($sources[$cityKey] ?? null) ? $sources[$cityKey] : [];
    $aliases = array_merge([(string) ($source['label'] ?? '')], is_array($source['city_aliases'] ?? null) ? $source['city_aliases'] : []);
    foreach ($aliases as $alias) {
        $alias = normalizePlaceQuery((string) $alias);
        if ($alias !== '' && str_starts_with($normalized, $alias)) {
            $normalized = mb_substr($normalized, mb_strlen($alias, 'UTF-8'), null, 'UTF-8');
            break;
        }
    }
    $normalized = preg_replace('/^[^路街大道巷弄號]{1,12}(?:區|鄉|鎮|市)/u', '', $normalized) ?? $normalized;
    $suffix = realpriceDoorAddress($normalized);
    if (!str_contains($suffix, '號') || realpriceAddressRoadToken($suffix) === '') {
        return '';
    }
    return $suffix;
}

function geocodeAttachDistance(array $results, ?float $centerLat, ?float $centerLng): array
{
    foreach ($results as &$result) {
        $result['distance_from_center'] = $centerLat !== null && $centerLng !== null
            ? round(distanceMeters($centerLat, $centerLng, (float) $result['lat'], (float) $result['lng']))
            : null;
    }
    unset($result);
    return $results;
}

/** 實價登錄已定位過的地址（本機門牌／TGOS 批次／手動等） */
function fetchGeocodeCacheHit(string $query): array
{
    $address = realpriceNormalizeAddress($query);
    if ($address === '') {
        return [];
    }
    $key = realpriceGeocodeKey($address);
    $cache = realpriceLoadGeocodeCache();
    $hit = is_array($cache['items'][$key] ?? null) ? $cache['items'][$key] : null;
    if ($hit === null) {
        return [];
    }
    if (($hit['precision'] ?? '') === 'district') {
        return [];
    }
    $lat = (float) ($hit['lat'] ?? 0);
    $lng = (float) ($hit['lng'] ?? 0);
    if ($lat < 20 || $lat > 27 || $lng < 118 || $lng > 123.5) {
        return [];
    }
    $provider = (string) ($hit['provider'] ?? 'geocode_cache');
    return [[
        'name' => realpriceDoorAddress($address) ?: $address,
        'display_name' => (string) ($hit['display_name'] ?? $address),
        'type' => 'address',
        'lat' => $lat,
        'lng' => $lng,
        'bbox' => [],
        'provider' => $provider,
    ]];
}

/** 全國門牌定位（需 config.local.php 的 tgos_app_id / tgos_api_key） */
function fetchTgosQueryAddr(string $query): array
{
    if (!tgosHasQueryAddrCredentials()) {
        return [];
    }
    try {
        $tgos = tgosQueryAddr($query);
    } catch (Throwable $e) {
        return [];
    }
    if (empty($tgos['found'])) {
        return [];
    }
    $lat = (float) ($tgos['lat'] ?? 0);
    $lng = (float) ($tgos['lng'] ?? 0);
    if ($lat < 20 || $lat > 27 || $lng < 118 || $lng > 123.5) {
        return [];
    }
    $label = (string) ($tgos['display_name'] ?? $query);
    return [[
        'name' => realpriceDoorAddress($label) ?: $label,
        'display_name' => $label,
        'type' => 'doorplate',
        'lat' => $lat,
        'lng' => $lng,
        'bbox' => [],
        'provider' => 'tgos',
    ]];
}

function fetchDoorplate(string $query): array
{
    $cityKey = doorplateSearchCityKey($query);
    if ($cityKey === '') {
        return [];
    }
    $suffix = doorplateSearchSuffix($query, $cityKey);
    if ($suffix === '') {
        return [];
    }
    $indexPath = doorplateSearchIndexPath($cityKey);
    if (!is_file($indexPath)) {
        return [];
    }
    $index = realpriceLoadJsonFile($indexPath, ['items' => []]);
    $items = is_array($index['items'] ?? null) ? $index['items'] : [];
    $item = is_array($items[$suffix] ?? null) ? $items[$suffix] : null;
    if ($item === null) {
        return [];
    }
    return [[
        'name' => (string) ($item['name'] ?? $suffix),
        'display_name' => (string) ($item['display_name'] ?? $suffix),
        'type' => 'doorplate',
        'lat' => (float) ($item['lat'] ?? 0),
        'lng' => (float) ($item['lng'] ?? 0),
        'bbox' => [],
        'provider' => 'doorplate_opendata',
    ]];
}

$query = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($query, 'UTF-8') < 2) {
    respond(['ok' => false, 'message' => '請輸入至少 2 個字。', 'results' => []], 400);
}

$compactQuery = preg_replace('/\s+/u', '', $query);
if (preg_match('/^\d+(?:-\d+)?(?:號|号)?(?:之\d+)?$/u', (string) $compactQuery) === 1) {
    respond(['ok' => false, 'message' => '只有門號無法定位，請加上縣市、區、路名，例如「桃園市大園區航站南路9號」。', 'results' => []], 400);
}

$centerLat = is_numeric($_GET['lat'] ?? null) ? (float) $_GET['lat'] : null;
$centerLng = is_numeric($_GET['lng'] ?? null) ? (float) $_GET['lng'] : null;

foreach ([
    ['fn' => 'fetchDoorplate', 'source' => 'doorplate_opendata'],
    ['fn' => 'fetchGeocodeCacheHit', 'source' => 'geocode_cache'],
    ['fn' => 'fetchTgosQueryAddr', 'source' => 'tgos'],
] as $step) {
    $fn = (string) $step['fn'];
    $hits = $fn($query);
    if ($hits !== []) {
        respond([
            'ok' => true,
            'query' => $query,
            'results' => geocodeAttachDistance($hits, $centerLat, $centerLng),
            'source' => (string) $step['source'],
        ]);
    }
}

try {
    $rows = fetchNominatim($query);
    if ($rows === [] && !preg_match('/台灣|臺灣/u', $query)) {
        usleep(1100000);
        $rows = fetchNominatim($query . ' 台灣');
    }
} catch (Throwable $e) {
    respond(['ok' => false, 'message' => $e->getMessage(), 'results' => []], 502);
}

$results = [];
foreach ($rows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $lat = (float) ($row['lat'] ?? 0);
    $lng = (float) ($row['lon'] ?? 0);
    if ($lat < 20 || $lat > 27 || $lng < 118 || $lng > 123.5) {
        continue;
    }
    $bbox = [];
    if (isset($row['boundingbox']) && is_array($row['boundingbox']) && count($row['boundingbox']) === 4) {
        $bbox = [
            (float) $row['boundingbox'][0],
            (float) $row['boundingbox'][2],
            (float) $row['boundingbox'][1],
            (float) $row['boundingbox'][3],
        ];
    }
    $results[] = [
        'name' => firstString($row, ['name', 'display_name']) ?: $query,
        'display_name' => firstString($row, ['display_name', 'name']),
        'type' => firstString($row, ['type', 'category']),
        'lat' => $lat,
        'lng' => $lng,
        'bbox' => $bbox,
        'distance_from_center' => $centerLat !== null && $centerLng !== null ? round(distanceMeters($centerLat, $centerLng, $lat, $lng)) : null,
    ];
}

$results = geocodeAttachDistance($results, $centerLat, $centerLng);

if ($centerLat !== null && $centerLng !== null) {
    usort($results, static function (array $a, array $b): int {
        return (int) ($a['distance_from_center'] ?? PHP_INT_MAX) <=> (int) ($b['distance_from_center'] ?? PHP_INT_MAX);
    });
}

respond([
    'ok' => true,
    'query' => $query,
    'results' => $results,
]);
