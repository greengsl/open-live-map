<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

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

$authorizationKey = getenv('CWA_AUTHORIZATION_KEY') ?: '';
if (is_file($configFile)) {
    $config = require $configFile;
    if (is_array($config)) {
        $authorizationKey = $authorizationKey ?: (string) ($config['cwa_authorization_key'] ?? '');
    }
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

function fetchJson(string $url, array $query): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is not enabled.');
    }

    $ch = curl_init($url . '?' . http_build_query($query));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status >= 400) {
        throw new RuntimeException($error ?: 'CWA API failed with HTTP ' . $status);
    }

    $json = json_decode((string) $body, true);
    if (!is_array($json)) {
        throw new RuntimeException('CWA API returned invalid JSON.');
    }

    return $json;
}

function firstNumber(array $row, array $keys): ?float
{
    foreach ($keys as $key) {
        $value = $row[$key] ?? null;
        if (is_array($value)) {
            $nested = firstNumber($value, ['value', 'Value', 'StationLatitude', 'StationLongitude']);
            if ($nested !== null) {
                return $nested;
            }
            continue;
        }
        if ($value !== null && $value !== '' && is_numeric($value)) {
            $number = (float) $value;
            if (!isCwaMissingNumber($number)) {
                return $number;
            }
        }
    }

    return null;
}

function isCwaMissingNumber(float $number): bool
{
    return $number <= -90;
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

function weatherElementValue(array $row, array $keys): ?float
{
    $element = $row['WeatherElement'] ?? $row['weatherElement'] ?? [];
    if (!is_array($element)) {
        return null;
    }

    foreach ($keys as $key) {
        $value = $element[$key] ?? null;
        if (is_array($value)) {
            $value = $value['value'] ?? $value['Value'] ?? $value['Precipitation'] ?? null;
        }
        if ($value !== null && $value !== '' && is_numeric($value)) {
            $number = (float) $value;
            if (!isCwaMissingNumber($number)) {
                return $number;
            }
        }
    }

    return null;
}

function weatherElementText(array $row, array $keys): string
{
    $element = $row['WeatherElement'] ?? $row['weatherElement'] ?? [];
    if (!is_array($element)) {
        return '';
    }

    foreach ($keys as $key) {
        $value = $element[$key] ?? null;
        if (is_array($value)) {
            $value = $value['value'] ?? $value['Value'] ?? null;
        }
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return '';
}

function stationCoordinates(array $row): array
{
    $geo = $row['GeoInfo'] ?? $row['geoInfo'] ?? [];
    if (is_array($geo)) {
        $coordinates = $geo['Coordinates'] ?? $geo['coordinates'] ?? [];
        if (is_array($coordinates)) {
            foreach ($coordinates as $coordinate) {
                if (!is_array($coordinate)) {
                    continue;
                }
                $lat = firstNumber($coordinate, ['StationLatitude', 'latitude', 'lat']);
                $lng = firstNumber($coordinate, ['StationLongitude', 'longitude', 'lon', 'lng']);
                if ($lat !== null && $lng !== null) {
                    return [$lat, $lng];
                }
            }
        }
    }

    $lat = firstNumber($row, ['lat', 'latitude', 'StationLatitude']);
    $lng = firstNumber($row, ['lon', 'lng', 'longitude', 'StationLongitude']);
    return [$lat, $lng];
}

function normalizeStation(array $row): ?array
{
    [$lat, $lng] = stationCoordinates($row);
    if ($lat === null || $lng === null) {
        return null;
    }

    $obsTime = $row['ObsTime']['DateTime'] ?? $row['obsTime']['DateTime'] ?? $row['time']['obsTime'] ?? $row['time']['dataTime'] ?? null;
    $stationName = firstString($row, ['StationName', 'stationName', 'locationName', 'LocationName']);
    $stationId = firstString($row, ['StationId', 'StationID', 'stationId', 'locationId']);
    $temperature = weatherElementValue($row, ['AirTemperature', 'TEMP', 'Temperature']);
    $rain = weatherElementValue($row, ['Now', 'Precipitation', 'HOUR_24', 'Rainfall']);
    $humidity = weatherElementValue($row, ['RelativeHumidity', 'HUMD']);
    $windSpeed = weatherElementValue($row, ['WindSpeed', 'WDSD']);
    $weather = weatherElementText($row, ['Weather', 'WeatherDescription']);

    return [
        'id' => $stationId !== '' ? $stationId : md5($stationName . $lat . $lng),
        'name' => $stationName !== '' ? $stationName : '氣象測站',
        'lat' => $lat,
        'lng' => $lng,
        'temperature' => $temperature,
        'rain' => $rain,
        'humidity' => $humidity,
        'wind_speed' => $windSpeed,
        'weather' => $weather,
        'updated_at' => is_string($obsTime) ? $obsTime : null,
    ];
}

function stationRows(array $payload): array
{
    $records = $payload['records'] ?? [];
    if (!is_array($records)) {
        return [];
    }

    if (isset($records['Station']) && is_array($records['Station'])) {
        return $records['Station'];
    }

    if (isset($records['location']) && is_array($records['location'])) {
        return $records['location'];
    }

    return [];
}

try {
    if (isset($_GET['diagnose'])) {
        respond([
            'ok' => true,
            'cwa_authorization_key_set' => $authorizationKey !== '',
            'active_cache_dir' => $cacheDir,
            'active_cache_dir_writable' => is_dir($cacheDir) && is_writable($cacheDir),
            'generated_at' => gmdate('c'),
        ]);
    }

    if ($authorizationKey === '') {
        throw new RuntimeException('CWA authorization key is not configured.');
    }

    $cacheFile = $cacheDir . '/weather-cwa-observation-v2.json';
    $cached = readCache($cacheFile, 600);
    if ($cached !== null) {
        $cached['notice'] = ($cached['notice'] ?? 'CWA 氣象資料已更新') . '（快取）';
        respond($cached);
    }

    $payload = fetchJson('https://opendata.cwa.gov.tw/api/v1/rest/datastore/O-A0003-001', [
        'Authorization' => $authorizationKey,
        'format' => 'JSON',
    ]);

    $stations = [];
    foreach (stationRows($payload) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $station = normalizeStation($row);
        if ($station !== null) {
            $stations[] = $station;
        }
    }

    $result = [
        'source' => 'CWA O-A0003-001',
        'notice' => '中央氣象署即時氣象測站資料已更新。',
        'generated_at' => gmdate('c'),
        'stations' => $stations,
    ];
    writeCache($cacheDir, $cacheFile, $result);
    respond($result);
} catch (Throwable $e) {
    $cacheFile = $cacheDir . '/weather-cwa-observation-v2.json';
    $cached = readCache($cacheFile, 3600);
    if ($cached !== null) {
        $cached['notice'] = 'CWA 暫時讀取失敗，顯示 1 小時內快取。';
        respond($cached);
    }

    http_response_code(502);
    respond([
        'source' => 'CWA O-A0003-001',
        'notice' => 'CWA 氣象資料讀取失敗：' . $e->getMessage(),
        'generated_at' => gmdate('c'),
        'stations' => [],
    ]);
}
