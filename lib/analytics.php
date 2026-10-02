<?php
declare(strict_types=1);

function analyticsDir(): string
{
    return dirname(__DIR__) . '/cache/analytics';
}

function analyticsSaltPath(): string
{
    return analyticsDir() . '/salt.txt';
}

function analyticsEnsureDir(): void
{
    $dir = analyticsDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

function analyticsSalt(): string
{
    analyticsEnsureDir();
    $path = analyticsSaltPath();
    if (is_file($path)) {
        $salt = trim((string) @file_get_contents($path));
        if ($salt !== '') return $salt;
    }
    $salt = bin2hex(random_bytes(24));
    @file_put_contents($path, $salt, LOCK_EX);
    return $salt;
}

function analyticsClientIp(): string
{
    $candidates = [
        (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''),
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];
    foreach ($candidates as $candidate) {
        $first = trim(explode(',', $candidate)[0] ?? '');
        if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return '';
}

function analyticsCountryName(string $code): string
{
    $code = strtoupper(trim($code));
    $names = [
        'TW' => '台灣',
        'JP' => '日本',
        'US' => '美國',
        'HK' => '香港',
        'MO' => '澳門',
        'CN' => '中國',
        'SG' => '新加坡',
        'KR' => '韓國',
        'MY' => '馬來西亞',
        'TH' => '泰國',
        'VN' => '越南',
        'PH' => '菲律賓',
        'ID' => '印尼',
        'AU' => '澳洲',
        'CA' => '加拿大',
        'GB' => '英國',
        'DE' => '德國',
        'FR' => '法國',
        'NL' => '荷蘭',
    ];
    if ($code === '' || $code === 'XX' || $code === 'T1') return '未知';
    return $names[$code] ?? $code;
}

function analyticsCountryCode(): string
{
    $code = strtoupper(trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    if ($code === '') {
        $code = strtoupper(trim((string) ($_SERVER['HTTP_X_APPENGINE_COUNTRY'] ?? '')));
    }
    return preg_match('/^[A-Z0-9]{2}$/', $code) ? $code : 'XX';
}

function analyticsRefHost(): string
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($referer === '') return '';
    $host = parse_url($referer, PHP_URL_HOST);
    return is_string($host) ? strtolower($host) : '';
}

function analyticsBotName(string $ua): string
{
    $patterns = [
        'Googlebot' => '/googlebot/i',
        'Bingbot' => '/bingbot/i',
        'Facebook' => '/facebookexternalhit|facebot/i',
        'LINE' => '/line-poker|linespider/i',
        'Applebot' => '/applebot/i',
        'DuckDuckBot' => '/duckduckbot/i',
        'Yandex' => '/yandexbot/i',
        'Semrush' => '/semrushbot/i',
        'Ahrefs' => '/ahrefsbot/i',
        'GPTBot' => '/gptbot/i',
        'ClaudeBot' => '/claudebot|anthropic-ai/i',
        'Crawler' => '/bot|crawler|spider|slurp|curl|wget|python-requests|httpclient/i',
    ];
    foreach ($patterns as $name => $pattern) {
        if (preg_match($pattern, $ua)) return $name;
    }
    return '';
}

function analyticsBrowser(string $ua): string
{
    if (preg_match('/Edg\//i', $ua)) return 'Edge';
    if (preg_match('/OPR\//i', $ua)) return 'Opera';
    if (preg_match('/CriOS\//i', $ua)) return 'Chrome iOS';
    if (preg_match('/Chrome\//i', $ua)) return 'Chrome';
    if (preg_match('/FxiOS\//i', $ua)) return 'Firefox iOS';
    if (preg_match('/Firefox\//i', $ua)) return 'Firefox';
    if (preg_match('/Safari\//i', $ua) && preg_match('/Version\//i', $ua)) return 'Safari';
    if (preg_match('/SamsungBrowser\//i', $ua)) return 'Samsung Internet';
    return $ua === '' ? '未知' : '其他';
}

function analyticsOs(string $ua): string
{
    if (preg_match('/iPhone|iPad|iPod/i', $ua)) return 'iOS';
    if (preg_match('/Android/i', $ua)) return 'Android';
    if (preg_match('/Windows NT/i', $ua)) return 'Windows';
    if (preg_match('/Mac OS X|Macintosh/i', $ua)) return 'macOS';
    if (preg_match('/Linux/i', $ua)) return 'Linux';
    return $ua === '' ? '未知' : '其他';
}

function analyticsDevice(string $ua): string
{
    if (preg_match('/iPad|Tablet/i', $ua)) return '平板';
    if (preg_match('/Mobile|iPhone|Android/i', $ua)) return '手機';
    return '桌面';
}

function analyticsLogVisit(): void
{
    if (PHP_SAPI === 'cli') return;
    analyticsEnsureDir();
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ip = analyticsClientIp();
    $country = analyticsCountryCode();
    $bot = analyticsBotName($ua);
    $now = new DateTimeImmutable('now');
    $record = [
        'ts' => $now->format(DATE_ATOM),
        'day' => $now->format('Y-m-d'),
        'hour' => $now->format('H'),
        'ip_hash' => $ip !== '' ? hash('sha256', analyticsSalt() . '|' . $ip) : '',
        'country' => $country,
        'country_name' => analyticsCountryName($country),
        'is_bot' => $bot !== '',
        'bot' => $bot,
        'browser' => analyticsBrowser($ua),
        'os' => analyticsOs($ua),
        'device' => analyticsDevice($ua),
        'path' => parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/',
        'ref_host' => analyticsRefHost(),
    ];
    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line !== false) {
        @file_put_contents(analyticsDir() . '/' . $now->format('Y-m-d') . '.jsonl', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function analyticsIncrement(array &$map, string $key): void
{
    $key = trim($key) !== '' ? $key : '未知';
    $map[$key] = ($map[$key] ?? 0) + 1;
}

function analyticsTop(array $map, int $limit = 10): array
{
    arsort($map);
    $rows = [];
    foreach (array_slice($map, 0, $limit, true) as $label => $count) {
        $rows[] = ['label' => (string) $label, 'count' => (int) $count];
    }
    return $rows;
}

function analyticsReport(int $days = 14, int $recentLimit = 120): array
{
    analyticsEnsureDir();
    $days = max(1, min(90, $days));
    $recentLimit = max(20, min(500, $recentLimit));
    $visits = 0;
    $bots = 0;
    $humans = 0;
    $unique = [];
    $countries = [];
    $browsers = [];
    $oses = [];
    $devices = [];
    $botNames = [];
    $hours = [];
    $paths = [];
    $recent = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $day = (new DateTimeImmutable('today'))->modify("-{$i} days")->format('Y-m-d');
        $path = analyticsDir() . '/' . $day . '.jsonl';
        if (!is_file($path)) continue;
        $handle = @fopen($path, 'rb');
        if (!is_resource($handle)) continue;
        while (($line = fgets($handle)) !== false) {
            $row = json_decode($line, true);
            if (!is_array($row)) continue;
            $visits++;
            $isBot = !empty($row['is_bot']);
            if ($isBot) {
                $bots++;
                analyticsIncrement($botNames, (string) ($row['bot'] ?? 'Crawler'));
            } else {
                $humans++;
            }
            $hash = (string) ($row['ip_hash'] ?? '');
            if ($hash !== '') $unique[$hash] = true;
            analyticsIncrement($countries, (string) ($row['country_name'] ?? $row['country'] ?? '未知'));
            analyticsIncrement($browsers, (string) ($row['browser'] ?? '未知'));
            analyticsIncrement($oses, (string) ($row['os'] ?? '未知'));
            analyticsIncrement($devices, (string) ($row['device'] ?? '未知'));
            analyticsIncrement($hours, str_pad((string) ($row['hour'] ?? '00'), 2, '0', STR_PAD_LEFT) . ':00');
            analyticsIncrement($paths, (string) ($row['path'] ?? '/'));
            $recent[] = $row;
            if (count($recent) > $recentLimit) {
                array_shift($recent);
            }
        }
        fclose($handle);
    }

    $recent = array_reverse($recent);
    return [
        'days' => $days,
        'visits' => $visits,
        'humans' => $humans,
        'bots' => $bots,
        'unique_visitors' => count($unique),
        'countries' => analyticsTop($countries),
        'browsers' => analyticsTop($browsers),
        'oses' => analyticsTop($oses),
        'devices' => analyticsTop($devices),
        'bots_by_name' => analyticsTop($botNames),
        'hours' => analyticsTop($hours, 24),
        'paths' => analyticsTop($paths),
        'recent' => $recent,
    ];
}
