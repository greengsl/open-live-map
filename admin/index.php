<?php
declare(strict_types=1);

session_start();

require dirname(__DIR__) . '/lib/source_config.php';
require dirname(__DIR__) . '/lib/monetization_config.php';
require dirname(__DIR__) . '/lib/cctv_custom.php';
require dirname(__DIR__) . '/lib/twlive_import.php';
require dirname(__DIR__) . '/lib/gov_taiwan_import.php';
require dirname(__DIR__) . '/lib/freeway_cctv_import.php';
require dirname(__DIR__) . '/lib/highway1968_import.php';
require dirname(__DIR__) . '/lib/tainan_cctv_import.php';
require dirname(__DIR__) . '/lib/realprice.php';
require dirname(__DIR__) . '/lib/doorplate.php';
require dirname(__DIR__) . '/lib/tgos.php';
require dirname(__DIR__) . '/lib/analytics.php';
require dirname(__DIR__) . '/lib/cctv_reports.php';
require dirname(__DIR__) . '/lib/cctv_submissions.php';
require dirname(__DIR__) . '/lib/ui_config.php';
require dirname(__DIR__) . '/lib/mapillary_config.php';

$localConfigFile = dirname(__DIR__) . '/config.local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
$adminPassword = is_array($localConfig) ? (string) ($localConfig['admin_password'] ?? '') : '';
$message = '';
$error = '';
$saved = false;

function adminWantsJson(): bool
{
    return ($_POST['ajax'] ?? '') === '1'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
}

function adminJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function adminCctvRowPayload(array $row): array
{
    $source = inferCctvSource(
        (string) ($row['stream_url'] ?? ''),
        (string) ($row['source_key'] ?? 'custom'),
        (string) ($row['source_label'] ?? '自訂監視器')
    );
    $enabled = !empty($row['enabled']);
    $searchText = implode(' ', [
        (string) ($row['name'] ?? ''),
        (string) ($row['camera_id'] ?? ''),
        (string) ($row['direction'] ?? ''),
        (string) ($row['lat'] ?? ''),
        (string) ($row['lng'] ?? ''),
        (string) ($row['stream_url'] ?? ''),
        (string) $source['label'],
    ]);

    return [
        'id' => (string) ($row['id'] ?? ''),
        'camera_id' => (string) ($row['camera_id'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'direction' => (string) ($row['direction'] ?? ''),
        'lat' => (string) ($row['lat'] ?? ''),
        'lng' => (string) ($row['lng'] ?? ''),
        'stream_url' => (string) ($row['stream_url'] ?? ''),
        'source_key' => (string) ($row['source_key'] ?? ''),
        'source_label' => (string) ($row['source_label'] ?? ''),
        'enabled' => $enabled,
        'source' => [
            'key' => (string) $source['key'],
            'label' => (string) $source['label'],
        ],
        'status' => $enabled ? 'enabled' : 'disabled',
        'status_label' => $enabled ? '啟用' : '停用',
        'search' => searchTextLower($searchText),
    ];
}

function saveAdminCctv(array $input): array
{
    $rows = loadCustomCctvs();
    $row = normalizeCustomCctvInput($input, $rows);
    $originalId = trim((string) ($input['original_id'] ?? ''));
    $next = [];
    foreach ($rows as $existing) {
        if (($existing['id'] ?? '') === $originalId || ($existing['id'] ?? '') === $row['id']) {
            continue;
        }
        $next[] = $existing;
    }
    $next[] = $row;
    saveCustomCctvs($next);
    return $row;
}

if ($adminPassword !== '' && ($_POST['action'] ?? '') === 'login') {
    if (hash_equals($adminPassword, (string) ($_POST['password'] ?? ''))) {
        $_SESSION['admin_authenticated'] = true;
        header('Location: ./');
        exit;
    }
    $error = '管理密碼不正確。';
}

if (($_GET['logout'] ?? '') === '1') {
    unset($_SESSION['admin_authenticated']);
    header('Location: ./');
    exit;
}

$authenticated = $adminPassword === '' || !empty($_SESSION['admin_authenticated']);
$adminTabs = [
    'sources' => '資料來源',
    'analytics' => '造訪記錄',
    'realprice' => '實價登錄',
    'appearance' => '介面外觀',
    'monetization' => '收益廣告',
    'reports' => '監視器回報',
    'submissions' => '監視器投稿',
    'cctv' => '自訂監視器',
];
$realpriceSubTabs = [
    'data' => '資料／索引',
    'coverage' => '定位覆蓋',
    'doorplate' => '本機門牌',
    'tgos' => 'TGOS 備援',
    'geocode' => 'Nominatim',
    'records' => '明細檢查',
    'log' => '操作日誌',
];
$postAction = (string) ($_POST['action'] ?? '');
$activeTab = (string) ($_GET['tab'] ?? 'sources');
$realpriceSub = (string) ($_GET['rp'] ?? 'data');
if ($postAction !== '') {
    $activeTab = match ($postAction) {
        'save' => 'sources',
        'save_monetization' => 'monetization',
        'download_realprice', 'check_realprice', 'rebuild_realprice', 'rebuild_realprice_start', 'rebuild_realprice_chunk', 'rebuild_realprice_cancel', 'rebuild_realprice_status', 'prepare_realprice_geocode', 'process_realprice_geocode', 'repair_realprice_geocode', 'refresh_realprice_coverage', 'refresh_realprice_coverage_chunk', 'refresh_realprice_coverage_cancel', 'clear_realprice_oplog', 'download_doorplate', 'match_doorplate', 'match_doorplate_start', 'match_doorplate_chunk', 'match_doorplate_cancel', 'match_doorplate_status', 'upload_doorplate', 'register_doorplate', 'export_tgos_batch', 'import_tgos_batch', 'import_tgos_chunk', 'import_tgos_cancel', 'save_realprice_override', 'save_realprice_custom' => 'realprice',
        'save_ui', 'save_mapillary' => 'appearance',
        'update_cctv_report', 'save_cctv_report_types' => 'reports',
        'approve_cctv_submission', 'reject_cctv_submission' => 'submissions',
        'save_cctv', 'delete_cctv', 'import_twlive', 'import_freeway_cctv', 'import_1968_highways', 'import_gov_taiwan', 'import_tainan_cctv' => 'cctv',
        default => $activeTab,
    };
    $realpriceSub = match ($postAction) {
        'download_realprice', 'check_realprice', 'rebuild_realprice', 'rebuild_realprice_start', 'rebuild_realprice_chunk', 'rebuild_realprice_cancel', 'rebuild_realprice_status' => 'data',
        'refresh_realprice_coverage', 'refresh_realprice_coverage_chunk', 'refresh_realprice_coverage_cancel' => 'coverage',
        'download_doorplate', 'match_doorplate', 'match_doorplate_start', 'match_doorplate_chunk', 'match_doorplate_cancel', 'match_doorplate_status', 'upload_doorplate', 'register_doorplate' => 'doorplate',
        'export_tgos_batch', 'import_tgos_batch', 'import_tgos_chunk', 'import_tgos_cancel' => 'tgos',
        'prepare_realprice_geocode', 'process_realprice_geocode', 'repair_realprice_geocode' => 'geocode',
        'save_realprice_override', 'save_realprice_custom' => 'records',
        'clear_realprice_oplog' => 'log',
        default => $realpriceSub,
    };
}
if ((string) ($_GET['edit_cctv'] ?? '') !== '') {
    $activeTab = 'cctv';
}
if (isset($_GET['analytics_days'])) {
    $activeTab = 'analytics';
}
if (isset($_GET['doorplate'])) {
    $activeTab = 'realprice';
    $realpriceSub = 'doorplate';
}
if (!isset($adminTabs[$activeTab])) {
    $activeTab = 'sources';
}
if ($activeTab === 'realprice' && !isset($realpriceSubTabs[$realpriceSub])) {
    $realpriceSub = 'data';
}

$sources = loadSourceConfig();
$monetization = $activeTab === 'monetization' ? loadMonetizationConfig() : defaultMonetizationConfig();
$uiConfig = ($activeTab === 'appearance' || $activeTab === 'reports' || $activeTab === 'sources') ? loadUiConfig() : defaultUiConfig();
$mapillaryConfig = $activeTab === 'appearance' ? loadMapillaryConfig() : defaultMapillaryConfig();
$realpriceStatus = $activeTab === 'realprice' ? realpriceStatus() : [];
$realpriceGeocodeStats = ($activeTab === 'realprice' && $realpriceSub === 'geocode') ? realpriceGeocodeStats() : [];
$doorplateStatus = ($activeTab === 'realprice' && in_array($realpriceSub, ['doorplate', 'coverage'], true)) ? doorplateStatus() : [];
$tgosState = ($activeTab === 'realprice' && $realpriceSub === 'tgos') ? tgosLoadState() : [];
$realpriceCoverage = ($activeTab === 'realprice' && in_array($realpriceSub, ['coverage', 'doorplate'], true)) ? realpriceCoverageStats() : [];
$realpriceOpLogs = ($activeTab === 'realprice' && $realpriceSub === 'log') ? realpriceOpLogRead(80) : [];
$realpriceRecordSearch = [];
$analyticsDays = max(1, min(90, (int) ($_GET['analytics_days'] ?? 14)));
$analyticsReport = ($authenticated && $activeTab === 'analytics') ? analyticsReport($analyticsDays) : null;

if ($authenticated && ($_POST['action'] ?? '') === 'save') {
    try {
        $next = loadSourceConfig();
        foreach ($next as $key => $source) {
            $next[$key]['enabled'] = isset($_POST['enabled'][$key]);
            $next[$key]['default_checked'] = isset($_POST['default_checked'][$key]);
        }
        saveSourceConfig($next);
        $sources = $next;
        $message = '資料來源設定已儲存。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'save_monetization') {
    try {
        $nextMonetization = defaultMonetizationConfig();
        $nextMonetization['adsense']['enabled'] = isset($_POST['adsense_enabled']);
        $nextMonetization['adsense']['client'] = trim((string) ($_POST['adsense_client'] ?? ''));
        $nextMonetization['adsense']['slots']['sidebar'] = trim((string) ($_POST['adsense_sidebar_slot'] ?? ''));
        $nextMonetization['adsense']['slots']['map'] = trim((string) ($_POST['adsense_map_slot'] ?? ''));
        $nextMonetization['support']['enabled'] = isset($_POST['support_enabled']);
        $nextMonetization['support']['title'] = trim((string) ($_POST['support_title'] ?? ''));
        $nextMonetization['support']['description'] = trim((string) ($_POST['support_description'] ?? ''));
        $nextMonetization['support']['button_label'] = trim((string) ($_POST['support_button_label'] ?? ''));
        $nextMonetization['support']['url'] = trim((string) ($_POST['support_url'] ?? ''));
        $nextMonetization['support']['contact_title'] = trim((string) ($_POST['support_contact_title'] ?? ''));
        $nextMonetization['support']['contact_email'] = trim((string) ($_POST['support_contact_email'] ?? ''));
        $nextMonetization['support']['contact_url_label'] = trim((string) ($_POST['support_contact_url_label'] ?? ''));
        $nextMonetization['support']['contact_url'] = trim((string) ($_POST['support_contact_url'] ?? ''));
        $nextMonetization['support']['contact_note'] = trim((string) ($_POST['support_contact_note'] ?? ''));
        if ($nextMonetization['adsense']['enabled'] && !adsenseEnabled($nextMonetization)) {
            throw new RuntimeException('AdSense 已啟用時，Publisher ID 必須是 ca-pub- 開頭加數字。');
        }
        if ($nextMonetization['support']['contact_email'] !== '' && filter_var($nextMonetization['support']['contact_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('聯絡 Email 格式不正確。');
        }
        if ($nextMonetization['support']['title'] === '') {
            $nextMonetization['support']['title'] = '支持 Open Live Map';
        }
        if ($nextMonetization['support']['button_label'] === '') {
            $nextMonetization['support']['button_label'] = '贊助本站';
        }
        if ($nextMonetization['support']['contact_title'] === '') {
            $nextMonetization['support']['contact_title'] = '聯絡方式';
        }
        saveMonetizationConfig($nextMonetization);
        $monetization = $nextMonetization;
        $message = '收益與廣告設定已儲存。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'save_ui') {
    try {
        $uiConfig = saveUiConfig([
            'monitor_overlay_opacity' => $_POST['monitor_overlay_opacity'] ?? 30,
            'monitor_overlay_blur' => $_POST['monitor_overlay_blur'] ?? 10,
            'map_marker_opacity' => $_POST['map_marker_opacity'] ?? 85,
        ]);
        $message = '介面外觀已儲存（監看窗 '
            . monitorOverlayOpacity($uiConfig) . '%／模糊 '
            . monitorOverlayBlur($uiConfig) . 'px、地圖標誌 '
            . mapMarkerOpacity($uiConfig) . '%）。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'save_mapillary') {
    try {
        $existing = loadMapillaryConfig();
        $token = trim((string) ($_POST['mapillary_access_token'] ?? ''));
        if ($token === '' && !empty($_POST['mapillary_keep_token'])) {
            $token = (string) ($existing['access_token'] ?? '');
        }
        $wantEnabled = isset($_POST['mapillary_enabled']);
        if ($wantEnabled && $token === '') {
            throw new RuntimeException('已勾選啟用 Mapillary，請填入 access token。');
        }
        $mapillaryConfig = saveMapillaryConfig([
            'enabled' => $wantEnabled,
            'access_token' => $token,
            'radius_m' => $_POST['mapillary_radius_m'] ?? 50,
        ]);
        $message = mapillaryEnabled($mapillaryConfig)
            ? 'Mapillary 街景已啟用（搜尋半徑 ' . (int) $mapillaryConfig['radius_m'] . ' m）。'
            : 'Mapillary 街景已關閉；前台不會載入相關功能。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $mapillaryConfig = loadMapillaryConfig();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'download_realprice') {
    try {
        $realpriceStatus = realpriceDownloadRecentSeasons(5);
        $message = '已下載近 5 季實價登錄 ZIP：'
            . number_format((int) ($realpriceStatus['season_zip_count'] ?? 0)) . ' 個季度檔可用。'
            . '請按「用本機季度 ZIP 重建索引」分批建立索引（避免 504）。';
        realpriceOpLog('download_realprice', $message, [
            'season_zip_count' => (int) ($realpriceStatus['season_zip_count'] ?? 0),
        ], 'ok');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('download_realprice', $error, [], 'error');
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'check_realprice') {
    try {
        $realpriceStatus = realpriceCheckRemoteUpdate();
        $message = ($realpriceStatus['remote_likely_changed'] ?? false)
            ? '官方檔案資訊可能已有變化，建議下載更新並以 SHA-256 確認。'
            : '官方檔案資訊看起來未變更。若要百分百確認，可執行下載更新，系統會比對 SHA-256。';
        realpriceOpLog('check_realprice', $message, [], 'ok');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('check_realprice', $error, [], 'error');
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'rebuild_realprice') {
    $error = '為避免 Cloudflare／Synology 504，請使用「用本機季度 ZIP 重建索引」按鈕（分批 AJAX，會顯示進度），不要用整頁送出。';
    if (adminWantsJson()) {
        adminJson(['ok' => false, 'message' => $error], 422);
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'rebuild_realprice_start') {
    try {
        $selected = $_POST['seasons'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        $result = realpriceIndexJobStart($selected);
        $message = (string) ($result['message'] ?? '已開始分批重建索引。');
        realpriceOpLog('rebuild_realprice_start', $message, [
            'zip_count' => (int) (($result['job']['zip_count'] ?? 0)),
            'selected' => array_values(array_map('strval', $selected)),
        ], 'ok');
        if (adminWantsJson()) {
            adminJson($result);
        }
        $realpriceStatus = realpriceStatus();
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('rebuild_realprice_start', $error, [], 'error');
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'rebuild_realprice_chunk') {
    try {
        $result = realpriceIndexJobChunk(10);
        if (adminWantsJson()) {
            adminJson($result);
        }
        $realpriceStatus = $result['status'] ?? realpriceStatus();
        $message = (string) ($result['message'] ?? '索引批次已處理。');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'rebuild_realprice_cancel') {
    try {
        $result = realpriceIndexJobCancel();
        $message = (string) ($result['message'] ?? '已取消索引重建。');
        realpriceOpLog('rebuild_realprice_cancel', $message, [], 'warn');
        if (adminWantsJson()) {
            adminJson($result);
        }
        $realpriceStatus = realpriceStatus();
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'rebuild_realprice_status') {
    $job = realpriceLoadJsonFile(realpriceIndexJobStatePath(), []);
    $status = (string) ($job['status'] ?? '');
    adminJson([
        'ok' => true,
        'done' => !in_array($status, ['running'], true),
        'cancelled' => $status === 'cancelled',
        'job' => $job,
        'message' => (string) ($job['message'] ?? ''),
        'status' => realpriceStatus(),
    ]);
}

if ($authenticated && ($_POST['action'] ?? '') === 'prepare_realprice_geocode') {
    try {
        $limit = (int) ($_POST['queue_limit'] ?? 1000);
        $realpriceGeocodeStats = realpriceBuildGeocodeQueue($limit);
        $message = '實價登錄地址定位佇列已建立：' . number_format((int) $realpriceGeocodeStats['queue_count']) . ' 筆待管理。';
        realpriceOpLog('prepare_geocode', $message, [
            'queue_count' => (int) ($realpriceGeocodeStats['queue_count'] ?? 0),
            'queue_limit' => $limit,
        ], 'ok');
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => '實價登錄地址定位佇列已建立。',
                'stats' => $realpriceGeocodeStats,
            ]);
        }
        $saved = true;
    } catch (Throwable $e) {
        realpriceOpLog('prepare_geocode', $e->getMessage(), [], 'error');
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'repair_realprice_geocode') {
    try {
        $queueLimit = (int) ($_POST['queue_limit'] ?? 3000);
        $realpriceGeocodeStats = realpriceRepairPoisonedGeocodeCache(true, $queueLimit);
        $message = (string) ($realpriceGeocodeStats['last_message'] ?: '誤標座標快取已清理。');
        realpriceOpLog('repair_geocode', $message, ['queue_limit' => $queueLimit], 'ok');
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => $message,
                'stats' => $realpriceGeocodeStats,
            ]);
        }
        $saved = true;
    } catch (Throwable $e) {
        realpriceOpLog('repair_geocode', $e->getMessage(), [], 'error');
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'refresh_realprice_coverage') {
    try {
        $job = realpriceCoverageJobStart();
        $message = (string) ($job['message'] ?? '已開始分批統計覆蓋。');
        realpriceOpLog('refresh_coverage', $message, [
            'total_rows' => (int) ($job['total_rows'] ?? 0),
        ], 'ok');
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => $message,
                'need_chunk' => true,
                'done' => false,
                'job' => $job,
            ]);
        }
        // Non-AJAX fallback: run a few chunks then show progress message.
        $guard = 0;
        $last = ['done' => false, 'message' => $message, 'job' => $job, 'coverage' => null];
        while ($guard < 20 && empty($last['done'])) {
            $guard++;
            $last = realpriceCoverageJobChunk(10000);
        }
        $message = (string) ($last['message'] ?? $message);
        if (is_array($last['coverage'] ?? null)) {
            $realpriceCoverage = $last['coverage'];
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('refresh_coverage', $error, [], 'error');
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'refresh_realprice_coverage_chunk') {
    try {
        $result = realpriceCoverageJobChunk((int) ($_POST['budget_ms'] ?? 12000));
        if (adminWantsJson()) {
            $payload = [
                'ok' => true,
                'message' => (string) ($result['message'] ?? ''),
                'done' => !empty($result['done']),
                'job' => $result['job'] ?? realpriceLoadCoverageJob(),
                'chunk' => $result['chunk'] ?? null,
            ];
            if (is_array($result['coverage'] ?? null)) {
                $payload['coverage'] = $result['coverage'];
                realpriceOpLog('refresh_coverage', (string) $payload['message'], [
                    'precise_count' => (int) ($result['coverage']['precise_count'] ?? 0),
                    'need_match_count' => (int) ($result['coverage']['need_match_count'] ?? 0),
                ], 'ok');
            }
            adminJson($payload);
        }
        $message = (string) ($result['message'] ?? '');
        if (is_array($result['coverage'] ?? null)) {
            $realpriceCoverage = $result['coverage'];
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error, 'job' => realpriceLoadCoverageJob()], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'refresh_realprice_coverage_cancel') {
    try {
        $job = realpriceCoverageJobCancel();
        if (adminWantsJson()) {
            adminJson(['ok' => true, 'message' => (string) ($job['message'] ?? '已取消'), 'job' => $job]);
        }
        $message = (string) ($job['message'] ?? '已取消');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'clear_realprice_oplog') {
    try {
        realpriceOpLogClear();
        $message = '操作日誌已清空。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'download_doorplate') {
    try {
        $cityKey = preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['city'] ?? '')) ?: '';
        $doorplateStatus = doorplateDownloadCity($cityKey);
        $message = (string) ($doorplateStatus['last_message'] ?? '門牌資料已下載。');
        realpriceOpLog('download_doorplate', $message, ['city' => $cityKey], 'ok');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('download_doorplate', $error, [], 'error');
        $doorplateStatus = doorplateStatus();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'upload_doorplate') {
    try {
        $cityKey = preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['city'] ?? '')) ?: '';
        // Upload only. Matching must be chunked via AJAX to avoid Synology 504.
        $doorplateStatus = doorplateSaveUploadedCsv($cityKey, $_FILES['doorplate_csv'] ?? []);
        $message = (string) ($doorplateStatus['last_message'] ?? '門牌 CSV 已上傳。') . ' 接著將分批自動比對。';
        realpriceOpLog('upload_doorplate', $message, ['city' => $cityKey], 'ok');
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => $message,
                'city' => $cityKey,
                'auto_match_pending' => true,
                'status' => $doorplateStatus,
            ]);
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('upload_doorplate', $error, [], 'error');
        $doorplateStatus = doorplateStatus();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error, 'status' => $doorplateStatus], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'register_doorplate') {
    try {
        $cityKey = preg_replace('/[^a-z0-9_]/', '', (string) ($_POST['city'] ?? '')) ?: '';
        $doorplateStatus = doorplateRegisterLocalCsv($cityKey);
        $message = (string) ($doorplateStatus['last_message'] ?? '本機門牌 CSV 已登記。') . ' 接著將分批自動比對。';
        realpriceOpLog('register_doorplate', $message, ['city' => $cityKey], 'ok');
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => $message,
                'city' => $cityKey,
                'auto_match_pending' => true,
                'status' => $doorplateStatus,
            ]);
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('register_doorplate', $error, [], 'error');
        $doorplateStatus = doorplateStatus();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error, 'status' => $doorplateStatus], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'match_doorplate_start') {
    try {
        $selected = $_POST['cities'] ?? [];
        if (!is_array($selected)) $selected = [];
        $selected = array_values(array_filter(array_map(
            static fn ($value): string => preg_replace('/[^a-z0-9_]/', '', (string) $value) ?: '',
            $selected
        )));
        $result = doorplateMatchJobStart($selected);
        $message = (string) ($result['message'] ?? '已開始分批比對。');
        realpriceOpLog('match_doorplate_start', $message, ['cities' => $selected], 'ok');
        if (adminWantsJson()) {
            adminJson($result);
        }
        $doorplateStatus = $result['status'] ?? doorplateStatus();
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('match_doorplate_start', $error, [], 'error');
        $doorplateStatus = doorplateStatus();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'match_doorplate_chunk') {
    try {
        // 較短批次，降低 Synology / nginx 504 造成前端卡住、畫面一直顯示 0 的機率。
        $result = doorplateMatchJobChunk(12000, 3);
        if (adminWantsJson()) {
            adminJson($result);
        }
        $doorplateStatus = $result['status'] ?? doorplateStatus();
        $realpriceGeocodeStats = $result['geocode_stats'] ?? $realpriceGeocodeStats;
        $message = (string) ($result['message'] ?? '比對批次已處理。');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $doorplateStatus = doorplateStatus();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'match_doorplate_cancel') {
    try {
        $result = doorplateMatchJobCancel();
        $message = (string) ($result['message'] ?? '已取消門牌比對。');
        realpriceOpLog('match_doorplate_cancel', $message, [
            'matched' => (int) (($result['job']['total_matched'] ?? 0)),
            'scanned' => (int) (($result['job']['total_scanned'] ?? 0)),
        ], 'warn');
        if (adminWantsJson()) {
            adminJson($result);
        }
        $doorplateStatus = $result['status'] ?? doorplateStatus();
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('match_doorplate_cancel', $error, [], 'error');
        $doorplateStatus = doorplateStatus();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'match_doorplate_status') {
    $job = realpriceLoadJsonFile(doorplateMatchStatePath(), []);
    $status = (string) ($job['status'] ?? '');
    adminJson([
        'ok' => true,
        'done' => !in_array($status, ['running'], true),
        'cancelled' => $status === 'cancelled',
        'job' => $job,
        'message' => (string) ($job['message'] ?? ''),
    ]);
}

if ($authenticated && ($_POST['action'] ?? '') === 'match_doorplate') {
    // Legacy full-form submit: refuse long sync work and ask for AJAX chunked flow.
    $error = '為避免 504 逾時，請使用頁面上的「執行本機門牌比對」按鈕（分批 AJAX），不要用舊的整頁送出。';
    if (adminWantsJson()) {
        adminJson(['ok' => false, 'message' => $error], 422);
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'export_tgos_batch') {
    try {
        $cityFilter = trim((string) ($_POST['tgos_city'] ?? ''));
        $limit = (int) ($_POST['tgos_limit'] ?? 10000);
        $encoding = (string) ($_POST['tgos_encoding'] ?? 'utf-8');
        $built = tgosBuildExport($cityFilter, $limit, $encoding);
        $exportMessage = (string) (tgosLoadState()['last_message'] ?? '已匯出 TGOS 批次');
        realpriceOpLog('tgos_export', $exportMessage, [
            'count' => (int) ($built['count'] ?? 0),
            'city' => $cityFilter,
            'encoding' => (string) ($built['encoding'] ?? ''),
        ], 'ok');
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => $exportMessage,
                'filename' => (string) ($built['filename'] ?? ''),
                'count' => (int) ($built['count'] ?? 0),
                'download' => 'index.php?tab=realprice&rp=tgos&tgos_dl=' . rawurlencode((string) ($built['filename'] ?? '')),
                'state' => tgosLoadState(),
            ]);
        }
        tgosDownloadExport($built);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $tgosState = tgosLoadState();
        realpriceOpLog('tgos_export', $error, [], 'error');
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error, 'state' => $tgosState], 422);
        }
    }
}

if ($authenticated && isset($_GET['tgos_dl']) && $activeTab === 'realprice') {
    try {
        $file = basename((string) $_GET['tgos_dl']);
        if ($file === '' || !preg_match('/^tgos-batch-.+\.csv$/i', $file)) {
            throw new RuntimeException('無效的匯出檔名。');
        }
        $path = tgosDir() . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException('找不到匯出檔，請重新匯出。');
        }
        $mapPath = preg_replace('/\.csv$/i', '.map.json', $path) ?: ($path . '.map.json');
        $map = is_file($mapPath) ? realpriceLoadJsonFile($mapPath, []) : [];
        $encoding = strtolower((string) ($map['encoding'] ?? 'utf-8')) === 'big5' ? 'big5' : 'utf-8';
        tgosDownloadExport([
            'path' => $path,
            'filename' => $file,
            'encoding' => $encoding,
        ]);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $tgosState = tgosLoadState();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'import_tgos_batch') {
    try {
        $file = $_FILES['tgos_result_csv'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('請選擇 TGOS 回傳的結果 CSV 檔。');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? 'tgos-result.csv');
        $job = tgosImportJobStart($tmp, $name);
        $message = (string) ($job['message'] ?? '已開始分批匯入 TGOS 結果。');
        $saved = true;
        $tgosState = tgosLoadState();
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => $message,
                'need_chunk' => true,
                'job' => $job,
                'state' => $tgosState,
            ]);
        }
        // Non-AJAX fallback: process a few chunks then ask to continue via UI.
        $guard = 0;
        $last = ['done' => false, 'message' => $message, 'job' => $job];
        while ($guard < 40 && empty($last['done'])) {
            $guard++;
            $last = tgosImportJobChunk(500);
        }
        $message = (string) ($last['message'] ?? $message);
        $tgosState = tgosLoadState();
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $tgosState = tgosLoadState();
        realpriceOpLog('tgos_import', $error, [], 'error');
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error, 'state' => $tgosState], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'import_tgos_chunk') {
    try {
        $result = tgosImportJobChunk((int) ($_POST['limit'] ?? 300));
        $tgosState = tgosLoadState();
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => (string) ($result['message'] ?? ''),
                'done' => !empty($result['done']),
                'job' => $result['job'] ?? tgosLoadImportJob(),
                'chunk' => $result['chunk'] ?? null,
                'state' => $tgosState,
            ]);
        }
        $message = (string) ($result['message'] ?? '');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $tgosState = tgosLoadState();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error, 'state' => $tgosState, 'job' => tgosLoadImportJob()], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'import_tgos_cancel') {
    try {
        $job = tgosImportJobCancel();
        $tgosState = tgosLoadState();
        if (adminWantsJson()) {
            adminJson(['ok' => true, 'message' => (string) ($job['message'] ?? '已取消'), 'job' => $job, 'state' => $tgosState]);
        }
        $message = (string) ($job['message'] ?? '已取消');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        if (adminWantsJson()) {
            adminJson(['ok' => false, 'message' => $error], 422);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'process_realprice_geocode') {
    try {
        $batchSize = (int) ($_POST['batch_size'] ?? 5);
        $realpriceGeocodeStats = realpriceProcessGeocodeBatch($batchSize);
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => (string) ($realpriceGeocodeStats['last_message'] ?: '地址定位批次已處理。'),
                'stats' => $realpriceGeocodeStats,
            ]);
        }
        $message = (string) ($realpriceGeocodeStats['last_message'] ?: '地址定位批次已處理。');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $realpriceGeocodeStats = realpriceGeocodeStats();
        if (adminWantsJson()) {
            adminJson([
                'ok' => false,
                'message' => $e->getMessage(),
                'stats' => $realpriceGeocodeStats,
            ], 429);
        }
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'save_realprice_override') {
    try {
        realpriceSaveRecordOverride((string) ($_POST['record_key'] ?? ''), [
            'lat' => $_POST['override_lat'] ?? '',
            'lng' => $_POST['override_lng'] ?? '',
            'note' => $_POST['override_note'] ?? '',
            'hidden' => isset($_POST['override_hidden']),
        ]);
        $message = '實價登錄本地修正已儲存。';
        realpriceOpLog('save_override', $message, [
            'record_key' => (string) ($_POST['record_key'] ?? ''),
        ], 'ok');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('save_override', $error, [], 'error');
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'save_realprice_custom') {
    try {
        realpriceSaveCustomRecord($_POST);
        $message = '自訂實價登錄案件已新增。';
        realpriceOpLog('save_custom', $message, [
            'city' => (string) ($_POST['city'] ?? ''),
            'district' => (string) ($_POST['district'] ?? ''),
        ], 'ok');
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        realpriceOpLog('save_custom', $error, [], 'error');
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'save_cctv') {
    try {
        $row = saveAdminCctv($_POST);
        if (adminWantsJson()) {
            adminJson([
                'ok' => true,
                'message' => '自訂監視器已儲存。',
                'row' => adminCctvRowPayload($row),
                'original_id' => trim((string) ($_POST['original_id'] ?? '')),
                'total' => count(loadCustomCctvs()),
            ]);
        }
        $message = '自訂監視器已儲存。';
        $saved = true;
    } catch (Throwable $e) {
        if (adminWantsJson()) {
            adminJson([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'delete_cctv') {
    try {
        $deleteId = (string) ($_POST['id'] ?? '');
        $next = array_values(array_filter(loadCustomCctvs(), static fn (array $row): bool => ($row['id'] ?? '') !== $deleteId));
        saveCustomCctvs($next);
        $message = '自訂監視器已刪除。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'save_cctv_report_types') {
    try {
        $keys = is_array($_POST['report_type_key'] ?? null) ? $_POST['report_type_key'] : [];
        $labels = is_array($_POST['report_type_label'] ?? null) ? $_POST['report_type_label'] : [];
        $sorts = is_array($_POST['report_type_sort'] ?? null) ? $_POST['report_type_sort'] : [];
        $enabled = is_array($_POST['report_type_enabled'] ?? null) ? $_POST['report_type_enabled'] : [];
        $rows = [];
        $max = max(count($keys), count($labels), count($sorts));
        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                'key' => (string) ($keys[$i] ?? ''),
                'label' => (string) ($labels[$i] ?? ''),
                'sort' => (int) ($sorts[$i] ?? (($i + 1) * 10)),
                'enabled' => isset($enabled[$i]),
            ];
        }
        saveCctvReportTypeRows($rows);
        $message = '監視器回報問題類型已儲存。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'update_cctv_report') {
    try {
        updateCctvReportStatus((string) ($_POST['id'] ?? ''), (string) ($_POST['status'] ?? 'open'));
        $message = '監視器回報狀態已更新。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'approve_cctv_submission') {
    try {
        approveCctvSubmission((string) ($_POST['id'] ?? ''));
        $message = '監視器投稿已核准並公開。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'reject_cctv_submission') {
    try {
        updateCctvSubmissionStatus((string) ($_POST['id'] ?? ''), 'rejected');
        $message = '監視器投稿已退回。';
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'import_twlive') {
    try {
        $result = twLiveImportHighway((string) ($_POST['twlive_url'] ?? ''), (int) ($_POST['twlive_limit'] ?? 20));
        $rows = loadCustomCctvs();
        $byId = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $byId[(string) $row['id']] = $row;
            }
        }
        foreach ($result['rows'] as $row) {
            $byId[(string) $row['id']] = $row;
        }
        saveCustomCctvs(array_values($byId));
        $message = sprintf(
            'tw.live 國道監視器已匯入 %d 筆；掃到 %d 筆，處理 %d 筆。',
            count($result['rows']),
            (int) $result['scanned'],
            (int) $result['processed']
        );
        if (($result['errors'] ?? []) !== []) {
            $message .= ' 略過 ' . count($result['errors']) . ' 筆資料不完整項目。';
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'import_freeway_cctv') {
    try {
        $result = freewayCctvRows((string) ($_POST['freeway_road_id'] ?? '000010'));
        $rows = loadCustomCctvs();
        $byId = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $byId[(string) $row['id']] = $row;
            }
        }
        foreach ($result['rows'] as $row) {
            $byId[(string) $row['id']] = $row;
        }
        saveCustomCctvs(array_values($byId));
        $message = sprintf(
            '高公局官方 CCTV 已匯入 %d 筆；官方 XML 掃到 %d 筆。',
            count($result['rows']),
            (int) $result['scanned']
        );
        if (($result['errors'] ?? []) !== []) {
            $message .= ' 略過 ' . count($result['errors']) . ' 筆資料不完整項目。';
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'import_1968_highways') {
    try {
        $routeText = (string) ($_POST['highway1968_routes'] ?? '');
        $routeIds = preg_split('/[\s,，]+/u', $routeText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $result = highway1968ImportRoutes($routeIds);
        $rows = loadCustomCctvs();
        $byId = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $byId[(string) $row['id']] = $row;
            }
        }
        foreach ($result['rows'] as $row) {
            $byId[(string) $row['id']] = $row;
        }
        saveCustomCctvs(array_values($byId));
        $message = sprintf(
            '省道快速道路影像已匯入 %d 筆；掃到 %d 筆。',
            count($result['rows']),
            (int) $result['scanned']
        );
        if (($result['errors'] ?? []) !== []) {
            $message .= ' 略過 ' . count($result['errors']) . ' 筆資料不完整項目。';
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'import_gov_taiwan') {
    try {
        $result = govTaiwanImportVideos(
            (string) ($_POST['gov_taiwan_url'] ?? ''),
            (int) ($_POST['gov_taiwan_limit'] ?? 20),
            (string) ($_POST['gov_taiwan_keyword'] ?? '')
        );
        $rows = loadCustomCctvs();
        $byId = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $byId[(string) $row['id']] = $row;
            }
        }
        foreach ($result['rows'] as $row) {
            $byId[(string) $row['id']] = $row;
        }
        saveCustomCctvs(array_values($byId));
        $message = sprintf(
            '我的E政府景點影像已匯入 %d 筆；掃到 %d 筆，處理 %d 筆。',
            count($result['rows']),
            (int) $result['scanned'],
            (int) $result['processed']
        );
        if (($result['errors'] ?? []) !== []) {
            $message .= ' 略過 ' . count($result['errors']) . ' 筆缺少座標或影像網址項目。';
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($authenticated && ($_POST['action'] ?? '') === 'import_tainan_cctv') {
    try {
        $result = tainanCctvRows((int) ($_POST['tainan_limit'] ?? 1000));
        $rows = loadCustomCctvs();
        $byId = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $byId[(string) $row['id']] = $row;
            }
        }
        foreach ($result['rows'] as $row) {
            $byId[(string) $row['id']] = $row;
        }
        saveCustomCctvs(array_values($byId));
        $message = sprintf(
            '臺南市 CCTV 已匯入 %d 筆；官方 API 掃到 %d 筆，處理 %d 筆。',
            count($result['rows']),
            (int) $result['scanned'],
            (int) $result['processed']
        );
        if (($result['errors'] ?? []) !== []) {
            $message .= ' 略過 ' . count($result['errors']) . ' 筆資料不完整項目。';
        }
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (($_GET['diagnose'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'authenticated' => $authenticated,
        'has_admin_password' => $adminPassword !== '',
        'post_action' => $_POST['action'] ?? '',
        'post_keys' => array_keys($_POST),
        'saved' => $saved,
        'config_path' => sourceConfigPath(),
        'config_writable' => is_writable(sourceConfigPath()) || is_writable(dirname(sourceConfigPath())),
        'monetization_path' => monetizationConfigPath(),
        'monetization_writable' => is_writable(monetizationConfigPath()) || is_writable(dirname(monetizationConfigPath())),
        'custom_cctv_path' => customCctvPath(),
        'custom_cctv_writable' => is_writable(customCctvPath()) || is_writable(dirname(customCctvPath())),
        'custom_cctv_count' => count(loadCustomCctvs()),
        'cctv_submission_path' => cctvSubmissionPath(),
        'cctv_submission_writable' => is_writable(cctvSubmissionPath()) || is_writable(dirname(cctvSubmissionPath())),
        'cctv_submission_count' => count(loadCctvSubmissions()),
        'gov_geocode_path' => govTaiwanGeocodeCachePath(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$customCctvs = ($authenticated && $activeTab === 'cctv') ? loadCustomCctvs() : [];
$cctvReports = ($authenticated && $activeTab === 'reports') ? loadCctvReports() : [];
$cctvReportStatuses = cctvReportStatuses();
$cctvReportTypeRows = ($authenticated && $activeTab === 'reports') ? loadCctvReportTypeRows() : [];
$cctvReportSummary = cctvReportSummary($cctvReports);
$cctvSubmissions = ($authenticated && $activeTab === 'submissions') ? loadCctvSubmissions() : [];
$cctvSubmissionStatuses = cctvSubmissionStatuses();
$cctvSubmissionSummary = cctvSubmissionSummary($cctvSubmissions);
$realpriceRecordFilters = [
    'keyword' => trim((string) ($_GET['realprice_q'] ?? '')),
    'city' => trim((string) ($_GET['realprice_city'] ?? '')),
    'type' => trim((string) ($_GET['realprice_type'] ?? '')),
    'status' => trim((string) ($_GET['realprice_status'] ?? '')),
    'limit' => (int) ($_GET['realprice_limit'] ?? 50),
    'page' => max(1, (int) ($_GET['realprice_page'] ?? 1)),
];
$realpriceShouldSearch = $authenticated
    && $activeTab === 'realprice'
    && $realpriceSub === 'records'
    && ($realpriceStatus['index_exists'] ?? false)
    && (
        $realpriceRecordFilters['keyword'] !== ''
        || $realpriceRecordFilters['city'] !== ''
        || $realpriceRecordFilters['type'] !== ''
        || $realpriceRecordFilters['status'] !== ''
        || isset($_GET['realprice_search'])
    );
if ($realpriceShouldSearch) {
    try {
        $realpriceRecordSearch = realpriceSearchRecords($realpriceRecordFilters);
    } catch (Throwable $e) {
        $realpriceRecordSearch = [
            'rows' => [],
            'matched_count' => 0,
            'returned_count' => 0,
            'error' => '查詢失敗：' . $e->getMessage(),
            'page' => 1,
            'page_count' => 1,
            'limit' => (int) $realpriceRecordFilters['limit'],
            'counts' => ['done' => 0, 'approx' => 0, 'missing' => 0],
            'record_count' => (int) ($realpriceStatus['indexed_count'] ?? 0),
            'cache_count' => 0,
            'generated_at' => '',
        ];
    }
}
$editId = (string) ($_GET['edit_cctv'] ?? '');
$editingCctv = null;
foreach ($customCctvs as $row) {
    if (($row['id'] ?? '') === $editId) {
        $editingCctv = $row;
        break;
    }
}
$customCctvSources = [];
foreach ($customCctvs as $row) {
    $source = inferCctvSource(
        (string) ($row['stream_url'] ?? ''),
        (string) ($row['source_key'] ?? 'custom'),
        (string) ($row['source_label'] ?? '自訂監視器')
    );
    $key = (string) $source['key'];
    if (!isset($customCctvSources[$key])) {
        $customCctvSources[$key] = [
            'key' => $key,
            'label' => (string) $source['label'],
            'count' => 0,
        ];
    }
    $customCctvSources[$key]['count']++;
}
uasort($customCctvSources, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function jsonAttr(array $value): string
{
    return htmlspecialchars((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}

function searchTextLower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Open Live Map Admin</title>
    <link rel="icon" href="../assets/images/favicon-32.png" type="image/png" sizes="32x32">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+TC:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= h((string) (@filemtime(dirname(__DIR__) . '/assets/css/admin.css') ?: time())) ?>">
</head>
<body>
<div class="admin-atmosphere" aria-hidden="true"></div>
<main class="admin-shell">
    <header class="admin-topbar">
        <div class="admin-brand">
            <span class="admin-brand-kicker">Open Live Map</span>
            <h1>管理控制台</h1>
            <p><?= $authenticated
                ? '資料來源、監視器、實價登錄、收益與站台狀態，集中在同一個沉浸式工作區。'
                : '地圖營運後台。請登入後管理資料、監看與站台設定。' ?></p>
        </div>
        <nav class="admin-top-links" aria-label="快捷連結">
            <a href="../">回地圖</a>
            <?php if ($authenticated): ?>
                <a href="?logout=1">登出</a>
            <?php endif; ?>
        </nav>
    </header>

    <?php if ($message !== ''): ?><div class="notice ok"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($adminPassword === ''): ?><div class="notice warn">尚未設定 <code>admin_password</code>，目前管理頁未受密碼保護。建議在 <code>config.local.php</code> 加上管理密碼。</div><?php endif; ?>

    <?php if (!$authenticated): ?>
        <form class="card login-card" method="post">
            <input type="hidden" name="action" value="login">
            <div class="login-card-copy">
                <h2>進入控制台</h2>
                <p>輸入管理密碼以管理地圖資料、監視器與站台設定。</p>
            </div>
            <label class="login-field">
                <span>管理密碼</span>
                <input type="password" name="password" autocomplete="current-password" autofocus placeholder="••••••••">
            </label>
            <div class="actions">
                <button class="primary" type="submit">登入</button>
            </div>
        </form>
    <?php else: ?>
        <nav class="admin-tabs" aria-label="管理頁籤">
            <?php foreach ($adminTabs as $tabKey => $tabLabel): ?>
                <a class="admin-tab<?= $activeTab === $tabKey ? ' active' : '' ?>" href="?tab=<?= h($tabKey) ?>"><?= h($tabLabel) ?></a>
            <?php endforeach; ?>
        </nav>
        <p class="tab-note">目前頁籤：<?= h($adminTabs[$activeTab]) ?> · 切換後才會載入其他模組</p>

        <?php if ($activeTab === 'sources'): ?>
        <form class="card" method="post">
            <input type="hidden" name="action" value="save">
            <table>
                <thead>
                    <tr>
                        <th>資料來源</th>
                        <th>前台啟用</th>
                        <th>預設勾選</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $key => $source): ?>
                        <tr>
                            <td>
                                <strong><?= h((string) $source['label']) ?></strong>
                                <small><?= h((string) $source['description']) ?></small>
                            </td>
                            <td><input type="checkbox" name="enabled[<?= h($key) ?>]"<?= checkedAttr((bool) $source['enabled']) ?>></td>
                            <td><input type="checkbox" name="default_checked[<?= h($key) ?>]"<?= checkedAttr((bool) $source['default_checked']) ?>></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="actions">
                <button class="primary" type="submit">儲存設定</button>
                <a class="button" href="../">查看地圖</a>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($activeTab === 'analytics'): ?>
        <h2 class="section-title">造訪記錄</h2>
        <section class="card">
            <div class="analytics-toolbar">
                <p>第一方本機記錄，IP 以雜湊保存；國家優先讀取代理或 Cloudflare 的國別標頭。</p>
                <div class="actions actions-flush">
                    <a class="button" href="?tab=analytics&analytics_days=7">7 天</a>
                    <a class="button" href="?tab=analytics&analytics_days=14">14 天</a>
                    <a class="button" href="?tab=analytics&analytics_days=30">30 天</a>
                </div>
            </div>
            <div class="metric-grid">
                <div class="metric"><small>期間</small><strong><?= h((string) ($analyticsReport['days'] ?? $analyticsDays)) ?> 天</strong></div>
                <div class="metric"><small>總造訪</small><strong><?= h(number_format((int) ($analyticsReport['visits'] ?? 0))) ?></strong></div>
                <div class="metric"><small>唯一訪客</small><strong><?= h(number_format((int) ($analyticsReport['unique_visitors'] ?? 0))) ?></strong></div>
                <div class="metric"><small>機器人</small><strong><?= h(number_format((int) ($analyticsReport['bots'] ?? 0))) ?></strong></div>
            </div>
            <div class="analytics-grid">
                <div class="analytics-panel">
                    <h3>國家</h3>
                    <ul class="analytics-list">
                        <?php foreach (($analyticsReport['countries'] ?? []) as $item): ?>
                            <li><b><?= h((string) $item['label']) ?></b><span><?= h(number_format((int) $item['count'])) ?></span></li>
                        <?php endforeach; ?>
                        <?php if (empty($analyticsReport['countries'])): ?><li><b>尚無資料</b><span>0</span></li><?php endif; ?>
                    </ul>
                </div>
                <div class="analytics-panel">
                    <h3>瀏覽器</h3>
                    <ul class="analytics-list">
                        <?php foreach (($analyticsReport['browsers'] ?? []) as $item): ?>
                            <li><b><?= h((string) $item['label']) ?></b><span><?= h(number_format((int) $item['count'])) ?></span></li>
                        <?php endforeach; ?>
                        <?php if (empty($analyticsReport['browsers'])): ?><li><b>尚無資料</b><span>0</span></li><?php endif; ?>
                    </ul>
                </div>
                <div class="analytics-panel">
                    <h3>作業系統</h3>
                    <ul class="analytics-list">
                        <?php foreach (($analyticsReport['oses'] ?? []) as $item): ?>
                            <li><b><?= h((string) $item['label']) ?></b><span><?= h(number_format((int) $item['count'])) ?></span></li>
                        <?php endforeach; ?>
                        <?php if (empty($analyticsReport['oses'])): ?><li><b>尚無資料</b><span>0</span></li><?php endif; ?>
                    </ul>
                </div>
                <div class="analytics-panel">
                    <h3>裝置</h3>
                    <ul class="analytics-list">
                        <?php foreach (($analyticsReport['devices'] ?? []) as $item): ?>
                            <li><b><?= h((string) $item['label']) ?></b><span><?= h(number_format((int) $item['count'])) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="analytics-panel">
                    <h3>機器人</h3>
                    <ul class="analytics-list">
                        <?php foreach (($analyticsReport['bots_by_name'] ?? []) as $item): ?>
                            <li><b><?= h((string) $item['label']) ?></b><span><?= h(number_format((int) $item['count'])) ?></span></li>
                        <?php endforeach; ?>
                        <?php if (empty($analyticsReport['bots_by_name'])): ?><li><b>尚無機器人</b><span>0</span></li><?php endif; ?>
                    </ul>
                </div>
                <div class="analytics-panel">
                    <h3>熱門路徑</h3>
                    <ul class="analytics-list">
                        <?php foreach (($analyticsReport['paths'] ?? []) as $item): ?>
                            <li><b><?= h((string) $item['label']) ?></b><span><?= h(number_format((int) $item['count'])) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="analytics-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>時間</th>
                            <th>國家</th>
                            <th>類型</th>
                            <th>瀏覽器</th>
                            <th>系統</th>
                            <th>裝置</th>
                            <th>路徑</th>
                            <th>來源</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($analyticsReport['recent'] ?? []) as $row): ?>
                            <tr>
                                <td><?= h((string) ($row['ts'] ?? '')) ?></td>
                                <td><?= h((string) ($row['country_name'] ?? $row['country'] ?? '未知')) ?></td>
                                <td><?= !empty($row['is_bot']) ? '機器人：' . h((string) ($row['bot'] ?? 'Crawler')) : '一般訪客' ?></td>
                                <td><?= h((string) ($row['browser'] ?? '未知')) ?></td>
                                <td><?= h((string) ($row['os'] ?? '未知')) ?></td>
                                <td><?= h((string) ($row['device'] ?? '未知')) ?></td>
                                <td><?= h((string) ($row['path'] ?? '/')) ?></td>
                                <td><?= h((string) (($row['ref_host'] ?? '') ?: '直接進入')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($analyticsReport['recent'])): ?>
                            <tr><td colspan="8">尚無造訪記錄。前台被開啟後會開始累積。</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'realprice'): ?>
        <nav class="admin-subtabs" aria-label="實價登錄功能">
            <?php foreach ($realpriceSubTabs as $subKey => $subLabel): ?>
                <a class="admin-subtab<?= $realpriceSub === $subKey ? ' active' : '' ?>" href="?tab=realprice&rp=<?= h($subKey) ?>"><?= h($subLabel) ?></a>
            <?php endforeach; ?>
        </nav>
        <p class="tab-note">實價登錄 › <?= h($realpriceSubTabs[$realpriceSub] ?? $realpriceSub) ?></p>

        <?php if ($realpriceSub === 'log'): ?>
        <h2 class="section-title">操作日誌</h2>
        <section class="card">
            <p class="muted mt-0">
                記錄實價登錄下載、索引重建、門牌比對開始／完成／取消、覆蓋統計等操作（最近約 80 筆）。檔案：
                <code><?= h(realpriceOpLogPath()) ?></code>
            </p>
            <?php if ($realpriceOpLogs === []): ?>
                <div class="notice">尚無操作紀錄。之後執行下載、比對或重建時會自動寫入。</div>
            <?php else: ?>
                <div class="report-table-wrap max-h-compact">
                    <table>
                        <thead>
                            <tr>
                                <th>時間</th>
                                <th>動作</th>
                                <th>結果</th>
                                <th>訊息</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($realpriceOpLogs as $logRow): ?>
                                <?php
                                $level = (string) ($logRow['level'] ?? 'info');
                                $levelLabel = match ($level) {
                                    'ok' => '成功',
                                    'error' => '失敗',
                                    'warn' => '取消／警告',
                                    default => '資訊',
                                };
                                ?>
                                <tr>
                                    <td><small><?= h((string) ($logRow['at'] ?? '')) ?></small></td>
                                    <td><code><?= h((string) ($logRow['action'] ?? '')) ?></code></td>
                                    <td>
                                        <span class="status-pill status-pill--<?= h($level === 'ok' ? 'ok' : ($level === 'error' ? 'error' : ($level === 'warn' ? 'warn' : 'info'))) ?>"><?= h($levelLabel) ?></span>
                                    </td>
                                    <td><small><?= h((string) ($logRow['message'] ?? '')) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <div class="actions">
                <form method="post" onsubmit="return confirm('確定清空操作日誌？');">
                    <input type="hidden" name="action" value="clear_realprice_oplog">
                    <button class="button" type="submit">清空日誌</button>
                </form>
            </div>
        </section>
        <?php endif; /* log */ ?>

        <?php if ($realpriceSub === 'data'): ?>
        <h2 class="section-title">實價登錄資料</h2>
        <?php
        $localSeasons = is_array($realpriceStatus['local_seasons'] ?? null) ? $realpriceStatus['local_seasons'] : [];
        $seasonZipCount = (int) ($realpriceStatus['season_zip_count'] ?? count($localSeasons));
        $canRebuildFromLocal = $seasonZipCount > 0 || !empty($realpriceStatus['zip_exists']);
        ?>
        <section class="card">
            <p>
                手動放入的歷史季 ZIP（例如 <code>111S1.zip</code>…）放在
                <code><?= h((string) ($realpriceStatus['seasons_dir'] ?? realpriceSeasonsDir())) ?></code>
                後，<strong>不必再「接上」別的介面</strong>——按下方「用本機季度 ZIP 重建索引」就會把目錄內<strong>全部</strong>有效 ZIP 合併進地圖索引。
            </p>
            <div class="form-grid">
                <label>
                    本期 ZIP
                    <input type="text" value="<?= $realpriceStatus['zip_exists'] ? ('已下載 ' . realpriceHumanSize((int) $realpriceStatus['zip_size'])) : '尚未下載' ?>" readonly>
                </label>
                <label>
                    本機季度 ZIP
                    <input type="text" value="<?= h(number_format($seasonZipCount) . ' 個') ?>" readonly>
                </label>
                <label>
                    下載／更新時間
                    <input type="text" value="<?= h((string) ($realpriceStatus['downloaded_at'] ?: '尚未由後台下載')) ?>" readonly>
                </label>
                <label>
                    索引狀態
                    <input type="text" value="<?= ((int) $realpriceStatus['indexed_count'] > 0) ? h(number_format((int) $realpriceStatus['indexed_count']) . ' 筆 / ' . (string) ($realpriceStatus['indexed_at'] ?: '時間未知')) : '尚未建立索引（請重建）' ?>" readonly>
                </label>
                <label>
                    索引大小
                    <input type="text" value="<?= h(realpriceHumanSize((int) ($realpriceStatus['index_size'] ?? 0))) ?>" readonly>
                </label>
                <label class="wide">
                    狀態說明
                    <input type="text" value="<?= h((string) (($realpriceStatus['notice'] ?? '') ?: '尚無')) ?>" readonly>
                </label>
            </div>
            <?php if ($localSeasons !== []): ?>
                <div class="analytics-table-wrap mt-tight">
                    <table id="realpriceSeasonTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="realpriceSeasonCheckAll" checked title="全選／取消"></th>
                                <th>季度</th>
                                <th>檔名</th>
                                <th>大小</th>
                                <th>檔案時間</th>
                                <th>索引狀態</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($localSeasons as $seasonRow): ?>
                                <?php
                                $st = (string) ($seasonRow['index_status'] ?? 'pending');
                                $stLabel = match ($st) {
                                    'ok' => '已索引',
                                    'stale' => '需更新',
                                    'empty' => '0 筆',
                                    default => '未索引',
                                };
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="realprice-season-check" value="<?= h((string) ($seasonRow['file'] ?? '')) ?>" checked>
                                    </td>
                                    <td><?= h((string) ($seasonRow['season'] ?? '')) ?></td>
                                    <td><code><?= h((string) ($seasonRow['file'] ?? '')) ?></code></td>
                                    <td><?= h(realpriceHumanSize((int) ($seasonRow['size'] ?? 0))) ?></td>
                                    <td><?= h((string) ($seasonRow['mtime'] ?? '')) ?></td>
                                    <td>
                                        <span class="season-index-status is-<?= h($st) ?>"><?= h($stLabel) ?></span>
                                        <small class="muted"> <?= h((string) ($seasonRow['index_note'] ?? '')) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="muted">勾選要納入的季度後再重建。<strong>勾選部分季度會合併進既有索引</strong>（已完成的季會保留）。若要從頭重做全部，請用「全部季度重建」。</p>
            <?php else: ?>
                <p class="muted">目前 <code>seasons/</code> 目錄尚無有效 ZIP。可從官方下載，或手動複製 <code>YYYYsN.zip</code> 進去。</p>
            <?php endif; ?>
            <?php
            $indexJob = realpriceLoadJsonFile(realpriceIndexJobStatePath(), []);
            $indexJobRunning = (($indexJob['status'] ?? '') === 'running');
            ?>
            <div class="doorplate-upload-progress" id="realpriceIndexProgress"<?= $indexJobRunning ? '' : ' hidden' ?>>
                <div class="doorplate-upload-bar"><span id="realpriceIndexBar" style="width:<?= (int) ($indexJob['pct'] ?? 0) ?>%"></span></div>
                <p class="muted" id="realpriceIndexStatusText"><?= h((string) ($indexJob['message'] ?? '準備重建索引…')) ?></p>
            </div>
            <div class="actions mt-tight">
                <button class="primary" type="button" id="realpriceIndexBtn"<?= $canRebuildFromLocal ? '' : ' disabled' ?>>重建勾選季度索引</button>
                <button class="button" type="button" id="realpriceIndexAllBtn"<?= $canRebuildFromLocal ? '' : ' disabled' ?>>全部季度重建</button>
                <button class="button" type="button" id="realpriceIndexCancelBtn"<?= $indexJobRunning ? '' : ' hidden' ?>>取消重建</button>
            </div>
            <p class="muted">會<strong>逐季 AJAX</strong>重建並顯示進度，避免 Cloudflare／Synology 504。完成後各季狀態與總筆數會更新。</p>
        </section>
        <form class="card compact-card js-busy-submit" method="post"
              data-busy-label="下載中…"
              data-busy-status="正在下載近 5 季 ZIP，檔案較大請耐心等候…">
            <input type="hidden" name="action" value="download_realprice">
            <p>
                也可由後台自動下載內政部<strong>近 5 個季度</strong>（會覆寫同名季檔）。下載完成後請再按上方「重建索引」分批建立，避免 504。手動已放好的較舊季不會被刪掉。
            </p>
            <div class="doorplate-upload-progress form-busy-progress" hidden>
                <div class="doorplate-upload-bar form-busy-bar is-indeterminate"><span></span></div>
                <p class="muted form-busy-status" aria-live="polite">準備下載…</p>
            </div>
            <div class="actions">
                <button class="button" type="submit">下載近 5 季 ZIP</button>
                <a class="button" href="https://plvr.land.moi.gov.tw/DownloadOpenData" target="_blank" rel="noopener">開啟官方開放資料</a>
            </div>
        </form>
        <?php endif; /* data (main) */ ?>

        <?php if ($realpriceSub === 'coverage'): ?>
        <section class="card" id="realpriceCoverageCard">
            <h3 class="mt-0">定位覆蓋一覽（已比對 / 未比對）</h3>
            <p class="muted mt-0">
                以索引中的<strong>不重複地址</strong>統計。精準＝門牌／手動座標可畫單點；尚待比對＝還沒有精準門牌座標（地圖縮放後可能只看到縣市氣泡或行政區彙總）。
            </p>
            <p class="muted" id="coverageLiveNote" hidden>比對進行中：下方數字為即時估算，完成後請再按「重新統計覆蓋」校正。</p>
            <?php if ((string) ($realpriceCoverage['message'] ?? '') !== ''): ?>
                <div class="notice" id="coverageNotice"><?= h((string) $realpriceCoverage['message']) ?></div>
            <?php endif; ?>
            <div class="form-grid" id="coverageSummaryGrid">
                <label>
                    不重複地址
                    <input type="text" id="coverageUnique" value="<?= h(number_format((int) ($realpriceCoverage['unique_addresses'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    已精準定位
                    <input type="text" id="coveragePrecise" value="<?= h(number_format((int) ($realpriceCoverage['precise_count'] ?? 0)) . ' 筆（' . (string) ($realpriceCoverage['precise_pct'] ?? 0) . '%）') ?>" readonly data-precise="<?= (int) ($realpriceCoverage['precise_count'] ?? 0) ?>" data-unique="<?= (int) ($realpriceCoverage['unique_addresses'] ?? 0) ?>">
                </label>
                <label>
                    尚待門牌比對
                    <input type="text" id="coverageNeed" value="<?= h(number_format((int) ($realpriceCoverage['need_match_count'] ?? 0)) . ' 筆') ?>" readonly data-need="<?= (int) ($realpriceCoverage['need_match_count'] ?? 0) ?>">
                </label>
                <label>
                    僅有行政區近似
                    <input type="text" id="coverageDistrict" value="<?= h(number_format((int) ($realpriceCoverage['district_fallback_count'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    完全無座標
                    <input type="text" id="coverageMissing" value="<?= h(number_format((int) ($realpriceCoverage['missing_count'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    統計時間
                    <input type="text" id="coverageGeneratedAt" value="<?= h((string) (($realpriceCoverage['generated_at'] ?? '') ?: '尚未統計')) ?>" readonly>
                </label>
            </div>
            <?php
            $providers = is_array($realpriceCoverage['by_provider'] ?? null) ? $realpriceCoverage['by_provider'] : [];
            $providerLabels = [
                'doorplate_opendata' => '本機門牌',
                'tgos' => 'TGOS',
                'nominatim' => 'Nominatim',
                'local_override' => '手動座標',
            ];
            ?>
            <?php if ($providers !== []): ?>
                <p class="muted" id="coverageProviders">精準來源：<?php
                    $bits = [];
                    foreach ($providers as $provider => $count) {
                        $label = $providerLabels[$provider] ?? (string) $provider;
                        $bits[] = $label . ' ' . number_format((int) $count);
                    }
                    echo h(implode('、', $bits));
                ?></p>
            <?php else: ?>
                <p class="muted" id="coverageProviders" hidden></p>
            <?php endif; ?>
            <div class="analytics-table-wrap mt-tight">
                <table id="coverageCityTable">
                    <thead>
                        <tr>
                            <th>縣市</th>
                            <th>地址數</th>
                            <th>已精準</th>
                            <th>尚待比對</th>
                            <th>本機門牌</th>
                            <th>TGOS</th>
                            <th>Nominatim</th>
                            <th>手動</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($realpriceCoverage['by_city'] ?? []) as $row): ?>
                            <?php $cityName = (string) ($row['city'] ?? ''); ?>
                            <tr data-city="<?= h($cityName) ?>">
                                <td><?= h($cityName) ?></td>
                                <td data-col="total"><?= h(number_format((int) ($row['total'] ?? 0))) ?></td>
                                <td data-col="precise"><?= h(number_format((int) ($row['precise'] ?? 0))) ?></td>
                                <td data-col="need_match"><?= h(number_format((int) ($row['need_match'] ?? 0))) ?></td>
                                <td data-col="doorplate"><?= h(number_format((int) ($row['doorplate'] ?? 0))) ?></td>
                                <td data-col="tgos"><?= h(number_format((int) ($row['tgos'] ?? 0))) ?></td>
                                <td data-col="nominatim"><?= h(number_format((int) ($row['nominatim'] ?? 0))) ?></td>
                                <td data-col="manual"><?= h(number_format((int) ($row['manual'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($realpriceCoverage['by_city'])): ?>
                            <tr><td colspan="8">尚無資料。請先下載 ZIP 並重建索引。</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <form method="post" class="actions mt-tight js-busy-submit" id="coverageRefreshForm"
                  data-busy-label="統計中…"
                  data-busy-status="正在分批統計覆蓋，請稍候…"
                  data-busy-mode="coverage-refresh">
                <input type="hidden" name="action" value="refresh_realprice_coverage">
                <div class="doorplate-upload-progress form-busy-progress" hidden>
                    <div class="doorplate-upload-bar form-busy-bar is-indeterminate"><span></span></div>
                    <p class="muted form-busy-status" aria-live="polite">準備統計…</p>
                </div>
                <button class="button" type="submit">重新統計覆蓋</button>
                <button class="button ghost" type="button" id="coverageRefreshCancelBtn" hidden>取消</button>
            </form>
            <p class="muted">
                覆蓋統計會分批掃描索引（避免 504）。完成後縣市精準數／TGOS 數才會更新。
            </p>
        </section>
        <?php endif; /* coverage */ ?>

        <?php if ($realpriceSub === 'data'): ?>
        <details class="admin-fold">
            <summary>實價登錄進階維護</summary>
            <div class="admin-fold-body">
                <section class="card compact-card">
                    <p>除錯資訊與低頻操作。一般只需要使用上方「下載 / 更新 ZIP 並重建索引」。</p>
                    <div class="form-grid">
                        <label class="span-2">
                            官方下載來源
                            <input type="text" value="<?= h((string) $realpriceStatus['download_url']) ?>" readonly>
                        </label>
                        <label>
                            官方檢查時間
                            <input type="text" value="<?= h((string) (($realpriceStatus['remote_checked_at'] ?? '') ?: '尚未檢查')) ?>" readonly>
                        </label>
                        <label>
                            官方檔案大小
                            <input type="text" value="<?= h(realpriceHumanSize((int) ($realpriceStatus['remote_content_length'] ?? 0))) ?>" readonly>
                        </label>
                        <label class="span-2">
                            本機保存位置
                            <input type="text" value="<?= h((string) $realpriceStatus['zip_path']) ?>" readonly>
                        </label>
                        <label>
                            官方 Last-Modified
                            <input type="text" value="<?= h((string) (($realpriceStatus['remote_last_modified'] ?? '') ?: '未提供')) ?>" readonly>
                        </label>
                        <label>
                            官方 ETag
                            <input type="text" value="<?= h((string) (($realpriceStatus['remote_etag'] ?? '') ?: '未提供')) ?>" readonly>
                        </label>
                    </div>
                </section>
                <form class="card compact-card js-busy-submit" method="post"
                      data-busy-label="檢查中…"
                      data-busy-status="正在檢查官方是否有更新…">
                    <input type="hidden" name="action" value="check_realprice">
                    <p>只讀取官方檔案資訊，不下載 ZIP。</p>
                    <div class="doorplate-upload-progress form-busy-progress" hidden>
                        <div class="doorplate-upload-bar form-busy-bar is-indeterminate"><span></span></div>
                        <p class="muted form-busy-status" aria-live="polite">準備檢查…</p>
                    </div>
                    <div class="actions">
                        <button class="button" type="submit">檢查官方更新</button>
                    </div>
                </form>
                <div class="card compact-card">
                    <p>與上方相同：掃描 <code>seasons/*.zip</code>（含手動檔）與本期 ZIP，分批重建地圖索引。</p>
                    <div class="actions">
                        <button class="button" type="button" id="realpriceIndexBtnAdvanced"<?= ((int) ($realpriceStatus['season_zip_count'] ?? 0) > 0 || !empty($realpriceStatus['zip_exists'])) ? '' : ' disabled' ?>>只重建索引（分批）</button>
                    </div>
                </div>
            </div>
        </details>
        <?php endif; /* data (advanced) */ ?>

        <?php if ($realpriceSub === 'doorplate'): ?>
        <h2 class="section-title">本機門牌開放資料定位（建議優先）</h2>
        <section class="card">
            <p>
                已列出<strong>全國 22 縣市</strong>門牌來源（不只六都）。實價登錄本身涵蓋全國，但門牌座標要靠各地方政府開放資料。
                <strong>可自動下載</strong>：臺北、新北、臺南、澎湖、臺中（Google Drive 月檔）；其餘多為手動上傳／複製。
                基隆／嘉義市／南投／宜蘭／連江等開放較慢，有檔一樣可上傳比對。
            </p>
            <?php if ((string) ($doorplateStatus['last_message'] ?? '') !== ''): ?>
                <div class="notice"><?= h((string) $doorplateStatus['last_message']) ?></div>
            <?php endif; ?>
            <?php
            $matchJob = is_array($doorplateStatus['job'] ?? null) ? $doorplateStatus['job'] : [];
            if (($matchJob['status'] ?? '') === 'running'):
            ?>
                <div class="notice">分批比對進行中：<?= h((string) ($matchJob['message'] ?? '處理中…')) ?></div>
            <?php elseif (($matchJob['status'] ?? '') === 'done' && (string) ($matchJob['message'] ?? '') !== ''): ?>
                <div class="notice">上次比對結果：<?= h((string) $matchJob['message']) ?></div>
            <?php endif; ?>
            <div class="analytics-table-wrap mt-tight">
                <table id="doorplateCityStatusTable">
                    <thead>
                        <tr>
                            <th>縣市</th>
                            <th>取得方式</th>
                            <th>本機檔案</th>
                            <th>上次比對</th>
                            <th>寫入／掃描</th>
                            <th>資料集</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($doorplateStatus['cities'] ?? []) as $city): ?>
                            <?php
                            $hasFile = (bool) ($city['file_exists'] ?? false);
                            $matchedAt = (string) ($city['matched_at'] ?? '');
                            $manualOnly = (bool) ($city['manual_only'] ?? false);
                            $note = trim((string) ($city['note'] ?? ''));
                            $getLabel = $manualOnly ? '手動上傳' : '可自動下載';
                            ?>
                            <tr>
                                <td><?= h((string) ($city['label'] ?? $city['key'])) ?></td>
                                <td>
                                    <span class="season-index-status <?= $manualOnly ? 'is-pending' : 'is-ok' ?>"><?= h($getLabel) ?></span>
                                    <?php if ($note !== ''): ?>
                                        <small class="muted"><?= h($note) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasFile): ?>
                                        <span class="season-index-status is-ok">已有</span>
                                        <?= h(realpriceHumanSize((int) ($city['file_size'] ?? 0))) ?>
                                    <?php else: ?>
                                        <span class="season-index-status is-pending">尚未下載</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($matchedAt !== '' ? date('Y-m-d H:i', strtotime($matchedAt) ?: time()) : '尚未比對') ?></td>
                                <td>
                                    <?php if ($matchedAt !== ''): ?>
                                        <?= h(number_format((int) ($city['matched_count'] ?? 0))) ?>／<?= h(number_format((int) ($city['scanned_rows'] ?? 0))) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((string) ($city['dataset'] ?? '') !== ''): ?>
                                        <a href="<?= h((string) $city['dataset']) ?>" target="_blank" rel="noopener">開啟</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="muted">此表只看<strong>各縣市門牌檔是否就緒、上次比對寫入多少</strong>；真正執行比對請用下方勾選縣市後按「執行本機門牌比對」。</p>
        </section>
        <form class="card compact-card js-busy-submit" method="post"
              data-busy-label="下載中…"
              data-busy-status="正在下載門牌開放資料，檔案可能很大請耐心等候…">
            <input type="hidden" name="action" value="download_doorplate">
            <p>下載可直連的縣市門牌 CSV（檔案可能數十到上百 MB，請耐心等候）。</p>
            <div class="form-grid">
                <label>
                    縣市
                    <select name="city">
                        <?php foreach (($doorplateStatus['cities'] ?? []) as $city): ?>
                            <?php if (!(bool) ($city['manual_only'] ?? false)): ?>
                                <option value="<?= h((string) $city['key']) ?>"><?= h((string) $city['label']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="doorplate-upload-progress form-busy-progress" hidden>
                <div class="doorplate-upload-bar form-busy-bar is-indeterminate"><span></span></div>
                <p class="muted form-busy-status" aria-live="polite">準備下載…</p>
            </div>
            <div class="actions">
                <button class="primary" type="submit">下載門牌開放資料</button>
            </div>
        </form>
        <form class="card compact-card" method="post" enctype="multipart/form-data" id="doorplateUploadForm">
            <input type="hidden" name="action" value="upload_doorplate">
            <p>
                手動上傳門牌 CSV（適用桃園／臺中／高雄）。下載檔常是 UUID 檔名（例如 <code>81b12339-….csv</code>），<strong>不必改名</strong>：下方選好縣市即可，系統會存成固定名稱。
                檔案很大、網頁上傳失敗時，請改用本機複製（見下方路徑）。
            </p>
            <div class="form-grid">
                <label>
                    縣市
                    <select name="city" id="doorplateUploadCity">
                        <?php foreach (($doorplateStatus['cities'] ?? []) as $city): ?>
                            <option value="<?= h((string) $city['key']) ?>"><?= h((string) $city['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="wide">
                    CSV 檔（原始檔名可任意）
                    <input type="file" name="doorplate_csv" id="doorplateUploadFile" accept=".csv,text/csv">
                </label>
                <label class="wide">
                    系統會存成（指定名稱）
                    <input type="text" id="doorplateTargetName" value="kaohsiung.csv" readonly>
                </label>
                <label class="wide">
                    本機複製目錄
                    <input type="text" value="<?= h(doorplateDir()) ?>" readonly>
                </label>
            </div>
            <p class="muted" id="doorplateCopyHint">
                大檔建議：把 CSV <strong>重新命名</strong>成 <code id="doorplateCopyName">kaohsiung.csv</code>，複製到上方目錄後，按「登記本機檔案並比對」。
            </p>
            <div class="doorplate-upload-progress" id="doorplateUploadProgress" hidden>
                <div class="doorplate-upload-bar"><span id="doorplateUploadBar"></span></div>
                <p class="muted" id="doorplateUploadStatus">準備上傳…</p>
            </div>
            <div class="actions">
                <button class="button" type="submit" id="doorplateUploadBtn">上傳門牌 CSV 並自動比對</button>
                <button class="primary" type="button" id="doorplateRegisterBtn">登記本機檔案並比對</button>
            </div>
        </form>
        <form class="card compact-card" method="post" id="doorplateMatchForm">
            <p>將已下載／上傳的門牌資料，與實價登錄索引中「尚未有精準門牌座標」的案件做本機比對。系統會<strong>分批 AJAX</strong>執行，避免 Synology 504 逾時。</p>
            <div class="actions actions-flush" style="margin-bottom: 10px;">
                <button class="button" type="button" id="doorplateMatchSelectAllBtn">全選</button>
                <button class="button" type="button" id="doorplateMatchSelectNoneBtn">全部取消勾選</button>
                <button class="button" type="button" id="doorplateMatchSelectReadyBtn">只勾有檔案</button>
            </div>
            <div class="form-grid" id="doorplateMatchCityGrid">
                <?php foreach (($doorplateStatus['cities'] ?? []) as $city): ?>
                    <label>
                        <input type="checkbox" name="cities[]" value="<?= h((string) $city['key']) ?>" data-file-exists="<?= ((bool) ($city['file_exists'] ?? false)) ? '1' : '0' ?>"<?= ((bool) ($city['file_exists'] ?? false)) ? ' checked' : '' ?>>
                        <?= h((string) ($city['label'] ?? $city['key'])) ?><?= ((bool) ($city['file_exists'] ?? false)) ? '' : '（尚無檔）' ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php
            $matchJobRunning = ($matchJob['status'] ?? '') === 'running';
            $matchJobJson = json_encode($matchJob, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            ?>
            <script type="application/json" id="doorplateMatchJobBootstrap"><?= $matchJobJson !== false ? $matchJobJson : '{}' ?></script>
            <div class="doorplate-upload-progress" id="doorplateMatchProgress"<?= $matchJobRunning ? '' : ' hidden' ?>>
                <div class="doorplate-upload-bar"><span id="doorplateMatchBar"></span></div>
                <div class="match-live" id="doorplateMatchLive">
                    <div class="match-live-grid">
                        <div><small>階段</small><strong id="matchLivePhase">—</strong></div>
                        <div><small>縣市進度</small><strong id="matchLiveCity">—</strong></div>
                        <div><small>檔案進度</small><strong id="matchLiveFile">—</strong></div>
                        <div><small>本批</small><strong id="matchLiveBatch">—</strong></div>
                        <div><small>累計寫入</small><strong id="matchLiveMatched">—</strong></div>
                        <div><small>累計掃描</small><strong id="matchLiveScanned">—</strong></div>
                        <div><small>剩餘鍵</small><strong id="matchLiveRemain">—</strong></div>
                        <div><small>已跑批次</small><strong id="matchLiveRound">—</strong></div>
                    </div>
                    <ol class="match-live-log" id="doorplateMatchLog" aria-live="polite"></ol>
                </div>
                <p class="muted" id="doorplateMatchStatus">準備比對…</p>
            </div>
            <div class="actions">
                <button class="primary" type="button" id="doorplateMatchBtn"<?= $realpriceStatus['index_exists'] ? '' : ' disabled' ?>>執行本機門牌比對</button>
                <button class="button" type="button" id="doorplateMatchCancelBtn"<?= $matchJobRunning ? '' : ' disabled' ?>>取消比對</button>
            </div>
            <p class="muted">比對可隨時按「取消比對」停止；已寫入的座標會保留，之後可再只勾該縣市續跑。</p>
        </form>
        <?php endif; /* doorplate */ ?>

        <?php if ($realpriceSub === 'tgos'): ?>
        <h2 class="section-title">TGOS 批次門牌比對（備援）</h2>
        <section class="card">
            <p>
                本機門牌比對後仍缺的地址，可匯出成官方格式 CSV，到
                <a href="https://www.tgos.tw/tgos/Addr" target="_blank" rel="noopener">TGOS 批次門牌地址比對</a>
                上傳（需先申請 API KEY）。結果約 1～2 天以 email 通知，下載後在此匯入。每日上限約 <strong>1 萬筆</strong>。
            </p>
            <?php if ((string) ($tgosState['last_message'] ?? '') !== ''): ?>
                <div class="notice"><?= h((string) $tgosState['last_message']) ?></div>
            <?php endif; ?>
            <div class="form-grid">
                <label>
                    上次匯出
                    <input type="text" value="<?= h((string) (($tgosState['last_export_at'] ?? '') ?: '尚未匯出')) ?>" readonly>
                </label>
                <label>
                    上次匯出筆數
                    <input type="text" value="<?= h(number_format((int) ($tgosState['last_export_count'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    上次匯入
                    <input id="tgosLastImportAt" type="text" value="<?= h((string) (($tgosState['last_import_at'] ?? '') ?: '尚未匯入')) ?>" readonly>
                </label>
                <label>
                    上次匯入寫入
                    <input id="tgosLastImportMatched" type="text" value="<?= h(number_format((int) ($tgosState['last_import_matched'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    即時 QueryAddr
                    <input type="text" value="<?= h(tgosHasQueryAddrCredentials() ? '已設定 APPId／APIKey（自動定位會優先用 TGOS）' : '未設定（僅用批次匯出／匯入）') ?>" readonly>
                </label>
            </div>
        </section>
        <form class="card compact-card js-busy-submit" method="post" id="tgosExportForm"
              data-busy-label="匯出中…"
              data-busy-status="正在掃描待定位地址並產生 CSV，請稍候…"
              data-busy-mode="tgos-export">
            <input type="hidden" name="action" value="export_tgos_batch">
            <p>匯出<strong>尚無精準門牌座標</strong>的不重複地址。請先盡量跑完本機門牌比對，再匯出剩餘缺口。</p>
            <div class="form-grid">
                <label>
                    縣市（空白＝全部）
                    <select name="tgos_city">
                        <option value="">全部縣市</option>
                        <?php foreach (realpriceCityMap() as $cityInfo): ?>
                            <?php $cityName = (string) ($cityInfo['name'] ?? ''); ?>
                            <option value="<?= h($cityName) ?>"><?= h($cityName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    本次筆數上限（每日約 1 萬）
                    <input type="number" name="tgos_limit" min="1" max="10000" value="10000">
                </label>
                <label>
                    編碼
                    <select name="tgos_encoding">
                        <option value="big5" selected>Big5（TGOS 建議）</option>
                        <option value="utf-8">UTF-8（無 BOM）</option>
                    </select>
                </label>
            </div>
            <div class="doorplate-upload-progress form-busy-progress" hidden>
                <div class="doorplate-upload-bar form-busy-bar is-indeterminate"><span></span></div>
                <p class="muted form-busy-status" aria-live="polite">準備匯出…</p>
            </div>
            <div class="actions">
                <button class="primary" type="submit"<?= $realpriceStatus['index_exists'] ? '' : ' disabled' ?>>匯出 TGOS 上傳 CSV</button>
            </div>
            <p class="muted">格式依 TGOS 範本：<code>id,Address,Response_Address,Response_X,Response_Y</code>（剛好 5 欄）。地址內逗號會自動改成頓號，避免 TGOS「欄位數量不正確」。系統另存 id 對照檔供匯入。</p>
        </form>
        <form class="card compact-card js-busy-submit" method="post" enctype="multipart/form-data" id="tgosImportForm"
              data-busy-label="匯入中…"
              data-busy-status="正在上傳並分批寫入座標快取，請稍候…"
              data-busy-mode="tgos-import">
            <input type="hidden" name="action" value="import_tgos_batch">
            <p>把 TGOS email 連結下載的結果 CSV 上傳回來。會<strong>分批</strong>寫入座標快取（來源標記為 TGOS），不會覆蓋本機門牌／手動座標。</p>
            <div id="tgosImportResultNotice" class="notice" hidden></div>
            <div class="form-grid">
                <label class="wide">
                    TGOS 結果 CSV
                    <input type="file" name="tgos_result_csv" accept=".csv,text/csv" required>
                </label>
            </div>
            <div class="doorplate-upload-progress form-busy-progress" hidden>
                <div class="doorplate-upload-bar form-busy-bar is-indeterminate"><span></span></div>
                <p class="muted form-busy-status" aria-live="polite">準備匯入…</p>
            </div>
            <div class="actions">
                <button class="primary" type="submit">匯入 TGOS 結果</button>
                <button type="button" id="tgosImportCancelBtn" hidden>取消匯入</button>
            </div>
            <p class="muted">約每批 300 筆（寫入輕量 TGOS 增量快取，不再重寫整份座標庫）。完成後請到「定位覆蓋」按「更新覆蓋統計」才會看到精準數變化。</p>
        </form>
        <?php endif; /* tgos */ ?>

        <?php if ($realpriceSub === 'geocode'): ?>
        <h2 class="section-title">實價登錄地址定位（Nominatim 備援）</h2>
        <section class="card">
            <p>優先順序：本機門牌 → TGOS 批次／QueryAddr → Nominatim。Nominatim 僅作最後補洞：慢、易限流，且台灣門牌命中率較差。</p>
            <?php if ((bool) ($realpriceGeocodeStats['is_rate_limited'] ?? false)): ?>
                <div class="notice error">地理編碼目前被限流，冷卻到 <?= h((string) $realpriceGeocodeStats['rate_limited_until']) ?>。請稍後再處理。</div>
            <?php endif; ?>
            <div class="form-grid">
                <label>
                    服務來源
                    <input type="text" value="<?= h((string) ($realpriceGeocodeStats['provider'] ?? 'nominatim')) ?>" readonly>
                </label>
                <label>
                    快取座標
                    <input type="text" value="<?= h(number_format((int) ($realpriceGeocodeStats['cache_count'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    佇列總數
                    <input type="text" value="<?= h(number_format((int) ($realpriceGeocodeStats['queue_count'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    待處理
                    <input type="text" value="<?= h(number_format((int) ($realpriceGeocodeStats['pending_count'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    已完成
                    <input type="text" value="<?= h(number_format((int) ($realpriceGeocodeStats['done_count'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    查無座標
                    <input type="text" value="<?= h(number_format((int) ($realpriceGeocodeStats['not_found_count'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    錯誤
                    <input type="text" value="<?= h(number_format((int) ($realpriceGeocodeStats['error_count'] ?? 0)) . ' 筆') ?>" readonly>
                </label>
                <label>
                    上次執行
                    <input type="text" value="<?= h((string) (($realpriceGeocodeStats['last_run_at'] ?? '') ?: '尚未執行')) ?>" readonly>
                </label>
                <label class="wide">
                    狀態訊息
                    <input type="text" value="<?= h((string) (($realpriceGeocodeStats['last_message'] ?? '') ?: '尚無訊息')) ?>" readonly>
                </label>
            </div>
        </section>
        <form class="card compact-card" method="post">
            <input type="hidden" name="action" value="process_realprice_geocode">
            <p>系統會先嘗試地址定位；若門牌查不到，只保存行政區近似，<strong>不會再把行政區座標寫進門牌快取</strong>（避免多筆成交全疊在同一點）。地圖上：門牌成功才顯示單點；僅有行政區時顯示「一區一個彙總點」。為避免 504，每次請求最多做 <strong>3 筆外部 Nominatim 查詢</strong>。</p>
            <div class="form-grid">
                <label>
                    本次處理筆數（含快取套用）
                    <input type="number" name="batch_size" min="1" max="200" value="20">
                </label>
            </div>
            <div class="actions">
                <button class="primary" type="button" id="realpriceGeocodeAllBtn"<?= $realpriceStatus['index_exists'] ? '' : ' disabled' ?>>檢查並自動定位全部</button>
                <button class="button" type="button" id="realpriceGeocodeStopBtn" disabled>停止</button>
            </div>
            <p class="muted" id="realpriceGeocodeAutoStatus">自動定位需保持此頁開啟；離開頁面會停止，但已完成進度會保留，回來可再按一次續跑。</p>
        </form>
        <form class="card compact-card js-busy-submit" method="post"
              data-busy-label="清理中…"
              data-busy-status="正在清理誤標座標並重建佇列…">
            <input type="hidden" name="action" value="repair_realprice_geocode">
            <p>若地圖上看到很多不同單價卻疊在行政區中心（例如桃園區中心），代表舊快取把「門牌」誤存成「行政區」。按下方按鈕可清理誤標並重建待定位佇列。</p>
            <div class="form-grid">
                <label>
                    重建佇列上限
                    <input type="number" name="queue_limit" min="100" max="10000" step="100" value="3000">
                </label>
            </div>
            <div class="doorplate-upload-progress form-busy-progress" hidden>
                <div class="doorplate-upload-bar form-busy-bar is-indeterminate"><span></span></div>
                <p class="muted form-busy-status" aria-live="polite">準備清理…</p>
            </div>
            <div class="actions">
                <button class="button" type="submit"<?= $realpriceStatus['index_exists'] ? '' : ' disabled' ?>>清理誤標座標並重建佇列</button>
            </div>
        </form>
        <details class="admin-fold">
            <summary>地址定位進階維護</summary>
            <div class="admin-fold-body">
                <form class="card compact-card js-busy-submit" method="post"
                      data-busy-label="建立中…"
                      data-busy-status="正在建立／重建定位佇列…">
                    <input type="hidden" name="action" value="prepare_realprice_geocode">
                    <p>手動建立待定位佇列。一般使用上方「檢查並自動定位全部」即可。</p>
                    <div class="form-grid">
                        <label>
                            建立佇列上限
                            <input type="number" name="queue_limit" min="50" max="10000" step="50" value="1000">
                        </label>
                    </div>
                    <div class="doorplate-upload-progress form-busy-progress" hidden>
                        <div class="doorplate-upload-bar form-busy-bar is-indeterminate"><span></span></div>
                        <p class="muted form-busy-status" aria-live="polite">準備建立佇列…</p>
                    </div>
                    <div class="actions">
                        <button class="button" type="submit"<?= $realpriceStatus['index_exists'] ? '' : ' disabled' ?>>建立 / 重建定位佇列</button>
                    </div>
                </form>
                <form class="card compact-card js-busy-submit" method="post"
                      data-busy-label="處理中…"
                      data-busy-status="正在處理下一批定位…">
                    <input type="hidden" name="action" value="process_realprice_geocode">
                    <p>手動處理下一批定位，用於測試或限流後小量續跑。</p>
                    <div class="doorplate-upload-progress form-busy-progress" hidden>
                        <div class="doorplate-upload-bar form-busy-bar is-indeterminate"><span></span></div>
                        <p class="muted form-busy-status" aria-live="polite">準備處理…</p>
                    </div>
                    <div class="actions">
                        <button class="button" type="submit"<?= ((int) ($realpriceGeocodeStats['pending_count'] ?? 0) > 0 && !(bool) ($realpriceGeocodeStats['is_rate_limited'] ?? false)) ? '' : ' disabled' ?>>處理下一批定位</button>
                        <button class="button" type="button" id="realpriceGeocodeAutoBtn"<?= ((int) ($realpriceGeocodeStats['pending_count'] ?? 0) > 0 && !(bool) ($realpriceGeocodeStats['is_rate_limited'] ?? false)) ? '' : ' disabled' ?>>自動批次處理既有佇列</button>
                    </div>
                </form>
            </div>
        </details>
        <?php endif; /* geocode */ ?>

        <?php if ($realpriceSub === 'records'): ?>
        <h2 class="section-title">實價登錄明細檢查</h2>
        <section class="card">
            <p>查看索引內每筆交易內容與座標快取狀態。已定位代表有座標；行政區近似代表門牌查不到時使用行政區座標；未定位代表目前尚無可套用座標。</p>
            <form method="get" class="realprice-record-filter">
                <input type="hidden" name="tab" value="realprice">
                <input type="hidden" name="rp" value="records">
                <input type="hidden" name="realprice_search" value="1">
                <div class="form-grid">
                    <label class="span-2">
                        關鍵字
                        <input type="text" name="realprice_q" value="<?= h((string) $realpriceRecordFilters['keyword']) ?>" placeholder="地址、行政區、編號、建物型態">
                    </label>
                    <label>
                        縣市
                        <select name="realprice_city">
                            <option value="">全部縣市</option>
                            <?php foreach (realpriceCityMap() as $cityInfo): ?>
                                <?php $cityName = (string) ($cityInfo['name'] ?? ''); ?>
                                <option value="<?= h($cityName) ?>"<?= $realpriceRecordFilters['city'] === $cityName ? ' selected' : '' ?>><?= h($cityName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        類型
                        <select name="realprice_type">
                            <option value="">全部類型</option>
                            <?php foreach (['sale', 'presale', 'rent'] as $typeKey): ?>
                                <option value="<?= h($typeKey) ?>"<?= $realpriceRecordFilters['type'] === $typeKey ? ' selected' : '' ?>><?= h(realpriceTypeLabel($typeKey)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        定位狀態
                        <select name="realprice_status">
                            <option value="">全部狀態</option>
                            <option value="done"<?= $realpriceRecordFilters['status'] === 'done' ? ' selected' : '' ?>>已定位</option>
                            <option value="approx"<?= $realpriceRecordFilters['status'] === 'approx' ? ' selected' : '' ?>>行政區近似</option>
                            <option value="missing"<?= $realpriceRecordFilters['status'] === 'missing' ? ' selected' : '' ?>>未定位</option>
                        </select>
                    </label>
                    <label>
                        每頁筆數
                        <input type="number" name="realprice_limit" min="20" max="200" value="<?= h((string) ($realpriceRecordFilters['limit'] ?: 50)) ?>">
                    </label>
                </div>
                <div class="actions">
                    <button class="primary" type="submit">查詢明細</button>
                    <a class="button" href="?tab=realprice&rp=records">清除條件</a>
                </div>
            </form>
            <details class="admin-fold">
                <summary>新增自訂實價登錄案件</summary>
                <div class="admin-fold-body">
                    <form method="post" class="card compact-card">
                        <input type="hidden" name="action" value="save_realprice_custom">
                        <p>自訂案件會存入本地修正層，不會寫入官方 ZIP；適合補測試資料或手動確認的案件。</p>
                        <div class="form-grid">
                            <label>
                                自訂編號
                                <input type="text" name="id" placeholder="可留空自動產生">
                            </label>
                            <label>
                                類型
                                <select name="type">
                                    <option value="sale">買賣</option>
                                    <option value="presale">預售屋</option>
                                    <option value="rent">租賃</option>
                                </select>
                            </label>
                            <label>
                                交易日期
                                <input type="text" name="date" value="<?= h(date('Y-m-d')) ?>" placeholder="YYYY-MM-DD">
                            </label>
                            <label>
                                縣市
                                <input type="text" name="city" placeholder="桃園市" required>
                            </label>
                            <label>
                                行政區
                                <input type="text" name="district" placeholder="中壢區" required>
                            </label>
                            <label class="span-2">
                                地址
                                <input type="text" name="address" placeholder="土地位置建物門牌" required>
                            </label>
                            <label>
                                總價元
                                <input type="number" name="total_price" min="0" step="1">
                            </label>
                            <label>
                                單價元/坪
                                <input type="number" name="unit_price_ping" min="0" step="1">
                            </label>
                            <label>
                                面積 m²
                                <input type="number" name="area_sqm" min="0" step="0.01">
                            </label>
                            <label>
                                座標 / 緯度
                                <input type="text" name="lat" inputmode="decimal" placeholder="25.067008862648787, 121.5155798234424" required>
                            </label>
                            <label>
                                經度
                                <input type="text" name="lng" inputmode="decimal" placeholder="貼整組座標時可留空">
                            </label>
                            <label>
                                交易標的
                                <input type="text" name="target">
                            </label>
                            <label>
                                建物型態
                                <input type="text" name="building_type">
                            </label>
                            <label class="wide">
                                備註
                                <input type="text" name="note" placeholder="資料來源或修正原因">
                            </label>
                        </div>
                        <div class="actions">
                            <button class="primary" type="submit">新增自訂案件</button>
                        </div>
                    </form>
                </div>
            </details>
            <?php if (!$realpriceStatus['index_exists']): ?>
                <div class="notice warn mt-tight">尚未建立實價登錄索引，請先下載 / 更新 ZIP 並重建索引。</div>
            <?php elseif (!$realpriceShouldSearch): ?>
                <div class="notice mt-tight">
                    為避免開啟頁面逾時，明細需先設定條件後按「查詢」。建議至少選縣市或輸入關鍵字。
                    索引約 <?= h(number_format((int) ($realpriceStatus['indexed_count'] ?? 0))) ?> 筆。
                </div>
            <?php else: ?>
                <div class="metric-grid">
                    <div class="metric"><small>索引總筆數</small><strong><?= h(number_format((int) ($realpriceRecordSearch['record_count'] ?? 0))) ?></strong></div>
                    <div class="metric"><small>座標快取</small><strong><?= h(number_format((int) ($realpriceRecordSearch['cache_count'] ?? 0))) ?></strong></div>
                    <div class="metric"><small>符合條件</small><strong><?= h(number_format((int) ($realpriceRecordSearch['matched_count'] ?? 0))) ?></strong></div>
                    <div class="metric"><small>本頁顯示</small><strong><?= h(number_format((int) ($realpriceRecordSearch['returned_count'] ?? 0))) ?></strong></div>
                </div>
                <div class="report-summary">
                    <span><b><?= h(number_format((int) ($realpriceRecordSearch['counts']['done'] ?? 0))) ?></b>已定位</span>
                    <span><b><?= h(number_format((int) ($realpriceRecordSearch['counts']['approx'] ?? 0))) ?></b>行政區近似</span>
                    <span><b><?= h(number_format((int) ($realpriceRecordSearch['counts']['missing'] ?? 0))) ?></b>未定位</span>
                    <span><b><?= h((string) ($realpriceRecordSearch['generated_at'] ?? '')) ?></b>索引時間</span>
                </div>
                <div class="analytics-table-wrap realprice-record-table">
                    <table>
                        <thead>
                            <tr>
                                <th>交易 / 地址</th>
                                <th>價格</th>
                                <th>座標狀態</th>
                                <th>座標內容</th>
                                <th>來源</th>
                                <th>本地修正</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (($realpriceRecordSearch['rows'] ?? []) === []): ?>
                                <tr><td colspan="6"><small>沒有符合條件的實價登錄資料。</small></td></tr>
                            <?php endif; ?>
                            <?php foreach (($realpriceRecordSearch['rows'] ?? []) as $row): ?>
                                <?php
                                    $record = is_array($row['record'] ?? null) ? $row['record'] : [];
                                    $geo = is_array($row['geocode'] ?? null) ? $row['geocode'] : [];
                                    $override = is_array($record['_override'] ?? null) ? $record['_override'] : [];
                                    $recordKey = realpriceRecordKey($record);
                                    $totalPrice = (int) ($record['total_price'] ?? 0);
                                    $unitPing = (int) ($record['unit_price_ping'] ?? 0);
                                    $area = (float) ($record['area_sqm'] ?? 0);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= h((string) ($record['city'] ?? '')) ?> <?= h((string) ($record['district'] ?? '')) ?> · <?= h(realpriceTypeLabel((string) ($record['type'] ?? ''))) ?></strong>
                                        <small><?= h((string) ($record['date'] ?? '')) ?> · <?= h((string) ($record['id'] ?? '')) ?></small><br>
                                        <small><?= h((string) ($record['address'] ?? '')) ?></small><br>
                                        <small><?= h((string) ($record['target'] ?? '')) ?> <?= h((string) ($record['building_type'] ?? '')) ?></small>
                                    </td>
                                    <td>
                                        <strong><?= $totalPrice > 0 ? h(number_format($totalPrice) . ' 元') : '--' ?></strong>
                                        <small><?= $unitPing > 0 ? h(number_format($unitPing) . ' 元/坪') : '單價 --' ?></small><br>
                                        <small><?= $area > 0 ? h(number_format($area, 2) . ' m²') : '面積 --' ?></small>
                                    </td>
                                    <td>
                                        <strong><?= h((string) ($geo['status_label'] ?? '未定位')) ?></strong>
                                        <small>精度：<?= h((string) (($geo['precision'] ?? '') ?: '--')) ?></small><br>
                                        <small>比對：<?= h((string) (($geo['match'] ?? '') ?: '--')) ?></small>
                                    </td>
                                    <td>
                                        <?php if (($geo['lat'] ?? '') !== '' && ($geo['lng'] ?? '') !== ''): ?>
                                            <strong><?= h((string) $geo['lat']) ?>, <?= h((string) $geo['lng']) ?></strong>
                                            <small><?= h((string) ($geo['display_name'] ?? '')) ?></small><br>
                                            <small>查詢：<?= h((string) ($geo['query'] ?? '')) ?></small>
                                        <?php else: ?>
                                            <small>尚無座標快取。</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= h((string) ($record['source_file'] ?? '')) ?></small><br>
                                        <small><?= h((string) (($geo['provider'] ?? '') ?: '--')) ?></small><br>
                                        <small><?= h((string) (($geo['updated_at'] ?? '') ?: '--')) ?></small>
                                    </td>
                                    <td>
                                        <form method="post" class="realprice-override-form">
                                            <input type="hidden" name="action" value="save_realprice_override">
                                            <input type="hidden" name="record_key" value="<?= h($recordKey) ?>">
                                            <label>
                                                座標 / 緯度
                                                <input type="text" name="override_lat" value="<?= h((string) ($override['lat'] ?? '')) ?>" placeholder="<?= h((string) (($geo['lat'] ?? '') !== '' && ($geo['lng'] ?? '') !== '' ? $geo['lat'] . ', ' . $geo['lng'] : '25.067008862648787, 121.5155798234424')) ?>">
                                            </label>
                                            <label>
                                                經度
                                                <input type="text" name="override_lng" value="<?= h((string) ($override['lng'] ?? '')) ?>" placeholder="貼整組座標時可留空">
                                            </label>
                                            <label>
                                                備註
                                                <input type="text" name="override_note" value="<?= h((string) ($override['note'] ?? '')) ?>" placeholder="修正原因">
                                            </label>
                                            <label class="realprice-hide-row">
                                                <input type="checkbox" name="override_hidden"<?= !empty($override['hidden']) ? ' checked' : '' ?>>
                                                隱藏
                                            </label>
                                            <button type="submit">儲存</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; /* records */ ?>
        <?php endif; /* realprice */ ?>

        <?php if ($activeTab === 'monetization'): ?>
        <h2 class="section-title">收益與廣告</h2>
        <form class="card" method="post">
            <input type="hidden" name="action" value="save_monetization">
            <p>管理前台「支持本站」與 AdSense 廣告。AdSense 未啟用或 Publisher ID 不正確時，首頁不會載入 Google 廣告腳本。</p>
            <div class="form-grid">
                <label>
                    啟用贊助區
                    <span><input type="checkbox" name="support_enabled"<?= checkedAttr((bool) ($monetization['support']['enabled'] ?? false)) ?>></span>
                </label>
                <label class="span-2">
                    贊助標題
                    <input type="text" name="support_title" value="<?= h((string) ($monetization['support']['title'] ?? '')) ?>" placeholder="支持 Open Live Map">
                </label>
                <label>
                    按鈕文字
                    <input type="text" name="support_button_label" value="<?= h((string) ($monetization['support']['button_label'] ?? '')) ?>" placeholder="贊助本站">
                </label>
                <label class="wide">
                    贊助說明
                    <input type="text" name="support_description" value="<?= h((string) ($monetization['support']['description'] ?? '')) ?>">
                </label>
                <label class="wide">
                    贊助連結
                    <input type="url" name="support_url" value="<?= h((string) ($monetization['support']['url'] ?? '')) ?>" placeholder="https://...">
                </label>
                <label class="span-2">
                    聯絡標題
                    <input type="text" name="support_contact_title" value="<?= h((string) ($monetization['support']['contact_title'] ?? '聯絡方式')) ?>" placeholder="聯絡方式">
                </label>
                <label>
                    聯絡 Email
                    <input type="text" name="support_contact_email" value="<?= h((string) ($monetization['support']['contact_email'] ?? '')) ?>" placeholder="hello@example.com">
                </label>
                <label class="span-2">
                    聯絡連結文字
                    <input type="text" name="support_contact_url_label" value="<?= h((string) ($monetization['support']['contact_url_label'] ?? '')) ?>" placeholder="聯絡表單">
                </label>
                <label>
                    聯絡連結
                    <input type="url" name="support_contact_url" value="<?= h((string) ($monetization['support']['contact_url'] ?? '')) ?>" placeholder="https://...">
                </label>
                <label class="wide">
                    聯絡備註
                    <input type="text" name="support_contact_note" value="<?= h((string) ($monetization['support']['contact_note'] ?? '')) ?>" placeholder="資料建議、合作或問題回報都可聯絡。">
                </label>
                <label>
                    啟用 AdSense
                    <span><input type="checkbox" name="adsense_enabled"<?= checkedAttr((bool) ($monetization['adsense']['enabled'] ?? false)) ?>></span>
                </label>
                <label class="span-2">
                    Publisher ID
                    <input type="text" name="adsense_client" value="<?= h((string) ($monetization['adsense']['client'] ?? '')) ?>" placeholder="ca-pub-0000000000000000">
                </label>
                <label>
                    側欄廣告 Slot
                    <input type="text" name="adsense_sidebar_slot" value="<?= h((string) ($monetization['adsense']['slots']['sidebar'] ?? '')) ?>" inputmode="numeric">
                </label>
                <label>
                    地圖廣告 Slot
                    <input type="text" name="adsense_map_slot" value="<?= h((string) ($monetization['adsense']['slots']['map'] ?? '')) ?>" inputmode="numeric">
                </label>
            </div>
            <div class="actions">
                <button class="primary" type="submit">儲存收益設定</button>
                <a class="button" href="../legal.php?page=privacy" target="_blank" rel="noopener">查看政策頁</a>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($activeTab === 'appearance'): ?>
        <h2 class="section-title">介面外觀</h2>
        <form class="card" method="post" id="uiAppearanceForm">
            <input type="hidden" name="action" value="save_ui">
            <p>集中調整前台監看窗與地圖標誌透明度。透明度越高越不透明；模糊設為 0 可關閉毛玻璃效果。</p>

            <h3 class="section-subtitle">監看窗</h3>
            <div class="form-grid">
                <label class="span-2">
                    監看窗底色透明度（%）
                    <input type="range" name="monitor_overlay_opacity" id="monitorOverlayOpacity" min="0" max="100" step="1" value="<?= h((string) monitorOverlayOpacity($uiConfig)) ?>">
                </label>
                <label>
                    透明度數值
                    <input type="number" id="monitorOverlayOpacityNumber" min="0" max="100" step="1" value="<?= h((string) monitorOverlayOpacity($uiConfig)) ?>">
                </label>
                <label class="span-2">
                    背景模糊（px）
                    <input type="range" name="monitor_overlay_blur" id="monitorOverlayBlur" min="0" max="24" step="1" value="<?= h((string) monitorOverlayBlur($uiConfig)) ?>">
                </label>
                <label>
                    模糊數值
                    <input type="number" id="monitorOverlayBlurNumber" min="0" max="24" step="1" value="<?= h((string) monitorOverlayBlur($uiConfig)) ?>">
                </label>
                <label class="wide">
                    監看窗預覽
                    <div id="monitorOverlayPreview" class="preview-stage preview-stage--overlay">
                        <div id="monitorOverlayPreviewGlass" class="preview-glass" style="background:rgba(14,22,28,<?= h(monitorOverlayAlphaCss($uiConfig)) ?>);backdrop-filter:blur(<?= h((string) monitorOverlayBlur($uiConfig)) ?>px);-webkit-backdrop-filter:blur(<?= h((string) monitorOverlayBlur($uiConfig)) ?>px);">底色／模糊預覽</div>
                    </div>
                </label>
            </div>

            <h3 class="section-subtitle">地圖標誌</h3>
            <div class="form-grid">
                <label class="span-2">
                    實價／監視器標誌透明度（%）
                    <input type="range" name="map_marker_opacity" id="mapMarkerOpacity" min="20" max="100" step="1" value="<?= h((string) mapMarkerOpacity($uiConfig)) ?>">
                </label>
                <label>
                    透明度數值
                    <input type="number" id="mapMarkerOpacityNumber" min="20" max="100" step="1" value="<?= h((string) mapMarkerOpacity($uiConfig)) ?>">
                </label>
                <label class="wide">
                    標誌預覽
                    <div id="mapMarkerPreview" class="preview-stage preview-stage--map">
                        <span id="mapMarkerPreviewBubble" class="preview-marker" style="opacity:<?= h(mapMarkerOpacityCss($uiConfig)) ?>;">
                            <strong>1,564</strong>
                            <small>大園區</small>
                            <em>桃園市</em>
                        </span>
                    </div>
                </label>
            </div>

            <div class="actions">
                <button class="primary" type="submit">儲存外觀設定</button>
                <button class="button" type="button" id="uiAppearanceResetBtn">恢復預設（監看窗 30%／10px、標誌 85%）</button>
            </div>
        </form>

        <h2 class="section-title">Mapillary 街景</h2>
        <form class="card" method="post" autocomplete="off">
            <input type="hidden" name="action" value="save_mapillary">
            <p>預設關閉。啟用後前台工具列才會出現街景按鈕；採「點選模式＋按需查詢」，不在主圖鋪覆蓋層，也不預載 Mapillary SDK。Token 只存在伺服器，不會送到瀏覽器。</p>
            <div class="form-grid">
                <label class="span-2">
                    <span><input type="checkbox" name="mapillary_enabled"<?= checkedAttr(!empty($mapillaryConfig['enabled'])) ?>> 啟用 Mapillary 街景</span>
                </label>
                <label class="span-2">
                    Access token
                    <input type="password" name="mapillary_access_token" value="" placeholder="<?= trim((string) ($mapillaryConfig['access_token'] ?? '')) !== '' ? '已儲存，留空可保留原 token' : '貼上 Mapillary client token' ?>" autocomplete="new-password">
                    <?php if (trim((string) ($mapillaryConfig['access_token'] ?? '')) !== ''): ?>
                    <input type="hidden" name="mapillary_keep_token" value="1">
                    <small>目前已有 token；若要更換請貼上新值後儲存。</small>
                    <?php endif; ?>
                </label>
                <label>
                    搜尋半徑（公尺，最大 50）
                    <input type="number" name="mapillary_radius_m" min="1" max="50" step="1" value="<?= h((string) (int) ($mapillaryConfig['radius_m'] ?? 50)) ?>">
                </label>
            </div>
            <p><small>申請：<a href="https://www.mapillary.com/dashboard/developers" target="_blank" rel="noopener noreferrer">Mapillary Developers</a>。台灣覆蓋不均，無影像時前台會提示。啟用後前台會在載入街景檢視器時使用 Client Token（以便地圖標記跟隨移動）。</small></p>
            <div class="actions">
                <button class="primary" type="submit">儲存 Mapillary 設定</button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($activeTab === 'reports'): ?>
        <h2 class="section-title">監視器回報</h2>
        <section class="card">
            <p>使用者可回報影像失效、非即時、位置錯誤或隱私疑慮。回報只會進入待處理清單，不會自動停用監視器。</p>
            <details class="admin-fold report-type-fold" open>
                <summary>回報問題類型設定</summary>
                <div class="admin-fold-body">
                    <form method="post" id="reportTypeForm">
                        <input type="hidden" name="action" value="save_cctv_report_types">
                        <p>前台回報視窗只會顯示「啟用」的類型。代碼會用於資料儲存，建議使用英文、數字、底線或連字號。</p>
                        <div class="report-type-editor" id="reportTypeEditor">
                            <div class="report-type-editor-head" aria-hidden="true">
                                <span>啟用</span>
                                <span>代碼</span>
                                <span>顯示名稱</span>
                                <span>排序</span>
                            </div>
                            <?php foreach ($cctvReportTypeRows as $i => $typeRow): ?>
                                <div class="report-type-editor-row">
                                    <label class="report-type-enabled">
                                        <input type="checkbox" name="report_type_enabled[<?= (int) $i ?>]"<?= !empty($typeRow['enabled']) ? ' checked' : '' ?>>
                                        <span>啟用</span>
                                    </label>
                                    <input type="text" name="report_type_key[<?= (int) $i ?>]" value="<?= h((string) ($typeRow['key'] ?? '')) ?>" placeholder="offline">
                                    <input type="text" name="report_type_label[<?= (int) $i ?>]" value="<?= h((string) ($typeRow['label'] ?? '')) ?>" placeholder="影像無法播放">
                                    <input type="number" name="report_type_sort[<?= (int) $i ?>]" value="<?= h((string) ($typeRow['sort'] ?? (($i + 1) * 10))) ?>" step="10">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="actions">
                            <button class="primary" type="submit">儲存類型設定</button>
                            <button class="ghost-button" type="button" id="addReportTypeBtn">新增一列</button>
                        </div>
                    </form>
                </div>
            </details>
            <div class="report-summary">
                <?php foreach ($cctvReportStatuses as $statusKey => $statusLabel): ?>
                    <span><b><?= h(number_format((int) ($cctvReportSummary[$statusKey] ?? 0))) ?></b><?= h($statusLabel) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="report-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>時間 / 類型</th>
                            <th>監視器</th>
                            <th>說明</th>
                            <th>來源</th>
                            <th>狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cctvReports === []): ?>
                            <tr><td colspan="5"><small>目前沒有監視器回報。</small></td></tr>
                        <?php endif; ?>
                        <?php foreach ($cctvReports as $report): ?>
                            <?php $status = normalizeCctvReportStatus((string) ($report['status'] ?? 'open')); ?>
                            <tr>
                                <td>
                                    <strong><?= h((string) ($report['created_at'] ?? '')) ?></strong>
                                    <small class="report-type"><?= h((string) ($report['type_label'] ?? $report['type'] ?? '問題回報')) ?></small>
                                </td>
                                <td>
                                    <strong><?= h((string) ($report['title'] ?? '未命名監視器')) ?></strong>
                                    <small><?= h((string) ($report['camera_id'] ?? $report['cctv_id'] ?? '')) ?></small>
                                    <?php if (($report['lat'] ?? '') !== '' && ($report['lng'] ?? '') !== ''): ?>
                                        <br><small><?= h((string) $report['lat']) ?>, <?= h((string) $report['lng']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="report-note">
                                    <?= h((string) ($report['description'] ?? '')) ?>
                                    <?php if ((string) ($report['contact'] ?? '') !== ''): ?>
                                        <br><small>聯絡：<?= h((string) $report['contact']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= h((string) ($report['source_label'] ?? '')) ?></small>
                                    <?php if ((string) ($report['stream_url'] ?? '') !== ''): ?><br><a href="<?= h((string) $report['stream_url']) ?>" target="_blank" rel="noopener">影像</a><?php endif; ?>
                                    <?php if ((string) ($report['page_url'] ?? '') !== ''): ?><br><a href="<?= h((string) $report['page_url']) ?>" target="_blank" rel="noopener">頁面</a><?php endif; ?>
                                </td>
                                <td>
                                    <form class="report-status-form" method="post">
                                        <input type="hidden" name="action" value="update_cctv_report">
                                        <input type="hidden" name="id" value="<?= h((string) ($report['id'] ?? '')) ?>">
                                        <select name="status" aria-label="回報狀態">
                                            <?php foreach ($cctvReportStatuses as $statusKey => $statusLabel): ?>
                                                <option value="<?= h($statusKey) ?>"<?= $status === $statusKey ? ' selected' : '' ?>><?= h($statusLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit">更新</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'submissions'): ?>
        <h2 class="section-title">監視器投稿</h2>
        <section class="card">
            <p>使用者提交的自訂監視器會先停在這裡；核准後才會寫入公開自訂監視器資料，退回則不會出現在地圖上。</p>
            <div class="report-summary">
                <?php foreach ($cctvSubmissionStatuses as $statusKey => $statusLabel): ?>
                    <span><b><?= h(number_format((int) ($cctvSubmissionSummary[$statusKey] ?? 0))) ?></b><?= h($statusLabel) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="report-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>時間 / 狀態</th>
                            <th>監視器</th>
                            <th>位置</th>
                            <th>來源</th>
                            <th>審核</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cctvSubmissions === []): ?>
                            <tr><td colspan="5"><small>目前沒有待審或已處理投稿。</small></td></tr>
                        <?php endif; ?>
                        <?php foreach ($cctvSubmissions as $submission): ?>
                            <?php $status = normalizeCctvSubmissionStatus((string) ($submission['status'] ?? 'pending')); ?>
                            <tr>
                                <td>
                                    <strong><?= h((string) ($submission['created_at'] ?? '')) ?></strong>
                                    <small class="report-type"><?= h((string) ($cctvSubmissionStatuses[$status] ?? $status)) ?></small>
                                    <?php if ((string) ($submission['approved_cctv_id'] ?? '') !== ''): ?>
                                        <br><small><?= h((string) $submission['approved_cctv_id']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= h((string) ($submission['name'] ?? '未命名監視器')) ?></strong>
                                    <small><?= h((string) ($submission['direction'] ?? '')) ?></small>
                                    <?php if ((string) ($submission['note'] ?? '') !== ''): ?>
                                        <br><small><?= h((string) $submission['note']) ?></small>
                                    <?php endif; ?>
                                    <?php if ((string) ($submission['contact'] ?? '') !== ''): ?>
                                        <br><small>聯絡：<?= h((string) $submission['contact']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= h((string) ($submission['lat'] ?? '')) ?>, <?= h((string) ($submission['lng'] ?? '')) ?></small>
                                    <br><a href="https://www.openstreetmap.org/?mlat=<?= h((string) ($submission['lat'] ?? '')) ?>&mlon=<?= h((string) ($submission['lng'] ?? '')) ?>#map=18/<?= h((string) ($submission['lat'] ?? '')) ?>/<?= h((string) ($submission['lng'] ?? '')) ?>" target="_blank" rel="noopener">開地圖</a>
                                </td>
                                <td>
                                    <small><?= h((string) ($submission['source_label'] ?? '使用者投稿')) ?></small>
                                    <?php if ((string) ($submission['stream_url'] ?? '') !== ''): ?><br><a href="<?= h((string) $submission['stream_url']) ?>" target="_blank" rel="noopener">影像</a><?php endif; ?>
                                    <?php if ((string) ($submission['page_url'] ?? '') !== ''): ?><br><a href="<?= h((string) $submission['page_url']) ?>" target="_blank" rel="noopener">頁面</a><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status === 'pending'): ?>
                                    <div class="row-actions">
                                        <form class="inline-form" method="post">
                                            <input type="hidden" name="action" value="approve_cctv_submission">
                                            <input type="hidden" name="id" value="<?= h((string) ($submission['id'] ?? '')) ?>">
                                            <button class="primary" type="submit">核准公開</button>
                                        </form>
                                        <form class="inline-form" method="post">
                                            <input type="hidden" name="action" value="reject_cctv_submission">
                                            <input type="hidden" name="id" value="<?= h((string) ($submission['id'] ?? '')) ?>">
                                            <button type="submit">退回</button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                        <small><?= h((string) ($submission['updated_at'] ?? '')) ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($activeTab === 'cctv'): ?>
        <h2 class="section-title">自訂監視器</h2>
        <details class="admin-fold">
            <summary>批次匯入工具</summary>
            <div class="admin-fold-body">
        <form class="card stack-gap" method="post">
            <input type="hidden" name="action" value="import_freeway_cctv">
            <p>從高公局 TISV 官方 CCTV XML 匯入國道監視器，包含官方提供的座標與影像網址，不經 tw.live。</p>
            <div class="form-grid">
                <label>
                    國道路線代碼
                    <input type="text" name="freeway_road_id" value="ALL" required>
                    <small>ALL 代表全部路線；例如 000010 代表國道1號。</small>
                </label>
            </div>
            <div class="actions">
                <button class="primary" type="submit">匯入高公局官方 CCTV</button>
            </div>
        </form>

        <form class="card stack-gap" method="post">
            <input type="hidden" name="action" value="import_1968_highways">
            <p>從 1968services 快速道路頁匯入公路局影像。適合補台61、台62、台64、台65、台66、台68、台72、台74、台76、台78、台82、台84、台86、台88。</p>
            <div class="form-grid">
                <label class="wide">
                    路線代碼
                    <input type="text" name="highway1968_routes" value="61,62,64,65,66,68,72,74,76,78,82,84,86,88" required>
                </label>
            </div>
            <div class="actions">
                <button class="primary" type="submit">匯入省道快速道路影像</button>
            </div>
        </form>

        <form class="card stack-gap" method="post">
            <input type="hidden" name="action" value="import_twlive">
            <p>從 tw.live 國道路線頁匯入監視器，會抓取各鏡頭詳情頁中的座標、名稱與高公局影像網址。tw.live 有防護機制，請小批量匯入。</p>
            <div class="form-grid">
                <label class="wide">
                    tw.live 國道路線頁
                    <input type="url" name="twlive_url" value="https://tw.live/national-highway/1/N/N/" placeholder="https://tw.live/national-highway/1/N/N/" required>
                </label>
                <label>
                    單次最多處理筆數
                    <input type="number" name="twlive_limit" value="20" min="1" max="40">
                </label>
            </div>
            <div class="actions">
                <button class="primary" type="submit">匯入國道監視器</button>
            </div>
        </form>

        <form class="card stack-gap" method="post">
            <input type="hidden" name="action" value="import_gov_taiwan">
            <p>從我的E政府即時影像匯入景點影像。來源頁沒有直接座標，系統會查一次座標後快取，查不到座標的項目會略過。</p>
            <div class="form-grid">
                <label class="wide">
                    我的E政府即時影像頁
                    <input type="url" name="gov_taiwan_url" value="https://www.gov.tw/taiwan/" placeholder="https://www.gov.tw/taiwan/" required>
                </label>
                <label>
                    關鍵字
                    <input type="text" name="gov_taiwan_keyword" placeholder="例：桃園、阿里山、陽明山">
                </label>
                <label>
                    單次最多處理筆數
                    <input type="number" name="gov_taiwan_limit" value="20" min="1" max="60">
                </label>
            </div>
            <div class="actions">
                <button class="primary" type="submit">匯入景點影像</button>
            </div>
        </form>

        <form class="card stack-gap" method="post">
            <input type="hidden" name="action" value="import_tainan_cctv">
            <p>從臺南市即時交通資訊服務網官方 API 匯入路口監視器，包含官方提供的座標與影像網址。</p>
            <div class="form-grid">
                <label>
                    單次最多處理筆數
                    <input type="number" name="tainan_limit" value="1000" min="1" max="1200">
                </label>
            </div>
            <div class="actions">
                <button class="primary" type="submit">匯入臺南市 CCTV</button>
            </div>
        </form>
            </div>
        </details>

        <div class="cctv-workbench">
        <form class="card cctv-edit-card<?= $editingCctv ? ' is-editing' : '' ?>" method="post" id="cctvEditor">
            <input type="hidden" name="action" value="save_cctv">
            <input type="hidden" name="original_id" value="<?= h((string) ($editingCctv['id'] ?? '')) ?>">
            <p><strong class="cctv-editor-mode"><?= $editingCctv ? '編輯監視器' : '新增監視器' ?></strong>自行新增或修正監視器點位。儲存後會併入「監視器」圖層，位置不會自動改動。</p>
            <div class="form-grid">
                <label>
                    監視器編號（空白自動產生）
                    <input type="text" name="camera_id" value="<?= h((string) ($editingCctv['camera_id'] ?? '')) ?>" placeholder="可留空">
                </label>
                <label class="span-2">
                    顯示名稱 / 道路
                    <input type="text" name="name" value="<?= h((string) ($editingCctv['name'] ?? '')) ?>" required>
                </label>
                <label>
                    啟用
                    <span><input type="checkbox" name="enabled"<?= checkedAttr((bool) ($editingCctv['enabled'] ?? true)) ?>></span>
                </label>
                <label class="span-2">
                    貼上座標
                    <input type="text" name="coordinates" inputmode="decimal" placeholder="25.05717238351745, 121.21745908318647">
                </label>
                <label>
                    緯度
                    <input type="text" name="lat" inputmode="decimal" value="<?= h((string) ($editingCctv['lat'] ?? '')) ?>" placeholder="25.057172" required>
                </label>
                <label>
                    經度
                    <input type="text" name="lng" inputmode="decimal" value="<?= h((string) ($editingCctv['lng'] ?? '')) ?>" placeholder="121.217459" required>
                </label>
                <label>
                    方向 / 說明
                    <input type="text" name="direction" value="<?= h((string) ($editingCctv['direction'] ?? '')) ?>">
                </label>
                <label class="span-2">
                    影像網址（空白則只標示點位）
                    <input type="url" name="stream_url" value="<?= h((string) ($editingCctv['stream_url'] ?? '')) ?>" placeholder="https://www.youtube.com/watch?v=...">
                </label>
                <details class="advanced-fields">
                    <summary>進階來源設定</summary>
                    <div class="form-grid">
                        <label>
                            來源代碼
                            <input type="text" name="source_key" value="<?= h((string) ($editingCctv['source_key'] ?? '')) ?>" placeholder="例如 youtube">
                        </label>
                        <label>
                            來源名稱
                            <input type="text" name="source_label" value="<?= h((string) ($editingCctv['source_label'] ?? '')) ?>" placeholder="例如 YouTube 影像">
                        </label>
                    </div>
                </details>
            </div>
            <div class="actions">
                <button class="primary cctv-submit-label" type="submit"><?= $editingCctv ? '儲存修改' : '新增監視器' ?></button>
                <button class="ghost-button" type="button" id="newCctvBtn">清空新增</button>
                <?php if ($editingCctv): ?><a class="button" href="?tab=cctv">取消編輯</a><?php endif; ?>
            </div>
        </form>

        <div class="card cctv-table-card">
            <div class="cctv-list-toolbar" role="search">
                <label>
                    快速搜尋
                    <input type="text" id="cctvSearch" placeholder="名稱、編號、道路、座標、網址">
                </label>
                <label>
                    來源
                    <select id="cctvSourceFilter">
                        <option value="">全部來源</option>
                        <?php foreach ($customCctvSources as $source): ?>
                            <option value="<?= h((string) $source['key']) ?>"><?= h((string) $source['label']) ?>（<?= (int) $source['count'] ?>）</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    狀態
                    <select id="cctvStatusFilter">
                        <option value="">全部</option>
                        <option value="enabled">啟用</option>
                        <option value="disabled">停用</option>
                    </select>
                </label>
                <button class="ghost-button" type="button" id="clearCctvSearch">清除</button>
            </div>
            <div class="cctv-list-meta" id="cctvListMeta">共 <?= count($customCctvs) ?> 筆監視器</div>
            <div class="cctv-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>監視器</th>
                        <th>來源</th>
                        <th>位置</th>
                        <th>狀態</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customCctvs === []): ?>
                        <tr><td colspan="5"><small>尚未新增自訂監視器。</small></td></tr>
                    <?php endif; ?>
                    <?php foreach ($customCctvs as $row): ?>
                        <?php $source = inferCctvSource((string) ($row['stream_url'] ?? ''), (string) ($row['source_key'] ?? 'custom'), (string) ($row['source_label'] ?? '自訂監視器')); ?>
                        <?php
                            $enabled = !empty($row['enabled']);
                            $searchText = implode(' ', [
                                (string) ($row['name'] ?? ''),
                                (string) ($row['camera_id'] ?? ''),
                                (string) ($row['direction'] ?? ''),
                                (string) ($row['lat'] ?? ''),
                                (string) ($row['lng'] ?? ''),
                                (string) ($row['stream_url'] ?? ''),
                                (string) $source['label'],
                            ]);
                            $rowPayload = [
                                'id' => (string) ($row['id'] ?? ''),
                                'camera_id' => (string) ($row['camera_id'] ?? ''),
                                'name' => (string) ($row['name'] ?? ''),
                                'direction' => (string) ($row['direction'] ?? ''),
                                'lat' => (string) ($row['lat'] ?? ''),
                                'lng' => (string) ($row['lng'] ?? ''),
                                'stream_url' => (string) ($row['stream_url'] ?? ''),
                                'source_key' => (string) ($row['source_key'] ?? ''),
                                'source_label' => (string) ($row['source_label'] ?? ''),
                                'enabled' => $enabled,
                            ];
                        ?>
                        <tr class="cctv-row<?= (($row['id'] ?? '') === ($editingCctv['id'] ?? null)) ? ' is-current' : '' ?>"
                            data-search="<?= h(searchTextLower($searchText)) ?>"
                            data-source="<?= h((string) $source['key']) ?>"
                            data-status="<?= $enabled ? 'enabled' : 'disabled' ?>"
                            data-id="<?= h((string) ($row['id'] ?? '')) ?>"
                            data-row="<?= jsonAttr($rowPayload) ?>">
                            <td>
                                <strong><?= h((string) ($row['name'] ?? '')) ?></strong>
                                <small><?= h((string) ($row['camera_id'] ?? '')) ?></small>
                            </td>
                            <td><small><?= h((string) $source['label']) ?></small></td>
                            <td><small><?= h((string) ($row['lat'] ?? '')) ?>, <?= h((string) ($row['lng'] ?? '')) ?></small></td>
                            <td><small><?= $enabled ? '啟用' : '停用' ?></small></td>
                            <td>
                                <div class="row-actions">
                                <button class="ghost-button cctv-quick-edit" type="button">編輯</button>
                                <a href="?edit_cctv=<?= h((string) ($row['id'] ?? '')) ?>">跳頁</a>
                                <?php if ((string) ($row['stream_url'] ?? '') !== ''): ?><a href="<?= h((string) $row['stream_url']) ?>" target="_blank" rel="noopener">影像</a><?php else: ?><span class="no-media-note">無影像</span><?php endif; ?>
                                <form class="inline-form cctv-delete-form" method="post" data-cctv-name="<?= h((string) ($row['name'] ?? $row['id'] ?? '')) ?>">
                                    <input type="hidden" name="action" value="delete_cctv">
                                    <input type="hidden" name="id" value="<?= h((string) ($row['id'] ?? '')) ?>">
                                    <button type="submit">刪除</button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
(() => {
    const appConfirm = async ({ title, text, confirmButtonText = '確定', cancelButtonText = '取消', icon = 'question' } = {}) => {
        if (!window.Swal) {
            return false;
        }
        const result = await Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText,
            reverseButtons: true,
            focusCancel: true,
            background: '#121a22',
            color: '#f2f7fb',
            confirmButtonColor: '#3ec9ae',
            cancelButtonColor: '#3a4a56',
        });
        return result.isConfirmed;
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[char]));

    // Stagger surface reveals for loaded modules
    document.querySelectorAll('.admin-shell > .card, .admin-shell > form.card, .admin-shell > section.card, .admin-shell > .section-title, .admin-shell > .admin-fold').forEach((el, i) => {
        el.style.animationDelay = `${Math.min(i * 0.045, 0.36)}s`;
        if (!el.classList.contains('card') && !el.classList.contains('admin-fold')) {
            el.classList.add('admin-reveal');
        }
    });

    document.querySelectorAll('form.js-busy-submit').forEach((busyForm) => {
        const setFormBusy = (busy, message = '') => {
            const submitter = busyForm.querySelector('button[type="submit"], input[type="submit"]');
            const progress = busyForm.querySelector('.form-busy-progress');
            const status = busyForm.querySelector('.form-busy-status');
            const bar = busyForm.querySelector('.form-busy-bar');
            if (submitter) {
                if (!submitter.dataset.idleLabel) {
                    submitter.dataset.idleLabel = submitter.textContent || submitter.value || '';
                }
                submitter.disabled = busy;
                const label = busy
                    ? (busyForm.dataset.busyLabel || '處理中…')
                    : submitter.dataset.idleLabel;
                if (submitter instanceof HTMLButtonElement) submitter.textContent = label;
                else submitter.value = label;
            }
            if (progress) progress.hidden = !busy && !message;
            if (bar) bar.classList.toggle('is-indeterminate', busy);
            if (status) {
                status.hidden = false;
                status.textContent = message || (busy
                    ? (busyForm.dataset.busyStatus || '處理中，請稍候…')
                    : '');
                if (!busy && !message) status.hidden = true;
            }
            busyForm.setAttribute('aria-busy', busy ? 'true' : 'false');
        };

        const mode = String(busyForm.dataset.busyMode || '');
        if (mode === 'coverage-refresh') {
            busyForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (busyForm.getAttribute('aria-busy') === 'true') return;
                setFormBusy(true, '開始分批統計覆蓋…');
                const body = new FormData(busyForm);
                body.set('ajax', '1');
                try {
                    const response = await fetch(`${window.location.pathname}?tab=realprice&rp=coverage`, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                        body,
                        credentials: 'same-origin',
                    });
                    const rawText = await response.text();
                    let payload = {};
                    try { payload = rawText ? JSON.parse(rawText) : {}; } catch (_) { payload = {}; }
                    if (!response.ok || payload.ok !== true) {
                        if (!payload.message && response.ok && !String(rawText || '').trim().startsWith('{')) {
                            throw new Error('伺服器沒有回傳統計結果（可能逾時）。請再試一次或改用內網後台。');
                        }
                        throw new Error(payload.message || `HTTP ${response.status}`);
                    }

                    const cancelBtn = document.querySelector('#coverageRefreshCancelBtn');
                    let cancelled = false;
                    const postChunk = async () => {
                        const chunkBody = new FormData();
                        chunkBody.set('action', 'refresh_realprice_coverage_chunk');
                        chunkBody.set('ajax', '1');
                        chunkBody.set('budget_ms', '12000');
                        const chunkRes = await fetch(`${window.location.pathname}?tab=realprice&rp=coverage`, {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                            body: chunkBody,
                            credentials: 'same-origin',
                        });
                        const chunkText = await chunkRes.text();
                        let chunkPayload = {};
                        try { chunkPayload = chunkText ? JSON.parse(chunkText) : {}; } catch (_) { chunkPayload = {}; }
                        if (!chunkRes.ok || chunkPayload.ok !== true) {
                            const snippet = String(chunkText || '').replace(/\s+/g, ' ').slice(0, 160);
                            if (!chunkPayload.message && chunkRes.ok && !String(chunkText || '').trim().startsWith('{')) {
                                throw new Error('分批統計沒有回傳 JSON（可能逾時）。請再試一次。' + (snippet ? ` 回應片段：${snippet}` : ''));
                            }
                            throw new Error(chunkPayload.message || `分批統計失敗 HTTP ${chunkRes.status}`);
                        }
                        return chunkPayload;
                    };

                    if (cancelBtn) {
                        cancelBtn.hidden = false;
                        cancelBtn.onclick = async () => {
                            cancelled = true;
                            try {
                                const cancelBody = new FormData();
                                cancelBody.set('action', 'refresh_realprice_coverage_cancel');
                                cancelBody.set('ajax', '1');
                                await fetch(`${window.location.pathname}?tab=realprice&rp=coverage`, {
                                    method: 'POST',
                                    headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                                    body: cancelBody,
                                    credentials: 'same-origin',
                                });
                            } catch (_) {}
                        };
                    }

                    let last = payload;
                    let guard = 0;
                    while (!cancelled && !last.done && guard < 5000) {
                        guard += 1;
                        const job = last.job || {};
                        const pct = Number(job.pct || 0);
                        setFormBusy(true, String(last.message || job.message || `統計中 ${pct}%…`));
                        const bar = busyForm.querySelector('.form-busy-bar');
                        if (bar) {
                            bar.classList.remove('is-indeterminate');
                            const span = bar.querySelector('span');
                            if (span) span.style.width = `${Math.max(2, Math.min(100, pct))}%`;
                        }
                        last = await postChunk();
                    }
                    if (cancelBtn) cancelBtn.hidden = true;

                    const summary = last.message || (cancelled ? '已取消覆蓋統計。' : '覆蓋統計完成。');
                    setFormBusy(false, summary);
                    if (!cancelled) {
                        window.setTimeout(() => {
                            window.location.href = `${window.location.pathname}?tab=realprice&rp=coverage`;
                        }, 800);
                    }
                } catch (error) {
                    setFormBusy(false, error.message || '統計失敗');
                    const status = busyForm.querySelector('.form-busy-status');
                    if (status) {
                        status.hidden = false;
                        status.textContent = error.message || '統計失敗';
                    }
                    const progress = busyForm.querySelector('.form-busy-progress');
                    if (progress) progress.hidden = false;
                    const cancelBtn = document.querySelector('#coverageRefreshCancelBtn');
                    if (cancelBtn) cancelBtn.hidden = true;
                }
            });
            return;
        }

        if (mode === 'tgos-export' || mode === 'tgos-import') {
            busyForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (busyForm.getAttribute('aria-busy') === 'true') return;
                setFormBusy(true);
                const body = new FormData(busyForm);
                body.set('ajax', '1');
                try {
                    const response = await fetch(`${window.location.pathname}?tab=realprice&rp=tgos`, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                        body,
                        credentials: 'same-origin',
                    });
                    let payload = {};
                    const rawText = await response.text();
                    try {
                        payload = rawText ? JSON.parse(rawText) : {};
                    } catch (_) {
                        payload = {};
                    }
                    if (!response.ok || payload.ok !== true) {
                        if (!payload.message && response.ok && !rawText.trim().startsWith('{')) {
                            throw new Error('伺服器沒有回傳匯入結果（可能處理過久被切斷）。請往上看「上次匯入／寫入筆數」確認是否已寫入；若仍是「尚未匯入」請再試一次或改用內網後台。');
                        }
                        throw new Error(payload.message || `HTTP ${response.status}`);
                    }
                    if (mode === 'tgos-export') {
                        setFormBusy(false, payload.message || '匯出完成，開始下載…');
                        const filename = String(payload.filename || '');
                        if (filename) {
                            const link = document.createElement('a');
                            link.href = `${window.location.pathname}?tab=realprice&rp=tgos&tgos_dl=${encodeURIComponent(filename)}`;
                            link.rel = 'noopener';
                            document.body.appendChild(link);
                            link.click();
                            link.remove();
                        }
                        window.setTimeout(() => {
                            window.location.href = `${window.location.pathname}?tab=realprice&rp=tgos`;
                        }, 1200);
                        return;
                    }

                    // Chunked TGOS import
                    const cancelBtn = document.querySelector('#tgosImportCancelBtn');
                    let cancelled = false;
                    const postChunk = async () => {
                        const chunkBody = new FormData();
                        chunkBody.set('action', 'import_tgos_chunk');
                        chunkBody.set('ajax', '1');
                        chunkBody.set('limit', '300');
                        const chunkRes = await fetch(`${window.location.pathname}?tab=realprice&rp=tgos`, {
                            method: 'POST',
                            headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                            body: chunkBody,
                            credentials: 'same-origin',
                        });
                        const chunkText = await chunkRes.text();
                        let chunkPayload = {};
                        try { chunkPayload = chunkText ? JSON.parse(chunkText) : {}; } catch (_) { chunkPayload = {}; }
                        if (!chunkRes.ok || chunkPayload.ok !== true) {
                            const snippet = String(chunkText || '').replace(/\s+/g, ' ').slice(0, 160);
                            if (!chunkPayload.message && chunkRes.ok && !String(chunkText || '').trim().startsWith('{')) {
                                throw new Error('分批寫入沒有回傳 JSON（可能記憶體不足或逾時）。請改用內網後台再試。' + (snippet ? ` 回應片段：${snippet}` : ''));
                            }
                            throw new Error(chunkPayload.message || `分批寫入失敗 HTTP ${chunkRes.status}`);
                        }
                        return chunkPayload;
                    };

                    if (cancelBtn) {
                        cancelBtn.hidden = false;
                        cancelBtn.onclick = async () => {
                            cancelled = true;
                            try {
                                const cancelBody = new FormData();
                                cancelBody.set('action', 'import_tgos_cancel');
                                cancelBody.set('ajax', '1');
                                await fetch(`${window.location.pathname}?tab=realprice&rp=tgos`, {
                                    method: 'POST',
                                    headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                                    body: cancelBody,
                                    credentials: 'same-origin',
                                });
                            } catch (_) {}
                        };
                    }

                    let last = payload;
                    let guard = 0;
                    while (!cancelled && !last.done && guard < 500) {
                        guard += 1;
                        const job = last.job || {};
                        const pct = Number(job.pct || 0);
                        const offset = Number(job.offset || 0);
                        const total = Number(job.total || 0);
                        const matched = Number(job.matched || 0);
                        setFormBusy(true, `匯入中 ${offset.toLocaleString('zh-TW')} / ${total.toLocaleString('zh-TW')}（${pct}%），已寫入 ${matched.toLocaleString('zh-TW')} 筆…`);
                        const bar = busyForm.querySelector('.form-busy-bar');
                        if (bar) {
                            bar.classList.remove('is-indeterminate');
                            const span = bar.querySelector('span');
                            if (span) span.style.width = `${Math.max(2, Math.min(100, pct))}%`;
                        }
                        last = await postChunk();
                    }
                    if (cancelBtn) cancelBtn.hidden = true;

                    const job = last.job || {};
                    const result = {
                        matched: Number(job.matched || 0),
                        skipped: Number(job.skipped || 0),
                        failed: Number(job.failed || 0),
                    };
                    const summary = last.message
                        || `TGOS 匯入完成：寫入 ${result.matched.toLocaleString('zh-TW')} 筆`
                            + (result.skipped > 0 ? `，略過 ${result.skipped.toLocaleString('zh-TW')} 筆` : '')
                            + (result.failed > 0 ? `，失敗 ${result.failed.toLocaleString('zh-TW')} 筆` : '')
                            + '。';
                    setFormBusy(false, summary);
                    const noticeHost = document.querySelector('#tgosImportResultNotice');
                    if (noticeHost) {
                        noticeHost.hidden = false;
                        noticeHost.className = cancelled ? 'notice error' : 'notice';
                        noticeHost.textContent = summary;
                    }
                    const state = (last.state && typeof last.state === 'object') ? last.state : {};
                    const importAt = document.querySelector('#tgosLastImportAt');
                    const importMatched = document.querySelector('#tgosLastImportMatched');
                    if (importAt && (state.last_import_at || result.matched > 0)) {
                        importAt.value = state.last_import_at || new Date().toLocaleString('zh-TW');
                    }
                    if (importMatched) {
                        importMatched.value = `${Number(state.last_import_matched ?? result.matched).toLocaleString('zh-TW')} 筆`;
                    }
                    if (window.Swal && !cancelled) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'TGOS 匯入完成',
                            html: `<p style="text-align:left;line-height:1.6">${escapeHtml(summary)}</p>`
                                + `<p style="text-align:left;margin-top:8px">寫入 <strong>${result.matched.toLocaleString('zh-TW')}</strong>`
                                + `　略過 <strong>${result.skipped.toLocaleString('zh-TW')}</strong>`
                                + `　失敗 <strong>${result.failed.toLocaleString('zh-TW')}</strong></p>`
                                + `<p style="text-align:left;margin-top:8px;opacity:.8">接著到「定位覆蓋」按「更新覆蓋統計」才會更新精準數。</p>`,
                            confirmButtonText: '知道了',
                            confirmButtonColor: '#3ec9ae',
                        });
                    }
                    window.setTimeout(() => {
                        window.location.href = `${window.location.pathname}?tab=realprice&rp=tgos`;
                    }, 1200);
                    return;
                } catch (error) {
                    setFormBusy(false, error.message || '操作失敗');
                    const status = busyForm.querySelector('.form-busy-status');
                    if (status) {
                        status.hidden = false;
                        status.textContent = error.message || '操作失敗';
                    }
                    const progress = busyForm.querySelector('.form-busy-progress');
                    if (progress) progress.hidden = false;
                }
            });
            return;
        }

        busyForm.addEventListener('submit', () => {
            setFormBusy(true);
        });
    });

    const bindDeleteForm = (deleteForm) => {
        deleteForm.addEventListener('submit', async (event) => {
            if (deleteForm.dataset.confirmed === '1') return;
            event.preventDefault();
            const name = deleteForm.dataset.cctvName || '這筆監視器';
            const confirmed = await appConfirm({
                title: '刪除監視器？',
                text: `確定刪除「${name}」？`,
                icon: 'warning',
                confirmButtonText: '刪除',
            });
            if (!confirmed) return;
            deleteForm.dataset.confirmed = '1';
            deleteForm.requestSubmit();
        });
    };

    document.querySelectorAll('.cctv-delete-form').forEach(bindDeleteForm);

    const form = document.querySelector('.cctv-edit-card');
    if (!form) return;
    const coordinates = form.querySelector('[name="coordinates"]');
    const lat = form.querySelector('[name="lat"]');
    const lng = form.querySelector('[name="lng"]');
    const originalId = form.querySelector('[name="original_id"]');
    const enabled = form.querySelector('[name="enabled"]');
    const mode = form.querySelector('.cctv-editor-mode');
    const submitLabel = form.querySelector('.cctv-submit-label');
    const advanced = form.querySelector('.advanced-fields');
    let rows = Array.from(document.querySelectorAll('.cctv-row'));
    const tableBody = document.querySelector('.cctv-table-card tbody');
    const search = document.querySelector('#cctvSearch');
    const sourceFilter = document.querySelector('#cctvSourceFilter');
    const statusFilter = document.querySelector('#cctvStatusFilter');
    const clearSearch = document.querySelector('#clearCctvSearch');
    const newButton = document.querySelector('#newCctvBtn');
    const meta = document.querySelector('#cctvListMeta');

    const fillPair = (value) => {
        const match = String(value || '').trim().match(/(-?\d+(?:\.\d+)?)\s*[,，\s]\s*(-?\d+(?:\.\d+)?)/);
        if (!match) return false;
        lat.value = match[1];
        lng.value = match[2];
        return true;
    };
    coordinates?.addEventListener('input', () => fillPair(coordinates.value));
    lat?.addEventListener('paste', (event) => {
        const text = event.clipboardData?.getData('text') || '';
        if (fillPair(text)) {
            coordinates.value = text.trim();
            event.preventDefault();
        }
    });

    const setField = (name, value) => {
        const input = form.querySelector(`[name="${name}"]`);
        if (input) input.value = value ?? '';
    };

    const setEditorMode = (editing) => {
        form.classList.toggle('is-editing', editing);
        if (mode) mode.textContent = editing ? '編輯監視器' : '新增監視器';
        if (submitLabel) submitLabel.textContent = editing ? '儲存修改' : '新增監視器';
    };

    const fillEditor = (data) => {
        if (!data) return;
        if (originalId) originalId.value = data.id || '';
        setField('camera_id', data.camera_id);
        setField('name', data.name);
        setField('coordinates', '');
        setField('lat', data.lat);
        setField('lng', data.lng);
        setField('direction', data.direction);
        setField('stream_url', data.stream_url);
        setField('source_key', data.source_key);
        setField('source_label', data.source_label);
        if (enabled) enabled.checked = Boolean(data.enabled);
        if (advanced && (data.source_key || data.source_label)) advanced.open = true;
        setEditorMode(Boolean(data.id));
        rows.forEach((row) => row.classList.toggle('is-current', row.dataset.id === data.id));
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        form.querySelector('[name="name"]')?.focus({ preventScroll: true });
    };

    const clearEditor = () => {
        ['camera_id', 'name', 'coordinates', 'lat', 'lng', 'direction', 'stream_url', 'source_key', 'source_label'].forEach((name) => {
            setField(name, '');
        });
        if (originalId) originalId.value = '';
        if (enabled) enabled.checked = true;
        if (advanced) advanced.open = false;
        setEditorMode(false);
        rows.forEach((row) => row.classList.remove('is-current'));
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        form.querySelector('[name="camera_id"]')?.focus({ preventScroll: true });
    };

    const applyFilters = () => {
        const query = (search?.value || '').trim().toLocaleLowerCase();
        const source = sourceFilter?.value || '';
        const status = statusFilter?.value || '';
        let visible = 0;
        rows.forEach((row) => {
            const okQuery = query === '' || (row.dataset.search || '').includes(query);
            const okSource = source === '' || row.dataset.source === source;
            const okStatus = status === '' || row.dataset.status === status;
            const show = okQuery && okSource && okStatus;
            row.hidden = !show;
            if (show) visible++;
        });
        if (meta) {
            meta.textContent = visible === rows.length
                ? `共 ${rows.length} 筆監視器`
                : `顯示 ${visible} / ${rows.length} 筆監視器`;
        }
    };

    const bindQuickEdit = (row) => {
        row.querySelector('.cctv-quick-edit')?.addEventListener('click', () => {
            try {
                fillEditor(JSON.parse(row.dataset.row || '{}'));
            } catch (error) {
                window.location.href = `?edit_cctv=${encodeURIComponent(row.dataset.id || '')}`;
            }
        });
    };

    const cctvRowHtml = (data) => {
        const streamLink = data.stream_url
            ? `<a href="${escapeHtml(data.stream_url)}" target="_blank" rel="noopener">影像</a>`
            : '<span class="no-media-note">無影像</span>';
        return `
            <td>
                <strong>${escapeHtml(data.name)}</strong>
                <small>${escapeHtml(data.camera_id)}</small>
            </td>
            <td><small>${escapeHtml(data.source?.label || data.source_label || '自訂監視器')}</small></td>
            <td><small>${escapeHtml(data.lat)}, ${escapeHtml(data.lng)}</small></td>
            <td><small>${escapeHtml(data.status_label || (data.enabled ? '啟用' : '停用'))}</small></td>
            <td>
                <div class="row-actions">
                    <button class="ghost-button cctv-quick-edit" type="button">編輯</button>
                    <a href="?edit_cctv=${encodeURIComponent(data.id || '')}">跳頁</a>
                    ${streamLink}
                    <form class="inline-form cctv-delete-form" method="post" data-cctv-name="${escapeHtml(data.name || data.id || '')}">
                        <input type="hidden" name="action" value="delete_cctv">
                        <input type="hidden" name="id" value="${escapeHtml(data.id || '')}">
                        <button type="submit">刪除</button>
                    </form>
                </div>
            </td>
        `;
    };

    const bindRowActions = (row) => {
        bindQuickEdit(row);
        row.querySelectorAll('.cctv-delete-form').forEach(bindDeleteForm);
    };

    const ensureSourceFilterOption = (data) => {
        const key = data.source?.key || data.source_key || '';
        const label = data.source?.label || data.source_label || '';
        if (!sourceFilter || key === '' || sourceFilter.querySelector(`option[value="${CSS.escape(key)}"]`)) return;
        const option = document.createElement('option');
        option.value = key;
        option.textContent = label || key;
        sourceFilter.appendChild(option);
    };

    const upsertCctvRow = (data, originalId = '') => {
        if (!tableBody || !data?.id) return null;
        let row = rows.find((item) => item.dataset.id === (originalId || data.id))
            || rows.find((item) => item.dataset.id === data.id);
        if (!row) {
            row = document.createElement('tr');
            row.className = 'cctv-row';
            tableBody.prepend(row);
        }
        row.dataset.id = data.id;
        row.dataset.search = data.search || '';
        row.dataset.source = data.source?.key || data.source_key || '';
        row.dataset.status = data.status || (data.enabled ? 'enabled' : 'disabled');
        row.dataset.row = JSON.stringify(data);
        row.innerHTML = cctvRowHtml(data);
        rows = Array.from(document.querySelectorAll('.cctv-row'));
        rows.forEach((item) => item.classList.toggle('is-current', item.dataset.id === data.id));
        bindRowActions(row);
        ensureSourceFilterOption(data);
        applyFilters();
        return row;
    };

    rows.forEach(bindRowActions);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = submitLabel;
        if (button) {
            button.disabled = true;
            button.textContent = '儲存中...';
        }
        try {
            const body = new FormData(form);
            body.set('ajax', '1');
            const response = await fetch(window.location.href, {
                method: 'POST',
                body,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'fetch',
                },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || `儲存失敗：${response.status}`);
            }
            const row = upsertCctvRow(payload.row, payload.original_id || '');
            fillEditor(payload.row);
            row?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: payload.message || '已儲存',
                    showConfirmButton: false,
                    timer: 1600,
                    background: '#121a22',
                    color: '#eef7fb',
                });
            }
        } catch (error) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: '儲存失敗',
                    text: error.message || '請稍後再試。',
                    background: '#121a22',
                    color: '#eef7fb',
                    confirmButtonColor: '#3ec9ae',
                });
            } else {
                alert(error.message || '儲存失敗');
            }
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalId?.value ? '儲存修改' : '新增監視器';
            }
        }
    });
    search?.addEventListener('input', applyFilters);
    sourceFilter?.addEventListener('change', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);
    clearSearch?.addEventListener('click', () => {
        if (search) search.value = '';
        if (sourceFilter) sourceFilter.value = '';
        if (statusFilter) statusFilter.value = '';
        applyFilters();
        search?.focus();
    });
    newButton?.addEventListener('click', clearEditor);
    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
        if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement || event.target instanceof HTMLSelectElement) return;
        event.preventDefault();
        search?.focus();
    });
    applyFilters();
})();
</script>
<script>
(() => {
    const range = document.querySelector('#monitorOverlayOpacity');
    const number = document.querySelector('#monitorOverlayOpacityNumber');
    const blurRange = document.querySelector('#monitorOverlayBlur');
    const blurNumber = document.querySelector('#monitorOverlayBlurNumber');
    const previewGlass = document.querySelector('#monitorOverlayPreviewGlass');
    const markerRange = document.querySelector('#mapMarkerOpacity');
    const markerNumber = document.querySelector('#mapMarkerOpacityNumber');
    const markerBubble = document.querySelector('#mapMarkerPreviewBubble');
    const resetBtn = document.querySelector('#uiAppearanceResetBtn');
    if (!range || !number || !blurRange || !blurNumber || !previewGlass || !markerRange || !markerNumber || !markerBubble) return;

    const sync = (opacityValue, blurValue, markerValue) => {
        const opacity = Math.max(0, Math.min(100, Math.round(Number(opacityValue) || 0)));
        const blur = Math.max(0, Math.min(24, Math.round(Number(blurValue) || 0)));
        const marker = Math.max(20, Math.min(100, Math.round(Number(markerValue) || 0)));
        range.value = String(opacity);
        number.value = String(opacity);
        blurRange.value = String(blur);
        blurNumber.value = String(blur);
        markerRange.value = String(marker);
        markerNumber.value = String(marker);
        previewGlass.style.background = `rgba(14, 22, 28, ${opacity / 100})`;
        previewGlass.style.backdropFilter = `blur(${blur}px)`;
        previewGlass.style.webkitBackdropFilter = `blur(${blur}px)`;
        previewGlass.textContent = `底色 ${opacity}% · 模糊 ${blur}px`;
        markerBubble.style.opacity = String(marker / 100);
    };

    range.addEventListener('input', () => sync(range.value, blurRange.value, markerRange.value));
    number.addEventListener('input', () => sync(number.value, blurRange.value, markerRange.value));
    blurRange.addEventListener('input', () => sync(range.value, blurRange.value, markerRange.value));
    blurNumber.addEventListener('input', () => sync(range.value, blurNumber.value, markerRange.value));
    markerRange.addEventListener('input', () => sync(range.value, blurRange.value, markerRange.value));
    markerNumber.addEventListener('input', () => sync(range.value, blurRange.value, markerNumber.value));
    resetBtn?.addEventListener('click', () => sync(30, 10, 85));
    sync(range.value, blurRange.value, markerRange.value);
})();
</script>
<script>
(() => {
    const editor = document.querySelector('#reportTypeEditor');
    const addButton = document.querySelector('#addReportTypeBtn');
    if (!editor || !addButton) return;
    const rowCount = () => editor.querySelectorAll('.report-type-editor-row').length;
    const addRow = () => {
        const index = rowCount();
        const row = document.createElement('div');
        row.className = 'report-type-editor-row';
        row.innerHTML = `
            <label class="report-type-enabled">
                <input type="checkbox" name="report_type_enabled[${index}]" checked>
                <span>啟用</span>
            </label>
            <input type="text" name="report_type_key[${index}]" value="" placeholder="custom_issue">
            <input type="text" name="report_type_label[${index}]" value="" placeholder="自訂問題類型">
            <input type="number" name="report_type_sort[${index}]" value="${(index + 1) * 10}" step="10">
        `;
        editor.appendChild(row);
        row.querySelector('input[name^="report_type_key"]')?.focus();
    };
    addButton.addEventListener('click', addRow);
})();
</script>
<script>
(() => {
    const allBtn = document.querySelector('#realpriceGeocodeAllBtn');
    const autoBtn = document.querySelector('#realpriceGeocodeAutoBtn');
    const stopBtn = document.querySelector('#realpriceGeocodeStopBtn');
    const status = document.querySelector('#realpriceGeocodeAutoStatus');
    const batchInput = document.querySelector('input[name="batch_size"]');
    if (!autoBtn || !stopBtn || !status || !batchInput) return;

    let running = false;
    let timer = 0;

    const setStatus = (message, isError = false) => {
        status.textContent = message;
        status.style.color = isError ? '#ffb4b4' : '';
    };

    const setRunning = (next) => {
        running = next;
        if (allBtn) allBtn.disabled = next;
        autoBtn.disabled = next;
        stopBtn.disabled = !next;
        batchInput.disabled = next;
    };

    const updateButtonsFromStats = (stats) => {
        const pending = Number(stats?.pending_count || 0);
        const limited = Boolean(stats?.is_rate_limited);
        if (!running) {
            autoBtn.disabled = pending <= 0 || limited;
            if (allBtn) allBtn.disabled = limited;
        }
        if (pending <= 0 || limited) {
            setRunning(false);
        }
    };

    const prepareFullQueue = async () => {
        const body = new FormData();
        body.set('ajax', '1');
        body.set('action', 'prepare_realprice_geocode');
        body.set('queue_limit', '10000');
        const response = await fetch(window.location.href, {
            method: 'POST',
            body,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'fetch',
            },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || `建立定位佇列失敗：${response.status}`);
        }
        return payload;
    };

    const runBatch = async () => {
        if (!running) return;
        const body = new FormData();
        body.set('ajax', '1');
        body.set('action', 'process_realprice_geocode');
        body.set('batch_size', batchInput.value || '20');
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'fetch',
                },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || `批次處理失敗：${response.status}`);
            }
            const stats = payload.stats || {};
            setStatus(`${payload.message || '已處理一批'} 待處理 ${Number(stats.pending_count || 0).toLocaleString('zh-TW')} 筆，已完成 ${Number(stats.done_count || 0).toLocaleString('zh-TW')} 筆。`);
            updateButtonsFromStats(stats);
            if (!running) return;
            timer = window.setTimeout(runBatch, 4500);
        } catch (error) {
            setRunning(false);
            setStatus(error.message || '自動批次已停止。', true);
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: '自動批次已停止',
                    text: error.message || '請查看地址定位狀態。',
                    background: '#121a22',
                    color: '#eef7fb',
                    confirmButtonColor: '#3ec9ae',
                });
            }
        }
    };

    autoBtn.addEventListener('click', () => {
        setRunning(true);
        setStatus('自動批次啟動中...');
        runBatch();
    });

    allBtn?.addEventListener('click', async () => {
        setRunning(true);
        setStatus('檢查實價登錄索引並建立全量定位佇列...');
        try {
            const payload = await prepareFullQueue();
            const stats = payload.stats || {};
            const pending = Number(stats.pending_count || 0);
            setStatus(`${payload.message || '定位佇列已建立'} 待處理 ${pending.toLocaleString('zh-TW')} 筆，開始自動分批定位...`);
            if (pending <= 0) {
                setRunning(false);
                updateButtonsFromStats(stats);
                return;
            }
            runBatch();
        } catch (error) {
            setRunning(false);
            setStatus(error.message || '全自動定位啟動失敗。', true);
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: '全自動定位未啟動',
                    text: error.message || '請查看地址定位狀態。',
                    background: '#121a22',
                    color: '#eef7fb',
                    confirmButtonColor: '#3ec9ae',
                });
            }
        }
    });

    stopBtn.addEventListener('click', () => {
        window.clearTimeout(timer);
        setRunning(false);
        setStatus('自動批次已手動停止。');
    });

    window.addEventListener('beforeunload', (event) => {
        if (!running) return;
        event.preventDefault();
        event.returnValue = '自動定位仍在執行，離開頁面會停止目前批次；已完成進度會保留。';
    });
})();
</script>
<script>
(() => {
    const postJson = async (fields, { timeoutMs = 120000 } = {}) => {
        const body = new FormData();
        Object.entries(fields).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => body.append(`${key}[]`, item));
            } else if (value !== undefined && value !== null) {
                body.append(key, String(value));
            }
        });
        body.set('ajax', '1');
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), timeoutMs);
        let response;
        try {
            response = await fetch(`${window.location.pathname}?tab=realprice`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'fetch',
                },
                body,
                credentials: 'same-origin',
                signal: controller.signal,
            });
        } catch (error) {
            window.clearTimeout(timer);
            if (error?.name === 'AbortError') {
                throw new Error('請求逾時，改為讀取伺服器進度…');
            }
            throw error;
        }
        window.clearTimeout(timer);
        let payload = {};
        try {
            payload = await response.json();
        } catch (_) {
            payload = {};
        }
        if (!response.ok || payload.ok !== true) {
            throw new Error(payload.message || `HTTP ${response.status}`);
        }
        return payload;
    };

    const formatMatchStatus = (payload) => {
        const job = payload?.job || {};
        const matched = Number(job.total_matched || 0);
        const scanned = Number(job.total_scanned || 0);
        if (payload?.message) return String(payload.message);
        const label = job.current_label || job.current_city || '比對中';
        return `${label}…（累計寫入 ${matched.toLocaleString('zh-TW')} 筆／掃描 ${scanned.toLocaleString('zh-TW')} 列）`;
    };

    const phaseLabel = (phase) => ({
        prepare_needed: '準備待比對清單',
        matching: '掃描門牌 CSV',
        cancelled: '已取消',
        done: '完成',
    }[String(phase || '')] || (phase || '進行中'));

    const cityAliasesForLive = {
        taipei: ['台北市', '臺北市'],
        newtaipei: ['新北市'],
        taoyuan: ['桃園市'],
        taichung: ['台中市', '臺中市'],
        tainan: ['台南市', '臺南市'],
        kaohsiung: ['高雄市'],
        keelung: ['基隆市'],
        hsinchu_city: ['新竹市'],
        hsinchu_county: ['新竹縣'],
        miaoli: ['苗栗縣'],
        changhua: ['彰化縣'],
        nantou: ['南投縣'],
        yunlin: ['雲林縣'],
        chiayi_city: ['嘉義市'],
        chiayi_county: ['嘉義縣'],
        pingtung: ['屏東縣'],
        yilan: ['宜蘭縣'],
        hualien: ['花蓮縣'],
        taitung: ['台東縣', '臺東縣'],
        penghu: ['澎湖縣'],
        kinmen: ['金門縣'],
        lienchiang: ['連江縣'],
    };

    const parseLocaleInt = (value) => {
        const n = Number(String(value ?? '').replace(/[^\d-]/g, ''));
        return Number.isFinite(n) ? n : 0;
    };

    const formatLocaleInt = (value) => Number(value || 0).toLocaleString('zh-TW');

    const bumpCoverageLive = (payload) => {
        const matched = Number(payload?.chunk?.matched || 0);
        if (matched <= 0) return;
        const cityKey = String(payload?.chunk?.city || payload?.job?.current_city || '');
        const label = String(payload?.chunk?.label || payload?.job?.current_label || '');
        const aliases = new Set([
            label,
            ...(cityAliasesForLive[cityKey] || []),
        ].map((v) => String(v || '').replace(/臺/g, '台')).filter(Boolean));

        const note = document.querySelector('#coverageLiveNote');
        if (note) note.hidden = false;

        let row = null;
        document.querySelectorAll('#coverageCityTable tbody tr[data-city]').forEach((tr) => {
            const city = String(tr.getAttribute('data-city') || '').replace(/臺/g, '台');
            if (aliases.has(city)) row = tr;
        });
        if (row) {
            const preciseCell = row.querySelector('[data-col="precise"]');
            const needCell = row.querySelector('[data-col="need_match"]');
            const doorCell = row.querySelector('[data-col="doorplate"]');
            if (preciseCell) preciseCell.textContent = formatLocaleInt(parseLocaleInt(preciseCell.textContent) + matched);
            if (doorCell) doorCell.textContent = formatLocaleInt(parseLocaleInt(doorCell.textContent) + matched);
            if (needCell) needCell.textContent = formatLocaleInt(Math.max(0, parseLocaleInt(needCell.textContent) - matched));
            row.classList.add('is-live-hit');
        }

        const preciseInput = document.querySelector('#coveragePrecise');
        const needInput = document.querySelector('#coverageNeed');
        if (preciseInput) {
            const unique = Number(preciseInput.dataset.unique || 0);
            const nextPrecise = Number(preciseInput.dataset.precise || parseLocaleInt(preciseInput.value)) + matched;
            preciseInput.dataset.precise = String(nextPrecise);
            const pct = unique > 0 ? ((nextPrecise / unique) * 100).toFixed(1).replace(/\.0$/, '') : '0';
            preciseInput.value = `${formatLocaleInt(nextPrecise)} 筆（${pct}%）`;
        }
        if (needInput) {
            const nextNeed = Math.max(0, Number(needInput.dataset.need || parseLocaleInt(needInput.value)) - matched);
            needInput.dataset.need = String(nextNeed);
            needInput.value = `${formatLocaleInt(nextNeed)} 筆`;
        }

        const providers = document.querySelector('#coverageProviders');
        if (providers) {
            providers.hidden = false;
            const text = providers.textContent || '精準來源：';
            if (text.includes('本機門牌')) {
                providers.textContent = text.replace(/本機門牌\s*([\d,]+)/, (_, n) => `本機門牌 ${formatLocaleInt(parseLocaleInt(n) + matched)}`);
            } else {
                providers.textContent = `${text.replace(/：$/, '')}${text.includes('：') ? '、' : '：'}本機門牌 ${formatLocaleInt(matched)}`;
            }
        }

        const generated = document.querySelector('#coverageGeneratedAt');
        if (generated) generated.value = '比對中（即時估算）';
    };

    const updateMatchLive = (payload, round = 0, note = '', options = {}) => {
        const job = payload?.job || {};
        const chunk = payload?.chunk || {};
        const cities = Array.isArray(job.cities) ? job.cities : [];
        const cityIndex = Number(job.city_index || 0);
        const label = chunk.label || job.current_label || job.current_city || '—';
        const filePct = Number(chunk.file_pct ?? job.file_pct ?? 0);
        const remaining = Number(chunk.remaining ?? job.remaining_keys ?? NaN);
        const setText = (id, value) => {
            const el = document.querySelector(id);
            if (el) el.textContent = value;
        };
        setText('#matchLivePhase', phaseLabel(job.phase || (payload?.done ? 'done' : '')));
        setText('#matchLiveCity', cities.length
            ? `${label}（${Math.min(cityIndex + 1, Math.max(cities.length, 1))}／${cities.length}）`
            : label);
        setText('#matchLiveFile', Number.isFinite(filePct) ? `${filePct}%` : '—');
        setText('#matchLiveBatch', chunk.scanned != null
            ? `${Number(chunk.matched || 0).toLocaleString('zh-TW')} 命中／${Number(chunk.scanned || 0).toLocaleString('zh-TW')} 列`
            : '—');
        setText('#matchLiveMatched', `${Number(job.total_matched || 0).toLocaleString('zh-TW')} 筆`);
        setText('#matchLiveScanned', `${Number(job.total_scanned || 0).toLocaleString('zh-TW')} 列`);
        setText('#matchLiveRemain', Number.isFinite(remaining)
            ? remaining.toLocaleString('zh-TW')
            : (job.needed_before ? `起點 ${Number(job.needed_before).toLocaleString('zh-TW')}` : '—'));
        setText('#matchLiveRound', round > 0 ? String(round) : '—');

        if (options.silentLog) return;
        const logEl = document.querySelector('#doorplateMatchLog');
        if (!logEl) return;
        const text = note || formatMatchStatus(payload) || '更新進度';
        const lastLi = logEl.lastElementChild;
        if (lastLi && lastLi.dataset.msg === text) {
            lastLi.dataset.count = String(Number(lastLi.dataset.count || 1) + 1);
            const stamp = new Date().toLocaleTimeString('zh-TW', { hour12: false });
            lastLi.textContent = `${stamp}  ${text}`;
            return;
        }
        const line = document.createElement('li');
        const stamp = new Date().toLocaleTimeString('zh-TW', { hour12: false });
        line.dataset.msg = text;
        line.dataset.count = '1';
        line.textContent = `${stamp}  ${text}`;
        if (String(note).includes('逾時') || String(note).includes('取消') || payload?.cancelled) {
            line.classList.add('is-warn');
        }
        if (payload?.done && !payload?.cancelled) line.classList.add('is-ok');
        logEl.appendChild(line);
        while (logEl.children.length > 40) logEl.removeChild(logEl.firstChild);
        logEl.scrollTop = logEl.scrollHeight;
    };

    const notify = (ok, title, text) => {
        if (window.Swal) {
            Swal.fire({
                icon: ok ? 'success' : 'error',
                title,
                text,
                background: '#121a22',
                color: '#eef7fb',
                confirmButtonColor: '#3ec9ae',
            });
            return;
        }
        alert(`${title}\n${text || ''}`);
    };

    let doorplateMatchCancelRequested = false;

    const runChunkedDoorplateMatch = async ({
        cities,
        progressWrap,
        progressBar,
        statusEl,
        button,
        cancelButton,
        idleLabel,
        busyLabel,
    }) => {
        const setStatus = (text, isError = false) => {
            if (!statusEl) return;
            statusEl.textContent = text;
            statusEl.style.color = isError ? '#ff8f8f' : '';
        };
        const setProgress = (percent) => {
            if (progressBar) progressBar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
        };
        const setBusy = (busy) => {
            if (button) {
                button.disabled = busy;
                button.textContent = busy ? (busyLabel || '比對中…') : (idleLabel || '執行本機門牌比對');
            }
            if (cancelButton) {
                cancelButton.disabled = !busy;
                if (!busy) cancelButton.textContent = '取消比對';
            }
        };
        const refreshStatus = async () => {
            try {
                return await postJson({ action: 'match_doorplate_status' }, { timeoutMs: 20000 });
            } catch (_) {
                return null;
            }
        };

        doorplateMatchCancelRequested = false;
        doorplateMatchLoopActive = true;
        stopMatchProgressPoll();
        if (progressWrap) progressWrap.hidden = false;
        const logEl = document.querySelector('#doorplateMatchLog');
        if (logEl) logEl.innerHTML = '';
        setBusy(true);
        setProgress(2);
        setStatus('建立分批比對工作…');
        updateMatchLive({ job: { phase: 'prepare_needed', cities: cities || [] }, message: '建立分批比對工作…' }, 0);

        let last = await postJson({
            action: 'match_doorplate_start',
            cities: cities || [],
        }, { timeoutMs: 60000 });
        setStatus(formatMatchStatus(last));
        updateMatchLive(last, 0);

        let guard = 0;
        while (!last.done && guard < 8000) {
            if (doorplateMatchCancelRequested) {
                setStatus('正在取消比對…');
                updateMatchLive(last, guard, '正在取消比對…');
                last = await postJson({ action: 'match_doorplate_cancel' }, { timeoutMs: 30000 });
                break;
            }
            guard += 1;
            try {
                last = await postJson({ action: 'match_doorplate_chunk' }, { timeoutMs: 45000 });
            } catch (error) {
                // nginx/PHP 可能仍在跑；先等 busy 解除，避免重送造成雙重掃描。
                let waited = 0;
                let statusPayload = null;
                while (waited < 120000) {
                    statusPayload = await refreshStatus();
                    if (statusPayload?.job) {
                        last = statusPayload;
                        const busy = Boolean(last.job?.busy) && (Date.parse(last.job?.busy_until || '') > Date.now());
                        if (busy) {
                            setStatus(`${formatMatchStatus(last)}（伺服器仍在掃描，等待中…）`);
                            updateMatchLive(last, guard, '伺服器仍在掃描，等待中…');
                            await new Promise((resolve) => window.setTimeout(resolve, 1500));
                            waited += 1500;
                            continue;
                        }
                        setStatus(`${formatMatchStatus(last)}（連線逾時，已改讀伺服器進度）`);
                        updateMatchLive(last, guard, '連線逾時，改讀伺服器進度');
                        if (last.done || last.cancelled) break;
                        await new Promise((resolve) => window.setTimeout(resolve, 400));
                        break;
                    }
                    await new Promise((resolve) => window.setTimeout(resolve, 2000));
                    waited += 2000;
                }
                if (last?.done || last?.cancelled) break;
                if (!statusPayload?.job) throw error;
                continue;
            }
            if (last.cancelled) break;
            if (last.busy) {
                setStatus(formatMatchStatus(last) || '伺服器忙碌，稍候再取進度…');
                updateMatchLive(last, guard, '伺服器忙碌，等待上一批結束…');
                await new Promise((resolve) => window.setTimeout(resolve, 1200));
                continue;
            }
            const job = last.job || {};
            const cityIndex = Number(job.city_index || 0);
            const cityCount = Array.isArray(job.cities) ? job.cities.length : 1;
            const filePct = Number(last.chunk?.file_pct || job.file_pct || 0);
            const base = cityCount > 0 ? (cityIndex / cityCount) * 100 : 0;
            const pct = last.done ? 100 : Math.min(98, Math.max(5, base + (filePct * 0.9) / Math.max(cityCount, 1)));
            setProgress(pct);
            setStatus(formatMatchStatus(last));
            updateMatchLive(last, guard);
            bumpCoverageLive(last);
            await new Promise((resolve) => window.setTimeout(resolve, 50));
        }

        setProgress(100);
        const cancelled = Boolean(last.cancelled) || doorplateMatchCancelRequested;
        const doneText = formatMatchStatus(last) || (cancelled ? '已取消門牌比對。' : '本機門牌比對完成。');
        setStatus(doneText);
        updateMatchLive(last, guard, cancelled ? '比對已取消' : '比對完成');
        notify(true, cancelled ? '門牌比對已取消' : '門牌比對完成', doneText);
        setBusy(false);
        doorplateMatchLoopActive = false;
        doorplateMatchCancelRequested = false;
        stopMatchProgressPoll();
        window.setTimeout(() => {
            window.location.href = `${window.location.pathname}?tab=realprice&rp=doorplate`;
        }, 900);
        return last;
    };

    window.runChunkedDoorplateMatch = runChunkedDoorplateMatch;

    const matchForm = document.querySelector('#doorplateMatchForm');
    const matchBtn = document.querySelector('#doorplateMatchBtn');
    const matchCancelBtn = document.querySelector('#doorplateMatchCancelBtn');
    const setDoorplateCityChecks = (mode) => {
        if (!matchForm) return;
        matchForm.querySelectorAll('input[name="cities[]"]').forEach((el) => {
            if (mode === 'all') el.checked = true;
            else if (mode === 'none') el.checked = false;
            else if (mode === 'ready') el.checked = el.getAttribute('data-file-exists') === '1';
        });
    };
    document.querySelector('#doorplateMatchSelectAllBtn')?.addEventListener('click', () => setDoorplateCityChecks('all'));
    document.querySelector('#doorplateMatchSelectNoneBtn')?.addEventListener('click', () => setDoorplateCityChecks('none'));
    document.querySelector('#doorplateMatchSelectReadyBtn')?.addEventListener('click', () => setDoorplateCityChecks('ready'));
    matchCancelBtn?.addEventListener('click', async () => {
        doorplateMatchCancelRequested = true;
        matchCancelBtn.disabled = true;
        matchCancelBtn.textContent = '取消中…';
        const statusEl = document.querySelector('#doorplateMatchStatus');
        if (statusEl) statusEl.textContent = '已要求取消，等待目前批次結束…';
        updateMatchLive({ job: { phase: 'cancelled' }, cancelled: true, message: '已要求取消…' }, 0, '已要求取消，等待目前批次結束…');
        // 重整後沒有比對迴圈時，也要打後端取消，否則伺服器工作會一直卡在 running。
        try {
            const result = await postJson({ action: 'match_doorplate_cancel' }, { timeoutMs: 30000 });
            const text = formatMatchStatus(result) || result.message || '已取消門牌比對。';
            if (statusEl) {
                statusEl.textContent = text;
                statusEl.style.color = '';
            }
            updateMatchLive(result, 0, '已取消');
            notify(true, '門牌比對已取消', text);
        } catch (error) {
            const msg = error?.message || String(error);
            if (statusEl) {
                statusEl.textContent = msg;
                statusEl.style.color = '#ff8f8f';
            }
        } finally {
            matchCancelBtn.textContent = '取消比對';
            matchCancelBtn.disabled = true;
            if (matchBtn) {
                matchBtn.disabled = false;
                matchBtn.textContent = '執行本機門牌比對';
            }
            doorplateMatchCancelRequested = false;
        }
    });
    if (matchForm && matchBtn) {
        matchBtn.addEventListener('click', async () => {
            const cities = Array.from(matchForm.querySelectorAll('input[name="cities[]"]:checked'))
                .map((el) => el.value)
                .filter(Boolean);
            if (cities.length === 0) {
                notify(false, '尚未選擇縣市', '請至少勾選一個已有門牌 CSV 的縣市。');
                return;
            }
            try {
                if (matchCancelBtn) matchCancelBtn.textContent = '取消比對';
                await runChunkedDoorplateMatch({
                    cities,
                    progressWrap: document.querySelector('#doorplateMatchProgress'),
                    progressBar: document.querySelector('#doorplateMatchBar'),
                    statusEl: document.querySelector('#doorplateMatchStatus'),
                    button: matchBtn,
                    cancelButton: matchCancelBtn,
                    idleLabel: '執行本機門牌比對',
                    busyLabel: '分批比對中…',
                });
            } catch (error) {
                const msg = error?.message || String(error);
                const statusEl = document.querySelector('#doorplateMatchStatus');
                if (statusEl) {
                    statusEl.textContent = msg;
                    statusEl.style.color = '#ff8f8f';
                }
                notify(false, '比對失敗', msg);
                matchBtn.disabled = false;
                matchBtn.textContent = '執行本機門牌比對';
                if (matchCancelBtn) {
                    matchCancelBtn.disabled = true;
                    matchCancelBtn.textContent = '取消比對';
                }
                doorplateMatchCancelRequested = false;
            }
        });
    }

    let doorplateMatchPollTimer = null;
    let doorplateMatchLoopActive = false;

    const applyMatchProgressUi = (payload, note = '', options = {}) => {
        const job = payload?.job || {};
        if (String(job.status || '') !== 'running') return false;
        const progressWrap = document.querySelector('#doorplateMatchProgress');
        const progressBar = document.querySelector('#doorplateMatchBar');
        if (progressWrap) progressWrap.hidden = false;
        const cityCount = Array.isArray(job.cities) ? job.cities.length : 1;
        const cityIndex = Number(job.city_index || 0);
        const filePct = Number(job.file_pct || 0);
        const base = cityCount > 0 ? (cityIndex / cityCount) * 100 : 0;
        const pct = Math.min(98, Math.max(2, base + (filePct * 0.9) / Math.max(cityCount, 1)));
        if (progressBar) progressBar.style.width = `${pct}%`;
        const liveStatus = document.querySelector('#doorplateMatchStatus');
        const statusText = note || formatMatchStatus(payload) || job.message
            || '偵測到未完成比對。';
        if (liveStatus) {
            liveStatus.textContent = statusText;
            liveStatus.style.color = '';
        }
        updateMatchLive(payload, Number(options.round || 0), statusText, {
            silentLog: Boolean(options.silentLog),
        });
        if (matchCancelBtn) matchCancelBtn.disabled = false;
        return true;
    };

    const stopMatchProgressPoll = () => {
        if (doorplateMatchPollTimer) {
            window.clearInterval(doorplateMatchPollTimer);
            doorplateMatchPollTimer = null;
        }
    };

    const resumeDoorplateMatchFromJob = async (job) => {
        if (doorplateMatchLoopActive) return;
        const cities = Array.isArray(job?.cities) ? job.cities.map(String).filter(Boolean) : [];
        if (cities.length === 0 || !matchBtn) return;
        // 勾選與工作相同的縣市，方便使用者看得到範圍
        if (matchForm) {
            matchForm.querySelectorAll('input[name="cities[]"]').forEach((el) => {
                el.checked = cities.includes(el.value);
            });
        }
        try {
            if (matchCancelBtn) matchCancelBtn.textContent = '取消比對';
            await runChunkedDoorplateMatch({
                cities,
                progressWrap: document.querySelector('#doorplateMatchProgress'),
                progressBar: document.querySelector('#doorplateMatchBar'),
                statusEl: document.querySelector('#doorplateMatchStatus'),
                button: matchBtn,
                cancelButton: matchCancelBtn,
                idleLabel: '執行本機門牌比對',
                busyLabel: '分批比對中…',
            });
        } catch (error) {
            const msg = error?.message || String(error);
            const statusEl = document.querySelector('#doorplateMatchStatus');
            if (statusEl) {
                statusEl.textContent = msg;
                statusEl.style.color = '#ff8f8f';
            }
            if (matchBtn) {
                matchBtn.disabled = false;
                matchBtn.textContent = '執行本機門牌比對';
            }
            if (matchCancelBtn) {
                matchCancelBtn.disabled = false;
                matchCancelBtn.textContent = '取消比對';
            }
        }
    };

    // 進入實價分頁：還原進度並自動續跑（輪詢只讀狀態不會前進，必須送 chunk）。
    (async () => {
        try {
            let bootstrap = {};
            const bootEl = document.querySelector('#doorplateMatchJobBootstrap');
            if (bootEl?.textContent) {
                try {
                    bootstrap = JSON.parse(bootEl.textContent);
                } catch (_) {
                    bootstrap = {};
                }
            }
            if (String(bootstrap.status || '') === 'running') {
                applyMatchProgressUi(
                    { job: bootstrap, message: bootstrap.message },
                    bootstrap.message || '已還原上次比對進度，準備自動續跑…',
                    { silentLog: false }
                );
            }
            const statusPayload = await postJson({ action: 'match_doorplate_status' }, { timeoutMs: 15000 });
            const job = statusPayload?.job;
            if (!job || String(job.status || '') !== 'running') return;
            applyMatchProgressUi(
                statusPayload,
                job.message || formatMatchStatus(statusPayload),
                { silentLog: false }
            );
            // 自動接續 chunk，進度數字才會動
            await resumeDoorplateMatchFromJob(job);
        } catch (_) {
            // ignore
        }
    })();

    const form = document.querySelector('#doorplateUploadForm');
    if (!form) return;
    const fileInput = document.querySelector('#doorplateUploadFile');
    const button = document.querySelector('#doorplateUploadBtn');
    const registerBtn = document.querySelector('#doorplateRegisterBtn');
    const progressWrap = document.querySelector('#doorplateUploadProgress');
    const progressBar = document.querySelector('#doorplateUploadBar');
    const statusText = document.querySelector('#doorplateUploadStatus');
    const citySelect = document.querySelector('#doorplateUploadCity');
    const targetNameInput = document.querySelector('#doorplateTargetName');
    const copyNameEl = document.querySelector('#doorplateCopyName');

    const syncDoorplateTargetName = () => {
        const key = String(citySelect?.value || 'kaohsiung').replace(/[^a-z0-9_]/g, '') || 'kaohsiung';
        const name = `${key}.csv`;
        if (targetNameInput) targetNameInput.value = name;
        if (copyNameEl) copyNameEl.textContent = name;
    };
    citySelect?.addEventListener('change', syncDoorplateTargetName);
    syncDoorplateTargetName();

    const setStatus = (text, isError = false) => {
        if (!statusText) return;
        statusText.textContent = text;
        statusText.style.color = isError ? '#ff8f8f' : '';
    };

    const setProgress = (percent) => {
        if (progressBar) progressBar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
    };

    const startMatchAfterReady = async (city, idleLabel) => {
        const matchCancelBtn = document.querySelector('#doorplateMatchCancelBtn');
        if (matchCancelBtn) matchCancelBtn.textContent = '取消比對';
        await runChunkedDoorplateMatch({
            cities: city ? [city] : [],
            progressWrap: document.querySelector('#doorplateMatchProgress') || progressWrap,
            progressBar: document.querySelector('#doorplateMatchBar') || progressBar,
            statusEl: document.querySelector('#doorplateMatchStatus') || statusText,
            button: registerBtn || button,
            cancelButton: matchCancelBtn,
            idleLabel,
            busyLabel: '分批比對中…',
        });
    };

    registerBtn?.addEventListener('click', async () => {
        const city = String(citySelect?.value || '').trim();
        if (!city) {
            notify(false, '尚未選擇縣市', '請先選擇要登記的縣市。');
            return;
        }
        if (progressWrap) progressWrap.hidden = false;
        registerBtn.disabled = true;
        registerBtn.textContent = '登記中…';
        setProgress(10);
        setStatus(`檢查本機 ${city}.csv …`);
        try {
            const payload = await postJson({ action: 'register_doorplate', city });
            setProgress(40);
            setStatus((payload.message || '本機檔案已登記') + ' 開始分批比對…');
            await startMatchAfterReady(String(payload.city || city), '登記本機檔案並比對');
        } catch (error) {
            const msg = error?.message || String(error);
            setStatus(msg, true);
            notify(false, '登記失敗', msg);
            registerBtn.disabled = false;
            registerBtn.textContent = '登記本機檔案並比對';
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const file = fileInput?.files?.[0];
        if (!file) {
            notify(false, '尚未選擇檔案', '請先選擇要上傳的門牌 CSV。大檔請改複製到本機目錄後按「登記本機檔案並比對」。');
            return;
        }
        if (file.size > 40 * 1024 * 1024) {
            const name = `${String(citySelect?.value || 'city')}.csv`;
            notify(
                false,
                '檔案偏大，建議本機複製',
                `目前約 ${(file.size / 1024 / 1024).toFixed(1)} MB，網頁上傳常會失敗。請把檔案重新命名為 ${name}，複製到門牌目錄後按「登記本機檔案並比對」。`
            );
        }

        const body = new FormData(form);
        body.set('ajax', '1');

        if (progressWrap) progressWrap.hidden = false;
        if (button) {
            button.disabled = true;
            button.textContent = '上傳中…';
        }
        setProgress(0);
        setStatus(`開始上傳 ${file.name}（${(file.size / 1024 / 1024).toFixed(1)} MB）→ 將存成 ${citySelect?.value || ''}.csv`);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', `${window.location.pathname}?tab=realprice`);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'fetch');
        xhr.responseType = 'json';

        xhr.upload.addEventListener('progress', (e) => {
            if (!e.lengthComputable) {
                setStatus('上傳中（伺服器未回報精確進度）…');
                return;
            }
            const percent = Math.round((e.loaded / e.total) * 100);
            setProgress(Math.min(percent, 90));
            setStatus(`上傳中 ${percent}%（${(e.loaded / 1024 / 1024).toFixed(1)} / ${(e.total / 1024 / 1024).toFixed(1)} MB）`);
        });

        xhr.upload.addEventListener('load', () => {
            setProgress(92);
            setStatus('檔案已送達伺服器，正在保存…');
        });

        xhr.addEventListener('load', async () => {
            const payload = xhr.response && typeof xhr.response === 'object'
                ? xhr.response
                : (() => {
                    try { return JSON.parse(xhr.responseText || '{}'); } catch { return {}; }
                })();
            const ok = xhr.status >= 200 && xhr.status < 300 && payload.ok !== false;
            if (!ok) {
                const city = String(citySelect?.value || 'kaohsiung');
                const hint = `若檔案很大，請改把 CSV 重新命名為 ${city}.csv，複製到門牌目錄後按「登記本機檔案並比對」。`;
                setProgress(0);
                setStatus((payload.message || `上傳失敗：HTTP ${xhr.status}`) + ' ' + hint, true);
                notify(false, '上傳失敗', (payload.message || `HTTP ${xhr.status}`) + '\n' + hint);
                if (button) {
                    button.disabled = false;
                    button.textContent = '上傳門牌 CSV 並自動比對';
                }
                return;
            }

            setProgress(95);
            setStatus((payload.message || '上傳完成') + ' 開始分批比對…');
            const city = String(payload.city || citySelect?.value || '').trim();
            try {
                await startMatchAfterReady(city, '上傳門牌 CSV 並自動比對');
            } catch (error) {
                const msg = error?.message || String(error);
                setStatus(msg, true);
                notify(false, '上傳成功但比對失敗', msg);
                if (button) {
                    button.disabled = false;
                    button.textContent = '上傳門牌 CSV 並自動比對';
                }
            }
        });

        xhr.addEventListener('error', () => {
            const city = String(citySelect?.value || 'kaohsiung');
            const hint = `請把 CSV 重新命名為 ${city}.csv，複製到門牌目錄後按「登記本機檔案並比對」。`;
            setProgress(0);
            setStatus('網路上傳失敗。' + hint, true);
            notify(false, '上傳失敗', '網路上傳失敗（常因檔案過大）。\n' + hint);
            if (button) {
                button.disabled = false;
                button.textContent = '上傳門牌 CSV 並自動比對';
            }
        });

        xhr.send(body);
    });
})();

(() => {
    const indexBtn = document.querySelector('#realpriceIndexBtn');
    const indexAllBtn = document.querySelector('#realpriceIndexAllBtn');
    const indexBtnAdvanced = document.querySelector('#realpriceIndexBtnAdvanced');
    const cancelBtn = document.querySelector('#realpriceIndexCancelBtn');
    const progressWrap = document.querySelector('#realpriceIndexProgress');
    const progressBar = document.querySelector('#realpriceIndexBar');
    const statusText = document.querySelector('#realpriceIndexStatusText');
    const checkAll = document.querySelector('#realpriceSeasonCheckAll');
    if (!indexBtn && !indexBtnAdvanced && !indexAllBtn) return;

    checkAll?.addEventListener('change', () => {
        document.querySelectorAll('.realprice-season-check').forEach((el) => {
            el.checked = !!checkAll.checked;
        });
    });

    const selectedSeasons = () => Array.from(document.querySelectorAll('.realprice-season-check:checked'))
        .map((el) => String(el.value || ''))
        .filter(Boolean);

    const sleep = (ms) => new Promise((r) => window.setTimeout(r, ms));

    const postJson = async (fields, { timeoutMs = 35000 } = {}) => {
        const body = new FormData();
        Object.entries(fields || {}).forEach(([key, value]) => {
            if (Array.isArray(value)) value.forEach((item) => body.append(`${key}[]`, String(item)));
            else if (value !== undefined && value !== null) body.append(key, String(value));
        });
        body.set('ajax', '1');
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), timeoutMs);
        let response;
        try {
            response = await fetch(`${window.location.pathname}?tab=realprice&rp=data`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' },
                body,
                credentials: 'same-origin',
                signal: controller.signal,
            });
        } catch (error) {
            window.clearTimeout(timer);
            const err = new Error(error?.name === 'AbortError'
                ? '請求逾時，改為讀取伺服器進度…'
                : (error?.message || '網路錯誤'));
            err.code = error?.name === 'AbortError' ? 'timeout' : 'network';
            throw err;
        }
        window.clearTimeout(timer);
        let payload = {};
        try { payload = await response.json(); } catch (_) { payload = {}; }
        if (!response.ok || payload.ok !== true) {
            const err = new Error(payload.message || `HTTP ${response.status}`);
            err.code = 'http';
            throw err;
        }
        return payload;
    };

    const readStatus = async () => {
        try {
            return await postJson({ action: 'rebuild_realprice_status' }, { timeoutMs: 20000 });
        } catch (_) {
            return null;
        }
    };

    const setUi = (payload, { running = false } = {}) => {
        const job = payload?.job || {};
        const pct = Math.max(0, Math.min(100, Number(job.pct || 0)));
        if (progressWrap) progressWrap.hidden = false;
        if (progressBar) progressBar.style.width = `${pct}%`;
        if (statusText) statusText.textContent = String(payload?.message || job.message || '處理中…');
        if (cancelBtn) cancelBtn.hidden = !running;
        [indexBtn, indexAllBtn, indexBtnAdvanced].forEach((btn) => {
            if (!btn) return;
            btn.disabled = running;
        });
        if (indexBtn) indexBtn.textContent = running ? '重建中…' : '重建勾選季度索引';
        if (indexAllBtn) indexAllBtn.textContent = running ? '重建中…' : '全部季度重建';
        if (indexBtnAdvanced) indexBtnAdvanced.textContent = running ? '重建中…' : '只重建索引（分批）';
    };

    let looping = false;
    const pumpChunks = async (initialPayload = null) => {
        let payload = initialPayload;
        while (true) {
            if (!payload || !payload.done) {
                try {
                    payload = await postJson({ action: 'rebuild_realprice_chunk' }, { timeoutMs: 35000 });
                } catch (error) {
                    const statusPayload = await readStatus();
                    if (statusPayload) {
                        setUi(statusPayload, { running: !statusPayload.done });
                        if (statusPayload.done) {
                            payload = statusPayload;
                            break;
                        }
                        if (statusText) {
                            statusText.textContent = `${error.message || '批次逾時'}；繼續中…`;
                        }
                        await sleep(1200);
                        continue;
                    }
                    if (statusText) {
                        statusText.textContent = `${error.message || '連線異常'}；3 秒後重試…`;
                    }
                    await sleep(3000);
                    continue;
                }
            }
            setUi(payload, { running: !payload.done });
            if (payload.done) break;
            if (payload.busy) await sleep(800);
        }
        return payload;
    };

    const runLoop = async ({ all = false } = {}) => {
        if (looping) return;
        const seasons = all ? [] : selectedSeasons();
        if (!all && seasons.length === 0) {
            window.alert('請至少勾選一個季度 ZIP。');
            return;
        }
        looping = true;
        try {
            setUi({ message: '建立分批索引工作…', job: { pct: 0 } }, { running: true });
            const startFields = { action: 'rebuild_realprice_start' };
            if (!all) startFields.seasons = seasons;
            let payload = null;
            try {
                payload = await postJson(startFields, { timeoutMs: 35000 });
            } catch (error) {
                // Start may have succeeded server-side even if the browser timed out.
                payload = await readStatus();
                if (!payload || payload.done || payload.job?.status !== 'running') {
                    throw error;
                }
                if (statusText) {
                    statusText.textContent = '連線逾時，但伺服器工作仍在，改為續跑…';
                }
            }
            setUi(payload, { running: true });
            payload = await pumpChunks(payload?.done ? payload : null);
            setUi(payload, { running: false });
            if (payload.cancelled) return;
            if (statusText) statusText.textContent = payload.message || '索引重建完成。';
            // Soft notice instead of blocking alert.
            window.setTimeout(() => {
                window.location.href = `${window.location.pathname}?tab=realprice&rp=data`;
            }, 800);
        } catch (error) {
            setUi({ message: error.message || '索引重建失敗', job: { pct: 0 } }, { running: false });
            if (statusText) statusText.textContent = error.message || '索引重建失敗';
        } finally {
            looping = false;
        }
    };

    indexBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        runLoop({ all: false });
    });
    indexAllBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        runLoop({ all: true });
    });
    indexBtnAdvanced?.addEventListener('click', (event) => {
        event.preventDefault();
        runLoop({ all: true });
    });

    cancelBtn?.addEventListener('click', async (event) => {
        event.preventDefault();
        try {
            const payload = await postJson({ action: 'rebuild_realprice_cancel' }, { timeoutMs: 30000 });
            setUi(payload, { running: false });
        } catch (error) {
            if (statusText) statusText.textContent = error.message || '取消失敗';
        }
    });

    (async () => {
        try {
            const statusPayload = await readStatus();
            if (statusPayload && !statusPayload.done && statusPayload.job?.status === 'running') {
                setUi(statusPayload, { running: true });
                looping = true;
                try {
                    const payload = await pumpChunks(null);
                    setUi(payload, { running: false });
                    if (!payload.cancelled) {
                        window.setTimeout(() => {
                            window.location.href = `${window.location.pathname}?tab=realprice&rp=data`;
                        }, 800);
                    }
                } finally {
                    looping = false;
                }
            }
        } catch (_) {}
    })();
})();
</script>
</body>
</html>
