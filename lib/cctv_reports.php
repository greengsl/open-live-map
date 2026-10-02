<?php
declare(strict_types=1);

function cctvReportPath(): string
{
    return dirname(__DIR__) . '/cache/cctv-reports.json';
}

function cctvReportTypeConfigPath(): string
{
    return dirname(__DIR__) . '/cache/cctv-report-types.json';
}

function defaultCctvReportTypeRows(): array
{
    return [
        ['key' => 'offline', 'label' => '影像無法播放', 'enabled' => true, 'sort' => 10],
        ['key' => 'not_live', 'label' => '不是即時影像', 'enabled' => true, 'sort' => 20],
        ['key' => 'wrong_location', 'label' => '位置或方向錯誤', 'enabled' => true, 'sort' => 30],
        ['key' => 'privacy', 'label' => '疑似侵犯隱私', 'enabled' => true, 'sort' => 40],
        ['key' => 'inappropriate', 'label' => '內容不適合公開顯示', 'enabled' => true, 'sort' => 50],
        ['key' => 'other', 'label' => '其他問題', 'enabled' => true, 'sort' => 60],
    ];
}

function normalizeCctvReportTypeKey(string $key, string $fallback = ''): string
{
    $key = strtolower(trim($key));
    $key = preg_replace('/[^a-z0-9_-]+/', '_', $key) ?? '';
    $key = trim($key, '_-');
    if ($key === '' && $fallback !== '') {
        $key = strtolower(trim($fallback));
        $key = preg_replace('/[^a-z0-9_-]+/', '_', $key) ?? '';
        $key = trim($key, '_-');
    }
    return substr($key, 0, 48);
}

function normalizeCctvReportTypeRows(array $rows): array
{
    $normalized = [];
    $seen = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) continue;
        $label = trim((string) ($row['label'] ?? ''));
        $key = normalizeCctvReportTypeKey((string) ($row['key'] ?? ''), 'type_' . ((int) $index + 1));
        if ($key === '' || $label === '') continue;
        if (isset($seen[$key])) {
            $key .= '_' . ((int) $index + 1);
        }
        $seen[$key] = true;
        $normalized[] = [
            'key' => $key,
            'label' => function_exists('mb_substr') ? mb_substr($label, 0, 80, 'UTF-8') : substr($label, 0, 80),
            'enabled' => filter_var($row['enabled'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'sort' => (int) ($row['sort'] ?? (($index + 1) * 10)),
        ];
    }
    usort($normalized, static fn (array $a, array $b): int => ((int) $a['sort'] <=> (int) $b['sort']) ?: strcmp((string) $a['label'], (string) $b['label']));
    return $normalized !== [] ? $normalized : defaultCctvReportTypeRows();
}

function loadCctvReportTypeRows(): array
{
    $path = cctvReportTypeConfigPath();
    if (!is_file($path)) {
        return defaultCctvReportTypeRows();
    }
    $json = json_decode((string) file_get_contents($path), true);
    $rows = is_array($json['types'] ?? null) ? $json['types'] : (is_array($json) ? $json : []);
    return normalizeCctvReportTypeRows($rows);
}

function saveCctvReportTypeRows(array $rows): array
{
    $rows = normalizeCctvReportTypeRows($rows);
    $enabled = array_values(array_filter($rows, static fn (array $row): bool => (bool) ($row['enabled'] ?? false)));
    if ($enabled === []) {
        throw new RuntimeException('至少要啟用一個回報問題類型。');
    }
    $path = cctvReportTypeConfigPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $payload = [
        'updated_at' => gmdate('c'),
        'types' => $rows,
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('監視器回報類型設定寫入失敗。');
    }
    return $rows;
}

function cctvReportTypes(bool $activeOnly = true): array
{
    $types = [];
    foreach (loadCctvReportTypeRows() as $row) {
        if ($activeOnly && !($row['enabled'] ?? false)) continue;
        $types[(string) $row['key']] = (string) $row['label'];
    }
    return $types !== [] ? $types : ['other' => '其他問題'];
}

function cctvReportStatuses(): array
{
    return [
        'open' => '待處理',
        'reviewing' => '處理中',
        'resolved' => '已處理',
        'ignored' => '已忽略',
    ];
}

function loadCctvReports(): array
{
    $path = cctvReportPath();
    if (!is_file($path)) {
        return [];
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        return [];
    }
    return array_values(array_filter($json, static fn ($row): bool => is_array($row)));
}

function saveCctvReports(array $rows): void
{
    $path = cctvReportPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('監視器回報寫入失敗。');
    }
}

function normalizeCctvReportStatus(string $status): string
{
    return array_key_exists($status, cctvReportStatuses()) ? $status : 'open';
}

function createCctvReport(array $input, array $server = []): array
{
    $types = cctvReportTypes();
    $type = trim((string) ($input['type'] ?? ''));
    if (!array_key_exists($type, $types)) {
        throw new RuntimeException('請選擇回報類型。');
    }

    $cameraId = trim((string) ($input['camera_id'] ?? ''));
    $cctvId = trim((string) ($input['cctv_id'] ?? ''));
    $title = trim((string) ($input['title'] ?? ''));
    if ($cameraId === '' && $cctvId === '' && $title === '') {
        throw new RuntimeException('缺少監視器資訊，無法建立回報。');
    }

    $description = trim((string) ($input['description'] ?? ''));
    $contact = trim((string) ($input['contact'] ?? ''));
    $descriptionLength = function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : strlen($description);
    if ($descriptionLength > 800) {
        $description = function_exists('mb_substr') ? mb_substr($description, 0, 800, 'UTF-8') : substr($description, 0, 800);
    }
    $contactLength = function_exists('mb_strlen') ? mb_strlen($contact, 'UTF-8') : strlen($contact);
    if ($contactLength > 160) {
        $contact = function_exists('mb_substr') ? mb_substr($contact, 0, 160, 'UTF-8') : substr($contact, 0, 160);
    }

    $now = gmdate('c');
    $ip = trim((string) ($server['HTTP_CF_CONNECTING_IP'] ?? $server['HTTP_X_FORWARDED_FOR'] ?? $server['REMOTE_ADDR'] ?? ''));
    if (str_contains($ip, ',')) {
        $ip = trim(explode(',', $ip)[0]);
    }

    return [
        'id' => 'report-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)),
        'status' => 'open',
        'type' => $type,
        'type_label' => $types[$type],
        'cctv_id' => $cctvId,
        'camera_id' => $cameraId,
        'title' => $title,
        'source_label' => trim((string) ($input['source_label'] ?? '')),
        'stream_url' => trim((string) ($input['stream_url'] ?? '')),
        'lat' => trim((string) ($input['lat'] ?? '')),
        'lng' => trim((string) ($input['lng'] ?? '')),
        'description' => $description,
        'contact' => $contact,
        'page_url' => trim((string) ($input['page_url'] ?? '')),
        'user_agent' => trim((string) ($server['HTTP_USER_AGENT'] ?? '')),
        'ip_hash' => $ip !== '' ? hash('sha256', $ip) : '',
        'created_at' => $now,
        'updated_at' => $now,
    ];
}

function addCctvReport(array $input, array $server = []): array
{
    $rows = loadCctvReports();
    $report = createCctvReport($input, $server);
    if ($report['ip_hash'] !== '') {
        $cutoff = time() - 3600;
        $recent = 0;
        foreach ($rows as $row) {
            if ((string) ($row['ip_hash'] ?? '') !== $report['ip_hash']) {
                continue;
            }
            $created = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
            if ($created >= $cutoff) {
                $recent++;
            }
        }
        if ($recent >= 12) {
            throw new RuntimeException('回報次數過多，請稍後再試。');
        }
    }
    array_unshift($rows, $report);
    saveCctvReports(array_slice($rows, 0, 1000));
    return $report;
}

function updateCctvReportStatus(string $id, string $status): array
{
    $rows = loadCctvReports();
    $status = normalizeCctvReportStatus($status);
    foreach ($rows as &$row) {
        if ((string) ($row['id'] ?? '') === $id) {
            $row['status'] = $status;
            $row['updated_at'] = gmdate('c');
            unset($row);
            saveCctvReports($rows);
            return $rows;
        }
    }
    throw new RuntimeException('找不到指定的監視器回報。');
}

function cctvReportSummary(array $rows): array
{
    $summary = array_fill_keys(array_keys(cctvReportStatuses()), 0);
    foreach ($rows as $row) {
        $status = normalizeCctvReportStatus((string) ($row['status'] ?? 'open'));
        $summary[$status]++;
    }
    return $summary;
}
