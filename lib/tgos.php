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
 * Strip leading postal code / odd spaces that hurt TGOS match rate.
 */
function tgosCleanAddressForExport(string $address): string
{
    $address = realpriceNormalizeAddress($address);
    $address = preg_replace('/^\d{3,5}/u', '', $address) ?? $address;
    $address = str_replace(['巿'], ['市'], $address);
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
function tgosBuildExport(string $cityFilter = '', int $limit = 10000, string $encoding = 'utf-8'): array
{
    $items = tgosCollectPendingAddresses($cityFilter, $limit);
    if ($items === []) {
        throw new RuntimeException('沒有尚待精準定位的地址可匯出（或篩選縣市後為空）。請先跑完本機門牌比對，或確認索引存在。');
    }

    $encoding = strtolower($encoding) === 'big5' ? 'big5' : 'utf-8';
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
        // UTF-8 BOM helps Excel; TGOS web upload accepts UTF-8 or Big5.
        fwrite($fh, "\xEF\xBB\xBF" . $header);
    }

    $map = [
        'generated_at' => date(DATE_ATOM),
        'city_filter' => $cityFilter,
        'encoding' => $encoding,
        'count' => count($items),
        'items' => [],
    ];

    $id = 1000;
    foreach ($items as $item) {
        $addr = tgosCleanAddressForExport((string) $item['address']);
        $line = $id . ',' . tgosCsvEscape($addr) . ',,,';
        if ($encoding === 'big5') {
            $bin = @iconv('UTF-8', 'BIG5//IGNORE', $line . "\n");
            fwrite($fh, $bin !== false ? $bin : ($line . "\n"));
        } else {
            fwrite($fh, $line . "\n");
        }
        $map['items'][(string) $id] = [
            'key' => (string) $item['key'],
            'address' => (string) $item['address'],
            'city' => (string) $item['city'],
            'district' => (string) $item['district'],
            'export_address' => $addr,
        ];
        $id++;
    }
    fclose($fh);

    realpriceSaveJsonFile($mapPath, $map);

    $state = tgosLoadState();
    $state['last_export_at'] = date(DATE_ATOM);
    $state['last_export_count'] = count($items);
    $state['last_message'] = '已匯出 ' . count($items) . ' 筆待上傳 TGOS（' . ($cityFilter !== '' ? $cityFilter : '全部縣市') . '，' . strtoupper($encoding) . '）。';
    $exports = is_array($state['exports'] ?? null) ? $state['exports'] : [];
    array_unshift($exports, [
        'at' => $state['last_export_at'],
        'count' => count($items),
        'city' => $cityFilter,
        'file' => basename($csvPath),
        'map' => basename($mapPath),
    ]);
    $state['exports'] = array_slice($exports, 0, 20);
    tgosSaveState($state);

    return [
        'path' => $csvPath,
        'map_path' => $mapPath,
        'count' => count($items),
        'filename' => basename($csvPath),
        'encoding' => $encoding,
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
function tgosImportResultFile(string $tmpPath, string $originalName = ''): array
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
        // Header looks garbled under UTF-8 — try Big5.
        $converted = @iconv('BIG5', 'UTF-8//IGNORE', $raw);
        if (is_string($converted) && preg_match('/Address|Response_|地址|門牌/u', substr($converted, 0, 800))) {
            $rawUtf = $converted;
        }
    }

    $tmpUtf = tgosDir() . '/_import-utf8-' . date('YmdHis') . '.csv';
    file_put_contents($tmpUtf, $rawUtf);

    $fh = fopen($tmpUtf, 'rb');
    if ($fh === false) {
        throw new RuntimeException('無法讀取結果檔。');
    }

    $header = fgetcsv($fh);
    if (!is_array($header) || $header === []) {
        fclose($fh);
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
        throw new RuntimeException('結果檔找不到坐標欄（Response_X / Response_Y 或經緯度）。');
    }

    $previewIds = [];
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
        if ($id !== '') {
            $previewIds[] = $id;
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

    $map = tgosFindMapForIds($previewIds);
    $mapItems = is_array($map['items'] ?? null) ? $map['items'] : [];

    $cache = realpriceLoadGeocodeCache();
    $cached = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $matched = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($rows as $row) {
        $ll = tgosDetectXyToLatLng((float) $row['x'], (float) $row['y']);
        if ($ll === null) {
            $failed++;
            continue;
        }

        $meta = null;
        $id = (string) $row['id'];
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
            $address = realpriceNormalizeAddress((string) ($row['address'] !== '' ? $row['address'] : $row['response']));
        }
        if ($address === '') {
            $failed++;
            continue;
        }
        if ($key === '') {
            $key = realpriceGeocodeKey($address);
        }

        $existing = is_array($cached[$key] ?? null) ? $cached[$key] : null;
        if ($existing
            && (($existing['provider'] ?? '') === 'doorplate_opendata' || ($existing['provider'] ?? '') === 'local_override')
            && !realpriceGeocodeHitIsDistrictFallback($existing, $city, $district)
            && (($existing['precision'] ?? '') !== 'district')
        ) {
            $skipped++;
            continue;
        }

        $cached[$key] = [
            'address' => $address,
            'city' => $city,
            'district' => $district,
            'lat' => (float) $ll['lat'],
            'lng' => (float) $ll['lng'],
            'display_name' => (string) ($row['response'] !== '' ? $row['response'] : $address),
            'provider' => 'tgos',
            'precision' => 'address',
            'query' => (string) ($row['address'] !== '' ? $row['address'] : $address),
            'srs' => (string) ($ll['srs'] ?? ''),
            'source_file' => $originalName !== '' ? $originalName : basename($tmpPath),
            'updated_at' => date(DATE_ATOM),
        ];
        $matched++;
    }

    $cache['items'] = $cached;
    $cache['updated_at'] = date(DATE_ATOM);
    realpriceSaveJsonFile(realpriceGeocodeCachePath(), $cache);

    $message = 'TGOS 結果已匯入：寫入 ' . number_format($matched) . ' 筆'
        . ($skipped > 0 ? '，略過已有本機／手動座標 ' . number_format($skipped) . ' 筆' : '')
        . ($failed > 0 ? '，無法解析 ' . number_format($failed) . ' 筆' : '')
        . '。';

    $state = tgosLoadState();
    $state['last_import_at'] = date(DATE_ATOM);
    $state['last_import_matched'] = $matched;
    $state['last_message'] = $message;
    tgosSaveState($state);

    realpriceOpLog('tgos_import', $message, [
        'matched' => $matched,
        'skipped' => $skipped,
        'failed' => $failed,
        'file' => $originalName,
    ], 'ok');

    $coverage = realpriceCoverageStats(true);

    return [
        'matched' => $matched,
        'skipped' => $skipped,
        'failed' => $failed,
        'message' => $message,
        'coverage' => $coverage,
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

    $address = tgosCleanAddressForExport($address);
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
