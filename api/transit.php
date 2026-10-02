<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$city = preg_replace('/[^A-Za-z]/', '', $_GET['city'] ?? 'Taipei') ?: 'Taipei';
$allowedCities = ['Taipei', 'NewTaipei', 'Taoyuan', 'Taichung', 'Tainan', 'Kaohsiung'];
$requestedCities = array_values(array_intersect(
    $allowedCities,
    array_filter(array_map(
        static fn (string $value): string => preg_replace('/[^A-Za-z]/', '', $value) ?: '',
        explode(',', (string) ($_GET['cities'] ?? $city)),
    )),
));
if (count($requestedCities) === 0) {
    $requestedCities = [$city];
}
$city = $requestedCities[0];
$requestedKind = preg_replace('/[^A-Za-z-]/', '', $_GET['kind'] ?? 'all') ?: 'all';
$allowedSources = ['bus', 'bike', 'rail', 'rail-tra', 'rail-metro', 'rail-thsr', 'cctv'];
$rawSources = (string) ($_GET['sources'] ?? '');
$requestedSources = array_values(array_intersect(
    $allowedSources,
    array_filter(array_map(
        static fn (string $value): string => preg_replace('/[^A-Za-z-]/', '', $value) ?: '',
        explode(',', $rawSources),
    )),
));
if (count($requestedSources) === 0) {
    $requestedSources = trim($rawSources) === 'none'
        ? []
        : (in_array($requestedKind, $allowedSources, true) ? [$requestedKind] : ['bus']);
}
if (in_array('rail', $requestedSources, true)) {
    $requestedSources = array_values(array_unique(array_merge(
        array_filter($requestedSources, static fn (string $source): bool => $source !== 'rail'),
        ['rail-tra', 'rail-metro', 'rail-thsr'],
    )));
}
$sampleFile = dirname(__DIR__) . '/assets/data/transit-sample.json';
$configFile = dirname(__DIR__) . '/config.local.php';
$preferredCacheDir = dirname(__DIR__) . '/cache';
if (!is_dir($preferredCacheDir)) {
    @mkdir($preferredCacheDir, 0775, true);
}
$cacheDir = is_dir($preferredCacheDir) && is_writable($preferredCacheDir)
    ? $preferredCacheDir
    : rtrim(sys_get_temp_dir(), '/\\') . '/tdx-live-map-cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$boundsCacheKey = preg_replace('/[^0-9.,-]/', '', (string) ($_GET['bounds'] ?? ''));
$cacheSchema = 'v17';
$cacheKey = $cacheSchema . '-' . $requestedKind . '-' . implode('-', $requestedSources) . '-' . implode('-', $requestedCities) . '-' . $boundsCacheKey;
$cacheFile = $cacheDir . '/transit-' . $cacheKey . '.json';
$cacheTtlSeconds = 90;
$dynamicCacheTtlSeconds = 75;
$rateLimitBackoffSeconds = 600;
$clientId = getenv('TDX_CLIENT_ID') ?: '';
$clientSecret = getenv('TDX_CLIENT_SECRET') ?: '';

if (is_file($configFile)) {
    $config = require $configFile;
    if (is_array($config)) {
        $clientId = $clientId ?: (string) ($config['tdx_client_id'] ?? '');
        $clientSecret = $clientSecret ?: (string) ($config['tdx_client_secret'] ?? '');
    }
}

if (isset($_GET['diagnose'])) {
    respond([
        'ok' => true,
        'city' => $city,
        'kind' => $requestedKind,
        'sources' => $requestedSources,
        'php_version' => PHP_VERSION,
        'curl_enabled' => function_exists('curl_init'),
        'config_file_exists' => is_file($configFile),
        'tdx_client_id_set' => $clientId !== '',
        'tdx_client_secret_set' => $clientSecret !== '',
        'preferred_cache_dir' => $preferredCacheDir,
        'preferred_cache_dir_exists' => is_dir($preferredCacheDir),
        'preferred_cache_dir_writable' => is_dir($preferredCacheDir) && is_writable($preferredCacheDir),
        'active_cache_dir' => $cacheDir,
        'active_cache_dir_exists' => is_dir($cacheDir),
        'active_cache_dir_writable' => is_dir($cacheDir) && is_writable($cacheDir),
        'sample_file_exists' => is_file($sampleFile),
        'generated_at' => gmdate('c'),
    ]);
}

function respond(array $payload): void
{
    if (ob_get_length() !== false && ob_get_length() > 0) {
        ob_clean();
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function samplePayload(string $file, string $notice): array
{
    $raw = is_file($file) ? file_get_contents($file) : '[]';
    $vehicles = json_decode($raw ?: '[]', true);
    if (!is_array($vehicles)) {
        $vehicles = [];
    }

    return [
        'source' => 'sample',
        'notice' => $notice,
        'generated_at' => gmdate('c'),
        'vehicles' => $vehicles,
    ];
}

function readCache(string $file, int $ttl): ?array
{
    if (!is_file($file) || time() - filemtime($file) > $ttl) {
        return null;
    }

    $json = json_decode((string) file_get_contents($file), true);
    return is_array($json) ? $json : null;
}

function readAnyCache(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }

    $json = json_decode((string) file_get_contents($file), true);
    return is_array($json) ? $json : null;
}

function fallbackTransitCache(string $cacheDir, array $requestedSources, array $requestedCities, string $reason): ?array
{
    $files = glob($cacheDir . '/transit-v*.json') ?: [];
    usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

    $sourceNeedles = array_values(array_filter(array_map(static function (string $source): string {
        return [
            'bus' => 'Bus',
            'bike' => 'Bike',
            'rail-tra' => 'TRA',
            'rail-metro' => 'Metro',
            'rail-thsr' => 'THSR',
            'cctv' => 'CCTV',
        ][$source] ?? '';
    }, $requestedSources)));

    $candidates = [];
    foreach ($files as $file) {
        if (filesize($file) < 5000) {
            continue;
        }
        $payload = readAnyCache($file);
        if ($payload === null || empty($payload['vehicles']) || !is_array($payload['vehicles'])) {
            continue;
        }
        $source = (string) ($payload['source'] ?? '');
        $sourceScore = 0;
        if ($sourceNeedles !== []) {
            foreach ($sourceNeedles as $needle) {
                if ($needle !== '' && stripos($source, $needle) !== false) {
                    $sourceScore++;
                }
            }
            if ($sourceScore === 0) {
                continue;
            }
        }
        $cityScore = 0;
        if ($requestedCities !== []) {
            foreach ($requestedCities as $city) {
                if (stripos($source, (string) $city) !== false || stripos(basename($file), (string) $city) !== false) {
                    $cityScore++;
                }
            }
            if ($cityScore === 0) {
                continue;
            }
        }
        $candidates[] = [
            'score' => ($sourceScore * 1000) + ($cityScore * 100) + min(99, count($payload['vehicles'])),
            'mtime' => filemtime($file),
            'payload' => $payload,
        ];
    }

    usort($candidates, static function (array $a, array $b): int {
        if ($a['score'] === $b['score']) {
            return $b['mtime'] <=> $a['mtime'];
        }
        return $b['score'] <=> $a['score'];
    });

    if ($candidates !== []) {
        $payload = $candidates[0]['payload'];
        $payload['notice'] = 'TDX 暫時讀取失敗，顯示最接近的可用快取：' . $reason;
        $payload['warnings'] = array_values(array_unique(array_merge(
            is_array($payload['warnings'] ?? null) ? $payload['warnings'] : [],
            ['TDX 憑證或服務暫時不可用，已使用快取']
        )));
        return $payload;
    }

    return null;
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

function throttleFile(string $dir, string $name): string
{
    return $dir . '/throttle-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $name) . '.json';
}

function shouldSkipAfterRateLimit(string $file, int $seconds): bool
{
    if (!is_file($file)) {
        return false;
    }

    $json = json_decode((string) file_get_contents($file), true);
    $until = is_array($json) ? (int) ($json['until'] ?? 0) : 0;
    return $until > time();
}

function markRateLimit(string $dir, string $file, int $seconds, string $message): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($file, json_encode([
            'until' => time() + $seconds,
            'message' => $message,
            'created_at' => gmdate('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

function friendlyWarning(string $label, Throwable $e): string
{
    $message = $e->getMessage();
    if (stripos($message, 'rate limit') !== false || stripos($message, '429') !== false) {
        return $label . '暫時被 TDX 限流';
    }

    return $label . '暫時讀取失敗';
}

function httpPostForm(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [$status, $body === false ? '' : $body, $error];
}

function httpGetJson(string $url, string $token): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [$status, $body === false ? '' : $body, $error];
}

function fetchTdxJson(string $path, string $token, array $query = []): array
{
    $query = array_merge(['$format' => 'JSON'], $query);
    $url = 'https://tdx.transportdata.tw/api/basic/v2/' . ltrim($path, '/') . '?' . http_build_query($query);
    [$status, $body, $error] = httpGetJson($url, $token);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException($path . ' failed: ' . ($error ?: $body));
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        throw new RuntimeException($path . ' response is invalid.');
    }

    return $json;
}

function tdxTokenCacheFile(string $clientId, string $cacheDir): string
{
    return $cacheDir . '/tdx-token-' . substr(hash('sha256', $clientId), 0, 16) . '.json';
}

function fetchTdxJsonWithTokenRefresh(string $path, string $clientId, string $clientSecret, string $cacheDir, string &$token, array $query = []): array
{
    try {
        return fetchTdxJson($path, $token, $query);
    } catch (Throwable $e) {
        $message = $e->getMessage();
        if (stripos($message, 'invalid token') === false && stripos($message, '401') === false && stripos($message, 'Unauthorized') === false) {
            throw $e;
        }

        $tokenFile = tdxTokenCacheFile($clientId, $cacheDir);
        if (is_file($tokenFile)) {
            @unlink($tokenFile);
        }
        $token = getTdxTokenCached($clientId, $clientSecret, $cacheDir, true);
        return fetchTdxJson($path, $token, $query);
    }
}

function fetchTdxJsonCached(string $path, string &$token, string $cacheDir, string $cacheKey, int $ttlSeconds, array $query = [], string $clientId = '', string $clientSecret = ''): array
{
    $cacheFile = $cacheDir . '/tdx-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $cacheKey) . '.json';
    $cached = readCache($cacheFile, $ttlSeconds);
    if ($cached !== null && isset($cached['data']) && is_array($cached['data'])) {
        return $cached['data'];
    }

    $data = ($clientId !== '' && $clientSecret !== '')
        ? fetchTdxJsonWithTokenRefresh($path, $clientId, $clientSecret, $cacheDir, $token, $query)
        : fetchTdxJson($path, $token, $query);
    writeCache($cacheDir, $cacheFile, [
        'generated_at' => gmdate('c'),
        'data' => $data,
    ]);

    return $data;
}

function cityBounds(string $city): ?array
{
    return [
        'Taipei' => [24.94, 121.45, 25.22, 121.68],
        'NewTaipei' => [24.65, 121.20, 25.32, 122.05],
        'Taoyuan' => [24.80, 120.95, 25.13, 121.50],
        'Taichung' => [24.00, 120.45, 24.45, 121.00],
        'Tainan' => [22.86, 120.00, 23.45, 120.65],
        'Kaohsiung' => [22.45, 120.10, 23.45, 121.05],
    ][$city] ?? null;
}

function cityMetroSystems(string $city): array
{
    return [
        'Taipei' => ['TRTC'],
        'NewTaipei' => ['TRTC', 'NTDLRT'],
        'Taoyuan' => ['TYMC'],
        'Taichung' => ['TMRT'],
        'Kaohsiung' => ['KRTC'],
    ][$city] ?? [];
}

function withinBounds(float $lat, float $lng, ?array $bounds): bool
{
    if ($bounds === null) {
        return true;
    }

    return $lat >= $bounds[0] && $lng >= $bounds[1] && $lat <= $bounds[2] && $lng <= $bounds[3];
}

function withinAnyCityBounds(float $lat, float $lng, array $cities): bool
{
    foreach ($cities as $city) {
        if (withinBounds($lat, $lng, cityBounds((string) $city))) {
            return true;
        }
    }

    return count($cities) === 0;
}

function getTdxToken(string $clientId, string $clientSecret): string
{
    [$status, $body, $error] = httpPostForm(
        'https://tdx.transportdata.tw/auth/realms/TDXConnect/protocol/openid-connect/token',
        [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ],
    );

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('TDX token failed: ' . ($error ?: $body));
    }

    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['access_token'])) {
        throw new RuntimeException('TDX token response is invalid.');
    }

    return (string) $json['access_token'];
}

function getTdxTokenCached(string $clientId, string $clientSecret, string $cacheDir, bool $forceRefresh = false): string
{
    $cacheFile = tdxTokenCacheFile($clientId, $cacheDir);
    $cached = is_file($cacheFile) ? json_decode((string) file_get_contents($cacheFile), true) : null;
    $cacheFreshEnough = is_file($cacheFile) && time() - filemtime($cacheFile) < 2700;
    if (!$forceRefresh && $cacheFreshEnough && is_array($cached) && !empty($cached['access_token']) && (int) ($cached['expires_at'] ?? 0) > time() + 60) {
        return (string) $cached['access_token'];
    }

    [$status, $body, $error] = httpPostForm(
        'https://tdx.transportdata.tw/auth/realms/TDXConnect/protocol/openid-connect/token',
        [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ],
    );

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('TDX token failed: ' . ($error ?: $body));
    }

    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['access_token'])) {
        throw new RuntimeException('TDX token response is invalid.');
    }

    writeCache($cacheDir, $cacheFile, [
        'access_token' => (string) $json['access_token'],
        'expires_at' => time() + max(60, (int) ($json['expires_in'] ?? 300)),
        'generated_at' => gmdate('c'),
    ]);

    return (string) $json['access_token'];
}

function normalizeTdxBus(array $row, int $index): ?array
{
    $lat = $row['BusPosition']['PositionLat'] ?? null;
    $lng = $row['BusPosition']['PositionLon'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }

    $route = $row['RouteName']['Zh_tw'] ?? $row['RouteName']['En'] ?? $row['RouteID'] ?? 'Bus';
    $plate = $row['PlateNumb'] ?? '';
    $direction = isset($row['Direction']) ? ((int) $row['Direction'] === 0 ? '去程' : '返程') : '';
    $statusMap = [
        0 => '正常',
        1 => '車禍',
        2 => '故障',
        3 => '塞車',
        4 => '緊急求援',
        5 => '加油',
        90 => '不明',
        91 => '去回不明',
        98 => '偏移路線',
        99 => '非營運',
    ];
    $busStatus = isset($row['BusStatus']) ? (int) $row['BusStatus'] : 0;

    return [
        'id' => 'tdx-bus-' . md5($route . '-' . ($plate ?: $index)),
        'kind' => 'bus',
        'route' => (string) $route,
        'headsign' => $direction,
        'plate' => (string) $plate,
        'operator' => (string) ($row['OperatorID'] ?? ''),
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'speed' => (float) ($row['Speed'] ?? 0),
        'bearing' => (float) ($row['Azimuth'] ?? 0),
        'status' => $statusMap[$busStatus] ?? '未知',
        'updated_at' => (string) ($row['GPSTime'] ?? $row['UpdateTime'] ?? gmdate('c')),
        'note' => 'TDX city bus realtime position',
    ];
}

function normalizeTdxBike(array $station, ?array $availability): ?array
{
    $lat = $station['StationPosition']['PositionLat'] ?? null;
    $lng = $station['StationPosition']['PositionLon'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }

    $stationUid = (string) ($station['StationUID'] ?? $station['StationID'] ?? md5(json_encode($station)));
    $name = $station['StationName']['Zh_tw'] ?? $station['StationName']['En'] ?? $stationUid;
    $rent = (int) ($availability['AvailableRentBikes'] ?? 0);
    $returns = (int) ($availability['AvailableReturnBikes'] ?? 0);
    $status = (int) ($availability['ServiceStatus'] ?? $station['ServiceStatus'] ?? 0);
    $statusText = [
        0 => '停止營運',
        1 => '正常營運',
        2 => '暫停營運',
    ][$status] ?? '狀態未知';

    return [
        'id' => 'tdx-bike-' . $stationUid,
        'kind' => 'bike',
        'route' => (string) $name,
        'headsign' => '可借 ' . $rent . ' / 可還 ' . $returns,
        'plate' => $stationUid,
        'operator' => (string) ($station['AuthorityID'] ?? 'YouBike'),
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'speed' => 0,
        'bearing' => 0,
        'status' => $statusText,
        'updated_at' => (string) ($availability['UpdateTime'] ?? $station['UpdateTime'] ?? gmdate('c')),
        'note' => 'TDX bike station availability',
        'available_rent_bikes' => $rent,
        'available_return_bikes' => $returns,
    ];
}

function normalizeTraRail(array $row, array $stationsById, array $cities): ?array
{
    $stationId = (string) ($row['StationID'] ?? '');
    if ($stationId === '' || !isset($stationsById[$stationId])) {
        return null;
    }

    $station = $stationsById[$stationId];
    $lat = $station['StationPosition']['PositionLat'] ?? null;
    $lng = $station['StationPosition']['PositionLon'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng) || !withinAnyCityBounds((float) $lat, (float) $lng, $cities)) {
        return null;
    }

    $trainNo = (string) ($row['TrainNo'] ?? 'TRA');
    $stationName = $row['StationName']['Zh_tw'] ?? $station['StationName']['Zh_tw'] ?? $stationId;
    $direction = isset($row['Direction']) ? ((int) $row['Direction'] === 0 ? '順行' : '逆行') : '方向未提供';
    $delay = (int) ($row['DelayTime'] ?? 0);

    return [
        'id' => 'tdx-rail-tra-' . md5($trainNo . '-' . $stationId),
        'kind' => 'rail',
        'route' => '台鐵 ' . $trainNo,
        'headsign' => $direction . ' / ' . $stationName,
        'plate' => $trainNo,
        'operator' => 'TRA',
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'speed' => 0,
        'bearing' => (float) ($row['Direction'] ?? 0) * 180,
        'status' => $delay > 0 ? '延誤 ' . $delay . ' 分' : '準點',
        'updated_at' => (string) ($row['UpdateTime'] ?? gmdate('c')),
        'note' => 'TDX TRA live train delay at station',
        'rail_system' => 'TRA',
    ];
}

function normalizeMetroRail(array $row, array $stationsById, string $system): ?array
{
    $stationId = (string) ($row['StationID'] ?? '');
    if ($stationId === '' || !isset($stationsById[$stationId])) {
        return null;
    }

    $station = $stationsById[$stationId];
    $lat = $station['StationPosition']['PositionLat'] ?? null;
    $lng = $station['StationPosition']['PositionLon'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }

    $stationName = $row['StationName']['Zh_tw'] ?? $station['StationName']['Zh_tw'] ?? $stationId;
    $line = (string) ($row['LineID'] ?? $row['RouteID'] ?? $system);
    $destination = $row['DestinationStationName']['Zh_tw'] ?? $row['TripHeadSign'] ?? $row['DestinationStationID'] ?? '';
    $estimateSeconds = $row['EstimateTime'] ?? null;
    $estimateText = is_numeric($estimateSeconds) ? '約 ' . max(0, (int) ceil(((int) $estimateSeconds) / 60)) . ' 分' : '到站時間未提供';

    return [
        'id' => 'tdx-rail-metro-' . md5($system . '-' . $stationId . '-' . $line . '-' . $destination),
        'kind' => 'rail',
        'route' => $system . ' ' . $line,
        'headsign' => (string) ($destination ?: $stationName),
        'plate' => $stationId,
        'operator' => $system,
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'speed' => 0,
        'bearing' => (float) ($row['Direction'] ?? 0) * 180,
        'status' => $estimateText,
        'updated_at' => (string) ($row['SrcUpdateTime'] ?? $row['UpdateTime'] ?? gmdate('c')),
        'note' => 'TDX metro live board at station',
        'rail_system' => 'Metro',
    ];
}

function normalizeThsrRail(array $row, array $stationsById, array $cities): ?array
{
    $stationId = (string) ($row['StationID'] ?? '');
    if ($stationId === '' || !isset($stationsById[$stationId])) {
        return null;
    }

    $station = $stationsById[$stationId];
    $lat = $station['StationPosition']['PositionLat'] ?? null;
    $lng = $station['StationPosition']['PositionLon'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng) || !withinAnyCityBounds((float) $lat, (float) $lng, $cities)) {
        return null;
    }

    $stationName = $row['StationName']['Zh_tw'] ?? $station['StationName']['Zh_tw'] ?? $stationId;
    $trainNo = (string) ($row['TrainNo'] ?? $row['TrainNumber'] ?? 'THSR');
    $destination = $row['DestinationStationName']['Zh_tw'] ?? $row['TripHeadSign'] ?? $row['DestinationStationID'] ?? '';
    $estimateSeconds = $row['EstimateTime'] ?? null;
    $delay = (int) ($row['DelayTime'] ?? 0);
    $status = is_numeric($estimateSeconds)
        ? '約 ' . max(0, (int) ceil(((int) $estimateSeconds) / 60)) . ' 分'
        : ($delay > 0 ? '延誤 ' . $delay . ' 分' : '到站時間未提供');

    return [
        'id' => 'tdx-rail-thsr-' . md5($trainNo . '-' . $stationId . '-' . $destination),
        'kind' => 'rail',
        'route' => '高鐵 ' . $trainNo,
        'headsign' => (string) (($destination !== '' ? $destination : '目的地未提供') . ' / ' . $stationName),
        'plate' => $trainNo,
        'operator' => 'THSR',
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'speed' => 0,
        'bearing' => (float) ($row['Direction'] ?? 0) * 180,
        'status' => $status,
        'updated_at' => (string) ($row['SrcUpdateTime'] ?? $row['UpdateTime'] ?? gmdate('c')),
        'note' => 'TDX THSR live board at station',
        'rail_system' => 'THSR',
    ];
}

function normalizeThsrStation(array $station, array $cities): ?array
{
    $lat = $station['StationPosition']['PositionLat'] ?? null;
    $lng = $station['StationPosition']['PositionLon'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng) || !withinAnyCityBounds((float) $lat, (float) $lng, $cities)) {
        return null;
    }

    $stationId = (string) ($station['StationID'] ?? $station['StationUID'] ?? md5(json_encode($station)));
    $stationName = $station['StationName']['Zh_tw'] ?? $station['StationName']['En'] ?? $stationId;

    return [
        'id' => 'tdx-rail-thsr-station-' . $stationId,
        'kind' => 'rail',
        'route' => '高鐵 ' . $stationName,
        'headsign' => (string) $stationName,
        'plate' => $stationId,
        'operator' => 'THSR',
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'speed' => 0,
        'bearing' => 0,
        'status' => '站點資訊',
        'updated_at' => (string) ($station['UpdateTime'] ?? gmdate('c')),
        'note' => 'TDX THSR station information',
        'rail_system' => 'THSR',
    ];
}

function todayTaipeiDate(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei')))->format('Y-m-d');
}

function timetableSeconds(string $time, int $dayOffset = 0): ?int
{
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($time), $matches)) {
        return null;
    }

    return ($dayOffset * 86400) + ((int) $matches[1] * 3600) + ((int) $matches[2] * 60) + (int) ($matches[3] ?? 0);
}

function stopEventSeconds(array $stop, string $field, int $dayOffset): ?int
{
    $time = (string) ($stop[$field] ?? '');
    if ($time === '') {
        return null;
    }

    return timetableSeconds($time, $dayOffset);
}

function currentTaipeiSeconds(): int
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Taipei'));
    return ((int) $now->format('H') * 3600) + ((int) $now->format('i') * 60) + (int) $now->format('s');
}

function taipeiServiceDayStartTimestamp(): int
{
    return (new DateTimeImmutable('today', new DateTimeZone('Asia/Taipei')))->getTimestamp();
}

function interpolatePosition(array $fromStation, array $toStation, float $ratio): ?array
{
    $fromLat = $fromStation['StationPosition']['PositionLat'] ?? null;
    $fromLng = $fromStation['StationPosition']['PositionLon'] ?? null;
    $toLat = $toStation['StationPosition']['PositionLat'] ?? null;
    $toLng = $toStation['StationPosition']['PositionLon'] ?? null;
    if (!is_numeric($fromLat) || !is_numeric($fromLng) || !is_numeric($toLat) || !is_numeric($toLng)) {
        return null;
    }

    $ratio = max(0, min(1, $ratio));
    return [
        (float) $fromLat + (((float) $toLat - (float) $fromLat) * $ratio),
        (float) $fromLng + (((float) $toLng - (float) $fromLng) * $ratio),
    ];
}

function bearingBetween(float $fromLat, float $fromLng, float $toLat, float $toLng): float
{
    $lat1 = deg2rad($fromLat);
    $lat2 = deg2rad($toLat);
    $deltaLng = deg2rad($toLng - $fromLng);
    $y = sin($deltaLng) * cos($lat2);
    $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLng);
    return fmod(rad2deg(atan2($y, $x)) + 360, 360);
}

function railDirectionLabelFromBearing(float $bearing): string
{
    $bearing = fmod($bearing + 360, 360);
    if ($bearing >= 315 || $bearing < 45) {
        return '北上';
    }
    if ($bearing >= 135 && $bearing < 225) {
        return '南下';
    }
    if ($bearing >= 45 && $bearing < 135) {
        return '東行';
    }
    return '西行';
}

function distanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
{
    if (!is_finite($fromLat) || !is_finite($fromLng) || !is_finite($toLat) || !is_finite($toLng)) {
        return INF;
    }
    $earth = 6371000;
    $dLat = deg2rad($toLat - $fromLat);
    $dLng = deg2rad($toLng - $fromLng);
    $lat1 = deg2rad($fromLat);
    $lat2 = deg2rad($toLat);
    $a = min(1, max(0, sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * (sin($dLng / 2) ** 2)));
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
}

function parseLineStringGeometry(string $geometry): array
{
    if (!preg_match('/LINESTRING\s*\((.+)\)/i', $geometry, $matches)) {
        return [];
    }

    $points = [];
    foreach (explode(',', $matches[1]) as $pair) {
        $parts = preg_split('/\s+/', trim($pair));
        if (count($parts) < 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            continue;
        }
        $lat = (float) $parts[1];
        $lng = (float) $parts[0];
        if (!is_finite($lat) || !is_finite($lng)) {
            continue;
        }
        $points[] = [$lat, $lng];
    }

    return $points;
}

function normalizeRailShapes(array $rows): array
{
    $shapes = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $points = parseLineStringGeometry((string) ($row['Geometry'] ?? ''));
        if (count($points) < 2) {
            continue;
        }
        $lineId = (string) ($row['LineID'] ?? $row['LineNo'] ?? count($shapes));
        $part = [];
        foreach ($points as $point) {
            if (count($part) > 0) {
                $previous = $part[count($part) - 1];
                if (distanceMeters($previous[0], $previous[1], $point[0], $point[1]) > 2500) {
                    if (count($part) >= 2) {
                        $shapes[] = [
                            'id' => $lineId . '-' . count($shapes),
                            'points' => simplifyPathPoints($part, 1800),
                        ];
                    }
                    $part = [];
                }
            }
            $part[] = $point;
        }
        if (count($part) >= 2) {
            $shapes[] = [
                'id' => $lineId . '-' . count($shapes),
                'points' => simplifyPathPoints($part, 1800),
            ];
        }
    }
    return $shapes;
}

function nearestShapeIndex(array $points, float $lat, float $lng): array
{
    $bestIndex = 0;
    $bestDistance = INF;
    foreach ($points as $index => $point) {
        $distance = distanceMeters($lat, $lng, (float) $point[0], (float) $point[1]);
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $bestIndex = (int) $index;
        }
    }
    return [$bestIndex, $bestDistance];
}

function simplifyPathPoints(array $points, int $maxPoints = 80): array
{
    $count = count($points);
    if ($count <= $maxPoints) {
        return $points;
    }

    $step = max(1, (int) ceil(($count - 1) / ($maxPoints - 1)));
    $result = [];
    for ($index = 0; $index < $count; $index += $step) {
        $result[] = $points[$index];
    }
    if (end($result) !== $points[$count - 1]) {
        $result[] = $points[$count - 1];
    }
    return $result;
}

function railPathBetweenStations(array $shapes, float $fromLat, float $fromLng, float $toLat, float $toLng): array
{
    $best = null;
    $straightDistance = distanceMeters($fromLat, $fromLng, $toLat, $toLng);
    foreach ($shapes as $shape) {
        $points = $shape['points'] ?? [];
        if (!is_array($points) || count($points) < 2) {
            continue;
        }
        [$fromIndex, $fromDistance] = nearestShapeIndex($points, $fromLat, $fromLng);
        [$toIndex, $toDistance] = nearestShapeIndex($points, $toLat, $toLng);
        if ($fromIndex === $toIndex) {
            continue;
        }
        if ($fromDistance > 3000 || $toDistance > 3000) {
            continue;
        }

        $slice = $fromIndex < $toIndex
            ? array_slice($points, $fromIndex, $toIndex - $fromIndex + 1)
            : array_reverse(array_slice($points, $toIndex, $fromIndex - $toIndex + 1));
        array_unshift($slice, [$fromLat, $fromLng]);
        $slice[] = [$toLat, $toLng];

        $pathDistance = 0.0;
        for ($index = 0; $index < count($slice) - 1; $index++) {
            $segmentDistance = distanceMeters($slice[$index][0], $slice[$index][1], $slice[$index + 1][0], $slice[$index + 1][1]);
            if (!is_finite($segmentDistance)) {
                $pathDistance = INF;
                break;
            }
            $pathDistance += $segmentDistance;
        }
        if (!is_finite($pathDistance)) {
            continue;
        }
        if ($straightDistance > 0 && $pathDistance > max(8000, $straightDistance * 3.5)) {
            continue;
        }

        $score = ($fromDistance * 4) + ($toDistance * 4) + $pathDistance + (abs($toIndex - $fromIndex) < 2 ? 100000 : 0);
        if ($best === null || $score < $best['score']) {
            $best = [
                'score' => $score,
                'path' => $slice,
            ];
        }
    }

    if ($best === null) {
        return [];
    }

    return simplifyPathPoints($best['path']);
}

function railPathBetweenStationsCached(string $cacheDir, string $system, string $fromId, string $toId, array $shapes, float $fromLat, float $fromLng, float $toLat, float $toLng): array
{
    $cacheFile = $cacheDir . '/rail-path-v3-' . preg_replace('/[^A-Za-z0-9_-]/', '-', strtolower($system . '-' . $fromId . '-' . $toId)) . '.json';
    $cached = readCache($cacheFile, 86400);
    if ($cached !== null && isset($cached['path']) && is_array($cached['path'])) {
        return $cached['path'];
    }

    $path = railPathBetweenStations($shapes, $fromLat, $fromLng, $toLat, $toLng);
    writeCache($cacheDir, $cacheFile, [
        'generated_at' => gmdate('c'),
        'path' => $path,
    ]);
    return $path;
}

function interpolatePathPosition(array $path, float $ratio): ?array
{
    if (count($path) < 2) {
        return null;
    }
    $ratio = max(0, min(1, $ratio));
    $segments = [];
    $total = 0.0;
    for ($index = 0; $index < count($path) - 1; $index++) {
        $distance = distanceMeters($path[$index][0], $path[$index][1], $path[$index + 1][0], $path[$index + 1][1]);
        $segments[] = $distance;
        $total += $distance;
    }
    if ($total <= 0) {
        return $path[0];
    }
    $target = $total * $ratio;
    $walked = 0.0;
    foreach ($segments as $index => $distance) {
        if ($walked + $distance >= $target) {
            $local = $distance > 0 ? (($target - $walked) / $distance) : 0;
            return [
                $path[$index][0] + (($path[$index + 1][0] - $path[$index][0]) * $local),
                $path[$index][1] + (($path[$index + 1][1] - $path[$index][1]) * $local),
            ];
        }
        $walked += $distance;
    }
    return $path[count($path) - 1];
}

function normalizeEstimatedRail(array $row, array $stationsById, array $cities, string $system, int $nowSeconds, array $shapes = [], string $cacheDir = ''): ?array
{
    $stopTimes = $row['StopTimes'] ?? [];
    if (!is_array($stopTimes) || count($stopTimes) < 2) {
        return null;
    }

    $previousDeparture = null;
    $dayOffset = 0;
    $trainInfo = is_array($row['DailyTrainInfo'] ?? null) ? $row['DailyTrainInfo'] : [];
    $trainNo = (string) ($trainInfo['TrainNo'] ?? '');
    if ($trainNo === '') {
        return null;
    }

    for ($index = 0; $index < count($stopTimes) - 1; $index++) {
        $fromStop = is_array($stopTimes[$index]) ? $stopTimes[$index] : [];
        $toStop = is_array($stopTimes[$index + 1]) ? $stopTimes[$index + 1] : [];
        $fromId = (string) ($fromStop['StationID'] ?? '');
        $toId = (string) ($toStop['StationID'] ?? '');
        if ($fromId === '' || $toId === '' || !isset($stationsById[$fromId], $stationsById[$toId])) {
            continue;
        }

        $departure = stopEventSeconds($fromStop, 'DepartureTime', $dayOffset)
            ?? stopEventSeconds($fromStop, 'ArrivalTime', $dayOffset);
        if ($departure === null) {
            continue;
        }
        if ($previousDeparture !== null && $departure < $previousDeparture) {
            $dayOffset++;
            $departure = stopEventSeconds($fromStop, 'DepartureTime', $dayOffset)
                ?? stopEventSeconds($fromStop, 'ArrivalTime', $dayOffset);
        }
        $arrival = stopEventSeconds($toStop, 'ArrivalTime', $dayOffset)
            ?? stopEventSeconds($toStop, 'DepartureTime', $dayOffset);
        if ($arrival !== null && $arrival < $departure) {
            $arrival += 86400;
        }
        $previousDeparture = $departure;
        if ($arrival === null || $arrival <= $departure) {
            continue;
        }

        $candidateNow = $nowSeconds;
        if ($candidateNow < $departure - 43200) {
            $candidateNow += 86400;
        }
        if ($candidateNow < $departure || $candidateNow > $arrival) {
            continue;
        }

        $fromStation = $stationsById[$fromId];
        $toStation = $stationsById[$toId];
        $ratio = ($candidateNow - $departure) / ($arrival - $departure);
        $position = interpolatePosition($fromStation, $toStation, $ratio);
        if ($position === null || !withinAnyCityBounds($position[0], $position[1], $cities)) {
            return null;
        }

        $fromName = $fromStop['StationName']['Zh_tw'] ?? $fromStation['StationName']['Zh_tw'] ?? $fromId;
        $toName = $toStop['StationName']['Zh_tw'] ?? $toStation['StationName']['Zh_tw'] ?? $toId;
        $typeName = $trainInfo['TrainTypeName']['Zh_tw'] ?? '';
        $prefix = $system === 'THSR' ? '高鐵' : '台鐵';
        $fromLat = (float) ($fromStation['StationPosition']['PositionLat'] ?? $position[0]);
        $fromLng = (float) ($fromStation['StationPosition']['PositionLon'] ?? $position[1]);
        $toLat = (float) ($toStation['StationPosition']['PositionLat'] ?? $position[0]);
        $toLng = (float) ($toStation['StationPosition']['PositionLon'] ?? $position[1]);
        $path = $cacheDir !== ''
            ? railPathBetweenStationsCached($cacheDir, $system, $fromId, $toId, $shapes, $fromLat, $fromLng, $toLat, $toLng)
            : railPathBetweenStations($shapes, $fromLat, $fromLng, $toLat, $toLng);
        if (count($path) >= 2) {
            $pathPosition = interpolatePathPosition($path, $ratio);
            if ($pathPosition !== null) {
                $position = $pathPosition;
            }
        }

        $bearing = bearingBetween($fromLat, $fromLng, $toLat, $toLng);

        return [
            'id' => 'tdx-rail-est-' . strtolower($system) . '-' . $trainNo,
            'kind' => 'rail',
            'route' => $prefix . ' ' . $trainNo,
            'headsign' => $fromName . ' → ' . $toName,
            'plate' => $trainNo,
            'operator' => $system,
            'lat' => $position[0],
            'lng' => $position[1],
            'speed' => 0,
            'bearing' => $bearing,
            'status' => '推估行駛中' . ($typeName !== '' ? ' / ' . $typeName : ''),
            'updated_at' => gmdate('c'),
            'note' => $prefix . '位置依今日時刻表與站點座標推估，非 GPS',
            'rail_system' => $system,
            'direction_label' => railDirectionLabelFromBearing($bearing),
            'estimated' => true,
            'estimate_type' => 'timetable',
            'estimate_from_lat' => $fromLat,
            'estimate_from_lng' => $fromLng,
            'estimate_to_lat' => $toLat,
            'estimate_to_lng' => $toLng,
            'estimate_start_ms' => (taipeiServiceDayStartTimestamp() + $departure) * 1000,
            'estimate_end_ms' => (taipeiServiceDayStartTimestamp() + $arrival) * 1000,
            'estimate_path' => $path,
            'estimate_path_source' => count($path) >= 2 ? 'tdx-shape' : 'station-line',
        ];
    }

    return null;
}

function fallbackThsrStations(array $cities): array
{
    $stations = [
        ['city' => 'Taipei', 'id' => '0990', 'name' => '南港', 'lat' => 25.05225, 'lng' => 121.60650],
        ['city' => 'Taipei', 'id' => '1000', 'name' => '台北', 'lat' => 25.04780, 'lng' => 121.51700],
        ['city' => 'NewTaipei', 'id' => '1010', 'name' => '板橋', 'lat' => 25.01430, 'lng' => 121.46390],
        ['city' => 'Taoyuan', 'id' => '1020', 'name' => '桃園', 'lat' => 25.01380, 'lng' => 121.21470],
        ['city' => 'Taichung', 'id' => '1080', 'name' => '台中', 'lat' => 24.11230, 'lng' => 120.61590],
        ['city' => 'Tainan', 'id' => '1160', 'name' => '台南', 'lat' => 22.92490, 'lng' => 120.28570],
        ['city' => 'Kaohsiung', 'id' => '1070', 'name' => '左營', 'lat' => 22.68740, 'lng' => 120.30750],
    ];

    return array_values(array_filter($stations, static fn (array $station): bool => in_array($station['city'], $cities, true)));
}

function normalizeFallbackThsrStation(array $station): array
{
    return [
        'id' => 'tdx-rail-thsr-fallback-' . $station['id'],
        'kind' => 'rail',
        'route' => '高鐵 ' . $station['name'],
        'headsign' => $station['name'],
        'plate' => $station['id'],
        'operator' => 'THSR',
        'lat' => (float) $station['lat'],
        'lng' => (float) $station['lng'],
        'speed' => 0,
        'bearing' => 0,
        'status' => '站點資訊',
        'updated_at' => gmdate('c'),
        'note' => 'THSR station fallback while TDX is rate limited',
        'rail_system' => 'THSR',
    ];
}

function firstString(array $row, array $keys): string
{
    foreach ($keys as $key) {
        $value = $row[$key] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return '';
}

function normalizeCctv(array $row, string $scope): ?array
{
    $position = $row['CCTVPosition'] ?? $row['Position'] ?? $row['CameraPosition'] ?? $row['Location'] ?? [];
    $lat = $position['PositionLat'] ?? $position['Latitude'] ?? $row['PositionLat'] ?? $row['Latitude'] ?? $row['Lat'] ?? null;
    $lng = $position['PositionLon'] ?? $position['PositionLng'] ?? $position['Longitude'] ?? $row['PositionLon'] ?? $row['PositionLng'] ?? $row['Longitude'] ?? $row['Lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }

    $name = firstString($row, ['CCTVName', 'CCTVID', 'RoadName', 'LocationDescription', 'LocationName', 'Name']);
    $id = firstString($row, ['CCTVID', 'ID', 'CameraID']) ?: md5(json_encode($row));
    $road = firstString($row, ['RoadName', 'RoadID', 'RoadSection', 'LocationDescription']);
    $streamUrl = firstString($row, ['VideoStreamURL', 'VideoURL', 'StreamURL', 'LiveURL', 'URL', 'CCTVURL']);
    $imageUrl = firstString($row, ['ImageURL', 'ImageUrl', 'SnapshotURL', 'SnapshotUrl']);

    return [
        'id' => 'tdx-cctv-' . md5($scope . '-' . $id),
        'kind' => 'cctv',
        'route' => $name ?: '即時監視器',
        'headsign' => $road ?: $scope,
        'plate' => $id,
        'operator' => 'TDX CCTV',
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'speed' => 0,
        'bearing' => 0,
        'status' => $streamUrl !== '' || $imageUrl !== '' ? '即時影像' : '僅提供位置',
        'updated_at' => (string) ($row['UpdateTime'] ?? $row['_parentUpdateTime'] ?? $row['SrcUpdateTime'] ?? $row['_parentSrcUpdateTime'] ?? gmdate('c')),
        'note' => 'TDX CCTV',
        'stream_url' => $streamUrl,
        'image_url' => $imageUrl,
    ];
}

function flattenCctvRows(array $rows): array
{
    $items = [];
    if (isset($rows['CCTVs']) && is_array($rows['CCTVs'])) {
        $parentUpdateTime = $rows['UpdateTime'] ?? null;
        $parentSrcUpdateTime = $rows['SrcUpdateTime'] ?? null;
        foreach ($rows['CCTVs'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $item['_parentUpdateTime'] = $parentUpdateTime;
            $item['_parentSrcUpdateTime'] = $parentSrcUpdateTime;
            $items[] = $item;
        }
        return $items;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (isset($row['CCTVs']) && is_array($row['CCTVs'])) {
            foreach ($row['CCTVs'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $item['_parentUpdateTime'] = $row['UpdateTime'] ?? null;
                $item['_parentSrcUpdateTime'] = $row['SrcUpdateTime'] ?? null;
                $items[] = $item;
            }
            continue;
        }

        $items[] = $row;
    }

    return $items;
}

function fetchFirstAvailableTdxJson(array $paths, string &$token, string $clientId, string $clientSecret, string $cacheDir): array
{
    $lastError = null;
    foreach ($paths as $path) {
        try {
            return fetchTdxJsonWithTokenRefresh($path, $clientId, $clientSecret, $cacheDir, $token);
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    throw $lastError ?: new RuntimeException('No CCTV endpoint is available.');
}

if ($clientId === '' || $clientSecret === '') {
    respond(samplePayload($sampleFile, '尚未設定 TDX_CLIENT_ID / TDX_CLIENT_SECRET，目前顯示範例資料。'));
}

if (!function_exists('curl_init')) {
    respond(samplePayload($sampleFile, 'PHP curl extension 尚未啟用，目前顯示範例資料。'));
}

try {
    $cached = isset($_GET['debug_cctv']) ? null : readCache($cacheFile, $cacheTtlSeconds);
    if ($cached !== null) {
        $cached['notice'] = ($cached['notice'] ?? '資料已更新') . '（快取）';
        respond($cached);
    }

    try {
        $token = getTdxTokenCached($clientId, $clientSecret, $cacheDir);
    } catch (Throwable $e) {
        $fallback = fallbackTransitCache($cacheDir, $requestedSources, $requestedCities, $e->getMessage());
        if ($fallback !== null) {
            respond($fallback);
        }
        throw $e;
    }
    $vehicles = [];
    $warnings = [];
    $stationCacheTtlSeconds = 86400;
    $includeBus = in_array('bus', $requestedSources, true);
    $includeBike = in_array('bike', $requestedSources, true);
    $includeRailTra = in_array('rail-tra', $requestedSources, true);
    $includeRailMetro = in_array('rail-metro', $requestedSources, true);
    $includeRailThsr = in_array('rail-thsr', $requestedSources, true);
    $includeRail = $includeRailTra || $includeRailMetro || $includeRailThsr;
    $includeCctv = in_array('cctv', $requestedSources, true);

    if ($includeBus) {
        foreach ($requestedCities as $targetCity) {
            try {
                $rows = fetchTdxJsonCached(
                    'Bus/RealTimeByFrequency/City/' . rawurlencode($targetCity),
                    $token,
                    $cacheDir,
                    'bus-realtime-' . $targetCity,
                    $dynamicCacheTtlSeconds,
                );
                foreach ($rows as $index => $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $vehicle = normalizeTdxBus($row, (int) $index);
                    if ($vehicle !== null) {
                        $vehicles[] = $vehicle;
                    }
                }
            } catch (Throwable $e) {
                $warnings[] = friendlyWarning($targetCity . ' 公車', $e);
            }
        }
    }

    if ($includeBike) {
        foreach ($requestedCities as $targetCity) {
            try {
                $stations = fetchTdxJsonCached('Bike/Station/City/' . rawurlencode($targetCity), $token, $cacheDir, 'bike-station-' . $targetCity, $stationCacheTtlSeconds);
                $availabilityRows = fetchTdxJsonCached(
                    'Bike/Availability/City/' . rawurlencode($targetCity),
                    $token,
                    $cacheDir,
                    'bike-availability-' . $targetCity,
                    $dynamicCacheTtlSeconds,
                );
                $availabilityByUid = [];
                foreach ($availabilityRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $uid = (string) ($row['StationUID'] ?? $row['StationID'] ?? '');
                    if ($uid !== '') {
                        $availabilityByUid[$uid] = $row;
                    }
                }

                foreach ($stations as $station) {
                    if (!is_array($station)) {
                        continue;
                    }
                    $uid = (string) ($station['StationUID'] ?? $station['StationID'] ?? '');
                    $vehicle = normalizeTdxBike($station, $availabilityByUid[$uid] ?? null);
                    if ($vehicle !== null) {
                        $vehicles[] = $vehicle;
                    }
                }
            } catch (Throwable $e) {
                $warnings[] = friendlyWarning($targetCity . ' 單車', $e);
            }
        }
    }

    if ($includeCctv) {
        foreach ($requestedCities as $targetCity) {
            $cctvThrottleFile = throttleFile($cacheDir, 'cctv-' . $targetCity);
            if (shouldSkipAfterRateLimit($cctvThrottleFile, 3600)) {
                $warnings[] = $targetCity . ' 監視器限流冷卻中，稍後自動再試';
                continue;
            }

            try {
                $cctvCacheFile = $cacheDir . '/tdx-cctv-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $targetCity) . '.json';
                $cctvCached = readCache($cctvCacheFile, 300);
                if ($cctvCached !== null && isset($cctvCached['data']) && is_array($cctvCached['data'])) {
                    $rows = $cctvCached['data'];
                } else {
                    $rows = fetchFirstAvailableTdxJson([
                        'Road/Traffic/CCTV/City/' . rawurlencode($targetCity),
                        'Road/CCTV/City/' . rawurlencode($targetCity),
                    ], $token, $clientId, $clientSecret, $cacheDir);
                    writeCache($cacheDir, $cctvCacheFile, [
                        'generated_at' => gmdate('c'),
                        'data' => $rows,
                    ]);
                }
                $flatCctvRows = flattenCctvRows($rows);
                $normalizedCctvCount = 0;
                foreach ($flatCctvRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $vehicle = normalizeCctv($row, $targetCity);
                    if ($vehicle !== null) {
                        $vehicles[] = $vehicle;
                        $normalizedCctvCount++;
                    }
                }
                if (isset($_GET['debug_cctv'])) {
                    $warnings[] = 'CCTV debug ' . $targetCity . ': raw=' . count($rows) . ', flat=' . count($flatCctvRows) . ', normalized=' . $normalizedCctvCount;
                }
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'rate limit') !== false || stripos($e->getMessage(), '429') !== false) {
                    markRateLimit($cacheDir, $cctvThrottleFile, 3600, $e->getMessage());
                }
                $warnings[] = isset($_GET['debug_cctv'])
                    ? $targetCity . ' 監視器錯誤：' . $e->getMessage()
                    : friendlyWarning($targetCity . ' 監視器', $e);
            }
        }
    }

    if ($includeRailMetro) {
        $metroSystemGroups = array_filter(array_map('cityMetroSystems', $requestedCities));
        $metroSystems = count($metroSystemGroups) > 0
            ? array_values(array_unique(array_merge(...$metroSystemGroups)))
            : [];
        foreach ($metroSystems as $system) {
            try {
                $metroStations = fetchTdxJsonCached('Rail/Metro/Station/' . rawurlencode($system), $token, $cacheDir, 'metro-station-' . $system, $stationCacheTtlSeconds);
                $metroStationsById = [];
                foreach ($metroStations as $station) {
                    if (!is_array($station)) {
                        continue;
                    }
                    $stationId = (string) ($station['StationID'] ?? '');
                    if ($stationId !== '') {
                        $metroStationsById[$stationId] = $station;
                    }
                }

                $metroRows = fetchTdxJsonCached(
                    'Rail/Metro/LiveBoard/' . rawurlencode($system),
                    $token,
                    $cacheDir,
                    'metro-liveboard-' . $system,
                    $dynamicCacheTtlSeconds,
                );
                foreach ($metroRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $vehicle = normalizeMetroRail($row, $metroStationsById, $system);
                    if ($vehicle !== null) {
                        $vehicles[] = $vehicle;
                    }
                }
            } catch (Throwable $e) {
                $warnings[] = friendlyWarning($system . ' 捷運', $e);
            }
        }
    }

    if ($includeRailTra) {
        try {
            $stations = fetchTdxJsonCached('Rail/TRA/Station', $token, $cacheDir, 'tra-station', $stationCacheTtlSeconds);
            $stationsById = [];
            foreach ($stations as $station) {
                if (!is_array($station)) {
                    continue;
                }
                $stationId = (string) ($station['StationID'] ?? '');
                if ($stationId !== '') {
                    $stationsById[$stationId] = $station;
                }
            }

            $timetables = fetchTdxJsonCached('Rail/TRA/DailyTimetable/Today', $token, $cacheDir, 'tra-timetable-' . todayTaipeiDate(), 300);
            $shapes = normalizeRailShapes(fetchTdxJsonCached('Rail/TRA/Shape', $token, $cacheDir, 'tra-shape', $stationCacheTtlSeconds));
            $nowSeconds = currentTaipeiSeconds();
            foreach ($timetables as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $vehicle = normalizeEstimatedRail($row, $stationsById, $requestedCities, 'TRA', $nowSeconds, $shapes, $cacheDir);
                if ($vehicle !== null) {
                    $vehicles[] = $vehicle;
                }
            }
        } catch (Throwable $e) {
            $warnings[] = friendlyWarning('台鐵推估列車', $e);
        }

        $traThrottleFile = throttleFile($cacheDir, 'tra');
        if (shouldSkipAfterRateLimit($traThrottleFile, $rateLimitBackoffSeconds)) {
            $warnings[] = '台鐵即時看板限流冷卻中，已保留時刻表推估';
        } else {
            try {
                $stations = fetchTdxJsonCached('Rail/TRA/Station', $token, $cacheDir, 'tra-station', $stationCacheTtlSeconds);
                $stationsById = [];
                foreach ($stations as $station) {
                    if (!is_array($station)) {
                        continue;
                    }
                    $stationId = (string) ($station['StationID'] ?? '');
                    if ($stationId !== '') {
                        $stationsById[$stationId] = $station;
                    }
                }

                $railRows = fetchTdxJsonCached(
                    'Rail/TRA/LiveTrainDelay',
                    $token,
                    $cacheDir,
                    'tra-live-delay',
                    $dynamicCacheTtlSeconds,
                );
                $delayByTrainNo = [];
                foreach ($railRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $trainNo = (string) ($row['TrainNo'] ?? '');
                    $delay = (int) ($row['DelayTime'] ?? 0);
                    if ($trainNo !== '' && $delay > 0) {
                        $delayByTrainNo[$trainNo] = max($delayByTrainNo[$trainNo] ?? 0, $delay);
                    }
                }
                if ($delayByTrainNo !== []) {
                    foreach ($vehicles as &$existingVehicle) {
                        if (($existingVehicle['kind'] ?? '') !== 'rail' || ($existingVehicle['operator'] ?? '') !== 'TRA') {
                            continue;
                        }
                        $trainNo = (string) ($existingVehicle['plate'] ?? '');
                        if ($trainNo === '' || !isset($delayByTrainNo[$trainNo])) {
                            continue;
                        }
                        $delay = $delayByTrainNo[$trainNo];
                        $existingVehicle['delay_minutes'] = $delay;
                        $existingVehicle['status'] = '誤點 ' . $delay . ' 分' . (!empty($existingVehicle['estimated']) ? ' / 時刻表推估' : '');
                    }
                    unset($existingVehicle);
                }
                foreach ($railRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $vehicle = normalizeTraRail($row, $stationsById, $requestedCities);
                    if ($vehicle !== null) {
                        $vehicles[] = $vehicle;
                    }
                }
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'rate limit') !== false || stripos($e->getMessage(), '429') !== false) {
                    markRateLimit($cacheDir, $traThrottleFile, $rateLimitBackoffSeconds, $e->getMessage());
                }
                $warnings[] = friendlyWarning('台鐵', $e);
            }
        }
    }

    if ($includeRailThsr) {
        $thsrThrottleFile = throttleFile($cacheDir, 'thsr');
        $thsrEstimatedCount = 0;
        if (!shouldSkipAfterRateLimit($thsrThrottleFile, $rateLimitBackoffSeconds)) {
            try {
                $stations = fetchTdxJsonCached('Rail/THSR/Station', $token, $cacheDir, 'thsr-station', $stationCacheTtlSeconds);
                $stationsById = [];
                foreach ($stations as $station) {
                    if (!is_array($station)) {
                        continue;
                    }
                    $stationId = (string) ($station['StationID'] ?? '');
                    if ($stationId !== '') {
                        $stationsById[$stationId] = $station;
                    }
                }

                $timetables = fetchTdxJsonCached('Rail/THSR/DailyTimetable/Today', $token, $cacheDir, 'thsr-timetable-' . todayTaipeiDate(), 300);
                $shapes = normalizeRailShapes(fetchTdxJsonCached('Rail/THSR/Shape', $token, $cacheDir, 'thsr-shape', $stationCacheTtlSeconds));
                $nowSeconds = currentTaipeiSeconds();
                foreach ($timetables as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $vehicle = normalizeEstimatedRail($row, $stationsById, $requestedCities, 'THSR', $nowSeconds, $shapes, $cacheDir);
                    if ($vehicle !== null) {
                        $vehicles[] = $vehicle;
                        $thsrEstimatedCount++;
                    }
                }
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'rate limit') !== false || stripos($e->getMessage(), '429') !== false) {
                    markRateLimit($cacheDir, $thsrThrottleFile, $rateLimitBackoffSeconds, $e->getMessage());
                }
                $warnings[] = friendlyWarning('高鐵推估列車', $e);
            }
        } else {
            $warnings[] = '高鐵限流冷卻中，顯示站點備援資料';
        }

        if ($thsrEstimatedCount === 0) {
            foreach (fallbackThsrStations($requestedCities) as $station) {
                $vehicles[] = normalizeFallbackThsrStation($station);
            }
        }
    }

    $warnings = array_values(array_unique($warnings));
    $sourceParts = array_values(array_filter([
        $includeBus ? 'Bus' : null,
        $includeBike ? 'Bike' : null,
        $includeRailTra ? 'TRA' : null,
        $includeRailMetro ? 'Metro' : null,
        $includeRailThsr ? 'THSR' : null,
        $includeCctv ? 'CCTV' : null,
    ]));
    $sourceLabels = array_values(array_filter([
        $includeBus ? '公車' : null,
        $includeBike ? '單車' : null,
        $includeRailTra ? '台鐵' : null,
        $includeRailMetro ? '捷運' : null,
        $includeRailThsr ? '高鐵' : null,
        $includeCctv ? '監視器' : null,
    ]));
    $notice = count($warnings) > 0
        ? 'TDX 部分資料已更新；' . implode('；', array_slice($warnings, 0, 3)) . (count($warnings) > 3 ? '；另有 ' . (count($warnings) - 3) . ' 項限流或暫時不可用' : '')
        : (count($sourceLabels) > 0 ? 'TDX ' . implode('、', $sourceLabels) . '資料已更新。' : '未選取 TDX 交通資料來源。');

    $payload = [
        'source' => (count($sourceParts) > 0 ? 'TDX ' . implode(' + ', $sourceParts) : 'TDX none') . ' / ' . implode(',', $requestedCities),
        'notice' => $notice,
        'generated_at' => gmdate('c'),
        'vehicles' => $vehicles,
        'warnings' => $warnings,
    ];
    if (count($vehicles) > 0 || count($warnings) === 0) {
        writeCache($cacheDir, $cacheFile, $payload);
    }
    respond($payload);
} catch (Throwable $e) {
    $cached = readCache($cacheFile, 3600);
    if ($cached !== null && !empty($cached['vehicles'])) {
        $cached['notice'] = 'TDX 暫時讀取失敗，顯示 1 小時內快取：' . $e->getMessage();
        respond($cached);
    }
    $fallback = fallbackTransitCache($cacheDir, $requestedSources, $requestedCities, $e->getMessage());
    if ($fallback !== null) {
        respond($fallback);
    }

    respond(samplePayload($sampleFile, 'TDX 讀取失敗，已改用範例資料：' . $e->getMessage()));
}
