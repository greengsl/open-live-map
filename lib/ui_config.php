<?php
declare(strict_types=1);

function defaultUiConfig(): array
{
    return [
        'monitor_overlay_opacity' => 30,
        'monitor_overlay_blur' => 10,
        'map_marker_opacity' => 85,
    ];
}

function uiConfigPath(): string
{
    return dirname(__DIR__) . '/config/ui.php';
}

function loadUiConfigFile(string $path): array
{
    clearstatcache(true, $path);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    /** @var mixed $loaded */
    $loaded = include $path;
    return is_array($loaded) ? $loaded : [];
}

function loadUiConfig(): array
{
    $config = defaultUiConfig();
    $path = uiConfigPath();
    if (!is_file($path)) {
        return $config;
    }
    $loaded = loadUiConfigFile($path);
    if ($loaded === []) {
        return $config;
    }
    return array_replace_recursive($config, $loaded);
}

function normalizePercentOpacity(mixed $value, int $fallback = 100): int
{
    if (is_string($value)) {
        $value = trim($value);
    }
    if ($value === '' || $value === null) {
        return max(0, min(100, $fallback));
    }
    $opacity = (int) round((float) $value);
    return max(0, min(100, $opacity));
}

function normalizeMonitorOverlayOpacity(mixed $value): int
{
    return normalizePercentOpacity($value, 30);
}

function normalizeMonitorOverlayBlur(mixed $value): int
{
    if (is_string($value)) {
        $value = trim($value);
    }
    $blur = (int) round((float) $value);
    return max(0, min(24, $blur));
}

function normalizeMapMarkerOpacity(mixed $value): int
{
    return normalizePercentOpacity($value, 85);
}

function normalizeUiConfig(array $config): array
{
    $next = defaultUiConfig();
    $next['monitor_overlay_opacity'] = normalizeMonitorOverlayOpacity($config['monitor_overlay_opacity'] ?? 30);
    $next['monitor_overlay_blur'] = normalizeMonitorOverlayBlur($config['monitor_overlay_blur'] ?? 10);
    $next['map_marker_opacity'] = normalizeMapMarkerOpacity($config['map_marker_opacity'] ?? 85);
    return $next;
}

function saveUiConfig(array $config): array
{
    $next = normalizeUiConfig($config);
    $path = uiConfigPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $payload = "<?php\n"
        . "declare(strict_types=1);\n\n"
        . "return [\n"
        . "    'monitor_overlay_opacity' => " . (int) $next['monitor_overlay_opacity'] . ",\n"
        . "    'monitor_overlay_blur' => " . (int) $next['monitor_overlay_blur'] . ",\n"
        . "    'map_marker_opacity' => " . (int) $next['map_marker_opacity'] . ",\n"
        . "];\n";
    if (file_put_contents($path, $payload, LOCK_EX) === false) {
        throw new RuntimeException('界面設定寫入失敗。');
    }
    clearstatcache(true, $path);
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    return $next;
}

function monitorOverlayOpacity(array $config = []): int
{
    if ($config === []) {
        $config = loadUiConfig();
    }
    return normalizeMonitorOverlayOpacity($config['monitor_overlay_opacity'] ?? 30);
}

function monitorOverlayBlur(array $config = []): int
{
    if ($config === []) {
        $config = loadUiConfig();
    }
    return normalizeMonitorOverlayBlur($config['monitor_overlay_blur'] ?? 10);
}

function mapMarkerOpacity(array $config = []): int
{
    if ($config === []) {
        $config = loadUiConfig();
    }
    return normalizeMapMarkerOpacity($config['map_marker_opacity'] ?? 85);
}

function monitorOverlayAlphaCss(array $config = []): string
{
    return number_format(monitorOverlayOpacity($config) / 100, 2, '.', '');
}

function monitorOverlayBlurCss(array $config = []): string
{
    return (string) monitorOverlayBlur($config) . 'px';
}

function mapMarkerOpacityCss(array $config = []): string
{
    return number_format(mapMarkerOpacity($config) / 100, 2, '.', '');
}
