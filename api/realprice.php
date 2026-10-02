<?php
declare(strict_types=1);

@ini_set('memory_limit', '1024M');
@set_time_limit(120);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require dirname(__DIR__) . '/lib/realprice.php';

function respond(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $status = realpriceStatus();
    $bounds = [];
    if (isset($_GET['south'], $_GET['west'], $_GET['north'], $_GET['east'])) {
        $bounds = [
            (float) $_GET['south'],
            (float) $_GET['west'],
            (float) $_GET['north'],
            (float) $_GET['east'],
        ];
    } elseif (isset($_GET['bounds'])) {
        $parts = array_map('floatval', explode(',', (string) $_GET['bounds']));
        if (count($parts) === 4) {
            $bounds = $parts;
        }
    }

    $query = [
        'bounds' => $bounds,
        'period' => (int) ($_GET['period'] ?? 12),
        'type' => (string) ($_GET['type'] ?? 'sale'),
        'zoom' => (int) ($_GET['zoom'] ?? 0),
    ];
    $result = $status['index_exists'] ? realpriceQuery($query) : [
        'clusters' => [],
        'district_clusters' => [],
        'items' => [],
        'district_summaries' => [],
        'item_count' => 0,
        'item_limited' => false,
        'address_item_count' => 0,
        'district_item_count' => 0,
        'district_cluster_count' => 0,
        'address_hit_count' => 0,
        'district_hit_count' => 0,
        'missing_geocode_count' => 0,
        'filtered_count' => 0,
        'coarse_count' => 0,
        'period' => $query['period'],
        'type' => $query['type'],
        'since' => '',
        'zoom' => $query['zoom'],
        'cluster_mode' => 'city',
        'item_zoom_min' => 14,
        'item_full_zoom_min' => 16,
        'district_cluster_zoom_min' => 11,
        'cluster_hide_zoom_min' => 11,
    ];

    $notice = !$status['zip_exists']
        ? '尚未下載實價登錄資料，請先到管理後台下載 ZIP。'
        : (!$status['index_exists']
            ? '尚未建立實價登錄地圖索引，請到管理後台重建索引。'
            : '實價登錄聚合資料已更新。');

    respond([
        'ok' => true,
        'source' => '內政部實價登錄',
        'generated_at' => date(DATE_ATOM),
        'status' => $status,
        'clusters' => $result['clusters'],
        'district_clusters' => $result['district_clusters'] ?? [],
        'items' => $result['items'] ?? [],
        'district_summaries' => $result['district_summaries'] ?? [],
        'item_count' => $result['item_count'] ?? 0,
        'item_limited' => $result['item_limited'] ?? false,
        'address_item_count' => $result['address_item_count'] ?? 0,
        'district_item_count' => $result['district_item_count'] ?? 0,
        'district_cluster_count' => $result['district_cluster_count'] ?? 0,
        'address_hit_count' => $result['address_hit_count'] ?? 0,
        'district_hit_count' => $result['district_hit_count'] ?? 0,
        'missing_geocode_count' => $result['missing_geocode_count'] ?? 0,
        'filtered_count' => $result['filtered_count'],
        'coarse_count' => $result['coarse_count'] ?? ($result['filtered_count'] ?? 0),
        'period' => $result['period'],
        'type' => $result['type'],
        'since' => $result['since'],
        'zoom' => $result['zoom'] ?? $query['zoom'],
        'cluster_mode' => $result['cluster_mode'] ?? 'city',
        'item_zoom_min' => $result['item_zoom_min'] ?? 14,
        'item_full_zoom_min' => $result['item_full_zoom_min'] ?? 16,
        'district_cluster_zoom_min' => $result['district_cluster_zoom_min'] ?? 11,
        'cluster_hide_zoom_min' => $result['cluster_hide_zoom_min'] ?? 11,
        'query_engine' => $result['query_engine'] ?? '',
        'notice' => $notice,
    ]);
} catch (Throwable $error) {
    respond([
        'ok' => false,
        'source' => '內政部實價登錄',
        'generated_at' => date(DATE_ATOM),
        'status' => [],
        'clusters' => [],
        'district_clusters' => [],
        'items' => [],
        'district_summaries' => [],
        'item_count' => 0,
        'item_limited' => false,
        'address_item_count' => 0,
        'district_item_count' => 0,
        'district_cluster_count' => 0,
        'address_hit_count' => 0,
        'district_hit_count' => 0,
        'missing_geocode_count' => 0,
        'filtered_count' => 0,
        'coarse_count' => 0,
        'period' => (int) ($_GET['period'] ?? 12),
        'type' => (string) ($_GET['type'] ?? 'sale'),
        'since' => '',
        'zoom' => (int) ($_GET['zoom'] ?? 0),
        'cluster_mode' => 'city',
        'item_zoom_min' => 14,
        'item_full_zoom_min' => 16,
        'district_cluster_zoom_min' => 11,
        'cluster_hide_zoom_min' => 11,
        'notice' => '實價登錄查詢失敗：' . $error->getMessage(),
    ]);
}
