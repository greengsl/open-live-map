<?php
declare(strict_types=1);

function sourceConfigPath(): string
{
    return dirname(__DIR__) . '/cache/data-sources.json';
}

function sourceSeedConfigPath(): string
{
    return dirname(__DIR__) . '/config/data-sources.json';
}

function defaultSourceConfig(): array
{
    return [
        'garbage' => [
            'enabled' => true,
            'default_checked' => true,
            'label' => '桃園環保車',
            'description' => '垃圾車、回收車 GPS',
        ],
        'cctv-cache' => [
            'enabled' => true,
            'default_checked' => false,
            'label' => '監視器',
            'description' => '保存點位，點擊時開啟影像',
        ],
        'weather' => [
            'enabled' => true,
            'default_checked' => false,
            'label' => '氣象測站',
            'description' => '中央氣象署 CWA 公開資料',
        ],
        'realprice' => [
            'enabled' => true,
            'default_checked' => false,
            'label' => '實價登錄',
            'description' => '內政部不動產成交批次資料',
        ],
        'stale' => [
            'enabled' => true,
            'default_checked' => true,
            'label' => '較舊移動資料',
            'description' => '公車、環保車等超過 10 分鐘資料',
        ],
    ];
}

function loadSourceConfig(): array
{
    $defaults = defaultSourceConfig();
    $json = [];
    foreach ([sourceSeedConfigPath(), sourceConfigPath()] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $loaded = json_decode((string) file_get_contents($path), true);
        if (is_array($loaded)) {
            $json = array_replace_recursive($json, $loaded);
        }
    }
    if ($json === []) return $defaults;
    foreach ($defaults as $key => $default) {
        if (!isset($json[$key]) || !is_array($json[$key])) {
            $json[$key] = $default;
            continue;
        }
        $json[$key] = array_replace($default, [
            'enabled' => (bool) ($json[$key]['enabled'] ?? $default['enabled']),
            'default_checked' => (bool) ($json[$key]['default_checked'] ?? $default['default_checked']),
        ]);
    }
    return array_intersect_key($json, $defaults);
}

function saveSourceConfig(array $config): void
{
    $path = sourceConfigPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('資料來源設定寫入失敗。');
    }
}

function sourceEnabled(array $sources, string $key): bool
{
    return (bool) ($sources[$key]['enabled'] ?? false);
}

function sourceDefaultChecked(array $sources, string $key): bool
{
    return (bool) ($sources[$key]['default_checked'] ?? false);
}

function checkedAttr(bool $checked): string
{
    return $checked ? ' checked' : '';
}
