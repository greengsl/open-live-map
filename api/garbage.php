<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$preferredCacheDir = dirname(__DIR__) . '/cache';
if (!is_dir($preferredCacheDir)) {
    @mkdir($preferredCacheDir, 0775, true);
}
$cacheDir = is_dir($preferredCacheDir) && is_writable($preferredCacheDir)
    ? $preferredCacheDir
    : rtrim(sys_get_temp_dir(), '/\\') . '/open-live-map-cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}

function respond(array $payload): void
{
    if (ob_get_length() !== false && ob_get_length() > 0) {
        ob_clean();
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function readCache(string $file, int $ttl): ?array
{
    if (!is_file($file) || time() - filemtime($file) > $ttl) {
        return null;
    }

    $json = json_decode((string) file_get_contents($file), true);
    return is_array($json) ? $json : null;
}

function writeCache(string $dir, string $file, array $payload): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

function curlRequest(string $url, array $options = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is not enabled.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Open Live Map',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/json,*/*'],
    ] + $options);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        throw new RuntimeException($error ?: 'Taoyuan garbage API failed with HTTP ' . $status);
    }

    return ['body' => (string) $body, 'status' => $status];
}

function timestampFromTyoemValue(mixed $value): string
{
    if (is_array($value) && isset($value['time']) && is_numeric($value['time'])) {
        return gmdate('c', (int) floor(((float) $value['time']) / 1000));
    }
    if (is_numeric($value)) {
        return gmdate('c', (int) floor(((float) $value) / 1000));
    }

    return gmdate('c');
}

function directionBearing(string $direction): float
{
    return [
        '北' => 0,
        '東北' => 45,
        '東' => 90,
        '東南' => 135,
        '南' => 180,
        '西南' => 225,
        '西' => 270,
        '西北' => 315,
    ][$direction] ?? 0;
}

function routeKey(string $plate): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/', '', $plate));
}

function fetchTaoyuanRouteIndex(string $cacheDir, string $token, string $cookieFile): array
{
    $cacheFile = $cacheDir . '/taoyuan-garbage-routes-v1.json';
    $cached = readCache($cacheFile, 21600);
    if ($cached !== null && isset($cached['routes']) && is_array($cached['routes'])) {
        return $cached['routes'];
    }

    $districts = [
        'lagi2-001' => '蘆竹區',
        'lagi2-002' => '八德區',
        'lagi2-003' => '桃園區',
        'lagi2-004' => '中壢區',
        'lagi2-005' => '平鎮區',
        'lagi2-006' => '楊梅區',
        'lagi2-007' => '大溪區',
        'lagi2-008' => '大園區',
        'lagi2-009' => '觀音區',
        'lagi2-010' => '新屋區',
        'lagi2-011' => '龜山區',
        'lagi2-012' => '龍潭區',
        'lagi2-013' => '復興區',
    ];

    $routes = [];
    foreach ($districts as $gid => $districtName) {
        $response = curlRequest('https://route.tyoem.gov.tw/web/dataManagerAgentWeb.jsp', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'random_form' => $token,
                'dcfid' => 'lagifQueryRouteByTown',
                'gid' => $gid,
            ]),
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/javascript, */*; q=0.01',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'Origin: https://route.tyoem.gov.tw',
                'Referer: https://route.tyoem.gov.tw/',
                'X-Requested-With: XMLHttpRequest',
            ],
        ]);
        $json = json_decode($response['body'], true);
        if (!is_array($json)) {
            continue;
        }
        foreach (($json['result'] ?? []) as $route) {
            if (!is_array($route)) {
                continue;
            }
            $plate = trim((string) ($route['car1'] ?? ''));
            $key = routeKey($plate);
            if ($key === '') {
                continue;
            }
            $routes[$key] = [
                'gid' => $gid,
                'district' => $districtName,
                'routing_id' => (string) ($route['routing_id'] ?? ''),
                'routing_name' => (string) ($route['routing_name'] ?? ''),
                'seq' => isset($route['seq']) ? (int) $route['seq'] : null,
                'run_type' => (string) ($route['run_type'] ?? ''),
                'plate' => $plate,
            ];
        }
    }

    writeCache($cacheDir, $cacheFile, [
        'generated_at' => gmdate('c'),
        'routes' => $routes,
    ]);

    return $routes;
}

function normalizeGarbageVehicle(array $row, array $routeIndex = []): ?array
{
    $lat = $row['lat'] ?? null;
    $lng = $row['lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }

    $carId = trim((string) ($row['car_id'] ?? ''));
    $carType = trim((string) ($row['car_type'] ?? '垃圾車'));
    $district = trim((string) ($row['gname'] ?? '桃園市'));
    $status = trim((string) ($row['status'] ?? ''));
    $direction = trim((string) ($row['direction'] ?? ''));
    $address = trim((string) ($row['addr'] ?? ''));
    $capacity = $row['capacity'] ?? null;
    $routeInfo = $routeIndex[routeKey($carId)] ?? null;
    $routingName = is_array($routeInfo) ? trim((string) ($routeInfo['routing_name'] ?? '')) : '';
    $routingDistrict = is_array($routeInfo) ? trim((string) ($routeInfo['district'] ?? '')) : '';
    $displayRoute = $routingName !== '' ? $carType . ' ' . $routingName : $carType;
    $headsignParts = array_values(array_filter([
        $district,
        $routingName !== '' ? '路班 ' . ($routingDistrict !== '' && $routingDistrict !== $district ? $routingDistrict . ' ' : '') . $routingName : '',
        $address,
    ], static fn (string $value): bool => $value !== ''));

    return [
        'id' => 'tyoem-garbage-' . md5($carId . '-' . $carType . '-' . $district),
        'kind' => 'garbage',
        'route' => $displayRoute,
        'headsign' => implode(' / ', $headsignParts),
        'plate' => $carId,
        'operator' => '桃園市政府環境管理處',
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'speed' => is_numeric($row['speed'] ?? null) ? (float) $row['speed'] : 0,
        'bearing' => directionBearing($direction),
        'status' => $status !== '' ? $status : '狀態未提供',
        'updated_at' => timestampFromTyoemValue($row['gpstime'] ?? $row['servertime'] ?? null),
        'note' => '桃園垃圾車 GPS / ' . $carType . ($routingName !== '' ? ' / 路班 ' . $routingName : '') . ($capacity !== null ? ' / 容量 ' . $capacity : ''),
        'address' => $address,
        'car_type' => $carType,
        'routing_id' => is_array($routeInfo) ? (string) ($routeInfo['routing_id'] ?? '') : '',
        'routing_name' => $routingName,
        'route_shift' => is_array($routeInfo) && isset($routeInfo['seq']) ? (string) $routeInfo['seq'] : '',
    ];
}

function boundsContains(array $bounds, float $lat, float $lng): bool
{
    [$south, $west, $north, $east] = $bounds;
    return $lat >= $south && $lat <= $north && $lng >= $west && $lng <= $east;
}

function requestBounds(): ?array
{
    $raw = (string) ($_GET['bounds'] ?? '');
    $parts = array_map('trim', explode(',', $raw));
    if (count($parts) !== 4) {
        return null;
    }
    $numbers = array_map('floatval', $parts);
    foreach ($numbers as $number) {
        if (!is_finite($number)) {
            return null;
        }
    }

    return $numbers;
}

function filterVehiclesByBounds(array $vehicles, ?array $bounds): array
{
    if ($bounds === null) {
        return array_values($vehicles);
    }

    return array_values(array_filter($vehicles, static function (array $vehicle) use ($bounds): bool {
        return isset($vehicle['lat'], $vehicle['lng'])
            && is_numeric($vehicle['lat'])
            && is_numeric($vehicle['lng'])
            && boundsContains($bounds, (float) $vehicle['lat'], (float) $vehicle['lng']);
    }));
}

function garbagePayload(array $vehicles, ?array $bounds, string $notice, string $generatedAt): array
{
    $filtered = filterVehiclesByBounds($vehicles, $bounds);
    return [
        'source' => '桃園垃圾車 GPS / route.tyoem.gov.tw',
        'notice' => $notice,
        'generated_at' => $generatedAt,
        'vehicles' => $filtered,
        'total_count' => count($vehicles),
        'filtered_count' => count($filtered),
    ];
}

try {
    if (isset($_GET['diagnose'])) {
        respond([
            'ok' => true,
            'active_cache_dir' => $cacheDir,
            'active_cache_dir_writable' => is_dir($cacheDir) && is_writable($cacheDir),
            'generated_at' => gmdate('c'),
        ]);
    }

    $cacheFile = $cacheDir . '/taoyuan-garbage-v1.json';
    $cached = readCache($cacheFile, 20);
    if ($cached !== null) {
        $cachedVehicles = is_array($cached['all_vehicles'] ?? null)
            ? $cached['all_vehicles']
            : (is_array($cached['vehicles'] ?? null) ? $cached['vehicles'] : []);
        respond(garbagePayload(
            $cachedVehicles,
            requestBounds(),
            ($cached['notice'] ?? '桃園垃圾車 GPS 資料已更新。') . '（快取）',
            (string) ($cached['generated_at'] ?? gmdate('c')),
        ));
    }

    $cookieFile = $cacheDir . '/tyoem-cookie.txt';
    $home = curlRequest('https://route.tyoem.gov.tw/', [
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    if (!preg_match('/name="random_form"\s+value="([^"]+)"/', $home['body'], $matches)) {
        throw new RuntimeException('Cannot read Taoyuan random_form token.');
    }

    $token = $matches[1];
    $api = curlRequest('https://route.tyoem.gov.tw/web/dataManagerAgentWeb.jsp', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'random_form' => $token,
            'dcfid' => 'carGpsAllQuery1',
        ]),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Origin: https://route.tyoem.gov.tw',
            'Referer: https://route.tyoem.gov.tw/',
            'X-Requested-With: XMLHttpRequest',
        ],
    ]);

    $json = json_decode($api['body'], true);
    if (!is_array($json)) {
        throw new RuntimeException('Taoyuan garbage API returned invalid JSON.');
    }

    $routeIndex = [];
    try {
        $routeIndex = fetchTaoyuanRouteIndex($cacheDir, $token, $cookieFile);
    } catch (Throwable) {
        $routeIndex = [];
    }

    $vehicles = [];
    foreach (($json['result'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $vehicle = normalizeGarbageVehicle($row, $routeIndex);
        if ($vehicle === null) {
            continue;
        }
        $vehicles[] = $vehicle;
    }

    $generatedAt = gmdate('c');
    $payload = [
        'source' => '桃園垃圾車 GPS / route.tyoem.gov.tw',
        'notice' => '桃園垃圾車 GPS 資料已更新。',
        'generated_at' => $generatedAt,
        'all_vehicles' => $vehicles,
    ];
    writeCache($cacheDir, $cacheFile, $payload);
    respond(garbagePayload($vehicles, requestBounds(), '桃園垃圾車 GPS 資料已更新。', $generatedAt));
} catch (Throwable $e) {
    $cacheFile = $cacheDir . '/taoyuan-garbage-v1.json';
    $cached = readCache($cacheFile, 300);
    if ($cached !== null) {
        $cachedVehicles = is_array($cached['all_vehicles'] ?? null)
            ? $cached['all_vehicles']
            : (is_array($cached['vehicles'] ?? null) ? $cached['vehicles'] : []);
        respond(garbagePayload(
            $cachedVehicles,
            requestBounds(),
            '桃園垃圾車暫時讀取失敗，顯示 5 分鐘內快取。',
            (string) ($cached['generated_at'] ?? gmdate('c')),
        ));
    }

    http_response_code(502);
    respond([
        'source' => '桃園垃圾車 GPS / route.tyoem.gov.tw',
        'notice' => '桃園垃圾車資料讀取失敗：' . $e->getMessage(),
        'generated_at' => gmdate('c'),
        'vehicles' => [],
    ]);
}
