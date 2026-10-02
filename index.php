<?php
declare(strict_types=1);

require __DIR__ . '/lib/i18n.php';
// Resolve (and set cookie) before stripping ?lang= from the URL.
i18nResolveLocale();
i18nMaybeRedirectCleanLangQuery();

require __DIR__ . '/lib/source_config.php';
require __DIR__ . '/lib/monetization_config.php';
require __DIR__ . '/lib/ui_config.php';
require __DIR__ . '/lib/analytics.php';
require __DIR__ . '/lib/mapillary_config.php';

$siteName = 'Open Live Map';
$sourceConfig = loadSourceConfig();
$monetizationConfig = loadMonetizationConfig();
$uiConfig = loadUiConfig();
$mapillaryConfig = loadMapillaryConfig();
$mapillaryActive = mapillaryEnabled($mapillaryConfig);
$monitorOverlayAlpha = monitorOverlayAlphaCss($uiConfig);
$monitorOverlayBlur = monitorOverlayBlurCss($uiConfig);
$mapMarkerOpacity = mapMarkerOpacityCss($uiConfig);
analyticsLogVisit();
$adsenseActive = adsenseEnabled($monetizationConfig);
$assetVersion = static function (string $path): string {
    $fullPath = __DIR__ . '/' . ltrim($path, '/');
    return is_file($fullPath) ? (string) filemtime($fullPath) : (string) time();
};
$i18nBundleJson = json_encode(i18nBundle(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($i18nBundleJson === false) {
    $i18nBundleJson = '{"locale":"zh-Hant","htmlLang":"zh-Hant","jsLocale":"zh-TW","messages":{}}';
}
$mapillaryBootJson = json_encode(mapillaryPublicBoot($mapillaryConfig), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($mapillaryBootJson === false) {
    $mapillaryBootJson = '{"enabled":false,"radius_m":50}';
}
$currentLang = i18nResolveLocale();
?>
<!doctype html>
<html lang="<?= htmlspecialchars(i18nHtmlLang(), ENT_QUOTES, 'UTF-8') ?>" style="--monitor-overlay-alpha: <?= htmlspecialchars($monitorOverlayAlpha, ENT_QUOTES, 'UTF-8') ?>; --monitor-overlay-blur: <?= htmlspecialchars($monitorOverlayBlur, ENT_QUOTES, 'UTF-8') ?>; --map-marker-opacity: <?= htmlspecialchars($mapMarkerOpacity, ENT_QUOTES, 'UTF-8') ?>;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= te('meta.description') ?>">
    <meta name="theme-color" content="#0c1218">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Open Live Map">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="manifest" href="manifest.webmanifest?v=<?= htmlspecialchars($assetVersion('manifest.webmanifest'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" href="assets/images/favicon-32.png?v=<?= htmlspecialchars($assetVersion('assets/images/favicon-32.png'), ENT_QUOTES, 'UTF-8') ?>" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="assets/images/app-icon-192.png?v=<?= htmlspecialchars($assetVersion('assets/images/app-icon-192.png'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+TC:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= htmlspecialchars($assetVersion('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($adsenseActive): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars((string) $monetizationConfig['adsense']['client'], ENT_QUOTES, 'UTF-8') ?>" crossorigin="anonymous"></script>
    <?php endif; ?>
</head>
<body>
    <script type="application/json" id="i18n-bundle"><?= $i18nBundleJson ?></script>
    <?php if ($mapillaryActive): ?>
    <script type="application/json" id="mapillary-boot"><?= $mapillaryBootJson ?></script>
    <?php endif; ?>
    <div class="app-shell">
        <button class="panel-peek-toggle" type="button" id="panelPeekBtn" aria-label="<?= te('aria.expandMenu') ?>" aria-expanded="false" title="<?= te('aria.expandMenu') ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"></path></svg>
        </button>
        <aside class="side-panel" aria-label="<?= te('aria.sidePanel') ?>">
            <header class="brand-block">
                <a class="brand" href="./" aria-label="<?= te('brand.home') ?>">
                    <span class="brand-mark" aria-hidden="true"></span>
                    <span>
                        <strong>Open Live Map</strong>
                        <small><?= te('brand.tagline') ?></small>
                    </span>
                </a>
                <span class="panel-actions">
                    <div class="lang-switch" role="group" aria-label="<?= te('lang.label') ?>">
                        <?php foreach (i18nAvailableLocales() as $code => $label): ?>
                            <a class="lang-switch-btn<?= $currentLang === $code ? ' is-active' : '' ?>" href="?lang=<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" hreflang="<?= htmlspecialchars($code === 'en' ? 'en' : 'zh-Hant', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endforeach; ?>
                    </div>
                    <button class="menu-expand-toggle" type="button" id="menuExpandToggle" title="<?= te('aria.expandAllMenusTitle') ?>" aria-label="<?= te('aria.expandAllMenus') ?>" aria-expanded="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7l4-4 4 4"></path><path d="M12 3v7"></path><path d="M8 17l4 4 4-4"></path><path d="M12 14v7"></path></svg>
                    </button>
                    <button class="panel-toggle" type="button" aria-label="<?= te('aria.collapsePanel') ?>" aria-expanded="true"></button>
                </span>
            </header>

            <details class="menu-section location-card" data-menu-section="location" open>
                <summary><span><small><?= te('section.location.kicker') ?></small><strong><?= te('section.location.title') ?></strong></span></summary>
                <section class="menu-section-body">
                    <h1><?= te('section.location.heading') ?></h1>
                    <p><?= te('section.location.lead') ?></p>
                    <div class="action-row">
                        <button class="button primary" type="button" id="locateBtn"><?= te('btn.locate') ?></button>
                        <button class="button quiet" type="button" id="installAppBtn" hidden><?= te('btn.installApp') ?></button>
                        <button class="button quiet" type="button" id="pickLocationBtn"><?= te('btn.pickLocation') ?></button>
                        <button class="button quiet" type="button" id="refreshBtn"><?= te('btn.refresh') ?></button>
                    </div>
                    <p class="status-line" id="locationStatus"><?= te('status.autoLocate') ?></p>
                </section>
            </details>

            <details class="menu-section" data-menu-section="saved-locations">
                <summary><span><small><?= te('section.places.kicker') ?></small><strong><?= te('section.places.title') ?></strong></span></summary>
                <section class="menu-section-body">
                    <p class="control-note"><?= te('section.places.note') ?></p>
                    <div class="saved-location-panel" aria-label="<?= te('section.places.aria') ?>">
                        <div class="saved-location-list" id="savedLocationList" hidden></div>
                    </div>
                </section>
            </details>

            <details class="menu-section" data-menu-section="monitor">
                <summary><span><small><?= te('section.monitor.kicker') ?></small><strong><?= te('section.monitor.title') ?></strong></span></summary>
                <section class="menu-section-body">
                    <p class="control-note"><?= te('section.monitor.note') ?></p>
                    <div class="saved-location-panel" aria-label="<?= te('section.monitor.aria') ?>">
                        <p class="monitor-preset-current" id="monitorPresetCurrent"><?= te('monitor.none') ?></p>
                        <div class="saved-location-form monitor-preset-form">
                            <input type="text" id="monitorPresetName" maxlength="32" autocomplete="off" placeholder="<?= te('monitor.namePlaceholder') ?>">
                            <button class="button quiet" type="button" id="saveMonitorPresetBtn"><?= te('monitor.saveAs') ?></button>
                        </div>
                        <div class="saved-location-list monitor-preset-list" id="monitorPresetList" hidden></div>
                    </div>
                </section>
            </details>

            <details class="menu-section" data-menu-section="custom-cctv">
                <summary><span><small><?= te('section.camera.kicker') ?></small><strong><?= te('section.camera.title') ?></strong></span></summary>
                <section class="menu-section-body">
                    <p class="control-note"><?= te('section.camera.note') ?></p>
                    <form class="user-cctv-form" id="userCctvForm">
                        <input type="hidden" id="userCctvId">
                        <label>
                            <?= te('camera.name') ?>
                            <input type="text" id="userCctvName" maxlength="48" autocomplete="off" placeholder="<?= te('camera.namePlaceholder') ?>">
                        </label>
                        <label>
                            <?= te('camera.url') ?>
                            <input type="url" id="userCctvUrl" autocomplete="off" placeholder="https://www.youtube.com/watch?v=...">
                        </label>
                        <label>
                            <?= te('camera.coord') ?>
                            <input type="text" id="userCctvCoord" inputmode="decimal" autocomplete="off" placeholder="35.6586, 139.7454">
                        </label>
                        <div class="user-cctv-actions">
                            <button class="button primary" type="submit" id="userCctvSaveBtn"><?= te('camera.add') ?></button>
                            <button class="button quiet" type="button" id="userCctvCenterBtn"><?= te('camera.useMapCenter') ?></button>
                            <button class="button quiet" type="button" id="userCctvClearBtn"><?= te('camera.clear') ?></button>
                        </div>
                    </form>
                    <div class="user-cctv-transfer">
                        <button class="button quiet" type="button" id="userCctvExportBtn"><?= te('camera.export') ?></button>
                        <button class="button quiet" type="button" id="userCctvImportBtn"><?= te('camera.import') ?></button>
                        <input type="file" id="userCctvImportFile" accept="application/json,.json" hidden>
                    </div>
                    <div class="saved-location-list user-cctv-list" id="userCctvList" hidden></div>
                </section>
            </details>

            <?php if (supportEnabled($monetizationConfig) || ($adsenseActive && adsenseSlot($monetizationConfig, 'sidebar') !== '')): ?>
            <details class="menu-section support-section" data-menu-section="support">
                <summary><span><small><?= te('section.support.kicker') ?></small><strong><?= te('section.support.title') ?></strong></span></summary>
                <section class="menu-section-body">
                    <?php if (supportEnabled($monetizationConfig)): ?>
                    <div class="support-box">
                        <strong><?= htmlspecialchars(i18nSupportField('title', (string) $monetizationConfig['support']['title']), ENT_QUOTES, 'UTF-8') ?></strong>
                        <p><?= htmlspecialchars(i18nSupportField('description', (string) $monetizationConfig['support']['description']), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (trim((string) $monetizationConfig['support']['url']) !== ''): ?>
                        <a class="button primary" href="<?= htmlspecialchars((string) $monetizationConfig['support']['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(i18nSupportField('button_label', (string) $monetizationConfig['support']['button_label']), ENT_QUOTES, 'UTF-8') ?></a>
                        <?php else: ?>
                        <span class="support-placeholder"><?= te('support.linkMissing') ?></span>
                        <?php endif; ?>
                        <?php
                            $supportContactEmail = trim((string) ($monetizationConfig['support']['contact_email'] ?? ''));
                            $supportContactUrl = trim((string) ($monetizationConfig['support']['contact_url'] ?? ''));
                            $supportContactNote = trim((string) ($monetizationConfig['support']['contact_note'] ?? ''));
                        ?>
                        <div class="support-contact">
                            <strong><?= htmlspecialchars(i18nSupportField('contact_title', (string) ($monetizationConfig['support']['contact_title'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                            <?php if ($supportContactEmail !== ''): ?>
                            <a href="mailto:<?= htmlspecialchars($supportContactEmail, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($supportContactEmail, ENT_QUOTES, 'UTF-8') ?></a>
                            <?php endif; ?>
                            <?php if ($supportContactUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($supportContactUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) (($monetizationConfig['support']['contact_url_label'] ?? '') ?: t('support.contactUs')), ENT_QUOTES, 'UTF-8') ?></a>
                            <?php endif; ?>
                            <?php if ($supportContactNote !== ''): ?>
                            <small><?= htmlspecialchars($supportContactNote, ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                            <?php if ($supportContactEmail === '' && $supportContactUrl === '' && $supportContactNote === ''): ?>
                            <small class="support-contact-empty"><?= te('support.contactEmpty') ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($adsenseActive && adsenseSlot($monetizationConfig, 'sidebar') !== ''): ?>
                    <div class="ad-slot ad-slot-sidebar" aria-label="<?= te('aria.sponsoredAd') ?>">
                        <ins class="adsbygoogle"
                             style="display:block"
                             data-ad-client="<?= htmlspecialchars((string) $monetizationConfig['adsense']['client'], ENT_QUOTES, 'UTF-8') ?>"
                             data-ad-slot="<?= htmlspecialchars(adsenseSlot($monetizationConfig, 'sidebar'), ENT_QUOTES, 'UTF-8') ?>"
                             data-ad-format="auto"
                             data-full-width-responsive="true"></ins>
                    </div>
                    <?php endif; ?>
                </section>
            </details>
            <?php endif; ?>

            <details class="menu-section" data-menu-section="search">
                <summary><span><small><?= te('section.search.kicker') ?></small><strong><?= te('section.search.title') ?></strong></span></summary>
                <section class="menu-section-body">
                    <form class="search-box" id="placeSearchForm" role="search">
                        <label for="placeSearchInput">
                            <span><?= te('search.label') ?></span>
                        </label>
                        <div class="search-row">
                            <input type="search" id="placeSearchInput" autocomplete="off" inputmode="search" placeholder="<?= te('search.placeholder') ?>">
                            <button class="button primary" type="submit" id="placeSearchBtn"><?= te('search.button') ?></button>
                        </div>
                        <p class="search-status" id="placeSearchStatus"><?= te('search.hint') ?></p>
                        <div class="place-results" id="placeSearchResults" hidden></div>
                    </form>
                </section>
            </details>

            <details class="menu-section" data-menu-section="layers" open>
                <summary><span><small><?= te('section.layers.kicker') ?></small><strong><?= te('section.layers.title') ?></strong></span></summary>
                <section class="menu-section-body">
                    <p class="control-note"><?= te('section.layers.note') ?></p>
                    <div class="toggle-group">
                    <?php if (sourceEnabled($sourceConfig, 'garbage')): ?>
                    <label class="toggle-row">
                        <input type="checkbox" class="source-toggle" value="garbage"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'garbage')) ?>>
                        <span><strong><?= htmlspecialchars(i18nSourceLabel('garbage', $sourceConfig['garbage']), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(i18nSourceDescription('garbage', $sourceConfig['garbage']), ENT_QUOTES, 'UTF-8') ?></small></span>
                    </label>
                    <?php endif; ?>
                    <?php if (sourceEnabled($sourceConfig, 'weather')): ?>
                    <label class="toggle-row">
                        <input type="checkbox" id="weatherLayerToggle"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'weather')) ?>>
                        <span><strong><?= htmlspecialchars(i18nSourceLabel('weather', $sourceConfig['weather']), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(i18nSourceDescription('weather', $sourceConfig['weather']), ENT_QUOTES, 'UTF-8') ?></small></span>
                    </label>
                    <div class="weather-field-panel" id="weatherFieldPanel" hidden>
                        <div class="cctv-source-head">
                            <strong><?= te('weather.fieldsTitle') ?></strong>
                            <span>
                                <button type="button" id="weatherFieldAllBtn"><?= te('btn.selectAll') ?></button>
                                <button type="button" id="weatherFieldNoneBtn"><?= te('btn.selectNone') ?></button>
                            </span>
                        </div>
                        <div class="cctv-source-list" id="weatherFieldList">
                            <label class="cctv-source-option"><span><input type="checkbox" class="weather-field-toggle" value="temperature" checked> <?= te('weather.temperature') ?></span><small><?= te('weather.priority') ?></small></label>
                            <label class="cctv-source-option"><span><input type="checkbox" class="weather-field-toggle" value="rain" checked> <?= te('weather.rain') ?></span><small>mm</small></label>
                            <label class="cctv-source-option"><span><input type="checkbox" class="weather-field-toggle" value="humidity" checked> <?= te('weather.humidity') ?></span><small>%</small></label>
                            <label class="cctv-source-option"><span><input type="checkbox" class="weather-field-toggle" value="wind_speed" checked> <?= te('weather.wind') ?></span><small>m/s</small></label>
                            <label class="cctv-source-option"><span><input type="checkbox" class="weather-field-toggle" value="weather" checked> <?= te('weather.condition') ?></span><small><?= te('weather.text') ?></small></label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (sourceEnabled($sourceConfig, 'realprice')): ?>
                    <label class="toggle-row">
                        <input type="checkbox" id="realpriceLayerToggle"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'realprice')) ?>>
                        <span><strong><?= htmlspecialchars(i18nSourceLabel('realprice', $sourceConfig['realprice']), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(i18nSourceDescription('realprice', $sourceConfig['realprice']), ENT_QUOTES, 'UTF-8') ?></small></span>
                    </label>
                    <div class="realprice-field-panel" id="realpricePanel" hidden>
                        <div class="cctv-source-head">
                            <strong><?= te('realprice.panelTitle') ?></strong>
                        </div>
                        <div class="realprice-filter-grid">
                            <label for="realpricePeriod"><?= te('realprice.period') ?>
                                <select id="realpricePeriod">
                                    <option value="12"><?= te('realprice.period.12') ?></option>
                                    <option value="6"><?= te('realprice.period.6') ?></option>
                                    <option value="3"><?= te('realprice.period.3') ?></option>
                                </select>
                            </label>
                            <label for="realpriceType"><?= te('realprice.type') ?>
                                <select id="realpriceType">
                                    <option value="sale"><?= te('realprice.type.sale') ?></option>
                                    <option value="presale"><?= te('realprice.type.presale') ?></option>
                                    <option value="rent"><?= te('realprice.type.rent') ?></option>
                                </select>
                            </label>
                        </div>
                        <p class="control-note" id="realpriceStatusText"><?= te('realprice.statusHint') ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (sourceEnabled($sourceConfig, 'cctv-cache')): ?>
                    <label class="toggle-row">
                        <input type="checkbox" class="source-toggle" value="cctv-cache"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'cctv-cache')) ?>>
                        <span><strong><?= htmlspecialchars(i18nSourceLabel('cctv-cache', $sourceConfig['cctv-cache']), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(i18nSourceDescription('cctv-cache', $sourceConfig['cctv-cache']), ENT_QUOTES, 'UTF-8') ?></small></span>
                    </label>
                    <div class="cctv-source-panel" id="cctvSourcePanel" hidden>
                        <div class="cctv-source-head">
                            <strong><?= te('cctv.sourcesTitle') ?></strong>
                            <span>
                                <button type="button" id="cctvSourceAllBtn"><?= te('btn.selectAll') ?></button>
                                <button type="button" id="cctvSourceNoneBtn"><?= te('btn.selectNone') ?></button>
                            </span>
                        </div>
                        <div class="cctv-source-list" id="cctvSourceList"></div>
                    </div>
                    <?php endif; ?>
                    <?php if (sourceEnabled($sourceConfig, 'stale')): ?>
                    <label class="toggle-row stale-toggle-row">
                        <input type="checkbox" id="stalePanelToggle"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'stale')) ?>>
                        <span><strong><?= htmlspecialchars(i18nSourceLabel('stale', $sourceConfig['stale']), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars(i18nSourceDescription('stale', $sourceConfig['stale']), ENT_QUOTES, 'UTF-8') ?></small></span>
                    </label>
                    <?php endif; ?>
                    </div>
                </section>
            </details>

            <details class="menu-section" data-menu-section="stats">
                <summary><span><small><?= te('section.stats.kicker') ?></small><strong><?= te('section.stats.title') ?></strong></span></summary>
                <section class="menu-section-body">
                    <div class="summary-grid" aria-label="<?= te('section.stats.aria') ?>">
                        <div>
                            <strong id="visibleCount">0</strong>
                            <span><?= te('stats.visible') ?></span>
                        </div>
                        <div>
                            <strong id="liveCount">0</strong>
                            <span><?= te('stats.live') ?></span>
                        </div>
                        <div>
                            <strong id="nearestDistance">--</strong>
                            <span><?= te('stats.nearest') ?></span>
                        </div>
                    </div>
                </section>
            </details>

            <details class="menu-section" data-menu-section="nearby">
                <summary><span><small><?= te('section.nearby.kicker') ?></small><strong><?= te('section.nearby.title') ?></strong></span></summary>
                <section class="menu-section-body spot-list-wrap" aria-label="<?= te('section.nearby.aria') ?>">
                    <div class="list-head">
                        <div>
                            <strong><?= te('nearby.heading') ?></strong>
                        </div>
                        <button class="text-button" type="button" id="fitBtn"><?= te('nearby.fit') ?></button>
                    </div>
                    <div class="spot-list" id="vehicleList"></div>
                </section>
            </details>

            <footer class="panel-foot">
                <nav class="legal-links" aria-label="<?= te('footer.legal') ?>">
                    <a href="legal.php?page=privacy"><?= te('footer.privacy') ?></a>
                    <a href="legal.php?page=terms"><?= te('footer.terms') ?></a>
                    <a href="legal.php?page=sources"><?= te('footer.sources') ?></a>
                </nav>
                <div class="source-row">
                    <span><?= te('footer.source') ?></span>
                    <code id="sourceName"><?= te('footer.notLoaded') ?></code>
                </div>
                <div class="source-row">
                    <span><?= te('footer.updated') ?></span>
                    <code id="updatedAt">--</code>
                </div>
                <div class="source-row">
                    <span><?= te('footer.monitor') ?></span>
                    <code><span id="monitorCount">0</span></code>
                </div>
            </footer>
        </aside>

        <main class="map-stage" aria-label="<?= te('map.aria') ?>">
            <div id="map"></div>

            <div class="map-toolbar" aria-label="<?= te('map.tools') ?>">
                <button class="icon-button" type="button" id="zoomUserBtn" title="<?= te('map.zoomUser') ?>" aria-label="<?= te('map.zoomUser') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21"></path><circle cx="12" cy="12" r="7.25"></circle></svg>
                </button>
                <button class="icon-button" type="button" id="basemapBtn" title="<?= te('map.basemap') ?>" aria-label="<?= te('map.basemap') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3.5 7.2 5.7-3 5.6 3 5.7-3v12.6l-5.7 3-5.6-3-5.7 3Z"></path><path d="M9.2 4.2v12.6M14.8 7.2v12.6"></path></svg>
                </button>
                <button class="icon-button" type="button" id="pickMapBtn" title="<?= te('map.pick') ?>" aria-label="<?= te('map.pick') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s6.5-5.8 6.5-11a6.5 6.5 0 1 0-13 0c0 5.2 6.5 11 6.5 11Z"></path><circle cx="12" cy="10" r="2.35"></circle></svg>
                </button>
                <button class="icon-button" type="button" id="measureBtn" title="<?= te('map.measure') ?>" aria-label="<?= te('map.measure') ?>" aria-pressed="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.8 19.2 19.2 4.8"></path><path d="M7.2 4.8H19.2V16.8"></path><path d="M4.8 19.2h4.2M4.8 19.2v-4.2M15.6 7.2h3.6M16.8 4.8v3.6"></path></svg>
                </button>
                <?php if ($mapillaryActive): ?>
                <button class="icon-button" type="button" id="streetViewBtn" title="<?= te('map.streetView') ?>" aria-label="<?= te('map.streetView') ?>" aria-pressed="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7.2" r="2.4"></circle><path d="M8.4 21v-6.2L7 10.8c1.4-.9 3.1-1.4 5-1.4s3.6.5 5 1.4L15.6 14.8V21"></path><path d="M4.8 21h14.4"></path></svg>
                </button>
                <?php endif; ?>
                <button class="icon-button" type="button" id="refreshMapBtn" title="<?= te('map.refresh') ?>" aria-label="<?= te('map.refresh') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.6" y="4.4" width="16.8" height="12.2" rx="2.2"></rect><path d="M15.8 9a3.2 3.2 0 1 0-3.1 4.4"></path><path d="M15.8 7.2v2.6h-2.6"></path></svg>
                </button>
                <button class="icon-button monitor-toolbar-button" type="button" id="pinAllMonitorsBtn" title="<?= te('map.pinAll') ?>" aria-label="<?= te('map.pinAll') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17v4"></path><path d="M9.2 3.8h5.6l1.6 5.4-2.8 2.2V14H10.4V11.4L7.6 9.2Z"></path></svg>
                </button>
                <button class="icon-button monitor-toolbar-button" type="button" id="unpinAllMonitorsBtn" title="<?= te('map.unpinAll') ?>" aria-label="<?= te('map.unpinAll') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v10"></path><path d="m8.8 9.8 3.2 3.2 3.2-3.2"></path><path d="M5.5 17.5h13"></path><path d="M7.5 20.5h9"></path></svg>
                </button>
                <button class="icon-button monitor-toolbar-button" type="button" id="minimapToggleBtn" title="<?= te('map.minimap') ?>" aria-label="<?= te('map.minimap') ?>" aria-pressed="true" hidden>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2.2"></rect><rect x="12.5" y="12.5" width="5.5" height="5.5" rx="1"></rect><circle cx="8.2" cy="9" r="1.35"></circle><circle cx="14.5" cy="8.2" r="1.1"></circle></svg>
                </button>
                <button class="icon-button" type="button" id="togglePanelBtn" title="<?= te('map.togglePanel') ?>" aria-label="<?= te('map.togglePanel') ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.5 7.25h13"></path><path d="M5.5 12h13"></path><path d="M5.5 16.75h13"></path></svg>
                </button>
                <div class="basemap-menu" id="basemapMenu" hidden aria-label="<?= te('map.basemapModes') ?>"></div>
            </div>

            <div class="map-load-status idle" id="mapLoadStatus" aria-live="polite">
                <span class="load-dot" aria-hidden="true"></span>
                <strong><?= te('map.loadWaiting') ?></strong>
                <small><?= te('map.loadHint') ?></small>
            </div>

            <div class="measure-readout" id="measureReadout" hidden><?= te('map.measureReadout', ['distance' => '0 m']) ?></div>

            <div class="cctv-monitor-dock" id="cctvMonitorDock" aria-label="<?= te('map.monitorDock') ?>"></div>

            <aside class="pinned-minimap" id="pinnedMinimap" hidden aria-label="<?= te('map.minimap') ?>">
                <header class="pinned-minimap-header">
                    <strong><?= te('map.minimapTitle') ?></strong>
                    <span class="pinned-minimap-actions">
                        <button type="button" class="pinned-minimap-fit" id="pinnedMinimapFitBtn" title="<?= te('map.minimapFit') ?>" aria-label="<?= te('map.minimapFit') ?>">⤢</button>
                        <button type="button" class="pinned-minimap-close" id="pinnedMinimapCloseBtn" title="<?= te('map.minimapHide') ?>" aria-label="<?= te('map.minimapHide') ?>">×</button>
                    </span>
                </header>
                <div class="pinned-minimap-map" id="pinnedMinimapMap" role="presentation"></div>
            </aside>

            <?php if ($mapillaryActive): ?>
            <aside class="streetview-panel" id="streetViewPanel" hidden aria-label="<?= te('map.streetView') ?>">
                <header class="streetview-panel-header">
                    <div class="streetview-panel-heading">
                        <strong id="streetViewPanelTitle"><?= te('map.streetView') ?></strong>
                        <small class="streetview-panel-hud" id="streetViewHud" hidden></small>
                    </div>
                    <span class="streetview-panel-actions">
                        <button type="button" class="streetview-panel-max" id="streetViewMaxBtn" title="<?= te('map.streetViewMaximize') ?>" aria-label="<?= te('map.streetViewMaximize') ?>" aria-pressed="false">⛶</button>
                        <button type="button" class="streetview-panel-close" id="streetViewCloseBtn" title="<?= te('map.streetViewClose') ?>" aria-label="<?= te('map.streetViewClose') ?>">×</button>
                    </span>
                </header>
                <div class="streetview-panel-body">
                    <p class="streetview-panel-status" id="streetViewStatus" hidden></p>
                    <div id="streetViewViewer" class="streetview-viewer" hidden role="region" aria-label="<?= te('map.streetView') ?>"></div>
                </div>
                <div class="streetview-panel-resize" id="streetViewResizeHandle" title="<?= te('map.streetViewResize') ?>" aria-hidden="true"></div>
            </aside>
            <?php endif; ?>

            <?php if ($adsenseActive && adsenseSlot($monetizationConfig, 'map') !== ''): ?>
            <aside class="map-ad-slot" aria-label="<?= te('aria.sponsoredAd') ?>">
                <button type="button" class="map-ad-close" aria-label="<?= te('aria.closeAd') ?>">×</button>
                <ins class="adsbygoogle"
                     style="display:block"
                     data-ad-client="<?= htmlspecialchars((string) $monetizationConfig['adsense']['client'], ENT_QUOTES, 'UTF-8') ?>"
                     data-ad-slot="<?= htmlspecialchars(adsenseSlot($monetizationConfig, 'map'), ENT_QUOTES, 'UTF-8') ?>"
                     data-ad-format="auto"
                     data-full-width-responsive="true"></ins>
            </aside>
            <?php endif; ?>

            <div class="legend" aria-label="<?= te('map.legend') ?>">
                <?php if (sourceEnabled($sourceConfig, 'garbage')): ?>
                <label class="legend-toggle">
                    <input type="checkbox" class="legend-source-toggle" value="garbage"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'garbage')) ?>>
                    <span class="legend-dot garbage" aria-hidden="true"></span>
                    <span><?= htmlspecialchars(i18nSourceLabel('garbage', $sourceConfig['garbage']), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endif; ?>
                <?php if (sourceEnabled($sourceConfig, 'weather')): ?>
                <label class="legend-toggle">
                    <input type="checkbox" id="legendWeatherToggle"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'weather')) ?>>
                    <span class="legend-dot weather" aria-hidden="true"></span>
                    <span><?= htmlspecialchars(i18nSourceLabel('weather', $sourceConfig['weather']), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endif; ?>
                <?php if (sourceEnabled($sourceConfig, 'realprice')): ?>
                <label class="legend-toggle">
                    <input type="checkbox" id="legendRealpriceToggle"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'realprice')) ?>>
                    <span class="legend-dot realprice" aria-hidden="true"></span>
                    <span><?= htmlspecialchars(i18nSourceLabel('realprice', $sourceConfig['realprice']), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endif; ?>
                <?php if (sourceEnabled($sourceConfig, 'cctv-cache')): ?>
                <label class="legend-toggle">
                    <input type="checkbox" class="legend-source-toggle" value="cctv-cache"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'cctv-cache')) ?>>
                    <span class="legend-dot cctv" aria-hidden="true"></span>
                    <span><?= htmlspecialchars(i18nSourceLabel('cctv-cache', $sourceConfig['cctv-cache']), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endif; ?>
                <?php if (sourceEnabled($sourceConfig, 'stale')): ?>
                <label class="legend-toggle stale-legend-toggle">
                    <input type="checkbox" id="staleLayerToggle"<?= checkedAttr(sourceDefaultChecked($sourceConfig, 'stale')) ?>>
                    <span class="legend-dot stale" aria-hidden="true"></span>
                    <span><?= htmlspecialchars(i18nSourceLabel('stale', $sourceConfig['stale']), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <template id="vehicleTemplate">
        <article class="vehicle-card">
            <button type="button">
                <span class="vehicle-type">BUS</span>
                <strong></strong>
                <small></small>
                <span class="vehicle-meta"></span>
            </button>
        </article>
    </template>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <?php if ($adsenseActive): ?>
    <script>
        document.querySelectorAll('.adsbygoogle').forEach(() => {
            try { (adsbygoogle = window.adsbygoogle || []).push({}); } catch (error) {}
        });
    </script>
    <?php endif; ?>
    <script src="assets/js/main.js?v=<?= htmlspecialchars($assetVersion('assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
