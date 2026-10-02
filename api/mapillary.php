<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require dirname(__DIR__) . '/lib/mapillary_config.php';

function mapillaryRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function mapillaryCacheDir(): string
{
    $dir = dirname(__DIR__) . '/cache/mapillary';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function mapillaryCacheKey(float $lat, float $lng, int $radius): string
{
    // ~11m grid to reuse nearby clicks briefly
    $latKey = (int) round($lat * 10000);
    $lngKey = (int) round($lng * 10000);
    return 'mly_' . $latKey . '_' . $lngKey . '_r' . $radius . '.json';
}

function mapillaryCacheGet(string $file): ?array
{
    $path = mapillaryCacheDir() . '/' . $file;
    if (!is_file($path)) {
        return null;
    }
    if (filemtime($path) < time() - 300) {
        return null;
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

function mapillaryCachePut(string $file, array $payload): void
{
    $path = mapillaryCacheDir() . '/' . $file;
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

try {
    $config = normalizeMapillaryConfig(loadMapillaryConfig());
    if (!mapillaryEnabled($config)) {
        mapillaryRespond([
            'ok' => false,
            'message' => 'Mapillary street view is disabled.',
        ], 403);
    }

    $lat = isset($_GET['lat']) ? (float) $_GET['lat'] : NAN;
    $lng = isset($_GET['lng']) ? (float) $_GET['lng'] : NAN;
    if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        mapillaryRespond([
            'ok' => false,
            'message' => 'Invalid coordinates.',
        ], 400);
    }

    $radius = (int) ($config['radius_m'] ?? 50);
    $radius = max(1, min(50, $radius));
    $cacheFile = mapillaryCacheKey($lat, $lng, $radius);
    $cached = mapillaryCacheGet($cacheFile);
    if (is_array($cached)) {
        $cached['cached'] = true;
        mapillaryRespond($cached);
    }

    $query = http_build_query([
        'access_token' => $config['access_token'],
        'lat' => $lat,
        'lng' => $lng,
        'radius' => $radius,
        'limit' => 1,
        'fields' => 'id,geometry,compass_angle,captured_at,thumb_256_url',
    ]);
    $url = 'https://graph.mapillary.com/images?' . $query;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "Accept: application/json\r\nUser-Agent: OpenLiveMap/1.0\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        mapillaryRespond([
            'ok' => false,
            'message' => 'Mapillary request failed.',
        ], 502);
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        mapillaryRespond([
            'ok' => false,
            'message' => 'Mapillary response invalid.',
        ], 502);
    }
    if (isset($json['error'])) {
        mapillaryRespond([
            'ok' => false,
            'message' => (string) ($json['error']['message'] ?? 'Mapillary API error'),
        ], 502);
    }

    $rows = [];
    if (isset($json['data']) && is_array($json['data'])) {
        $rows = $json['data'];
    } elseif (isset($json['features']) && is_array($json['features'])) {
        $rows = $json['features'];
    }

    if ($rows === []) {
        $payload = [
            'ok' => true,
            'id' => null,
            'message' => 'No nearby Mapillary image.',
            'lat' => $lat,
            'lng' => $lng,
            'radius_m' => $radius,
            'cached' => false,
        ];
        mapillaryCachePut($cacheFile, $payload);
        mapillaryRespond($payload);
    }

    $first = $rows[0];
    $id = '';
    $imageLat = null;
    $imageLng = null;
    if (isset($first['id'])) {
        $id = (string) $first['id'];
        $coords = $first['geometry']['coordinates'] ?? null;
        if (is_array($coords) && count($coords) >= 2) {
            $imageLng = (float) $coords[0];
            $imageLat = (float) $coords[1];
        }
    } elseif (isset($first['properties']['id']) || isset($first['properties']['key'])) {
        $id = (string) ($first['properties']['id'] ?? $first['properties']['key']);
        $coords = $first['geometry']['coordinates'] ?? null;
        if (is_array($coords) && count($coords) >= 2) {
            $imageLng = (float) $coords[0];
            $imageLat = (float) $coords[1];
        }
    }

    if ($id === '') {
        $payload = [
            'ok' => true,
            'id' => null,
            'message' => 'No nearby Mapillary image.',
            'lat' => $lat,
            'lng' => $lng,
            'radius_m' => $radius,
            'cached' => false,
        ];
        mapillaryCachePut($cacheFile, $payload);
        mapillaryRespond($payload);
    }

    $payload = [
        'ok' => true,
        'id' => $id,
        'lat' => $imageLat,
        'lng' => $imageLng,
        'query_lat' => $lat,
        'query_lng' => $lng,
        'radius_m' => $radius,
        'compass_angle' => $first['compass_angle'] ?? ($first['properties']['compass_angle'] ?? null),
        'captured_at' => $first['captured_at'] ?? ($first['properties']['captured_at'] ?? null),
        'thumb_256_url' => $first['thumb_256_url'] ?? null,
        'embed_url' => 'https://www.mapillary.com/embed?image_key=' . rawurlencode($id) . '&style=photo',
        'cached' => false,
    ];
    mapillaryCachePut($cacheFile, $payload);
    mapillaryRespond($payload);
} catch (Throwable $e) {
    mapillaryRespond([
        'ok' => false,
        'message' => $e->getMessage(),
    ], 500);
}
