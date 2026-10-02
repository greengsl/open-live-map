<?php
declare(strict_types=1);

function defaultMapillaryConfig(): array
{
    return [
        'enabled' => false,
        'access_token' => '',
        'radius_m' => 50,
    ];
}

function mapillaryConfigPath(): string
{
    return dirname(__DIR__) . '/config/mapillary.php';
}

function loadMapillaryConfig(): array
{
    $config = defaultMapillaryConfig();
    $path = mapillaryConfigPath();
    if (!is_file($path)) {
        return $config;
    }
    clearstatcache(true, $path);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    /** @var mixed $loaded */
    $loaded = include $path;
    if (!is_array($loaded)) {
        return $config;
    }
    return array_replace_recursive($config, $loaded);
}

function normalizeMapillaryConfig(array $config): array
{
    $next = defaultMapillaryConfig();
    $next['enabled'] = (bool) ($config['enabled'] ?? false);
    $next['access_token'] = trim((string) ($config['access_token'] ?? ''));
    $radius = (int) round((float) ($config['radius_m'] ?? 50));
    $next['radius_m'] = max(1, min(50, $radius));
    if ($next['enabled'] && $next['access_token'] === '') {
        $next['enabled'] = false;
    }
    return $next;
}

function saveMapillaryConfig(array $config): array
{
    $next = normalizeMapillaryConfig($config);
    $path = mapillaryConfigPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $tokenExport = var_export($next['access_token'], true);
    $payload = "<?php\n"
        . "declare(strict_types=1);\n\n"
        . "return [\n"
        . "    'enabled' => " . ($next['enabled'] ? 'true' : 'false') . ",\n"
        . "    'access_token' => {$tokenExport},\n"
        . "    'radius_m' => " . (int) $next['radius_m'] . ",\n"
        . "];\n";
    if (file_put_contents($path, $payload, LOCK_EX) === false) {
        throw new RuntimeException('Mapillary 設定寫入失敗。');
    }
    clearstatcache(true, $path);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    return $next;
}

function mapillaryEnabled(array $config = []): bool
{
    if ($config === []) {
        $config = loadMapillaryConfig();
    }
    $normalized = normalizeMapillaryConfig($config);
    return (bool) $normalized['enabled'] && $normalized['access_token'] !== '';
}

function mapillaryPublicBoot(array $config = []): array
{
    if ($config === []) {
        $config = loadMapillaryConfig();
    }
    $normalized = normalizeMapillaryConfig($config);
    $enabled = mapillaryEnabled($normalized);
    $boot = [
        'enabled' => $enabled,
        'radius_m' => (int) $normalized['radius_m'],
    ];
    // Client token is required by MapillaryJS viewer (loaded on demand only).
    if ($enabled) {
        $boot['access_token'] = $normalized['access_token'];
    }
    return $boot;
}
