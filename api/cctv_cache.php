<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$cacheDir = dirname(__DIR__) . '/cache';
require dirname(__DIR__) . '/lib/cctv_custom.php';

function respond(array $payload): void
{
    if (ob_get_length() !== false && ob_get_length() > 0) {
        ob_clean();
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function parseBounds(?string $raw): ?array
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $parts = array_map('floatval', explode(',', $raw));
    if (count($parts) !== 4) {
        return null;
    }
    [$south, $west, $north, $east] = $parts;
    if (!is_finite($south) || !is_finite($west) || !is_finite($north) || !is_finite($east)) {
        return null;
    }
    return [
        'south' => min($south, $north),
        'west' => min($west, $east),
        'north' => max($south, $north),
        'east' => max($west, $east),
    ];
}

function requestZoom(): ?int
{
    $zoom = filter_input(INPUT_GET, 'zoom', FILTER_VALIDATE_INT);
    return is_int($zoom) ? $zoom : null;
}

function requestLimit(): int
{
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
    if (!is_int($limit)) {
        return 800;
    }
    return max(100, min(1200, $limit));
}

function inBounds(float $lat, float $lng, ?array $bounds): bool
{
    if ($bounds === null) {
        return true;
    }
    return $lat >= $bounds['south']
        && $lat <= $bounds['north']
        && $lng >= $bounds['west']
        && $lng <= $bounds['east'];
}

function boundsCenter(?array $bounds): ?array
{
    if ($bounds === null) {
        return null;
    }
    return [
        'lat' => ($bounds['south'] + $bounds['north']) / 2,
        'lng' => ($bounds['west'] + $bounds['east']) / 2,
    ];
}

function squaredDistanceToCenter(array $vehicle, ?array $center): float
{
    if ($center === null) {
        return 0.0;
    }
    $latDelta = ((float) $vehicle['lat']) - $center['lat'];
    $lngDelta = (((float) $vehicle['lng']) - $center['lng']) * cos(deg2rad($center['lat']));
    return ($latDelta * $latDelta) + ($lngDelta * $lngDelta);
}

function firstString(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '') {
            return trim((string) $row[$key]);
        }
    }
    return '';
}

function collectCctvRows(mixed $value, array &$rows): void
{
    if (!is_array($value)) {
        return;
    }
    if (isset($value['CCTVs']) && is_array($value['CCTVs'])) {
        foreach ($value['CCTVs'] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
    }
    foreach ($value as $child) {
        if (is_array($child)) {
            collectCctvRows($child, $rows);
        }
    }
}

function cachedCctvSource(string $scope, string $streamUrl, string $imageUrl): array
{
    $known = inferCctvSource($streamUrl !== '' ? $streamUrl : $imageUrl);
    if ($known['key'] !== 'custom') {
        return $known;
    }
    $scopeKey = strtolower($scope);
    return match ($scopeKey) {
        'taipei' => ['key' => 'taipei', 'label' => '臺北市'],
        'newtaipei' => ['key' => 'newtaipei', 'label' => '新北市'],
        'taoyuan' => ['key' => 'taoyuan', 'label' => '桃園市'],
        'taichung' => ['key' => 'taichung', 'label' => '臺中市'],
        default => ['key' => 'cached-' . preg_replace('/[^a-z0-9_-]+/i', '-', $scopeKey), 'label' => $scope],
    };
}

function normalizeCctv(array $row, string $scope, int $fileTime): ?array
{
    $position = [];
    foreach (['CCTVPosition', 'Position', 'CameraPosition', 'Location'] as $key) {
        if (isset($row[$key]) && is_array($row[$key])) {
            $position = $row[$key];
            break;
        }
    }

    $lat = (float) ($row['PositionLat'] ?? $row['Latitude'] ?? $row['Lat'] ?? $position['PositionLat'] ?? $position['Latitude'] ?? 0);
    $lng = (float) ($row['PositionLon'] ?? $row['Longitude'] ?? $row['Lng'] ?? $position['PositionLon'] ?? $position['Longitude'] ?? 0);
    if ($lat < 20 || $lat > 27 || $lng < 118 || $lng > 123) {
        return null;
    }

    $cameraId = firstString($row, ['CCTVID', 'ID', 'CameraID']) ?: md5(json_encode($row));
    $road = firstString($row, ['RoadName', 'LocationDescription', 'LocationName', 'CCTVName', 'Name']) ?: $cameraId;
    $streamUrl = firstString($row, ['VideoStreamURL', 'VideoURL', 'StreamURL', 'LiveURL', 'URL', 'CCTVURL']);
    $imageUrl = firstString($row, ['ImageURL', 'SnapshotURL', 'PreviewURL']);
    $source = cachedCctvSource($scope, $streamUrl, $imageUrl);

    return [
        'id' => 'cached-cctv-' . md5($scope . '-' . $cameraId),
        'kind' => 'cctv',
        'route' => $road,
        'headsign' => firstString($row, ['RoadDirection', 'Direction']) ?: '歷史點位',
        'plate' => $cameraId,
        'operator' => $source['label'],
        'lat' => $lat,
        'lng' => $lng,
        'speed' => null,
        'bearing' => 0,
        'status' => '歷史快取',
        'updated_at' => gmdate('c', $fileTime),
        'note' => $source['label'] . '歷史點位快取',
        'stream_url' => $streamUrl,
        'image_url' => $imageUrl,
        'source_key' => $source['key'],
        'source_label' => $source['label'],
    ];
}

function sourceBreakdown(array $vehicles): array
{
    $breakdown = [];
    foreach ($vehicles as $vehicle) {
        $key = (string) ($vehicle['source_key'] ?? 'unknown');
        $label = (string) ($vehicle['source_label'] ?? $vehicle['operator'] ?? $key);
        if (!isset($breakdown[$key])) {
            $breakdown[$key] = [
                'key' => $key,
                'label' => $label,
                'count' => 0,
            ];
        }
        $breakdown[$key]['count']++;
    }
    usort($breakdown, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));
    return array_values($breakdown);
}

function clusterCellSize(?int $zoom): float
{
    if ($zoom === null || $zoom <= 4) {
        return 4.0;
    }
    if ($zoom <= 6) {
        return 1.5;
    }
    if ($zoom <= 8) {
        return 0.45;
    }
    if ($zoom <= 10) {
        return 0.16;
    }
    return 0.06;
}

function clusterVehicles(array $vehicles, ?int $zoom, int $maxClusters = 180): array
{
    $cellSize = clusterCellSize($zoom);
    $groups = [];
    foreach ($vehicles as $vehicle) {
        $lat = (float) $vehicle['lat'];
        $lng = (float) $vehicle['lng'];
        $key = floor($lat / $cellSize) . ':' . floor($lng / $cellSize);
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'count' => 0,
                'lat_sum' => 0.0,
                'lng_sum' => 0.0,
                'south' => $lat,
                'west' => $lng,
                'north' => $lat,
                'east' => $lng,
                'sources' => [],
            ];
        }
        $groups[$key]['count']++;
        $groups[$key]['lat_sum'] += $lat;
        $groups[$key]['lng_sum'] += $lng;
        $groups[$key]['south'] = min($groups[$key]['south'], $lat);
        $groups[$key]['west'] = min($groups[$key]['west'], $lng);
        $groups[$key]['north'] = max($groups[$key]['north'], $lat);
        $groups[$key]['east'] = max($groups[$key]['east'], $lng);
        $sourceKey = (string) ($vehicle['source_key'] ?? 'unknown');
        $sourceLabel = (string) ($vehicle['source_label'] ?? $vehicle['operator'] ?? $sourceKey);
        if (!isset($groups[$key]['sources'][$sourceKey])) {
            $groups[$key]['sources'][$sourceKey] = [
                'key' => $sourceKey,
                'label' => $sourceLabel,
                'count' => 0,
            ];
        }
        $groups[$key]['sources'][$sourceKey]['count']++;
    }

    $clusters = array_map(static function (array $group): array {
        $sources = array_values($group['sources']);
        usort($sources, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));
        $count = max(1, (int) $group['count']);
        return [
            'id' => 'cctv-cluster-' . md5(json_encode([$group['south'], $group['west'], $group['north'], $group['east'], $count])),
            'lat' => $group['lat_sum'] / $count,
            'lng' => $group['lng_sum'] / $count,
            'count' => $count,
            'bounds' => [
                $group['south'],
                $group['west'],
                $group['north'],
                $group['east'],
            ],
            'sources' => array_slice($sources, 0, 4),
        ];
    }, array_values($groups));
    usort($clusters, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
    return array_slice($clusters, 0, $maxClusters);
}

try {
    if (!is_dir($cacheDir)) {
        respond([
            'source' => '歷史監視器點位',
            'notice' => '尚無監視器快取資料。',
            'generated_at' => gmdate('c'),
            'vehicles' => [],
            'total_count' => 0,
            'filtered_count' => 0,
        ]);
    }

    $bounds = parseBounds($_GET['bounds'] ?? null);
    $zoom = requestZoom();
    $limit = requestLimit();

    $files = glob($cacheDir . '/tdx-cctv-*.json') ?: [];
    $vehiclesById = [];
    $latestTime = 0;

    foreach ($files as $file) {
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json)) {
            continue;
        }
        $fileTime = filemtime($file) ?: time();
        $latestTime = max($latestTime, $fileTime);
        $scope = preg_replace('/^tdx-cctv-|\.json$/', '', basename($file)) ?: basename($file);
        $rows = [];
        collectCctvRows($json['data'] ?? $json, $rows);
        foreach ($rows as $row) {
            $vehicle = normalizeCctv($row, $scope, $fileTime);
            if ($vehicle === null) {
                continue;
            }
            $vehiclesById[$vehicle['id']] = $vehicle;
        }
    }

    foreach (loadCustomCctvs() as $row) {
        $vehicle = customCctvToVehicle($row);
        if ($vehicle === null) {
            continue;
        }
        $latestTime = max($latestTime, strtotime((string) $vehicle['updated_at']) ?: time());
        $vehiclesById[$vehicle['id']] = $vehicle;
    }

    $allVehicles = array_values($vehiclesById);
    $filtered = array_values(array_filter($allVehicles, static fn (array $vehicle): bool => inBounds((float) $vehicle['lat'], (float) $vehicle['lng'], $bounds)));
    $filteredCount = count($filtered);
    $clusters = [];
    $clusterMode = $zoom !== null && ($zoom < 10 || ($filteredCount > $limit && $zoom < 14));
    if ($clusterMode) {
        $clusters = clusterVehicles($filtered, $zoom);
    }
    $center = boundsCenter($bounds);
    if ($clusterMode) {
        $filtered = [];
    } elseif ($filteredCount > $limit) {
        usort($filtered, static fn (array $a, array $b): int => squaredDistanceToCenter($a, $center) <=> squaredDistanceToCenter($b, $center));
        $filtered = array_slice($filtered, 0, $limit);
    }

    respond([
        'source' => '監視器點位',
        'notice' => $clusterMode
            ? "目前範圍監視器過多，已改以 {$filteredCount} 筆監視器聚合顯示；請放大地圖查看單一 CAM。"
            : '監視器位置來自保存點位與自訂資料，未重新連線更新。',
        'generated_at' => gmdate('c', $latestTime > 0 ? $latestTime : time()),
        'vehicles' => $filtered,
        'clusters' => $clusters,
        'total_count' => count($allVehicles),
        'filtered_count' => $filteredCount,
        'returned_count' => count($filtered),
        'cluster_mode' => $clusterMode,
        'limited' => $clusterMode,
        'min_zoom' => 10,
        'filtered_source_breakdown' => sourceBreakdown($filtered),
    ]);
} catch (Throwable $e) {
    respond([
        'source' => '歷史監視器點位',
        'notice' => '監視器快取讀取失敗：' . $e->getMessage(),
        'generated_at' => gmdate('c'),
        'vehicles' => [],
        'total_count' => 0,
        'filtered_count' => 0,
    ]);
}
