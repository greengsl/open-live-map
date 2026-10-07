<?php
declare(strict_types=1);

function realpriceDir(): string
{
    return dirname(__DIR__) . '/cache/realprice';
}

function realpriceZipPath(): string
{
    return realpriceDir() . '/lvr_landcsv.zip';
}

function realpriceMetaPath(): string
{
    return realpriceDir() . '/metadata.json';
}

function realpriceIndexPath(): string
{
    return realpriceDir() . '/index.json';
}

function realpriceSqlitePath(): string
{
    return realpriceDir() . '/index.sqlite';
}

function realpriceIndexReady(): bool
{
    return is_file(realpriceSqlitePath()) || is_file(realpriceIndexPath());
}

function realpriceGeocodeCachePath(): string
{
    return realpriceDir() . '/geocode-cache.json';
}

/** TGOS 批次匯入增量（避免每批重寫巨大的 geocode-cache.json） */
function realpriceGeocodeTgosOverlayPath(): string
{
    return realpriceDir() . '/geocode-tgos-overlay.json';
}

function realpriceLoadGeocodeTgosOverlay(): array
{
    return realpriceLoadJsonFile(realpriceGeocodeTgosOverlayPath(), ['items' => [], 'updated_at' => '']);
}

function realpriceSaveGeocodeTgosOverlay(array $overlay): void
{
    $overlay['updated_at'] = date(DATE_ATOM);
    if (!isset($overlay['items']) || !is_array($overlay['items'])) {
        $overlay['items'] = [];
    }
    realpriceSaveJsonFile(realpriceGeocodeTgosOverlayPath(), $overlay);
}

function realpriceGeocodeQueuePath(): string
{
    return realpriceDir() . '/geocode-queue.json';
}

function realpriceGeocodeStatePath(): string
{
    return realpriceDir() . '/geocode-state.json';
}

function realpriceOverridePath(): string
{
    return realpriceDir() . '/overrides.json';
}

function realpriceOfficialDownloadUrl(): string
{
    return 'https://plvr.land.moi.gov.tw/Download?type=zip&fileName=lvr_landcsv.zip';
}

function realpriceSeasonsDir(): string
{
    $dir = realpriceDir() . '/seasons';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function realpriceSeasonZipPath(string $season): string
{
    $season = strtoupper(preg_replace('/[^0-9Ss]/', '', $season) ?? '');
    return realpriceSeasonsDir() . '/' . $season . '.zip';
}

function realpriceSeasonDownloadUrl(string $season): string
{
    return 'https://plvr.land.moi.gov.tw/DownloadSeason?season=' . rawurlencode($season) . '&type=zip&fileName=lvr_landcsv.zip';
}

/**
 * Build recent season codes like 115S2, 115S1, 114S4...
 */
function realpriceRecentSeasonCodes(int $count = 5): array
{
    $count = max(1, min(12, $count));
    $now = new DateTimeImmutable('today');
    $year = (int) $now->format('Y') - 1911;
    $season = (int) ceil(((int) $now->format('n')) / 3);
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = $year . 'S' . $season;
        $season--;
        if ($season < 1) {
            $season = 4;
            $year--;
        }
    }
    return $codes;
}

function realpriceIsZipFile(string $path): bool
{
    if (!is_file($path) || filesize($path) < 1000) {
        return false;
    }
    $fh = @fopen($path, 'rb');
    if ($fh === false) {
        return false;
    }
    $magic = fread($fh, 2);
    fclose($fh);
    return $magic === 'PK';
}

function realpriceHttpDownloadFile(string $url, string $destPath): int
{
    $tmp = $destPath . '.part.' . bin2hex(random_bytes(3));
    if (function_exists('curl_init')) {
        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            throw new RuntimeException('無法建立下載暫存檔。');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_USERAGENT => 'OpenLiveMap/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if ($ok === false || $status >= 400) {
            @unlink($tmp);
            throw new RuntimeException('下載失敗 HTTP ' . $status . ($err !== '' ? " ({$err})" : ''));
        }
    } else {
        $in = @fopen($url, 'rb');
        if (!is_resource($in)) {
            throw new RuntimeException('無法連線下載：' . $url);
        }
        $out = @fopen($tmp, 'wb');
        if (!is_resource($out)) {
            fclose($in);
            throw new RuntimeException('無法建立下載暫存檔。');
        }
        $bytes = stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        if ($bytes === false || $bytes <= 0) {
            @unlink($tmp);
            throw new RuntimeException('下載內容為空。');
        }
    }
    if (!@rename($tmp, $destPath)) {
        @unlink($destPath);
        if (!@rename($tmp, $destPath)) {
            @unlink($tmp);
            throw new RuntimeException('無法寫入：' . $destPath);
        }
    }
    return (int) filesize($destPath);
}

function realpriceListSeasonZipPaths(): array
{
    $paths = glob(realpriceSeasonsDir() . '/*.zip') ?: [];
    $valid = [];
    foreach ($paths as $path) {
        if (realpriceIsZipFile($path)) {
            $valid[] = $path;
        }
    }
    rsort($valid);
    return $valid;
}

/**
 * Inventory of local season ZIPs under cache/realprice/seasons（含手動放入的歷史季）.
 *
 * @return list<array{season:string,file:string,path:string,size:int,mtime:string,mtime_ts:int,index_status:string,indexed_records:int,indexed_at:string,index_note:string}>
 */
function realpriceListLocalSeasons(): array
{
    $meta = realpriceLoadMeta();
    $indexed = is_array($meta['indexed_seasons'] ?? null) ? $meta['indexed_seasons'] : [];
    $rows = [];
    foreach (realpriceListSeasonZipPaths() as $path) {
        $base = basename($path, '.zip');
        $file = basename($path);
        $season = strtoupper(preg_replace('/[^0-9Ss]/', '', $base) ?? '');
        if ($season === '') {
            $season = $base;
        }
        $mtime = @filemtime($path) ?: 0;
        $size = (int) filesize($path);
        $info = is_array($indexed[$file] ?? null) ? $indexed[$file] : (is_array($indexed[$season] ?? null) ? $indexed[$season] : []);
        $indexedRecords = (int) ($info['records'] ?? 0);
        $indexedAt = (string) ($info['indexed_at'] ?? '');
        $indexedSize = (int) ($info['size'] ?? 0);
        $indexedMtime = (int) ($info['mtime_ts'] ?? 0);
        $status = 'pending';
        $note = '尚未索引';
        if ($indexedRecords > 0 || $indexedAt !== '') {
            if ($indexedMtime > 0 && $mtime > $indexedMtime) {
                $status = 'stale';
                $note = 'ZIP 已更新，建議重建';
            } elseif ($indexedSize > 0 && $indexedSize !== $size) {
                $status = 'stale';
                $note = 'ZIP 大小變更，建議重建';
            } elseif ($indexedRecords > 0) {
                $status = 'ok';
                $note = '已索引 ' . number_format($indexedRecords) . ' 筆';
            } else {
                $status = 'empty';
                $note = '已跑過但 0 筆（請檢查 ZIP）';
            }
        }
        $rows[] = [
            'season' => $season,
            'file' => $file,
            'path' => $path,
            'size' => $size,
            'mtime' => $mtime ? date('Y-m-d H:i', $mtime) : '',
            'mtime_ts' => $mtime,
            'index_status' => $status,
            'indexed_records' => $indexedRecords,
            'indexed_at' => $indexedAt,
            'index_note' => $note,
        ];
    }
    return $rows;
}

function realpriceZipSha256(string $path): string
{
    if (!is_file($path)) return '';
    $hash = hash_file('sha256', $path);
    return is_string($hash) ? $hash : '';
}

function realpriceRemoteInfo(): array
{
    $url = realpriceOfficialDownloadUrl();
    $headers = @get_headers($url, true);
    if ($headers === false || !is_array($headers)) {
        throw new RuntimeException('無法讀取官方實價登錄檔案資訊。');
    }
    $statusLine = is_string($headers[0] ?? null) ? (string) $headers[0] : '';
    $value = static function (array $headers, string $name): string {
        $found = $headers[$name] ?? $headers[strtolower($name)] ?? '';
        if (is_array($found)) {
            $found = end($found);
        }
        return trim((string) $found);
    };
    return [
        'checked_at' => date(DATE_ATOM),
        'status' => $statusLine,
        'content_length' => (int) $value($headers, 'Content-Length'),
        'last_modified' => $value($headers, 'Last-Modified'),
        'etag' => $value($headers, 'ETag'),
    ];
}

function realpriceCityMap(): array
{
    return [
        'a' => ['name' => '臺北市', 'lat' => 25.0375, 'lng' => 121.5637],
        'b' => ['name' => '臺中市', 'lat' => 24.1477, 'lng' => 120.6736],
        'c' => ['name' => '基隆市', 'lat' => 25.1276, 'lng' => 121.7392],
        'd' => ['name' => '臺南市', 'lat' => 22.9999, 'lng' => 120.2270],
        'e' => ['name' => '高雄市', 'lat' => 22.6273, 'lng' => 120.3014],
        'f' => ['name' => '新北市', 'lat' => 25.0120, 'lng' => 121.4650],
        'g' => ['name' => '宜蘭縣', 'lat' => 24.7021, 'lng' => 121.7378],
        'h' => ['name' => '桃園市', 'lat' => 24.9937, 'lng' => 121.3009],
        'i' => ['name' => '嘉義市', 'lat' => 23.4801, 'lng' => 120.4491],
        'j' => ['name' => '新竹縣', 'lat' => 24.8387, 'lng' => 121.0177],
        'k' => ['name' => '苗栗縣', 'lat' => 24.5602, 'lng' => 120.8214],
        'm' => ['name' => '南投縣', 'lat' => 23.9609, 'lng' => 120.9719],
        'n' => ['name' => '彰化縣', 'lat' => 24.0750, 'lng' => 120.5440],
        'o' => ['name' => '新竹市', 'lat' => 24.8138, 'lng' => 120.9675],
        'p' => ['name' => '雲林縣', 'lat' => 23.7092, 'lng' => 120.4313],
        'q' => ['name' => '嘉義縣', 'lat' => 23.4518, 'lng' => 120.2555],
        't' => ['name' => '屏東縣', 'lat' => 22.5519, 'lng' => 120.5488],
        'u' => ['name' => '花蓮縣', 'lat' => 23.9872, 'lng' => 121.6015],
        'v' => ['name' => '臺東縣', 'lat' => 22.7972, 'lng' => 121.0714],
        'w' => ['name' => '金門縣', 'lat' => 24.4321, 'lng' => 118.3171],
        'x' => ['name' => '澎湖縣', 'lat' => 23.5711, 'lng' => 119.5793],
        'z' => ['name' => '連江縣', 'lat' => 26.1602, 'lng' => 119.9517],
    ];
}

function realpriceTypeMap(): array
{
    return [
        'a' => 'sale',
        'b' => 'presale',
        'c' => 'rent',
    ];
}

function realpriceLoadMeta(): array
{
    $path = realpriceMetaPath();
    if (!is_file($path)) {
        return [];
    }
    $json = json_decode((string) file_get_contents($path), true);
    return is_array($json) ? $json : [];
}

function realpriceSaveMeta(array $meta): void
{
    $dir = realpriceDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents(realpriceMetaPath(), $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('實價登錄 metadata 寫入失敗。');
    }
}

function realpriceStatus(): array
{
    $zipPath = realpriceZipPath();
    $meta = realpriceLoadMeta();
    $exists = is_file($zipPath);
    return [
        'download_url' => realpriceOfficialDownloadUrl(),
        'zip_path' => $zipPath,
        'zip_exists' => $exists,
        'zip_size' => $exists ? (int) filesize($zipPath) : 0,
        'downloaded_at' => (string) ($meta['downloaded_at'] ?? ''),
        'source_url' => (string) ($meta['source_url'] ?? realpriceOfficialDownloadUrl()),
        'zip_sha256' => (string) ($meta['zip_sha256'] ?? ($exists ? realpriceZipSha256($zipPath) : '')),
        'remote_checked_at' => (string) ($meta['remote_checked_at'] ?? ''),
        'remote_content_length' => (int) ($meta['remote_content_length'] ?? 0),
        'remote_last_modified' => (string) ($meta['remote_last_modified'] ?? ''),
        'remote_etag' => (string) ($meta['remote_etag'] ?? ''),
        'remote_status' => (string) ($meta['remote_status'] ?? ''),
        'last_download_changed' => array_key_exists('last_download_changed', $meta) ? (bool) $meta['last_download_changed'] : null,
        'indexed_at' => (string) ($meta['indexed_at'] ?? ''),
        'indexed_count' => (int) ($meta['indexed_count'] ?? 0),
        'indexed_file_count' => (int) ($meta['indexed_file_count'] ?? 0),
        'indexed_zip_count' => (int) ($meta['indexed_zip_count'] ?? 0),
        'index_path' => is_file(realpriceSqlitePath()) ? realpriceSqlitePath() : realpriceIndexPath(),
        'index_exists' => realpriceIndexReady(),
        'index_size' => is_file(realpriceSqlitePath())
            ? (int) filesize(realpriceSqlitePath())
            : (is_file(realpriceIndexPath()) ? (int) filesize(realpriceIndexPath()) : 0),
        'season_zip_count' => count(realpriceListSeasonZipPaths()),
        'local_seasons' => realpriceListLocalSeasons(),
        'seasons_dir' => realpriceSeasonsDir(),
        'seasons' => is_array($meta['seasons'] ?? null) ? $meta['seasons'] : [],
        'notice' => ((int) ($meta['indexed_count'] ?? 0) > 0)
            ? (string) ($meta['notice'] ?? '實價登錄索引已建立。')
            : ((count(realpriceListSeasonZipPaths()) > 0 || $exists)
                ? (string) ($meta['notice'] ?? '實價登錄 ZIP 已下載，尚未建立地圖索引。')
                : '尚未下載實價登錄 ZIP。'),
    ];
}

function realpriceDownloadZip(): array
{
    // Prefer seasonal open-data batches. The old "本期" URL is only a thin current slice (~1-2MB)
    // and makes the map look empty when filtered to "近一年".
    return realpriceDownloadRecentSeasons(5);
}

function realpriceDownloadRecentSeasons(int $seasonCount = 5): array
{
    @set_time_limit(0);
    $dir = realpriceSeasonsDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $codes = realpriceRecentSeasonCodes($seasonCount);
    $downloaded = [];
    $skipped = [];
    foreach ($codes as $code) {
        $path = realpriceSeasonZipPath($code);
        $url = realpriceSeasonDownloadUrl($code);
        $tmp = $path . '.download';
        try {
            $bytes = realpriceHttpDownloadFile($url, $tmp);
            if ($bytes < 100000 || !realpriceIsZipFile($tmp)) {
                @unlink($tmp);
                $skipped[] = $code . '（非有效季度 ZIP）';
                continue;
            }
            if (is_file($path)) {
                @unlink($path);
            }
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                throw new RuntimeException('無法保存季度 ZIP：' . $code);
            }
            $downloaded[] = [
                'season' => $code,
                'size' => (int) filesize($path),
                'path' => $path,
            ];
        } catch (Throwable $e) {
            @unlink($tmp);
            $skipped[] = $code . '（' . $e->getMessage() . '）';
        }
    }

    // Also refresh the thin "本期" file for newest declarations (optional merge source).
    $currentOk = false;
    try {
        $currentTmp = realpriceZipPath() . '.download';
        $bytes = realpriceHttpDownloadFile(realpriceOfficialDownloadUrl(), $currentTmp);
        if ($bytes > 1000 && realpriceIsZipFile($currentTmp)) {
            if (is_file(realpriceZipPath())) {
                @unlink(realpriceZipPath());
            }
            @rename($currentTmp, realpriceZipPath());
            $currentOk = true;
        } else {
            @unlink($currentTmp);
        }
    } catch (Throwable $e) {
        @unlink(realpriceZipPath() . '.download');
    }

    if ($downloaded === [] && !realpriceIsZipFile(realpriceZipPath())) {
        throw new RuntimeException('無法下載任何實價登錄季度 ZIP。略過：' . implode('、', $skipped));
    }

    $meta = realpriceLoadMeta();
    $meta['source_url'] = realpriceSeasonDownloadUrl((string) ($downloaded[0]['season'] ?? $codes[0]));
    $meta['downloaded_at'] = date(DATE_ATOM);
    $meta['seasons'] = $downloaded;
    $meta['seasons_skipped'] = $skipped;
    $meta['current_period_downloaded'] = $currentOk;
    $meta['zip_size'] = is_file(realpriceZipPath()) ? (int) filesize(realpriceZipPath()) : 0;
    $meta['zip_sha256'] = is_file(realpriceZipPath()) ? realpriceZipSha256(realpriceZipPath()) : '';
    $meta['last_download_changed'] = true;
    $meta['notice'] = '已下載 ' . count($downloaded) . ' 個季度批次'
        . ($currentOk ? '（含本期）' : '')
        . '。請接著執行分批重建索引。';
    realpriceSaveMeta($meta);

    return realpriceStatus();
}

function realpriceCheckRemoteUpdate(): array
{
    $remote = realpriceRemoteInfo();
    $meta = realpriceLoadMeta();
    $status = realpriceStatus();
    $remoteLength = (int) ($remote['content_length'] ?? 0);
    $localSize = (int) ($status['zip_size'] ?? 0);
    $likelyChanged = !$status['zip_exists'];
    if ($remoteLength > 0 && $localSize > 0 && $remoteLength !== $localSize) {
        $likelyChanged = true;
    }
    $remoteLastModified = (string) ($remote['last_modified'] ?? '');
    $localLastModified = (string) ($meta['remote_last_modified'] ?? '');
    if ($remoteLastModified !== '' && $localLastModified !== '' && $remoteLastModified !== $localLastModified) {
        $likelyChanged = true;
    }
    $remoteEtag = (string) ($remote['etag'] ?? '');
    $localEtag = (string) ($meta['remote_etag'] ?? '');
    if ($remoteEtag !== '' && $localEtag !== '' && $remoteEtag !== $localEtag) {
        $likelyChanged = true;
    }

    $meta['remote_checked_at'] = (string) $remote['checked_at'];
    $meta['remote_content_length'] = $remoteLength;
    $meta['remote_last_modified'] = $remoteLastModified;
    $meta['remote_etag'] = $remoteEtag;
    $meta['remote_status'] = (string) ($remote['status'] ?? '');
    $meta['remote_likely_changed'] = $likelyChanged;
    realpriceSaveMeta($meta);

    $status = realpriceStatus();
    $status['remote_likely_changed'] = $likelyChanged;
    $status['season_zip_count'] = count(realpriceListSeasonZipPaths());
    return $status;
}

function realpriceLoadSqliteMeta(): array
{
    $path = realpriceSqlitePath();
    if (!is_file($path) || !class_exists('PDO')) {
        return [];
    }
    try {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $rows = $pdo->query('SELECT key, value FROM meta')->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!is_array($rows) || $rows === []) {
            return [];
        }
        $out = [
            'generated_at' => '',
            'source_url' => '',
            'zip_paths' => [],
            'zip_size' => 0,
            'file_count' => 0,
            'record_count' => 0,
            'records' => [],
            'city_map' => realpriceCityMap(),
            'storage' => 'sqlite',
        ];
        foreach ($rows as $key => $value) {
            $decoded = json_decode((string) $value, true);
            $out[(string) $key] = $decoded === null && !is_numeric((string) $value)
                ? (string) $value
                : $decoded;
        }
        if (!is_array($out['city_map'] ?? null)) {
            $out['city_map'] = realpriceCityMap();
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function realpriceOpenSqliteReadonly(): ?PDO
{
    static $pdoInstance = null;
    static $pdoFailed = false;
    if ($pdoFailed) {
        return null;
    }
    if ($pdoInstance instanceof PDO) {
        return $pdoInstance;
    }
    $path = realpriceSqlitePath();
    if (!is_file($path) || !class_exists('PDO')) {
        $pdoFailed = true;
        return null;
    }
    try {
        $pdoInstance = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdoInstance->exec('CREATE INDEX IF NOT EXISTS idx_records_type_date_city_district ON records(type, date, city, district)');
        realpriceEnsureFloorColumns($pdoInstance);
        return $pdoInstance;
    } catch (Throwable $e) {
        $pdoFailed = true;
        return null;
    }
}

function realpriceTableHasColumn(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        if (!$stmt) {
            return false;
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ((string) ($row['name'] ?? '') === $column) {
                return true;
            }
        }
    } catch (Throwable $e) {
        return false;
    }
    return false;
}

/** 舊索引相容：補上移轉層次／總樓層數字段（資料需另 backfill 或重建）。 */
function realpriceEnsureFloorColumns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    foreach (['records', 'map_points'] as $table) {
        try {
            $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($table))->fetchColumn();
            if (!$exists) {
                continue;
            }
            if (!realpriceTableHasColumn($pdo, $table, 'floor')) {
                $pdo->exec('ALTER TABLE ' . $table . " ADD COLUMN floor TEXT NOT NULL DEFAULT ''");
            }
            if (!realpriceTableHasColumn($pdo, $table, 'total_floors')) {
                $pdo->exec('ALTER TABLE ' . $table . " ADD COLUMN total_floors TEXT NOT NULL DEFAULT ''");
            }
        } catch (Throwable $e) {
            // 唯讀或鎖定時略過；SELECT 端會再相容處理。
        }
    }
}

/** @return list<string> */
function realpriceCityNamesForBounds(array $bounds, array $cityMap, float $paddingDeg = 0.25): array
{
    if (count($bounds) !== 4) {
        return [];
    }
    [$south, $west, $north, $east] = $bounds;
    $south -= $paddingDeg;
    $west -= $paddingDeg;
    $north += $paddingDeg;
    $east += $paddingDeg;
    $names = [];
    foreach ($cityMap as $info) {
        if (!is_array($info)) {
            continue;
        }
        $lat = (float) ($info['lat'] ?? 0);
        $lng = (float) ($info['lng'] ?? 0);
        if ($lat >= $south && $lat <= $north && $lng >= $west && $lng <= $east) {
            $name = (string) ($info['name'] ?? '');
            if ($name !== '') {
                $names[] = $name;
            }
        }
    }
    return $names;
}

function realpriceDistrictClusterPosition(string $summaryKey, string $cityName, string $districtName, array $cityInfo, array $geocoded): array
{
    $districtKey = realpriceGeocodeScopedKey('district', $cityName, $districtName);
    $districtHit = is_array($geocoded[$districtKey] ?? null) ? $geocoded[$districtKey] : null;
    $cityLat = (float) ($cityInfo['lat'] ?? 0);
    $cityLng = (float) ($cityInfo['lng'] ?? 0);
    if ($districtHit) {
        return [
            'lat' => (float) ($districtHit['lat'] ?? 0),
            'lng' => (float) ($districtHit['lng'] ?? 0),
            'position_source' => 'district_cache',
        ];
    }
    $seed = abs(crc32($summaryKey));
    return [
        'lat' => $cityLat + ((($seed % 17) - 8) * 0.012),
        'lng' => $cityLng + ((((int) ($seed / 17) % 17) - 8) * 0.012),
        'position_source' => 'city_offset',
    ];
}

function realpriceFetchCitySamples(PDO $pdo, string $type, string $since, string $city, int $limit = 5): array
{
    $stmt = $pdo->prepare(
        'SELECT district, date, target, building_type, total_price, unit_price_ping
         FROM records
         WHERE type = ? AND date >= ? AND city = ?
         ORDER BY date DESC
         LIMIT ' . max(1, min(5, $limit))
    );
    $stmt->execute([$type, $since, $city]);
    $samples = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!is_array($row)) {
            continue;
        }
        $samples[] = [
            'district' => (string) ($row['district'] ?? ''),
            'date' => (string) ($row['date'] ?? ''),
            'target' => (string) ($row['target'] ?? ''),
            'building_type' => (string) ($row['building_type'] ?? ''),
            'total_price' => (int) ($row['total_price'] ?? 0),
            'unit_price_ping' => (int) ($row['unit_price_ping'] ?? 0),
        ];
    }
    return $samples;
}

function realpriceFetchDistrictSamples(PDO $pdo, string $type, string $since, string $city, string $district, int $limit = 5): array
{
    $stmt = $pdo->prepare(
        'SELECT district, date, target, building_type, total_price, unit_price_ping
         FROM records
         WHERE type = ? AND date >= ? AND city = ? AND district = ?
         ORDER BY date DESC
         LIMIT ' . max(1, min(5, $limit))
    );
    $stmt->execute([$type, $since, $city, $district]);
    $samples = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!is_array($row)) {
            continue;
        }
        $samples[] = [
            'district' => (string) ($row['district'] ?? $district),
            'date' => (string) ($row['date'] ?? ''),
            'target' => (string) ($row['target'] ?? ''),
            'building_type' => (string) ($row['building_type'] ?? ''),
            'total_price' => (int) ($row['total_price'] ?? 0),
            'unit_price_ping' => (int) ($row['unit_price_ping'] ?? 0),
        ];
    }
    return $samples;
}

function realpriceQueryFromSqlite(array $params, string $clusterMode): ?array
{
    $pdo = realpriceOpenSqliteReadonly();
    if (!$pdo instanceof PDO) {
        return null;
    }

    $index = realpriceLoadSqliteMeta();
    $cityMap = is_array($index['city_map'] ?? null) ? $index['city_map'] : realpriceCityMap();
    $type = (string) ($params['type'] ?? 'sale');
    if (!in_array($type, ['sale', 'presale', 'rent'], true)) {
        $type = 'sale';
    }
    $period = (int) ($params['period'] ?? 12);
    $zoom = (int) ($params['zoom'] ?? 0);
    $since = realpriceSinceDate($period);
    $bounds = is_array($params['bounds'] ?? null) ? $params['bounds'] : [];
    $districtClusterZoomMin = 11;
    $itemZoomMin = 14;
    $itemFullZoomMin = 16;

    $stmt = $pdo->prepare(
        'SELECT city, city_code, district,
                COUNT(*) AS cnt,
                SUM(total_price) AS total_price_sum,
                SUM(CASE WHEN unit_price_ping > 0 THEN unit_price_ping ELSE 0 END) AS unit_ping_sum,
                SUM(CASE WHEN unit_price_ping > 0 THEN 1 ELSE 0 END) AS unit_ping_count,
                MAX(date) AS latest_date
         FROM records
         WHERE type = ? AND date >= ?
         GROUP BY city, city_code, district'
    );
    $stmt->execute([$type, $since]);
    $districtRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($districtRows)) {
        return null;
    }

    $geocoded = [];
    if ($clusterMode === 'district') {
        $geocodeCache = realpriceLoadGeocodeCache();
        $geocoded = is_array($geocodeCache['items'] ?? null) ? $geocodeCache['items'] : [];
    }

    $groups = [];
    $districtClusters = [];
    $coarseCount = 0;

    foreach ($districtRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $cityName = (string) ($row['city'] ?? '');
        $cityCode = (string) ($row['city_code'] ?? '');
        $districtName = (string) ($row['district'] ?? '未分區');
        $count = (int) ($row['cnt'] ?? 0);
        if ($count <= 0 || $cityName === '') {
            continue;
        }
        $cityInfo = $cityMap[$cityCode] ?? ['name' => $cityName, 'lat' => 0.0, 'lng' => 0.0];
        $cityLat = (float) ($cityInfo['lat'] ?? 0);
        $cityLng = (float) ($cityInfo['lng'] ?? 0);
        $cityInBounds = realpriceBoundsContains($bounds, $cityLat, $cityLng);
        if ($cityInBounds) {
            $coarseCount += $count;
        }

        if ($clusterMode === 'district') {
            $summaryKey = $cityName . '|' . $districtName;
            $pos = realpriceDistrictClusterPosition($summaryKey, $cityName, $districtName, $cityInfo, $geocoded);
            if (!realpriceBoundsContains($bounds, (float) $pos['lat'], (float) $pos['lng'])) {
                continue;
            }
            $unitPingCount = (int) ($row['unit_ping_count'] ?? 0);
            $districtClusters[] = [
                'id' => 'realprice-district-cluster-' . sha1($summaryKey . $type),
                'kind' => 'realprice-district-cluster',
                'city' => $cityName,
                'district' => $districtName,
                'city_code' => $cityCode,
                'lat' => round((float) $pos['lat'], 7),
                'lng' => round((float) $pos['lng'], 7),
                'type' => $type,
                'count' => $count,
                'avg_total_price' => (int) round(((int) ($row['total_price_sum'] ?? 0)) / $count),
                'avg_unit_price_ping' => $unitPingCount > 0
                    ? (int) round(((int) ($row['unit_ping_sum'] ?? 0)) / $unitPingCount)
                    : 0,
                'latest_date' => (string) ($row['latest_date'] ?? ''),
                'position_source' => (string) ($pos['position_source'] ?? 'city_offset'),
                'samples' => [],
            ];
            continue;
        }

        if (!$cityInBounds) {
            continue;
        }

        if (!isset($groups[$cityName])) {
            $groups[$cityName] = [
                'id' => 'realprice-' . $cityCode . '-' . $type,
                'kind' => 'realprice',
                'city' => $cityName,
                'city_code' => $cityCode,
                'lat' => $cityLat,
                'lng' => $cityLng,
                'type' => $type,
                'count' => 0,
                'total_price_sum' => 0,
                'unit_price_ping_sum' => 0,
                'unit_price_count' => 0,
                'located_count' => 0,
                'address_count' => 0,
                'district_count' => 0,
                'latest_date' => '',
                'districts' => [],
                'samples' => realpriceFetchCitySamples($pdo, $type, $since, $cityName),
            ];
        }
        $group =& $groups[$cityName];
        $group['count'] += $count;
        $group['total_price_sum'] += (int) ($row['total_price_sum'] ?? 0);
        $unitPingCount = (int) ($row['unit_ping_count'] ?? 0);
        $group['unit_price_ping_sum'] += (int) ($row['unit_ping_sum'] ?? 0);
        $group['unit_price_count'] += $unitPingCount;
        $date = (string) ($row['latest_date'] ?? '');
        if ($date > $group['latest_date']) {
            $group['latest_date'] = $date;
        }
        $group['districts'][$districtName] = [
            'name' => $districtName,
            'count' => $count,
            'total_price_sum' => (int) ($row['total_price_sum'] ?? 0),
            'unit_price_ping_sum' => (int) ($row['unit_ping_sum'] ?? 0),
            'unit_price_count' => $unitPingCount,
        ];
        unset($group);
    }

    usort($districtClusters, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
    if ($clusterMode === 'district') {
        foreach (array_slice($districtClusters, 0, 24, true) as $index => $cluster) {
            $districtClusters[$index]['samples'] = realpriceFetchDistrictSamples(
                $pdo,
                $type,
                $since,
                (string) ($cluster['city'] ?? ''),
                (string) ($cluster['district'] ?? '')
            );
        }
    }

    $clusters = array_values(array_map(static function (array $group): array {
        $districts = array_values(array_map(static function (array $district): array {
            return [
                'name' => $district['name'],
                'count' => $district['count'],
                'avg_total_price' => $district['count'] > 0 ? (int) round($district['total_price_sum'] / $district['count']) : 0,
                'avg_unit_price_ping' => $district['unit_price_count'] > 0
                    ? (int) round($district['unit_price_ping_sum'] / $district['unit_price_count'])
                    : 0,
            ];
        }, $group['districts']));
        usort($districts, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $count = (int) $group['count'];
        return [
            'id' => $group['id'],
            'kind' => 'realprice',
            'city' => $group['city'],
            'city_code' => $group['city_code'],
            'lat' => $group['lat'],
            'lng' => $group['lng'],
            'type' => $group['type'],
            'count' => $count,
            'avg_total_price' => $count > 0 ? (int) round($group['total_price_sum'] / $count) : 0,
            'avg_unit_price_ping' => $group['unit_price_count'] > 0
                ? (int) round($group['unit_price_ping_sum'] / $group['unit_price_count'])
                : 0,
            'located_count' => 0,
            'address_count' => 0,
            'district_count' => 0,
            'latest_date' => $group['latest_date'],
            'districts' => array_slice($districts, 0, 8),
            'samples' => array_slice($group['samples'], 0, 5),
        ];
    }, $groups));
    usort($clusters, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

    $filteredCount = $clusterMode === 'district'
        ? array_sum(array_map(static fn (array $item): int => (int) $item['count'], $districtClusters))
        : array_sum(array_map(static fn (array $item): int => (int) $item['count'], $clusters));

    return [
        'clusters' => $clusterMode === 'city' ? $clusters : [],
        'district_clusters' => $districtClusters,
        'items' => [],
        'district_summaries' => [],
        'item_count' => 0,
        'item_limited' => false,
        'address_item_count' => 0,
        'district_item_count' => 0,
        'district_cluster_count' => count($districtClusters),
        'address_hit_count' => 0,
        'district_hit_count' => 0,
        'missing_geocode_count' => 0,
        'filtered_count' => $filteredCount,
        'coarse_count' => $coarseCount,
        'period' => $period,
        'type' => $type,
        'since' => $since,
        'zoom' => $zoom,
        'cluster_mode' => $clusterMode,
        'item_zoom_min' => $itemZoomMin,
        'item_full_zoom_min' => $itemFullZoomMin,
        'district_cluster_zoom_min' => $districtClusterZoomMin,
        'cluster_hide_zoom_min' => $districtClusterZoomMin,
        'query_engine' => 'sqlite_aggregate',
    ];
}

function realpriceMapPointsReady(PDO $pdo): bool
{
    try {
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='map_points'")->fetchColumn();
        if (!$exists) {
            return false;
        }
        $count = (int) $pdo->query('SELECT COUNT(*) FROM map_points')->fetchColumn();
        return $count > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function realpriceQueryDetailFromSqlite(array $params): ?array
{
    $pdo = realpriceOpenSqliteReadonly();
    if (!$pdo instanceof PDO || !realpriceMapPointsReady($pdo)) {
        return null;
    }

    $type = (string) ($params['type'] ?? 'sale');
    if (!in_array($type, ['sale', 'presale', 'rent'], true)) {
        $type = 'sale';
    }
    $period = (int) ($params['period'] ?? 12);
    $zoom = (int) ($params['zoom'] ?? 0);
    $since = realpriceSinceDate($period);
    $bounds = is_array($params['bounds'] ?? null) ? $params['bounds'] : [];
    if (count($bounds) !== 4) {
        return null;
    }
    [$south, $west, $north, $east] = $bounds;
    $districtClusterZoomMin = 11;
    $itemZoomMin = 14;
    $itemFullZoomMin = 16;
    $itemLimit = $zoom >= $itemFullZoomMin ? 800 : 350;

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM map_points
         WHERE type = ? AND date >= ?
           AND lat BETWEEN ? AND ?
           AND lng BETWEEN ? AND ?'
    );
    $countStmt->execute([$type, $since, $south, $north, $west, $east]);
    $filteredCount = (int) $countStmt->fetchColumn();

    $hasFloor = realpriceTableHasColumn($pdo, 'map_points', 'floor');
    $floorSelect = $hasFloor ? 'floor, total_floors,' : "'' AS floor, '' AS total_floors,";
    $stmt = $pdo->prepare(
        'SELECT id, type, date, city, city_code, district, target, address, building_type,
                ' . $floorSelect . ' total_price, unit_price_sqm, unit_price_ping, area_sqm, source_file,
                lat, lng, precision
         FROM map_points
         WHERE type = ? AND date >= ?
           AND lat BETWEEN ? AND ?
           AND lng BETWEEN ? AND ?
         ORDER BY date DESC
         LIMIT ' . (int) $itemLimit
    );
    $stmt->execute([$type, $since, $south, $north, $west, $east]);
    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!is_array($row)) {
            continue;
        }
        $items[] = [
            'id' => (string) ($row['id'] ?? ''),
            'kind' => 'realprice-item',
            'city' => (string) ($row['city'] ?? ''),
            'district' => (string) ($row['district'] ?? ''),
            'lat' => (float) ($row['lat'] ?? 0),
            'lng' => (float) ($row['lng'] ?? 0),
            'type' => (string) ($row['type'] ?? $type),
            'date' => (string) ($row['date'] ?? ''),
            'target' => (string) ($row['target'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'building_type' => (string) ($row['building_type'] ?? ''),
            'floor' => (string) ($row['floor'] ?? ''),
            'total_floors' => (string) ($row['total_floors'] ?? ''),
            'total_price' => (int) ($row['total_price'] ?? 0),
            'unit_price_ping' => (int) ($row['unit_price_ping'] ?? 0),
            'area_sqm' => (float) ($row['area_sqm'] ?? 0),
            'area_ping' => round(((float) ($row['area_sqm'] ?? 0)) / 3.305785, 2),
            'precision' => (string) ($row['precision'] ?? 'address'),
        ];
    }

    return [
        'clusters' => [],
        'district_clusters' => [],
        'items' => $items,
        'district_summaries' => [],
        'item_count' => count($items),
        'item_limited' => $filteredCount > count($items),
        'address_item_count' => count($items),
        'district_item_count' => 0,
        'district_cluster_count' => 0,
        'address_hit_count' => count($items),
        'district_hit_count' => 0,
        'missing_geocode_count' => 0,
        'filtered_count' => $filteredCount,
        'coarse_count' => $filteredCount,
        'period' => $period,
        'type' => $type,
        'since' => $since,
        'zoom' => $zoom,
        'cluster_mode' => 'detail',
        'item_zoom_min' => $itemZoomMin,
        'item_full_zoom_min' => $itemFullZoomMin,
        'district_cluster_zoom_min' => $districtClusterZoomMin,
        'cluster_hide_zoom_min' => $districtClusterZoomMin,
        'query_engine' => 'sqlite_map_points',
        'map_points_total' => (int) ($pdo->query('SELECT COUNT(*) FROM map_points')->fetchColumn() ?: 0),
    ];
}

/**
 * @return Generator<int, array<string, mixed>>
 */
/** @param list<string>|null $cities */
function realpriceIterateRecords(?string $type = null, ?string $since = null, ?array $cities = null): Generator
{
    $pdo = realpriceOpenSqliteReadonly();
    if ($pdo instanceof PDO) {
        $hasFloor = realpriceTableHasColumn($pdo, 'records', 'floor');
        $floorSelect = $hasFloor ? 'floor, total_floors,' : "'' AS floor, '' AS total_floors,";
        $sql = 'SELECT id, type, date, city, city_code, district, target, address, building_type,
                       ' . $floorSelect . ' total_price, unit_price_sqm, unit_price_ping, area_sqm, source_file
                FROM records WHERE 1=1';
        $params = [];
        if ($type !== null && $type !== '') {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }
        if ($since !== null && $since !== '') {
            $sql .= ' AND date >= ?';
            $params[] = $since;
        }
        if (is_array($cities) && $cities !== []) {
            $placeholders = implode(',', array_fill(0, count($cities), '?'));
            $sql .= ' AND city IN (' . $placeholders . ')';
            foreach ($cities as $cityName) {
                $params[] = (string) $cityName;
            }
        }
        if ($type !== null && $type !== '' && $since !== null && $since !== '') {
            $sql .= ' ORDER BY date DESC';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            yield [
                'id' => (string) ($row['id'] ?? ''),
                'type' => (string) ($row['type'] ?? ''),
                'date' => (string) ($row['date'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'city_code' => (string) ($row['city_code'] ?? ''),
                'district' => (string) ($row['district'] ?? ''),
                'target' => (string) ($row['target'] ?? ''),
                'address' => (string) ($row['address'] ?? ''),
                'building_type' => (string) ($row['building_type'] ?? ''),
                'floor' => (string) ($row['floor'] ?? ''),
                'total_floors' => (string) ($row['total_floors'] ?? ''),
                'total_price' => (int) ($row['total_price'] ?? 0),
                'unit_price_sqm' => (int) ($row['unit_price_sqm'] ?? 0),
                'unit_price_ping' => (int) ($row['unit_price_ping'] ?? 0),
                'area_sqm' => (float) ($row['area_sqm'] ?? 0),
                'source_file' => (string) ($row['source_file'] ?? ''),
            ];
        }
        return;
    }

    // Fallback：舊版巨型 JSON（可能 OOM，僅相容用）
    @ini_set('memory_limit', '2048M');
    $path = realpriceIndexPath();
    if (!is_file($path)) {
        return;
    }
    $json = json_decode((string) file_get_contents($path), true);
    $records = is_array($json['records'] ?? null) ? $json['records'] : [];
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        if ($type !== null && $type !== '' && (string) ($record['type'] ?? '') !== $type) {
            continue;
        }
        if ($since !== null && $since !== '' && strcmp((string) ($record['date'] ?? ''), $since) < 0) {
            continue;
        }
        yield $record;
    }
}

function realpriceLoadIndex(): array
{
    $sqliteMeta = realpriceLoadSqliteMeta();
    if ($sqliteMeta !== []) {
        return $sqliteMeta;
    }

    // 近多季合併後 index.json 約 200MB+，解碼成 PHP array 需要更高記憶體上限。
    @ini_set('memory_limit', '2048M');
    $path = realpriceIndexPath();
    if (!is_file($path)) {
        return [];
    }
    $json = json_decode((string) file_get_contents($path), true);
    return is_array($json) ? $json : [];
}

function realpriceLoadJsonFile(string $path, array $fallback = []): array
{
    if (!is_file($path)) return $fallback;
    $json = json_decode((string) file_get_contents($path), true);
    return is_array($json) ? $json : $fallback;
}

function realpriceSaveJsonFile(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('實價登錄地理編碼資料寫入失敗。');
    }
}

function realpriceRecordKey(array $record): string
{
    return (string) ($record['type'] ?? '') . '|' . (string) ($record['id'] ?? '');
}

function realpriceLoadOverrides(): array
{
    $overrides = realpriceLoadJsonFile(realpriceOverridePath(), ['records' => [], 'custom_records' => []]);
    return [
        'records' => is_array($overrides['records'] ?? null) ? $overrides['records'] : [],
        'custom_records' => is_array($overrides['custom_records'] ?? null) ? $overrides['custom_records'] : [],
        'updated_at' => (string) ($overrides['updated_at'] ?? ''),
    ];
}

function realpriceSaveOverrides(array $overrides): array
{
    $payload = [
        'updated_at' => date(DATE_ATOM),
        'records' => is_array($overrides['records'] ?? null) ? $overrides['records'] : [],
        'custom_records' => is_array($overrides['custom_records'] ?? null) ? $overrides['custom_records'] : [],
    ];
    realpriceSaveJsonFile(realpriceOverridePath(), $payload);
    return $payload;
}

function realpriceApplyOverridesToRecords(array $records, array $overrides): array
{
    $recordOverrides = is_array($overrides['records'] ?? null) ? $overrides['records'] : [];
    $customRecords = is_array($overrides['custom_records'] ?? null) ? $overrides['custom_records'] : [];
    $next = [];
    foreach ($records as $record) {
        if (!is_array($record)) continue;
        $key = realpriceRecordKey($record);
        $override = is_array($recordOverrides[$key] ?? null) ? $recordOverrides[$key] : [];
        if (!empty($override['hidden'])) continue;
        if ($override !== []) {
            $record['_override'] = $override;
        }
        $next[] = $record;
    }
    foreach ($customRecords as $record) {
        if (!is_array($record) || !empty($record['hidden'])) continue;
        $record['source_file'] = (string) ($record['source_file'] ?? 'local_override');
        $record['_custom'] = true;
        $next[] = $record;
    }
    return $next;
}

function realpriceNormalizeOverrideRecord(array $input): array
{
    $lat = trim((string) ($input['lat'] ?? ''));
    $lng = trim((string) ($input['lng'] ?? ''));
    if (($lng === '' || !is_numeric($lng)) && preg_match('/(-?\d+(?:\.\d+)?)\s*[,，\s]\s*(-?\d+(?:\.\d+)?)/', $lat, $matches)) {
        $lat = $matches[1];
        $lng = $matches[2];
    }
    if (($lat === '' || !is_numeric($lat)) && preg_match('/(-?\d+(?:\.\d+)?)\s*[,，\s]\s*(-?\d+(?:\.\d+)?)/', $lng, $matches)) {
        $lat = $matches[1];
        $lng = $matches[2];
    }
    $latNumber = is_numeric($lat) ? (float) $lat : null;
    $lngNumber = is_numeric($lng) ? (float) $lng : null;
    if (($lat !== '' || $lng !== '') && ($latNumber === null || $lngNumber === null || abs($latNumber) > 90 || abs($lngNumber) > 180)) {
        throw new RuntimeException('手動座標格式不正確。');
    }
    $note = trim((string) ($input['note'] ?? ''));
    return [
        'lat' => $latNumber === null ? '' : (string) $latNumber,
        'lng' => $lngNumber === null ? '' : (string) $lngNumber,
        'note' => function_exists('mb_substr') ? mb_substr($note, 0, 300, 'UTF-8') : substr($note, 0, 300),
        'hidden' => !empty($input['hidden']),
        'updated_at' => date(DATE_ATOM),
    ];
}

function realpriceNormalizeLocalId(string $id): string
{
    $id = strtolower(trim($id));
    $id = preg_replace('/[^a-z0-9_-]+/', '_', $id) ?? '';
    return substr(trim($id, '_-'), 0, 64);
}

function realpriceSaveRecordOverride(string $recordKey, array $input): array
{
    $recordKey = trim($recordKey);
    if ($recordKey === '') {
        throw new RuntimeException('缺少實價登錄記錄 key。');
    }
    $overrides = realpriceLoadOverrides();
    $row = realpriceNormalizeOverrideRecord($input);
    if ($row['lat'] === '' && $row['lng'] === '' && $row['note'] === '' && !$row['hidden']) {
        unset($overrides['records'][$recordKey]);
    } else {
        $overrides['records'][$recordKey] = $row;
    }
    return realpriceSaveOverrides($overrides);
}

function realpriceSaveCustomRecord(array $input): array
{
    $city = trim((string) ($input['city'] ?? ''));
    $district = trim((string) ($input['district'] ?? ''));
    $type = trim((string) ($input['type'] ?? 'sale'));
    if (!in_array($type, ['sale', 'presale', 'rent'], true)) $type = 'sale';
    $date = trim((string) ($input['date'] ?? date('Y-m-d')));
    $address = trim((string) ($input['address'] ?? ''));
    if ($city === '' || $district === '' || $address === '') {
        throw new RuntimeException('新增自訂案件需填縣市、行政區與地址。');
    }
    $geo = realpriceNormalizeOverrideRecord($input);
    if ($geo['lat'] === '' || $geo['lng'] === '') {
        throw new RuntimeException('新增自訂案件需填可用座標。');
    }
    $id = realpriceNormalizeLocalId((string) ($input['id'] ?? ''));
    if ($id === '') {
        $id = 'local-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    }
    $record = [
        'id' => $id,
        'city' => $city,
        'city_code' => 'local',
        'district' => $district,
        'type' => $type,
        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d'),
        'target' => trim((string) ($input['target'] ?? '')),
        'address' => $address,
        'building_type' => trim((string) ($input['building_type'] ?? '')),
        'floor' => trim((string) ($input['floor'] ?? '')),
        'total_floors' => trim((string) ($input['total_floors'] ?? '')),
        'total_price' => max(0, (int) realpriceNumber((string) ($input['total_price'] ?? '0'))),
        'unit_price_sqm' => 0,
        'unit_price_ping' => max(0, (int) realpriceNumber((string) ($input['unit_price_ping'] ?? '0'))),
        'area_sqm' => max(0, (float) realpriceNumber((string) ($input['area_sqm'] ?? '0'))),
        'source_file' => 'local_override',
        '_override' => $geo,
        '_custom' => true,
    ];
    $overrides = realpriceLoadOverrides();
    $custom = is_array($overrides['custom_records'] ?? null) ? $overrides['custom_records'] : [];
    $custom[realpriceRecordKey($record)] = $record;
    $overrides['custom_records'] = $custom;
    realpriceSaveOverrides($overrides);
    return $record;
}

function realpriceNormalizeAddress(string $address): string
{
    $address = strtr($address, [
        '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
        '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
        'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
        'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
        'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
        'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
        'Ｚ' => 'Z', '－' => '-', '—' => '-', '―' => '-', '～' => '-',
        '，' => ',', '、' => ',', '　' => '',
    ]);
    $address = trim(preg_replace('/\s+/u', '', $address) ?? $address);
    return str_replace(['臺'], ['台'], $address);
}

function realpriceDoorAddress(string $address): string
{
    $address = realpriceNormalizeAddress($address);
    if (preg_match('/^(.+?號)/u', $address, $matches)) {
        return $matches[1];
    }
    return $address;
}

function realpriceAddressRoadToken(string $address): string
{
    $address = realpriceNormalizeAddress($address);
    if (preg_match('/([^縣市區鄉鎮村里段]+(?:路|街|大道|巷|弄))/u', $address, $matches)) {
        return (string) $matches[1];
    }
    return '';
}

function realpriceCompactPlaceText(string $value): string
{
    $value = realpriceNormalizeAddress($value);
    $value = preg_replace('/[,，、\s]+/u', '', $value) ?? $value;
    $value = preg_replace('/\d{3,5}/u', '', $value) ?? $value;
    return str_replace(['台灣', '臺灣'], '', $value);
}

function realpriceIsDistrictOnlyText(string $value, string $city = '', string $district = ''): bool
{
    $compact = realpriceCompactPlaceText($value);
    $cityToken = realpriceCompactPlaceText($city);
    $districtToken = realpriceCompactPlaceText($district);
    if ($compact === '' || $districtToken === '') return false;
    $variants = [$districtToken];
    if ($cityToken !== '') {
        $variants[] = $cityToken . $districtToken;
        $variants[] = $districtToken . $cityToken;
    }
    return in_array($compact, array_unique($variants), true);
}

function realpriceGeocodeHitPrecision(array $hit, string $city = '', string $district = ''): string
{
    $precision = (string) ($hit['precision'] ?? '');
    if ($precision === 'manual') return 'manual';
    if ($precision === 'district') return 'district';
    if (realpriceIsDistrictOnlyText((string) ($hit['query'] ?? ''), $city, $district)) {
        return 'district';
    }
    if (realpriceIsDistrictOnlyText((string) ($hit['display_name'] ?? ''), $city, $district)) {
        return 'district';
    }
    return $precision !== '' ? $precision : 'address';
}

function realpriceGeocodeCandidateList(string $query, string $city = '', string $district = ''): array
{
    $normalized = realpriceNormalizeAddress($query);
    $door = realpriceDoorAddress($normalized);
    $districtQuery = ($city !== '' && $district !== '') ? realpriceNormalizeAddress($city . $district) : '';
    $raw = [];
    if (!realpriceIsDistrictOnlyText($door, $city, $district)) {
        $raw[] = ['query' => $door, 'precision' => 'address'];
        $raw[] = ['query' => $door !== $normalized ? $normalized : '', 'precision' => 'address'];
        $raw[] = ['query' => $door !== '' ? trim($door . ' 台灣') : '', 'precision' => 'address'];
    }
    $raw[] = ['query' => $districtQuery, 'precision' => 'district'];
    $raw[] = ['query' => $districtQuery !== '' ? trim($districtQuery . ' 台灣') : '', 'precision' => 'district'];
    $seen = [];
    $items = [];
    foreach ($raw as $item) {
        $candidate = trim((string) ($item['query'] ?? ''));
        if ($candidate === '' || isset($seen[$candidate])) continue;
        $seen[$candidate] = true;
        $items[] = ['query' => $candidate, 'precision' => (string) $item['precision']];
    }
    return $items;
}

function realpriceGeocodeResultAcceptable(array $result, string $candidate, string $precision, string $city, string $district, string $sourceAddress): bool
{
    if ($precision === 'district') return true;
    if (realpriceIsDistrictOnlyText($candidate, $city, $district)) return false;
    $display = realpriceNormalizeAddress((string) ($result['display_name'] ?? ''));
    $cityToken = realpriceNormalizeAddress($city);
    $districtToken = realpriceNormalizeAddress($district);
    if ($cityToken !== '' && !str_contains($display, $cityToken)) return false;
    if ($districtToken !== '' && !str_contains($display, $districtToken)) return false;

    $road = realpriceAddressRoadToken($candidate) ?: realpriceAddressRoadToken($sourceAddress);
    if ($road !== '' && !str_contains($display, $road)) return false;

    $category = strtolower((string) ($result['category'] ?? ''));
    $type = strtolower((string) ($result['type'] ?? ''));
    $addresstype = strtolower((string) ($result['addresstype'] ?? ''));
    if (in_array($category, ['boundary'], true) || in_array($addresstype, ['city', 'town', 'village', 'municipality', 'county'], true)) {
        return false;
    }
    if (in_array($type, ['administrative', 'townhall'], true)) {
        return false;
    }
    return true;
}

function realpriceGeocodeHitIsDistrictFallback(array $hit, string $city = '', string $district = ''): bool
{
    $precision = realpriceGeocodeHitPrecision($hit, $city, $district);
    if ($precision === 'district') return true;
    $query = (string) ($hit['query'] ?? '');
    $display = (string) ($hit['display_name'] ?? '');
    $address = (string) ($hit['address'] ?? '');
    $queryHasStreet = realpriceAddressRoadToken($query) !== '' || str_contains(realpriceNormalizeAddress($query), '號');
    $addressHasStreet = realpriceAddressRoadToken($address) !== '' || str_contains(realpriceNormalizeAddress($address), '號');
    if ($addressHasStreet && !$queryHasStreet && ($query !== '' || $display !== '')) {
        return true;
    }
    return false;
}

function realpriceRepairPoisonedGeocodeCache(bool $rebuildQueue = true, int $queueLimit = 3000): array
{
    $cache = realpriceLoadGeocodeCache();
    $cached = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $next = [];
    $removed = 0;
    $districtEnsured = 0;
    $relabeled = 0;
    foreach ($cached as $key => $hit) {
        if (!is_array($hit)) {
            $removed++;
            continue;
        }
        $city = (string) ($hit['city'] ?? '');
        $district = (string) ($hit['district'] ?? '');
        $address = (string) ($hit['address'] ?? '');
        $precision = (string) ($hit['precision'] ?? '');
        if ($precision === 'manual') {
            $next[$key] = $hit;
            continue;
        }
        $addressHasStreet = realpriceAddressRoadToken($address) !== ''
            || str_contains(realpriceNormalizeAddress($address), '號');
        $poisoned = $addressHasStreet && realpriceGeocodeHitIsDistrictFallback($hit, $city, $district);
        if ($poisoned) {
            if ($city !== '' && $district !== '') {
                $districtKey = realpriceGeocodeScopedKey('district', $city, $district);
                if (!isset($next[$districtKey])) {
                    if (isset($cached[$districtKey]) && is_array($cached[$districtKey])) {
                        $next[$districtKey] = $cached[$districtKey];
                    } else {
                        $next[$districtKey] = [
                            'address' => realpriceNormalizeAddress($city . $district),
                            'city' => $city,
                            'district' => $district,
                            'lat' => (float) ($hit['lat'] ?? 0),
                            'lng' => (float) ($hit['lng'] ?? 0),
                            'display_name' => (string) ($hit['display_name'] ?? ''),
                            'provider' => (string) ($hit['provider'] ?? 'nominatim'),
                            'precision' => 'district',
                            'query' => (string) ($hit['query'] ?? realpriceNormalizeAddress($city . $district)),
                            'category' => (string) ($hit['category'] ?? ''),
                            'type' => (string) ($hit['type'] ?? ''),
                            'addresstype' => (string) ($hit['addresstype'] ?? ''),
                            'updated_at' => date(DATE_ATOM),
                        ];
                        $districtEnsured++;
                    }
                }
            }
            $removed++;
            continue;
        }
        if ($precision !== 'district' && realpriceGeocodeHitIsDistrictFallback($hit, $city, $district)) {
            $hit['precision'] = 'district';
            $relabeled++;
        }
        $next[$key] = $hit;
    }
    $cache['items'] = $next;
    $cache['updated_at'] = date(DATE_ATOM);
    $cache['repaired_at'] = date(DATE_ATOM);
    $cache['repair_removed'] = $removed;
    realpriceSaveJsonFile(realpriceGeocodeCachePath(), $cache);

    $queueStats = [
        'queue_count' => 0,
        'pending_count' => 0,
    ];
    if ($rebuildQueue) {
        $queueStats = realpriceBuildGeocodeQueue($queueLimit);
    }
    $state = realpriceLoadGeocodeState();
    $state['last_message'] = "已清理誤標行政區座標 {$removed} 筆，補齊行政區快取 {$districtEnsured} 筆，重標 {$relabeled} 筆。";
    $state['last_run_at'] = date(DATE_ATOM);
    realpriceSaveJsonFile(realpriceGeocodeStatePath(), $state);

    return array_merge(realpriceGeocodeStats(), [
        'repair_removed' => $removed,
        'repair_district_ensured' => $districtEnsured,
        'repair_relabeled' => $relabeled,
        'repair_queue_pending' => (int) ($queueStats['pending_count'] ?? 0),
    ]);
}

function realpriceGeocodeKey(string $address): string
{
    return sha1(realpriceDoorAddress($address));
}

function realpriceLoadGeocodeCache(): array
{
    $cache = realpriceLoadJsonFile(realpriceGeocodeCachePath(), ['items' => []]);
    $items = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $overlay = realpriceLoadGeocodeTgosOverlay();
    $overlayItems = is_array($overlay['items'] ?? null) ? $overlay['items'] : [];
    if ($overlayItems !== []) {
        foreach ($overlayItems as $key => $hit) {
            if (!is_string($key) || $key === '' || !is_array($hit)) {
                continue;
            }
            $existing = is_array($items[$key] ?? null) ? $items[$key] : null;
            if ($existing
                && (($existing['provider'] ?? '') === 'doorplate_opendata' || ($existing['provider'] ?? '') === 'local_override')
                && (($existing['precision'] ?? '') !== 'district')
            ) {
                continue;
            }
            $items[$key] = $hit;
        }
        $cache['items'] = $items;
        if (!empty($overlay['updated_at'])) {
            $cache['overlay_updated_at'] = $overlay['updated_at'];
        }
    }
    return $cache;
}

function realpriceLoadGeocodeQueue(): array
{
    return realpriceLoadJsonFile(realpriceGeocodeQueuePath(), ['generated_at' => '', 'items' => []]);
}

function realpriceLoadGeocodeState(): array
{
    return realpriceLoadJsonFile(realpriceGeocodeStatePath(), []);
}

function realpriceGeocodeStats(): array
{
    $cache = realpriceLoadGeocodeCache();
    $queue = realpriceLoadGeocodeQueue();
    $state = realpriceLoadGeocodeState();
    $items = is_array($queue['items'] ?? null) ? $queue['items'] : [];
    $counts = ['pending' => 0, 'done' => 0, 'not_found' => 0, 'error' => 0, 'skipped' => 0];
    foreach ($items as $item) {
        $status = (string) ($item['status'] ?? 'pending');
        if (!array_key_exists($status, $counts)) $status = 'pending';
        $counts[$status]++;
    }
    $rateLimitedUntil = (string) ($state['rate_limited_until'] ?? '');
    return [
        'cache_count' => count((array) ($cache['items'] ?? [])),
        'queue_count' => count($items),
        'pending_count' => $counts['pending'],
        'done_count' => $counts['done'],
        'not_found_count' => $counts['not_found'],
        'error_count' => $counts['error'],
        'skipped_count' => $counts['skipped'],
        'queue_generated_at' => (string) ($queue['generated_at'] ?? ''),
        'last_run_at' => (string) ($state['last_run_at'] ?? ''),
        'last_message' => (string) ($state['last_message'] ?? ''),
        'rate_limited_until' => $rateLimitedUntil,
        'is_rate_limited' => $rateLimitedUntil !== '' && strtotime($rateLimitedUntil) !== false && strtotime($rateLimitedUntil) > time(),
        'provider' => (string) ($state['provider'] ?? 'nominatim'),
    ];
}

function realpriceCoverageStatsPath(): string
{
    return realpriceDir() . '/coverage-stats.json';
}

function realpriceOpLogPath(): string
{
    return realpriceDir() . '/op-log.jsonl';
}

/**
 * Append one operation log line (實價登錄／門牌比對等).
 *
 * @param array<string, mixed> $context
 */
function realpriceOpLog(string $action, string $message, array $context = [], string $level = 'info'): void
{
    $dir = realpriceDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $entry = [
        'at' => date(DATE_ATOM),
        'action' => $action,
        'level' => $level,
        'message' => $message,
    ];
    if ($context !== []) {
        $entry['context'] = $context;
    }
    $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return;
    }
    @file_put_contents(realpriceOpLogPath(), $line . "\n", FILE_APPEND | LOCK_EX);

    // Keep file small: trim when oversized.
    $path = realpriceOpLogPath();
    if (is_file($path) && filesize($path) > 512000) {
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (is_array($lines) && count($lines) > 200) {
            $keep = array_slice($lines, -200);
            @file_put_contents($path, implode("\n", $keep) . "\n", LOCK_EX);
        }
    }
}

/**
 * @return list<array<string, mixed>>
 */
function realpriceOpLogRead(int $limit = 80): array
{
    $limit = max(1, min(300, $limit));
    $path = realpriceOpLogPath();
    if (!is_file($path)) {
        return [];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines) || $lines === []) {
        return [];
    }
    $lines = array_slice($lines, -$limit);
    $rows = [];
    foreach (array_reverse($lines) as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        $rows[] = [
            'at' => (string) ($decoded['at'] ?? ''),
            'action' => (string) ($decoded['action'] ?? ''),
            'level' => (string) ($decoded['level'] ?? 'info'),
            'message' => (string) ($decoded['message'] ?? ''),
            'context' => is_array($decoded['context'] ?? null) ? $decoded['context'] : [],
        ];
    }
    return $rows;
}

function realpriceOpLogClear(): void
{
    $path = realpriceOpLogPath();
    if (is_file($path)) {
        @file_put_contents($path, '', LOCK_EX);
    }
    realpriceOpLog('clear_log', '已清空操作日誌。', [], 'info');
}

function realpriceCoverageJobPath(): string
{
    return realpriceDir() . '/coverage-job.json';
}

function realpriceCoverageJobDbPath(): string
{
    return realpriceDir() . '/coverage-job.sqlite';
}

function realpriceCoverageJobSignature(): string
{
    $indexPath = is_file(realpriceSqlitePath()) ? realpriceSqlitePath() : realpriceIndexPath();
    $cachePath = realpriceGeocodeCachePath();
    $overlayPath = realpriceGeocodeTgosOverlayPath();
    return implode('|', [
        is_file($indexPath) ? ((string) filesize($indexPath) . '|' . (string) filemtime($indexPath)) : '0',
        is_file($cachePath) ? ((string) filesize($cachePath) . '|' . (string) filemtime($cachePath)) : '0',
        is_file($overlayPath) ? ((string) filesize($overlayPath) . '|' . (string) filemtime($overlayPath)) : '0',
    ]);
}

function realpriceLoadCoverageJob(): array
{
    return realpriceLoadJsonFile(realpriceCoverageJobPath(), [
        'status' => 'idle',
        'signature' => '',
        'last_rowid' => 0,
        'scanned_rows' => 0,
        'unique' => 0,
        'precise' => 0,
        'district_fallback' => 0,
        'missing' => 0,
        'by_provider' => [],
        'by_city' => [],
        'message' => '',
        'pct' => 0,
        'total_rows' => 0,
        'started_at' => '',
        'updated_at' => '',
    ]);
}

function realpriceSaveCoverageJob(array $job): void
{
    $job['updated_at'] = date(DATE_ATOM);
    realpriceSaveJsonFile(realpriceCoverageJobPath(), $job);
}

function realpriceCoverageJobOpenDb(bool $create = false): ?PDO
{
    $path = realpriceCoverageJobDbPath();
    if (!$create && !is_file($path)) {
        return null;
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($create) {
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA synchronous=NORMAL');
        $pdo->exec('CREATE TABLE IF NOT EXISTS seen (k TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS geo (
            k TEXT PRIMARY KEY,
            provider TEXT,
            precision TEXT,
            city TEXT,
            district TEXT,
            query TEXT,
            display_name TEXT,
            address TEXT
        )');
    }
    return $pdo;
}

function realpriceCoverageJobStart(): array
{
    @set_time_limit(60);
    if (!is_file(realpriceSqlitePath()) && !is_file(realpriceIndexPath())) {
        throw new RuntimeException('尚未建立實價登錄索引。');
    }
    if (!is_file(realpriceSqlitePath()) || !class_exists('PDO')) {
        throw new RuntimeException('覆蓋統計需要 SQLite 索引。請先重建實價登錄索引。');
    }

    @unlink(realpriceCoverageJobDbPath());
    $jobDb = realpriceCoverageJobOpenDb(true);
    if (!$jobDb instanceof PDO) {
        throw new RuntimeException('無法建立覆蓋統計暫存資料庫。');
    }

    $totalRows = 0;
    $src = realpriceOpenSqliteReadonly();
    if ($src instanceof PDO) {
        try {
            // MAX(rowid) is enough for progress and avoids slow COUNT(*) on large tables.
            $totalRows = (int) $src->query('SELECT MAX(rowid) FROM records')->fetchColumn();
        } catch (Throwable $e) {
            $totalRows = 0;
        }
    }

    $job = [
        'status' => 'running',
        'phase' => 'warm_geo',
        'signature' => realpriceCoverageJobSignature(),
        'geo_last_key' => '',
        'geo_indexed' => 0,
        'geo_total' => 0,
        'last_rowid' => 0,
        'scanned_rows' => 0,
        'unique' => 0,
        'precise' => 0,
        'district_fallback' => 0,
        'missing' => 0,
        'by_provider' => [],
        'by_city' => [],
        'message' => '開始分批統計覆蓋：先索引座標快取…',
        'pct' => 0,
        'total_rows' => $totalRows,
        'started_at' => date(DATE_ATOM),
    ];
    realpriceSaveCoverageJob($job);
    return $job;
}

function realpriceCoverageJobWarmGeo(PDO $jobDb, array &$job, float $deadline): array
{
    $cache = realpriceLoadGeocodeCache();
    $cachedItems = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $geoTotal = count($cachedItems);
    $job['geo_total'] = $geoTotal;
    if ($geoTotal === 0) {
        $job['phase'] = 'scan';
        $job['message'] = '座標快取為空，開始掃描索引…';
        $job['pct'] = 5;
        return ['warmed' => 0, 'phase_done' => true];
    }

    $lastKey = (string) ($job['geo_last_key'] ?? '');
    $skipping = $lastKey !== '';
    $ins = $jobDb->prepare(
        'INSERT OR REPLACE INTO geo(k, provider, precision, city, district, query, display_name, address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $jobDb->beginTransaction();
    $warmed = 0;
    $indexed = (int) ($job['geo_indexed'] ?? 0);
    $phaseDone = true;
    foreach ($cachedItems as $key => $hit) {
        $key = (string) $key;
        if ($skipping) {
            if ($key === $lastKey) {
                $skipping = false;
            }
            continue;
        }
        if (!is_array($hit)) {
            continue;
        }
        $ins->execute([
            $key,
            (string) ($hit['provider'] ?? ''),
            (string) ($hit['precision'] ?? ''),
            (string) ($hit['city'] ?? ''),
            (string) ($hit['district'] ?? ''),
            (string) ($hit['query'] ?? ''),
            (string) ($hit['display_name'] ?? ''),
            (string) ($hit['address'] ?? ''),
        ]);
        $lastKey = $key;
        $warmed++;
        $indexed++;
        if (($warmed % 2000) === 0 && microtime(true) >= $deadline) {
            $phaseDone = false;
            break;
        }
    }
    $jobDb->commit();

    $job['geo_last_key'] = $lastKey;
    $job['geo_indexed'] = $indexed;
    if ($phaseDone) {
        $job['phase'] = 'scan';
        $job['geo_last_key'] = '';
        $job['message'] = '座標快取已索引（' . number_format($indexed) . '），開始掃描實價列…';
        $job['pct'] = 8;
    } else {
        $job['message'] = '索引座標快取 ' . number_format($indexed) . ' / ' . number_format($geoTotal) . '…';
        $job['pct'] = $geoTotal > 0 ? min(7.9, round(($indexed / $geoTotal) * 8, 1)) : 1;
    }
    return ['warmed' => $warmed, 'phase_done' => $phaseDone];
}

function realpriceCoverageJobChunk(int $timeBudgetMs = 12000): array
{
    @set_time_limit(75);
    @ini_set('memory_limit', '768M');
    $job = realpriceLoadCoverageJob();
    if (($job['status'] ?? '') !== 'running') {
        return [
            'job' => $job,
            'done' => true,
            'message' => (string) ($job['message'] ?? '目前沒有進行中的覆蓋統計。'),
            'coverage' => realpriceCoverageStats(false),
        ];
    }

    $jobDb = realpriceCoverageJobOpenDb(false);
    $src = realpriceOpenSqliteReadonly();
    if (!$jobDb instanceof PDO || !$src instanceof PDO) {
        throw new RuntimeException('覆蓋統計暫存或索引資料庫無法開啟。');
    }

    $deadline = microtime(true) + max(4, min(18, $timeBudgetMs / 1000));
    $phase = (string) ($job['phase'] ?? 'warm_geo');

    if ($phase !== 'scan') {
        $warm = realpriceCoverageJobWarmGeo($jobDb, $job, $deadline);
        realpriceSaveCoverageJob($job);
        $phase = (string) ($job['phase'] ?? 'warm_geo');
        if ($phase !== 'scan' || microtime(true) >= ($deadline - 1.5)) {
            return [
                'job' => $job,
                'done' => false,
                'chunk' => [
                    'phase' => 'warm_geo',
                    'warmed' => (int) ($warm['warmed'] ?? 0),
                    'pct' => (float) ($job['pct'] ?? 0),
                ],
                'message' => (string) $job['message'],
                'coverage' => null,
            ];
        }
        // warm finished with time left — continue into scan in this request
    }

    $precise = (int) ($job['precise'] ?? 0);
    $districtFallback = (int) ($job['district_fallback'] ?? 0);
    $missing = (int) ($job['missing'] ?? 0);
    $unique = (int) ($job['unique'] ?? 0);
    $byProvider = is_array($job['by_provider'] ?? null) ? $job['by_provider'] : [];
    $byCity = is_array($job['by_city'] ?? null) ? $job['by_city'] : [];
    $lastRowId = (int) ($job['last_rowid'] ?? 0);
    $scanned = (int) ($job['scanned_rows'] ?? 0);
    $totalRows = (int) ($job['total_rows'] ?? 0);

    $insertSeen = $jobDb->prepare('INSERT OR IGNORE INTO seen(k) VALUES (?)');
    $lookup = $jobDb->prepare(
        'SELECT provider, precision, city, district, query, display_name, address FROM geo WHERE k = ?'
    );
    $stmt = $src->prepare(
        'SELECT rowid AS rid, city, district, address
         FROM records
         WHERE rowid > ?
         ORDER BY rowid
         LIMIT 1200'
    );

    $batchScanned = 0;
    $batchUnique = 0;
    $done = false;

    while (microtime(true) < $deadline) {
        $stmt->execute([$lastRowId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            $done = true;
            break;
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $lastRowId = (int) ($row['rid'] ?? $lastRowId);
            $batchScanned++;
            $scanned++;
            $address = realpriceNormalizeAddress((string) ($row['address'] ?? ''));
            if ($address === '' || mb_strlen($address, 'UTF-8') < 5) {
                continue;
            }
            $key = realpriceGeocodeKey($address);
            $insertSeen->execute([$key]);
            if ($insertSeen->rowCount() === 0) {
                continue;
            }
            $batchUnique++;
            $unique++;

            $city = realpriceNormalizeAddress((string) ($row['city'] ?? '')) ?: '未分縣市';
            $district = realpriceNormalizeAddress((string) ($row['district'] ?? ''));
            if (!isset($byCity[$city]) || !is_array($byCity[$city])) {
                $byCity[$city] = [
                    'city' => $city,
                    'total' => 0,
                    'precise' => 0,
                    'need_match' => 0,
                    'doorplate' => 0,
                    'tgos' => 0,
                    'nominatim' => 0,
                    'manual' => 0,
                ];
            }
            $byCity[$city]['total']++;

            $lookup->execute([$key]);
            $existing = $lookup->fetch(PDO::FETCH_ASSOC);
            $existing = is_array($existing) ? $existing : null;
            $isPrecise = $existing
                && !realpriceGeocodeHitIsDistrictFallback($existing, $city, $district)
                && (($existing['precision'] ?? '') !== 'district');

            if ($isPrecise) {
                $precise++;
                $byCity[$city]['precise']++;
                $provider = (string) ($existing['provider'] ?? 'unknown');
                $byProvider[$provider] = (int) ($byProvider[$provider] ?? 0) + 1;
                if ($provider === 'doorplate_opendata') {
                    $byCity[$city]['doorplate']++;
                } elseif ($provider === 'tgos') {
                    $byCity[$city]['tgos']++;
                } elseif ($provider === 'local_override') {
                    $byCity[$city]['manual']++;
                } else {
                    $byCity[$city]['nominatim']++;
                }
            } else {
                $byCity[$city]['need_match']++;
                if ($existing && ((($existing['precision'] ?? '') === 'district') || realpriceGeocodeHitIsDistrictFallback($existing, $city, $district))) {
                    $districtFallback++;
                } else {
                    $missing++;
                }
            }
        }
    }

    $job['last_rowid'] = $lastRowId;
    $job['scanned_rows'] = $scanned;
    $job['unique'] = $unique;
    $job['precise'] = $precise;
    $job['district_fallback'] = $districtFallback;
    $job['missing'] = $missing;
    $job['by_provider'] = $byProvider;
    $job['by_city'] = $byCity;
    if ($totalRows > 0) {
        // warm_geo uses 0–8%; scan uses 8–99.9% (progress by rowid)
        $scanPct = min(91.9, ($lastRowId / max(1, $totalRows)) * 91.9);
        $job['pct'] = round(8 + $scanPct, 1);
    } else {
        $job['pct'] = $done ? 100 : 8;
    }

    $coverage = null;
    if ($done) {
        uasort($byCity, static fn (array $a, array $b): int => ($b['need_match'] <=> $a['need_match']) ?: ($b['total'] <=> $a['total']));
        $need = $unique - $precise;
        $stats = [
            'signature' => (string) ($job['signature'] ?? realpriceCoverageJobSignature()),
            'generated_at' => date(DATE_ATOM),
            'unique_addresses' => $unique,
            'precise_count' => $precise,
            'need_match_count' => $need,
            'district_fallback_count' => $districtFallback,
            'missing_count' => $missing,
            'precise_pct' => $unique > 0 ? round(($precise / $unique) * 100, 1) : 0,
            'by_provider' => $byProvider,
            'by_city' => array_values($byCity),
            'message' => $unique > 0
                ? ('精準門牌座標 ' . number_format($precise) . ' / ' . number_format($unique) . '（' . round(($precise / $unique) * 100, 1) . '%）；尚待比對 ' . number_format($need) . ' 筆。')
                : '索引中沒有可統計的地址。',
        ];
        realpriceSaveJsonFile(realpriceCoverageStatsPath(), $stats);
        $job['status'] = 'done';
        $job['phase'] = 'done';
        $job['pct'] = 100;
        $job['message'] = (string) $stats['message'];
        $coverage = $stats;
        $jobDb = null;
        @unlink(realpriceCoverageJobDbPath());
    } else {
        $job['message'] = '統計中 rowid ' . number_format($lastRowId)
            . ($totalRows > 0 ? ' / ' . number_format($totalRows) : '')
            . '（唯一地址 ' . number_format($unique) . '，精準 ' . number_format($precise) . '）…';
    }
    realpriceSaveCoverageJob($job);

    return [
        'job' => $job,
        'done' => $done,
        'chunk' => [
            'phase' => 'scan',
            'scanned' => $batchScanned,
            'unique' => $batchUnique,
            'pct' => (float) ($job['pct'] ?? 0),
        ],
        'message' => (string) $job['message'],
        'coverage' => $coverage,
    ];
}

function realpriceCoverageJobCancel(): array
{
    $job = realpriceLoadCoverageJob();
    $job['status'] = 'cancelled';
    $job['phase'] = 'cancelled';
    $job['message'] = '已取消覆蓋統計。';
    realpriceSaveCoverageJob($job);
    @unlink(realpriceCoverageJobDbPath());
    return $job;
}

/**
 * Summarize unique indexed addresses vs precise doorplate/manual/nominatim hits.
 * Cached to coverage-stats.json. Page load never full-scans.
 * Full rebuild is via realpriceCoverageJobStart/Chunk (AJAX) to avoid Synology 504.
 */
function realpriceCoverageStats(bool $force = false): array
{
    $indexPath = is_file(realpriceSqlitePath()) ? realpriceSqlitePath() : realpriceIndexPath();
    if (!is_file($indexPath)) {
        return [
            'unique_addresses' => 0,
            'precise_count' => 0,
            'need_match_count' => 0,
            'district_fallback_count' => 0,
            'missing_count' => 0,
            'precise_pct' => 0,
            'by_provider' => [],
            'by_city' => [],
            'generated_at' => '',
            'message' => '尚未建立實價登錄索引。',
        ];
    }

    $signature = realpriceCoverageJobSignature();
    $path = realpriceCoverageStatsPath();
    if (is_file($path)) {
        $cached = realpriceLoadJsonFile($path, []);
        if (is_array($cached['by_city'] ?? null)) {
            if (($cached['signature'] ?? '') === $signature && !$force) {
                return $cached;
            }
            // 索引／座標快取已變，但進頁不重算（避免整庫掃描 timeout）。
            $cached['stale'] = true;
            $cached['message'] = (string) (($cached['message'] ?? '') !== ''
                ? $cached['message']
                : '定位覆蓋統計（可能過期）')
                . ' 資料已更新，請按「重新統計覆蓋」重算。';
            return $cached;
        }
    }

    return realpriceCoverageStatsQuick($signature);
}

/** 輕量統計：用 map_points / meta，不掃全表。 */
function realpriceCoverageStatsQuick(string $signature = ''): array
{
    $meta = realpriceLoadMeta();
    $pdo = realpriceOpenSqliteReadonly();
    $mapPoints = 0;
    $byCity = [];
    if ($pdo instanceof PDO) {
        try {
            $hasMap = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='map_points'")->fetchColumn();
            if ($hasMap) {
                $mapPoints = (int) $pdo->query('SELECT COUNT(*) FROM map_points')->fetchColumn();
                $rows = $pdo->query(
                    'SELECT city, COUNT(*) AS cnt, COUNT(DISTINCT geocode_key) AS uniq
                     FROM map_points GROUP BY city ORDER BY cnt DESC'
                )->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $city = realpriceNormalizeAddress((string) ($row['city'] ?? '')) ?: '未分縣市';
                    $byCity[] = [
                        'city' => $city,
                        'total' => (int) ($row['cnt'] ?? 0),
                        'precise' => (int) ($row['uniq'] ?? 0),
                        'need_match' => 0,
                        'doorplate' => (int) ($row['uniq'] ?? 0),
                        'tgos' => 0,
                        'nominatim' => 0,
                        'manual' => 0,
                    ];
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    $indexed = (int) ($meta['indexed_count'] ?? 0);
    $cache = realpriceLoadGeocodeCache();
    $cachedItems = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $cacheCount = count($cachedItems);
    $districtFallback = 0;
    foreach ($cachedItems as $hit) {
        if (!is_array($hit)) {
            continue;
        }
        if ((string) ($hit['precision'] ?? '') === 'district') {
            $districtFallback++;
        }
    }
    $precise = $mapPoints > 0 ? $mapPoints : max(0, $cacheCount - $districtFallback);
    $need = max(0, $indexed - $precise);

    return [
        'signature' => $signature,
        'unique_addresses' => $indexed,
        'precise_count' => $precise,
        'need_match_count' => $need,
        'district_fallback_count' => $districtFallback,
        'missing_count' => $need,
        'precise_pct' => $indexed > 0 ? round(($precise / $indexed) * 100, 1) : 0,
        'by_provider' => $mapPoints > 0 ? ['doorplate_opendata' => $mapPoints] : [],
        'by_city' => $byCity,
        'generated_at' => date(DATE_ATOM),
        'quick' => true,
        'message' => '快速統計：已定位門牌點約 ' . number_format($precise)
            . '／索引 ' . number_format($indexed)
            . ' 筆。完整覆蓋表請按「更新覆蓋統計」。',
    ];
}

function realpriceBuildGeocodeQueue(int $limit = 1000): array
{
    $limit = max(50, min(10000, $limit));
    $overrides = realpriceLoadOverrides();
    $recordOverrides = is_array($overrides['records'] ?? null) ? $overrides['records'] : [];
    $cache = realpriceLoadGeocodeCache();
    $cached = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $seen = [];
    $items = [];
    foreach (realpriceIterateRecords() as $record) {
        if (!is_array($record)) {
            continue;
        }
        $recordKey = realpriceRecordKey($record);
        $override = is_array($recordOverrides[$recordKey] ?? null) ? $recordOverrides[$recordKey] : [];
        if (!empty($override['hidden'])) {
            continue;
        }
        $address = realpriceNormalizeAddress((string) ($record['address'] ?? ''));
        if ($address === '' || mb_strlen($address, 'UTF-8') < 5) continue;
        $key = realpriceGeocodeKey($address);
        if (isset($seen[$key])) continue;
        $existing = is_array($cached[$key] ?? null) ? $cached[$key] : null;
        // Only skip when we already have a true door/manual coordinate for this address.
        if ($existing && !realpriceGeocodeHitIsDistrictFallback($existing, (string) ($record['city'] ?? ''), (string) ($record['district'] ?? '')) && (($existing['precision'] ?? '') !== 'district')) {
            continue;
        }
        $seen[$key] = true;
        $items[] = [
            'key' => $key,
            'address' => $address,
            'city' => (string) ($record['city'] ?? ''),
            'district' => (string) ($record['district'] ?? ''),
            'status' => 'pending',
            'attempts' => 0,
            'message' => '',
        ];
        if (count($items) >= $limit) break;
    }
    realpriceSaveJsonFile(realpriceGeocodeQueuePath(), [
        'generated_at' => date(DATE_ATOM),
        'items' => $items,
    ]);
    $state = realpriceLoadGeocodeState();
    $state['last_message'] = '已建立地理編碼佇列：' . count($items) . ' 筆。';
    $state['provider'] = 'nominatim';
    realpriceSaveJsonFile(realpriceGeocodeStatePath(), $state);
    return realpriceGeocodeStats();
}

function realpriceGeocodeScopedKey(string $scope, string $city, string $district): string
{
    return sha1($scope . '|' . realpriceNormalizeAddress($city) . '|' . realpriceNormalizeAddress($district));
}

function realpriceGeocodeLookup(string $query, string $city = '', string $district = ''): array
{
    // Prefer TGOS QueryAddr when APPId/APIKey are configured (全國門牌定位；與批次上傳金鑰不同)。
    if (!function_exists('tgosHasQueryAddrCredentials')) {
        $tgosLib = __DIR__ . '/tgos.php';
        if (is_file($tgosLib)) {
            require_once $tgosLib;
        }
    }
    if (function_exists('tgosHasQueryAddrCredentials') && tgosHasQueryAddrCredentials()) {
        try {
            $tgos = tgosQueryAddr($query);
            if (!empty($tgos['found'])) {
                return [
                    'found' => true,
                    'lat' => (float) ($tgos['lat'] ?? 0),
                    'lng' => (float) ($tgos['lng'] ?? 0),
                    'display_name' => (string) ($tgos['display_name'] ?? ''),
                    'precision' => 'address',
                    'query' => (string) ($tgos['query'] ?? $query),
                    'provider' => 'tgos',
                    'category' => '',
                    'type' => '',
                    'addresstype' => '',
                ];
            }
        } catch (Throwable $e) {
            // Fall through to Nominatim; do not abort the whole batch on one TGOS failure.
            if (str_starts_with($e->getMessage(), 'RATE_LIMIT:')) {
                throw $e;
            }
        }
    }

    $queries = realpriceGeocodeCandidateList($query, $city, $district);

    $headers = [
        'User-Agent: OpenLiveMap/1.0 (https://info.green.myds.me/)',
        'Accept: application/json',
    ];

    foreach ($queries as $index => $item) {
        $candidate = (string) ($item['query'] ?? '');
        $precision = (string) ($item['precision'] ?? 'address');
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'format' => 'jsonv2',
            'limit' => '1',
            'countrycodes' => 'tw',
            'q' => $candidate,
        ]);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string) $line, $matches)) {
                $status = (int) $matches[1];
                break;
            }
        }
        if ($status === 429) {
            throw new RuntimeException('RATE_LIMIT: 地理編碼服務回應 429，已觸發限流。');
        }
        if ($body === false || $status >= 500) {
            throw new RuntimeException('地理編碼服務暫時無法使用。');
        }
        $json = json_decode((string) $body, true);
        if (is_array($json) && isset($json[0]) && is_array($json[0])) {
            $first = $json[0];
            if (!realpriceGeocodeResultAcceptable($first, $candidate, $precision, $city, $district, $query)) {
                if ($index < count($queries) - 1) {
                    usleep(350000);
                }
                continue;
            }
            return [
                'found' => true,
                'lat' => (float) ($first['lat'] ?? 0),
                'lng' => (float) ($first['lon'] ?? 0),
                'display_name' => (string) ($first['display_name'] ?? ''),
                'precision' => $precision,
                'query' => $candidate,
                'provider' => 'nominatim',
                'category' => (string) ($first['category'] ?? ''),
                'type' => (string) ($first['type'] ?? ''),
                'addresstype' => (string) ($first['addresstype'] ?? ''),
            ];
        }
        if ($index < count($queries) - 1) {
            usleep(350000);
        }
    }

    return ['found' => false, 'lat' => 0.0, 'lng' => 0.0, 'display_name' => '', 'precision' => '', 'query' => ''];
}

function realpriceProcessGeocodeBatch(int $batchSize = 5): array
{
    $batchSize = max(1, min(200, $batchSize));
    @set_time_limit(0);
    $state = realpriceLoadGeocodeState();
    $rateLimitedUntil = (string) ($state['rate_limited_until'] ?? '');
    if ($rateLimitedUntil !== '' && strtotime($rateLimitedUntil) !== false && strtotime($rateLimitedUntil) > time()) {
        throw new RuntimeException('地理編碼目前冷卻中，請等到 ' . $rateLimitedUntil . ' 後再試。');
    }
    $queue = realpriceLoadGeocodeQueue();
    $items = is_array($queue['items'] ?? null) ? $queue['items'] : [];
    $cache = realpriceLoadGeocodeCache();
    $cached = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $processed = 0;
    $found = 0;
    $notFound = 0;
    $errors = 0;
    $externalLookups = 0;
    $maxExternalLookups = 3;
    foreach ($items as $index => $item) {
        if ($processed >= $batchSize) break;
        if (($item['status'] ?? 'pending') !== 'pending') continue;
        $key = (string) ($item['key'] ?? '');
        $address = (string) ($item['address'] ?? '');
        if ($key === '' || $address === '') {
            $items[$index]['status'] = 'skipped';
            continue;
        }
        $canonicalKey = realpriceGeocodeKey($address);
        if ($canonicalKey !== '' && $canonicalKey !== $key) {
            $key = $canonicalKey;
            $items[$index]['key'] = $canonicalKey;
        }
        $city = (string) ($item['city'] ?? '');
        $district = (string) ($item['district'] ?? '');
        $districtKey = ($city !== '' && $district !== '') ? realpriceGeocodeScopedKey('district', $city, $district) : '';
        if (isset($cached[$key]) && !realpriceGeocodeHitIsDistrictFallback($cached[$key], $city, $district)) {
            $items[$index]['status'] = 'done';
            $items[$index]['message'] = '已存在門牌座標快取。';
            continue;
        }
        if (isset($cached[$key]) && realpriceGeocodeHitIsDistrictFallback($cached[$key], $city, $district)) {
            // Clear poisoned address-key district fallback so door geocoding can retry.
            unset($cached[$key]);
        }
        if ($externalLookups >= $maxExternalLookups) {
            // Soft fallback only after address lookups are exhausted for this batch.
            if ($districtKey !== '' && isset($cached[$districtKey])) {
                $items[$index]['status'] = 'district_only';
                $items[$index]['message'] = '本批次外部查詢已滿；目前僅有行政區近似座標。';
                continue;
            }
            break;
        }
        $processed++;
        $externalLookups++;
        $items[$index]['attempts'] = (int) ($items[$index]['attempts'] ?? 0) + 1;
        $items[$index]['last_attempt_at'] = date(DATE_ATOM);
        try {
            $result = realpriceGeocodeLookup($address, $city, $district);
            if ($result['found']) {
                if (($result['precision'] ?? '') === 'district') {
                    if ($districtKey !== '') {
                        $cached[$districtKey] = [
                            'address' => trim($city . $district),
                            'city' => $city,
                            'district' => $district,
                            'lat' => (float) $result['lat'],
                            'lng' => (float) $result['lng'],
                            'display_name' => (string) $result['display_name'],
                            'provider' => 'nominatim',
                            'precision' => 'district',
                            'query' => (string) ($result['query'] ?? ''),
                            'category' => (string) ($result['category'] ?? ''),
                            'type' => (string) ($result['type'] ?? ''),
                            'addresstype' => (string) ($result['addresstype'] ?? ''),
                            'updated_at' => date(DATE_ATOM),
                        ];
                    }
                    // Keep address key free so a later pass can still try true door geocoding.
                    unset($cached[$key]);
                    $items[$index]['status'] = 'district_only';
                    $items[$index]['message'] = '門牌查無精準座標，已保存行政區近似。';
                    $found++;
                } else {
                    $cached[$key] = [
                        'address' => $address,
                        'city' => $city,
                        'district' => $district,
                        'lat' => (float) $result['lat'],
                        'lng' => (float) $result['lng'],
                        'display_name' => (string) $result['display_name'],
                        'provider' => (string) ($result['provider'] ?? 'nominatim'),
                        'precision' => (string) ($result['precision'] ?? 'address'),
                        'query' => (string) ($result['query'] ?? ''),
                        'category' => (string) ($result['category'] ?? ''),
                        'type' => (string) ($result['type'] ?? ''),
                        'addresstype' => (string) ($result['addresstype'] ?? ''),
                        'updated_at' => date(DATE_ATOM),
                    ];
                    $items[$index]['status'] = 'done';
                    $items[$index]['message'] = '已取得地址座標。';
                    $found++;
                }
            } else {
                if ($districtKey !== '' && isset($cached[$districtKey])) {
                    $items[$index]['status'] = 'district_only';
                    $items[$index]['message'] = '門牌查無精準座標，僅有行政區近似。';
                    $notFound++;
                } else {
                    $items[$index]['status'] = 'not_found';
                    $items[$index]['message'] = '查無座標。';
                    $notFound++;
                }
            }
            if ($externalLookups < $maxExternalLookups && $processed < $batchSize) {
                usleep(1200000);
            }
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (str_starts_with($message, 'RATE_LIMIT:')) {
                $state['rate_limited_until'] = (new DateTimeImmutable('+30 minutes'))->format(DATE_ATOM);
                $state['last_message'] = '地理編碼被限流，已暫停到 ' . $state['rate_limited_until'];
                $items[$index]['status'] = 'error';
                $items[$index]['message'] = $state['last_message'];
                $errors++;
                break;
            }
            $items[$index]['status'] = 'error';
            $items[$index]['message'] = $message;
            $errors++;
        }
    }
    $cache['items'] = $cached;
    $cache['updated_at'] = date(DATE_ATOM);
    $queue['items'] = $items;
    $state['provider'] = function_exists('tgosHasQueryAddrCredentials') && tgosHasQueryAddrCredentials() ? 'tgos+nominatim' : 'nominatim';
    $state['last_run_at'] = date(DATE_ATOM);
    if (!isset($state['last_message']) || $errors === 0) {
        $state['last_message'] = "本次外部查詢 {$externalLookups} 筆，成功 {$found} 筆，查無 {$notFound} 筆，錯誤 {$errors} 筆。已快取行政區會自動套用，避免請求逾時。";
    }
    realpriceSaveJsonFile(realpriceGeocodeCachePath(), $cache);
    realpriceSaveJsonFile(realpriceGeocodeQueuePath(), $queue);
    realpriceSaveJsonFile(realpriceGeocodeStatePath(), $state);
    return realpriceGeocodeStats();
}

function realpriceCsvValue(array $row, string $key): string
{
    return trim((string) ($row[$key] ?? ''));
}

function realpriceNumber(string $value): float
{
    $value = str_replace([',', '，', ' '], '', $value);
    if ($value === '' || !is_numeric($value)) return 0.0;
    return (float) $value;
}

function realpriceMinguoToIso(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value);
    if ($digits === null || strlen($digits) < 6) return '';
    $day = (int) substr($digits, -2);
    $month = (int) substr($digits, -4, 2);
    $yearPart = substr($digits, 0, -4);
    $year = (int) $yearPart + 1911;
    if (!checkdate($month, $day, $year)) return '';
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function realpriceRecordFromRow(array $row, string $cityCode, string $type, string $file): ?array
{
    $cityMap = realpriceCityMap();
    $city = $cityMap[$cityCode]['name'] ?? '';
    if ($city === '') return null;

    $district = realpriceCsvValue($row, '鄉鎮市區');
    $dateRaw = realpriceCsvValue($row, $type === 'rent' ? '租賃年月日' : '交易年月日');
    $date = realpriceMinguoToIso($dateRaw);
    $total = realpriceNumber(realpriceCsvValue($row, $type === 'rent' ? '總額元' : '總價元'));
    $unit = realpriceNumber(realpriceCsvValue($row, '單價元平方公尺'));
    $area = realpriceNumber(realpriceCsvValue($row, $type === 'rent' ? '建物總面積平方公尺' : '建物移轉總面積平方公尺'));
    $serial = realpriceCsvValue($row, '編號') ?: sha1($file . '|' . implode('|', $row));

    if ($district === '' || $date === '' || $total <= 0) {
        return null;
    }

    return [
        'id' => $serial,
        'city' => $city,
        'city_code' => $cityCode,
        'district' => $district,
        'type' => $type,
        'date' => $date,
        'target' => realpriceCsvValue($row, '交易標的'),
        'address' => realpriceCsvValue($row, '土地位置建物門牌'),
        'building_type' => realpriceCsvValue($row, '建物型態'),
        'floor' => realpriceCsvValue($row, '移轉層次'),
        'total_floors' => realpriceCsvValue($row, '總樓層數'),
        'total_price' => (int) round($total),
        'unit_price_sqm' => (int) round($unit),
        'unit_price_ping' => $unit > 0 ? (int) round($unit * 3.305785) : 0,
        'area_sqm' => round($area, 2),
        'source_file' => $file,
    ];
}

function realpriceAppendRecordsFromZip(string $zipPath, array &$records, array &$seen, int &$fileCount): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('實價登錄 ZIP 無法開啟：' . basename($zipPath));
    }
    $cityMap = realpriceCityMap();
    $typeMap = realpriceTypeMap();
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $base = basename($name);
            if (!preg_match('/^([a-z])_lvr_land_([abc])\.csv$/i', $base, $matches)) {
                continue;
            }
            $cityCode = strtolower($matches[1]);
            $typeCode = strtolower($matches[2]);
            if (!isset($cityMap[$cityCode], $typeMap[$typeCode])) {
                continue;
            }
            $stream = $zip->getStream($name);
            if (!is_resource($stream)) {
                continue;
            }
            $header = fgetcsv($stream);
            fgetcsv($stream);
            if (!is_array($header)) {
                fclose($stream);
                continue;
            }
            $fileCount++;
            while (($values = fgetcsv($stream)) !== false) {
                if (!is_array($values) || count($values) < 8) continue;
                $row = [];
                foreach ($header as $index => $key) {
                    $row[(string) $key] = (string) ($values[$index] ?? '');
                }
                $record = realpriceRecordFromRow($row, $cityCode, $typeMap[$typeCode], $base);
                if (!$record) continue;
                $dedupeKey = $record['id'] . '|' . $record['type'];
                if (isset($seen[$dedupeKey])) continue;
                $seen[$dedupeKey] = true;
                $records[] = $record;
            }
            fclose($stream);
        }
    } finally {
        $zip->close();
    }
}

function realpriceAppendRecordsToSqlite(PDO $pdo, PDOStatement $insert, string $zipPath, array &$seen, int &$fileCount, int &$recordCount, bool $useIgnore = false): int
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('實價登錄 ZIP 無法開啟：' . basename($zipPath));
    }
    $cityMap = realpriceCityMap();
    $typeMap = realpriceTypeMap();
    $added = 0;
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $base = basename($name);
            if (!preg_match('/^([a-z])_lvr_land_([abc])\.csv$/i', $base, $matches)) {
                continue;
            }
            $cityCode = strtolower($matches[1]);
            $typeCode = strtolower($matches[2]);
            if (!isset($cityMap[$cityCode], $typeMap[$typeCode])) {
                continue;
            }
            $stream = $zip->getStream($name);
            if (!is_resource($stream)) {
                continue;
            }
            $header = fgetcsv($stream);
            fgetcsv($stream);
            if (!is_array($header)) {
                fclose($stream);
                continue;
            }
            $header = array_map(static function ($value): string {
                $text = trim((string) $value);
                if (str_starts_with($text, "\xEF\xBB\xBF")) {
                    $text = substr($text, 3);
                }
                return $text;
            }, $header);
            $fileCount++;
            while (($values = fgetcsv($stream)) !== false) {
                if (!is_array($values) || count($values) < 8) continue;
                $row = [];
                foreach ($header as $index => $key) {
                    $row[(string) $key] = (string) ($values[$index] ?? '');
                }
                $record = realpriceRecordFromRow($row, $cityCode, $typeMap[$typeCode], $base);
                if (!$record) continue;
                $dedupeKey = $record['id'] . '|' . $record['type'];
                if (!$useIgnore) {
                    if (isset($seen[$dedupeKey])) continue;
                    $seen[$dedupeKey] = true;
                }
                $insert->execute([
                    (string) $record['id'],
                    (string) $record['type'],
                    (string) $record['date'],
                    (string) $record['city'],
                    (string) $record['city_code'],
                    (string) $record['district'],
                    (string) $record['target'],
                    (string) $record['address'],
                    (string) $record['building_type'],
                    (string) ($record['floor'] ?? ''),
                    (string) ($record['total_floors'] ?? ''),
                    (int) $record['total_price'],
                    (int) $record['unit_price_sqm'],
                    (int) $record['unit_price_ping'],
                    (float) $record['area_sqm'],
                    (string) $record['source_file'],
                ]);
                $added++;
                $recordCount++;
            }
            fclose($stream);
        }
    } finally {
        $zip->close();
    }
    return $added;
}

function realpriceBuildIndex(): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive 未啟用，無法建立實價登錄索引。');
    }
    if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PHP PDO SQLite 未啟用，無法建立實價登錄索引。');
    }

    @ini_set('memory_limit', '512M');
    @set_time_limit(0);

    $zipPaths = realpriceListSeasonZipPaths();
    if (realpriceIsZipFile(realpriceZipPath())) {
        $zipPaths[] = realpriceZipPath();
    }
    $zipPaths = array_values(array_unique($zipPaths));
    if ($zipPaths === []) {
        throw new RuntimeException('尚未下載實價登錄季度 ZIP。請先執行「下載／更新」。');
    }

    $dir = realpriceDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $tmp = realpriceSqlitePath() . '.part.' . bin2hex(random_bytes(4));
    @unlink($tmp);
    $pdo = new PDO('sqlite:' . $tmp, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('PRAGMA journal_mode=OFF');
    $pdo->exec('PRAGMA synchronous=OFF');
    $pdo->exec(
        'CREATE TABLE meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE records (
            id TEXT NOT NULL,
            type TEXT NOT NULL,
            date TEXT NOT NULL,
            city TEXT NOT NULL,
            city_code TEXT NOT NULL,
            district TEXT NOT NULL,
            target TEXT NOT NULL,
            address TEXT NOT NULL,
            building_type TEXT NOT NULL,
            floor TEXT NOT NULL,
            total_floors TEXT NOT NULL,
            total_price INTEGER NOT NULL,
            unit_price_sqm INTEGER NOT NULL,
            unit_price_ping INTEGER NOT NULL,
            area_sqm REAL NOT NULL,
            source_file TEXT NOT NULL
        )'
    );
    $insert = $pdo->prepare(
        'INSERT INTO records(
            id, type, date, city, city_code, district, target, address,
            building_type, floor, total_floors, total_price, unit_price_sqm, unit_price_ping, area_sqm, source_file
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );

    $seen = [];
    $fileCount = 0;
    $recordCount = 0;
    $totalZipBytes = 0;
    $pdo->beginTransaction();
    try {
        foreach ($zipPaths as $zipPath) {
            $totalZipBytes += (int) filesize($zipPath);
            realpriceAppendRecordsToSqlite($pdo, $insert, $zipPath, $seen, $fileCount, $recordCount);
        }
        $pdo->exec('CREATE INDEX idx_records_type_date ON records(type, date)');
        $pdo->exec('CREATE INDEX idx_records_city ON records(city)');

        $generatedAt = date(DATE_ATOM);
        $metaPairs = [
            'generated_at' => $generatedAt,
            'source_url' => realpriceSeasonDownloadUrl((string) (realpriceRecentSeasonCodes(1)[0] ?? '115S2')),
            'zip_paths' => array_map('basename', $zipPaths),
            'zip_size' => $totalZipBytes,
            'file_count' => $fileCount,
            'record_count' => $recordCount,
            'city_map' => realpriceCityMap(),
        ];
        $metaInsert = $pdo->prepare('INSERT INTO meta(key, value) VALUES (?, ?)');
        foreach ($metaPairs as $key => $value) {
            $metaInsert->execute([(string) $key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo = null;
        @unlink($tmp);
        throw $e;
    }
    $pdo = null;

    if (!@rename($tmp, realpriceSqlitePath())) {
        @unlink($tmp);
        throw new RuntimeException('實價登錄 SQLite 索引替換失敗。');
    }

    // 舊版巨型 JSON 不再維護，避免再次把 API 打爆記憶體。
    if (is_file(realpriceIndexPath())) {
        @unlink(realpriceIndexPath());
    }

    $meta = realpriceLoadMeta();
    $meta['indexed_at'] = $generatedAt;
    $meta['indexed_count'] = $recordCount;
    $meta['indexed_file_count'] = $fileCount;
    $meta['indexed_zip_count'] = count($zipPaths);
    $meta['zip_size'] = $totalZipBytes;
    $meta['notice'] = '實價登錄索引已建立（' . count($zipPaths) . ' 個 ZIP，' . number_format($recordCount) . ' 筆，SQLite）。';
    realpriceSaveMeta($meta);

    return realpriceStatus();
}

function realpriceIndexJobStatePath(): string
{
    return realpriceDir() . '/index-job.json';
}

function realpriceListZipCsvTasks(string $zipPath): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('實價登錄 ZIP 無法開啟：' . basename($zipPath));
    }
    $cityMap = realpriceCityMap();
    $typeMap = realpriceTypeMap();
    $tasks = [];
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $base = basename($name);
            if (!preg_match('/^([a-z])_lvr_land_([abc])\.csv$/i', $base, $matches)) {
                continue;
            }
            $cityCode = strtolower($matches[1]);
            $typeCode = strtolower($matches[2]);
            if (!isset($cityMap[$cityCode], $typeMap[$typeCode])) {
                continue;
            }
            $tasks[] = [
                'zip' => $zipPath,
                'zip_name' => basename($zipPath),
                'entry' => $name,
                'base' => $base,
                'city' => $cityCode,
                'type' => (string) $typeMap[$typeCode],
            ];
        }
    } finally {
        $zip->close();
    }
    return $tasks;
}

function realpriceIndexJobCreateSqlite(string $tmp): PDO
{
    @unlink($tmp);
    $pdo = new PDO('sqlite:' . $tmp, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('PRAGMA journal_mode=OFF');
    $pdo->exec('PRAGMA synchronous=OFF');
    $pdo->exec(
        'CREATE TABLE meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE records (
            id TEXT NOT NULL,
            type TEXT NOT NULL,
            date TEXT NOT NULL,
            city TEXT NOT NULL,
            city_code TEXT NOT NULL,
            district TEXT NOT NULL,
            target TEXT NOT NULL,
            address TEXT NOT NULL,
            building_type TEXT NOT NULL,
            floor TEXT NOT NULL,
            total_floors TEXT NOT NULL,
            total_price INTEGER NOT NULL,
            unit_price_sqm INTEGER NOT NULL,
            unit_price_ping INTEGER NOT NULL,
            area_sqm REAL NOT NULL,
            source_file TEXT NOT NULL,
            UNIQUE(id, type)
        )'
    );
    return $pdo;
}

function realpriceIndexJobStart(array $selectedFiles = []): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive 未啟用，無法建立實價登錄索引。');
    }
    if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PHP PDO SQLite 未啟用，無法建立實價登錄索引。');
    }

    $prev = realpriceLoadJsonFile(realpriceIndexJobStatePath(), []);
    if (($prev['status'] ?? '') === 'running') {
        $updated = strtotime((string) ($prev['updated_at'] ?? '')) ?: 0;
        $age = $updated > 0 ? (time() - $updated) : PHP_INT_MAX;
        if ($age < 45 && empty($prev['busy'])) {
            $prev['message'] = '偵測到進行中的索引工作，改為繼續上次進度…'
                . ((string) ($prev['current_label'] ?? '') !== ''
                    ? ('（' . (string) $prev['current_label'] . '）')
                    : '');
            $prev['updated_at'] = date(DATE_ATOM);
            realpriceSaveJsonFile(realpriceIndexJobStatePath(), $prev);
            return [
                'ok' => true,
                'done' => false,
                'resume' => true,
                'job' => $prev,
                'status' => realpriceStatus(),
                'message' => $prev['message'],
            ];
        }
        realpriceIndexJobCancel();
    }

    $allPaths = realpriceListSeasonZipPaths();
    if (realpriceIsZipFile(realpriceZipPath())) {
        $allPaths[] = realpriceZipPath();
    }
    $allPaths = array_values(array_unique($allPaths));
    if ($allPaths === []) {
        throw new RuntimeException('尚未下載實價登錄季度 ZIP。請先執行「下載／更新」。');
    }

    $selectedFiles = array_values(array_filter(array_map(
        static fn ($value): string => basename(str_replace('\\', '/', (string) $value)),
        $selectedFiles
    )));
    $zipPaths = [];
    if ($selectedFiles === []) {
        $zipPaths = $allPaths;
    } else {
        $want = array_fill_keys(array_map('strtolower', $selectedFiles), true);
        foreach ($allPaths as $path) {
            $base = strtolower(basename($path));
            $season = strtolower(basename($path, '.zip'));
            if (isset($want[$base]) || isset($want[$season]) || isset($want[$season . '.zip'])) {
                $zipPaths[] = $path;
            }
        }
    }
    if ($zipPaths === []) {
        throw new RuntimeException('沒有符合勾選條件的季度 ZIP。');
    }

    $dir = realpriceDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $tmp = realpriceSqlitePath() . '.part.' . bin2hex(random_bytes(4));
    $selectedOnly = $selectedFiles !== [];
    $mergeExisting = false;
    $seedStats = [];
    $seedRecords = 0;
    $seedFiles = 0;
    $existingSqlite = realpriceSqlitePath();

    // 勾選部分季度時：複製既有索引再追加，避免未勾選的季被整份蓋掉。
    if ($selectedOnly && is_file($existingSqlite) && (int) filesize($existingSqlite) > 0) {
        if (!@copy($existingSqlite, $tmp)) {
            throw new RuntimeException('無法複製既有索引以合併季度，請改用「全部季度重建」。');
        }
        $mergeExisting = true;
        $meta = realpriceLoadMeta();
        $seedStats = is_array($meta['indexed_seasons'] ?? null) ? $meta['indexed_seasons'] : [];
        try {
            $pdoSeed = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdoSeed->exec('PRAGMA journal_mode=OFF');
            $pdoSeed->exec('PRAGMA synchronous=OFF');
            $seedRecords = (int) $pdoSeed->query('SELECT COUNT(*) FROM records')->fetchColumn();
            $pdoSeed = null;
        } catch (Throwable $e) {
            @unlink($tmp);
            throw new RuntimeException('既有索引無法讀取，請改用「全部季度重建」。');
        }
        foreach ($seedStats as $stat) {
            if (is_array($stat)) {
                $seedFiles += (int) ($stat['files'] ?? 0);
            }
        }
        $pdo = null;
    } else {
        $pdo = realpriceIndexJobCreateSqlite($tmp);
        $pdo = null;
    }

    $msg = $mergeExisting
        ? ('合併索引：保留既有 ' . number_format($seedRecords) . ' 筆，再處理 ' . count($zipPaths) . ' 個勾選 ZIP…')
        : ('已建立索引工作，共 ' . count($zipPaths) . ' 個 ZIP，將逐季處理…');

    $job = [
        'started_at' => date(DATE_ATOM),
        'updated_at' => date(DATE_ATOM),
        'status' => 'running',
        'tmp_sqlite' => $tmp,
        'zip_paths' => $zipPaths,
        'zip_count' => count($zipPaths),
        'zip_index' => 0,
        'selected_only' => $selectedOnly,
        'merge_existing' => $mergeExisting,
        'file_count' => $seedFiles,
        'record_count' => $seedRecords,
        'season_stats' => $seedStats,
        'total_zip_bytes' => array_sum(array_map(
            static fn (string $path): int => is_file($path) ? (int) filesize($path) : 0,
            $zipPaths
        )),
        'current_label' => '',
        'pct' => 0,
        'message' => $msg,
    ];
    realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);

    return [
        'ok' => true,
        'done' => false,
        'job' => $job,
        'status' => realpriceStatus(),
        'message' => $job['message'],
    ];
}

function realpriceIndexJobCancel(): array
{
    $job = realpriceLoadJsonFile(realpriceIndexJobStatePath(), []);
    $tmp = (string) ($job['tmp_sqlite'] ?? '');
    if ($tmp !== '' && is_file($tmp)) {
        @unlink($tmp);
    }
    $job['status'] = 'cancelled';
    $job['updated_at'] = date(DATE_ATOM);
    $job['message'] = '索引重建已取消。';
    $job['busy'] = false;
    unset($job['busy_until'], $job['tasks']);
    realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);
    return [
        'ok' => true,
        'done' => true,
        'cancelled' => true,
        'job' => $job,
        'status' => realpriceStatus(),
        'message' => $job['message'],
    ];
}

function realpriceIndexJobFinalize(array $job): array
{
    $tmp = (string) ($job['tmp_sqlite'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        throw new RuntimeException('索引暫存檔遺失，請重新開始重建。');
    }

    $pdo = new PDO('sqlite:' . $tmp, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_type_date ON records(type, date)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_records_city ON records(city)');
    $recordCount = (int) $pdo->query('SELECT COUNT(*) FROM records')->fetchColumn();

    $generatedAt = date(DATE_ATOM);
    $zipPaths = is_array($job['zip_paths'] ?? null) ? $job['zip_paths'] : [];
    $seasonStats = is_array($job['season_stats'] ?? null) ? $job['season_stats'] : [];
    // 僅在實際複製既有 DB 合併時，才保留其他季的狀態標記。
    if (!empty($job['merge_existing'])) {
        $prevMeta = realpriceLoadMeta();
        $prevSeasons = is_array($prevMeta['indexed_seasons'] ?? null) ? $prevMeta['indexed_seasons'] : [];
        if ($prevSeasons !== []) {
            $merged = $prevSeasons;
            foreach ($seasonStats as $key => $stat) {
                if (is_array($stat)) {
                    $merged[$key] = $stat;
                }
            }
            $seasonStats = $merged;
        }
    }

    $fileCount = 0;
    $totalZipBytes = 0;
    foreach ($seasonStats as $stat) {
        if (!is_array($stat)) {
            continue;
        }
        $fileCount += (int) ($stat['files'] ?? 0);
        $totalZipBytes += (int) ($stat['size'] ?? 0);
    }
    if ($fileCount <= 0) {
        $fileCount = (int) ($job['file_count'] ?? 0);
    }
    if ($totalZipBytes <= 0) {
        $totalZipBytes = (int) ($job['total_zip_bytes'] ?? 0);
    }

    $indexedZipNames = array_values(array_filter(array_map(
        static function ($stat) {
            if (!is_array($stat)) {
                return '';
            }
            return (string) ($stat['file'] ?? '');
        },
        $seasonStats
    )));
    if ($indexedZipNames === []) {
        $indexedZipNames = array_map('basename', $zipPaths);
    }

    $metaPairs = [
        'generated_at' => $generatedAt,
        'source_url' => realpriceSeasonDownloadUrl((string) (realpriceRecentSeasonCodes(1)[0] ?? '115S2')),
        'zip_paths' => $indexedZipNames,
        'zip_size' => $totalZipBytes,
        'file_count' => $fileCount,
        'record_count' => $recordCount,
        'city_map' => realpriceCityMap(),
    ];
    $metaInsert = $pdo->prepare('INSERT OR REPLACE INTO meta(key, value) VALUES (?, ?)');
    foreach ($metaPairs as $key => $value) {
        $metaInsert->execute([(string) $key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
    $pdo = null;

    $finalPath = realpriceSqlitePath();
    if (is_file($finalPath)) {
        @unlink($finalPath);
    }
    if (!@rename($tmp, $finalPath)) {
        if (!@copy($tmp, $finalPath)) {
            @unlink($tmp);
            throw new RuntimeException('實價登錄 SQLite 索引替換失敗。');
        }
        @unlink($tmp);
    }
    if (is_file(realpriceIndexPath())) {
        @unlink(realpriceIndexPath());
    }

    $meta = realpriceLoadMeta();
    $meta['indexed_at'] = $generatedAt;
    $meta['indexed_count'] = $recordCount;
    $meta['indexed_file_count'] = $fileCount;
    $meta['indexed_zip_count'] = count($seasonStats);
    $meta['zip_size'] = $totalZipBytes;
    $meta['indexed_seasons'] = $seasonStats;
    if (!empty($job['merge_existing'])) {
        $scope = '合併勾選季度';
    } elseif (!empty($job['selected_only'])) {
        $scope = '勾選季度';
    } else {
        $scope = '全部本機 ZIP';
    }
    $meta['notice'] = '實價登錄索引已建立（' . $scope . '，' . count($seasonStats) . ' 個 ZIP，' . number_format($recordCount) . ' 筆，SQLite，分批）。';
    realpriceSaveMeta($meta);

    $job['status'] = 'done';
    $job['pct'] = 100;
    $job['record_count'] = $recordCount;
    $job['updated_at'] = date(DATE_ATOM);
    $job['message'] = $meta['notice'];
    $job['current_label'] = '完成';
    unset($job['tasks'], $job['busy'], $job['busy_until']);
    realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);

    return [
        'ok' => true,
        'done' => true,
        'job' => $job,
        'status' => realpriceStatus(),
        'message' => $job['message'],
    ];
}

function realpriceAppendCsvEntryToSqlite(
    PDO $pdo,
    PDOStatement $insert,
    string $zipPath,
    array $task,
    int $rowOffset,
    float $deadline,
    int &$fileCount
): array {
    $entry = (string) ($task['entry'] ?? '');
    $base = (string) ($task['base'] ?? basename($entry));
    $cityCode = (string) ($task['city'] ?? '');
    $type = (string) ($task['type'] ?? '');
    if ($entry === '' || $cityCode === '' || $type === '') {
        return ['added' => 0, 'rows_read' => 0, 'finished' => true, 'file_counted' => false];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('實價登錄 ZIP 無法開啟：' . basename($zipPath));
    }
    $added = 0;
    $rowsRead = 0;
    $finished = true;
    $fileCounted = false;
    try {
        $stream = $zip->getStream($entry);
        if (!is_resource($stream)) {
            return ['added' => 0, 'rows_read' => 0, 'finished' => true, 'file_counted' => false];
        }
        $header = fgetcsv($stream);
        fgetcsv($stream);
        if (!is_array($header)) {
            fclose($stream);
            return ['added' => 0, 'rows_read' => 0, 'finished' => true, 'file_counted' => false];
        }
        $header = array_map(static function ($value): string {
            $text = trim((string) $value);
            if (str_starts_with($text, "\xEF\xBB\xBF")) {
                $text = substr($text, 3);
            }
            return $text;
        }, $header);
        if ($rowOffset <= 0) {
            $fileCount++;
            $fileCounted = true;
        }
        $skip = max(0, $rowOffset);
        while (($values = fgetcsv($stream)) !== false) {
            if ($skip > 0) {
                $skip--;
                continue;
            }
            $rowsRead++;
            if (is_array($values) && count($values) >= 8) {
                $row = [];
                foreach ($header as $index => $key) {
                    $row[(string) $key] = (string) ($values[$index] ?? '');
                }
                $record = realpriceRecordFromRow($row, $cityCode, $type, $base);
                if ($record) {
                    $sourceFile = basename($zipPath) . '::' . $base;
                    $insert->execute([
                        (string) $record['id'],
                        (string) $record['type'],
                        (string) $record['date'],
                        (string) $record['city'],
                        (string) $record['city_code'],
                        (string) $record['district'],
                        (string) $record['target'],
                        (string) $record['address'],
                        (string) $record['building_type'],
                        (string) ($record['floor'] ?? ''),
                        (string) ($record['total_floors'] ?? ''),
                        (int) $record['total_price'],
                        (int) $record['unit_price_sqm'],
                        (int) $record['unit_price_ping'],
                        (float) $record['area_sqm'],
                        $sourceFile,
                    ]);
                    $added++;
                }
            }
            if (microtime(true) >= $deadline) {
                $finished = false;
                break;
            }
        }
        fclose($stream);
    } finally {
        $zip->close();
    }
    return [
        'added' => $added,
        'rows_read' => $rowsRead,
        'finished' => $finished,
        'file_counted' => $fileCounted,
    ];
}

function realpriceIndexJobChunk(int $maxSeconds = 8): array
{
    @ini_set('memory_limit', '512M');
    @set_time_limit(max(25, $maxSeconds + 15));

    $job = realpriceLoadJsonFile(realpriceIndexJobStatePath(), []);
    if (($job['status'] ?? '') !== 'running') {
        return [
            'ok' => true,
            'done' => true,
            'job' => $job,
            'status' => realpriceStatus(),
            'message' => (string) ($job['message'] ?? '沒有進行中的索引工作。'),
        ];
    }

    $busyUntil = strtotime((string) ($job['busy_until'] ?? '')) ?: 0;
    $updated = strtotime((string) ($job['updated_at'] ?? '')) ?: 0;
    // If previous worker likely died, clear stale busy lock quickly.
    if (!empty($job['busy']) && $busyUntil > time() && $updated > 0 && (time() - $updated) < 20) {
        return [
            'ok' => true,
            'done' => false,
            'busy' => true,
            'job' => $job,
            'status' => realpriceStatus(),
            'message' => '上一小批仍在處理，稍後繼續…',
        ];
    }

    $job['busy'] = true;
    $job['busy_until'] = date(DATE_ATOM, time() + max(20, $maxSeconds + 12));
    $job['updated_at'] = date(DATE_ATOM);
    realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);

    try {
        $tmp = (string) ($job['tmp_sqlite'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new RuntimeException('索引暫存檔遺失，請重新開始重建。');
        }
        $zipPaths = is_array($job['zip_paths'] ?? null) ? $job['zip_paths'] : [];
        $zipIndex = (int) ($job['zip_index'] ?? 0);
        $zipTotal = max(1, count($zipPaths));
        if ($zipIndex >= count($zipPaths)) {
            $job['busy'] = false;
            unset($job['busy_until']);
            realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);
            return realpriceIndexJobFinalize($job);
        }

        $zipPath = (string) $zipPaths[$zipIndex];
        if (!is_file($zipPath)) {
            throw new RuntimeException('找不到 ZIP：' . basename($zipPath));
        }

        $entries = is_array($job['current_entries'] ?? null) ? $job['current_entries'] : [];
        $entryIndex = (int) ($job['entry_index'] ?? 0);
        if ($entries === []) {
            $entries = realpriceListZipCsvTasks($zipPath);
            $entryIndex = 0;
            $job['current_entries'] = $entries;
            $job['entry_index'] = 0;
            $job['entry_row'] = 0;
            $job['zip_added'] = 0;
            $job['zip_files'] = 0;
            // 合併模式下重跑同一季時，先清掉該季已寫入的資料列。
            if (!empty($job['merge_existing'])) {
                $pdoDel = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $del = $pdoDel->prepare('DELETE FROM records WHERE source_file LIKE ?');
                $del->execute([basename($zipPath) . '::%']);
                $removed = (int) $del->rowCount();
                if ($removed > 0) {
                    $job['record_count'] = max(0, (int) ($job['record_count'] ?? 0) - $removed);
                    $job['message'] = '合併重建 ' . basename($zipPath) . '：已移除舊資料 ' . number_format($removed) . ' 筆…';
                    $job['updated_at'] = date(DATE_ATOM);
                    realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);
                }
                $pdoDel = null;
            }
        }

        $pdo = new PDO('sqlite:' . $tmp, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('PRAGMA journal_mode=OFF');
        $pdo->exec('PRAGMA synchronous=OFF');
        $insert = $pdo->prepare(
            'INSERT OR IGNORE INTO records(
                id, type, date, city, city_code, district, target, address,
                building_type, floor, total_floors, total_price, unit_price_sqm, unit_price_ping, area_sqm, source_file
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        $started = microtime(true);
        $deadline = $started + max(3, $maxSeconds);
        $entryRow = (int) ($job['entry_row'] ?? 0);
        $batchAdded = 0;
        while ($entryIndex < count($entries) && microtime(true) < $deadline) {
            $task = $entries[$entryIndex];
            if (!is_array($task)) {
                $entryIndex++;
                $entryRow = 0;
                continue;
            }
            $label = basename($zipPath) . ' / ' . (string) ($task['base'] ?? '');
            $job['current_label'] = $label;
            $fileCount = 0;
            $result = realpriceAppendCsvEntryToSqlite(
                $pdo,
                $insert,
                $zipPath,
                $task,
                $entryRow,
                $deadline,
                $fileCount
            );
            $added = (int) ($result['added'] ?? 0);
            $rowsRead = (int) ($result['rows_read'] ?? 0);
            $finished = !empty($result['finished']);
            $batchAdded += $added;
            $entryRow += $rowsRead;
            $job['zip_added'] = (int) ($job['zip_added'] ?? 0) + $added;
            if (!empty($result['file_counted'])) {
                $job['zip_files'] = (int) ($job['zip_files'] ?? 0) + 1;
                $job['file_count'] = (int) ($job['file_count'] ?? 0) + 1;
            }
            $job['record_count'] = (int) ($job['record_count'] ?? 0) + $added;
            if ($finished) {
                $entryIndex++;
                $entryRow = 0;
            }
            $job['entry_index'] = $entryIndex;
            $job['entry_row'] = $entryRow;
            $zipPct = count($entries) > 0
                ? (($entryIndex + ($finished ? 0 : 0.5)) / count($entries))
                : 1;
            $job['pct'] = (int) min(99, floor((($zipIndex + $zipPct) / $zipTotal) * 100));
            $job['message'] = '索引中 ' . $job['pct'] . '%：' . $label
                . '（+' . number_format($added) . '，本季 ' . number_format((int) $job['zip_added'])
                . '，累計 ' . number_format((int) $job['record_count']) . ' 筆'
                . ($finished ? '' : '，續跑中') . '）';
            $job['updated_at'] = date(DATE_ATOM);
            realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);
            if (!$finished) {
                break;
            }
        }
        $pdo = null;

        // Finished all CSVs in this ZIP.
        if ($entryIndex >= count($entries)) {
            $seasonKey = basename($zipPath);
            $seasonStats = is_array($job['season_stats'] ?? null) ? $job['season_stats'] : [];
            $seasonStats[$seasonKey] = [
                'season' => strtoupper(basename($zipPath, '.zip')),
                'file' => $seasonKey,
                'records' => (int) ($job['zip_added'] ?? 0),
                'files' => (int) ($job['zip_files'] ?? 0),
                'size' => (int) filesize($zipPath),
                'mtime_ts' => (int) (@filemtime($zipPath) ?: 0),
                'indexed_at' => date(DATE_ATOM),
            ];
            $job['season_stats'] = $seasonStats;
            $job['zip_index'] = $zipIndex + 1;
            $job['entry_index'] = 0;
            $job['entry_row'] = 0;
            unset($job['current_entries'], $job['zip_added'], $job['zip_files']);
            $job['pct'] = (int) min(99, floor(($job['zip_index'] / $zipTotal) * 100));
            $job['message'] = '完成 ' . $seasonKey . '（累計 ' . number_format((int) $job['record_count']) . ' 筆），繼續下一季…';
        }

        $job['busy'] = false;
        unset($job['busy_until']);
        $job['updated_at'] = date(DATE_ATOM);

        if ((int) ($job['zip_index'] ?? 0) >= count($zipPaths)) {
            // Sync exact DB count before finalize.
            $pdoCount = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $job['record_count'] = (int) $pdoCount->query('SELECT COUNT(*) FROM records')->fetchColumn();
            $pdoCount = null;
            realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);
            $result = realpriceIndexJobFinalize($job);
            realpriceOpLog('rebuild_realprice', (string) $result['message'], [
                'indexed_count' => (int) (($result['status']['indexed_count'] ?? 0)),
            ], 'ok');
            return $result;
        }

        realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);
        return [
            'ok' => true,
            'done' => false,
            'job' => $job,
            'status' => realpriceStatus(),
            'message' => $job['message'],
        ];
    } catch (Throwable $e) {
        $job['status'] = 'error';
        $job['busy'] = false;
        unset($job['busy_until']);
        $job['message'] = '索引失敗：' . $e->getMessage();
        $job['updated_at'] = date(DATE_ATOM);
        realpriceSaveJsonFile(realpriceIndexJobStatePath(), $job);
        $tmp = (string) ($job['tmp_sqlite'] ?? '');
        if ($tmp !== '' && is_file($tmp)) {
            @unlink($tmp);
        }
        realpriceOpLog('rebuild_realprice', $job['message'], [], 'error');
        throw $e;
    }
}

function realpriceBoundsContains(array $bounds, float $lat, float $lng): bool
{
    if (count($bounds) !== 4) return true;
    [$south, $west, $north, $east] = $bounds;
    return $lat >= $south && $lat <= $north && $lng >= $west && $lng <= $east;
}

function realpriceSinceDate(int $months): string
{
    $months = max(1, min(36, $months));
    return (new DateTimeImmutable('today'))->modify('-' . $months . ' months')->format('Y-m-d');
}

function realpriceQuery(array $params): array
{
    $index = realpriceLoadIndex();
    $overrides = realpriceLoadOverrides();
    $recordOverrides = is_array($overrides['records'] ?? null) ? $overrides['records'] : [];
    $customRecords = is_array($overrides['custom_records'] ?? null) ? $overrides['custom_records'] : [];
    $cityMap = is_array($index['city_map'] ?? null) ? $index['city_map'] : realpriceCityMap();
    $type = (string) ($params['type'] ?? 'sale');
    if (!in_array($type, ['sale', 'presale', 'rent'], true)) {
        $type = 'sale';
    }
    $period = (int) ($params['period'] ?? 12);
    $zoom = (int) ($params['zoom'] ?? 0);
    $since = realpriceSinceDate($period);
    $bounds = is_array($params['bounds'] ?? null) ? $params['bounds'] : [];
    $districtClusterZoomMin = 11;
    $itemZoomMin = 14;
    $itemFullZoomMin = 16;
    $clusterMode = $zoom >= $itemZoomMin ? 'detail' : ($zoom >= $districtClusterZoomMin ? 'district' : 'city');

    if ($clusterMode === 'detail') {
        $fastDetail = realpriceQueryDetailFromSqlite($params);
        if (is_array($fastDetail)) {
            return $fastDetail;
        }
    }

    if ($clusterMode !== 'detail') {
        $fast = realpriceQueryFromSqlite($params, $clusterMode);
        if (is_array($fast)) {
            return $fast;
        }
    }

    $geocodeCache = realpriceLoadGeocodeCache();
    $geocoded = is_array($geocodeCache['items'] ?? null) ? $geocodeCache['items'] : [];
    $groups = [];
    $addressItems = [];
    $districtSummaries = [];
    $districtClusterGroups = [];
    $itemLimit = $zoom >= $itemFullZoomMin ? 800 : ($zoom >= $itemZoomMin ? 350 : 0);
    $buildDistrictClusters = $clusterMode === 'district';
    $coarseCount = 0;
    $addressHitCount = 0;
    $districtHitCount = 0;
    $missingGeocodeCount = 0;
    $citiesInView = realpriceCityNamesForBounds($bounds, $cityMap);
    $cityFilter = count($citiesInView) > 0 && count($citiesInView) < count($cityMap) ? $citiesInView : null;

    $recordSource = static function () use ($type, $since, $recordOverrides, $customRecords, $cityFilter): Generator {
        foreach (realpriceIterateRecords($type, $since, $cityFilter) as $record) {
            if (!is_array($record)) {
                continue;
            }
            $key = realpriceRecordKey($record);
            $override = is_array($recordOverrides[$key] ?? null) ? $recordOverrides[$key] : [];
            if (!empty($override['hidden'])) {
                continue;
            }
            if ($override !== []) {
                $record['_override'] = $override;
            }
            yield $record;
        }
        foreach ($customRecords as $record) {
            if (!is_array($record) || !empty($record['hidden'])) {
                continue;
            }
            if ((string) ($record['type'] ?? '') !== $type) {
                continue;
            }
            if (strcmp((string) ($record['date'] ?? ''), $since) < 0) {
                continue;
            }
            $record['source_file'] = (string) ($record['source_file'] ?? 'local_override');
            $record['_custom'] = true;
            yield $record;
        }
    };

    foreach ($recordSource() as $record) {
        if ($clusterMode === 'detail' && $itemLimit > 0 && count($addressItems) >= $itemLimit) {
            break;
        }
        if (($record['type'] ?? '') !== $type) continue;
        if (strcmp((string) ($record['date'] ?? ''), $since) < 0) continue;
        $cityCode = (string) ($record['city_code'] ?? '');
        $cityInfo = $cityMap[$cityCode] ?? null;
        $manual = is_array($record['_override'] ?? null) ? $record['_override'] : [];
        if (!is_array($cityInfo) && (($manual['lat'] ?? '') === '' || ($manual['lng'] ?? '') === '')) continue;
        $lat = is_array($cityInfo) ? (float) ($cityInfo['lat'] ?? 0) : (float) $manual['lat'];
        $lng = is_array($cityInfo) ? (float) ($cityInfo['lng'] ?? 0) : (float) $manual['lng'];
        $cityInBounds = realpriceBoundsContains($bounds, $lat, $lng);
        if ($cityInBounds) {
            $coarseCount++;
        }
        $address = (string) ($record['address'] ?? '');
        $cityName = (string) ($record['city'] ?? ($cityInfo['name'] ?? $cityCode));
        $districtName = (string) ($record['district'] ?? '未分區');
        $hit = null;
        $itemLat = 0.0;
        $itemLng = 0.0;
        $itemInBounds = false;
        $hitPrecision = '';
        $districtLat = 0.0;
        $districtLng = 0.0;
        $districtInBounds = false;
        $needGeo = $itemLimit > 0 || $buildDistrictClusters;
        if ($needGeo) {
            $addressKey = realpriceGeocodeKey($address);
            $districtKey = realpriceGeocodeScopedKey('district', $cityName, $districtName);
            if (($manual['lat'] ?? '') !== '' && ($manual['lng'] ?? '') !== '') {
                $hit = ['lat' => $manual['lat'], 'lng' => $manual['lng'], 'precision' => 'manual', 'provider' => 'local_override', 'query' => '手動座標'];
            } elseif (is_array($geocoded[$addressKey] ?? null) && !realpriceGeocodeHitIsDistrictFallback($geocoded[$addressKey], $cityName, $districtName)) {
                $hit = $geocoded[$addressKey];
            }
            $districtHit = is_array($geocoded[$districtKey] ?? null) ? $geocoded[$districtKey] : null;
            if ($hit) {
                $hitPrecision = realpriceGeocodeHitPrecision($hit, $cityName, $districtName);
                $itemLat = (float) ($hit['lat'] ?? 0);
                $itemLng = (float) ($hit['lng'] ?? 0);
                $itemInBounds = realpriceBoundsContains($bounds, $itemLat, $itemLng);
                $hit['precision'] = $hitPrecision;
                if ($itemInBounds) {
                    $addressHitCount++;
                }
            } elseif ($districtHit) {
                $districtLat = (float) ($districtHit['lat'] ?? 0);
                $districtLng = (float) ($districtHit['lng'] ?? 0);
                $districtInBounds = realpriceBoundsContains($bounds, $districtLat, $districtLng);
                $hitPrecision = 'district';
                if ($districtInBounds) {
                    $districtHitCount++;
                }
            } elseif ($cityInBounds && $itemLimit > 0) {
                $missingGeocodeCount++;
            }
        }

        if ($buildDistrictClusters) {
            $summaryKey = $cityName . '|' . $districtName;
            if (!isset($districtClusterGroups[$summaryKey])) {
                $districtClusterGroups[$summaryKey] = [
                    'id' => 'realprice-district-cluster-' . sha1($summaryKey . $type),
                    'kind' => 'realprice-district-cluster',
                    'city' => $cityName,
                    'district' => $districtName,
                    'city_code' => $cityCode,
                    'type' => $type,
                    'count' => 0,
                    'total_price_sum' => 0,
                    'unit_price_ping_sum' => 0,
                    'unit_price_count' => 0,
                    'lat_sum' => 0.0,
                    'lng_sum' => 0.0,
                    'coord_count' => 0,
                    'district_lat' => $districtLat,
                    'district_lng' => $districtLng,
                    'city_lat' => $lat,
                    'city_lng' => $lng,
                    'latest_date' => '',
                    'samples' => [],
                ];
            }
            $dg =& $districtClusterGroups[$summaryKey];
            $dg['count']++;
            $dg['total_price_sum'] += (int) ($record['total_price'] ?? 0);
            $unitPingD = (int) ($record['unit_price_ping'] ?? 0);
            if ($unitPingD > 0) {
                $dg['unit_price_ping_sum'] += $unitPingD;
                $dg['unit_price_count']++;
            }
            if ($hit && $hitPrecision !== 'district' && $itemLat != 0.0 && $itemLng != 0.0) {
                $dg['lat_sum'] += $itemLat;
                $dg['lng_sum'] += $itemLng;
                $dg['coord_count']++;
            } elseif ($districtLat != 0.0 && $districtLng != 0.0) {
                $dg['district_lat'] = $districtLat;
                $dg['district_lng'] = $districtLng;
            }
            $dateD = (string) ($record['date'] ?? '');
            if ($dateD > $dg['latest_date']) {
                $dg['latest_date'] = $dateD;
            }
            if (count($dg['samples']) < 5) {
                $dg['samples'][] = [
                    'district' => $districtName,
                    'date' => $dateD,
                    'target' => (string) ($record['target'] ?? ''),
                    'building_type' => (string) ($record['building_type'] ?? ''),
                    'total_price' => (int) ($record['total_price'] ?? 0),
                    'unit_price_ping' => $unitPingD,
                ];
            }
            unset($dg);
            // Mid-zoom: skip city bubble accumulation; district bubbles are filtered later by position.
            continue;
        }

        $useDetailScope = $itemLimit > 0;
        $recordInBounds = $useDetailScope ? ($itemInBounds || $districtInBounds || $cityInBounds) : $cityInBounds;
        if (!$recordInBounds) continue;

        if ($itemInBounds && $hitPrecision !== 'district' && count($addressItems) < $itemLimit) {
            $addressItems[] = [
                'id' => (string) ($record['id'] ?? sha1($address . ($record['date'] ?? ''))),
                'kind' => 'realprice-item',
                'city' => $cityName,
                'district' => $districtName,
                'lat' => $itemLat,
                'lng' => $itemLng,
                'type' => $type,
                'date' => (string) ($record['date'] ?? ''),
                'target' => (string) ($record['target'] ?? ''),
                'address' => $address,
                'building_type' => (string) ($record['building_type'] ?? ''),
                'floor' => (string) ($record['floor'] ?? ''),
                'total_floors' => (string) ($record['total_floors'] ?? ''),
                'total_price' => (int) ($record['total_price'] ?? 0),
                'unit_price_ping' => (int) ($record['unit_price_ping'] ?? 0),
                'area_sqm' => (float) ($record['area_sqm'] ?? 0),
                'area_ping' => round(((float) ($record['area_sqm'] ?? 0)) / 3.305785, 2),
                'precision' => (string) ($hit['precision'] ?? 'unknown'),
            ];
        } elseif ($districtInBounds && !$itemInBounds) {
            $summaryKey = $cityName . '|' . $districtName;
            if (!isset($districtSummaries[$summaryKey])) {
                $districtSummaries[$summaryKey] = [
                    'id' => 'realprice-district-' . sha1($summaryKey . $type),
                    'kind' => 'realprice-district',
                    'city' => $cityName,
                    'district' => $districtName,
                    'lat' => $districtLat,
                    'lng' => $districtLng,
                    'type' => $type,
                    'count' => 0,
                    'total_price_sum' => 0,
                    'unit_price_ping_sum' => 0,
                    'unit_price_count' => 0,
                    'precision' => 'district',
                ];
            }
            $districtSummaries[$summaryKey]['count']++;
            $districtSummaries[$summaryKey]['total_price_sum'] += (int) ($record['total_price'] ?? 0);
            $unitPingSummary = (int) ($record['unit_price_ping'] ?? 0);
            if ($unitPingSummary > 0) {
                $districtSummaries[$summaryKey]['unit_price_ping_sum'] += $unitPingSummary;
                $districtSummaries[$summaryKey]['unit_price_count']++;
            }
        }

        $key = $cityName;
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'id' => 'realprice-' . $cityCode . '-' . $type,
                'kind' => 'realprice',
                'city' => $key,
                'city_code' => $cityCode,
                'lat' => $lat,
                'lng' => $lng,
                'type' => $type,
                'count' => 0,
                'total_price_sum' => 0,
                'unit_price_ping_sum' => 0,
                'unit_price_count' => 0,
                'located_count' => 0,
                'address_count' => 0,
                'district_count' => 0,
                'latest_date' => '',
                'districts' => [],
                'samples' => [],
            ];
        }
        $group =& $groups[$key];
        $group['count']++;
        $group['total_price_sum'] += (int) ($record['total_price'] ?? 0);
        $unitPing = (int) ($record['unit_price_ping'] ?? 0);
        if ($unitPing > 0) {
            $group['unit_price_ping_sum'] += $unitPing;
            $group['unit_price_count']++;
        }
        if ($itemInBounds) {
            $group['located_count']++;
            $group['address_count']++;
        } elseif ($districtInBounds) {
            $group['located_count']++;
            $group['district_count']++;
        }
        $date = (string) ($record['date'] ?? '');
        if ($date > $group['latest_date']) {
            $group['latest_date'] = $date;
        }
        if (!isset($group['districts'][$districtName])) {
            $group['districts'][$districtName] = ['name' => $districtName, 'count' => 0, 'total_price_sum' => 0, 'unit_price_ping_sum' => 0, 'unit_price_count' => 0];
        }
        $group['districts'][$districtName]['count']++;
        $group['districts'][$districtName]['total_price_sum'] += (int) ($record['total_price'] ?? 0);
        if ($unitPing > 0) {
            $group['districts'][$districtName]['unit_price_ping_sum'] += $unitPing;
            $group['districts'][$districtName]['unit_price_count']++;
        }
        if (count($group['samples']) < 5) {
            $group['samples'][] = [
                'district' => $districtName,
                'date' => $date,
                'target' => (string) ($record['target'] ?? ''),
                'building_type' => (string) ($record['building_type'] ?? ''),
                'total_price' => (int) ($record['total_price'] ?? 0),
                'unit_price_ping' => $unitPing,
            ];
        }
        unset($group);
    }

    $items = array_slice($addressItems, 0, $itemLimit);
    $districtSummaryItems = array_values(array_map(static function (array $summary): array {
        return [
            'id' => $summary['id'],
            'kind' => 'realprice-district',
            'city' => $summary['city'],
            'district' => $summary['district'],
            'lat' => $summary['lat'],
            'lng' => $summary['lng'],
            'type' => $summary['type'],
            'count' => $summary['count'],
            'avg_total_price' => $summary['count'] > 0 ? (int) round($summary['total_price_sum'] / $summary['count']) : 0,
            'avg_unit_price_ping' => $summary['unit_price_count'] > 0 ? (int) round($summary['unit_price_ping_sum'] / $summary['unit_price_count']) : 0,
            'precision' => 'district',
        ];
    }, $districtSummaries));
    usort($districtSummaryItems, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

    $districtClusters = [];
    foreach ($districtClusterGroups as $summaryKey => $group) {
        $clusterLat = 0.0;
        $clusterLng = 0.0;
        $positionSource = 'city_offset';
        if ((int) ($group['coord_count'] ?? 0) > 0) {
            $clusterLat = $group['lat_sum'] / $group['coord_count'];
            $clusterLng = $group['lng_sum'] / $group['coord_count'];
            $positionSource = 'address_avg';
        } elseif ((float) ($group['district_lat'] ?? 0) != 0.0 && (float) ($group['district_lng'] ?? 0) != 0.0) {
            $clusterLat = (float) $group['district_lat'];
            $clusterLng = (float) $group['district_lng'];
            $positionSource = 'district_cache';
        } else {
            $seed = abs(crc32($summaryKey));
            $clusterLat = (float) $group['city_lat'] + ((($seed % 17) - 8) * 0.012);
            $clusterLng = (float) $group['city_lng'] + ((((int) ($seed / 17) % 17) - 8) * 0.012);
            $positionSource = 'city_offset';
        }
        if (!realpriceBoundsContains($bounds, $clusterLat, $clusterLng)) {
            continue;
        }
        $districtClusters[] = [
            'id' => $group['id'],
            'kind' => 'realprice-district-cluster',
            'city' => $group['city'],
            'district' => $group['district'],
            'city_code' => $group['city_code'],
            'lat' => round($clusterLat, 7),
            'lng' => round($clusterLng, 7),
            'type' => $group['type'],
            'count' => (int) $group['count'],
            'avg_total_price' => $group['count'] > 0 ? (int) round($group['total_price_sum'] / $group['count']) : 0,
            'avg_unit_price_ping' => $group['unit_price_count'] > 0 ? (int) round($group['unit_price_ping_sum'] / $group['unit_price_count']) : 0,
            'latest_date' => $group['latest_date'],
            'position_source' => $positionSource,
            'samples' => $group['samples'],
        ];
    }
    usort($districtClusters, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

    $clusters = array_values(array_map(static function (array $group): array {
        $districts = array_values(array_map(static function (array $district): array {
            return [
                'name' => $district['name'],
                'count' => $district['count'],
                'avg_total_price' => $district['count'] > 0 ? (int) round($district['total_price_sum'] / $district['count']) : 0,
                'avg_unit_price_ping' => $district['unit_price_count'] > 0 ? (int) round($district['unit_price_ping_sum'] / $district['unit_price_count']) : 0,
            ];
        }, $group['districts']));
        usort($districts, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        return [
            'id' => $group['id'],
            'kind' => 'realprice',
            'city' => $group['city'],
            'city_code' => $group['city_code'],
            'lat' => $group['lat'],
            'lng' => $group['lng'],
            'type' => $group['type'],
            'count' => $group['count'],
            'avg_total_price' => $group['count'] > 0 ? (int) round($group['total_price_sum'] / $group['count']) : 0,
            'avg_unit_price_ping' => $group['unit_price_count'] > 0 ? (int) round($group['unit_price_ping_sum'] / $group['unit_price_count']) : 0,
            'located_count' => (int) ($group['located_count'] ?? 0),
            'address_count' => (int) ($group['address_count'] ?? 0),
            'district_count' => (int) ($group['district_count'] ?? 0),
            'latest_date' => $group['latest_date'],
            'districts' => array_slice($districts, 0, 8),
            'samples' => $group['samples'],
        ];
    }, $groups));

    usort($clusters, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

    $filteredCount = $clusterMode === 'district'
        ? array_sum(array_map(static fn (array $item): int => (int) $item['count'], $districtClusters))
        : array_sum(array_map(static fn (array $item): int => (int) $item['count'], $clusters));

    return [
        'clusters' => $clusterMode === 'city' ? $clusters : [],
        'district_clusters' => $districtClusters,
        'items' => $items,
        'district_summaries' => $districtSummaryItems,
        'item_count' => count($items),
        'item_limited' => $itemLimit > 0 && count($items) >= $itemLimit,
        'address_item_count' => count($items),
        'district_item_count' => count($districtSummaryItems),
        'district_cluster_count' => count($districtClusters),
        'address_hit_count' => $addressHitCount,
        'district_hit_count' => $districtHitCount,
        'missing_geocode_count' => $missingGeocodeCount,
        'filtered_count' => $filteredCount,
        'coarse_count' => $coarseCount,
        'period' => $period,
        'type' => $type,
        'since' => $since,
        'zoom' => $zoom,
        'cluster_mode' => $clusterMode,
        'item_zoom_min' => $itemZoomMin,
        'item_full_zoom_min' => $itemFullZoomMin,
        'district_cluster_zoom_min' => $districtClusterZoomMin,
        'cluster_hide_zoom_min' => $districtClusterZoomMin,
    ];
}

function realpriceTypeLabel(string $type): string
{
    return [
        'sale' => '買賣',
        'presale' => '預售屋',
        'rent' => '租賃',
    ][$type] ?? $type;
}

function realpriceGeocodeForRecord(array $record, array $geocoded): array
{
    $override = is_array($record['_override'] ?? null) ? $record['_override'] : [];
    if (($override['lat'] ?? '') !== '' && ($override['lng'] ?? '') !== '') {
        return [
            'status' => 'done',
            'status_label' => '手動座標',
            'match' => 'manual',
            'lat' => (string) $override['lat'],
            'lng' => (string) $override['lng'],
            'precision' => 'manual',
            'provider' => 'local_override',
            'query' => '手動修正',
            'display_name' => (string) ($override['note'] ?? ''),
            'updated_at' => (string) ($override['updated_at'] ?? ''),
        ];
    }
    $address = (string) ($record['address'] ?? '');
    $addressKey = realpriceGeocodeKey($address);
    $districtKey = realpriceGeocodeScopedKey('district', (string) ($record['city'] ?? ''), (string) ($record['district'] ?? ''));
    $hit = is_array($geocoded[$addressKey] ?? null) ? $geocoded[$addressKey] : null;
    $match = $hit ? 'address' : '';
    if (!$hit && is_array($geocoded[$districtKey] ?? null)) {
        $hit = $geocoded[$districtKey];
        $match = 'district';
    }
    if (!$hit) {
        return [
            'status' => 'missing',
            'status_label' => '未定位',
            'match' => '',
            'lat' => '',
            'lng' => '',
            'precision' => '',
            'provider' => '',
            'query' => '',
            'display_name' => '',
            'updated_at' => '',
        ];
    }
    $precision = realpriceGeocodeHitPrecision($hit, (string) ($record['city'] ?? ''), (string) ($record['district'] ?? ''));
    return [
        'status' => $precision === 'district' || $match === 'district' ? 'approx' : 'done',
        'status_label' => $precision === 'district' || $match === 'district' ? '行政區近似' : '已定位',
        'match' => $match,
        'lat' => (string) ($hit['lat'] ?? ''),
        'lng' => (string) ($hit['lng'] ?? ''),
        'precision' => $precision,
        'provider' => (string) ($hit['provider'] ?? ''),
        'query' => (string) ($hit['query'] ?? ''),
        'display_name' => (string) ($hit['display_name'] ?? ''),
        'updated_at' => (string) ($hit['updated_at'] ?? ''),
    ];
}

function realpriceTextContains(string $haystack, string $needle): bool
{
    if ($needle === '') return true;
    if (function_exists('mb_stripos')) {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }
    return stripos($haystack, $needle) !== false;
}

function realpriceSearchRecords(array $params = []): array
{
    $index = realpriceLoadIndex();
    $overrides = realpriceLoadOverrides();
    $recordOverrides = is_array($overrides['records'] ?? null) ? $overrides['records'] : [];
    $customRecords = is_array($overrides['custom_records'] ?? null) ? $overrides['custom_records'] : [];
    $cache = realpriceLoadGeocodeCache();
    $geocoded = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $keyword = trim((string) ($params['keyword'] ?? ''));
    $city = trim((string) ($params['city'] ?? ''));
    $type = trim((string) ($params['type'] ?? ''));
    $status = trim((string) ($params['status'] ?? ''));
    $limit = max(20, min(200, (int) ($params['limit'] ?? 50)));
    $page = max(1, (int) ($params['page'] ?? 1));
    $offset = ($page - 1) * $limit;
    $rows = [];
    $matched = 0;
    $scanned = 0;
    $counts = ['done' => 0, 'approx' => 0, 'missing' => 0];
    $recordCount = (int) ($index['record_count'] ?? 0);
    $hasMore = false;
    $totalKnown = false;
    $corrupt = false;
    $error = '';

    // 無關鍵字／定位狀態時，可用 SQL 估總筆數做分頁（快）。
    if ($keyword === '' && $status === '') {
        try {
            $sqlTotal = realpriceCountRecordsFast($type !== '' ? $type : null, $city !== '' ? $city : null);
            if ($sqlTotal !== null) {
                $customExtra = 0;
                foreach ($customRecords as $record) {
                    if (!is_array($record) || !empty($record['hidden'])) {
                        continue;
                    }
                    if ($type !== '' && (string) ($record['type'] ?? '') !== $type) {
                        continue;
                    }
                    if ($city !== '' && (string) ($record['city'] ?? '') !== $city) {
                        continue;
                    }
                    $customExtra++;
                }
                $matched = $sqlTotal + $customExtra;
                $totalKnown = true;
            }
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'malformed') !== false || stripos($e->getMessage(), 'disk image') !== false) {
                $corrupt = true;
                $error = '實價登錄索引資料庫已損壞（database disk image is malformed）。請到上方按「用本機季度 ZIP 重建索引」。';
            }
        }
    }

    if ($corrupt) {
        return [
            'rows' => [],
            'matched_count' => 0,
            'returned_count' => 0,
            'scanned_count' => 0,
            'limit' => $limit,
            'page' => $page,
            'page_count' => 1,
            'has_more' => false,
            'total_known' => false,
            'counts' => $counts,
            'generated_at' => (string) ($index['generated_at'] ?? ''),
            'record_count' => $recordCount,
            'cache_count' => count($geocoded),
            'error' => $error,
        ];
    }

    $recordSource = static function () use ($type, $city, $recordOverrides, $customRecords): Generator {
        $cityFilter = $city !== '' ? [$city] : null;
        foreach (realpriceIterateRecords($type !== '' ? $type : null, null, $cityFilter) as $record) {
            if (!is_array($record)) {
                continue;
            }
            $key = realpriceRecordKey($record);
            $override = is_array($recordOverrides[$key] ?? null) ? $recordOverrides[$key] : [];
            if (!empty($override['hidden'])) {
                continue;
            }
            if ($override !== []) {
                $record['_override'] = $override;
            }
            yield $record;
        }
        foreach ($customRecords as $record) {
            if (!is_array($record) || !empty($record['hidden'])) {
                continue;
            }
            if ($type !== '' && (string) ($record['type'] ?? '') !== $type) {
                continue;
            }
            if ($city !== '' && (string) ($record['city'] ?? '') !== $city) {
                continue;
            }
            $record['source_file'] = (string) ($record['source_file'] ?? 'local_override');
            $record['_custom'] = true;
            yield $record;
        }
    };

    try {
        $seenForPage = 0;
        foreach ($recordSource() as $record) {
            if (!is_array($record)) {
                continue;
            }
            $scanned++;
            if ($city !== '' && (string) ($record['city'] ?? '') !== $city) {
                continue;
            }
            if ($type !== '' && (string) ($record['type'] ?? '') !== $type) {
                continue;
            }
            if ($keyword !== '') {
                $haystack = implode(' ', [
                    (string) ($record['city'] ?? ''),
                    (string) ($record['district'] ?? ''),
                    (string) ($record['address'] ?? ''),
                    (string) ($record['target'] ?? ''),
                    (string) ($record['building_type'] ?? ''),
                    (string) ($record['id'] ?? ''),
                ]);
                if (!realpriceTextContains($haystack, $keyword)) {
                    continue;
                }
            }
            $geo = realpriceGeocodeForRecord($record, $geocoded);
            $geoStatus = (string) $geo['status'];
            if (isset($counts[$geoStatus])) {
                $counts[$geoStatus]++;
            }
            if ($status !== '' && $geoStatus !== $status) {
                continue;
            }

            if (!$totalKnown) {
                $matched++;
            } else {
                // 總數已知時，只負責挑本頁列
            }

            $seenForPage++;
            if ($seenForPage <= $offset) {
                continue;
            }
            if (count($rows) < $limit) {
                $rows[] = [
                    'record' => $record,
                    'geocode' => $geo,
                ];
                continue;
            }
            $hasMore = true;
            // 有狀態／關鍵字時仍需掃完才能得到準確總數；否則可提早結束。
            if ($totalKnown || ($status === '' && $keyword === '')) {
                break;
            }
        }
        if (!$totalKnown && $hasMore && $status === '' && $keyword === '') {
            // 未做完整計數：至少顯示「目前頁 + 可能還有」
            $matched = max($matched, $offset + count($rows) + 1);
        }
    } catch (Throwable $e) {
        if (stripos($e->getMessage(), 'malformed') !== false || stripos($e->getMessage(), 'disk image') !== false) {
            $error = '實價登錄索引資料庫已損壞。請重建索引後再查詢明細。';
            $rows = [];
            $matched = 0;
        } else {
            throw $e;
        }
    }

    if ($totalKnown) {
        $pageCount = max(1, (int) ceil($matched / $limit));
        if ($page > $pageCount) {
            $page = $pageCount;
        }
        $hasMore = $page < $pageCount;
    } else {
        $pageCount = $hasMore ? ($page + 1) : max(1, $page);
    }

    return [
        'rows' => $rows,
        'matched_count' => $matched,
        'returned_count' => count($rows),
        'scanned_count' => $scanned,
        'limit' => $limit,
        'page' => $page,
        'page_count' => $pageCount,
        'has_more' => $hasMore,
        'total_known' => $totalKnown,
        'counts' => $counts,
        'generated_at' => (string) ($index['generated_at'] ?? ''),
        'record_count' => $recordCount > 0 ? $recordCount : $scanned,
        'cache_count' => count($geocoded),
        'error' => $error,
    ];
}

/**
 * Fast COUNT for pagination when filters are simple (type/city only).
 */
function realpriceCountRecordsFast(?string $type = null, ?string $city = null): ?int
{
    $pdo = realpriceOpenSqliteReadonly();
    if (!$pdo instanceof PDO) {
        return null;
    }
    $sql = 'SELECT COUNT(*) FROM records WHERE 1=1';
    $params = [];
    if ($type !== null && $type !== '') {
        $sql .= ' AND type = ?';
        $params[] = $type;
    }
    if ($city !== null && $city !== '') {
        $sql .= ' AND city = ?';
        $params[] = $city;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function realpriceHumanSize(int $bytes): string
{
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
