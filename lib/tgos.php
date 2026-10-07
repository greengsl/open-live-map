<?php
declare(strict_types=1);

/**
 * TGOS 批次門牌地址比對：匯出待比對 CSV → 官網上傳 → 匯入結果寫入 geocode-cache。
 * 官方服務為線上申請 API KEY 後於 TGOS 網頁上傳；結果以 email 通知下載（約 1～2 天，每日約 1 萬筆）。
 * 另可選設定 tgos_app_id / tgos_api_key（全國門牌定位 QueryAddr）作為即時備援。
 */

require_once __DIR__ . '/realprice.php';
require_once __DIR__ . '/doorplate.php';

function tgosDir(): string
{
    $dir = realpriceDir() . '/tgos';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function tgosStatePath(): string
{
    return tgosDir() . '/state.json';
}

function tgosLoadState(): array
{
    return realpriceLoadJsonFile(tgosStatePath(), [
        'last_export_at' => '',
        'last_import_at' => '',
        'last_message' => '',
        'last_export_count' => 0,
        'last_import_matched' => 0,
        'exports' => [],
    ]);
}

function tgosSaveState(array $state): void
{
    $state['updated_at'] = date(DATE_ATOM);
    realpriceSaveJsonFile(tgosStatePath(), $state);
}

function tgosLocalConfig(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $path = dirname(__DIR__) . '/config.local.php';
    $cfg = is_file($path) ? require $path : [];
    $cached = is_array($cfg) ? $cfg : [];
    return $cached;
}

function tgosHasQueryAddrCredentials(): bool
{
    $cfg = tgosLocalConfig();
    $appId = trim((string) ($cfg['tgos_app_id'] ?? ''));
    $apiKey = trim((string) ($cfg['tgos_api_key'] ?? ''));
    return $appId !== '' && $apiKey !== '';
}

/**
 * Strip / normalize address for TGOS batch CSV.
 * TGOS 上傳驗證是「以逗號切開必須剛好 5 欄」，不會正確處理 CSV 引號；
 * 因此地址內不可再出現半形逗號、換行或雙引號。
 */
function tgosCleanAddressForExport(string $address): string
{
    $address = realpriceNormalizeAddress($address);
    $address = preg_replace('/^\d{3,5}/u', '', $address) ?? $address;
    $address = str_replace(['巿'], ['市'], $address);
    // 半形逗號會破壞 TGOS 欄位計數；改為頓號仍可模糊比對。
    $address = str_replace([',', '，', '"', "'", "\r", "\n", "\t"], ['、', '、', '', '', '', '', ''], $address);
    $address = preg_replace('/\s+/u', '', $address) ?? $address;
    return trim($address);
}

/**
 * Collect unique addresses still missing precise doorplate coords.
 *
 * @return list<array{key:string,address:string,city:string,district:string}>
 */
function tgosCollectPendingAddresses(string $cityFilter = '', int $limit = 10000): array
{
    $limit = max(1, min(10000, $limit));
    $cityFilter = realpriceNormalizeAddress($cityFilter);
    $cache = realpriceLoadGeocodeCache();
    $cached = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $seen = [];
    $items = [];

    foreach (realpriceIterateRecords() as $record) {
        if (!is_array($record)) {
            continue;
        }
        $address = realpriceNormalizeAddress((string) ($record['address'] ?? ''));
        if ($address === '' || mb_strlen($address, 'UTF-8') < 5) {
            continue;
        }
        // 地號不適合門牌比對
        if (str_contains($address, '地號')) {
            continue;
        }
        $city = realpriceNormalizeAddress((string) ($record['city'] ?? ''));
        $district = realpriceNormalizeAddress((string) ($record['district'] ?? ''));
        if ($cityFilter !== '' && $city !== $cityFilter) {
            continue;
        }
        $key = realpriceGeocodeKey($address);
        if (isset($seen[$key])) {
            continue;
        }
        $existing = is_array($cached[$key] ?? null) ? $cached[$key] : null;
        if ($existing
            && !realpriceGeocodeHitIsDistrictFallback($existing, $city, $district)
            && (($existing['precision'] ?? '') !== 'district')
        ) {
            continue;
        }
        $seen[$key] = true;
        $items[] = [
            'key' => $key,
            'address' => $address,
            'city' => $city,
            'district' => $district,
        ];
        if (count($items) >= $limit) {
            break;
        }
    }

    return $items;
}

/**
 * Build TGOS batch CSV + sidecar id map. Returns paths and counts (does not stream download).
 *
 * @return array{path:string,map_path:string,count:int,filename:string,encoding:string}
 */
function tgosBuildExport(string $cityFilter = '', int $limit = 10000, string $encoding = 'big5'): array
{
    $items = tgosCollectPendingAddresses($cityFilter, $limit);
    if ($items === []) {
        throw new RuntimeException('沒有尚待精準定位的地址可匯出（或篩選縣市後為空）。請先跑完本機門牌比對，或確認索引存在。');
    }

    // TGOS 官方／社群實務多以 Big5 上傳較穩；utf-8 仍可用但不加 BOM。
    $encoding = strtolower($encoding) === 'utf-8' ? 'utf-8' : 'big5';
    $stamp = date('Ymd-His');
    $citySlug = $cityFilter !== '' ? preg_replace('/\W+/u', '', $cityFilter) : 'all';
    $base = 'tgos-batch-' . $citySlug . '-' . $stamp;
    $csvPath = tgosDir() . '/' . $base . '.csv';
    $mapPath = tgosDir() . '/' . $base . '.map.json';

    $fh = fopen($csvPath, 'wb');
    if ($fh === false) {
        throw new RuntimeException('無法寫入 TGOS 匯出檔。');
    }

    $header = "id,Address,Response_Address,Response_X,Response_Y\n";
    if ($encoding === 'big5') {
        $headerBin = @iconv('UTF-8', 'BIG5//IGNORE', $header);
        fwrite($fh, $headerBin !== false ? $headerBin : $header);
    } else {
        // 不加 BOM：部分 TGOS 驗證會把 BOM 算進第一欄。
        fwrite($fh, $header);
    }

    $map = [
        'generated_at' => date(DATE_ATOM),
        'city_filter' => $cityFilter,
        'encoding' => $encoding,
        'count' => 0,
        'items' => [],
        'sanitized_comma_count' => 0,
    ];

    $id = 1000;
    $written = 0;
    $sanitizedComma = 0;
    foreach ($items as $item) {
        $rawAddr = (string) $item['address'];
        if (str_contains($rawAddr, ',') || str_contains($rawAddr, '，')) {
            $sanitizedComma++;
        }
        $addr = tgosCleanAddressForExport($rawAddr);
        if ($addr === '' || mb_strlen($addr, 'UTF-8') < 5) {
            continue;
        }
        // 嚴格 5 欄、不使用 CSV 引號（TGOS 驗證不認引號）。
        $line = $id . ',' . $addr . ',,,';
        if (substr_count($line, ',') !== 4) {
            // 極少數異常字元：再清一次半形逗號後重試。
            $addr = str_replace(',', '、', $addr);
            $line = $id . ',' . $addr . ',,,';
            if (substr_count($line, ',') !== 4) {
                continue;
            }
        }
        if ($encoding === 'big5') {
            $bin = @iconv('UTF-8', 'BIG5//IGNORE', $line . "\n");
            if ($bin === false || $bin === "\n" || trim($bin) === '') {
                continue;
            }
            fwrite($fh, $bin);
        } else {
            fwrite($fh, $line . "\n");
        }
        $map['items'][(string) $id] = [
            'key' => (string) $item['key'],
            'address' => $rawAddr,
            'city' => (string) $item['city'],
            'district' => (string) $item['district'],
            'export_address' => $addr,
        ];
        $id++;
        $written++;
    }
    fclose($fh);

    if ($written <= 0) {
        @unlink($csvPath);
        throw new RuntimeException('匯出後沒有可用地址列（可能都被過濾）。請調整縣市或先完成門牌比對。');
    }
    $map['count'] = $written;
    $map['sanitized_comma_count'] = $sanitizedComma;

    realpriceSaveJsonFile($mapPath, $map);

    $state = tgosLoadState();
    $state['last_export_at'] = date(DATE_ATOM);
    $state['last_export_count'] = $written;
    $state['last_message'] = '已匯出 ' . $written . ' 筆待上傳 TGOS（'
        . ($cityFilter !== '' ? $cityFilter : '全部縣市')
        . '，' . strtoupper($encoding)
        . ($sanitizedComma > 0 ? '；已將 ' . $sanitizedComma . ' 筆地址內逗號改為頓號以免 TGOS 欄位錯誤' : '')
        . '）。';
    $exports = is_array($state['exports'] ?? null) ? $state['exports'] : [];
    array_unshift($exports, [
        'at' => $state['last_export_at'],
        'count' => $written,
        'city' => $cityFilter,
        'file' => basename($csvPath),
        'map' => basename($mapPath),
        'encoding' => $encoding,
        'sanitized_comma_count' => $sanitizedComma,
    ]);
    $state['exports'] = array_slice($exports, 0, 20);
    tgosSaveState($state);

    return [
        'path' => $csvPath,
        'map_path' => $mapPath,
        'count' => $written,
        'filename' => basename($csvPath),
        'encoding' => $encoding,
        'sanitized_comma_count' => $sanitizedComma,
    ];
}

function tgosCsvEscape(string $value): string
{
    if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n") || str_contains($value, "\r")) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

function tgosDownloadExport(array $built): void
{
    $path = (string) ($built['path'] ?? '');
    $filename = (string) ($built['filename'] ?? 'tgos-batch.csv');
    if ($path === '' || !is_file($path)) {
        throw new RuntimeException('匯出檔不存在。');
    }
    header('Content-Type: text/csv; charset=' . ((string) ($built['encoding'] ?? 'utf-8') === 'big5' ? 'big5' : 'utf-8'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

/**
 * Find newest matching sidecar map for import ids.
 */
function tgosFindMapForIds(array $ids): ?array
{
    $files = glob(tgosDir() . '/tgos-batch-*.map.json') ?: [];
    usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
    foreach ($files as $file) {
        $map = realpriceLoadJsonFile($file, []);
        $items = is_array($map['items'] ?? null) ? $map['items'] : [];
        $hits = 0;
        foreach ($ids as $id) {
            if (isset($items[(string) $id])) {
                $hits++;
            }
        }
        if ($hits > 0 && $hits >= (int) (count($ids) * 0.3)) {
            $map['_path'] = $file;
            return $map;
        }
    }
    return null;
}

function tgosDetectXyToLatLng(float $x, float $y): ?array
{
    // WGS84
    if ($y > 20 && $y < 27 && $x > 118 && $x < 123.5) {
        return ['lat' => $y, 'lng' => $x, 'srs' => 'wgs84'];
    }
    // swapped WGS84
    if ($x > 20 && $x < 27 && $y > 118 && $y < 123.5) {
        return ['lat' => $x, 'lng' => $y, 'srs' => 'wgs84'];
    }
    // TWD97 TM2
    if ($x > 50000 && $x < 450000 && $y > 2300000 && $y < 2900000) {
        $ll = doorplateTwd97ToWgs84($x, $y);
        return ['lat' => (float) $ll['lat'], 'lng' => (float) $ll['lng'], 'srs' => 'twd97'];
    }
    return null;
}

/**
 * Import TGOS result CSV into geocode-cache (provider=tgos).
 *
 * @return array{matched:int,skipped:int,failed:int,message:string,coverage?:array}
 */
function tgosImportJobPath(): string
{
    return tgosDir() . '/import-job.json';
}

function tgosImportRowsPath(): string
{
    return tgosDir() . '/_import-rows.json';
}

function tgosImportMapCachePath(): string
{
    return tgosDir() . '/_import-map.json';
}

function tgosLoadImportJob(): array
{
    return realpriceLoadJsonFile(tgosImportJobPath(), [
        'status' => 'idle',
        'offset' => 0,
        'total' => 0,
        'matched' => 0,
        'skipped' => 0,
        'failed' => 0,
        'original_name' => '',
        'message' => '',
        'started_at' => '',
        'updated_at' => '',
    ]);
}

function tgosSaveImportJob(array $job): void
{
    $job['updated_at'] = date(DATE_ATOM);
    realpriceSaveJsonFile(tgosImportJobPath(), $job);
}

/**
 * Parse TGOS result CSV into normalized rows (only rows with numeric X/Y).
 *
 * @return array{rows: list<array<string,mixed>>, original_name: string}
 */
function tgosParseResultUpload(string $tmpPath, string $originalName = ''): array
{
    if ($tmpPath === '' || !is_file($tmpPath)) {
        throw new RuntimeException('找不到上傳的結果檔。');
    }

    $raw = file_get_contents($tmpPath);
    if ($raw === false || $raw === '') {
        throw new RuntimeException('結果檔是空的。');
    }

    $rawUtf = $raw;
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $rawUtf = substr($raw, 3);
    } elseif (!mb_check_encoding($raw, 'UTF-8')) {
        $converted = @iconv('BIG5', 'UTF-8//IGNORE', $raw);
        if (is_string($converted) && $converted !== '') {
            $rawUtf = $converted;
        }
    } elseif (!preg_match('/Address|Response_|地址|門牌/u', substr($raw, 0, 800))) {
        $converted = @iconv('BIG5', 'UTF-8//IGNORE', $raw);
        if (is_string($converted) && preg_match('/Address|Response_|地址|門牌/u', substr($converted, 0, 800))) {
            $rawUtf = $converted;
        }
    }

    $tmpUtf = tgosDir() . '/_import-utf8-' . date('YmdHis') . '.csv';
    file_put_contents($tmpUtf, $rawUtf);

    $fh = fopen($tmpUtf, 'rb');
    if ($fh === false) {
        @unlink($tmpUtf);
        throw new RuntimeException('無法讀取結果檔。');
    }

    $header = fgetcsv($fh);
    if (!is_array($header) || $header === []) {
        fclose($fh);
        @unlink($tmpUtf);
        throw new RuntimeException('結果檔缺少表頭。預期欄位：id, Address, Response_Address, Response_X, Response_Y');
    }
    $norm = [];
    foreach ($header as $i => $col) {
        $h = strtolower(trim((string) $col));
        $h = str_replace([' ', '_'], '', $h);
        $norm[$i] = $h;
    }
    $colId = tgosFindHeaderIndex($norm, ['id', '編號', '序號']);
    $colAddr = tgosFindHeaderIndex($norm, ['address', 'addr', '地址', '門牌地址', '輸入地址']);
    $colResp = tgosFindHeaderIndex($norm, ['responseaddress', 'response_address', '回傳地址', '比對地址', '結果地址']);
    $colX = tgosFindHeaderIndex($norm, ['responsex', 'response_x', 'x', 'twd97x', '經度', 'lon', 'lng']);
    $colY = tgosFindHeaderIndex($norm, ['responsey', 'response_y', 'y', 'twd97y', '緯度', 'lat']);

    if ($colX < 0 || $colY < 0) {
        fclose($fh);
        @unlink($tmpUtf);
        throw new RuntimeException('結果檔找不到坐標欄（Response_X / Response_Y 或經緯度）。');
    }

    $rows = [];
    while (($row = fgetcsv($fh)) !== false) {
        if (!is_array($row) || $row === []) {
            continue;
        }
        $id = $colId >= 0 ? trim((string) ($row[$colId] ?? '')) : '';
        $addr = $colAddr >= 0 ? trim((string) ($row[$colAddr] ?? '')) : '';
        $resp = $colResp >= 0 ? trim((string) ($row[$colResp] ?? '')) : '';
        $xRaw = trim((string) ($row[$colX] ?? ''));
        $yRaw = trim((string) ($row[$colY] ?? ''));
        if ($xRaw === '' || $yRaw === '' || !is_numeric($xRaw) || !is_numeric($yRaw)) {
            continue;
        }
        $rows[] = [
            'id' => $id,
            'address' => $addr,
            'response' => $resp,
            'x' => (float) $xRaw,
            'y' => (float) $yRaw,
        ];
    }
    fclose($fh);
    @unlink($tmpUtf);

    if ($rows === []) {
        throw new RuntimeException('結果檔沒有可匯入的坐標列（可能全部比對失敗）。');
    }

    return [
        'rows' => $rows,
        'original_name' => $originalName !== '' ? $originalName : basename($tmpPath),
    ];
}

function tgosImportJobStart(string $tmpPath, string $originalName = ''): array
{
    @set_time_limit(120);
    $parsed = tgosParseResultUpload($tmpPath, $originalName);
    $rows = $parsed['rows'];
    $name = (string) $parsed['original_name'];

    $previewIds = [];
    foreach ($rows as $row) {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id !== '') {
            $previewIds[] = $id;
            if (count($previewIds) >= 200) {
                break;
            }
        }
    }
    $map = tgosFindMapForIds($previewIds);
    $mapItems = is_array($map['items'] ?? null) ? $map['items'] : [];
    realpriceSaveJsonFile(tgosImportMapCachePath(), ['items' => $mapItems]);
    realpriceSaveJsonFile(tgosImportRowsPath(), ['rows' => $rows]);

    $job = [
        'status' => 'running',
        'offset' => 0,
        'total' => count($rows),
        'matched' => 0,
        'skipped' => 0,
        'failed' => 0,
        'original_name' => $name,
        'message' => '已解析 ' . number_format(count($rows)) . ' 筆有效坐標，開始分批寫入…',
        'started_at' => date(DATE_ATOM),
        'pct' => 0,
    ];
    tgosSaveImportJob($job);
    return $job;
}

function tgosImportJobChunk(int $limit = 300): array
{
    @set_time_limit(60);
    @ini_set('memory_limit', '512M');
    $limit = max(50, min(800, $limit));
    $job = tgosLoadImportJob();
    if (($job['status'] ?? '') !== 'running') {
        return [
            'job' => $job,
            'done' => true,
            'message' => (string) ($job['message'] ?? '目前沒有進行中的 TGOS 匯入。'),
        ];
    }

    $rowsFile = realpriceLoadJsonFile(tgosImportRowsPath(), []);
    $rows = is_array($rowsFile['rows'] ?? null) ? $rowsFile['rows'] : [];
    $total = count($rows);
    if ($total === 0) {
        $job['status'] = 'error';
        $job['message'] = '匯入暫存列檔遺失，請重新上傳。';
        tgosSaveImportJob($job);
        return ['job' => $job, 'done' => true, 'message' => $job['message']];
    }

    $mapFile = realpriceLoadJsonFile(tgosImportMapCachePath(), []);
    $mapItems = is_array($mapFile['items'] ?? null) ? $mapFile['items'] : [];
    $offset = max(0, (int) ($job['offset'] ?? 0));
    $slice = array_slice($rows, $offset, $limit);

    // 只寫入輕量 overlay／delta，不每批重寫 20MB+ 的 geocode-cache.json（Synology 易 504／空回應）。
    $overlay = realpriceLoadGeocodeTgosOverlay();
    $deltaItems = is_array($overlay['items'] ?? null) ? $overlay['items'] : [];
    $matched = 0;
    $skipped = 0;
    $failed = 0;
    $originalName = (string) ($job['original_name'] ?? '');

    foreach ($slice as $row) {
        if (!is_array($row)) {
            $failed++;
            continue;
        }
        $ll = tgosDetectXyToLatLng((float) ($row['x'] ?? 0), (float) ($row['y'] ?? 0));
        if ($ll === null) {
            $failed++;
            continue;
        }

        $meta = null;
        $id = (string) ($row['id'] ?? '');
        if ($id !== '' && isset($mapItems[$id]) && is_array($mapItems[$id])) {
            $meta = $mapItems[$id];
        }

        $address = '';
        $city = '';
        $district = '';
        $key = '';
        if ($meta) {
            $address = (string) ($meta['address'] ?? '');
            $city = (string) ($meta['city'] ?? '');
            $district = (string) ($meta['district'] ?? '');
            $key = (string) ($meta['key'] ?? '');
        }
        if ($address === '') {
            $address = realpriceNormalizeAddress((string) (($row['address'] ?? '') !== '' ? $row['address'] : ($row['response'] ?? '')));
        }
        if ($address === '') {
            $failed++;
            continue;
        }
        if ($key === '') {
            $key = realpriceGeocodeKey($address);
        }

        $deltaItems[$key] = [
            'address' => $address,
            'city' => $city,
            'district' => $district,
            'lat' => (float) $ll['lat'],
            'lng' => (float) $ll['lng'],
            'display_name' => (string) (($row['response'] ?? '') !== '' ? $row['response'] : $address),
            'provider' => 'tgos',
            'precision' => 'address',
            'query' => (string) (($row['address'] ?? '') !== '' ? $row['address'] : $address),
            'srs' => (string) ($ll['srs'] ?? ''),
            'source_file' => $originalName,
            'updated_at' => date(DATE_ATOM),
        ];
        $matched++;
    }

    $overlay['items'] = $deltaItems;
    realpriceSaveGeocodeTgosOverlay($overlay);

    $offset += count($slice);
    $job['offset'] = $offset;
    $job['matched'] = (int) ($job['matched'] ?? 0) + $matched;
    $job['skipped'] = (int) ($job['skipped'] ?? 0) + $skipped;
    $job['failed'] = (int) ($job['failed'] ?? 0) + $failed;
    $job['total'] = $total;
    $job['pct'] = $total > 0 ? round(($offset / $total) * 100, 1) : 100;
    $done = $offset >= $total;

    if ($done) {
        $message = 'TGOS 結果已匯入：寫入 ' . number_format((int) $job['matched']) . ' 筆'
            . ((int) $job['skipped'] > 0 ? '，略過 ' . number_format((int) $job['skipped']) . ' 筆' : '')
            . ((int) $job['failed'] > 0 ? '，無法解析 ' . number_format((int) $job['failed']) . ' 筆' : '')
            . '（已寫入 TGOS 增量快取）。';
        $job['status'] = 'done';
        $job['message'] = $message;
        $job['pct'] = 100;

        $state = tgosLoadState();
        $state['last_import_at'] = date(DATE_ATOM);
        $state['last_import_matched'] = (int) $job['matched'];
        $state['last_message'] = $message;
        tgosSaveState($state);

        realpriceOpLog('tgos_import', $message, [
            'matched' => (int) $job['matched'],
            'skipped' => (int) $job['skipped'],
            'failed' => (int) $job['failed'],
            'file' => $originalName,
            'overlay' => basename(realpriceGeocodeTgosOverlayPath()),
        ], 'ok');

        @unlink(tgosImportRowsPath());
        @unlink(tgosImportMapCachePath());
    } else {
        $job['message'] = '匯入中 ' . number_format($offset) . ' / ' . number_format($total)
            . '（本批寫入 ' . number_format($matched) . '）…';
    }
    tgosSaveImportJob($job);

    return [
        'job' => $job,
        'done' => $done,
        'chunk' => [
            'matched' => $matched,
            'skipped' => $skipped,
            'failed' => $failed,
            'offset' => $offset,
            'total' => $total,
            'pct' => (float) ($job['pct'] ?? 0),
        ],
        'message' => (string) $job['message'],
        'state' => $done ? tgosLoadState() : null,
    ];
}

function tgosImportJobCancel(): array
{
    $job = tgosLoadImportJob();
    $job['status'] = 'cancelled';
    $job['message'] = '已取消 TGOS 匯入（已寫入的座標會保留）。';
    tgosSaveImportJob($job);
    @unlink(tgosImportRowsPath());
    @unlink(tgosImportMapCachePath());
    return $job;
}

/** @deprecated Prefer chunked import_tgos_job_* for large files. */
function tgosImportResultFile(string $tmpPath, string $originalName = ''): array
{
    $job = tgosImportJobStart($tmpPath, $originalName);
    $guard = 0;
    $last = ['job' => $job, 'done' => false, 'message' => ''];
    while ($guard < 500) {
        $guard++;
        $last = tgosImportJobChunk(800);
        if (!empty($last['done'])) {
            break;
        }
    }
    $job = is_array($last['job'] ?? null) ? $last['job'] : $job;
    return [
        'matched' => (int) ($job['matched'] ?? 0),
        'skipped' => (int) ($job['skipped'] ?? 0),
        'failed' => (int) ($job['failed'] ?? 0),
        'rows_with_xy' => (int) ($job['total'] ?? 0),
        'message' => (string) ($job['message'] ?? $last['message'] ?? ''),
    ];
}

function tgosFindHeaderIndex(array $normHeaders, array $aliases): int
{
    foreach ($normHeaders as $i => $h) {
        foreach ($aliases as $alias) {
            $a = strtolower(str_replace([' ', '_'], '', $alias));
            if ($h === $a || str_contains($h, $a)) {
                return (int) $i;
            }
        }
    }
    return -1;
}

/**
 * Optional real-time QueryAddr (全國門牌定位). Needs tgos_app_id + tgos_api_key in config.local.php.
 */
function tgosQueryAddr(string $address): array
{
    $cfg = tgosLocalConfig();
    $appId = trim((string) ($cfg['tgos_app_id'] ?? ''));
    $apiKey = trim((string) ($cfg['tgos_api_key'] ?? ''));
    if ($appId === '' || $apiKey === '') {
        throw new RuntimeException('尚未設定 tgos_app_id / tgos_api_key（此為「全國門牌定位」即時 API，與批次上傳金鑰不同）。');
    }

    $address = realpriceNormalizeAddress($address);
    $address = preg_replace('/^\d{3,5}/u', '', $address) ?? $address;
    $address = str_replace(['巿'], ['市'], $address);
    $address = trim($address);
    $params = http_build_query([
        'oAPPId' => $appId,
        'oAPIKey' => $apiKey,
        'oAddress' => $address,
        'oSRS' => 'EPSG:4326',
        'oFuzzyType' => '2',
        'oResultDataType' => 'JSON',
        'oFuzzyBuffer' => '0',
        'oIsOnlyFullMatch' => 'false',
        'oIsSupportPast' => 'true',
        'oIsShowCodeBase' => 'false',
        'oIsLockCounty' => 'false',
        'oIsLockTown' => 'false',
        'oIsLockVillage' => 'false',
        'oIsLockRoadSection' => 'false',
        'oIsLockLane' => 'false',
        'oIsLockAlley' => 'false',
        'oIsLockArea' => 'false',
        'oIsSameNumber_SubNumber' => 'true',
        'oCanIgnoreVillage' => 'true',
        'oCanIgnoreNeighborhood' => 'true',
        'oReturnMaxCount' => '1',
    ]);
    $url = 'https://addr.tgos.tw/AddrWS/v40/QueryAddr.asmx/QueryAddr?' . $params;
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'header' => "User-Agent: OpenLiveMap/1.0\r\nAccept: application/json\r\n",
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || $body === '') {
        throw new RuntimeException('TGOS QueryAddr 無回應。');
    }

    // Sometimes wrapped in XML; try JSON extract
    $json = json_decode($body, true);
    if (!is_array($json)) {
        if (preg_match('/\{.*\}/s', $body, $m)) {
            $json = json_decode($m[0], true);
        }
    }
    if (!is_array($json)) {
        throw new RuntimeException('TGOS QueryAddr 回傳無法解析。');
    }

    $candidates = $json['AddressList'] ?? $json['Info'] ?? $json;
    if (isset($candidates[0]) && is_array($candidates[0])) {
        $first = $candidates[0];
    } elseif (is_array($candidates) && isset($candidates['X'])) {
        $first = $candidates;
    } else {
        return ['found' => false, 'lat' => 0.0, 'lng' => 0.0, 'display_name' => '', 'precision' => '', 'query' => $address];
    }

    $x = (float) ($first['X'] ?? $first['x'] ?? $first['lon'] ?? $first['lng'] ?? 0);
    $y = (float) ($first['Y'] ?? $first['y'] ?? $first['lat'] ?? 0);
    $ll = tgosDetectXyToLatLng($x, $y);
    if ($ll === null) {
        return ['found' => false, 'lat' => 0.0, 'lng' => 0.0, 'display_name' => '', 'precision' => '', 'query' => $address];
    }

    return [
        'found' => true,
        'lat' => (float) $ll['lat'],
        'lng' => (float) $ll['lng'],
        'display_name' => (string) ($first['Address'] ?? $first['address'] ?? $address),
        'precision' => 'address',
        'query' => $address,
        'provider' => 'tgos',
    ];
}
