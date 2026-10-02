<?php
declare(strict_types=1);

/**
 * Frontend i18n: zh-Hant (default) + en.
 * Preference: ?lang= → cookie olm_lang → default zh-Hant.
 */

function i18nAvailableLocales(): array
{
    return [
        'zh-Hant' => '繁中',
        'en' => 'EN',
    ];
}

function i18nDefaultLocale(): string
{
    return 'zh-Hant';
}

function i18nNormalizeLocale(string $locale): string
{
    $locale = trim($locale);
    if ($locale === 'zh' || $locale === 'zh-TW' || $locale === 'zh-Hant-TW' || $locale === 'zh_TW') {
        return 'zh-Hant';
    }
    if ($locale === 'en-US' || $locale === 'en-GB') {
        return 'en';
    }
    $known = i18nAvailableLocales();
    return isset($known[$locale]) ? $locale : i18nDefaultLocale();
}

function i18nResolveLocale(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $fromQuery = isset($_GET['lang']) ? i18nNormalizeLocale((string) $_GET['lang']) : '';
    if ($fromQuery !== '' && isset($_GET['lang'])) {
        $resolved = $fromQuery;
        if (!headers_sent()) {
            setcookie('olm_lang', $resolved, [
                'expires' => time() + 86400 * 365,
                'path' => '/',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        return $resolved;
    }

    $fromCookie = isset($_COOKIE['olm_lang']) ? i18nNormalizeLocale((string) $_COOKIE['olm_lang']) : '';
    if ($fromCookie !== '' && isset(i18nAvailableLocales()[$fromCookie])) {
        $resolved = $fromCookie;
        return $resolved;
    }

    $resolved = i18nDefaultLocale();
    return $resolved;
}

function i18nHtmlLang(): string
{
    $locale = i18nResolveLocale();
    return $locale === 'en' ? 'en' : 'zh-Hant';
}

function i18nJsLocale(): string
{
    return i18nResolveLocale() === 'en' ? 'en-US' : 'zh-TW';
}

function i18nDictPath(string $locale): string
{
    $locale = i18nNormalizeLocale($locale);
    return dirname(__DIR__) . '/assets/i18n/' . $locale . '.json';
}

function i18nLoadDict(string $locale): array
{
    static $cache = [];
    $locale = i18nNormalizeLocale($locale);
    if (isset($cache[$locale])) {
        return $cache[$locale];
    }
    $path = i18nDictPath($locale);
    if (!is_file($path)) {
        $cache[$locale] = [];
        return $cache[$locale];
    }
    $raw = file_get_contents($path);
    $json = is_string($raw) ? json_decode($raw, true) : null;
    $cache[$locale] = is_array($json) ? $json : [];
    return $cache[$locale];
}

function i18nDict(): array
{
    static $merged = null;
    if ($merged !== null) {
        return $merged;
    }
    $fallback = i18nLoadDict(i18nDefaultLocale());
    $current = i18nLoadDict(i18nResolveLocale());
    $merged = array_merge($fallback, $current);
    return $merged;
}

/**
 * Translate with optional {name} placeholders.
 */
function t(string $key, array $vars = [], ?string $fallback = null): string
{
    $dict = i18nDict();
    $text = array_key_exists($key, $dict) ? (string) $dict[$key] : ($fallback ?? $key);
    if ($vars === []) {
        return $text;
    }
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function te(string $key, array $vars = [], ?string $fallback = null): string
{
    return htmlspecialchars(t($key, $vars, $fallback), ENT_QUOTES, 'UTF-8');
}

function i18nSourceLabel(string $sourceKey, array $source): string
{
    return t('source.' . $sourceKey . '.label', [], (string) ($source['label'] ?? $sourceKey));
}

function i18nSourceDescription(string $sourceKey, array $source): string
{
    return t('source.' . $sourceKey . '.description', [], (string) ($source['description'] ?? ''));
}

/**
 * Translate known default support copy; leave custom admin text unchanged.
 */
function i18nSupportField(string $field, string $value): string
{
    $value = trim($value);
    $defaults = [
        'title' => '支持 Open Live Map',
        'description' => '協助維持伺服器、資料整理與公開地圖服務。',
        'button_label' => '贊助本站',
        'contact_title' => '聯絡方式',
    ];
    $keys = [
        'title' => 'support.title',
        'description' => 'support.description',
        'button_label' => 'support.button',
        'contact_title' => 'support.contactTitle',
    ];
    if ($field === 'contact_title' && $value === '') {
        return t('support.contactDefault');
    }
    if (isset($defaults[$field], $keys[$field]) && $value === $defaults[$field]) {
        return t($keys[$field]);
    }
    return $value;
}

/**
 * Bundle for frontend JS.
 *
 * @return array{locale:string,htmlLang:string,jsLocale:string,messages:array<string,string>}
 */
function i18nBundle(): array
{
    return [
        'locale' => i18nResolveLocale(),
        'htmlLang' => i18nHtmlLang(),
        'jsLocale' => i18nJsLocale(),
        'messages' => i18nDict(),
    ];
}

function i18nMaybeRedirectCleanLangQuery(): void
{
    if (!isset($_GET['lang']) || headers_sent()) {
        return;
    }
    // Ensure cookie is written before the clean-URL redirect.
    i18nResolveLocale();
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($uri);
    $path = (string) ($parts['path'] ?? '/');
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }
    unset($query['lang']);
    $qs = http_build_query($query);
    $target = $path . ($qs !== '' ? ('?' . $qs) : '');
    header('Location: ' . $target, true, 302);
    exit;
}
