<?php
declare(strict_types=1);

function defaultMonetizationConfig(): array
{
    return [
        'adsense' => [
            'enabled' => false,
            'client' => '',
            'slots' => [
                'sidebar' => '',
                'map' => '',
            ],
        ],
        'support' => [
            'enabled' => true,
            'title' => '支持 Open Live Map',
            'description' => '協助維持伺服器、資料整理與公開地圖服務。',
            'button_label' => '贊助本站',
            'url' => '',
            'contact_title' => '聯絡方式',
            'contact_email' => '',
            'contact_url_label' => '',
            'contact_url' => '',
            'contact_note' => '',
        ],
    ];
}

function monetizationConfigPath(): string
{
    return dirname(__DIR__) . '/config/monetization.php';
}

function loadMonetizationConfig(): array
{
    $config = defaultMonetizationConfig();
    $path = monetizationConfigPath();
    if (!is_file($path)) {
        return $config;
    }
    $loaded = require $path;
    if (!is_array($loaded)) {
        return $config;
    }
    return array_replace_recursive($config, $loaded);
}

function exportPhpArray(array $value, int $indent = 0): string
{
    $spaces = str_repeat('    ', $indent);
    $nextSpaces = str_repeat('    ', $indent + 1);
    $lines = ['['];
    foreach ($value as $key => $item) {
        $exportedKey = is_int($key) ? (string) $key : var_export((string) $key, true);
        if (is_array($item)) {
            $lines[] = $nextSpaces . $exportedKey . ' => ' . exportPhpArray($item, $indent + 1) . ',';
        } elseif (is_bool($item)) {
            $lines[] = $nextSpaces . $exportedKey . ' => ' . ($item ? 'true' : 'false') . ',';
        } else {
            $lines[] = $nextSpaces . $exportedKey . ' => ' . var_export((string) $item, true) . ',';
        }
    }
    $lines[] = $spaces . ']';
    return implode(PHP_EOL, $lines);
}

function saveMonetizationConfig(array $config): void
{
    $path = monetizationConfigPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $payload = "<?php\n"
        . "declare(strict_types=1);\n\n"
        . "return " . exportPhpArray($config) . ";\n";
    if (file_put_contents($path, $payload, LOCK_EX) === false) {
        throw new RuntimeException('收益設定寫入失敗。');
    }
}

function adsenseEnabled(array $config): bool
{
    $client = trim((string) ($config['adsense']['client'] ?? ''));
    return (bool) ($config['adsense']['enabled'] ?? false)
        && preg_match('/^ca-pub-\d+$/', $client) === 1;
}

function adsenseSlot(array $config, string $key): string
{
    return trim((string) ($config['adsense']['slots'][$key] ?? ''));
}

function supportEnabled(array $config): bool
{
    return (bool) ($config['support']['enabled'] ?? false);
}
