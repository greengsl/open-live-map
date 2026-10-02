<?php
declare(strict_types=1);

/**
 * 縣市門牌開放資料下載與本機比對定位。
 * 來源為各地方政府門牌位置 CSV（TWD97 / 部分含 WGS84），授權多為政府資料開放授權條款。
 */

require_once __DIR__ . '/realprice.php';

function doorplateDir(): string
{
    $dir = realpriceDir() . '/doorplate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function doorplateMetaPath(): string
{
    return doorplateDir() . '/sources-meta.json';
}

function doorplateSources(): array
{
    // 實價登錄涵蓋全國縣市；門牌開放資料各縣進度不一。
    // url 有值且非 manual_only 才能「一鍵下載」；其餘請手動上傳／複製到 {key}.csv。
    return [
        'taipei' => [
            'label' => '臺北市',
            'city_aliases' => ['台北市', '臺北市'],
            'dataset' => 'https://data.gov.tw/dataset/155472',
            'url' => 'https://data.taipei/api/dataset/b7c8e724-1e98-45ee-a0bd-f3840623ed97/resource/ce76ca0c-7f94-4935-ab47-1d2a41ca2abb/download',
            'coord' => 'twd97',
            'note' => '可自動下載',
        ],
        'newtaipei' => [
            'label' => '新北市',
            'city_aliases' => ['新北市'],
            'dataset' => 'https://data.gov.tw/dataset/168887',
            'url' => 'https://data.ntpc.gov.tw/api/datasets/d7b568ab-3819-40c8-a6e7-a6b199443101/csv/file',
            'coord' => 'twd97',
            'note' => '可自動下載',
        ],
        'taoyuan' => [
            'label' => '桃園市',
            'city_aliases' => ['桃園市'],
            'dataset' => 'https://opendata.tycg.gov.tw/datalist/ec47dbd5-9ed8-4c8d-8ce1-ccb63b1b72e6',
            'url' => '',
            'coord' => 'twd97',
            'note' => '需登入開放平台；手動上傳 taoyuan.csv',
            'manual_only' => true,
        ],
        'taichung' => [
            'label' => '臺中市',
            'city_aliases' => ['台中市', '臺中市'],
            'dataset' => 'https://data.gov.tw/dataset/177460',
            // 115年8月（最新）；Google Drive 大檔需 confirm 下載流程
            'url' => 'https://drive.google.com/file/d/1oCjMy_eccQHv7R-VUEBZiC9gtJZ18GcV/view?usp=sharing',
            'google_drive_id' => '1oCjMy_eccQHv7R-VUEBZiC9gtJZ18GcV',
            'coord' => 'wgs84',
            'note' => '可自動下載（Google Drive 月檔，約 150MB；115年8月）',
            'catalog' => [
                ['label' => '115年1月', 'id' => '1oxPMFv5twHRSkK6BtlHD-8t2qfwGF1R9'],
                ['label' => '115年2月', 'id' => '1J2eMhQPuorZrRdvp1tbwX4d4DNEL-IxR'],
                ['label' => '115年3月', 'id' => '1oZLx9gWFGd7VTaPa9P14Zzm2lNWygTYL'],
                ['label' => '115年4月', 'id' => '1vIEQ0vTlONnOH5yWIxsyyymEtlwitTnZ'],
                ['label' => '115年5月', 'id' => '1tki-DhjoP1ZRB4U2-r6pcVnEGQBVeAlL'],
                ['label' => '115年6月', 'id' => '19Vqa2TeDxUKBiRFaii4qDlew6d7G-mjm'],
                ['label' => '115年7月', 'id' => '1U5NXtrz5clJUFef1auZNUH3Zc9c45NqD'],
                ['label' => '115年8月', 'id' => '1oCjMy_eccQHv7R-VUEBZiC9gtJZ18GcV'],
            ],
        ],
        'tainan' => [
            'label' => '臺南市',
            'city_aliases' => ['台南市', '臺南市'],
            'dataset' => 'https://data.gov.tw/dataset/120044',
            'url' => 'https://data.tainan.gov.tw/File/ResourceCsvDownload/af44f904-2f4c-49b2-aaf8-1a64dce09bd4',
            'coord' => 'twd97',
            'note' => '可自動下載',
        ],
        'kaohsiung' => [
            'label' => '高雄市',
            'city_aliases' => ['高雄市'],
            'dataset' => 'https://data.kcg.gov.tw/',
            'url' => '',
            'coord' => 'twd97',
            'note' => '手動上傳 kaohsiung.csv',
            'manual_only' => true,
        ],
        'keelung' => [
            'label' => '基隆市',
            'city_aliases' => ['基隆市'],
            'dataset' => 'https://data.gov.tw/',
            'url' => '',
            'coord' => 'twd97',
            'note' => '尚未見穩定公開 CSV；有檔可手動上傳 keelung.csv',
            'manual_only' => true,
        ],
        'hsinchu_city' => [
            'label' => '新竹市',
            'city_aliases' => ['新竹市'],
            'dataset' => 'https://data.gov.tw/dataset/157547',
            'url' => '',
            'coord' => 'twd97',
            'note' => '資料集多分區 CSV；合併後上傳 hsinchu_city.csv',
            'manual_only' => true,
        ],
        'hsinchu_county' => [
            'label' => '新竹縣',
            'city_aliases' => ['新竹縣'],
            'dataset' => 'https://data.gov.tw/dataset/172380',
            'url' => '',
            'coord' => 'twd97',
            'note' => 'data.gov.tw 可下載；手動上傳 hsinchu_county.csv',
            'manual_only' => true,
        ],
        'miaoli' => [
            'label' => '苗栗縣',
            'city_aliases' => ['苗栗縣'],
            'dataset' => 'https://data.gov.tw/dataset/178083',
            'url' => '',
            'coord' => 'twd97',
            'note' => 'data.gov.tw 可下載（可能 BIG5）；手動上傳 miaoli.csv',
            'manual_only' => true,
        ],
        'changhua' => [
            'label' => '彰化縣',
            'city_aliases' => ['彰化縣'],
            'dataset' => 'https://data.gov.tw/dataset/170727',
            'url' => '',
            'coord' => 'twd97',
            'note' => 'data.gov.tw 可下載；手動上傳 changhua.csv',
            'manual_only' => true,
        ],
        'nantou' => [
            'label' => '南投縣',
            'city_aliases' => ['南投縣'],
            'dataset' => 'https://data.gov.tw/',
            'url' => '',
            'coord' => 'twd97',
            'note' => '開放進度不一；有檔可手動上傳 nantou.csv',
            'manual_only' => true,
        ],
        'yunlin' => [
            'label' => '雲林縣',
            'city_aliases' => ['雲林縣'],
            'dataset' => 'https://data.gov.tw/dataset/166201',
            'url' => '',
            'coord' => 'twd97',
            'note' => '多分檔 XLSX/JSON；轉成單一 CSV 後上傳 yunlin.csv',
            'manual_only' => true,
        ],
        'chiayi_city' => [
            'label' => '嘉義市',
            'city_aliases' => ['嘉義市'],
            'dataset' => 'https://data.gov.tw/',
            'url' => '',
            'coord' => 'twd97',
            'note' => '開放進度不一；有檔可手動上傳 chiayi_city.csv',
            'manual_only' => true,
        ],
        'chiayi_county' => [
            'label' => '嘉義縣',
            'city_aliases' => ['嘉義縣'],
            'dataset' => 'https://data.gov.tw/dataset/172873',
            'url' => '',
            'coord' => 'twd97',
            'note' => 'data.gov.tw 可下載；手動上傳 chiayi_county.csv',
            'manual_only' => true,
        ],
        'pingtung' => [
            'label' => '屏東縣',
            'city_aliases' => ['屏東縣'],
            'dataset' => 'https://data.gov.tw/dataset/170847',
            'url' => '',
            'coord' => 'twd97',
            'note' => 'data.gov.tw 可下載；手動上傳 pingtung.csv',
            'manual_only' => true,
        ],
        'yilan' => [
            'label' => '宜蘭縣',
            'city_aliases' => ['宜蘭縣'],
            'dataset' => 'https://data.gov.tw/',
            'url' => '',
            'coord' => 'twd97',
            'note' => '開放進度不一；有檔可手動上傳 yilan.csv',
            'manual_only' => true,
        ],
        'hualien' => [
            'label' => '花蓮縣',
            'city_aliases' => ['花蓮縣'],
            'dataset' => 'https://data.gov.tw/dataset/175221',
            'url' => '',
            'coord' => 'twd97',
            'note' => 'data.gov.tw 可下載；手動上傳 hualien.csv',
            'manual_only' => true,
        ],
        'taitung' => [
            'label' => '臺東縣',
            'city_aliases' => ['台東縣', '臺東縣'],
            'dataset' => 'https://data.gov.tw/dataset/165619',
            'url' => '',
            'coord' => 'twd97',
            'note' => 'data.gov.tw 可下載；手動上傳 taitung.csv',
            'manual_only' => true,
        ],
        'penghu' => [
            'label' => '澎湖縣',
            'city_aliases' => ['澎湖縣'],
            'dataset' => 'https://data.gov.tw/dataset/170852',
            'url' => 'https://opendata.penghu.gov.tw/dataset/302f94b3-89f9-4877-969a-bd931df82cb3/resource/12be535a-83de-43ee-8922-fea1549bdd4e/download/u600000-03-2024-09-26-1727342410.csv',
            'coord' => 'twd97',
            'note' => '可自動下載',
        ],
        'kinmen' => [
            'label' => '金門縣',
            'city_aliases' => ['金門縣'],
            'dataset' => 'https://data.gov.tw/dataset/171571',
            'url' => '',
            'coord' => 'twd97',
            'note' => '多為 XLSX/JSON；轉 CSV 後上傳 kinmen.csv',
            'manual_only' => true,
        ],
        'lienchiang' => [
            'label' => '連江縣',
            'city_aliases' => ['連江縣', '馬祖'],
            'dataset' => 'https://data.gov.tw/',
            'url' => '',
            'coord' => 'twd97',
            'note' => '開放進度不一；有檔可手動上傳 lienchiang.csv',
            'manual_only' => true,
        ],
    ];
}

function doorplateCsvPath(string $cityKey): string
{
    return doorplateDir() . '/' . preg_replace('/[^a-z0-9_]/', '', $cityKey) . '.csv';
}

function doorplateLoadMeta(): array
{
    return realpriceLoadJsonFile(doorplateMetaPath(), ['cities' => []]);
}

function doorplateSaveMeta(array $meta): void
{
    $meta['updated_at'] = date(DATE_ATOM);
    realpriceSaveJsonFile(doorplateMetaPath(), $meta);
}

function doorplateStatus(): array
{
    $sources = doorplateSources();
    $meta = doorplateLoadMeta();
    $cities = [];
    foreach ($sources as $key => $source) {
        $path = doorplateCsvPath($key);
        $cityMeta = is_array($meta['cities'][$key] ?? null) ? $meta['cities'][$key] : [];
        $cities[$key] = [
            'key' => $key,
            'label' => $source['label'],
            'dataset' => $source['dataset'],
            'manual_only' => (bool) ($source['manual_only'] ?? false),
            'note' => (string) ($source['note'] ?? ''),
            'file_exists' => is_file($path),
            'file_size' => is_file($path) ? (int) filesize($path) : 0,
            'downloaded_at' => (string) ($cityMeta['downloaded_at'] ?? ''),
            'matched_at' => (string) ($cityMeta['matched_at'] ?? ''),
            'matched_count' => (int) ($cityMeta['matched_count'] ?? 0),
            'scanned_rows' => (int) ($cityMeta['scanned_rows'] ?? 0),
        ];
    }
    $job = realpriceLoadJsonFile(doorplateMatchStatePath(), []);
    return [
        'dir' => doorplateDir(),
        'cities' => $cities,
        'last_message' => (string) ($meta['last_message'] ?? ''),
        'last_match_at' => (string) ($meta['last_match_at'] ?? ''),
        'last_match_written' => (int) ($meta['last_match_written'] ?? 0),
        'job' => [
            'status' => (string) ($job['status'] ?? ''),
            'phase' => (string) ($job['phase'] ?? ''),
            'message' => (string) ($job['message'] ?? ''),
            'total_matched' => (int) ($job['total_matched'] ?? 0),
            'total_scanned' => (int) ($job['total_scanned'] ?? 0),
            'needed_before' => (int) ($job['needed_before'] ?? 0),
            'remaining_keys' => (int) ($job['remaining_keys'] ?? 0),
            'file_pct' => (int) ($job['file_pct'] ?? 0),
            'city_index' => (int) ($job['city_index'] ?? 0),
            'cities' => is_array($job['cities'] ?? null) ? array_values($job['cities']) : [],
            'current_city' => (string) ($job['current_city'] ?? ''),
            'current_label' => (string) ($job['current_label'] ?? ''),
            'busy' => !empty($job['busy']),
            'busy_until' => (string) ($job['busy_until'] ?? ''),
            'updated_at' => (string) ($job['updated_at'] ?? ''),
        ],
    ];
}

/**
 * TWD97 TM2 (zone 121) → WGS84 lon/lat.
 */
function doorplateTwd97ToWgs84(float $x, float $y): array
{
    $a = 6378137.0;
    $f = 1 / 298.257222101;
    $k0 = 0.9999;
    $dx = 250000.0;
    $lon0 = deg2rad(121.0);
    $dy = 0.0;

    $e2 = 2 * $f - $f * $f;
    $e = sqrt($e2);
    $x -= $dx;
    $y -= $dy;
    $m = $y / $k0;
    $mu = $m / ($a * (1 - $e2 / 4 - 3 * $e2 * $e2 / 64 - 5 * pow($e2, 3) / 256));
    $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));

    $j1 = 3 * $e1 / 2 - 27 * pow($e1, 3) / 32;
    $j2 = 21 * $e1 * $e1 / 16 - 55 * pow($e1, 4) / 32;
    $j3 = 151 * pow($e1, 3) / 96;
    $j4 = 1097 * pow($e1, 4) / 512;
    $fp = $mu + $j1 * sin(2 * $mu) + $j2 * sin(4 * $mu) + $j3 * sin(6 * $mu) + $j4 * sin(8 * $mu);

    $e_sin = sin($fp);
    $e_cos = cos($fp);
    $e_tan = tan($fp);
    $eg = $e2 / (1 - $e2);
    $c1 = $eg * $e_cos * $e_cos;
    $t1 = $e_tan * $e_tan;
    $n1 = $a / sqrt(1 - $e2 * $e_sin * $e_sin);
    $r1 = $a * (1 - $e2) / pow(1 - $e2 * $e_sin * $e_sin, 1.5);
    $d = $x / ($n1 * $k0);

    $q1 = $n1 * $e_tan / $r1;
    $q2 = $d * $d / 2;
    $q3 = (5 + 3 * $t1 + 10 * $c1 - 4 * $c1 * $c1 - 9 * $eg) * pow($d, 4) / 24;
    $q4 = (61 + 90 * $t1 + 298 * $c1 + 45 * $t1 * $t1 - 3 * $c1 * $c1 - 252 * $eg) * pow($d, 6) / 720;
    $lat = $fp - $q1 * ($q2 - $q3 + $q4);

    $q5 = $d;
    $q6 = (1 + 2 * $t1 + $c1) * pow($d, 3) / 6;
    $q7 = (5 - 2 * $c1 + 28 * $t1 - 3 * $c1 * $c1 + 8 * $eg + 24 * $t1 * $t1) * pow($d, 5) / 120;
    $lon = $lon0 + ($q5 - $q6 + $q7) / $e_cos;

    return [
        'lat' => rad2deg($lat),
        'lng' => rad2deg($lon),
    ];
}

function doorplateGoogleDriveFileId(string $urlOrId): string
{
    $value = trim($urlOrId);
    if ($value === '') return '';
    if (preg_match('~^[a-zA-Z0-9_-]{20,}$~', $value)) {
        return $value;
    }
    if (preg_match('~/file/d/([a-zA-Z0-9_-]+)~', $value, $m)) {
        return $m[1];
    }
    if (preg_match('~[?&]id=([a-zA-Z0-9_-]+)~', $value, $m)) {
        return $m[1];
    }
    return '';
}

function doorplateHttpDownloadRaw(string $url, string $destPath, array $extraHeaders = []): array
{
    $tmp = $destPath . '.part';
    $headers = array_merge([
        'User-Agent: Mozilla/5.0 (compatible; OpenLiveMap/1.0)',
    ], $extraHeaders);
    if (function_exists('curl_init')) {
        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            throw new RuntimeException('無法寫入門牌暫存檔。');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_COOKIEJAR => $tmp . '.cookie',
            CURLOPT_COOKIEFILE => $tmp . '.cookie',
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        @unlink($tmp . '.cookie');
        if ($ok === false || $status >= 400) {
            @unlink($tmp);
            throw new RuntimeException('下載門牌資料失敗：HTTP ' . $status . ($err !== '' ? " ({$err})" : ''));
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'timeout' => 900,
                'follow_location' => 1,
            ],
        ]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            throw new RuntimeException('下載門牌資料失敗（file_get_contents）。');
        }
        if (@file_put_contents($tmp, $data) === false) {
            throw new RuntimeException('無法寫入門牌暫存檔。');
        }
    }
    return ['tmp' => $tmp, 'size' => (int) filesize($tmp)];
}

function doorplateHttpDownload(string $url, string $destPath): array
{
    if ($url === '') {
        throw new RuntimeException('此縣市沒有可自動下載的公開 CSV 網址，請改用手動上傳。');
    }

    $driveId = doorplateGoogleDriveFileId($url);
    $downloadUrl = $url;
    if ($driveId !== '') {
        $downloadUrl = 'https://drive.google.com/uc?export=download&id=' . rawurlencode($driveId);
    }

    $raw = doorplateHttpDownloadRaw($downloadUrl, $destPath);
    $tmp = $raw['tmp'];
    $size = (int) $raw['size'];
    $head = (string) @file_get_contents($tmp, false, null, 0, 2500);

    // Google Drive large-file virus scan interstitial.
    if ($driveId !== '' && $size < 20000 && (str_contains($head, 'Virus scan warning') || str_contains($head, 'download-form') || str_contains($head, 'confirm='))) {
        $confirm = 't';
        $uuid = '';
        if (preg_match('/name="confirm"\s+value="([^"]+)"/', $head, $m)) {
            $confirm = $m[1];
        } elseif (preg_match('/confirm=([0-9A-Za-z_]+)/', $head, $m)) {
            $confirm = $m[1];
        }
        if (preg_match('/name="uuid"\s+value="([^"]+)"/', $head, $m)) {
            $uuid = $m[1];
        }
        @unlink($tmp);
        $retry = 'https://drive.usercontent.google.com/download?id=' . rawurlencode($driveId)
            . '&export=download&confirm=' . rawurlencode($confirm);
        if ($uuid !== '') {
            $retry .= '&uuid=' . rawurlencode($uuid);
        }
        $raw = doorplateHttpDownloadRaw($retry, $destPath);
        $tmp = $raw['tmp'];
        $size = (int) $raw['size'];
        $head = (string) @file_get_contents($tmp, false, null, 0, 200);
    }

    if ($size < 1000 || str_starts_with(ltrim($head), '<!DOCTYPE') || str_starts_with(ltrim($head), '<html')) {
        @unlink($tmp);
        throw new RuntimeException('下載內容不是有效 CSV（可能被 Google Drive 擋下，請改手動下載後登記）。');
    }

    if (!@rename($tmp, $destPath)) {
        @unlink($destPath);
        if (!@rename($tmp, $destPath)) {
            @unlink($tmp);
            throw new RuntimeException('無法覆寫門牌 CSV。');
        }
    }
    return ['path' => $destPath, 'size' => $size];
}

function doorplateDownloadCity(string $cityKey): array
{
    $sources = doorplateSources();
    if (!isset($sources[$cityKey])) {
        throw new InvalidArgumentException('未知縣市：' . $cityKey);
    }
    $source = $sources[$cityKey];
    $url = (string) ($source['url'] ?? '');
    if ($url === '' && !empty($source['google_drive_id'])) {
        $url = (string) $source['google_drive_id'];
    }
    if (!empty($source['manual_only']) || $url === '') {
        throw new RuntimeException(($source['label'] ?? $cityKey) . ' 需手動上傳 CSV。');
    }
    @set_time_limit(0);
    $path = doorplateCsvPath($cityKey);
    $result = doorplateHttpDownload($url, $path);
    $meta = doorplateLoadMeta();
    $meta['cities'][$cityKey] = array_merge(
        is_array($meta['cities'][$cityKey] ?? null) ? $meta['cities'][$cityKey] : [],
        [
            'downloaded_at' => date(DATE_ATOM),
            'file_size' => $result['size'],
            'source_url' => $url,
        ]
    );
    $meta['last_message'] = ($source['label'] ?? $cityKey) . ' 門牌資料已下載：' . realpriceHumanSize($result['size']);
    doorplateSaveMeta($meta);
    return doorplateStatus();
}

function doorplateNormalizeHeader(string $header): string
{
    $header = realpriceNormalizeAddress($header);
    $header = str_replace(["\xEF\xBB\xBF", ' ', '　'], '', $header);
    return mb_strtolower($header, 'UTF-8');
}

function doorplateDetectColumns(array $headers): array
{
    $map = [];
    foreach ($headers as $index => $raw) {
        $h = doorplateNormalizeHeader((string) $raw);
        if ($h === '') continue;
        if ($h === '地址' || $h === 'address' || $h === '完整地址') {
            $map['full_address'] = $index;
        } elseif (
            str_contains($h, 'streetroad')
            || str_contains($h, 'street')
            || str_contains($h, '街路')
            || str_contains($h, '路段')
            || str_contains($h, '街_路')
            || $h === '街'
            || $h === 'road'
        ) {
            $map['street'] = $index;
        } elseif ($h === 'lane' || $h === '巷') {
            $map['lane'] = $index;
        } elseif ($h === 'alley' || $h === '弄') {
            $map['alley'] = $index;
        } elseif (
            $h === 'number'
            || $h === 'housenumber'
            || $h === '號'
            || $h === '號樓'
            || $h === '门牌'
            || str_contains($h, '門牌號')
            || str_contains($h, 'housenumber')
        ) {
            $map['number'] = $index;
        } elseif ($h === 'area' || $h === '地區') {
            $map['area'] = $index;
        } elseif (
            str_contains($h, 'x_3826')
            || str_contains($h, '橫座標')
            || str_contains($h, '橫坐標')
            || str_contains($h, 'coordinatex')
            || $h === 'twd97x'
            || $h === 'x'
        ) {
            $map['x'] = $index;
        } elseif (
            str_contains($h, 'y_3826')
            || str_contains($h, '縱座標')
            || str_contains($h, '縱坐標')
            || str_contains($h, 'coordinatey')
            || $h === 'twd97y'
            || $h === 'y'
        ) {
            $map['y'] = $index;
        } elseif (str_contains($h, 'wgs84') && (str_contains($h, '經') || str_contains($h, 'lon') || str_contains($h, 'lng'))) {
            $map['lng'] = $index;
        } elseif (str_contains($h, 'wgs84') && (str_contains($h, '緯') || str_contains($h, 'lat'))) {
            $map['lat'] = $index;
        } elseif (($h === 'lng' || $h === 'lon' || $h === '經度') && !isset($map['lng'])) {
            $map['lng'] = $index;
        } elseif (($h === 'lat' || $h === '緯度') && !isset($map['lat'])) {
            $map['lat'] = $index;
        } elseif (str_contains($h, 'county') || str_contains($h, '縣市')) {
            $map['county'] = $index;
        } elseif (str_contains($h, 'areacode') || str_contains($h, '鄉鎮') || str_contains($h, '行政區域')) {
            $map['areacode'] = $index;
        }
    }
    return $map;
}

function doorplateBuildStreetNumber(array $row, array $cols): string
{
    if (isset($cols['full_address'])) {
        $full = realpriceNormalizeAddress((string) ($row[$cols['full_address']] ?? ''));
        if ($full !== '' && str_contains($full, '號')) {
            return $full;
        }
    }
    $street = realpriceNormalizeAddress((string) ($row[$cols['street'] ?? -1] ?? ''));
    $area = realpriceNormalizeAddress((string) ($row[$cols['area'] ?? -1] ?? ''));
    $lane = realpriceNormalizeAddress((string) ($row[$cols['lane'] ?? -1] ?? ''));
    $alley = realpriceNormalizeAddress((string) ($row[$cols['alley'] ?? -1] ?? ''));
    $number = realpriceNormalizeAddress((string) ($row[$cols['number'] ?? -1] ?? ''));
    // 門牌號寫法常見：
    // - 175之2號 / 91號二樓 → 取到第一個「號」
    // - 97號之1 → 正規成 97之1號（否則會被截成 97號而對不上實價）
    if (preg_match('/^(\d+(?:之\d+)*)號之(\d+)/u', $number, $m)) {
        $number = $m[1] . '之' . $m[2] . '號';
    } elseif (preg_match('/^(.+?號)/u', $number, $m)) {
        $number = $m[1];
    } else {
        $number = preg_replace('/號.*$/u', '', $number) ?? $number;
        if ($number !== '') {
            $number .= '號';
        }
    }
    $lane = preg_replace('/巷$/u', '', $lane) ?? $lane;
    $alley = preg_replace('/弄$/u', '', $alley) ?? $alley;
    if ($street === '' && $area !== '') {
        $street = $area;
        $area = '';
    }
    $parts = $street;
    if ($area !== '' && !str_contains($street, $area)) {
        $parts .= $area;
    }
    if ($lane !== '') $parts .= $lane . '巷';
    if ($alley !== '') $parts .= $alley . '弄';
    if ($number !== '') {
        $parts .= str_ends_with($number, '號') ? $number : ($number . '號');
    }
    return realpriceNormalizeAddress($parts);
}

function doorplateRowLatLng(array $row, array $cols, string $coordMode = 'twd97'): ?array
{
    if (isset($cols['lat'], $cols['lng'])) {
        $lat = (float) str_replace(',', '', (string) ($row[$cols['lat']] ?? ''));
        $lng = (float) str_replace(',', '', (string) ($row[$cols['lng']] ?? ''));
        if ($lat > 20 && $lat < 27 && $lng > 116 && $lng < 123) {
            return ['lat' => $lat, 'lng' => $lng, 'precision_source' => 'wgs84'];
        }
    }
    if (isset($cols['x'], $cols['y'])) {
        $x = (float) str_replace(',', '', (string) ($row[$cols['x']] ?? ''));
        $y = (float) str_replace(',', '', (string) ($row[$cols['y']] ?? ''));
        if ($x > 100000 && $y > 2400000) {
            $ll = doorplateTwd97ToWgs84($x, $y);
            if ($ll['lat'] > 20 && $ll['lat'] < 27 && $ll['lng'] > 116 && $ll['lng'] < 123) {
                return ['lat' => $ll['lat'], 'lng' => $ll['lng'], 'precision_source' => 'twd97'];
            }
        }
        // Some files accidentally put WGS84 into x/y-like columns.
        if ($coordMode === 'wgs84' && $y > 20 && $y < 27 && $x > 116 && $x < 123) {
            return ['lat' => $y, 'lng' => $x, 'precision_source' => 'wgs84'];
        }
    }
    return null;
}

function doorplateAddressSuffixKey(string $city, string $district, string $address): string
{
    $city = realpriceNormalizeAddress($city);
    $district = realpriceNormalizeAddress($district);
    $door = realpriceDoorAddress($address);
    $suffix = $door;
    if ($city !== '' && str_starts_with($suffix, $city)) {
        $suffix = substr($suffix, strlen($city));
    }
    if ($district !== '' && str_starts_with($suffix, $district)) {
        $suffix = substr($suffix, strlen($district));
    }
    $suffix = realpriceNormalizeAddress((string) $suffix);
    return sha1('suffix|' . $city . '|' . $suffix);
}

/**
 * Build suffix-key lookup for addresses that still need precise doorplate coords.
 *
 * @param list<string>|null $cityKeys doorplate source keys (e.g. taipei); null = all cities
 */
function doorplateBuildNeededLookup(?array $cityKeys = null): array
{
    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');

    $allowedCities = null;
    if (is_array($cityKeys) && $cityKeys !== []) {
        $allowedCities = [];
        $sources = doorplateSources();
        foreach ($cityKeys as $key) {
            $key = (string) $key;
            if (!isset($sources[$key])) {
                continue;
            }
            foreach (($sources[$key]['city_aliases'] ?? []) as $alias) {
                $normalized = realpriceNormalizeAddress((string) $alias);
                if ($normalized !== '') {
                    $allowedCities[$normalized] = true;
                }
            }
            $label = realpriceNormalizeAddress((string) ($sources[$key]['label'] ?? ''));
            if ($label !== '') {
                $allowedCities[$label] = true;
            }
        }
        if ($allowedCities === []) {
            $allowedCities = null;
        }
    }

    $cache = realpriceLoadGeocodeCache();
    $cached = is_array($cache['items'] ?? null) ? $cache['items'] : [];
    $needed = [];
    foreach (realpriceIterateRecords() as $record) {
        $address = realpriceNormalizeAddress((string) ($record['address'] ?? ''));
        if ($address === '' || mb_strlen($address, 'UTF-8') < 5) {
            continue;
        }
        $city = realpriceNormalizeAddress((string) ($record['city'] ?? ''));
        if ($allowedCities !== null && !isset($allowedCities[$city])) {
            continue;
        }
        $district = realpriceNormalizeAddress((string) ($record['district'] ?? ''));
        $key = realpriceGeocodeKey($address);
        $existing = is_array($cached[$key] ?? null) ? $cached[$key] : null;
        if ($existing && !realpriceGeocodeHitIsDistrictFallback($existing, $city, $district) && ($existing['precision'] ?? '') !== 'district') {
            continue;
        }
        // 沒有「號」的門牌（僅街名）無法跟門牌 CSV 對上。
        // 「地號」虽含「號」字，但是土地地號，不可進門牌比對清單。
        if (!str_contains($address, '號') || str_contains($address, '地號')) {
            continue;
        }
        $suffixKey = doorplateAddressSuffixKey($city, $district, $address);
        $needed[$suffixKey][] = [
            'geocode_key' => $key,
            'address' => $address,
            'city' => $city,
            'district' => $district,
        ];
    }
    return $needed;
}

function doorplateMatchStatePath(): string
{
    return doorplateDir() . '/match-job.json';
}

function doorplateMatchNeededPath(): string
{
    return doorplateDir() . '/match-needed.json';
}

function doorplateMatchNeededDbPath(): string
{
    return doorplateDir() . '/match-needed.sqlite';
}

/**
 * Persist needed lookup as SQLite so each match chunk does not re-parse a huge JSON.
 *
 * @param array<string, list<array<string, string>>> $needed
 */
function doorplateSaveNeededLookup(array $needed, array $cityFilter = []): int
{
    $path = doorplateMatchNeededDbPath();
    @unlink($path);
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('PRAGMA journal_mode=OFF');
    $pdo->exec('PRAGMA synchronous=OFF');
    $pdo->exec(
        'CREATE TABLE needed (
            suffix_key TEXT NOT NULL,
            geocode_key TEXT NOT NULL,
            address TEXT NOT NULL,
            city TEXT NOT NULL,
            district TEXT NOT NULL,
            PRIMARY KEY (suffix_key, geocode_key)
        )'
    );
    $insert = $pdo->prepare(
        'INSERT OR IGNORE INTO needed(suffix_key, geocode_key, address, city, district) VALUES (?,?,?,?,?)'
    );
    $pdo->beginTransaction();
    $rows = 0;
    foreach ($needed as $suffixKey => $targets) {
        if (!is_array($targets)) {
            continue;
        }
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }
            $gKey = (string) ($target['geocode_key'] ?? '');
            if ($gKey === '') {
                continue;
            }
            $insert->execute([
                (string) $suffixKey,
                $gKey,
                (string) ($target['address'] ?? ''),
                (string) ($target['city'] ?? ''),
                (string) ($target['district'] ?? ''),
            ]);
            $rows++;
        }
    }
    $pdo->commit();
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_needed_suffix ON needed(suffix_key)');

    // Keep a tiny JSON pointer for status / old tools; do not store full items.
    realpriceSaveJsonFile(doorplateMatchNeededPath(), [
        'generated_at' => date(DATE_ATOM),
        'engine' => 'sqlite',
        'db' => basename($path),
        'suffix_keys' => count($needed),
        'rows' => $rows,
        'city_filter' => $cityFilter,
    ]);

    return count($needed);
}

function doorplateOpenNeededDb(): PDO
{
    $path = doorplateMatchNeededDbPath();
    if (!is_file($path)) {
        throw new RuntimeException('找不到待比對清單資料庫，請重新開始門牌比對。');
    }
    return new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/** @return array<string, true> */
function doorplateLoadNeededSuffixSet(PDO $pdo): array
{
    $set = [];
    $stmt = $pdo->query('SELECT DISTINCT suffix_key FROM needed');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = (string) ($row['suffix_key'] ?? '');
        if ($key !== '') {
            $set[$key] = true;
        }
    }
    return $set;
}

/**
 * @return list<array{geocode_key:string,address:string,city:string,district:string}>
 */
function doorplateNeededTargetsForSuffix(PDO $pdo, string $suffixKey): array
{
    $stmt = $pdo->prepare(
        'SELECT geocode_key, address, city, district FROM needed WHERE suffix_key = ?'
    );
    $stmt->execute([$suffixKey]);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = [
            'geocode_key' => (string) ($row['geocode_key'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'district' => (string) ($row['district'] ?? ''),
        ];
    }
    return $out;
}

function doorplateNeededRemainingCount(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(DISTINCT suffix_key) FROM needed')->fetchColumn();
}

function doorplateMatchCity(string $cityKey, array &$neededSet, PDO $neededDb, array &$geocodeItems): array
{
    $chunk = doorplateMatchCityChunk($cityKey, $neededSet, $neededDb, $geocodeItems, 0, PHP_INT_MAX, 0);
    return [
        'matched' => (int) ($chunk['matched'] ?? 0),
        'scanned' => (int) ($chunk['scanned'] ?? 0),
    ];
}

/**
 * Process a time/row-bounded CSV chunk for one city.
 * $byteOffset=0 means start from beginning (read headers first).
 *
 * @param array<string, true> $neededSet
 */
function doorplateMatchCityChunk(
    string $cityKey,
    array &$neededSet,
    PDO $neededDb,
    array &$geocodeItems,
    int $byteOffset = 0,
    int $maxRows = 80000,
    int $maxSeconds = 10,
    ?array $savedCols = null,
    ?callable $onProgress = null
): array {
    $sources = doorplateSources();
    if (!isset($sources[$cityKey])) {
        throw new InvalidArgumentException('未知縣市：' . $cityKey);
    }
    $source = $sources[$cityKey];
    $path = doorplateCsvPath($cityKey);
    if (!is_file($path)) {
        throw new RuntimeException(($source['label'] ?? $cityKey) . ' 尚未有門牌 CSV，請先下載或上傳。');
    }
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        throw new RuntimeException('無法讀取門牌 CSV：' . $path);
    }

    $cols = is_array($savedCols) ? $savedCols : null;
    if ($byteOffset <= 0) {
        $bom = fread($fh, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fh);
        }
        $headers = fgetcsv($fh);
        if (!is_array($headers)) {
            fclose($fh);
            throw new RuntimeException('門牌 CSV 沒有標題列。');
        }
        $cols = doorplateDetectColumns($headers);
        if (!isset($cols['full_address']) && (!isset($cols['street']) || !isset($cols['number']))) {
            fclose($fh);
            throw new RuntimeException('門牌 CSV 找不到街路/門牌欄位（或完整地址欄）。');
        }
        if (!isset($cols['x'], $cols['y']) && !isset($cols['lat'], $cols['lng'])) {
            fclose($fh);
            throw new RuntimeException('門牌 CSV 找不到座標欄位。');
        }
        $byteOffset = (int) ftell($fh);
    } else {
        if (!is_array($cols) || $cols === []) {
            fclose($fh);
            throw new RuntimeException('門牌比對工作階段缺少欄位資訊，請重新開始比對。');
        }
        fseek($fh, $byteOffset);
    }

    $aliases = array_map('realpriceNormalizeAddress', $source['city_aliases'] ?? [$source['label']]);
    $matched = 0;
    $scanned = 0;
    $coordMode = (string) ($source['coord'] ?? 'twd97');
    $startedAt = microtime(true);
    $done = false;
    $maxRows = max(1000, $maxRows);
    $deleteStmt = $neededDb->prepare('DELETE FROM needed WHERE suffix_key = ?');

    while (($row = fgetcsv($fh)) !== false) {
        if (!is_array($row) || count($row) < 3) {
            $byteOffset = (int) ftell($fh);
            continue;
        }
        $scanned++;
        $streetNumber = doorplateBuildStreetNumber($row, $cols);
        if ($streetNumber !== '' && str_contains($streetNumber, '號')) {
            $ll = doorplateRowLatLng($row, $cols, $coordMode);
            if ($ll !== null) {
                foreach ($aliases as $alias) {
                    $suffixKey = sha1('suffix|' . $alias . '|' . $streetNumber);
                    if (!isset($neededSet[$suffixKey])) {
                        continue;
                    }
                    $targets = doorplateNeededTargetsForSuffix($neededDb, $suffixKey);
                    foreach ($targets as $target) {
                        $gKey = (string) ($target['geocode_key'] ?? '');
                        if ($gKey === '') {
                            continue;
                        }
                        $geocodeItems[$gKey] = [
                            'address' => (string) ($target['address'] ?? ''),
                            'city' => (string) ($target['city'] ?? ''),
                            'district' => (string) ($target['district'] ?? ''),
                            'lat' => round((float) $ll['lat'], 7),
                            'lng' => round((float) $ll['lng'], 7),
                            'display_name' => $alias . $streetNumber,
                            'provider' => 'doorplate_opendata',
                            'precision' => 'address',
                            'query' => $alias . $streetNumber,
                            'source_city' => $cityKey,
                            'updated_at' => date(DATE_ATOM),
                        ];
                        $matched++;
                    }
                    $deleteStmt->execute([$suffixKey]);
                    unset($neededSet[$suffixKey]);
                }
            }
        }
        $byteOffset = (int) ftell($fh);
        if ($onProgress) {
            $onProgress($byteOffset, $scanned, $matched);
        }
        if ($scanned >= $maxRows) {
            break;
        }
        if ($maxSeconds > 0 && (microtime(true) - $startedAt) >= $maxSeconds) {
            break;
        }
    }
    if (feof($fh)) {
        $done = true;
    }
    fclose($fh);

    return [
        'matched' => $matched,
        'scanned' => $scanned,
        'byte_offset' => $byteOffset,
        'done' => $done,
        'cols' => $cols,
        'elapsed' => round(microtime(true) - $startedAt, 3),
    ];
}

function doorplateMatchJobStart(array $cityKeys): array
{
    $sources = doorplateSources();
    $cityKeys = array_values(array_filter(array_map('strval', $cityKeys), static function (string $key) use ($sources): bool {
        return isset($sources[$key]) && is_file(doorplateCsvPath($key));
    }));
    if ($cityKeys === []) {
        throw new RuntimeException('沒有可比對的門牌 CSV，請先下載或上傳。');
    }

    // 若上一輪仍在 running：優先「續跑」而不是報錯卡死。
    // （瀏覽器重整／504 後常見：後端還在跑或 busy 鎖未清，前端卻以為要重新 start。）
    $prev = realpriceLoadJsonFile(doorplateMatchStatePath(), []);
    if (($prev['status'] ?? '') === 'running') {
        $updated = strtotime((string) ($prev['updated_at'] ?? '')) ?: 0;
        $busyUntil = strtotime((string) ($prev['busy_until'] ?? '')) ?: 0;
        $isBusy = !empty($prev['busy']) && $busyUntil > time();
        $age = $updated > 0 ? (time() - $updated) : PHP_INT_MAX;
        $prevCities = is_array($prev['cities'] ?? null) ? array_values(array_map('strval', $prev['cities'])) : [];
        $sameCities = $prevCities === $cityKeys
            || (count(array_diff($cityKeys, $prevCities)) === 0 && count(array_diff($prevCities, $cityKeys)) === 0);

        // 仍在掃描／剛更新過 → 直接續跑既有工作
        if ($isBusy || $age < 300 || ((int) ($prev['byte_offset'] ?? 0) > 0 && $sameCities)) {
            if (!$sameCities && !$isBusy && $age >= 120) {
                // 縣市不同且已停滯，允許改開新工作
            } else {
                $prev['updated_at'] = date(DATE_ATOM);
                if ($isBusy && $age > 180) {
                    // busy 鎖過久但 PHP 可能已死：強制清鎖讓下一 chunk 能進來
                    $prev['busy'] = false;
                    unset($prev['busy_until']);
                }
                $prev['message'] = '偵測到進行中的比對，改為繼續上次進度…'
                    . ((string) ($prev['current_label'] ?? '') !== ''
                        ? ('（' . (string) $prev['current_label'] . ' ' . (int) ($prev['file_pct'] ?? 0) . '%）')
                        : '');
                realpriceSaveJsonFile(doorplateMatchStatePath(), $prev);
                return [
                    'ok' => true,
                    'done' => false,
                    'resume' => true,
                    'job' => $prev,
                    'status' => doorplateStatus(),
                    'message' => $prev['message'],
                ];
            }
        }

        $prev['status'] = 'cancelled';
        $prev['phase'] = 'cancelled';
        $prev['busy'] = false;
        unset($prev['busy_until']);
        $prev['updated_at'] = date(DATE_ATOM);
        $prev['message'] = '偵測到停滯的比對工作，已自動結束以便重新開始。';
        realpriceSaveJsonFile(doorplateMatchStatePath(), $prev);
    }

    // Defer building the needed lookup to the first chunk request so Synology nginx
    // does not 504 on a single long "start" call.
    $job = [
        'started_at' => date(DATE_ATOM),
        'updated_at' => date(DATE_ATOM),
        'phase' => 'prepare_needed',
        'cities' => $cityKeys,
        'city_index' => 0,
        'byte_offset' => 0,
        'cols' => null,
        'total_matched' => 0,
        'total_scanned' => 0,
        'needed_before' => 0,
        'status' => 'running',
        'message' => '已建立比對工作，接著準備待比對地址清單…',
    ];
    realpriceSaveJsonFile(doorplateMatchStatePath(), $job);
    $meta = doorplateLoadMeta();
    $meta['last_message'] = $job['message'];
    doorplateSaveMeta($meta);
    return [
        'ok' => true,
        'done' => false,
        'job' => $job,
        'status' => doorplateStatus(),
        'message' => $job['message'],
    ];
}

function doorplateMatchJobCancel(): array
{
    $job = realpriceLoadJsonFile(doorplateMatchStatePath(), []);
    $matched = (int) ($job['total_matched'] ?? 0);
    $scanned = (int) ($job['total_scanned'] ?? 0);
    $job['status'] = 'cancelled';
    $job['phase'] = 'cancelled';
    $job['busy'] = false;
    unset($job['busy_until']);
    $job['updated_at'] = date(DATE_ATOM);
    $job['message'] = '已取消門牌比對'
        . ($matched > 0 || $scanned > 0
            ? ('（已寫入 ' . number_format($matched) . ' 筆、掃描 ' . number_format($scanned) . ' 列；已完成進度會保留）。')
            : '。');
    realpriceSaveJsonFile(doorplateMatchStatePath(), $job);
    $meta = doorplateLoadMeta();
    $meta['last_message'] = $job['message'];
    $meta['updated_at'] = date(DATE_ATOM);
    doorplateSaveMeta($meta);
    return [
        'ok' => true,
        'done' => true,
        'cancelled' => true,
        'job' => $job,
        'status' => doorplateStatus(),
        'geocode_stats' => realpriceGeocodeStats(),
        'message' => $job['message'],
    ];
}

function doorplateMatchAcquireLock(array &$job, int $ttlSeconds = 90): bool
{
    $busyUntil = strtotime((string) ($job['busy_until'] ?? '')) ?: 0;
    $updated = strtotime((string) ($job['updated_at'] ?? '')) ?: 0;
    // 心跳超過 3 分鐘沒動，視為殭屍鎖（PHP 被 nginx 砍掉後常見）
    $staleBusy = !empty($job['busy']) && $updated > 0 && (time() - $updated) > 180;
    if (($job['busy'] ?? false) && $busyUntil > time() && !$staleBusy) {
        return false;
    }
    $job['busy'] = true;
    $job['busy_until'] = date(DATE_ATOM, time() + max(30, $ttlSeconds));
    $job['updated_at'] = date(DATE_ATOM);
    realpriceSaveJsonFile(doorplateMatchStatePath(), $job);
    return true;
}

function doorplateMatchReleaseLock(array &$job): void
{
    $job['busy'] = false;
    unset($job['busy_until']);
    $job['updated_at'] = date(DATE_ATOM);
    realpriceSaveJsonFile(doorplateMatchStatePath(), $job);
}

function doorplateMatchJobChunk(int $maxRows = 80000, int $maxSeconds = 10): array
{
    @set_time_limit(0);
    $job = realpriceLoadJsonFile(doorplateMatchStatePath(), []);
    $status = (string) ($job['status'] ?? '');
    if ($status === 'cancelled') {
        return [
            'ok' => true,
            'done' => true,
            'cancelled' => true,
            'job' => $job,
            'status' => doorplateStatus(),
            'geocode_stats' => realpriceGeocodeStats(),
            'message' => (string) ($job['message'] ?? '已取消門牌比對。'),
        ];
    }
    if ($status !== 'running') {
        throw new RuntimeException('目前沒有進行中的門牌比對工作，請重新開始。');
    }

    if (!doorplateMatchAcquireLock($job, max(60, $maxSeconds + 45))) {
        return [
            'ok' => true,
            'done' => false,
            'busy' => true,
            'job' => $job,
            'status' => doorplateStatus(),
            'geocode_stats' => realpriceGeocodeStats(),
            'message' => (string) ($job['message'] ?? '伺服器仍在處理上一批，請稍候…'),
        ];
    }

    try {
        return doorplateMatchJobChunkLocked($job, $maxRows, $maxSeconds);
    } catch (Throwable $e) {
        $fresh = realpriceLoadJsonFile(doorplateMatchStatePath(), $job);
        doorplateMatchReleaseLock($fresh);
        throw $e;
    }
}

/**
 * @param array<string, mixed> $job
 */
function doorplateMatchJobChunkLocked(array $job, int $maxRows, int $maxSeconds): array
{
    if (($job['phase'] ?? 'matching') === 'prepare_needed') {
        $fresh = realpriceLoadJsonFile(doorplateMatchStatePath(), []);
        if (($fresh['status'] ?? '') === 'cancelled') {
            doorplateMatchReleaseLock($fresh);
            return doorplateMatchJobCancel();
        }
        $cities = is_array($job['cities'] ?? null) ? $job['cities'] : [];
        $needed = doorplateBuildNeededLookup($cities !== [] ? $cities : null);
        $fresh = realpriceLoadJsonFile(doorplateMatchStatePath(), []);
        if (($fresh['status'] ?? '') === 'cancelled') {
            doorplateMatchReleaseLock($fresh);
            return [
                'ok' => true,
                'done' => true,
                'cancelled' => true,
                'job' => $fresh,
                'status' => doorplateStatus(),
                'geocode_stats' => realpriceGeocodeStats(),
                'message' => (string) ($fresh['message'] ?? '已取消門牌比對。'),
            ];
        }
        $neededBefore = doorplateSaveNeededLookup($needed, $cities);
        $job['phase'] = 'matching';
        $job['needed_before'] = $neededBefore;
        $job['remaining_keys'] = $neededBefore;
        $job['updated_at'] = date(DATE_ATOM);
        $scope = $cities !== [] ? ('僅 ' . implode('、', $cities)) : '全國';
        $job['message'] = '待比對地址清單已準備（' . number_format($neededBefore) . ' 鍵，' . $scope . '），開始掃描門牌 CSV…';
        doorplateMatchReleaseLock($job);
        $meta = doorplateLoadMeta();
        $meta['last_message'] = $job['message'];
        doorplateSaveMeta($meta);
        return [
            'ok' => true,
            'done' => false,
            'job' => $job,
            'status' => doorplateStatus(),
            'geocode_stats' => realpriceGeocodeStats(),
            'message' => $job['message'],
        ];
    }

    $cities = is_array($job['cities'] ?? null) ? $job['cities'] : [];
    $cityIndex = (int) ($job['city_index'] ?? 0);
    if ($cityIndex >= count($cities)) {
        $job['status'] = 'done';
        $job['phase'] = 'done';
        $job['message'] = '本機門牌比對已完成。';
        doorplateMatchReleaseLock($job);
        return [
            'ok' => true,
            'done' => true,
            'job' => $job,
            'status' => doorplateStatus(),
            'geocode_stats' => realpriceGeocodeStats(),
            'message' => $job['message'],
        ];
    }

    $neededDb = doorplateOpenNeededDb();
    $neededSet = doorplateLoadNeededSuffixSet($neededDb);
    $overlay = [];
    $cityKey = (string) $cities[$cityIndex];
    $sources = doorplateSources();
    $label = (string) ($sources[$cityKey]['label'] ?? $cityKey);
    $csvSize = is_file(doorplateCsvPath($cityKey)) ? (int) filesize(doorplateCsvPath($cityKey)) : 0;

    // Warm only: read headers / set offset, return immediately so next AJAX does the long scan.
    if ((int) ($job['byte_offset'] ?? 0) <= 0 || !is_array($job['cols'] ?? null) || ($job['cols'] ?? []) === []) {
        $warm = doorplateMatchCityChunk($cityKey, $neededSet, $neededDb, $overlay, 0, 1, 2, null, null);
        $job['cols'] = $warm['cols'];
        $job['byte_offset'] = (int) $warm['byte_offset'];
        $job['total_matched'] = (int) ($job['total_matched'] ?? 0) + (int) $warm['matched'];
        $job['total_scanned'] = (int) ($job['total_scanned'] ?? 0) + (int) $warm['scanned'];
        $job['file_pct'] = 0;
        $job['current_city'] = $cityKey;
        $job['current_label'] = $label;
        $job['message'] = "{$label} 已就緒，開始掃描門牌 CSV…";
        if ((int) ($warm['matched'] ?? 0) > 0) {
            $cache = realpriceLoadGeocodeCache();
            $items = is_array($cache['items'] ?? null) ? $cache['items'] : [];
            foreach ($overlay as $gKey => $hit) {
                $items[$gKey] = $hit;
            }
            $cache['items'] = $items;
            $cache['updated_at'] = date(DATE_ATOM);
            realpriceSaveJsonFile(realpriceGeocodeCachePath(), $cache);
        }
        if (!empty($warm['done'])) {
            $job['city_index'] = $cityIndex + 1;
            $job['byte_offset'] = 0;
            $job['cols'] = null;
        }
        doorplateMatchReleaseLock($job);
        return [
            'ok' => true,
            'done' => false,
            'job' => $job,
            'chunk' => [
                'matched' => $warm['matched'],
                'scanned' => $warm['scanned'],
                'elapsed' => $warm['elapsed'],
                'city' => $cityKey,
                'label' => $label,
                'file_pct' => 0,
                'remaining' => doorplateNeededRemainingCount($neededDb),
            ],
            'status' => doorplateStatus(),
            'geocode_stats' => realpriceGeocodeStats(),
            'message' => $job['message'],
        ];
    }

    $progressTick = 0.0;
    $chunk = doorplateMatchCityChunk(
        $cityKey,
        $neededSet,
        $neededDb,
        $overlay,
        (int) ($job['byte_offset'] ?? 0),
        $maxRows,
        $maxSeconds,
        is_array($job['cols'] ?? null) ? $job['cols'] : null,
        static function (int $offset, int $scanned, int $matched) use (&$job, $label, $csvSize, &$progressTick): void {
            $now = microtime(true);
            if (($now - $progressTick) < 1.5) {
                return;
            }
            $progressTick = $now;
            $pct = $csvSize > 0 ? min(99, (int) round(($offset / $csvSize) * 100)) : 0;
            $job['byte_offset'] = $offset;
            $job['file_pct'] = $pct;
            $job['busy'] = true;
            $job['busy_until'] = date(DATE_ATOM, time() + 90);
            $job['updated_at'] = date(DATE_ATOM);
            $job['message'] = "{$label} 比對中：檔案 {$pct}%｜本批暫計 {$scanned} 列／命中 {$matched}";
            realpriceSaveJsonFile(doorplateMatchStatePath(), $job);
        }
    );

    $fresh = realpriceLoadJsonFile(doorplateMatchStatePath(), []);
    if (($fresh['status'] ?? '') === 'cancelled') {
        if ((int) ($chunk['matched'] ?? 0) > 0) {
            $cache = realpriceLoadGeocodeCache();
            $items = is_array($cache['items'] ?? null) ? $cache['items'] : [];
            foreach ($overlay as $gKey => $hit) {
                $items[$gKey] = $hit;
            }
            $cache['items'] = $items;
            $cache['updated_at'] = date(DATE_ATOM);
            realpriceSaveJsonFile(realpriceGeocodeCachePath(), $cache);
        }
        $fresh['total_matched'] = (int) ($fresh['total_matched'] ?? 0) + (int) ($chunk['matched'] ?? 0);
        $fresh['total_scanned'] = (int) ($fresh['total_scanned'] ?? 0) + (int) ($chunk['scanned'] ?? 0);
        $fresh['message'] = '已取消門牌比對（已寫入 '
            . number_format((int) $fresh['total_matched']) . ' 筆、掃描 '
            . number_format((int) $fresh['total_scanned']) . ' 列；已完成進度會保留）。';
        doorplateMatchReleaseLock($fresh);
        return [
            'ok' => true,
            'done' => true,
            'cancelled' => true,
            'job' => $fresh,
            'status' => doorplateStatus(),
            'geocode_stats' => realpriceGeocodeStats(),
            'message' => $fresh['message'],
        ];
    }

    if ((int) ($chunk['matched'] ?? 0) > 0) {
        $cache = realpriceLoadGeocodeCache();
        $items = is_array($cache['items'] ?? null) ? $cache['items'] : [];
        foreach ($overlay as $gKey => $hit) {
            $items[$gKey] = $hit;
        }
        $cache['items'] = $items;
        $cache['updated_at'] = date(DATE_ATOM);
        realpriceSaveJsonFile(realpriceGeocodeCachePath(), $cache);
    }

    $job['total_matched'] = (int) ($job['total_matched'] ?? 0) + (int) $chunk['matched'];
    $job['total_scanned'] = (int) ($job['total_scanned'] ?? 0) + (int) $chunk['scanned'];
    $job['updated_at'] = date(DATE_ATOM);
    $job['cols'] = $chunk['cols'];
    $job['byte_offset'] = (int) $chunk['byte_offset'];
    $job['current_city'] = $cityKey;
    $job['current_label'] = $label;
    $remaining = doorplateNeededRemainingCount($neededDb);

    $meta = doorplateLoadMeta();
    $cityMeta = is_array($meta['cities'][$cityKey] ?? null) ? $meta['cities'][$cityKey] : [];
    $cityMeta['matched_count'] = (int) ($cityMeta['matched_count'] ?? 0) + (int) $chunk['matched'];
    $cityMeta['scanned_rows'] = (int) ($cityMeta['scanned_rows'] ?? 0) + (int) $chunk['scanned'];
    $cityMeta['matched_at'] = date(DATE_ATOM);
    $meta['cities'][$cityKey] = $cityMeta;

    $doneAll = false;
    if (!empty($chunk['done'])) {
        $job['city_index'] = $cityIndex + 1;
        $job['byte_offset'] = 0;
        $job['cols'] = null;
        $job['file_pct'] = 100;
        if ($job['city_index'] >= count($cities)) {
            $doneAll = true;
            $job['status'] = 'done';
            $job['phase'] = 'done';
            $job['message'] = "本機門牌比對完成：寫入 {$job['total_matched']} 筆（掃描 {$job['total_scanned']} 列；待比對鍵 {$job['needed_before']} → 剩餘 {$remaining}）。";
            $meta['last_match_at'] = date(DATE_ATOM);
            $meta['last_match_written'] = (int) $job['total_matched'];
            realpriceSaveJsonFile(doorplateMatchNeededPath(), [
                'generated_at' => date(DATE_ATOM),
                'engine' => 'sqlite',
                'db' => basename(doorplateMatchNeededDbPath()),
                'suffix_keys' => $remaining,
                'city_filter' => $cities,
            ]);
            realpriceOpLog('match_doorplate_done', $job['message'], [
                'cities' => $cities,
                'matched' => (int) $job['total_matched'],
                'scanned' => (int) $job['total_scanned'],
                'needed_before' => (int) ($job['needed_before'] ?? 0),
                'remaining' => $remaining,
            ], 'ok');
        } else {
            $next = (string) ($cities[$job['city_index']] ?? '');
            $nextLabel = (string) ($sources[$next]['label'] ?? $next);
            $job['message'] = "{$label} 完成，接著處理 {$nextLabel}…（目前累計寫入 {$job['total_matched']} 筆）";
        }
    }
    $meta['last_message'] = $job['message'];
    doorplateSaveMeta($meta);
    $offset = (int) ($job['byte_offset'] ?? 0);
    $filePct = $csvSize > 0 ? min(99, (int) round(($offset / $csvSize) * 100)) : 0;
    if (!empty($chunk['done'])) {
        $filePct = 100;
    }
    $job['remaining_keys'] = $remaining;
    $job['file_pct'] = $filePct;
    if (!$doneAll && empty($chunk['done'])) {
        $job['message'] = "{$label} 比對中：檔案 {$filePct}%｜本批 {$chunk['scanned']} 列／命中 {$chunk['matched']}｜累計寫入 {$job['total_matched']}｜掃描 {$job['total_scanned']}｜剩餘鍵 {$remaining}";
    }
    doorplateMatchReleaseLock($job);

    return [
        'ok' => true,
        'done' => $doneAll,
        'job' => $job,
        'chunk' => [
            'matched' => $chunk['matched'],
            'scanned' => $chunk['scanned'],
            'elapsed' => $chunk['elapsed'],
            'city' => $cityKey,
            'label' => $label,
            'file_pct' => $filePct,
            'remaining' => $remaining,
        ],
        'status' => doorplateStatus(),
        'geocode_stats' => realpriceGeocodeStats(),
        'message' => $job['message'],
    ];
}

function doorplateMatchToGeocodeCache(?array $cityKeys = null): array
{
    // Compatibility wrapper: only starts a chunked job.
    // Do NOT run the full CSV scan in one HTTP request (Synology nginx will 504).
    $sources = doorplateSources();
    if ($cityKeys === null || $cityKeys === []) {
        $cityKeys = [];
        foreach ($sources as $key => $_source) {
            if (is_file(doorplateCsvPath((string) $key))) {
                $cityKeys[] = (string) $key;
            }
        }
    }
    $start = doorplateMatchJobStart($cityKeys);
    return [
        'status' => $start['status'] ?? doorplateStatus(),
        'geocode_stats' => realpriceGeocodeStats(),
        'written' => 0,
        'needed_before' => (int) ($start['job']['needed_before'] ?? 0),
        'needed_after' => (int) ($start['job']['needed_before'] ?? 0),
        'details' => [],
        'message' => (string) ($start['message'] ?? '已開始分批比對，請以 AJAX 續跑。'),
        'job' => $start['job'] ?? [],
        'done' => false,
    ];
}

function doorplateSaveUploadedCsv(string $cityKey, array $file): array
{
    $sources = doorplateSources();
    if (!isset($sources[$cityKey])) {
        throw new InvalidArgumentException('未知縣市：' . $cityKey);
    }
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        $hints = [
            UPLOAD_ERR_INI_SIZE => '檔案超過 PHP upload_max_filesize 限制。',
            UPLOAD_ERR_FORM_SIZE => '檔案超過表單 MAX_FILE_SIZE 限制。',
            UPLOAD_ERR_PARTIAL => '檔案只上傳了一部分，請重試。',
            UPLOAD_ERR_NO_FILE => '沒有選擇檔案。',
            UPLOAD_ERR_NO_TMP_DIR => '伺服器缺少暫存目錄。',
            UPLOAD_ERR_CANT_WRITE => '伺服器無法寫入上傳檔案。',
            UPLOAD_ERR_EXTENSION => '上傳被 PHP 擴充功能中斷。',
        ];
        throw new RuntimeException($hints[$errorCode] ?? ('上傳失敗，錯誤碼：' . $errorCode));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        throw new RuntimeException('找不到上傳暫存檔。');
    }
    $dest = doorplateCsvPath($cityKey);
    if (!@move_uploaded_file($tmp, $dest)) {
        if (!@rename($tmp, $dest) && !@copy($tmp, $dest)) {
            throw new RuntimeException('無法保存上傳的門牌 CSV。');
        }
    }
    $size = (int) filesize($dest);
    if ($size < 1000) {
        throw new RuntimeException('上傳檔案過小，可能不是有效的門牌 CSV。');
    }
    $meta = doorplateLoadMeta();
    $meta['cities'][$cityKey] = array_merge(
        is_array($meta['cities'][$cityKey] ?? null) ? $meta['cities'][$cityKey] : [],
        [
            'downloaded_at' => date(DATE_ATOM),
            'file_size' => $size,
            'source_url' => 'upload',
        ]
    );
    $meta['last_message'] = ($sources[$cityKey]['label'] ?? $cityKey) . ' 門牌 CSV 已上傳：' . realpriceHumanSize($size);
    doorplateSaveMeta($meta);
    return doorplateStatus();
}

/**
 * Register a CSV that was copied manually into cache/realprice/doorplate/{city}.csv
 * (useful when browser upload hits Synology PHP size limits).
 */
function doorplateRegisterLocalCsv(string $cityKey): array
{
    $sources = doorplateSources();
    if (!isset($sources[$cityKey])) {
        throw new InvalidArgumentException('未知縣市：' . $cityKey);
    }
    $path = doorplateCsvPath($cityKey);
    if (!is_file($path)) {
        $expected = basename($path);
        throw new RuntimeException(
            '找不到本機檔案。請把 CSV 複製並重新命名為「' . $expected . '」，放到：' . doorplateDir()
        );
    }
    $size = (int) filesize($path);
    if ($size < 1000) {
        throw new RuntimeException('本機檔案過小，可能不是有效的門牌 CSV：' . $path);
    }
    $meta = doorplateLoadMeta();
    $meta['cities'][$cityKey] = array_merge(
        is_array($meta['cities'][$cityKey] ?? null) ? $meta['cities'][$cityKey] : [],
        [
            'downloaded_at' => date(DATE_ATOM),
            'file_size' => $size,
            'source_url' => 'local_copy',
        ]
    );
    $meta['last_message'] = ($sources[$cityKey]['label'] ?? $cityKey)
        . ' 已登記本機門牌 CSV：' . realpriceHumanSize($size)
        . '（' . basename($path) . '）';
    doorplateSaveMeta($meta);
    return doorplateStatus();
}
