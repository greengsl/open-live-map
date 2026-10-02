<?php
declare(strict_types=1);

require dirname(__DIR__) . '/lib/mapillary_config.php';

function mapillaryTileFail(int $status = 403): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo 'Mapillary coverage unavailable.';
    exit;
}

function mapillaryTileCacheDir(): string
{
    $dir = dirname(__DIR__) . '/cache/mapillary/tiles';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

try {
    $config = normalizeMapillaryConfig(loadMapillaryConfig());
    if (!mapillaryEnabled($config)) {
        mapillaryTileFail(403);
    }

    $z = isset($_GET['z']) ? (int) $_GET['z'] : -1;
    $x = isset($_GET['x']) ? (int) $_GET['x'] : -1;
    $y = isset($_GET['y']) ? (int) $_GET['y'] : -1;
    if ($z < 0 || $z > 14 || $x < 0 || $y < 0) {
        mapillaryTileFail(400);
    }
    $maxIndex = (1 << $z) - 1;
    if ($x > $maxIndex || $y > $maxIndex) {
        mapillaryTileFail(400);
    }

    $cacheFile = mapillaryTileCacheDir() . '/mly1_' . $z . '_' . $x . '_' . $y . '.pbf';
    if (is_file($cacheFile) && filemtime($cacheFile) >= time() - 21600) {
        $cached = file_get_contents($cacheFile);
        if (is_string($cached) && $cached !== '') {
            header('Content-Type: application/x-protobuf');
            header('Cache-Control: public, max-age=3600');
            header('Access-Control-Allow-Origin: *');
            echo $cached;
            exit;
        }
    }

    $url = 'https://tiles.mapillary.com/maps/vtp/mly1_public/2/'
        . $z . '/' . $x . '/' . $y
        . '?access_token=' . rawurlencode($config['access_token']);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'header' => "Accept: application/x-protobuf,*/*\r\nUser-Agent: OpenLiveMap/1.0\r\n",
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || $raw === '') {
        mapillaryTileFail(502);
    }

    $statusLine = $http_response_header[0] ?? '';
    if (preg_match('/\s(\d{3})\s/', $statusLine, $m) && (int) $m[1] >= 400) {
        mapillaryTileFail((int) $m[1] >= 500 ? 502 : (int) $m[1]);
    }

    @file_put_contents($cacheFile, $raw, LOCK_EX);

    header('Content-Type: application/x-protobuf');
    header('Cache-Control: public, max-age=3600');
    echo $raw;
} catch (Throwable $e) {
    mapillaryTileFail(500);
}
