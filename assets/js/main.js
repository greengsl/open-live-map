const appShell = document.querySelector('.app-shell');

const i18nBundle = (() => {
    try {
        const el = document.querySelector('#i18n-bundle');
        if (!el?.textContent) return {};
        return JSON.parse(el.textContent);
    } catch (_) {
        return {};
    }
})();
const i18nMessages = (i18nBundle && typeof i18nBundle.messages === 'object' && i18nBundle.messages) ? i18nBundle.messages : {};
const i18nLocale = String(i18nBundle.locale || 'zh-Hant');
const i18nJsLocale = String(i18nBundle.jsLocale || 'zh-TW');

function t(key, vars = {}) {
    let text = Object.prototype.hasOwnProperty.call(i18nMessages, key)
        ? String(i18nMessages[key])
        : String(key);
    Object.keys(vars || {}).forEach((name) => {
        text = text.split(`{${name}}`).join(String(vars[name]));
    });
    return text;
}

function fmtNum(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return String(value ?? '');
    return n.toLocaleString(i18nJsLocale);
}

try {
    localStorage.setItem('olm_lang', i18nLocale);
} catch (_) {}
document.querySelectorAll('.lang-switch-btn').forEach((link) => {
    link.addEventListener('click', () => {
        try {
            const url = new URL(link.href, window.location.href);
            const lang = url.searchParams.get('lang');
            if (lang) localStorage.setItem('olm_lang', lang);
        } catch (_) {}
    });
});

const locateBtn = document.querySelector('#locateBtn');
const installAppBtn = document.querySelector('#installAppBtn');
const pickLocationBtn = document.querySelector('#pickLocationBtn');
const refreshBtn = document.querySelector('#refreshBtn');
const placeSearchForm = document.querySelector('#placeSearchForm');
const placeSearchInput = document.querySelector('#placeSearchInput');
const placeSearchStatus = document.querySelector('#placeSearchStatus');
const placeSearchResults = document.querySelector('#placeSearchResults');
const zoomUserBtn = document.querySelector('#zoomUserBtn');
const basemapBtn = document.querySelector('#basemapBtn');
const basemapMenu = document.querySelector('#basemapMenu');
let pickMapBtn = document.querySelector('#pickMapBtn');
const measureBtn = document.querySelector('#measureBtn');
const clearMeasureBtn = document.querySelector('#clearMeasureBtn');
let refreshMapBtn = document.querySelector('#refreshMapBtn');
const streetViewBtn = document.querySelector('#streetViewBtn');
const streetViewPanel = document.querySelector('#streetViewPanel');
const streetViewCloseBtn = document.querySelector('#streetViewCloseBtn');
const streetViewMaxBtn = document.querySelector('#streetViewMaxBtn');
const streetViewResizeHandle = document.querySelector('#streetViewResizeHandle');
const streetViewStatus = document.querySelector('#streetViewStatus');
const streetViewViewerEl = document.querySelector('#streetViewViewer');
const streetViewPanelTitle = document.querySelector('#streetViewPanelTitle');
const streetViewHud = document.querySelector('#streetViewHud');
const streetViewPanelBody = streetViewPanel?.querySelector('.streetview-panel-body') || null;
const pinAllMonitorsBtn = document.querySelector('#pinAllMonitorsBtn');
const unpinAllMonitorsBtn = document.querySelector('#unpinAllMonitorsBtn');
const minimapToggleBtn = document.querySelector('#minimapToggleBtn');
const pinnedMinimapEl = document.querySelector('#pinnedMinimap');
const pinnedMinimapMapEl = document.querySelector('#pinnedMinimapMap');
const pinnedMinimapFitBtn = document.querySelector('#pinnedMinimapFitBtn');
const pinnedMinimapCloseBtn = document.querySelector('#pinnedMinimapCloseBtn');
const togglePanelBtn = document.querySelector('#togglePanelBtn');
const panelToggle = document.querySelector('.panel-toggle');
const panelPeekBtn = document.querySelector('#panelPeekBtn');
const menuExpandToggle = document.querySelector('#menuExpandToggle');
const vehicleList = document.querySelector('#vehicleList');
const vehicleTemplate = document.querySelector('#vehicleTemplate');
const locationStatus = document.querySelector('#locationStatus');
let mapLoadStatus = document.querySelector('#mapLoadStatus');
const updatedAt = document.querySelector('#updatedAt');
const sourceName = document.querySelector('#sourceName');
const measureReadout = document.querySelector('#measureReadout');
const cctvMonitorDock = document.querySelector('#cctvMonitorDock');
const visibleCount = document.querySelector('#visibleCount');
const liveCount = document.querySelector('#liveCount');
const nearestDistance = document.querySelector('#nearestDistance');
const monitorCount = document.querySelector('#monitorCount');
const fitBtn = document.querySelector('#fitBtn');
const citySelect = document.querySelector('#citySelect');
const sourceToggles = Array.from(document.querySelectorAll('.source-toggle'));
const legendSourceToggles = Array.from(document.querySelectorAll('.legend-source-toggle'));
const weatherLayerToggle = document.querySelector('#weatherLayerToggle');
const legendWeatherToggle = document.querySelector('#legendWeatherToggle');
const weatherFieldPanel = document.querySelector('#weatherFieldPanel');
const weatherFieldToggles = Array.from(document.querySelectorAll('.weather-field-toggle'));
const weatherFieldAllBtn = document.querySelector('#weatherFieldAllBtn');
const weatherFieldNoneBtn = document.querySelector('#weatherFieldNoneBtn');
const realpriceLayerToggle = document.querySelector('#realpriceLayerToggle');
const legendRealpriceToggle = document.querySelector('#legendRealpriceToggle');
const realpricePanel = document.querySelector('#realpricePanel');
const realpriceStatusText = document.querySelector('#realpriceStatusText');
const realpricePeriod = document.querySelector('#realpricePeriod');
const realpriceType = document.querySelector('#realpriceType');
const staleLayerToggle = document.querySelector('#staleLayerToggle');
const stalePanelToggle = document.querySelector('#stalePanelToggle');
const staleLayerControl = document.querySelector('.stale-legend-toggle');
const stalePanelControl = document.querySelector('.stale-toggle-row');
const cctvSourcePanel = document.querySelector('#cctvSourcePanel');
const cctvSourceList = document.querySelector('#cctvSourceList');
const cctvSourceAllBtn = document.querySelector('#cctvSourceAllBtn');
const cctvSourceNoneBtn = document.querySelector('#cctvSourceNoneBtn');
const savedLocationList = document.querySelector('#savedLocationList');
const monitorPresetName = document.querySelector('#monitorPresetName');
const saveMonitorPresetBtn = document.querySelector('#saveMonitorPresetBtn');
const monitorPresetList = document.querySelector('#monitorPresetList');
const monitorPresetCurrent = document.querySelector('#monitorPresetCurrent');
const userCctvForm = document.querySelector('#userCctvForm');
const userCctvIdInput = document.querySelector('#userCctvId');
const userCctvNameInput = document.querySelector('#userCctvName');
const userCctvUrlInput = document.querySelector('#userCctvUrl');
const userCctvCoordInput = document.querySelector('#userCctvCoord');
const userCctvSaveBtn = document.querySelector('#userCctvSaveBtn');
const userCctvCenterBtn = document.querySelector('#userCctvCenterBtn');
const userCctvClearBtn = document.querySelector('#userCctvClearBtn');
const userCctvExportBtn = document.querySelector('#userCctvExportBtn');
const userCctvImportBtn = document.querySelector('#userCctvImportBtn');
const userCctvImportFile = document.querySelector('#userCctvImportFile');
const userCctvList = document.querySelector('#userCctvList');
const mapAdClose = document.querySelector('.map-ad-close');

const defaultCenter = [25.033964, 121.564468];
let userPosition = null;
let currentGpsPosition = null;
let userMarker = null;
let userPositionMarkerKind = 'gps';
let placeSearchMarker = null;
let vehicles = [];
let vehicleCanvasLayer = null;
let weatherLayer = null;
let cctvClusterLayer = null;
let pinnedMonitorLayer = null;
let pinnedMinimapMap = null;
let pinnedMinimapMarkersLayer = null;
let pinnedMinimapViewportRect = null;
let pinnedMinimapEnabled = true;
let pinnedMinimapLastPinKey = '';
let realpriceLayer = null;
let weatherStations = [];
let weatherLoaded = false;
let weatherLoading = false;
let realpriceLoading = false;
let realpriceLoaded = false;
let realpriceRequestSeq = 0;
let realpriceLoadTimer = 0;
let realpriceClusters = [];
let realpriceItems = [];
let realpriceDistrictSummaries = [];
let realpriceDistrictClusters = [];
let realpriceClusterHideZoomMin = 11;
let realpriceDistrictClusterZoomMin = 11;
let realpriceItemZoomMin = 14;
let drawnVehicleHits = [];
let canvasHoverTooltip = null;
let selectedVehicleId = null;
let monitoredVehicles = new Map();
let positionAnimations = new Map();
let pickingLocation = false;
let measuringDistance = false;
let streetViewPicking = false;
let streetViewLookupToken = 0;
let streetViewCoverageLayer = null;
let streetViewVectorGridPromise = null;
let streetViewMapillaryPromise = null;
let streetViewViewer = null;
let streetViewMarker = null;
let streetViewFollowMap = true;
let streetViewHoverCoverage = false;
let streetViewHoverRaf = 0;
let streetViewMaximized = false;
let streetViewPanelSize = null;
let streetViewResizeState = null;

function resizeStreetViewViewer() {
    window.requestAnimationFrame(() => {
        try {
            streetViewViewer?.resize();
        } catch (_) {}
    });
}

function applyStreetViewPanelSize(size = streetViewPanelSize) {
    if (!streetViewPanel || streetViewMaximized) return;
    if (!size) {
        streetViewPanel.style.width = '';
        streetViewPanel.style.height = '';
        if (streetViewPanelBody) streetViewPanelBody.style.height = '';
        resizeStreetViewViewer();
        return;
    }
    const stage = document.querySelector('.map-stage');
    const maxW = Math.max(280, (stage?.clientWidth || window.innerWidth) - 24);
    const maxH = Math.max(220, (stage?.clientHeight || window.innerHeight) - 24);
    const width = Math.min(maxW, Math.max(280, Number(size.width) || 420));
    const height = Math.min(maxH, Math.max(220, Number(size.height) || 320));
    streetViewPanel.style.width = `${width}px`;
    streetViewPanel.style.height = `${height}px`;
    if (streetViewPanelBody) {
        const headerH = streetViewPanel.querySelector('.streetview-panel-header')?.offsetHeight || 40;
        streetViewPanelBody.style.height = `${Math.max(160, height - headerH)}px`;
    }
    streetViewPanelSize = { width, height };
    resizeStreetViewViewer();
}

function setStreetViewMaximized(enabled) {
    if (!streetViewPanel) return;
    streetViewMaximized = !!enabled;
    streetViewPanel.classList.toggle('is-maximized', streetViewMaximized);
    streetViewMaxBtn?.classList.toggle('active', streetViewMaximized);
    streetViewMaxBtn?.setAttribute('aria-pressed', String(streetViewMaximized));
    const maxLabel = streetViewMaximized ? t('map.streetViewRestore') : t('map.streetViewMaximize');
    streetViewMaxBtn?.setAttribute('title', maxLabel);
    streetViewMaxBtn?.setAttribute('aria-label', maxLabel);
    streetViewMaxBtn && (streetViewMaxBtn.textContent = streetViewMaximized ? '❐' : '⛶');
    if (streetViewMaximized) {
        streetViewPanel.style.width = '';
        streetViewPanel.style.height = '';
        if (streetViewPanelBody) streetViewPanelBody.style.height = '';
    } else {
        applyStreetViewPanelSize(streetViewPanelSize);
    }
    resizeStreetViewViewer();
}

function beginStreetViewResize(event) {
    if (!streetViewPanel || streetViewMaximized) return;
    event.preventDefault();
    event.stopPropagation();
    const rect = streetViewPanel.getBoundingClientRect();
    streetViewResizeState = {
        startX: event.clientX,
        startY: event.clientY,
        startWidth: rect.width,
        startHeight: rect.height,
    };
    streetViewPanel.classList.add('is-resizing');
    window.addEventListener('pointermove', onStreetViewResizeMove);
    window.addEventListener('pointerup', endStreetViewResize);
    window.addEventListener('pointercancel', endStreetViewResize);
}

function onStreetViewResizeMove(event) {
    if (!streetViewResizeState || !streetViewPanel) return;
    // Handle is top-right: drag right/up grows, left/down shrinks.
    const dx = event.clientX - streetViewResizeState.startX;
    const dy = streetViewResizeState.startY - event.clientY;
    applyStreetViewPanelSize({
        width: streetViewResizeState.startWidth + dx,
        height: streetViewResizeState.startHeight + dy,
    });
}

function endStreetViewResize() {
    streetViewResizeState = null;
    streetViewPanel?.classList.remove('is-resizing');
    window.removeEventListener('pointermove', onStreetViewResizeMove);
    window.removeEventListener('pointerup', endStreetViewResize);
    window.removeEventListener('pointercancel', endStreetViewResize);
    resizeStreetViewViewer();
}
const mapillaryBoot = (() => {
    try {
        const el = document.querySelector('#mapillary-boot');
        if (!el?.textContent) return { enabled: false, radius_m: 50, access_token: '' };
        const parsed = JSON.parse(el.textContent);
        return {
            enabled: !!(parsed && parsed.enabled),
            radius_m: Math.max(1, Math.min(50, Number(parsed?.radius_m) || 50)),
            access_token: String(parsed?.access_token || ''),
        };
    } catch (_) {
        return { enabled: false, radius_m: 50, access_token: '' };
    }
})();
const mapillaryEnabled = !!(mapillaryBoot.enabled && streetViewBtn && mapillaryBoot.access_token);
let measurePoints = [];
let measureLayer = null;
let measureLine = null;
let measureMarkers = [];
let renderTimer = null;
let redrawTimer = null;
let motionFrame = null;
let loadTimer = null;
let lastLoadKey = '';
let deferredInstallPrompt = null;
let cctvSourceSelection = null;
let cctvSourceFilterSignature = '';
let cctvClusters = [];
let pinnedMonitorMarkers = new Map();
let pinnedMonitorIds = new Set();
let pinnedMonitorSizes = new Map();

const maxListItems = 120;
const maxCanvasVehicles = 2600;
const maxWeatherStations = 260;
const cctvDetailMinZoom = 10;
const cctvPayloadLimit = 800;
const minAnimationDistanceMeters = 8;
const maxAnimationDistanceMeters = 900;
const minBusAnimationMs = 8000;
const maxBusAnimationMs = 45000;
const busAnimationMetersPerSecond = 9;
const monitorStorageKey = 'open-live-map-monitored';
const monitorSizeStorageKey = 'open-live-map-monitor-size';
const monitorDockStorageKey = 'open-live-map-monitor-dock';
const monitorPresetStorageKey = 'open-live-map-monitor-presets';
const pinnedMonitorStorageKey = 'open-live-map-pinned-monitors';
const pinnedMonitorSizeStorageKey = 'open-live-map-pinned-monitor-sizes';
const pinnedMinimapPrefStorageKey = 'open-live-map-pinned-minimap';
const legacyPinnedMonitorSizeStorageKey = 'open-live-map-pinned-monitor-size';
const savedLocationsStorageKey = 'open-live-map-saved-locations';
const userCctvStorageKey = 'open-live-map-user-cctvs';
const settingsStorageKey = 'open-live-map-settings';
const menuStateStorageKey = 'open-live-map-menu-sections';
const monitorPalette = ['#f2c94c', '#f0745e', '#8f6ff5', '#00b8d9', '#ff8f3d', '#80c342', '#ff6fb1', '#00a887'];
const defaultCctvReportTypes = {
    offline: t('js.report.offline'),
    not_live: t('js.report.not_live'),
    wrong_location: t('js.report.wrong_location'),
    privacy: t('js.report.privacy'),
    inappropriate: t('js.report.inappropriate'),
    other: t('js.report.other'),
};
let cctvReportTypes = { ...defaultCctvReportTypes };
let cctvReportTypesLoaded = false;
const weatherFieldMeta = {
    temperature: { label: t('js.weather.temperature'), unit: '°C', digits: 1 },
    rain: { label: t('js.weather.rain'), unit: 'mm', digits: 1 },
    humidity: { label: t('js.weather.humidity'), unit: '%', digits: 0 },
    wind_speed: { label: t('js.weather.wind'), unit: 'm/s', digits: 1 },
    weather: { label: t('js.weather.condition'), unit: '', digits: 0 },
};
const basemapStorageDefault = 'light';
const measureIconSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.8 19.2 19.2 4.8"></path><path d="M7.2 4.8H19.2V16.8"></path><path d="M4.8 19.2h4.2M4.8 19.2v-4.2M15.6 7.2h3.6M16.8 4.8v3.6"></path></svg>';
const clearMeasureIconSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.2"></circle><path d="m9 9 6 6M15 9l-6 6"></path></svg>';
const pinIconSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 4 6 6"></path><path d="m14.5 9.5-5 5"></path><path d="m9 3 12 12"></path><path d="M5 21l4.5-6.5"></path><path d="m9 3-2 2 4 4-4 4 4 4 4-4 4 4 2-2Z"></path></svg>';
const unpinIconSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 4 20 11"></path><path d="m13.5 9.5-4.5 4.5"></path><path d="m8 4-2 2 4 4-4 4 4 4 4-4 4 4 2-2Z"></path><path d="M4 21l5-7"></path><path d="M15 5h4v4"></path><path d="M19 5 13 11"></path></svg>';
const monitorSizeDownSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="7" width="10" height="10" rx="1.6"></rect></svg>';
const monitorSizeUpSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4.5" y="4.5" width="15" height="15" rx="1.6"></rect><path d="M12 8.2v7.6M8.2 12h7.6"></path></svg>';
const monitorColDownSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="5" height="14" rx="1"></rect><rect x="10.5" y="5" width="5" height="14" rx="1"></rect><path d="M17.2 12h3.8"></path></svg>';
const monitorColUpSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="5" width="5" height="14" rx="1"></rect><rect x="10.5" y="5" width="5" height="14" rx="1"></rect><path d="M19.2 8.8v6.4M16 12h6.4"></path></svg>';
const monitorInfoOnSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="1.6"></rect><path d="M6.5 14h11M6.5 17h7.5"></path></svg>';
const monitorInfoOffSvg = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="1.6"></rect><path d="M6.5 14h11M6.5 17h7.5M5 5.5l14 13"></path></svg>';
const basemaps = {
    light: {
        label: t('js.basemap.light'),
        title: t('js.basemap.lightTitle'),
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        className: 'basemap-light',
        options: {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        },
    },
    standard: {
        label: t('js.basemap.standard'),
        title: t('js.basemap.standardTitle'),
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        options: {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        },
    },
    transport: {
        label: t('js.basemap.traffic'),
        title: t('js.basemap.trafficTitle'),
        url: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
        options: {
            maxZoom: 20,
            attribution: '&copy; OpenStreetMap contributors, Tiles style by HOT',
        },
    },
    dark: {
        label: t('js.basemap.dark'),
        title: t('js.basemap.darkTitle'),
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        className: 'basemap-dark',
        options: {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        },
    },
    satellite: {
        label: t('js.basemap.satellite'),
        title: t('js.basemap.satelliteTitle'),
        url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        options: {
            maxZoom: 19,
            attribution: 'Tiles &copy; Esri',
        },
    },
};
const cityAreas = {
    Taipei: { label: '臺北市', center: [25.033964, 121.564468], bounds: [24.94, 121.45, 25.22, 121.68] },
    NewTaipei: { label: '新北市', center: [25.012, 121.465], bounds: [24.65, 121.20, 25.32, 122.05] },
    Taoyuan: { label: '桃園市', center: [24.9937, 121.3009], bounds: [24.80, 120.95, 25.13, 121.50] },
    Taichung: { label: '臺中市', center: [24.1477, 120.6736], bounds: [24.00, 120.45, 24.45, 121.00] },
    Tainan: { label: '臺南市', center: [22.9999, 120.227], bounds: [22.86, 120.00, 23.45, 120.65] },
    Kaohsiung: { label: '高雄市', center: [22.6273, 120.3014], bounds: [22.45, 120.10, 23.45, 121.05] },
};

const map = L.map('map', {
    zoomControl: false,
}).setView(defaultCenter, 13);

L.control.zoom({ position: 'bottomright' }).addTo(map);

if (cctvMonitorDock) {
    L.DomEvent.disableClickPropagation(cctvMonitorDock);
    L.DomEvent.disableScrollPropagation(cctvMonitorDock);
}
if (streetViewPanel) {
    L.DomEvent.disableClickPropagation(streetViewPanel);
    L.DomEvent.disableScrollPropagation(streetViewPanel);
}

let currentBasemapKey = basemapStorageDefault;
let currentBasemapLayer = null;

function ensureToolbarButton(id, label, title, beforeElement = null) {
    let button = document.querySelector(`#${id}`);
    if (button) return button;
    const toolbar = document.querySelector('.map-toolbar');
    if (!toolbar) return null;
    button = document.createElement('button');
    button.className = 'icon-button';
    button.type = 'button';
    button.id = id;
    button.title = title;
    button.setAttribute('aria-label', title);
    button.textContent = label;
    toolbar.insertBefore(button, beforeElement);
    return button;
}

function ensureMapLoadStatus() {
    let status = document.querySelector('#mapLoadStatus');
    if (status) return status;
    const mapStage = document.querySelector('.map-stage');
    if (!mapStage) return null;
    status = document.createElement('div');
    status.className = 'map-load-status';
    status.id = 'mapLoadStatus';
    status.setAttribute('aria-live', 'polite');
    status.innerHTML = '<span class="load-dot" aria-hidden="true"></span><strong>' + t('js.load.notYet') + '</strong><small>' + t('js.load.byBounds') + '</small>';
    const measure = document.querySelector('#measureReadout');
    mapStage.insertBefore(status, measure || null);
    return status;
}

pickMapBtn = ensureToolbarButton('pickMapBtn', '⌖', t('js.map.pick'), measureBtn) || pickMapBtn;
refreshMapBtn = ensureToolbarButton('refreshMapBtn', '↻', t('js.map.refresh'), togglePanelBtn) || refreshMapBtn;
mapLoadStatus = ensureMapLoadStatus() || mapLoadStatus;
document.querySelector('#mapContextMenu')?.remove();

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js').catch(() => {});
    });
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    if (installAppBtn) {
        installAppBtn.hidden = false;
    }
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    if (installAppBtn) {
        installAppBtn.hidden = true;
    }
});

function formatDistance(meters) {
    if (!Number.isFinite(meters)) return '--';
    if (meters < 1000) return `${Math.round(meters)} m`;
    return `${(meters / 1000).toFixed(1)} km`;
}

function formatPreciseDistance(meters) {
    if (!Number.isFinite(meters)) return '--';
    if (meters < 1000) return `${Math.round(meters)} m`;
    if (meters < 10000) return `${(meters / 1000).toFixed(2)} km`;
    return `${(meters / 1000).toFixed(1)} km`;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[char]));
}

function weatherColor(station) {
    const field = primaryWeatherField();
    if (field === 'humidity') return '#8f6ff5';
    if (field === 'wind_speed') return '#38b99c';
    if (field === 'weather') return '#f2c94c';
    const rain = weatherNumber(station.rain);
    if (field === 'rain') {
        if (Number.isFinite(rain) && rain >= 10) return '#3d80d8';
        if (Number.isFinite(rain) && rain > 0) return '#00a6d6';
        return '#f2c94c';
    }
    const temp = weatherNumber(station.temperature);
    if (Number.isFinite(temp) && temp >= 32) return '#f0745e';
    if (Number.isFinite(temp) && temp <= 16) return '#6fb4ff';
    return '#f2c94c';
}

function selectedWeatherFields() {
    const fields = weatherFieldToggles
        .filter((toggle) => toggle.checked)
        .map((toggle) => toggle.value)
        .filter((field) => weatherFieldMeta[field]);
    return fields;
}

function primaryWeatherField() {
    return selectedWeatherFields()[0] || '';
}

function renderWeatherFieldPanel() {
    if (!weatherFieldPanel) return;
    weatherFieldPanel.hidden = !weatherLayerToggle?.checked;
}

function renderRealpricePanel() {
    if (!realpricePanel) return;
    realpricePanel.hidden = !realpriceLayerToggle?.checked;
}

function syncRealpriceToggles(enabled = Boolean(realpriceLayerToggle?.checked)) {
    if (realpriceLayerToggle) realpriceLayerToggle.checked = enabled;
    if (legendRealpriceToggle) legendRealpriceToggle.checked = enabled;
    renderRealpricePanel();
}

function formatBytes(bytes) {
    const number = Number(bytes);
    if (!Number.isFinite(number) || number <= 0) return '--';
    if (number < 1024) return `${Math.round(number)} B`;
    if (number < 1024 * 1024) return `${(number / 1024).toFixed(1)} KB`;
    if (number < 1024 * 1024 * 1024) return `${(number / 1024 / 1024).toFixed(1)} MB`;
    return `${(number / 1024 / 1024 / 1024).toFixed(2)} GB`;
}

function realpriceStatusMessage(payload) {
    const status = payload?.status || {};
    if (!status.zip_exists) {
        return payload?.notice || t('js.realprice.noZip');
    }
    const size = formatBytes(status.zip_size);
    const downloadedAt = status.downloaded_at ? formatAge(status.downloaded_at) : t('js.realprice.timeUnknown');
    if (!status.indexed_at) {
        return t('js.realprice.zipReady', { size, age: downloadedAt });
    }
    const count = Number(status.indexed_count) || 0;
    const visible = Number(payload?.filtered_count) || 0;
    const coarse = Number(payload?.coarse_count) || 0;
    const items = Number(payload?.item_count) || 0;
    const districtSummaries = Array.isArray(payload?.district_summaries)
        ? payload.district_summaries.length
        : (Number(payload?.district_item_count) || 0);
    const districtClusters = Array.isArray(payload?.district_clusters)
        ? payload.district_clusters.length
        : (Number(payload?.district_cluster_count) || 0);
    const districtHits = Number(payload?.district_hit_count) || 0;
    const missing = Number(payload?.missing_geocode_count) || 0;
    const zoom = Number(payload?.zoom ?? map.getZoom?.() ?? 0);
    const detailZoom = Number(payload?.item_zoom_min) || 14;
    const districtZoom = Number(payload?.district_cluster_zoom_min) || 11;
    const mode = String(payload?.cluster_mode || '');
    const missNote = missing > 0 ? t('js.realprice.missingNote', { missing: fmtNum(missing) }) : '';
    if (visible <= 0 && districtClusters <= 0) {
        if (zoom >= detailZoom && coarse > 0) {
            return t('js.realprice.noCoords', { count: fmtNum(count), age: downloadedAt, coarse: fmtNum(coarse) });
        }
        return t('js.realprice.noMatch', { count: fmtNum(count), age: downloadedAt });
    }
    if (mode === 'district' || (zoom >= districtZoom && zoom < detailZoom)) {
        return t('js.realprice.districtMode', {
            count: fmtNum(count), age: downloadedAt, districts: fmtNum(districtClusters),
            visible: fmtNum(visible), detailZoom,
        });
    }
    if (zoom < detailZoom) {
        return t('js.realprice.cityMode', {
            count: fmtNum(count), age: downloadedAt, visible: fmtNum(visible),
            districtZoom, detailZoom,
        });
    }
    if (items <= 0 && districtSummaries <= 0) {
        return t('js.realprice.noDoor', {
            count: fmtNum(count), age: downloadedAt, visible: fmtNum(visible), missNote,
        });
    }
    const parts = [];
    if (items > 0) parts.push(t('js.realprice.parts.door', { n: fmtNum(items) }));
    if (districtSummaries > 0) {
        parts.push(t('js.realprice.parts.districtApprox', {
            n: fmtNum(districtSummaries), hits: fmtNum(districtHits),
        }));
    }
    return t('js.realprice.showDoor', {
        count: fmtNum(count),
        age: downloadedAt,
        visible: fmtNum(visible),
        parts: parts.join(t('js.listSep')),
        limited: payload?.item_limited ? t('js.realprice.limited') : '',
        missNote,
    });
}

function realpriceTypeLabel(type) {
    return {
        sale: t('js.realprice.sale'),
        presale: t('js.realprice.presale'),
        rent: t('js.realprice.rent'),
    }[type] || type || t('js.realprice.generic');
}

function formatRealpriceMoney(value, options = {}) {
    const number = Number(value);
    if (!Number.isFinite(number) || number <= 0) return '--';
    if (options.rentMonthly) {
        if (number >= 10000) {
            const wan = number / 10000;
            const text = wan >= 10 ? fmtNum(Math.round(wan)) : wan.toFixed(wan >= 1 ? 1 : 2).replace(/\.0$/, '');
            return t('js.realprice.unit.wanMonth', { n: text });
        }
        return t('js.realprice.unit.month', { n: fmtNum(Math.round(number)) });
    }
    if (options.unitPrice) {
        const wan = number / 10000;
        if (wan < 0.1) return t('js.realprice.unit.ping', { n: fmtNum(Math.round(number)) });
        const text = wan >= 10 ? fmtNum(Math.round(wan)) : wan.toFixed(1).replace(/\.0$/, '');
        return t('js.realprice.unit.wanPing', { n: text });
    }
    if (number >= 100000000) {
        return t('js.realprice.unit.yi', { n: (number / 100000000).toFixed(2) });
    }
    if (number >= 10000) {
        const wan = number / 10000;
        const text = wan >= 10 ? fmtNum(Math.round(wan)) : wan.toFixed(1).replace(/\.0$/, '');
        return t('js.realprice.unit.wan', { n: text });
    }
    return t('js.realprice.unit.yuan', { n: fmtNum(Math.round(number)) });
}

function realpricePrimaryMetric(item) {
    const type = String(item?.type || 'sale');
    if (type === 'rent') {
        return {
            label: t('js.realprice.labelRent'),
            text: formatRealpriceMoney(item.total_price, { rentMonthly: true }),
            secondaryLabel: t('js.realprice.unitPrice'),
            secondaryText: Number(item.unit_price_ping) > 0
                ? formatRealpriceMoney(item.unit_price_ping, { unitPrice: true })
                : '--',
        };
    }
    return {
        label: t('js.realprice.labelTotal'),
        text: formatRealpriceMoney(item.total_price),
        secondaryLabel: t('js.realprice.unitPrice'),
        secondaryText: formatRealpriceMoney(item.unit_price_ping, { unitPrice: true }),
    };
}

function realpriceMarkerCaption(item) {
    const type = String(item?.type || 'sale');
    if (type === 'rent') {
        return formatRealpriceMoney(item.total_price, { rentMonthly: true });
    }
    const unit = Number(item?.unit_price_ping) || 0;
    if (unit > 0) return formatRealpriceMoney(unit, { unitPrice: true });
    return formatRealpriceMoney(item.total_price);
}

function realpriceClusterHtml(cluster) {
    const count = Number(cluster.count) || 0;
    const type = String(cluster.type || 'sale');
    const isDistrict = String(cluster.kind || '') === 'realprice-district-cluster' || Boolean(cluster.district);
    const title = isDistrict
        ? (cluster.district || cluster.city || t('js.realprice.district'))
        : (cluster.city || t('js.realprice.generic'));
    const avgMetric = type === 'rent'
        ? formatRealpriceMoney(cluster.avg_total_price, { rentMonthly: true })
        : formatRealpriceMoney(cluster.avg_unit_price_ping, { unitPrice: true });
    const located = Number(cluster.located_count) || 0;
    const addressCount = Number(cluster.address_count) || 0;
    const districtCount = Number(cluster.district_count) || 0;
    let note = isDistrict ? (cluster.city || t('js.realprice.district')) : t('js.realprice.coarse');
    if (!isDistrict && located > 0) {
        note = addressCount > 0
            ? t('js.realprice.doorCount', { n: fmtNum(addressCount) })
            : t('js.realprice.approxCount', { n: fmtNum(districtCount) });
    }
    return `
        <span class="realprice-bubble type-${escapeHtml(type)}${isDistrict ? ' district-level' : ''}">
            <strong>${fmtNum(count)}</strong>
            <small>${escapeHtml(title)}</small>
            <em>${escapeHtml(note)} · ${escapeHtml(avgMetric)}</em>
        </span>
    `;
}

function realpriceDistrictClusterPopupHtml(cluster) {
    const type = String(cluster.type || 'sale');
    const samples = Array.isArray(cluster.samples) ? cluster.samples : [];
    const sampleRows = samples.length
        ? samples.map((item) => `
            <li>
                <b>${escapeHtml(item.date || '')}</b>
                <span>${escapeHtml(item.building_type || item.target || '')}</span>
                <em>${escapeHtml(type === 'rent'
                    ? formatRealpriceMoney(item.total_price, { rentMonthly: true })
                    : formatRealpriceMoney(item.total_price))}</em>
            </li>
        `).join('')
        : '<li>' + escapeHtml(t('js.realprice.noSamples')) + '</li>';
    return `
        <article class="realprice-popup">
            <h3>${escapeHtml(cluster.district || t('js.realprice.district'))} · ${escapeHtml(cluster.city || '')}</h3>
            <p>${escapeHtml(t('js.realprice.districtAggMeta', { type: realpriceTypeLabel(cluster.type), count: fmtNum(Number(cluster.count || 0)), date: cluster.latest_date || '--' }))}</p>
            <dl>
                <div><dt>${type === 'rent' ? t('js.realprice.avgRent') : t('js.realprice.avgTotal')}</dt><dd>${escapeHtml(formatRealpriceMoney(cluster.avg_total_price, type === 'rent' ? { rentMonthly: true } : {}))}</dd></div>
                <div><dt>${type === 'rent' ? t('js.realprice.refUnit') : t('js.realprice.avgUnit')}</dt><dd>${escapeHtml(formatRealpriceMoney(cluster.avg_unit_price_ping, { unitPrice: true }))}</dd></div>
            </dl>
            <ul>${sampleRows}</ul>
            <p class="realprice-precision-note">${escapeHtml(t('js.realprice.districtNote', { district: cluster.district || t('js.realprice.district') }))}</p>
        </article>
    `;
}

function realpricePopupHtml(cluster) {
    const districts = Array.isArray(cluster.districts) ? cluster.districts : [];
    const samples = Array.isArray(cluster.samples) ? cluster.samples : [];
    const type = String(cluster.type || 'sale');
    const avgUnitLabel = type === 'rent' ? t('js.realprice.avgRent') : t('js.realprice.avgUnit');
    const avgUnitValue = type === 'rent'
        ? formatRealpriceMoney(cluster.avg_total_price, { rentMonthly: true })
        : formatRealpriceMoney(cluster.avg_unit_price_ping, { unitPrice: true });
    const districtRows = districts.length
        ? districts.map((item) => `
            <tr>
                <td>${escapeHtml(item.name || '--')}</td>
                <td>${fmtNum(Number(item.count || 0))}</td>
                <td>${escapeHtml(type === 'rent'
                    ? formatRealpriceMoney(item.avg_total_price, { rentMonthly: true })
                    : formatRealpriceMoney(item.avg_unit_price_ping, { unitPrice: true }))}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="3">' + escapeHtml(t('js.realprice.noDistrictStats')) + '</td></tr>';
    const sampleRows = samples.length
        ? samples.map((item) => `
            <li>
                <b>${escapeHtml(item.district || '')}</b>
                <span>${escapeHtml(item.date || '')} · ${escapeHtml(item.building_type || item.target || '')}</span>
                <em>${escapeHtml(type === 'rent'
                    ? formatRealpriceMoney(item.total_price, { rentMonthly: true })
                    : formatRealpriceMoney(item.total_price))}</em>
            </li>
        `).join('')
        : '<li>' + escapeHtml(t('js.realprice.noSamples')) + '</li>';
    return `
        <article class="realprice-popup">
            <h3>${escapeHtml(cluster.city || t('js.realprice.registry'))}</h3>
            <p>${escapeHtml(t('js.realprice.cityMeta', { type: realpriceTypeLabel(cluster.type), count: fmtNum(Number(cluster.count || 0)), located: fmtNum(Number(cluster.located_count || 0)), date: cluster.latest_date || '--' }))}</p>
            <dl>
                <div><dt>${type === 'rent' ? t('js.realprice.avgRent') : t('js.realprice.avgTotal')}</dt><dd>${escapeHtml(formatRealpriceMoney(cluster.avg_total_price, type === 'rent' ? { rentMonthly: true } : {}))}</dd></div>
                <div><dt>${escapeHtml(avgUnitLabel)}</dt><dd>${escapeHtml(avgUnitValue)}</dd></div>
            </dl>
            <table>
                <thead><tr><th>${escapeHtml(t('js.realprice.district'))}</th><th>${escapeHtml(t('js.realprice.count'))}</th><th>${type === 'rent' ? t('js.realprice.avgRentShort') : t('js.realprice.avgPriceShort')}</th></tr></thead>
                <tbody>${districtRows}</tbody>
            </table>
            <ul>${sampleRows}</ul>
        </article>
    `;
}

function realpricePrecisionLabel(precision) {
    if (precision === 'address') return t('js.realprice.precisionAddress');
    if (precision === 'manual') return t('js.realprice.precisionManual');
    if (precision === 'district') return t('js.realprice.precisionDistrict');
    return t('js.realprice.precisionCache');
}

function realpriceItemLatLng(item, index) {
    const lat = Number(item.lat);
    const lng = Number(item.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
    if (item.precision !== 'district') return [lat, lng];
    const key = String(item.id || `${item.city || ''}-${item.district || ''}-${index}`);
    const seed = Array.from(key).reduce((sum, ch) => sum + ch.charCodeAt(0), 0);
    const angle = (seed % 360) * Math.PI / 180;
    const radius = 0.00028 + ((seed % 9) * 0.000045);
    return [lat + Math.sin(angle) * radius, lng + Math.cos(angle) * radius];
}

/** 同門牌／近距離座標合成一組，避免多筆標籤完全重疊。 */
function realpriceLocationGroupKey(item) {
    const lat = Number(item.lat);
    const lng = Number(item.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return '';
    // ~1.1m；同門牌 geocode 通常完全相同，略取整即可。
    return `${lat.toFixed(5)},${lng.toFixed(5)}`;
}

function groupRealpriceItemsByLocation(items) {
    const groups = new Map();
    (Array.isArray(items) ? items : []).forEach((item, index) => {
        const key = realpriceLocationGroupKey(item);
        if (!key) return;
        if (!groups.has(key)) {
            const lat = Number(item.lat);
            const lng = Number(item.lng);
            groups.set(key, {
                key,
                lat,
                lng,
                precision: String(item.precision || ''),
                items: [],
            });
        }
        groups.get(key).items.push({ ...item, _index: index });
    });
    return Array.from(groups.values()).map((group) => {
        group.items.sort((a, b) => String(b.date || '').localeCompare(String(a.date || '')));
        return group;
    });
}

function realpriceStackPriceRange(items) {
    const type = String(items[0]?.type || 'sale');
    const values = items
        .map((item) => {
            if (type === 'rent') return Number(item.total_price) || 0;
            const unit = Number(item.unit_price_ping) || 0;
            return unit > 0 ? unit : (Number(item.total_price) || 0);
        })
        .filter((value) => value > 0);
    if (!values.length) return '--';
    const min = Math.min(...values);
    const max = Math.max(...values);
    const format = (value) => (type === 'rent'
        ? formatRealpriceMoney(value, { rentMonthly: true })
        : (Number(items[0]?.unit_price_ping) > 0
            ? formatRealpriceMoney(value, { unitPrice: true })
            : formatRealpriceMoney(value)));
    if (min === max) return format(min);
    return `${format(min)}～${format(max)}`;
}

function realpriceItemHtml(item) {
    const price = realpriceMarkerCaption(item);
    const type = String(item.type || 'sale');
    const sub = type === 'rent'
        ? t('js.realprice.rent')
        : (item.building_type || item.district || item.city || t('js.realprice.generic'));
    const shortSub = String(sub).replace(/[（(].*$/, '').trim() || sub;
    return `
        <span class="realprice-item-dot type-${escapeHtml(type)} ${escapeHtml(item.precision || 'unknown')}">
            <strong>${escapeHtml(price)}</strong>
            <small>${escapeHtml(shortSub)}</small>
        </span>
    `;
}

function realpriceStackHtml(group) {
    const items = group.items || [];
    const type = String(items[0]?.type || 'sale');
    const count = items.length;
    const range = realpriceStackPriceRange(items);
    const label = type === 'rent' ? t('js.realprice.sameAddressRent') : t('js.realprice.sameAddressDeal');
    return `
        <span class="realprice-item-dot stack type-${escapeHtml(type)} ${escapeHtml(group.precision || 'unknown')}">
            <b class="realprice-stack-count">${count}</b>
            <strong>${escapeHtml(range)}</strong>
            <small>${escapeHtml(label)}</small>
        </span>
    `;
}

function formatRealpriceArea(sqm) {
    const meters = Number(sqm) || 0;
    if (!(meters > 0)) return '--';
    const ping = meters / 3.305785;
    const pingText = ping >= 10
        ? ping.toFixed(1).replace(/\.0$/, '')
        : ping.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
    const sqmText = meters >= 10
        ? fmtNum(Math.round(meters))
        : meters.toFixed(1).replace(/\.0$/, '');
    return t('js.realprice.areaFmt', { ping: pingText, sqm: sqmText });
}

/** 門牌到「號」為止（座標聚合用）。 */
function realpriceDoorLabel(address) {
    const text = String(address || '').replace(/\s+/g, '');
    const match = text.match(/^(.+?號)/);
    return match ? match[1] : (text || t('js.notProvided'));
}

/** 樓層／戶別：優先官方「移轉層次」，否則從門牌「號」後面擷取。 */
function realpriceFloorLabel(item) {
    const official = String(item?.floor || '').replace(/\s+/g, '').trim();
    if (official) return official;
    const text = String(item?.address || '').replace(/\s+/g, '');
    const match = text.match(/號(.+)$/);
    if (!match) return '';
    return String(match[1] || '').replace(/^[\s,，、\-]+/, '').trim();
}

function realpriceItemPopupHtml(item) {
    const precision = realpricePrecisionLabel(item.precision);
    const metric = realpricePrimaryMetric(item);
    const address = String(item.address || t('js.notProvided'));
    const floor = realpriceFloorLabel(item);
    const totalFloors = String(item.total_floors || '').trim();
    const floorText = floor
        ? (totalFloors ? t('js.realprice.floorsTotal', { floor, total: totalFloors }) : floor)
        : '';
    return `
        <article class="realprice-popup realprice-item-popup">
            <h3>${escapeHtml(item.district || item.city || t('js.realprice.registry'))}</h3>
            <p>${escapeHtml(realpriceTypeLabel(item.type))} · ${escapeHtml(item.date || '--')} · ${escapeHtml(precision)}</p>
            <dl>
                <div><dt>${escapeHtml(metric.label)}</dt><dd>${escapeHtml(metric.text)}</dd></div>
                <div><dt>${escapeHtml(metric.secondaryLabel)}</dt><dd>${escapeHtml(metric.secondaryText)}</dd></div>
            </dl>
            <ul>
                <li class="realprice-address-row"><b>${escapeHtml(t('js.realprice.address'))}</b><span class="realprice-address">${escapeHtml(address)}</span></li>
                ${floorText ? `<li><b>${escapeHtml(t('js.realprice.floorUnit'))}</b><span>${escapeHtml(floorText)}</span></li>` : `<li><b>${escapeHtml(t('js.realprice.floorUnit'))}</b><span>${escapeHtml(t('js.realprice.noFloor'))}</span></li>`}
                <li><b>${escapeHtml(t('js.realprice.buildingType'))}</b><span>${escapeHtml(item.building_type || item.target || '--')}</span></li>
                <li><b>${escapeHtml(t('js.realprice.area'))}</b><span>${escapeHtml(formatRealpriceArea(item.area_sqm))}</span></li>
            </ul>
            ${item.precision === 'district' ? `<p class="realprice-precision-note">${escapeHtml(t('js.realprice.districtApproxNote'))}</p>` : ''}
        </article>
    `;
}

function realpriceStackPopupHtml(group) {
    const items = Array.isArray(group.items) ? group.items : [];
    const first = items[0] || {};
    const type = String(first.type || 'sale');
    const door = realpriceDoorLabel(first.address);
    const floors = new Set(items.map((item) => realpriceFloorLabel(item)).filter(Boolean));
    const rows = items.map((item) => {
        const metric = realpricePrimaryMetric(item);
        const floor = realpriceFloorLabel(item) || t('js.realprice.noFloor');
        return `
            <li>
                <b>${escapeHtml(item.date || '--')}</b>
                <span title="${escapeHtml([item.address, item.building_type].filter(Boolean).join(' · '))}">${escapeHtml(floor)}</span>
                <em>${escapeHtml(metric.text)}</em>
                <small>${escapeHtml(formatRealpriceArea(item.area_sqm))}${item.building_type ? ` · ${escapeHtml(item.building_type)}` : ''}</small>
            </li>
        `;
    }).join('');
    const floorNote = floors.size > 0
        ? t('js.realprice.groupFloorNote')
        : t('js.realprice.groupNoFloorNote');
    return `
        <article class="realprice-popup realprice-item-popup realprice-stack-popup">
            <h3>${escapeHtml(first.district || first.city || t('js.realprice.sameAddressDeal'))}</h3>
            <p>${escapeHtml(t('js.realprice.sameDoorMeta', { type: realpriceTypeLabel(type), n: fmtNum(items.length) }))}${floors.size > 1 ? escapeHtml(t('js.realprice.sameDoorFloors', { n: floors.size })) : ''}</p>
            <ul>
                <li class="realprice-address-row"><b>${escapeHtml(t('js.realprice.doorplate'))}</b><span class="realprice-address">${escapeHtml(door)}</span></li>
            </ul>
            <ol class="realprice-stack-list">${rows}</ol>
            <p class="realprice-precision-note">${floorNote}</p>
        </article>
    `;
}

function realpriceDistrictSummaryHtml(summary) {
    const count = Number(summary.count) || 0;
    const type = String(summary.type || 'sale');
    const avgMetric = type === 'rent'
        ? formatRealpriceMoney(summary.avg_total_price, { rentMonthly: true })
        : formatRealpriceMoney(summary.avg_unit_price_ping, { unitPrice: true });
    return `
        <span class="realprice-item-dot district summary type-${escapeHtml(type)}">
            <strong>${escapeHtml(t('js.realprice.dealCountShort', { n: fmtNum(count) }))}</strong>
            <small>${escapeHtml(summary.district || summary.city || t('js.realprice.district'))}</small>
            <em>${escapeHtml(avgMetric)}</em>
        </span>
    `;
}

function realpriceDistrictSummaryPopupHtml(summary) {
    const type = String(summary.type || 'sale');
    return `
        <article class="realprice-popup realprice-item-popup">
            <h3>${escapeHtml(summary.district || summary.city || t('js.realprice.precisionDistrict'))}</h3>
            <p>${escapeHtml(t('js.realprice.districtApproxMeta', { type: realpriceTypeLabel(summary.type) }))}</p>
            <dl>
                <div><dt>${escapeHtml(t('js.realprice.count'))}</dt><dd>${fmtNum(Number(summary.count || 0))}</dd></div>
                <div><dt>${type === 'rent' ? t('js.realprice.avgRent') : t('js.realprice.avgTotal')}</dt><dd>${escapeHtml(formatRealpriceMoney(summary.avg_total_price, type === 'rent' ? { rentMonthly: true } : {}))}</dd></div>
                <div><dt>${type === 'rent' ? t('js.realprice.refUnit') : t('js.realprice.avgUnit')}</dt><dd>${escapeHtml(formatRealpriceMoney(summary.avg_unit_price_ping, { unitPrice: true }))}</dd></div>
            </dl>
            <p class="realprice-precision-note">${escapeHtml(t('js.realprice.districtApproxBody'))}</p>
        </article>
    `;
}

function renderRealpriceLayer() {
    if (!realpriceLayerToggle?.checked) {
        realpriceLayer?.clearLayers();
        return;
    }
    if (!realpriceLayer) {
        realpriceLayer = L.layerGroup().addTo(map);
    }
    realpriceLayer.clearLayers();
    groupRealpriceItemsByLocation(realpriceItems).forEach((group) => {
        const items = group.items || [];
        if (!items.length) return;
        const isStack = items.length > 1;
        const latLng = isStack
            ? [group.lat, group.lng]
            : realpriceItemLatLng(items[0], items[0]._index || 0);
        if (!latLng) return;
        const title = isStack
            ? t('js.realprice.sameAddrTitle', { place: items[0].district || items[0].city || t('js.realprice.generic'), n: items.length })
            : `${items[0].district || items[0].city || t('js.realprice.generic')} ${realpriceMarkerCaption(items[0])}`;
        L.marker(latLng, {
            icon: L.divIcon({
                className: 'realprice-item-icon',
                html: isStack ? realpriceStackHtml(group) : realpriceItemHtml(items[0]),
                iconSize: isStack ? [108, 48] : [86, 42],
                iconAnchor: isStack ? [54, 24] : [43, 21],
                popupAnchor: [0, -18],
            }),
            zIndexOffset: isStack ? 460 : 440,
            title,
        })
            .bindPopup(
                isStack ? realpriceStackPopupHtml(group) : realpriceItemPopupHtml(items[0]),
                { maxWidth: isStack ? 460 : 420, className: 'realprice-leaflet-popup' },
            )
            .addTo(realpriceLayer);
    });
    realpriceDistrictSummaries.forEach((summary) => {
        const lat = Number(summary.lat);
        const lng = Number(summary.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'realprice-item-icon',
                html: realpriceDistrictSummaryHtml(summary),
                iconSize: [96, 48],
                iconAnchor: [48, 24],
                popupAnchor: [0, -18],
            }),
            zIndexOffset: 410,
            title: t('js.realprice.approxTitle', { place: summary.district || summary.city || t('js.realprice.district'), n: Number(summary.count || 0) }),
        })
            .bindPopup(realpriceDistrictSummaryPopupHtml(summary), { maxWidth: 340, className: 'realprice-leaflet-popup' })
            .addTo(realpriceLayer);
    });

    const zoom = map.getZoom();
    const showDetail = zoom >= realpriceItemZoomMin
        && (realpriceItems.length > 0 || realpriceDistrictSummaries.length > 0);
    const showDistrictClusters = !showDetail
        && zoom >= realpriceDistrictClusterZoomMin
        && realpriceDistrictClusters.length > 0;
    const showCityClusters = !showDetail
        && !showDistrictClusters
        && realpriceClusters.length > 0;

    if (showDistrictClusters) {
        realpriceDistrictClusters.forEach((cluster) => {
            const lat = Number(cluster.lat);
            const lng = Number(cluster.lng);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'realprice-cluster-icon',
                    html: realpriceClusterHtml(cluster),
                    iconSize: [98, 74],
                    iconAnchor: [49, 37],
                    popupAnchor: [0, -24],
                }),
                zIndexOffset: 390,
                title: t('js.realprice.countTitle', { place: cluster.district || cluster.city || t('js.realprice.district'), n: fmtNum(Number(cluster.count || 0)) }),
            })
                .bindPopup(realpriceDistrictClusterPopupHtml(cluster), { maxWidth: 360, className: 'realprice-leaflet-popup' })
                .addTo(realpriceLayer);
        });
        return;
    }

    if (!showCityClusters) return;
    realpriceClusters.forEach((cluster) => {
        const lat = Number(cluster.lat);
        const lng = Number(cluster.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'realprice-cluster-icon',
                html: realpriceClusterHtml(cluster),
                iconSize: [98, 74],
                iconAnchor: [49, 37],
                popupAnchor: [0, -24],
            }),
            zIndexOffset: 380,
            title: t('js.realprice.countTitle', { place: cluster.city || t('js.realprice.generic'), n: fmtNum(Number(cluster.count || 0)) }),
        })
            .bindPopup(realpricePopupHtml(cluster), { maxWidth: 360, className: 'realprice-leaflet-popup' })
            .addTo(realpriceLayer);
    });
}

function realpricePayloadSummary(payload) {
    const filtered = Number(payload?.filtered_count) || 0;
    const mode = String(payload?.cluster_mode || 'city');
    if (mode === 'district') {
        const clusters = Number(payload?.district_cluster_count) || 0;
        return t('js.realprice.statusDistrict', { clusters, filtered: fmtNum(filtered) });
    }
    if (mode === 'detail') {
        const items = Number(payload?.item_count) || 0;
        return t('js.realprice.statusDoor', { items: fmtNum(items), filtered: fmtNum(filtered) });
    }
    const clusters = Array.isArray(payload?.clusters) ? payload.clusters.length : 0;
    return t('js.realprice.statusCity', { clusters, filtered: fmtNum(filtered) });
}

function scheduleLoadRealprice(delay = 450) {
    window.clearTimeout(realpriceLoadTimer);
    if (!realpriceLayerToggle?.checked) return;
    if (realpriceStatusText && !realpriceLoading) {
        realpriceStatusText.textContent = t('js.realprice.waitingBounds');
    }
    realpriceLoadTimer = window.setTimeout(() => {
        loadRealpriceLayer().catch(() => {});
    }, delay);
}

async function loadRealpriceLayer(options = {}) {
    if (!realpriceLayerToggle?.checked) return;
    const requestSeq = ++realpriceRequestSeq;
    realpriceLoading = true;
    if (realpriceStatusText) {
        realpriceStatusText.textContent = t('js.realprice.loading');
    }
    try {
        const bounds = map.getBounds();
        const params = new URLSearchParams({
            south: bounds.getSouth().toFixed(6),
            west: bounds.getWest().toFixed(6),
            north: bounds.getNorth().toFixed(6),
            east: bounds.getEast().toFixed(6),
            zoom: String(map.getZoom()),
            period: realpricePeriod?.value || '12',
            type: realpriceType?.value || 'sale',
            ts: String(Date.now()),
        });
        const response = await fetch(`api/realprice.php?${params.toString()}`, { cache: 'no-store' });
        const payload = await response.json();
        if (requestSeq !== realpriceRequestSeq) return;
        if (!response.ok || payload.ok === false) {
            throw new Error(payload.notice || t('js.realprice.loadFailedHttp', { status: response.status }));
        }
        realpriceClusters = Array.isArray(payload.clusters) ? payload.clusters : [];
        realpriceItems = Array.isArray(payload.items) ? payload.items : [];
        realpriceDistrictSummaries = Array.isArray(payload.district_summaries) ? payload.district_summaries : [];
        realpriceDistrictClusters = Array.isArray(payload.district_clusters) ? payload.district_clusters : [];
        realpriceClusterHideZoomMin = Number(payload.cluster_hide_zoom_min) || 11;
        realpriceDistrictClusterZoomMin = Number(payload.district_cluster_zoom_min) || 11;
        realpriceItemZoomMin = Number(payload.item_zoom_min) || 14;
        realpriceLoaded = true;
        renderRealpriceLayer();
        if (realpriceStatusText) {
            realpriceStatusText.textContent = realpriceStatusMessage(payload);
        }
        const summary = realpricePayloadSummary(payload);
        if (options.updateStatus || selectedSources().length === 0) {
            updateMapLoadStatus('idle', t('js.realprice.loaded', { n: fmtNum(Number(payload.filtered_count || 0)) }), summary);
        }
    } catch (error) {
        if (requestSeq !== realpriceRequestSeq) return;
        realpriceClusters = [];
        realpriceItems = [];
        realpriceDistrictSummaries = [];
        realpriceDistrictClusters = [];
        renderRealpriceLayer();
        if (realpriceStatusText) {
            realpriceStatusText.textContent = error.message || t('js.realprice.loadFailed');
        }
    } finally {
        if (requestSeq === realpriceRequestSeq) {
            realpriceLoading = false;
        }
    }
}

function weatherNumber(value) {
    if (value === null || value === undefined || value === '') return NaN;
    const number = Number(value);
    return Number.isFinite(number) ? number : NaN;
}

function formatWeatherValue(value, unit, digits = 1) {
    const number = weatherNumber(value);
    if (!Number.isFinite(number)) return '--';
    return `${number.toFixed(digits)} ${unit}`;
}

function weatherFieldValue(station, field) {
    if (field === 'weather') {
        const text = String(station.weather || '').trim();
        return text !== '' && text !== '-99' ? text : '--';
    }
    const meta = weatherFieldMeta[field];
    if (!meta) return '--';
    return formatWeatherValue(station[field], meta.unit, meta.digits);
}

function selectedWeatherRows(station) {
    return selectedWeatherFields()
        .map((field) => {
            const meta = weatherFieldMeta[field];
            if (!meta) return null;
            return {
                field,
                label: meta.label,
                value: weatherFieldValue(station, field),
            };
        })
        .filter(Boolean);
}

function weatherMarkerLabel(station) {
    const field = primaryWeatherField();
    if (field === '') return '--';
    if (field === 'temperature') {
        return weatherTempLabel(station);
    }
    if (field === 'weather') {
        const text = String(station.weather || '').trim();
        return text !== '' ? text.slice(0, 2) : '--';
    }
    const value = weatherNumber(station[field]);
    if (!Number.isFinite(value)) return '--';
    if (field === 'rain') return `${value.toFixed(value >= 10 ? 0 : 1)}mm`;
    if (field === 'humidity') return `${Math.round(value)}%`;
    if (field === 'wind_speed') return `${value.toFixed(1)}m/s`;
    return String(Math.round(value));
}

function weatherTempLabel(station) {
    const temp = weatherNumber(station?.temperature);
    if (!Number.isFinite(temp)) return '--';
    return `${Math.round(temp)}°`;
}

function weatherTooltipHtml(station) {
    const rows = selectedWeatherRows(station);
    const body = rows.length
        ? rows.map((row) => `<span><b>${escapeHtml(row.label)}</b>${escapeHtml(row.value)}</span>`).join('')
        : `<span>${escapeHtml(t('js.weather.noFields'))}</span>`;
    return `<strong>${escapeHtml(station.name || t('js.weather.station'))}</strong>${body}`;
}

function weatherGroupTooltipHtml(stations) {
    return stations
        .map((station) => weatherTooltipHtml(station))
        .join('<hr>');
}

function weatherMarkerTitle(station, label) {
    const rows = selectedWeatherRows(station);
    const summary = rows.map((row) => `${row.label} ${row.value}`).join('，');
    return `${station.name || t('js.weather.short')} ${summary || label}`;
}

function weatherDeclutterCellSize() {
    const zoom = map.getZoom();
    if (zoom >= 15) return 30;
    if (zoom >= 13) return 40;
    if (zoom >= 11) return 52;
    return 68;
}

function weatherStationPriority(station) {
    let score = 0;
    if (weatherNumber(station.temperature) === weatherNumber(station.temperature)) score += 1;
    if (weatherNumber(station.rain) > 0) score += 4;
    if (weatherNumber(station.wind_speed) >= 8) score += 3;
    if (String(station.weather || '').trim() && station.weather !== '-99') score += 1;
    if (/^(臺北|新北|桃園|新竹|臺中|臺南|高雄|基隆|宜蘭|花蓮|臺東|澎湖|金門|馬祖)$/.test(String(station.name || ''))) {
        score += 2;
    }
    return score;
}

function visibleWeatherStations(bounds) {
    const candidates = weatherStations
        .filter((station) => Number.isFinite(station.lat) && Number.isFinite(station.lng) && bounds.contains([station.lat, station.lng]))
        .map((station, index) => ({
            station,
            index,
            point: map.latLngToLayerPoint([station.lat, station.lng]),
            priority: weatherStationPriority(station),
        }))
        .sort((a, b) => b.priority - a.priority || a.index - b.index)
        .slice(0, maxWeatherStations);
    const cellSize = weatherDeclutterCellSize();
    const occupied = new Map();
    candidates.forEach((item) => {
        const key = `${Math.round(item.point.x / cellSize)}:${Math.round(item.point.y / cellSize)}`;
        const existing = occupied.get(key);
        if (!existing) {
            occupied.set(key, { ...item, stations: [item.station] });
        } else {
            existing.stations.push(item.station);
        }
    });
    return Array.from(occupied.values()).sort((a, b) => a.index - b.index);
}

function weatherGroupVisibleLimit() {
    const zoom = map.getZoom();
    if (zoom >= 15) return 8;
    if (zoom >= 13) return 6;
    if (zoom >= 11) return 4;
    return 3;
}

function weatherGroupHtml(stations) {
    const limit = weatherGroupVisibleLimit();
    const rows = stations.slice(0, limit).map((station) => {
        const color = weatherColor(station);
        const name = String(station.name || t('js.weather.short')).replace(/\s+/g, '');
        return `<span style="--weather-color:${escapeHtml(color)}"><b>${escapeHtml(name.slice(0, 4))}</b>${escapeHtml(weatherMarkerLabel(station))}</span>`;
    });
    if (stations.length > limit) {
        rows.push(`<span class="weather-extra">${escapeHtml(t('js.weather.moreStations', { n: stations.length - limit }))}</span>`);
    }
    return rows.join('');
}

function weatherGroupTitle(stations) {
    return stations
        .map((station) => weatherMarkerTitle(station, weatherMarkerLabel(station)))
        .join(' / ');
}

function weatherGroupPopup(stations) {
    return stations
        .map((station) => weatherPopup(station))
        .join('<hr class="weather-popup-divider">');
}

function loadSettings() {
    try {
        return JSON.parse(localStorage.getItem(settingsStorageKey) || '{}') || {};
    } catch {
        return {};
    }
}

function saveSettings() {
    const settings = {
        sources: selectedSources(),
        weather: Boolean(weatherLayerToggle?.checked),
        weatherFields: selectedWeatherFields(),
        realprice: Boolean(realpriceLayerToggle?.checked),
        realpricePeriod: realpricePeriod?.value || '12',
        realpriceType: realpriceType?.value || 'sale',
        showStale: Boolean(staleLayerToggle?.checked),
        basemap: currentBasemapKey,
        cctvSources: cctvSourceSelection ? Array.from(cctvSourceSelection) : null,
    };
    localStorage.setItem(settingsStorageKey, JSON.stringify(settings));
}

function applySavedSettings() {
    const settings = loadSettings();
    if (Array.isArray(settings.sources)) {
        const savedSources = new Set(settings.sources);
        if (savedSources.has('rail')) {
            savedSources.add('rail-tra');
            savedSources.add('rail-metro');
            savedSources.add('rail-thsr');
        }
        sourceToggles.forEach((toggle) => {
            toggle.checked = savedSources.has(toggle.value);
        });
    }
    if (weatherLayerToggle && typeof settings.weather === 'boolean') {
        weatherLayerToggle.checked = settings.weather;
    }
    if (Array.isArray(settings.weatherFields)) {
        const savedWeatherFields = new Set(settings.weatherFields);
        weatherFieldToggles.forEach((toggle) => {
            toggle.checked = savedWeatherFields.has(toggle.value);
        });
    }
    if (realpriceLayerToggle && typeof settings.realprice === 'boolean') {
        realpriceLayerToggle.checked = settings.realprice;
    }
    if (realpricePeriod && typeof settings.realpricePeriod === 'string') {
        realpricePeriod.value = settings.realpricePeriod;
    }
    if (realpriceType && typeof settings.realpriceType === 'string') {
        realpriceType.value = settings.realpriceType;
    }
    if (staleLayerToggle && typeof settings.showStale === 'boolean') {
        staleLayerToggle.checked = settings.showStale;
    }
    if (stalePanelToggle && typeof settings.showStale === 'boolean') {
        stalePanelToggle.checked = settings.showStale;
    }
    if (Array.isArray(settings.cctvSources)) {
        cctvSourceSelection = new Set(settings.cctvSources);
    }
    setBasemap(settings.basemap || basemapStorageDefault, { save: false });
}

function setBasemap(key, options = {}) {
    const nextKey = basemaps[key] ? key : basemapStorageDefault;
    const config = basemaps[nextKey];
    if (!config) return;
    if (currentBasemapLayer) {
        map.removeLayer(currentBasemapLayer);
    }
    currentBasemapLayer = L.tileLayer(config.url, config.options).addTo(map);
    currentBasemapKey = nextKey;
    const mapContainer = map.getContainer();
    Object.values(basemaps).forEach((item) => {
        if (item.className) {
            mapContainer.classList.remove(item.className);
        }
    });
    if (config.className) {
        mapContainer.classList.add(config.className);
    }
    if (basemapBtn) {
        basemapBtn.classList.toggle('active', nextKey !== basemapStorageDefault);
        basemapBtn.title = t('js.basemap.prefix', { label: config.label });
        basemapBtn.setAttribute('aria-label', t('js.basemap.prefix', { label: config.label }));
    }
    if (basemapMenu) {
        basemapMenu.querySelectorAll('button').forEach((button) => {
            const active = button.dataset.basemap === nextKey;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }
    if (options.save !== false) {
        saveSettings();
    }
}

function buildBasemapMenu() {
    if (!basemapMenu) return;
    basemapMenu.innerHTML = Object.entries(basemaps)
        .map(([key, config]) => `<button type="button" data-basemap="${escapeHtml(key)}" aria-pressed="false"><strong>${escapeHtml(config.label)}</strong><small>${escapeHtml(config.title)}</small></button>`)
        .join('');
    basemapMenu.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-basemap]');
        if (!button) return;
        setBasemap(button.dataset.basemap);
        basemapMenu.hidden = true;
        basemapBtn?.classList.remove('menu-open');
        basemapBtn?.setAttribute('aria-expanded', 'false');
    });
}

function selectedSources() {
    const sources = sourceToggles
        .filter((toggle) => toggle.checked)
        .map((toggle) => toggle.value)
        .filter(Boolean);
    return sources;
}

function garbageSourceEnabled() {
    return selectedSources().includes('garbage');
}

function cctvCacheSourceEnabled() {
    return selectedSources().includes('cctv-cache');
}

function freshnessFilteredSourceEnabled() {
    const sources = selectedSources();
    return ['bus', 'garbage', 'rail'].some((kind) => sources.includes(kind));
}

function cctvDetailAllowed() {
    return map.getZoom() >= cctvDetailMinZoom;
}

function cctvSourceKey(vehicle) {
    const explicit = String(vehicle?.source_key || '').trim();
    if (explicit) return explicit;
    const url = safeHttpUrl(vehicle?.stream_url || vehicle?.image_url);
    try {
        const host = new URL(url).hostname.toLowerCase();
        if (/^trafficvideo\d*\.tainan\.gov\.tw$/.test(host)) return 'tainan';
        if (host === 'trafficcctv.nantou.gov.tw') return 'nantou';
        if (host === 'cctvtraffic.tycg.gov.tw') return 'taoyuan';
        if (/^cctv[a-z0-9-]*\.freeway\.gov\.tw$/.test(host) || host === 'tisvcloud.freeway.gov.tw') return 'freeway';
        if (/^cctv-ss\d+\.thb\.gov\.tw$/.test(host) || host === 'www.1968services.tw') return 'thb';
        if (host === 'atis.ntpc.gov.tw' || host === 'apiatis.ntpc.gov.tw' || /^cctvatis\d+\.ntpc\.gov\.tw$/.test(host)) return 'newtaipei';
        if (host === 'tw.live') return 'twlive';
        if (host === 'www.gov.tw' || host === 'gov.tw') return 'govtw';
        if (host.includes('youtube')) return 'youtube';
    } catch {
        return 'unknown';
    }
    return 'custom';
}

function cctvSourceLabel(vehicleOrKey) {
    if (typeof vehicleOrKey === 'object' && vehicleOrKey !== null) {
        const explicit = String(vehicleOrKey.source_label || '').trim();
        if (explicit) return explicit;
        vehicleOrKey = cctvSourceKey(vehicleOrKey);
    }
    return {
        freeway: t('js.cctv.src.freeway'),
        thb: t('js.cctv.src.thb'),
        nantou: '南投縣',
        newtaipei: '新北市',
        taipei: '臺北市',
        taoyuan: '桃園市',
        hsinchu: '新竹縣市',
        miaoli: '苗栗縣',
        taichung: '臺中市',
        changhua: '彰化縣',
        yunlin: '雲林縣',
        chiayi: '嘉義縣市',
        tainan: '臺南市',
        kaohsiung: '高雄市',
        pingtung: '屏東縣',
        yilan: '宜蘭縣',
        hualien: '花蓮縣',
        taitung: '臺東縣',
        keelung: '基隆市',
        offshore: t('js.cctv.src.offshore'),
        twlive: 'tw.live',
        govtw: t('js.cctv.src.govtw'),
        youtube: t('js.cctv.src.youtube'),
        'user-custom': t('js.cctv.src.userCustom'),
        custom: t('js.cctv.src.custom'),
        unknown: t('js.cctv.src.unknown'),
    }[vehicleOrKey] || String(vehicleOrKey || t('js.cctv.src.unknown'));
}

function cctvSourceGroup(vehicle) {
    const key = cctvSourceKey(vehicle);
    if (key === 'freeway') return 'freeway';
    if (key === 'thb') return 'highway';
    if ([
        'keelung', 'taipei', 'newtaipei', 'taoyuan', 'hsinchu', 'miaoli', 'taichung',
        'changhua', 'nantou', 'yunlin', 'chiayi', 'tainan', 'kaohsiung', 'pingtung',
        'yilan', 'hualien', 'taitung', 'offshore',
    ].includes(key)) return 'city';
    if (['govtw', 'youtube'].includes(key)) return 'scenic';
    if (key === 'user-custom') return 'custom';
    if (key === 'twlive') return 'aggregated';
    return 'custom';
}

function cctvSourceColor(vehicle) {
    return {
        freeway: '#2563eb',
        highway: '#0f8f7a',
        city: '#2eb8a8',
        scenic: '#d4a04a',
        aggregated: '#64748b',
        custom: '#334155',
    }[cctvSourceGroup(vehicle)] || '#2eb8a8';
}

function cctvPointLabel(vehicle) {
    const group = cctvSourceGroup(vehicle);
    if (group === 'freeway') return t('js.cctv.group.freeway');
    if (group === 'highway') return t('js.cctv.group.highway');
    if (group === 'city') return t('js.cctv.group.city');
    if (group === 'scenic') return t('js.cctv.group.scenic');
    if (group === 'aggregated') return t('js.cctv.group.aggregated');
    return t('js.cctv.group.custom');
}

function cctvStateLabel(vehicle) {
    return `${cctvPointLabel(vehicle)} · ${cctvSourceLabel(vehicle)}`;
}

function cctvIsSnapshot(vehicle) {
    const streamUrl = safeHttpUrl(vehicle?.stream_url);
    const imageUrl = safeHttpUrl(vehicle?.image_url);
    if (imageUrl && !streamUrl) return true;
    if (cctvSourceKey(vehicle) === 'thb') return true;
    return /\/snapshot(?:[/?#]|$)/i.test(streamUrl);
}

function cctvMediaActionLabel(vehicle) {
    return cctvIsSnapshot(vehicle) ? t('js.cctv.openSnapshot') : t('js.cctv.openLive');
}

function cctvMediaStatusLabel(vehicle, hasMedia = null) {
    const available = hasMedia ?? Boolean(safeHttpUrl(vehicle?.stream_url || vehicle?.image_url));
    if (!available) return t('js.cctv.noLink');
    return cctvIsSnapshot(vehicle) ? t('js.cctv.snapshotNoTime') : t('js.cctv.liveReady');
}

function cctvSourceAllowed(vehicle) {
    if (vehicle.kind !== 'cctv' || cctvSourceSelection === null) return true;
    return cctvSourceSelection.has(cctvSourceKey(vehicle));
}

function sourceToggleFor(kind) {
    return sourceToggles.find((toggle) => toggle.value === kind) || null;
}

function legendSourceToggleFor(kind) {
    return legendSourceToggles.find((toggle) => toggle.value === kind) || null;
}

function setSourceEnabled(kind, enabled, options = {}) {
    const sourceToggle = sourceToggleFor(kind);
    const legendToggle = legendSourceToggleFor(kind);
    [sourceToggle, legendToggle].forEach((toggle) => {
        if (!toggle || toggle.disabled) return;
        toggle.checked = enabled;
    });
    saveSettings();
    updateStaleToggleVisibility();
    if (options.reload) {
        lastLoadKey = '';
        scheduleLoadVehicles(options.delay ?? 80);
    }
}

function syncLegendToggles() {
    sourceToggles.forEach((toggle) => {
        const legendToggle = legendSourceToggleFor(toggle.value);
        if (legendToggle) {
            legendToggle.checked = toggle.checked;
            legendToggle.disabled = toggle.disabled;
        }
    });
    if (legendWeatherToggle && weatherLayerToggle) {
        legendWeatherToggle.checked = weatherLayerToggle.checked;
    }
    if (legendRealpriceToggle && realpriceLayerToggle) {
        legendRealpriceToggle.checked = realpriceLayerToggle.checked;
    }
    if (stalePanelToggle && staleLayerToggle) {
        stalePanelToggle.checked = staleLayerToggle.checked;
    }
    updateStaleToggleVisibility();
}

function updateStaleToggleVisibility() {
    const visible = freshnessFilteredSourceEnabled();
    [staleLayerControl, stalePanelControl].forEach((control) => {
        if (control) control.hidden = !visible;
    });
    [staleLayerToggle, stalePanelToggle].forEach((toggle) => {
        if (toggle) toggle.disabled = !visible;
    });
}

function setShowStale(enabled) {
    if (staleLayerToggle) staleLayerToggle.checked = enabled;
    if (stalePanelToggle) stalePanelToggle.checked = enabled;
    saveSettings();
    render();
}

function cctvSourceStats(vehicleList = vehicles) {
    const stats = new Map();
    vehicleList
        .filter((vehicle) => vehicle.kind === 'cctv')
        .forEach((vehicle) => {
            const key = cctvSourceKey(vehicle);
            const label = cctvSourceLabel(vehicle);
            const item = stats.get(key) || { key, label, count: 0 };
            item.count++;
            stats.set(key, item);
        });
    return Array.from(stats.values())
        .sort((a, b) => b.count - a.count || a.label.localeCompare(b.label, 'zh-Hant'));
}

function renderCctvSourceFilters(options = {}) {
    if (!cctvSourcePanel || !cctvSourceList) return;
    const stats = cctvSourceStats();
    const signature = JSON.stringify({
        enabled: cctvCacheSourceEnabled(),
        stats,
        selected: cctvSourceSelection ? Array.from(cctvSourceSelection).sort() : null,
    });
    if (!options.force && signature === cctvSourceFilterSignature) return;
    cctvSourceFilterSignature = signature;

    const visible = cctvCacheSourceEnabled() && stats.length > 0;
    cctvSourcePanel.hidden = !visible;
    if (!visible) {
        cctvSourceList.innerHTML = '';
        return;
    }

    cctvSourceList.innerHTML = stats.map((item) => {
        const checked = cctvSourceSelection === null || cctvSourceSelection.has(item.key);
        return `
            <label class="cctv-source-option">
                <span><input type="checkbox" class="cctv-source-toggle" value="${escapeHtml(item.key)}"${checked ? ' checked' : ''}> ${escapeHtml(item.label)}</span>
                <small>${item.count}</small>
            </label>
        `;
    }).join('');

    cctvSourceList.querySelectorAll('.cctv-source-toggle').forEach((toggle) => {
        toggle.addEventListener('change', () => {
            const allKeys = stats.map((item) => item.key);
            cctvSourceSelection = new Set(
                Array.from(cctvSourceList.querySelectorAll('.cctv-source-toggle'))
                    .filter((input) => input.checked)
                    .map((input) => input.value),
            );
            if (cctvSourceSelection.size === allKeys.length) {
                cctvSourceSelection = null;
            }
            saveSettings();
            renderCctvSourceFilters({ force: true });
            render();
        });
    });
}

function setAllCctvSources(enabled) {
    cctvSourceSelection = enabled ? null : new Set();
    saveSettings();
    renderCctvSourceFilters({ force: true });
    render();
}

function enableUserCustomCctvVisibility() {
    const sourceToggle = sourceToggleFor('cctv-cache');
    if (sourceToggle && !sourceToggle.checked) {
        setSourceEnabled('cctv-cache', true, { reload: false });
    }
    if (cctvSourceSelection !== null) {
        cctvSourceSelection.add('user-custom');
        saveSettings();
    }
}

function loadMonitoredVehicles() {
    try {
        const saved = JSON.parse(localStorage.getItem(monitorStorageKey) || '[]');
        monitoredVehicles = new Map(Array.isArray(saved) ? saved.map((item) => [item.id, item]) : []);
    } catch {
        monitoredVehicles = new Map();
    }
}

function saveMonitoredVehicles() {
    localStorage.setItem(monitorStorageKey, JSON.stringify(Array.from(monitoredVehicles.values())));
    updateMonitorPresetCurrent();
}

function loadPinnedMonitors() {
    try {
        const saved = JSON.parse(localStorage.getItem(pinnedMonitorStorageKey) || '[]');
        pinnedMonitorIds = new Set(Array.isArray(saved) ? saved.map(String) : []);
    } catch {
        pinnedMonitorIds = new Set();
    }
}

function savePinnedMonitors() {
    localStorage.setItem(pinnedMonitorStorageKey, JSON.stringify(Array.from(pinnedMonitorIds)));
}

function loadPinnedMonitorSizes() {
    try {
        const saved = JSON.parse(localStorage.getItem(pinnedMonitorSizeStorageKey) || '{}') || {};
        pinnedMonitorSizes = new Map(Object.entries(saved)
            .map(([id, value]) => [id, Math.min(680, Math.max(220, Number(value)))])
            .filter(([, value]) => Number.isFinite(value)));
    } catch {
        pinnedMonitorSizes = new Map();
    }
    if (pinnedMonitorSizes.size === 0) {
        try {
            const legacy = JSON.parse(localStorage.getItem(legacyPinnedMonitorSizeStorageKey) || '{}') || {};
            const width = Math.min(680, Math.max(220, Number(legacy.width)));
            if (Number.isFinite(width)) {
                pinnedMonitorIds.forEach((id) => pinnedMonitorSizes.set(id, width));
                savePinnedMonitorSizes();
            }
        } catch {
            // Ignore old malformed size preference.
        }
    }
}

function savePinnedMonitorSizes() {
    localStorage.setItem(pinnedMonitorSizeStorageKey, JSON.stringify(Object.fromEntries(pinnedMonitorSizes)));
}

function pinnedMonitorWidth(vehicleId) {
    const width = Number(pinnedMonitorSizes.get(String(vehicleId)));
    return Number.isFinite(width) ? Math.min(680, Math.max(220, width)) : bestPinnedMonitorWidth();
}

function bestPinnedMonitorWidth() {
    const mapWidth = map.getContainer()?.getBoundingClientRect()?.width || window.innerWidth || 360;
    if (mapWidth <= 560) return Math.max(240, Math.min(340, Math.round(mapWidth - 52)));
    if (mapWidth <= 920) return 320;
    return 360;
}

function setPinnedMonitorWidth(vehicleId, width, { persist = true } = {}) {
    const id = String(vehicleId || '');
    const clamped = Math.min(680, Math.max(220, Math.round(Number(width) || 340)));
    if (id) pinnedMonitorSizes.set(id, clamped);
    if (persist) savePinnedMonitorSizes();
    pinnedMonitorMarkers.get(id)?.getElement()
        ?.querySelector('.pinned-monitor-window')
        ?.style.setProperty('--pinned-monitor-width', `${clamped}px`);
    return clamped;
}

function resetPinnedMonitorWidth(vehicleId) {
    const id = String(vehicleId || '');
    if (id) pinnedMonitorSizes.delete(id);
    savePinnedMonitorSizes();
    return setPinnedMonitorWidth(id, bestPinnedMonitorWidth(), { persist: false });
}

function togglePinnedMonitor(vehicleId, pinned = null) {
    const nextPinned = pinned === null ? !pinnedMonitorIds.has(vehicleId) : Boolean(pinned);
    if (nextPinned) {
        pinnedMonitorIds.add(vehicleId);
    } else {
        pinnedMonitorIds.delete(vehicleId);
    }
    savePinnedMonitors();
    renderCctvMonitorWindows();
    return nextPinned;
}

function setAllPinnedMonitors(pinned) {
    const cctvs = monitoredCctvVehicles();
    if (pinned) {
        cctvs.forEach((vehicle) => pinnedMonitorIds.add(String(vehicle.id)));
    } else {
        cctvs.forEach((vehicle) => {
            pinnedMonitorIds.delete(String(vehicle.id));
            pinnedMonitorSizes.delete(String(vehicle.id));
        });
    }
    savePinnedMonitors();
    savePinnedMonitorSizes();
    renderCctvMonitorWindows();
}

function prunePinnedMonitors(activeIds = new Set(monitoredVehicles.keys())) {
    let changed = false;
    pinnedMonitorIds.forEach((id) => {
        if (!activeIds.has(id)) {
            pinnedMonitorIds.delete(id);
            pinnedMonitorSizes.delete(id);
            changed = true;
        }
    });
    if (changed) {
        savePinnedMonitors();
        savePinnedMonitorSizes();
    }
}

function updateMonitorToolbarButtons() {
    const cctvs = monitoredCctvVehicles();
    const total = cctvs.length;
    const pinnedCount = cctvs.filter((vehicle) => pinnedMonitorIds.has(String(vehicle.id))).length;
    if (pinAllMonitorsBtn) {
        pinAllMonitorsBtn.hidden = total === 0;
        pinAllMonitorsBtn.disabled = total === 0 || pinnedCount === total;
        pinAllMonitorsBtn.classList.toggle('active', total > 0 && pinnedCount === total);
        pinAllMonitorsBtn.title = total > 0 && pinnedCount === total ? t('js.monitor.pinAllDone') : t('map.pinAll');
        pinAllMonitorsBtn.setAttribute('aria-label', pinAllMonitorsBtn.title);
    }
    if (unpinAllMonitorsBtn) {
        unpinAllMonitorsBtn.hidden = total === 0;
        unpinAllMonitorsBtn.disabled = pinnedCount === 0;
        unpinAllMonitorsBtn.classList.toggle('active', pinnedCount > 0);
        unpinAllMonitorsBtn.title = pinnedCount > 0 ? t('js.monitor.unpinCount', { n: pinnedCount }) : t('js.monitor.unpinNone');
        unpinAllMonitorsBtn.setAttribute('aria-label', unpinAllMonitorsBtn.title);
    }
    if (minimapToggleBtn) {
        minimapToggleBtn.hidden = pinnedCount < 2;
        minimapToggleBtn.classList.toggle('active', pinnedMinimapEnabled);
        minimapToggleBtn.setAttribute('aria-pressed', String(pinnedMinimapEnabled));
        minimapToggleBtn.title = pinnedMinimapEnabled ? t('map.minimap') : t('map.minimapShow');
        minimapToggleBtn.setAttribute('aria-label', minimapToggleBtn.title);
    }
}

function monitorPresets() {
    try {
        const saved = JSON.parse(localStorage.getItem(monitorPresetStorageKey) || '[]');
        return Array.isArray(saved) ? saved.filter((item) => item?.id && Array.isArray(item.items)) : [];
    } catch {
        return [];
    }
}

function saveMonitorPresets(presets) {
    localStorage.setItem(monitorPresetStorageKey, JSON.stringify(presets));
}

function currentMonitorPresetPayload(name, id = `monitor-${Date.now()}`) {
    return {
        id,
        name,
        items: Array.from(monitoredVehicles.values()),
        size: monitorWindowSize(),
        dock: {
            columns: monitorDockColumns(),
            height: applyMonitorDockHeight(),
        },
        updated_at: new Date().toISOString(),
    };
}

function updateMonitorPresetCurrent() {
    if (!monitorPresetCurrent) return;
    const count = monitoredVehicles.size;
    if (count === 0) {
        monitorPresetCurrent.textContent = t('monitor.none');
        return;
    }
    const columns = monitorDockColumns();
    monitorPresetCurrent.textContent = t('js.monitor.current', { count, columns });
}

async function appConfirm({ title, text, confirmButtonText = t('js.confirm.ok'), cancelButtonText = t('js.confirm.cancel'), icon = 'question' } = {}) {
    if (!window.Swal) {
        locationStatus.textContent = t('js.confirm.dialogMissing');
        return false;
    }
    const result = await Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        reverseButtons: true,
        focusCancel: true,
        background: '#17212b',
        color: '#eef7fb',
        confirmButtonColor: '#2fb694',
        cancelButtonColor: '#56616d',
    });
    return result.isConfirmed;
}

async function appPrompt({
    title,
    inputLabel,
    inputValue = '',
    inputPlaceholder = '',
    confirmButtonText = t('js.confirm.save'),
    cancelButtonText = t('js.confirm.skip'),
} = {}) {
    if (!window.Swal) {
        locationStatus.textContent = t('js.confirm.nameMissing');
        return null;
    }
    const result = await Swal.fire({
        title,
        input: 'text',
        inputLabel,
        inputValue,
        inputPlaceholder,
        inputAttributes: {
            maxlength: 32,
            autocapitalize: 'off',
            autocomplete: 'off',
        },
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        reverseButtons: true,
        background: '#17212b',
        color: '#eef7fb',
        confirmButtonColor: '#2fb694',
        cancelButtonColor: '#56616d',
        inputValidator: (value) => {
            if (String(value || '').trim() === '') return t('js.confirm.nameRequired');
            return null;
        },
    });
    return result.isConfirmed ? String(result.value || '').trim() : null;
}

function cctvReportPayload(vehicle, type, description, contact) {
    return {
        type,
        description,
        contact,
        cctv_id: vehicle.id || '',
        camera_id: vehicle.plate || '',
        title: vehicle.route || vehicle.plate || t('js.monitor.liveTitle'),
        source_label: cctvSourceLabel(vehicle),
        stream_url: cctvMediaUrl(vehicle) || '',
        lat: Number.isFinite(Number(vehicle.lat)) ? String(vehicle.lat) : '',
        lng: Number.isFinite(Number(vehicle.lng)) ? String(vehicle.lng) : '',
        page_url: window.location.href,
    };
}

async function loadCctvReportTypes() {
    if (cctvReportTypesLoaded) return cctvReportTypes;
    try {
        const response = await fetch('api/cctv_report.php', {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.ok === false || !payload.types || typeof payload.types !== 'object') {
            throw new Error(payload.message || t('js.report.typesFail'));
        }
        const next = Object.fromEntries(
            Object.entries(payload.types)
                .map(([key, label]) => [String(key).trim(), String(label).trim()])
                .filter(([key, label]) => key !== '' && label !== '')
        );
        cctvReportTypes = Object.keys(next).length > 0 ? next : { ...defaultCctvReportTypes };
    } catch {
        cctvReportTypes = { ...defaultCctvReportTypes };
    } finally {
        cctvReportTypesLoaded = true;
    }
    return cctvReportTypes;
}

async function reportCctvIssue(vehicle) {
    if (!vehicle || vehicle.kind !== 'cctv') return;
    if (!window.Swal) {
        locationStatus.textContent = t('js.report.dialogMissing');
        return;
    }
    const types = await loadCctvReportTypes();
    const typeOptions = Object.entries(types)
        .map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`)
        .join('');
    if (typeOptions === '') {
        locationStatus.textContent = t('js.report.noTypes');
        return;
    }
    const result = await Swal.fire({
        title: t('js.report.title'),
        html: `
            <div class="cctv-report-dialog">
                <p><strong>${escapeHtml(vehicle.route || vehicle.plate || t('js.monitor.liveTitle'))}</strong></p>
                <label>${escapeHtml(t('js.report.typeLabel'))}
                    <select id="cctvReportType">${typeOptions}</select>
                </label>
                <label>${escapeHtml(t('js.report.descLabel'))}
                    <textarea id="cctvReportDescription" rows="4" maxlength="800" placeholder="${escapeHtml(t('js.report.descPh'))}"></textarea>
                </label>
                <label>${escapeHtml(t('js.report.contactLabel'))}
                    <input id="cctvReportContact" type="text" maxlength="160" placeholder="${escapeHtml(t('js.report.contactPh'))}">
                </label>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: t('js.report.submit'),
        cancelButtonText: t('js.confirm.cancel'),
        reverseButtons: true,
        focusConfirm: false,
        background: '#17212b',
        color: '#eef7fb',
        confirmButtonColor: '#2fb694',
        cancelButtonColor: '#56616d',
        preConfirm: () => {
            const type = document.querySelector('#cctvReportType')?.value || '';
            const description = document.querySelector('#cctvReportDescription')?.value.trim() || '';
            const contact = document.querySelector('#cctvReportContact')?.value.trim() || '';
            if (!type) {
                Swal.showValidationMessage(t('js.report.needType'));
                return false;
            }
            return { type, description, contact };
        },
    });
    if (!result.isConfirmed || !result.value) return;

    try {
        const response = await fetch('api/cctv_report.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(cctvReportPayload(vehicle, result.value.type, result.value.description, result.value.contact)),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.ok === false) {
            throw new Error(payload.message || t('js.report.failHttp', { status: response.status }));
        }
        locationStatus.textContent = payload.message || t('js.report.sent');
        await Swal.fire({
            icon: 'success',
            title: t('js.report.receivedTitle'),
            text: t('js.report.receivedText'),
            timer: 1800,
            showConfirmButton: false,
            background: '#17212b',
            color: '#eef7fb',
        });
    } catch (error) {
        locationStatus.textContent = error.message || t('js.report.failTitle');
        await Swal.fire({
            icon: 'error',
            title: t('js.report.failTitle'),
            text: error.message || t('js.report.retry'),
            background: '#17212b',
            color: '#eef7fb',
            confirmButtonColor: '#2fb694',
        });
    }
}

function monitorWindowSize() {
    try {
        const saved = JSON.parse(localStorage.getItem(monitorSizeStorageKey) || '{}') || {};
        return {
            width: Number.isFinite(Number(saved.width)) ? Number(saved.width) : 300,
        };
    } catch {
        return { width: 300 };
    }
}

function monitorDockAvailableWidth() {
    // Match CSS dock cap: min(..., calc(100% - 148px))
    const parent = cctvMonitorDock?.parentElement;
    const base = parent?.clientWidth || window.innerWidth || 1200;
    return Math.max(220, Math.round(base - 148));
}

function monitorDockMaxColumns() {
    if (window.matchMedia('(max-width: 560px)').matches) return 1;
    const cardWidth = monitorWindowSize().width;
    const gap = 10;
    const chrome = 8;
    const available = monitorDockAvailableWidth();
    // width = card*cols + gap*(cols-1) + chrome <= available
    const max = Math.floor((available + gap - chrome) / (cardWidth + gap));
    return Math.max(1, max);
}

function monitorDockColumns() {
    const columns = Number(monitorDockState().columns);
    const max = monitorDockMaxColumns();
    return Math.max(1, Math.min(max, Number.isFinite(columns) ? Math.round(columns) : 1));
}

function applyMonitorDockLayout() {
    const width = monitorWindowSize().width;
    const columns = window.matchMedia('(max-width: 560px)').matches ? 1 : monitorDockColumns();
    const gap = 10;
    document.documentElement.style.setProperty('--cctv-monitor-width', `${width}px`);
    document.documentElement.style.setProperty('--cctv-monitor-columns', String(columns));
    document.documentElement.style.setProperty('--cctv-monitor-dock-width', `${(width * columns) + (gap * Math.max(0, columns - 1)) + 8}px`);
    syncMonitorColumnButtons();
}

function syncMonitorColumnButtons() {
    const columns = monitorDockColumns();
    const max = monitorDockMaxColumns();
    const dec = cctvMonitorDock?.querySelector('.cctv-monitor-col-dec');
    const inc = cctvMonitorDock?.querySelector('.cctv-monitor-col-inc');
    if (dec) {
        dec.hidden = columns <= 1;
        dec.disabled = columns <= 1;
    }
    if (inc) {
        inc.hidden = false;
        inc.disabled = columns >= max;
        inc.title = columns >= max
            ? t('js.monitor.columnsMax')
            : t('js.monitor.addColumn');
    }
}

function adjustMonitorDockColumns(delta) {
    setMonitorDockColumns(monitorDockColumns() + Number(delta || 0));
}

function monitorDockHeightBounds() {
    let maxHeight = Math.max(180, Math.round(window.innerHeight - 118));
    if (cctvMonitorDock && window.matchMedia('(min-width: 561px)').matches) {
        const parent = cctvMonitorDock.parentElement?.getBoundingClientRect();
        const rect = cctvMonitorDock.getBoundingClientRect();
        if (parent) {
            const top = Number.isFinite(rect.top) ? Math.max(8, rect.top - parent.top) : 86;
            maxHeight = Math.max(180, Math.round(parent.height - top - 8));
        }
    }
    return {
        min: Math.min(180, maxHeight),
        max: maxHeight,
        fallback: Math.min(Math.round(window.innerHeight * 0.68), maxHeight),
    };
}

function applyMonitorDockHeight(state = monitorDockState()) {
    const bounds = monitorDockHeightBounds();
    const saved = Number(state.height);
    const height = Number.isFinite(saved) ? saved : bounds.fallback;
    const clamped = Math.max(bounds.min, Math.min(bounds.max, Math.round(height)));
    document.documentElement.style.setProperty('--cctv-monitor-dock-height', `${clamped}px`);
    return clamped;
}

function setMonitorDockHeight(height) {
    const bounds = monitorDockHeightBounds();
    const clamped = Math.max(bounds.min, Math.min(bounds.max, Math.round(Number(height) || bounds.fallback)));
    document.documentElement.style.setProperty('--cctv-monitor-dock-height', `${clamped}px`);
    saveMonitorDockState({ height: clamped });
    scheduleMonitorDockOverflowCheck();
}

function setMonitorWindowSize(width) {
    const clamped = Math.max(220, Math.min(520, Math.round(Number(width) || 300)));
    document.documentElement.style.removeProperty('--cctv-monitor-height');
    localStorage.setItem(monitorSizeStorageKey, JSON.stringify({ width: clamped }));
    applyMonitorDockLayout();
    applyMonitorDockState();
    scheduleMonitorDockOverflowCheck();
}

function adjustMonitorWindowSize(delta) {
    setMonitorWindowSize(monitorWindowSize().width + delta);
}

function setMonitorDockColumns(columns) {
    const max = monitorDockMaxColumns();
    saveMonitorDockState({ columns: Math.max(1, Math.min(max, Math.round(Number(columns) || 1))) });
    applyMonitorDockLayout();
    applyMonitorDockState();
    scheduleMonitorDockOverflowCheck();
}

async function saveCurrentMonitorPreset() {
    if (monitoredVehicles.size === 0) {
        locationStatus.textContent = t('js.monitor.noneSave');
        return;
    }
    const defaultName = t('js.monitor.defaultName', { time: new Date().toLocaleString(i18nJsLocale, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) });
    const name = monitorPresetName?.value.trim() || defaultName;
    const presets = monitorPresets();
    const trimmedName = name.trim();
    const existingIndex = presets.findIndex((preset) => preset.name === trimmedName);
    if (existingIndex >= 0) {
        const confirmed = await appConfirm({
            title: t('js.monitor.overwriteTitle'),
            text: t('js.monitor.overwriteText', { name: trimmedName }),
            confirmButtonText: t('js.monitor.overwriteBtn'),
        });
        if (!confirmed) return;
    }
    const preset = currentMonitorPresetPayload(
        trimmedName,
        existingIndex >= 0 ? presets[existingIndex].id : `monitor-${Date.now()}`
    );
    if (existingIndex >= 0) {
        presets[existingIndex] = preset;
    } else {
        presets.unshift(preset);
    }
    saveMonitorPresets(presets);
    if (monitorPresetName) monitorPresetName.value = '';
    renderMonitorPresets();
    locationStatus.textContent = t('js.monitor.saved', { name: trimmedName });
}

function loadMonitorPreset(presetId) {
    const preset = monitorPresets().find((item) => item.id === presetId);
    if (!preset) return;
    monitoredVehicles = new Map(preset.items.map((item) => [item.id, item]));
    selectedVehicleId = null;
    saveMonitoredVehicles();
    if (Number.isFinite(Number(preset.size?.width))) {
        localStorage.setItem(monitorSizeStorageKey, JSON.stringify({ width: Number(preset.size.width) }));
    }
    if (Number.isFinite(Number(preset.dock?.columns))) {
        const dockPatch = { columns: Number(preset.dock.columns), collapsed: false };
        if (Number.isFinite(Number(preset.dock?.height))) {
            dockPatch.height = Number(preset.dock.height);
        }
        saveMonitorDockState(dockPatch);
    } else {
        saveMonitorDockState({ collapsed: false });
    }
    applyMonitorDockLayout();
    applyMonitorDockState();
    vehicleCanvasLayer?.redraw();
    renderCctvMonitorWindows();
    renderList(visibleVehiclesCache());
    updateMonitorPresetCurrent();
    locationStatus.textContent = t('js.monitor.loaded', { name: preset.name || t('js.monitor.unnamed') });
}

async function overwriteMonitorPreset(presetId) {
    if (monitoredVehicles.size === 0) {
        locationStatus.textContent = t('js.monitor.noneOverwrite');
        return;
    }
    const presets = monitorPresets();
    const index = presets.findIndex((item) => item.id === presetId);
    const preset = presets[index];
    if (!preset) return;
    const confirmed = await appConfirm({
        title: t('js.monitor.overwriteOneTitle'),
        text: t('js.monitor.overwriteOneText', { count: monitoredVehicles.size, name: preset.name || t('js.monitor.unnamed') }),
        confirmButtonText: t('js.monitor.overwriteBtn'),
    });
    if (!confirmed) return;
    presets[index] = currentMonitorPresetPayload(preset.name || t('js.monitor.unnamed'), preset.id);
    saveMonitorPresets(presets);
    renderMonitorPresets();
    locationStatus.textContent = t('js.monitor.overwritten', { name: preset.name || t('js.monitor.unnamed') });
}

async function renameMonitorPreset(presetId) {
    const presets = monitorPresets();
    const index = presets.findIndex((item) => item.id === presetId);
    const preset = presets[index];
    if (!preset) return;
    const name = await appPrompt({
        title: t('js.monitor.renameTitle'),
        inputLabel: t('js.monitor.renameLabel'),
        inputValue: preset.name || '',
        confirmButtonText: t('js.monitor.renameBtn'),
        cancelButtonText: t('js.confirm.cancel'),
    });
    if (!name) return;
    const duplicate = presets.some((item) => item.id !== presetId && item.name === name);
    if (duplicate) {
        locationStatus.textContent = t('js.monitor.dupName', { name });
        return;
    }
    presets[index] = { ...preset, name, updated_at: new Date().toISOString() };
    saveMonitorPresets(presets);
    renderMonitorPresets();
    locationStatus.textContent = t('js.monitor.renamed', { name });
}

async function deleteMonitorPreset(presetId) {
    const presets = monitorPresets();
    const preset = presets.find((item) => item.id === presetId);
    if (!preset) return;
    const confirmed = await appConfirm({
        title: t('js.monitor.deleteTitle'),
        text: t('js.monitor.deleteText', { name: preset.name || t('js.monitor.unnamed') }),
        icon: 'warning',
        confirmButtonText: t('js.monitor.deleteBtn'),
    });
    if (!confirmed) return;
    saveMonitorPresets(presets.filter((item) => item.id !== presetId));
    renderMonitorPresets();
    locationStatus.textContent = t('js.monitor.deleted', { name: preset.name || t('js.monitor.unnamed') });
}

function renderMonitorPresets() {
    if (!monitorPresetList) return;
    const presets = monitorPresets();
    monitorPresetList.innerHTML = '';
    monitorPresetList.hidden = presets.length === 0;
    presets.forEach((preset) => {
        const row = document.createElement('div');
        row.className = 'saved-location-row monitor-preset-row';
        row.innerHTML = `
            <div class="monitor-preset-summary">
                <div>
                    <strong></strong>
                    <small></small>
                </div>
            </div>
            <div class="monitor-preset-actions">
                <button type="button" class="monitor-preset-load">${t('js.monitor.load')}</button>
                <button type="button" class="monitor-preset-overwrite">${t('js.monitor.overwriteBtn')}</button>
                <button type="button" class="monitor-preset-rename">${t('js.monitor.renameBtn')}</button>
                <button type="button" class="monitor-preset-delete" title="${t('js.monitor.deleteTitle')}" aria-label="${t('js.monitor.deleteTitle')}">${t('js.monitor.deleteBtn')}</button>
            </div>
        `;
        const title = row.querySelector('strong');
        const meta = row.querySelector('small');
        if (title) title.textContent = preset.name || t('js.monitor.unnamed');
        if (meta) {
            const columns = Number(preset.dock?.columns) || 1;
            meta.textContent = t('js.monitor.presetMeta', { count: preset.items.length, columns });
        }
        row.querySelector('.monitor-preset-load')?.addEventListener('click', () => loadMonitorPreset(preset.id));
        row.querySelector('.monitor-preset-overwrite')?.addEventListener('click', () => overwriteMonitorPreset(preset.id));
        row.querySelector('.monitor-preset-rename')?.addEventListener('click', () => renameMonitorPreset(preset.id));
        row.querySelector('.monitor-preset-delete')?.addEventListener('click', () => deleteMonitorPreset(preset.id));
        monitorPresetList.appendChild(row);
    });
}

function clearAllMonitors() {
    if (monitoredVehicles.size === 0) return;
    monitoredVehicles.clear();
    selectedVehicleId = null;
    saveMonitoredVehicles();
    vehicleCanvasLayer?.redraw();
    renderCctvMonitorWindows();
    renderList(visibleVehiclesCache());
}

function moveMonitor(vehicleId, direction) {
    const entries = Array.from(monitoredVehicles.entries());
    const index = entries.findIndex(([id]) => id === vehicleId);
    const nextIndex = index + direction;
    if (index < 0 || nextIndex < 0 || nextIndex >= entries.length) return;
    [entries[index], entries[nextIndex]] = [entries[nextIndex], entries[index]];
    monitoredVehicles = new Map(entries);
    saveMonitoredVehicles();
    renderCctvMonitorWindows();
    renderList(visibleVehiclesCache());
}

function monitorDockState() {
    try {
        return JSON.parse(localStorage.getItem(monitorDockStorageKey) || '{}') || {};
    } catch {
        return {};
    }
}

function saveMonitorDockState(patch = {}) {
    const next = { ...monitorDockState(), ...patch };
    localStorage.setItem(monitorDockStorageKey, JSON.stringify(next));
    return next;
}

function monitorFeedInfoHidden() {
    return Boolean(monitorDockState().hideFeedInfo);
}

function applyMonitorFeedInfoVisibility() {
    const hidden = monitorFeedInfoHidden();
    document.documentElement.classList.toggle('monitor-feed-info-hidden', hidden);
    const button = cctvMonitorDock?.querySelector('.cctv-monitor-info-toggle');
    if (button) {
        button.classList.toggle('is-off', hidden);
        button.setAttribute('aria-pressed', String(hidden));
        const label = hidden ? t('js.monitor.showFeedInfo') : t('js.monitor.hideFeedInfo');
        button.title = label;
        button.setAttribute('aria-label', label);
        button.innerHTML = hidden ? monitorInfoOffSvg : monitorInfoOnSvg;
    }
}

function setMonitorFeedInfoHidden(hidden) {
    saveMonitorDockState({ hideFeedInfo: Boolean(hidden) });
    applyMonitorFeedInfoVisibility();
    scheduleMonitorDockOverflowCheck();
}

function applyMonitorDockState() {
    if (!cctvMonitorDock) return;
    const state = monitorDockState();
    applyMonitorDockHeight(state);
    applyMonitorFeedInfoVisibility();
    cctvMonitorDock.classList.toggle('collapsed', Boolean(state.collapsed));
    if (Number.isFinite(Number(state.left)) && Number.isFinite(Number(state.top))) {
        const parent = cctvMonitorDock.parentElement?.getBoundingClientRect();
        const rect = cctvMonitorDock.getBoundingClientRect();
        const safeRight = window.matchMedia('(min-width: 921px)').matches ? 74 : 8;
        const maxLeft = parent ? Math.max(8, parent.width - rect.width - safeRight) : Number(state.left);
        const maxTop = parent ? Math.max(8, parent.height - rect.height - 8) : Number(state.top);
        const left = Math.max(8, Math.min(maxLeft, Number(state.left)));
        const top = Math.max(8, Math.min(maxTop, Number(state.top)));
        cctvMonitorDock.style.left = `${left}px`;
        cctvMonitorDock.style.top = `${top}px`;
        cctvMonitorDock.style.right = 'auto';
        cctvMonitorDock.style.bottom = 'auto';
    } else {
        cctvMonitorDock.style.left = '';
        cctvMonitorDock.style.top = '';
        cctvMonitorDock.style.right = '';
        cctvMonitorDock.style.bottom = '';
    }
}

function setMonitorDockCollapsed(collapsed) {
    if (!cctvMonitorDock) return;
    cctvMonitorDock.classList.toggle('collapsed', collapsed);
    cctvMonitorDock.classList.remove('can-resize');
    saveMonitorDockState({ collapsed });
    const button = cctvMonitorDock.querySelector('.cctv-monitor-collapse');
    if (button) {
        button.textContent = collapsed ? '▴' : '▾';
        button.title = collapsed ? t('js.monitor.expand') : t('js.monitor.collapse');
        button.setAttribute('aria-label', collapsed ? t('js.monitor.expand') : t('js.monitor.collapse'));
        button.setAttribute('aria-expanded', String(!collapsed));
    }
    if (!collapsed) scheduleMonitorDockOverflowCheck();
}

function initMonitorDockDrag(handle) {
    if (!handle || !cctvMonitorDock) return;
    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;
    const onMove = (event) => {
        const parent = cctvMonitorDock.parentElement?.getBoundingClientRect();
        const rect = cctvMonitorDock.getBoundingClientRect();
        if (!parent) return;
        const safeRight = window.matchMedia('(min-width: 921px)').matches ? 74 : 8;
        const maxLeft = Math.max(8, parent.width - rect.width - safeRight);
        const maxTop = Math.max(8, parent.height - rect.height - 8);
        const left = Math.max(8, Math.min(maxLeft, startLeft + event.clientX - startX));
        const top = Math.max(8, Math.min(maxTop, startTop + event.clientY - startY));
        cctvMonitorDock.style.left = `${left}px`;
        cctvMonitorDock.style.top = `${top}px`;
        cctvMonitorDock.style.right = 'auto';
        cctvMonitorDock.style.bottom = 'auto';
    };
    const onUp = () => {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        const rect = cctvMonitorDock.getBoundingClientRect();
        const parent = cctvMonitorDock.parentElement?.getBoundingClientRect();
        if (parent) {
            saveMonitorDockState({
                left: Math.round(rect.left - parent.left),
                top: Math.round(rect.top - parent.top),
            });
        }
    };
    handle.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const rect = cctvMonitorDock.getBoundingClientRect();
        const parent = cctvMonitorDock.parentElement?.getBoundingClientRect();
        if (!parent) return;
        startX = event.clientX;
        startY = event.clientY;
        startLeft = rect.left - parent.left;
        startTop = rect.top - parent.top;
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
    });
}

function initMonitorDockResize(handle) {
    if (!handle || !cctvMonitorDock) return;
    let startY = 0;
    let startHeight = 0;
    let active = false;
    let pointerId = null;
    let wasMapDragging = false;
    let wasScrollZoom = false;
    const onMove = (event) => {
        if (!active) return;
        event.preventDefault();
        event.stopPropagation();
        setMonitorDockHeight(startHeight + (event.clientY - startY));
    };
    const finish = () => {
        if (!active) return;
        active = false;
        window.removeEventListener('pointermove', onMove, true);
        window.removeEventListener('pointerup', finish, true);
        window.removeEventListener('pointercancel', finish, true);
        window.removeEventListener('blur', finish, true);
        document.removeEventListener('pointerup', finish, true);
        document.removeEventListener('pointercancel', finish, true);
        document.removeEventListener('keydown', onKeyDown, true);
        if (pointerId !== null && handle.hasPointerCapture?.(pointerId)) {
            try {
                handle.releasePointerCapture(pointerId);
            } catch (error) {
                // Pointer capture may already be released by the browser.
            }
        }
        pointerId = null;
        if (wasMapDragging) map.dragging.enable();
        if (wasScrollZoom) map.scrollWheelZoom.enable();
        handle.classList.remove('is-resizing');
        handle.blur();
        document.body.classList.remove('is-resizing-monitor-dock');
        scheduleMonitorDockOverflowCheck();
    };
    const onKeyDown = (event) => {
        if (event.key === 'Escape') {
            finish();
        }
    };
    handle.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (active) {
            finish();
        }
        active = true;
        pointerId = event.pointerId;
        startY = event.clientY;
        startHeight = cctvMonitorDock.getBoundingClientRect().height;
        handle.setPointerCapture?.(event.pointerId);
        handle.classList.add('is-resizing');
        document.body.classList.add('is-resizing-monitor-dock');
        wasMapDragging = map.dragging.enabled();
        wasScrollZoom = map.scrollWheelZoom.enabled();
        map.dragging.disable();
        map.scrollWheelZoom.disable();
        window.addEventListener('pointermove', onMove, { passive: false, capture: true });
        window.addEventListener('pointerup', finish, { passive: false, capture: true });
        window.addEventListener('pointercancel', finish, { passive: false, capture: true });
        window.addEventListener('blur', finish, { capture: true });
        document.addEventListener('pointerup', finish, { passive: false, capture: true });
        document.addEventListener('pointercancel', finish, { passive: false, capture: true });
        document.addEventListener('keydown', onKeyDown, { capture: true });
    });
    handle.addEventListener('lostpointercapture', finish);
}

function ensureCctvMonitorDockControls(count) {
    if (!cctvMonitorDock) return;
    let controls = cctvMonitorDock.querySelector('.cctv-monitor-controls');
    if (!controls) {
        controls = document.createElement('div');
        controls.className = 'cctv-monitor-controls';
        controls.innerHTML = `
            <div class="cctv-monitor-control-row cctv-monitor-heading-row">
                <button type="button" class="cctv-monitor-drag" title="${t('js.monitor.drag')}" aria-label="${t('js.monitor.drag')}">✥</button>
                <span class="cctv-monitor-title">${t('js.monitor.dockTitle')} <span></span></span>
                <button type="button" class="cctv-monitor-collapse" title="${t('js.monitor.collapse')}" aria-label="${t('js.monitor.collapse')}" aria-expanded="true">▾</button>
            </div>
            <div class="cctv-monitor-control-row cctv-monitor-action-row">
                <button type="button" class="cctv-monitor-clear" title="${t('js.monitor.clearAll')}" aria-label="${t('js.monitor.clearAll')}">×</button>
                <button type="button" class="cctv-monitor-size" data-size-delta="-40" title="${t('js.monitor.shrinkFeed')}" aria-label="${t('js.monitor.shrinkFeed')}">${monitorSizeDownSvg}</button>
                <button type="button" class="cctv-monitor-size" data-size-delta="40" title="${t('js.monitor.growFeed')}" aria-label="${t('js.monitor.growFeed')}">${monitorSizeUpSvg}</button>
                <button type="button" class="cctv-monitor-info-toggle" title="${t('js.monitor.hideFeedInfo')}" aria-label="${t('js.monitor.hideFeedInfo')}" aria-pressed="false">${monitorInfoOnSvg}</button>
                <span class="cctv-monitor-column-controls" title="${t('js.monitor.columns')}">
                    <button type="button" class="cctv-monitor-col-dec" title="${t('js.monitor.removeColumn')}" aria-label="${t('js.monitor.removeColumn')}" hidden>${monitorColDownSvg}</button>
                    <button type="button" class="cctv-monitor-col-inc" title="${t('js.monitor.addColumn')}" aria-label="${t('js.monitor.addColumn')}">${monitorColUpSvg}</button>
                </span>
            </div>
        `;
        cctvMonitorDock.prepend(controls);
        initMonitorDockDrag(controls.querySelector('.cctv-monitor-drag'));
        initMonitorDockDrag(controls.querySelector('.cctv-monitor-title'));
        controls.querySelector('.cctv-monitor-clear')?.addEventListener('click', (event) => {
            event.stopPropagation();
            clearAllMonitors();
        });
        controls.querySelectorAll('.cctv-monitor-size').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.stopPropagation();
                adjustMonitorWindowSize(Number(button.dataset.sizeDelta || 0));
                button.focus({ preventScroll: true });
            });
        });
        controls.querySelector('.cctv-monitor-info-toggle')?.addEventListener('click', (event) => {
            event.stopPropagation();
            setMonitorFeedInfoHidden(!monitorFeedInfoHidden());
        });
        controls.querySelector('.cctv-monitor-col-dec')?.addEventListener('click', (event) => {
            event.stopPropagation();
            adjustMonitorDockColumns(-1);
        });
        controls.querySelector('.cctv-monitor-col-inc')?.addEventListener('click', (event) => {
            event.stopPropagation();
            adjustMonitorDockColumns(1);
        });
        controls.querySelector('.cctv-monitor-collapse')?.addEventListener('click', (event) => {
            event.stopPropagation();
            setMonitorDockCollapsed(!cctvMonitorDock.classList.contains('collapsed'));
        });
    }
    applyMonitorDockLayout();
    applyMonitorFeedInfoVisibility();
    const countEl = controls.querySelector('.cctv-monitor-title span');
    if (countEl) countEl.textContent = t('js.monitor.countN', { n: count });
    setMonitorDockCollapsed(Boolean(monitorDockState().collapsed));
    // Upgrade older dock markup (columns <select> or plain +/- text buttons).
    const needsIconUpgrade = !controls.querySelector('.cctv-monitor-size svg')
        || controls.querySelector('select.cctv-monitor-columns')
        || !controls.querySelector('.cctv-monitor-info-toggle');
    if (needsIconUpgrade) {
        controls.remove();
        return ensureCctvMonitorDockControls(count);
    }
}

function ensureCctvMonitorStrip() {
    if (!cctvMonitorDock) return null;
    let strip = cctvMonitorDock.querySelector(':scope > .cctv-monitor-strip');
    if (!strip) {
        strip = document.createElement('div');
        strip.className = 'cctv-monitor-strip';
        cctvMonitorDock.appendChild(strip);
    }
    return strip;
}

function ensureCctvMonitorResizeHandle() {
    if (!cctvMonitorDock) return;
    let handle = cctvMonitorDock.querySelector(':scope > .cctv-monitor-resize');
    if (!handle) {
        handle = document.createElement('button');
        handle.type = 'button';
        handle.className = 'cctv-monitor-resize';
        handle.title = t('js.monitor.resizeHeight');
        handle.setAttribute('aria-label', t('js.monitor.resizeHeight'));
        cctvMonitorDock.appendChild(handle);
        initMonitorDockResize(handle);
    }
}

let monitorDockOverflowFrame = 0;

function updateMonitorDockOverflowState() {
    monitorDockOverflowFrame = 0;
    if (!cctvMonitorDock || cctvMonitorDock.hidden || cctvMonitorDock.classList.contains('collapsed')) {
        cctvMonitorDock?.classList.remove('can-resize');
        return;
    }
    const strip = cctvMonitorDock.querySelector(':scope > .cctv-monitor-strip');
    if (!strip) {
        cctvMonitorDock.classList.remove('can-resize');
        return;
    }
    const dockStyle = window.getComputedStyle(cctvMonitorDock);
    const stripStyle = window.getComputedStyle(strip);
    const controls = cctvMonitorDock.querySelector(':scope > .cctv-monitor-controls');
    const maxDockHeight = Number.parseFloat(dockStyle.maxHeight) || cctvMonitorDock.clientHeight || window.innerHeight;
    const dockPadding = Number.parseFloat(dockStyle.paddingTop || '0') + Number.parseFloat(dockStyle.paddingBottom || '0');
    const stripPadding = Number.parseFloat(stripStyle.paddingTop || '0') + Number.parseFloat(stripStyle.paddingBottom || '0');
    const controlHeight = controls ? controls.getBoundingClientRect().height : 0;
    const availableStripHeight = Math.max(80, maxDockHeight - controlHeight - dockPadding - stripPadding);
    const overflow = strip.scrollHeight > availableStripHeight + 4;
    cctvMonitorDock.classList.toggle('can-resize', overflow);
}

function scheduleMonitorDockOverflowCheck() {
    if (monitorDockOverflowFrame) {
        window.cancelAnimationFrame(monitorDockOverflowFrame);
    }
    monitorDockOverflowFrame = window.requestAnimationFrame(() => {
        monitorDockOverflowFrame = window.requestAnimationFrame(updateMonitorDockOverflowState);
    });
}

function monitorColor(id) {
    const saved = monitoredVehicles.get(id);
    if (saved?.color) return saved.color;
    const ids = Array.from(monitoredVehicles.keys());
    const index = Math.max(0, ids.indexOf(id));
    return monitorPalette[index % monitorPalette.length];
}

function monitorSnapshot(vehicle) {
    return {
        id: vehicle.id,
        kind: vehicle.kind,
        route: vehicle.route,
        plate: vehicle.plate,
        headsign: vehicle.headsign,
        operator: vehicle.operator,
        lat: Number(vehicle.lat),
        lng: Number(vehicle.lng),
        status: vehicle.status,
        note: vehicle.note,
        stream_url: vehicle.stream_url,
        image_url: vehicle.image_url,
        source_key: vehicle.source_key,
        source_label: vehicle.source_label,
        color: monitorPalette[monitoredVehicles.size % monitorPalette.length],
        added_at: new Date().toISOString(),
    };
}

function youtubeEmbedUrl(rawUrl) {
    try {
        const url = new URL(rawUrl);
        const host = url.hostname.toLowerCase().replace(/^www\./, '');
        let id = '';
        if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'music.youtube.com') {
            const parts = url.pathname.split('/').filter(Boolean);
            if (url.pathname === '/embed/live_stream') {
                const channelId = url.searchParams.get('channel') || '';
                if (/^UC[A-Za-z0-9_-]{20,}$/.test(channelId)) {
                    return `https://www.youtube-nocookie.com/embed/live_stream?channel=${encodeURIComponent(channelId)}&autoplay=1&mute=1&playsinline=1&rel=0`;
                }
            }
            if (parts[0] === 'channel' && parts[2] === 'live' && /^UC[A-Za-z0-9_-]{20,}$/.test(parts[1] || '')) {
                return `https://www.youtube-nocookie.com/embed/live_stream?channel=${encodeURIComponent(parts[1])}&autoplay=1&mute=1&playsinline=1&rel=0`;
            }
            if (url.pathname === '/watch') {
                id = url.searchParams.get('v') || '';
            } else {
                if (parts[0] === 'embed' || parts[0] === 'shorts' || parts[0] === 'live') {
                    id = parts[1] || '';
                }
            }
        } else if (host === 'youtu.be') {
            id = url.pathname.split('/').filter(Boolean)[0] || '';
        }
        if (!/^[A-Za-z0-9_-]{6,}$/.test(id)) return '';
        return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}?autoplay=1&mute=1&playsinline=1&rel=0`;
    } catch {
        return '';
    }
}

function cctvMediaUrl(vehicle) {
    const rawUrl = safeHttpUrl(vehicle?.stream_url || vehicle?.image_url);
    if (!rawUrl) return '';
    const youtubeUrl = youtubeEmbedUrl(rawUrl);
    if (youtubeUrl) return youtubeUrl;
    const taoyuanFlvMatch = rawUrl.match(/^https:\/\/cctvtraffic\.tycg\.gov\.tw\/play\/(nvr\d+)\/(camera\d+)$/i);
    if (taoyuanFlvMatch) {
        const src = `https://cctvtraffic.tycg.gov.tw/hls/${taoyuanFlvMatch[1]}/${taoyuanFlvMatch[2]}/live.m3u8`;
        const title = vehicle?.route || vehicle?.plate || t('js.live.taoyuan');
        return `cctv_player.php?src=${encodeURIComponent(src)}&open=${encodeURIComponent(rawUrl)}&title=${encodeURIComponent(title)}`;
    }
    const ntpcMatch = rawUrl.match(/^https:\/\/atis\.ntpc\.gov\.tw\/ATIS\/ShowFrame4CCTV\/(C\d{6})$/i);
    if (ntpcMatch) {
        const title = vehicle?.route || vehicle?.plate || t('js.live.newtaipei');
        return `ntpc_cctv_player.php?device=${encodeURIComponent(ntpcMatch[1].toUpperCase())}&title=${encodeURIComponent(title)}`;
    }
    const nantouMatch = rawUrl.match(/^https:\/\/trafficcctv\.nantou\.gov\.tw\/cctv\/(\d{3})\.html$/i);
    if (nantouMatch) {
        const id = nantouMatch[1];
        const src = `https://trafficcctv.nantou.gov.tw/cctv/${id}/${id}.m3u8`;
        const title = vehicle?.route || vehicle?.plate || t('js.live.nantou');
        return `cctv_player.php?src=${encodeURIComponent(src)}&title=${encodeURIComponent(title)}`;
    }
    const freewayMatch = rawUrl.match(/^https:\/\/cctv[a-z0-9-]*\.freeway\.gov\.tw\/abs2mjpg\/(?:b?m?jpg|jpg)\?camera=\d+$/i);
    if (freewayMatch) {
        const title = vehicle?.route || vehicle?.plate || t('js.live.freeway');
        return `cctv_image.php?src=${encodeURIComponent(rawUrl)}&title=${encodeURIComponent(title)}`;
    }
    const tainanMatch = rawUrl.match(/^https:\/\/trafficvideo\d*\.tainan\.gov\.tw\/[A-Za-z0-9_-]+$/i);
    if (tainanMatch) {
        const title = vehicle?.route || vehicle?.plate || t('js.live.tainan');
        return `cctv_image.php?src=${encodeURIComponent(rawUrl)}&title=${encodeURIComponent(title)}`;
    }
    const thbMatch = rawUrl.match(/^https:\/\/cctv-ss\d+\.thb\.gov\.tw(?::443)?\/[A-Za-z0-9+()._\-/%]+(?:\/snapshot)?$/i);
    if (thbMatch) {
        const title = vehicle?.route || vehicle?.plate || t('js.monitor.snapshotTitle');
        return `cctv_image.php?src=${encodeURIComponent(rawUrl)}&title=${encodeURIComponent(title)}`;
    }
    return rawUrl;
}

function cctvOpenUrl(vehicle) {
    return safeHttpUrl(vehicle?.stream_url || vehicle?.image_url) || cctvMediaUrl(vehicle);
}

function isAppleMobileBrowser() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function isFlvMonitorUrl(url) {
    return /^flv_cctv_player\.php\?/i.test(String(url || ''));
}

function canEmbedMonitorUrl(url) {
    if (isFlvMonitorUrl(url) && isAppleMobileBrowser()) return false;
    return true;
}

function monitoredCctvVehicles() {
    if (!cctvCacheSourceEnabled()) return [];
    const byId = new Map(vehicles.map((vehicle) => [vehicle.id, vehicle]));
    return Array.from(monitoredVehicles.values())
        .map((saved) => ({ ...saved, ...(byId.get(saved.id) || {}) }))
        .filter((vehicle) => vehicle.kind === 'cctv' && cctvSourceAllowed(vehicle) && cctvMediaUrl(vehicle));
}

function focusMonitorVehicle(vehicleId, fallbackVehicle = null) {
    const latest = vehicles.find((item) => item.id === vehicleId)
        || monitoredVehicles.get(vehicleId)
        || fallbackVehicle;
    const lat = Number(latest?.lat);
    const lng = Number(latest?.lng);
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
        map.setView([lat, lng], Math.max(map.getZoom(), 17));
        selectedVehicleId = latest.id || vehicleId;
        vehicleCanvasLayer?.redraw();
        syncPinnedMinimap({ highlightId: String(vehicleId) });
        return true;
    }
    locationStatus.textContent = t('js.monitor.noCoords');
    return false;
}

function loadPinnedMinimapPreference() {
    try {
        const raw = localStorage.getItem(pinnedMinimapPrefStorageKey);
        if (raw === null) return true;
        return raw === '1' || raw === 'true';
    } catch (_) {
        return true;
    }
}

function savePinnedMinimapPreference(enabled) {
    pinnedMinimapEnabled = Boolean(enabled);
    try {
        localStorage.setItem(pinnedMinimapPrefStorageKey, pinnedMinimapEnabled ? '1' : '0');
    } catch (_) {}
    updateMonitorToolbarButtons();
    syncPinnedMinimap();
}

function pinnedMinimapPoints() {
    return monitoredCctvVehicles()
        .filter((vehicle) => pinnedMonitorIds.has(String(vehicle.id)))
        .map((vehicle) => {
            const lat = Number(vehicle.lat);
            const lng = Number(vehicle.lng);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
            return {
                id: String(vehicle.id),
                lat,
                lng,
                color: monitorColor(vehicle.id),
                title: vehicle.route || vehicle.plate || vehicle.id,
            };
        })
        .filter(Boolean);
}

function pinnedPointsSpreadOutsideView(points) {
    if (!points || points.length < 2) return false;
    const view = map.getBounds().pad(-0.04);
    return points.some((point) => !view.contains([point.lat, point.lng]));
}

function shouldShowPinnedMinimap(points = pinnedMinimapPoints()) {
    return pinnedMinimapEnabled && pinnedPointsSpreadOutsideView(points);
}

function ensurePinnedMinimapMap() {
    if (!pinnedMinimapMapEl || pinnedMinimapMap) return pinnedMinimapMap;
    pinnedMinimapMap = L.map(pinnedMinimapMapEl, {
        zoomControl: false,
        attributionControl: false,
        dragging: false,
        doubleClickZoom: false,
        scrollWheelZoom: false,
        boxZoom: false,
        keyboard: false,
        touchZoom: false,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        className: 'basemap-light',
    }).addTo(pinnedMinimapMap);
    pinnedMinimapMarkersLayer = L.layerGroup().addTo(pinnedMinimapMap);
    if (pinnedMinimapEl) {
        L.DomEvent.disableClickPropagation(pinnedMinimapEl);
        L.DomEvent.disableScrollPropagation(pinnedMinimapEl);
    }
    return pinnedMinimapMap;
}

function updatePinnedMinimapViewportRect() {
    if (!pinnedMinimapMap) return;
    const bounds = map.getBounds();
    if (pinnedMinimapViewportRect) {
        pinnedMinimapViewportRect.setBounds(bounds);
        return;
    }
    pinnedMinimapViewportRect = L.rectangle(bounds, {
        className: 'pinned-minimap-viewport',
        color: '#5fe0c7',
        weight: 1.5,
        fillColor: '#5fe0c7',
        fillOpacity: 0.12,
        interactive: false,
    }).addTo(pinnedMinimapMap);
}

function renderPinnedMinimapMarkers(points, highlightId = '') {
    if (!pinnedMinimapMarkersLayer) return;
    pinnedMinimapMarkersLayer.clearLayers();
    points.forEach((point) => {
        const marker = L.marker([point.lat, point.lng], {
            interactive: true,
            keyboard: false,
            title: point.title,
            icon: L.divIcon({
                className: '',
                html: `<span class="pinned-minimap-dot${highlightId && highlightId === point.id ? ' is-active' : ''}" style="background:${point.color}"></span>`,
                iconSize: [12, 12],
                iconAnchor: [6, 6],
            }),
        });
        marker.on('click', (event) => {
            L.DomEvent.stopPropagation(event);
            focusMonitorVehicle(point.id);
        });
        pinnedMinimapMarkersLayer.addLayer(marker);
    });
}

function fitPinnedMinimapToPoints(points) {
    if (!pinnedMinimapMap || !points.length) return;
    const bounds = L.latLngBounds(points.map((point) => [point.lat, point.lng]));
    if (!bounds.isValid()) return;
    pinnedMinimapMap.fitBounds(bounds.pad(0.22), { animate: false, maxZoom: 12 });
}

function setPinnedMinimapVisible(visible) {
    if (!pinnedMinimapEl) return;
    pinnedMinimapEl.hidden = !visible;
    if (visible) {
        ensurePinnedMinimapMap();
        window.setTimeout(() => pinnedMinimapMap?.invalidateSize(false), 0);
    }
}

function syncPinnedMinimap({ highlightId = '' } = {}) {
    const points = pinnedMinimapPoints();
    const show = shouldShowPinnedMinimap(points);
    setPinnedMinimapVisible(show);
    updateMonitorToolbarButtons();
    if (!show) {
        pinnedMinimapLastPinKey = '';
        return;
    }
    ensurePinnedMinimapMap();
    const pinKey = points.map((point) => point.id).sort().join('|');
    if (pinKey !== pinnedMinimapLastPinKey) {
        fitPinnedMinimapToPoints(points);
        pinnedMinimapLastPinKey = pinKey;
    }
    renderPinnedMinimapMarkers(points, highlightId);
    updatePinnedMinimapViewportRect();
}

function fitAllPinnedMonitors() {
    const points = pinnedMinimapPoints();
    if (points.length === 0) return;
    const bounds = L.latLngBounds(points.map((point) => [point.lat, point.lng]));
    if (!bounds.isValid()) return;
    map.fitBounds(bounds.pad(0.18), { padding: [48, 48], maxZoom: 16, animate: true });
    window.setTimeout(() => syncPinnedMinimap(), 320);
}

function ensurePinnedMonitorLayer() {
    if (!pinnedMonitorLayer) {
        pinnedMonitorLayer = L.layerGroup().addTo(map);
    }
    return pinnedMonitorLayer;
}

function isOlmCctvEmbedUrl(url) {
    return /^(cctv_player|cctv_image|ntpc_cctv_player|flv_cctv_player)\.php\?/i.test(String(url || ''));
}

function monitorClockText(date = new Date()) {
    try {
        return date.toLocaleString('zh-TW', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        });
    } catch (_) {
        return date.toISOString();
    }
}

function monitorHealthLabel(status) {
    if (status === 'live') return t('js.monitor.health.live');
    if (status === 'waiting') return t('js.monitor.health.waiting');
    if (status === 'stalled') return t('js.monitor.health.stalled');
    if (status === 'error') return t('js.monitor.health.error');
    if (status === 'external') return t('js.monitor.health.external');
    return t('js.monitor.health.waiting');
}

function setMonitorCardHealth(card, status, detail = '') {
    if (!card) return;
    card.dataset.health = status || 'waiting';
    const badge = card.querySelector('.cctv-monitor-health');
    if (!badge) return;
    badge.dataset.status = status || 'waiting';
    badge.textContent = monitorHealthLabel(status);
    badge.title = detail || monitorHealthLabel(status);
}

function updateMonitorCardOverlays(card, vehicle = null) {
    if (!card) return;
    const clock = card.querySelector('.cctv-monitor-overlay-clock');
    if (clock) clock.textContent = monitorClockText();
    if (!vehicle) return;
    const title = vehicle.route || vehicle.plate || t('js.monitor.liveTitle');
    const source = cctvSourceLabel(vehicle);
    const coords = Number.isFinite(Number(vehicle.lat)) && Number.isFinite(Number(vehicle.lng))
        ? `${Number(vehicle.lat).toFixed(5)}, ${Number(vehicle.lng).toFixed(5)}`
        : '';
    const line = card.querySelector('.cctv-monitor-overlay-meta');
    if (line) {
        line.textContent = [title, source, coords].filter(Boolean).join(' · ');
    }
}

let monitorOverlayClockTimer = 0;

function ensureMonitorOverlayClock() {
    if (monitorOverlayClockTimer) return;
    monitorOverlayClockTimer = window.setInterval(() => {
        document.querySelectorAll('.cctv-monitor-window').forEach((card) => {
            updateMonitorCardOverlays(card);
        });
    }, 1000);
}

function findMonitorCardByIframeSource(sourceWindow) {
    const cards = document.querySelectorAll('.cctv-monitor-window');
    for (const card of cards) {
        const iframe = card.querySelector('iframe');
        if (iframe && iframe.contentWindow === sourceWindow) return card;
    }
    return null;
}

function initMonitorEmbedBridge() {
    window.addEventListener('message', (event) => {
        const data = event.data;
        if (!data || typeof data !== 'object') return;
        if (data.type === 'olm-cctv-health') {
            const card = findMonitorCardByIframeSource(event.source);
            if (card) setMonitorCardHealth(card, data.status || 'waiting', data.detail || '');
        }
    });
}

initMonitorEmbedBridge();
ensureMonitorOverlayClock();

function monitorCardHtml({ pinned = false } = {}) {
    return `
        <header>
            <strong></strong>
            <span class="cctv-monitor-card-actions">
                <button type="button" class="cctv-monitor-card-report" title="${t('js.monitor.report')}" aria-label="${t('js.monitor.reportAria')}">!</button>
                ${pinned ? '' : `<button type="button" class="cctv-monitor-card-pin" title="${t('js.monitor.pin')}" aria-label="${t('js.monitor.pin')}">${pinIconSvg}</button>`}
                ${pinned
                    ? `<button type="button" class="pinned-monitor-size" data-size-delta="-40" title="${t('js.monitor.shrink')}" aria-label="${t('js.monitor.shrink')}">−</button><button type="button" class="pinned-monitor-size" data-size-delta="40" title="${t('js.monitor.grow')}" aria-label="${t('js.monitor.grow')}">+</button><button type="button" class="pinned-monitor-fit" title="${t('js.monitor.fit')}" aria-label="${t('js.monitor.fit')}">${t('js.monitor.fitShort')}</button><button type="button" class="cctv-monitor-card-unpin" title="${t('js.monitor.unpin')}" aria-label="${t('js.monitor.unpin')}">${unpinIconSvg}</button>`
                    : `<button type="button" class="cctv-monitor-card-up" title="${t('js.monitor.moveUp')}" aria-label="${t('js.monitor.moveUp')}">↑</button><button type="button" class="cctv-monitor-card-down" title="${t('js.monitor.moveDown')}" aria-label="${t('js.monitor.moveDown')}">↓</button>`}
                <button type="button" class="cctv-monitor-card-close" title="${t('js.monitor.cancel')}" aria-label="${t('js.monitor.cancel')}">×</button>
            </span>
        </header>
        <div class="cctv-monitor-frame">
            <iframe loading="lazy" referrerpolicy="strict-origin-when-cross-origin" scrolling="no" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
            <div class="cctv-monitor-overlay" aria-hidden="true">
                <span class="cctv-monitor-health" data-status="waiting">${t('js.monitor.health.waiting')}</span>
                <div class="cctv-monitor-overlay-text">
                    <span class="cctv-monitor-overlay-meta"></span>
                    <span class="cctv-monitor-overlay-clock"></span>
                </div>
            </div>
            <div class="cctv-monitor-fallback" hidden>
                <strong></strong>
                <small></small>
                <a target="_blank" rel="noopener">${t('js.monitor.openSource')}</a>
            </div>
        </div>
        <footer>
            <span></span>
            <a target="_blank" rel="noopener">${t('js.monitor.openMedia')}</a>
        </footer>
        ${pinned ? `<span class="pinned-monitor-resize-handle" title="${t('js.monitor.resize')}" aria-hidden="true"></span>` : ''}
    `;
}

function updateMonitorCardContent(card, vehicle, index = 0, count = 1) {
    const mediaUrl = cctvMediaUrl(vehicle);
    const openUrl = cctvOpenUrl(vehicle);
    card.style.setProperty('--monitor-color', monitorColor(vehicle.id));
    const upButton = card.querySelector('.cctv-monitor-card-up');
    const downButton = card.querySelector('.cctv-monitor-card-down');
    if (upButton) upButton.disabled = index === 0;
    if (downButton) downButton.disabled = index === count - 1;
    const title = vehicle.route || vehicle.plate || t('js.monitor.liveTitle');
    const subtitle = vehicle.kind === 'cctv'
        ? `${vehicle.headsign || vehicle.plate || cctvPointLabel(vehicle)} · ${cctvMediaStatusLabel(vehicle)}`
        : (vehicle.headsign || vehicle.plate || t('js.monitor.liveFeed'));
    const titleEl = card.querySelector('strong');
    const subtitleEl = card.querySelector('footer span');
    const link = card.querySelector('footer a');
    const iframe = card.querySelector('iframe');
    const fallback = card.querySelector('.cctv-monitor-fallback');
    const canEmbed = canEmbedMonitorUrl(mediaUrl);
    if (titleEl) titleEl.textContent = title;
    if (subtitleEl) subtitleEl.textContent = subtitle;
    if (iframe) {
        iframe.title = title;
        iframe.removeAttribute('width');
        iframe.removeAttribute('height');
        iframe.style.width = '';
        iframe.style.height = '';
        iframe.hidden = !canEmbed;
        if (!canEmbed) {
            iframe.removeAttribute('src');
        } else if (iframe.getAttribute('src') !== mediaUrl) {
            iframe.src = mediaUrl;
            if (isOlmCctvEmbedUrl(mediaUrl)) setMonitorCardHealth(card, 'waiting');
            else setMonitorCardHealth(card, 'external');
        } else if (!isOlmCctvEmbedUrl(mediaUrl)) {
            setMonitorCardHealth(card, 'external');
        } else if (!card.dataset.health) {
            setMonitorCardHealth(card, 'waiting');
        }
    }
    if (fallback) {
        fallback.hidden = canEmbed;
        const overlay = card.querySelector('.cctv-monitor-overlay');
        if (overlay) overlay.hidden = !canEmbed;
        const fallbackTitle = fallback.querySelector('strong');
        const fallbackDetail = fallback.querySelector('small');
        const fallbackLink = fallback.querySelector('a');
        if (fallbackTitle) fallbackTitle.textContent = t('js.monitor.iosEmbedFail');
        if (fallbackDetail) fallbackDetail.textContent = isFlvMonitorUrl(mediaUrl)
            ? t('js.monitor.iosFlv')
            : t('js.monitor.iosAlt');
        if (fallbackLink) fallbackLink.href = openUrl;
    }
    if (link) link.href = openUrl;
    updateMonitorCardOverlays(card, vehicle);
}

function bindMonitorCardActions(card, vehicle, options = {}) {
    card.querySelector('.cctv-monitor-card-report')?.addEventListener('click', (event) => {
        event.stopPropagation();
        reportCctvIssue(vehicle);
    });
    card.querySelector('.cctv-monitor-card-pin')?.addEventListener('click', (event) => {
        event.stopPropagation();
        togglePinnedMonitor(card.dataset.cctvId, true);
    });
    card.querySelector('.cctv-monitor-card-unpin')?.addEventListener('click', (event) => {
        event.stopPropagation();
        togglePinnedMonitor(card.dataset.cctvId, false);
    });
    card.querySelector('.cctv-monitor-card-up')?.addEventListener('click', (event) => {
        event.stopPropagation();
        moveMonitor(card.dataset.cctvId, -1);
    });
    card.querySelector('.cctv-monitor-card-down')?.addEventListener('click', (event) => {
        event.stopPropagation();
        moveMonitor(card.dataset.cctvId, 1);
    });
    card.querySelectorAll('.pinned-monitor-size').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            setPinnedMonitorWidth(card.dataset.cctvId, pinnedMonitorWidth(card.dataset.cctvId) + Number(button.dataset.sizeDelta || 0));
        });
    });
    card.querySelector('.pinned-monitor-fit')?.addEventListener('click', (event) => {
        event.stopPropagation();
        resetPinnedMonitorWidth(card.dataset.cctvId);
    });
    card.querySelector('.cctv-monitor-card-close')?.addEventListener('click', (event) => {
        event.stopPropagation();
        pinnedMonitorIds.delete(card.dataset.cctvId);
        savePinnedMonitors();
        const latest = vehicles.find((item) => item.id === card.dataset.cctvId)
            || monitoredVehicles.get(card.dataset.cctvId)
            || vehicle;
        toggleMonitor(latest);
        renderList(visibleVehiclesCache());
    });
    card.addEventListener('click', (event) => {
        if (event.target.closest('button, a')) return;
        focusMonitorVehicle(card.dataset.cctvId, vehicle);
    });
    if (options.resizable) {
        initPinnedMonitorResize(card);
    }
}

function initPinnedMonitorResize(card) {
    const handle = card.querySelector('.pinned-monitor-resize-handle');
    if (!handle) return;
    let startX = 0;
    let startWidth = 0;
    let nextWidth = 0;
    let frame = 0;
    let wasMapDragging = true;
    let wasScrollZoom = true;
    const apply = () => {
        frame = 0;
        setPinnedMonitorWidth(card.dataset.cctvId, nextWidth, { persist: false });
    };
    const onMove = (event) => {
        event.preventDefault();
        event.stopPropagation();
        nextWidth = startWidth + event.clientX - startX;
        if (!frame) frame = window.requestAnimationFrame(apply);
    };
    const onUp = (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (frame) {
            window.cancelAnimationFrame(frame);
            frame = 0;
        }
        setPinnedMonitorWidth(card.dataset.cctvId, nextWidth || startWidth);
        handle.releasePointerCapture?.(event.pointerId);
        handle.classList.remove('is-resizing');
        document.body.classList.remove('is-resizing-pinned-monitor');
        if (wasMapDragging) map.dragging.enable();
        if (wasScrollZoom) map.scrollWheelZoom.enable();
        window.removeEventListener('pointermove', onMove, true);
        window.removeEventListener('pointerup', onUp, true);
        window.removeEventListener('pointercancel', onUp, true);
    };
    handle.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        event.stopPropagation();
        startX = event.clientX;
        startWidth = card.getBoundingClientRect().width;
        nextWidth = startWidth;
        handle.setPointerCapture?.(event.pointerId);
        handle.classList.add('is-resizing');
        document.body.classList.add('is-resizing-pinned-monitor');
        wasMapDragging = map.dragging.enabled();
        wasScrollZoom = map.scrollWheelZoom.enabled();
        map.dragging.disable();
        map.scrollWheelZoom.disable();
        window.addEventListener('pointermove', onMove, { passive: false, capture: true });
        window.addEventListener('pointerup', onUp, { passive: false, capture: true });
        window.addEventListener('pointercancel', onUp, { passive: false, capture: true });
    });
}

function renderPinnedMonitorWindows(cctvs) {
    const layer = ensurePinnedMonitorLayer();
    const pinnedCctvs = cctvs.filter((vehicle) => pinnedMonitorIds.has(vehicle.id));
    const activeIds = new Set(pinnedCctvs.map((vehicle) => vehicle.id));
    pinnedMonitorMarkers.forEach((marker, id) => {
        if (!activeIds.has(id)) {
            layer.removeLayer(marker);
            pinnedMonitorMarkers.delete(id);
        }
    });
    pinnedCctvs.forEach((vehicle) => {
        const lat = Number(vehicle.lat);
        const lng = Number(vehicle.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        let marker = pinnedMonitorMarkers.get(vehicle.id);
        if (!marker) {
            const card = document.createElement('section');
            card.className = 'cctv-monitor-window pinned-monitor-window';
            card.dataset.cctvId = vehicle.id;
            card.style.setProperty('--pinned-monitor-width', `${pinnedMonitorWidth(vehicle.id)}px`);
            card.innerHTML = monitorCardHtml({ pinned: true });
            bindMonitorCardActions(card, vehicle, { resizable: true });
            marker = L.marker([lat, lng], {
                interactive: true,
                zIndexOffset: 620,
                icon: L.divIcon({
                    className: 'pinned-monitor-icon',
                    html: card,
                    iconSize: null,
                    iconAnchor: [18, 18],
                }),
            }).addTo(layer);
            pinnedMonitorMarkers.set(vehicle.id, marker);
            window.setTimeout(() => {
                const element = marker.getElement();
                if (element) {
                    L.DomEvent.disableClickPropagation(element);
                    L.DomEvent.disableScrollPropagation(element);
                }
            }, 0);
        } else {
            marker.setLatLng([lat, lng]);
        }
        const element = marker.getElement();
        const card = element?.querySelector('.pinned-monitor-window');
        if (card) {
            card.style.setProperty('--pinned-monitor-width', `${pinnedMonitorWidth(vehicle.id)}px`);
            updateMonitorCardContent(card, vehicle);
        }
    });
    syncPinnedMinimap();
}

function renderCctvMonitorWindows() {
    if (!cctvMonitorDock) return;
    const cctvs = monitoredCctvVehicles();
    prunePinnedMonitors(new Set(cctvs.map((vehicle) => String(vehicle.id))));
    renderPinnedMonitorWindows(cctvs);
    const dockCctvs = cctvs.filter((vehicle) => !pinnedMonitorIds.has(vehicle.id));
    cctvMonitorDock.hidden = cctvs.length === 0 || dockCctvs.length === 0;
    updateMonitorToolbarButtons();
    if (cctvs.length === 0) {
        cctvMonitorDock.innerHTML = '';
        cctvMonitorDock.classList.remove('can-resize');
        pinnedMonitorIds.clear();
        savePinnedMonitors();
        renderPinnedMonitorWindows([]);
        updateMonitorToolbarButtons();
        return;
    }
    if (dockCctvs.length === 0) return;
    applyMonitorDockState();
    ensureCctvMonitorDockControls(cctvs.length);
    const monitorStrip = ensureCctvMonitorStrip();
    if (!monitorStrip) return;
    ensureCctvMonitorResizeHandle();
    const activeIds = new Set(dockCctvs.map((vehicle) => vehicle.id));
    monitorStrip.querySelectorAll('.cctv-monitor-window').forEach((card) => {
        if (!activeIds.has(card.dataset.cctvId)) {
            card.remove();
        }
    });
    let previousMonitorNode = null;
    dockCctvs.forEach((vehicle, index) => {
        let card = monitorStrip.querySelector(`[data-cctv-id="${CSS.escape(vehicle.id)}"]`);
        if (!card) {
            card = document.createElement('section');
            card.className = 'cctv-monitor-window';
            card.dataset.cctvId = vehicle.id;
            card.innerHTML = monitorCardHtml();
            bindMonitorCardActions(card, vehicle);
        }
        const iframe = card.querySelector('iframe');
        if (iframe && !iframe.parentElement?.classList.contains('cctv-monitor-frame')) {
            const frame = document.createElement('div');
            frame.className = 'cctv-monitor-frame';
            iframe.parentElement?.insertBefore(frame, iframe);
            frame.appendChild(iframe);
        }
        if (iframe) {
            iframe.removeAttribute('width');
            iframe.removeAttribute('height');
            iframe.style.width = '';
            iframe.style.height = '';
        }
        const expectedNext = previousMonitorNode ? previousMonitorNode.nextElementSibling : monitorStrip.firstElementChild;
        if (expectedNext !== card) {
            monitorStrip.insertBefore(card, expectedNext || null);
        }
        previousMonitorNode = card;
        updateMonitorCardContent(card, vehicle, index, dockCctvs.length);
    });
    scheduleMonitorDockOverflowCheck();

}

function toggleMonitor(vehicle, forceOn = false) {
    if (!vehicle?.id) return false;
    if (monitoredVehicles.has(vehicle.id) && !forceOn) {
        monitoredVehicles.delete(vehicle.id);
        pinnedMonitorIds.delete(vehicle.id);
        pinnedMonitorSizes.delete(vehicle.id);
        savePinnedMonitors();
        savePinnedMonitorSizes();
        if (selectedVehicleId === vehicle.id) selectedVehicleId = null;
    } else {
        const startsNewMonitorSet = monitoredVehicles.size === 0;
        const current = monitoredVehicles.get(vehicle.id);
        monitoredVehicles.set(vehicle.id, current || monitorSnapshot(vehicle));
        if (startsNewMonitorSet && !current) {
            saveMonitorDockState({ columns: 1 });
        }
        selectedVehicleId = vehicle.id;
    }
    saveMonitoredVehicles();
    vehicleCanvasLayer?.redraw();
    renderCctvMonitorWindows();
    return monitoredVehicles.has(vehicle.id);
}

function syncMonitoredSnapshots() {
    let changed = false;
    vehicles.forEach((vehicle) => {
        if (!monitoredVehicles.has(vehicle.id)) return;
        const current = monitoredVehicles.get(vehicle.id);
        const next = {
            ...current,
            route: vehicle.route,
            plate: vehicle.plate,
            headsign: vehicle.headsign,
            kind: vehicle.kind,
            operator: vehicle.operator,
            lat: Number(vehicle.lat),
            lng: Number(vehicle.lng),
            status: vehicle.status,
            note: vehicle.note,
            stream_url: vehicle.stream_url,
            image_url: vehicle.image_url,
            source_key: vehicle.source_key,
            source_label: vehicle.source_label,
        };
        monitoredVehicles.set(vehicle.id, {
            ...next,
        });
        changed = changed || JSON.stringify(current) !== JSON.stringify(next);
    });
    if (changed) saveMonitoredVehicles();
    renderCctvMonitorWindows();
}

function measureTotalDistance() {
    return measurePoints.reduce((total, point, index) => {
        if (index === 0) return 0;
        return total + map.distance(measurePoints[index - 1], point);
    }, 0);
}

function measurePointLabel(index) {
    if (index === 0) return t('js.measure.origin');
    return formatPreciseDistance(map.distance(measurePoints[index - 1], measurePoints[index]));
}

function updateMeasureGeometry() {
    measureLine?.setLatLngs(measurePoints);
    measureMarkers.forEach((marker, index) => {
        marker.setTooltipContent(measurePointLabel(index));
    });
    updateMeasureReadout();
}

function updateMeasureReadout() {
    if (!measureReadout) return;
    if (!measuringDistance && measurePoints.length === 0) {
        measureReadout.hidden = true;
        updateMeasureButtonState();
        return;
    }

    measureReadout.hidden = false;
    if (measurePoints.length === 0) {
        measureReadout.textContent = t('js.measure.start');
        updateMeasureButtonState();
        return;
    }

    const total = measureTotalDistance();
    measureReadout.textContent = measurePoints.length === 1
        ? t('js.measure.next')
        : t('js.measure.readout', { distance: formatPreciseDistance(total), segments: measurePoints.length - 1 });
    updateMeasureButtonState();
}

function updateMeasureButtonState() {
    if (!measureBtn) return;
    const hasMeasure = measurePoints.length > 0;
    const clearing = measuringDistance || hasMeasure;
    measureBtn.classList.toggle('active', clearing);
    measureBtn.classList.toggle('is-clear-mode', clearing);
    measureBtn.setAttribute('aria-pressed', String(measuringDistance));
    measureBtn.title = clearing ? t('js.map.clearMeasure') : t('js.map.measure');
    measureBtn.setAttribute('aria-label', clearing ? t('js.map.clearMeasure') : t('js.map.measure'));
    const nextIcon = clearing ? clearMeasureIconSvg : measureIconSvg;
    if (measureBtn.innerHTML.trim() !== nextIcon) {
        measureBtn.innerHTML = nextIcon;
    }
}

function redrawMeasureLayer() {
    if (!measureLayer) {
        measureLayer = L.layerGroup().addTo(map);
    }
    measureLayer.clearLayers();
    measureLine = null;
    measureMarkers = [];

    if (measurePoints.length > 1) {
        measureLine = L.polyline(measurePoints, {
            color: '#f2c94c',
            weight: 4,
            opacity: .95,
            dashArray: '8 8',
        }).addTo(measureLayer);
    }

    measurePoints.forEach((point, index) => {
        const marker = L.marker(point, {
            draggable: true,
            autoPan: true,
            keyboard: false,
            riseOnHover: true,
            icon: L.divIcon({
                className: 'measure-point-icon',
                html: '<span aria-hidden="true"></span>',
                iconSize: [20, 20],
                iconAnchor: [10, 10],
            }),
        }).addTo(measureLayer);
        marker.bindTooltip(measurePointLabel(index), {
            permanent: true,
            direction: 'top',
            offset: [0, -10],
            className: 'measure-tooltip',
        });
        marker.on('click', (event) => L.DomEvent.stop(event));
        marker.on('drag', (event) => {
            measurePoints[index] = event.target.getLatLng();
            updateMeasureGeometry();
        });
        marker.on('dragend', (event) => {
            measurePoints[index] = event.target.getLatLng();
            updateMeasureGeometry();
        });
        measureMarkers.push(marker);
    });

    updateMeasureReadout();
}

function addMeasurePoint(latLng) {
    measurePoints.push(latLng);
    redrawMeasureLayer();
}

function clearMeasure() {
    measurePoints = [];
    measureLayer?.clearLayers();
    measureLine = null;
    measureMarkers = [];
    updateMeasureReadout();
}

function setMeasureMode(enabled) {
    measuringDistance = enabled;
    measureBtn?.classList.toggle('active', measuringDistance);
    measureBtn?.setAttribute('aria-pressed', String(measuringDistance));
    map.getContainer().classList.toggle('measuring-distance', measuringDistance);
    if (measuringDistance) {
        pickingLocation = false;
        pickLocationBtn.classList.remove('active');
        pickLocationBtn.setAttribute('aria-pressed', 'false');
        pickMapBtn?.classList.remove('active');
        pickMapBtn?.setAttribute('aria-pressed', 'false');
        map.getContainer().classList.remove('picking-location');
        if (streetViewPicking) {
            setStreetViewMode(false, { silent: true });
        }
        locationStatus.textContent = t('js.measure.mode');
    }
    updateMeasureReadout();
}

function showStreetViewStatus(message) {
    if (!streetViewPanel || !streetViewStatus) return;
    streetViewPanel.hidden = false;
    streetViewStatus.hidden = false;
    streetViewStatus.textContent = message;
    if (streetViewViewerEl) {
        streetViewViewerEl.hidden = true;
    }
}

function streetViewPosIcon(bearing = 0) {
    const rot = Number.isFinite(bearing) ? bearing : 0;
    return L.divIcon({
        className: 'streetview-pos-icon',
        html: `<span style="transform:rotate(${rot}deg)" aria-hidden="true"></span>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });
}

function updateStreetViewMapPosition(lat, lng, bearing = 0, { pan = false } = {}) {
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    const latLng = L.latLng(lat, lng);
    if (!streetViewMarker) {
        streetViewMarker = L.marker(latLng, {
            interactive: false,
            keyboard: false,
            zIndexOffset: 1200,
            icon: streetViewPosIcon(bearing),
        }).addTo(map);
    } else {
        streetViewMarker.setLatLng(latLng);
        streetViewMarker.setIcon(streetViewPosIcon(bearing));
    }
    if (pan && streetViewFollowMap) {
        const zoom = Math.max(map.getZoom(), 17);
        map.panTo(latLng, { animate: true });
        if (map.getZoom() < 16) {
            map.setView(latLng, zoom, { animate: true });
        }
    }
}

function clearStreetViewMapPosition() {
    if (streetViewMarker) {
        map.removeLayer(streetViewMarker);
        streetViewMarker = null;
    }
}

function destroyStreetViewViewer() {
    if (streetViewViewer) {
        try {
            streetViewViewer.remove();
        } catch (_) {}
        streetViewViewer = null;
    }
    if (streetViewViewerEl) {
        streetViewViewerEl.innerHTML = '';
        streetViewViewerEl.hidden = true;
    }
}

function setStreetViewHud(message = '', { playing = false } = {}) {
    if (streetViewPanel) {
        streetViewPanel.classList.toggle('is-playing', !!playing);
    }
    if (!streetViewHud) return;
    const text = String(message || '').trim();
    streetViewHud.hidden = text === '';
    streetViewHud.textContent = text;
}

function closeStreetViewPanel() {
    streetViewLookupToken += 1;
    if (streetViewPanel) streetViewPanel.hidden = true;
    if (streetViewStatus) {
        streetViewStatus.hidden = true;
        streetViewStatus.textContent = '';
    }
    setStreetViewHud('');
    setStreetViewMaximized(false);
    destroyStreetViewViewer();
    clearStreetViewMapPosition();
}

function loadStreetViewMapillary() {
    if (window.mapillary?.Viewer) {
        return Promise.resolve(window.mapillary);
    }
    if (streetViewMapillaryPromise) {
        return streetViewMapillaryPromise;
    }
    streetViewMapillaryPromise = new Promise((resolve, reject) => {
        const cssId = 'mapillary-js-css';
        if (!document.getElementById(cssId)) {
            const link = document.createElement('link');
            link.id = cssId;
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.css';
            document.head.appendChild(link);
        }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/mapillary-js@4.1.2/dist/mapillary.js';
        script.async = true;
        script.onload = () => {
            if (window.mapillary?.Viewer) resolve(window.mapillary);
            else {
                streetViewMapillaryPromise = null;
                reject(new Error('MapillaryJS missing'));
            }
        };
        script.onerror = () => {
            streetViewMapillaryPromise = null;
            reject(new Error('MapillaryJS failed'));
        };
        document.head.appendChild(script);
    });
    return streetViewMapillaryPromise;
}

function bindStreetViewViewerEvents(viewer) {
    const syncFromImage = async () => {
        try {
            const image = await viewer.getImage();
            if (!image) return;
            const lngLat = image.lngLat || image.computedLngLat;
            if (!lngLat) return;
            let bearing = 0;
            try {
                bearing = await viewer.getBearing();
            } catch (_) {
                bearing = Number(image.compassAngle || image.computedCompassAngle || 0) || 0;
            }
            updateStreetViewMapPosition(lngLat.lat, lngLat.lng, bearing, { pan: true });
            if (streetViewPanel?.classList.contains('is-playing')) {
                const captured = image.capturedAt || image.captured_at || null;
                let when = '';
                if (captured) {
                    const date = new Date(captured);
                    if (!Number.isNaN(date.getTime())) {
                        when = date.toLocaleDateString(i18nJsLocale);
                    }
                }
                setStreetViewHud(
                    when
                        ? `${t('js.streetView.playingShort')} · ${when}`
                        : t('js.streetView.playing'),
                    { playing: true },
                );
            }
        } catch (_) {}
    };
    viewer.on('image', () => {
        syncFromImage();
    });
    viewer.on('bearing', async () => {
        try {
            const image = await viewer.getImage();
            const lngLat = image?.lngLat || image?.computedLngLat;
            if (!lngLat) return;
            const bearing = await viewer.getBearing();
            updateStreetViewMapPosition(lngLat.lat, lngLat.lng, bearing, { pan: false });
        } catch (_) {}
    });

    const onPlayingChange = (playing) => {
        if (playing) {
            setStreetViewHud(t('js.streetView.playing'), { playing: true });
        } else {
            setStreetViewHud('');
        }
    };

    const readPlayingFlag = (event) => {
        if (typeof event === 'boolean') return event;
        if (event && typeof event.playing === 'boolean') return event.playing;
        return null;
    };

    // MapillaryJS sequence play/stop
    try {
        const sequence = viewer.getComponent?.('sequence');
        if (sequence?.on) {
            sequence.on('playing', (event) => {
                const playing = readPlayingFlag(event);
                if (playing !== null) onPlayingChange(playing);
            });
        }
    } catch (_) {}

    // Fallback: some builds expose viewer-level playing
    try {
        viewer.on('playing', (event) => {
            const playing = readPlayingFlag(event);
            if (playing !== null) onPlayingChange(playing);
        });
    } catch (_) {}
}

async function showStreetViewImage(imageId, lat = null, lng = null, bearing = 0) {
    if (!streetViewPanel || !streetViewViewerEl || !imageId) return;
    streetViewPanel.hidden = false;
    if (streetViewStatus) {
        streetViewStatus.hidden = true;
        streetViewStatus.textContent = '';
    }
    if (Number.isFinite(lat) && Number.isFinite(lng)) {
        updateStreetViewMapPosition(lat, lng, bearing, { pan: true });
    }
    try {
        const mapillaryApi = await loadStreetViewMapillary();
        streetViewViewerEl.hidden = false;
        if (!streetViewViewer) {
            streetViewViewer = new mapillaryApi.Viewer({
                accessToken: mapillaryBoot.access_token,
                container: streetViewViewerEl,
                imageId: String(imageId),
                component: {
                    cover: false,
                    bearing: true,
                    sequence: true,
                    direction: true,
                    zoom: true,
                },
            });
            bindStreetViewViewerEvents(streetViewViewer);
            // Extra play/stop detection for Mapillary control bar.
            if (streetViewViewerEl && !streetViewViewerEl._olmPlayHooked) {
                streetViewViewerEl._olmPlayHooked = true;
                streetViewViewerEl.addEventListener('click', (event) => {
                    const btn = event.target?.closest?.('button, [role="button"], .mapillary-button');
                    if (!btn) return;
                    const label = `${btn.getAttribute('aria-label') || ''} ${btn.getAttribute('title') || ''} ${btn.className || ''}`.toLowerCase();
                    if (!/play|pause|stop|播放|暫停|停止/.test(label)) return;
                    window.setTimeout(() => {
                        try {
                            const sequence = streetViewViewer?.getComponent?.('sequence');
                            const playing = sequence?.isPlaying?.() ?? sequence?.playing;
                            if (typeof playing === 'boolean') {
                                if (playing) setStreetViewHud(t('js.streetView.playing'), { playing: true });
                                else setStreetViewHud('');
                                return;
                            }
                        } catch (_) {}
                        // Toggle fallback when Mapillary does not expose playing state.
                        if (streetViewPanel?.classList.contains('is-playing')) setStreetViewHud('');
                        else setStreetViewHud(t('js.streetView.playing'), { playing: true });
                    }, 80);
                });
            }
        } else {
            await streetViewViewer.moveTo(String(imageId));
        }
        // Ensure layout after panel becomes visible
        resizeStreetViewViewer();
    } catch (_) {
        // Fallback embed: map marker stays at the opened image only (no live sync).
        destroyStreetViewViewer();
        if (!streetViewViewerEl) {
            showStreetViewStatus(t('js.streetView.fail'));
            return;
        }
        if (streetViewStatus) {
            streetViewStatus.hidden = true;
            streetViewStatus.textContent = '';
        }
        const embedUrl = `https://www.mapillary.com/embed?image_key=${encodeURIComponent(String(imageId))}&style=photo`;
        streetViewViewerEl.hidden = false;
        streetViewViewerEl.innerHTML = `<iframe title="${t('js.streetView.title')}" src="${embedUrl}" style="width:100%;height:100%;border:0;background:#111" allowfullscreen loading="lazy"></iframe>`;
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            updateStreetViewMapPosition(lat, lng, bearing, { pan: true });
        }
    }
}

function loadStreetViewVectorGrid() {
    if (window.L?.vectorGrid) {
        return Promise.resolve();
    }
    if (streetViewVectorGridPromise) {
        return streetViewVectorGridPromise;
    }
    streetViewVectorGridPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet.vectorgrid@1.3.0/dist/Leaflet.VectorGrid.bundled.min.js';
        script.async = true;
        script.onload = () => {
            if (window.L?.vectorGrid) resolve();
            else reject(new Error('Leaflet.VectorGrid missing'));
        };
        script.onerror = () => {
            streetViewVectorGridPromise = null;
            reject(new Error('Leaflet.VectorGrid failed'));
        };
        document.head.appendChild(script);
    });
    return streetViewVectorGridPromise;
}

function unloadStreetViewCoverage() {
    setStreetViewHoverCoverage(false);
    if (streetViewHoverRaf) {
        window.cancelAnimationFrame(streetViewHoverRaf);
        streetViewHoverRaf = 0;
    }
    if (streetViewCoverageLayer) {
        map.removeLayer(streetViewCoverageLayer);
        streetViewCoverageLayer = null;
    }
    const coveragePane = map.getPane?.('streetviewCoverage');
    if (coveragePane?._olmPointerObserver) {
        coveragePane._olmPointerObserver.disconnect();
        coveragePane._olmPointerObserver = null;
        coveragePane._olmPointerGuard = false;
    }
}

function setStreetViewHoverCoverage(active) {
    streetViewHoverCoverage = !!active;
    document.querySelector('.map-stage')?.classList.toggle('streetview-hover-coverage', streetViewHoverCoverage);
    map.getContainer().classList.toggle('streetview-hover-coverage', streetViewHoverCoverage);
}

function isStreetViewCoverageAt(containerPoint, tolerance = 3) {
    if (!streetViewPicking || !streetViewCoverageLayer) return false;
    const pane = map.getPane('streetviewCoverage');
    if (!pane) return false;
    const mapRect = map.getContainer().getBoundingClientRect();
    const clientX = mapRect.left + containerPoint.x;
    const clientY = mapRect.top + containerPoint.y;
    const canvases = pane.querySelectorAll('canvas');
    for (const canvas of canvases) {
        const rect = canvas.getBoundingClientRect();
        if (
            clientX < rect.left - tolerance
            || clientX >= rect.right + tolerance
            || clientY < rect.top - tolerance
            || clientY >= rect.bottom + tolerance
        ) {
            continue;
        }
        let ctx;
        try {
            ctx = canvas.getContext('2d', { willReadFrequently: true });
        } catch (_) {
            ctx = canvas.getContext('2d');
        }
        if (!ctx) continue;
        const scaleX = canvas.width / Math.max(rect.width, 1);
        const scaleY = canvas.height / Math.max(rect.height, 1);
        const localX = (clientX - rect.left) * scaleX;
        const localY = (clientY - rect.top) * scaleY;
        const radius = Math.max(1, Math.ceil(tolerance * Math.max(scaleX, scaleY)));
        const x0 = Math.max(0, Math.floor(localX - radius));
        const y0 = Math.max(0, Math.floor(localY - radius));
        const w = Math.min(canvas.width - x0, radius * 2 + 1);
        const h = Math.min(canvas.height - y0, radius * 2 + 1);
        if (w <= 0 || h <= 0) continue;
        let data;
        try {
            data = ctx.getImageData(x0, y0, w, h).data;
        } catch (_) {
            continue;
        }
        for (let i = 3; i < data.length; i += 4) {
            if (data[i] > 24) return true;
        }
    }
    return false;
}

function scheduleStreetViewHoverCheck(containerPoint) {
    if (!streetViewPicking) {
        setStreetViewHoverCoverage(false);
        return;
    }
    const point = containerPoint;
    if (streetViewHoverRaf) return;
    streetViewHoverRaf = window.requestAnimationFrame(() => {
        streetViewHoverRaf = 0;
        if (!streetViewPicking) {
            setStreetViewHoverCoverage(false);
            return;
        }
        setStreetViewHoverCoverage(isStreetViewCoverageAt(point, 4));
    });
}

async function ensureStreetViewCoverage() {
    if (!mapillaryEnabled || !streetViewPicking) return;
    try {
        await loadStreetViewVectorGrid();
        if (!streetViewPicking) return;
        if (streetViewCoverageLayer) return;
        if (!map.getPane('streetviewCoverage')) {
            map.createPane('streetviewCoverage');
            const pane = map.getPane('streetviewCoverage');
            pane.style.zIndex = 350;
            pane.style.pointerEvents = 'none';
        } else {
            map.getPane('streetviewCoverage').style.pointerEvents = 'none';
        }
        const lineStyle = {
            color: '#05CB63',
            weight: 2.5,
            opacity: 0.72,
        };
        const thinLineStyle = {
            color: '#05CB63',
            weight: 1.5,
            opacity: 0.55,
        };
        const pointStyle = {
            radius: 3,
            fillColor: '#05CB63',
            fillOpacity: 0.7,
            color: '#034b28',
            weight: 1,
            opacity: 0.7,
        };
        streetViewCoverageLayer = L.vectorGrid.protobuf('api/mapillary_tile.php?z={z}&x={x}&y={y}', {
            maxZoom: 19,
            maxNativeZoom: 14,
            minZoom: 6,
            interactive: false,
            pane: 'streetviewCoverage',
            rendererFactory: L.canvas.tile,
            vectorTileLayerStyles: {
                overview: thinLineStyle,
                sequence: lineStyle,
                image: pointStyle,
            },
        });
        streetViewCoverageLayer.addTo(map);
        // VectorGrid canvas tiles can still eat clicks; force through.
        const coveragePane = map.getPane('streetviewCoverage');
        if (coveragePane) {
            coveragePane.style.pointerEvents = 'none';
            coveragePane.querySelectorAll('canvas, svg').forEach((el) => {
                el.style.pointerEvents = 'none';
            });
            if (!coveragePane._olmPointerGuard) {
                coveragePane._olmPointerGuard = true;
                const observer = new MutationObserver(() => {
                    coveragePane.style.pointerEvents = 'none';
                    coveragePane.querySelectorAll('canvas, svg').forEach((el) => {
                        el.style.pointerEvents = 'none';
                    });
                });
                observer.observe(coveragePane, { childList: true, subtree: true });
                coveragePane._olmPointerObserver = observer;
            }
        }
    } catch (_) {
        if (streetViewPicking && locationStatus) {
            locationStatus.textContent = t('js.streetView.coverageFail');
        }
    }
}

function setStreetViewMode(enabled, options = {}) {
    if (!mapillaryEnabled) return;
    streetViewPicking = !!enabled;
    streetViewBtn?.classList.toggle('active', streetViewPicking);
    streetViewBtn?.setAttribute('aria-pressed', String(streetViewPicking));
    document.querySelector('.map-stage')?.classList.toggle('streetview-picking', streetViewPicking);
    map.getContainer().classList.toggle('streetview-picking', streetViewPicking);
    if (!streetViewPicking) {
        setStreetViewHoverCoverage(false);
    }
    if (streetViewPicking) {
        if (measuringDistance) {
            setMeasureMode(false);
        }
        if (pickingLocation) {
            finishPickLocationMode();
        }
        ensureStreetViewCoverage();
        if (!options.silent) {
            locationStatus.textContent = t('js.streetView.on');
        }
    } else {
        unloadStreetViewCoverage();
        if (!options.keepPanel) {
            closeStreetViewPanel();
        } else {
            destroyStreetViewViewer();
            clearStreetViewMapPosition();
        }
        if (!options.silent) {
            locationStatus.textContent = t('js.streetView.off');
        }
    }
}

async function lookupStreetViewAt(latLng) {
    if (!mapillaryEnabled || !latLng) return;
    const token = ++streetViewLookupToken;
    showStreetViewStatus(t('js.streetView.loading'));
    if (streetViewPanelTitle) {
        streetViewPanelTitle.textContent = t('js.streetView.title');
    }
    try {
        const params = new URLSearchParams({
            lat: String(latLng.lat),
            lng: String(latLng.lng),
        });
        const response = await fetch(`api/mapillary.php?${params.toString()}`, {
            credentials: 'same-origin',
            cache: 'no-store',
        });
        const data = await response.json().catch(() => null);
        if (token !== streetViewLookupToken) return;
        if (!response.ok || !data || data.ok === false) {
            showStreetViewStatus(t('js.streetView.fail'));
            return;
        }
        if (!data.id) {
            showStreetViewStatus(t('js.streetView.none'));
            return;
        }
        const imageLat = Number(data.lat);
        const imageLng = Number(data.lng);
        const bearing = Number(data.compass_angle);
        await showStreetViewImage(
            String(data.id),
            Number.isFinite(imageLat) ? imageLat : latLng.lat,
            Number.isFinite(imageLng) ? imageLng : latLng.lng,
            Number.isFinite(bearing) ? bearing : 0,
        );
    } catch (_) {
        if (token !== streetViewLookupToken) return;
        showStreetViewStatus(t('js.streetView.fail'));
    }
}

function sourceLabel(source) {
    return {
        bus: t('js.source.bus'),
        'rail-tra': t('js.source.railTra'),
        'rail-metro': t('js.source.railMetro'),
        'rail-thsr': t('js.source.railThsr'),
        bike: t('js.source.bike'),
        cctv: t('js.source.cctv'),
        'cctv-cache': t('js.source.cctv'),
        garbage: t('js.source.garbage'),
    }[source] || source;
}

function updateMapLoadStatus(state, title, detail = '') {
    if (!mapLoadStatus) return;
    mapLoadStatus.classList.remove('loading', 'warning', 'idle');
    mapLoadStatus.classList.add(state || 'idle');
    const titleEl = mapLoadStatus.querySelector('strong');
    const detailEl = mapLoadStatus.querySelector('small');
    if (titleEl) titleEl.textContent = title;
    if (detailEl) detailEl.textContent = detail;
}

function boundsIntersect(leafletBounds, areaBounds) {
    const [south, west, north, east] = areaBounds;
    return leafletBounds.getSouth() <= north
        && leafletBounds.getNorth() >= south
        && leafletBounds.getWest() <= east
        && leafletBounds.getEast() >= west;
}

function mapCities() {
    const bounds = map.getBounds();
    const cities = Object.entries(cityAreas)
        .filter(([, area]) => boundsIntersect(bounds, area.bounds))
        .map(([city]) => city);
    return cities.length > 0 ? cities.slice(0, 4) : [citySelect?.value || 'Taipei'];
}

function mapBoundsParam() {
    const bounds = map.getBounds();
    return [
        bounds.getSouth(),
        bounds.getWest(),
        bounds.getNorth(),
        bounds.getEast(),
    ].map((value) => value.toFixed(5)).join(',');
}

function loadKey() {
    return `${map.getZoom()}|${mapCities().join('|')}|${mapBoundsParam()}|${selectedSources().join('|')}`;
}

function scheduleLoadVehicles(delay = 650) {
    window.clearTimeout(loadTimer);
    const sources = selectedSources().map(sourceLabel).join(t('js.listSep')) || t('js.source.none');
    updateMapLoadStatus('loading', t('js.load.waitBounds'), t('js.load.afterMove', { sources }));
    loadTimer = window.setTimeout(() => {
        const key = loadKey();
        if (key === lastLoadKey) {
            render();
            updateMapLoadStatus('idle', t('js.load.fresh'), t('js.load.countSources', { count: vehicles.length, sources }));
            return;
        }
        loadVehicles({ reason: 'map' }).catch((error) => {
            locationStatus.textContent = error.message;
            updateMapLoadStatus('warning', t('js.load.fail'), error.message || t('js.load.retry'));
        });
    }, delay);
}

function parseTime(value) {
    const time = Date.parse(value);
    return Number.isFinite(time) ? time : null;
}

function minutesAgo(value) {
    const time = parseTime(value);
    if (!time) return null;
    return Math.max(0, Math.round((Date.now() - time) / 60000));
}

function formatAge(value) {
    const minutes = minutesAgo(value);
    if (minutes === null) return t('js.age.unknownUpdate');
    if (minutes < 1) return t('js.age.justUpdated');
    if (minutes < 60) return t('js.age.minUpdated', { n: minutes });
    return t('js.age.hourUpdated', { n: Math.floor(minutes / 60) });
}

function isFresh(vehicle) {
    const minutes = minutesAgo(vehicle.updated_at);
    return minutes !== null && minutes <= 10;
}

function usesFreshnessFilter(vehicle) {
    return ['bus', 'garbage', 'rail'].includes(vehicle?.kind);
}

function monitorStateLabel(vehicle, monitored, cancelled = false) {
    if (vehicle?.kind === 'cctv') {
        if (cancelled) return t('js.watch.cancelled');
        return monitored ? t('js.watch.on') : t('js.watch.off');
    }
    if (cancelled) return t('js.monitor.state.cancelled');
    return monitored ? t('js.monitor.state.on') : t('js.monitor.state.off');
}

function distanceToUser(vehicle) {
    if (!userPosition) return null;
    return map.distance(userPosition, currentVehicleLatLng(vehicle));
}

function easePosition(t) {
    return t < .5 ? 4 * t * t * t : 1 - ((-2 * t + 2) ** 3) / 2;
}

function hasContinuousEstimate(vehicle) {
    return vehicle?.estimated
        && Number.isFinite(vehicle.estimate_from_lat)
        && Number.isFinite(vehicle.estimate_from_lng)
        && Number.isFinite(vehicle.estimate_to_lat)
        && Number.isFinite(vehicle.estimate_to_lng)
        && Number.isFinite(vehicle.estimate_start_ms)
        && Number.isFinite(vehicle.estimate_end_ms)
        && vehicle.estimate_end_ms > vehicle.estimate_start_ms;
}

function estimatedVehicleLatLng(vehicle) {
    const ratio = Math.max(0, Math.min(1, (Date.now() - vehicle.estimate_start_ms) / (vehicle.estimate_end_ms - vehicle.estimate_start_ms)));
    if (Array.isArray(vehicle.estimate_path) && vehicle.estimate_path.length >= 2) {
        return interpolatePath(vehicle.estimate_path, ratio);
    }
    return [
        vehicle.estimate_from_lat + ((vehicle.estimate_to_lat - vehicle.estimate_from_lat) * ratio),
        vehicle.estimate_from_lng + ((vehicle.estimate_to_lng - vehicle.estimate_from_lng) * ratio),
    ];
}

function interpolatePath(path, ratio) {
    const segments = [];
    let total = 0;
    for (let index = 0; index < path.length - 1; index += 1) {
        const distance = map.distance(path[index], path[index + 1]);
        segments.push(distance);
        total += distance;
    }
    if (total <= 0) return path[0];
    const target = total * ratio;
    let walked = 0;
    for (let index = 0; index < segments.length; index += 1) {
        const distance = segments[index];
        if (walked + distance >= target) {
            const local = distance > 0 ? (target - walked) / distance : 0;
            return [
                path[index][0] + ((path[index + 1][0] - path[index][0]) * local),
                path[index][1] + ((path[index + 1][1] - path[index][1]) * local),
            ];
        }
        walked += distance;
    }
    return path[path.length - 1];
}

function hasEstimatedMotion() {
    return vehicles.some(hasContinuousEstimate);
}

function currentVehicleLatLng(vehicle) {
    if (hasContinuousEstimate(vehicle)) {
        return estimatedVehicleLatLng(vehicle);
    }

    const animation = positionAnimations.get(vehicle.id);
    if (!animation) {
        return [vehicle.lat, vehicle.lng];
    }

    const progress = Math.min(1, (performance.now() - animation.startedAt) / animation.duration);
    if (progress >= 1) {
        positionAnimations.delete(vehicle.id);
        return [animation.toLat, animation.toLng];
    }

    const eased = easePosition(progress);
    return [
        animation.fromLat + (animation.toLat - animation.fromLat) * eased,
        animation.fromLng + (animation.toLng - animation.fromLng) * eased,
    ];
}

function startMotionLoop() {
    if (motionFrame !== null) return;
    const tick = () => {
        vehicleCanvasLayer?.redraw();
        if (positionAnimations.size > 0 || hasEstimatedMotion()) {
            motionFrame = window.requestAnimationFrame(tick);
        } else {
            motionFrame = null;
        }
    };
    motionFrame = window.requestAnimationFrame(tick);
}

function stagePositionAnimations(nextVehicles) {
    const previousById = new Map(vehicles.map((vehicle) => [vehicle.id, vehicle]));
    const previousPositions = new Map(vehicles.map((vehicle) => [vehicle.id, currentVehicleLatLng(vehicle)]));
    const nextIds = new Set(nextVehicles.map((vehicle) => vehicle.id));
    const now = performance.now();

    positionAnimations.forEach((_, id) => {
        if (!nextIds.has(id)) {
            positionAnimations.delete(id);
        }
    });

    nextVehicles.forEach((vehicle) => {
        if (vehicle.kind !== 'bus' && vehicle.kind !== 'garbage' && !(vehicle.kind === 'rail' && vehicle.estimated)) return;
        if (hasContinuousEstimate(vehicle)) return;
        if (!isFresh(vehicle)) return;
        const previousVehicle = previousById.get(vehicle.id);
        if (!previousVehicle || !isFresh(previousVehicle)) return;
        const previous = previousPositions.get(vehicle.id);
        if (!previous) return;

        const distance = map.distance(previous, [vehicle.lat, vehicle.lng]);
        if (distance < minAnimationDistanceMeters || distance > maxAnimationDistanceMeters) return;
        const duration = Math.min(maxBusAnimationMs, Math.max(minBusAnimationMs, (distance / busAnimationMetersPerSecond) * 1000));

        positionAnimations.set(vehicle.id, {
            fromLat: previous[0],
            fromLng: previous[1],
            toLat: vehicle.lat,
            toLng: vehicle.lng,
            startedAt: now,
            duration,
        });
    });

    if (positionAnimations.size > 0) {
        startMotionLoop();
    }
}

function normalizeVehicle(raw) {
    return {
        id: raw.id,
        kind: raw.kind,
        route: raw.route || '',
        headsign: raw.headsign || '',
        plate: raw.plate || '',
        operator: raw.operator || '',
        lat: Number(raw.lat),
        lng: Number(raw.lng),
        speed: Number(raw.speed || 0),
        bearing: Number(raw.bearing || 0),
        status: raw.status || '',
        updated_at: raw.updated_at || '',
        note: raw.note || '',
        stream_url: raw.stream_url || '',
        image_url: raw.image_url || '',
        source_key: raw.source_key || '',
        source_label: raw.source_label || '',
        available_rent_bikes: Number(raw.available_rent_bikes),
        available_return_bikes: Number(raw.available_return_bikes),
        car_type: raw.car_type || '',
        address: raw.address || '',
        routing_id: raw.routing_id || '',
        routing_name: raw.routing_name || '',
        route_shift: raw.route_shift || '',
        rail_system: raw.rail_system || '',
        direction_label: raw.direction_label || '',
        delay_minutes: Number(raw.delay_minutes),
        estimated: Boolean(raw.estimated),
        estimate_type: raw.estimate_type || '',
        estimate_from_lat: Number(raw.estimate_from_lat),
        estimate_from_lng: Number(raw.estimate_from_lng),
        estimate_to_lat: Number(raw.estimate_to_lat),
        estimate_to_lng: Number(raw.estimate_to_lng),
        estimate_start_ms: Number(raw.estimate_start_ms),
        estimate_end_ms: Number(raw.estimate_end_ms),
        estimate_path: Array.isArray(raw.estimate_path)
            ? raw.estimate_path
                .map((point) => Array.isArray(point) ? [Number(point[0]), Number(point[1])] : null)
                .filter((point) => point && Number.isFinite(point[0]) && Number.isFinite(point[1]))
            : [],
        estimate_path_source: raw.estimate_path_source || '',
    };
}

function kindLabel(kind) {
    return {
        bus: t('js.source.bus'),
        rail: t('js.source.rail'),
        bike: t('js.source.bike'),
        cctv: t('js.source.cctv'),
        garbage: t('js.source.garbageFull'),
    }[kind] || kind;
}

function railSystem(vehicle) {
    const value = `${vehicle.rail_system || vehicle.operator || vehicle.route || vehicle.note || ''}`.toUpperCase();
    if (value.includes('THSR') || value.includes('高鐵')) return t('js.source.railThsr');
    if (value.includes('TRA') || value.includes('台鐵')) return t('js.source.railTra');
    if (value.includes('METRO') || ['TRTC', 'NTDLRT', 'TYMC', 'TMRT', 'KRTC'].some((system) => value.includes(system))) return t('js.source.railMetro');
    return t('js.source.rail');
}

function railDirectionLabel(vehicle) {
    const explicit = (vehicle.direction_label || '').toString().trim();
    if (explicit !== '') return explicit;
    const text = `${vehicle.headsign || ''} ${vehicle.status || ''}`;
    if (text.includes('北上') || text.includes('往北')) return t('js.dir.north');
    if (text.includes('南下') || text.includes('往南')) return t('js.dir.south');
    const bearing = Number(vehicle.bearing);
    if (!Number.isFinite(bearing)) return '';
    const normalized = ((bearing % 360) + 360) % 360;
    if (normalized >= 315 || normalized < 45) return t('js.dir.north');
    if (normalized >= 135 && normalized < 225) return t('js.dir.south');
    if (normalized >= 45 && normalized < 135) return t('js.dir.east');
    return t('js.dir.west');
}

function railDelayLabel(vehicle) {
    const delay = Number(vehicle.delay_minutes);
    if (Number.isFinite(delay) && delay > 0) return t('js.rail.delay', { n: Math.round(delay) });
    const status = (vehicle.status || '').toString();
    const match = status.match(/(?:延誤|誤點)\s*(\d+)\s*分/);
    if (match) return t('js.rail.delay', { n: match[1] });
    if (status.includes('準點')) return t('js.rail.onTime');
    return '';
}

function garbageVehicleType(vehicle) {
    const type = `${vehicle.car_type || vehicle.route || vehicle.note || ''}`;
    if (type.includes('回收')) return t('js.garbage.recycle');
    if (type.includes('曳引')) return t('js.garbage.tow');
    return t('js.garbage.trash');
}

function garbageShortLabel(vehicle) {
    const type = `${vehicle.car_type || vehicle.route || vehicle.note || ''}`;
    if (type.includes('回收')) return t('js.garbage.recycleShort');
    if (type.includes('曳引')) return t('js.garbage.towShort');
    return t('js.garbage.trashShort');
}

function markerText(vehicle) {
    if (vehicle.kind === 'bus') return t('js.kind.bus');
    if (vehicle.kind === 'rail') return railSystem(vehicle);
    if (vehicle.kind === 'bike') return t('js.kind.bike');
    if (vehicle.kind === 'cctv') return t('js.kind.cctv');
    if (vehicle.kind === 'garbage') return garbageShortLabel(vehicle);
    return 'LIVE';
}

function cctvDirectionLabel(vehicle) {
    const text = `${vehicle.headsign || ''} ${vehicle.plate || ''} ${vehicle.route || ''}`.toUpperCase();
    if (/北上|往北| NORTH|[-_]N[-_]|[-/]N(?:[-/]|$)|\bNB\b/.test(text)) return t('js.dir.north');
    if (/南下|往南| SOUTH|[-_]S[-_]|[-/]S(?:[-/]|$)|\bSB\b/.test(text)) return t('js.dir.south');
    if (/東向|東行|往東| EAST|[-_]E[-_]|[-/]E(?:[-/]|$)|\bEB\b/.test(text)) return t('js.dir.east');
    if (/西向|西行|往西| WEST|[-_]W[-_]|[-/]W(?:[-/]|$)|\bWB\b/.test(text)) return t('js.dir.west');
    if (/\(R\)|[-_]R(?:[-_]|$)/.test(text)) return t('js.dir.forward');
    if (/\(L\)|[-_]L(?:[-_]|$)/.test(text)) return t('js.dir.reverse');
    return '';
}

function cctvDirectionBearing(vehicle) {
    const direction = cctvDirectionLabel(vehicle);
    return {
        北上: 0,
        南下: 180,
        東行: 90,
        西行: 270,
        順向: 45,
        逆向: 225,
        [t('js.dir.north')]: 0,
        [t('js.dir.south')]: 180,
        [t('js.dir.east')]: 90,
        [t('js.dir.west')]: 270,
        [t('js.dir.forward')]: 45,
        [t('js.dir.reverse')]: 225,
    }[direction] ?? null;
}

function safeHttpUrl(value) {
    const url = String(value || '').trim();
    return /^https?:\/\//i.test(url) ? url : '';
}

function userIcon() {
    return L.divIcon({
        html: '<span class="user-marker" aria-hidden="true"><span></span></span>',
        className: '',
        iconSize: [38, 38],
        iconAnchor: [19, 19],
    });
}

function savedPointIcon() {
    return L.divIcon({
        html: '<span class="saved-point-marker" aria-hidden="true"><span></span></span>',
        className: '',
        iconSize: [38, 44],
        iconAnchor: [19, 40],
    });
}

function userPositionIcon(kind = 'gps') {
    return kind === 'saved-point' ? savedPointIcon() : userIcon();
}

function savedPointPopupHtml(name, lat, lng, label = t('js.popup.savedPoint')) {
    return `
        <strong>${escapeHtml(name || label)}</strong>
        <br><small>${escapeHtml(label)}</small>
        <br><small>${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}</small>
    `;
}

function popupHtml(vehicle) {
    const speed = Number.isFinite(vehicle.speed) ? `${Math.round(vehicle.speed)} km/h` : '--';
    const distance = distanceToUser(vehicle);
    const streamUrl = safeHttpUrl(vehicle.stream_url);
    const imageUrl = safeHttpUrl(vehicle.image_url);
    const mediaUrl = streamUrl || imageUrl;
    const statusText = vehicle.kind === 'cctv'
        ? cctvPointLabel(vehicle)
        : (vehicle.status || t('js.notProvided'));
    const stateBadge = vehicle.kind === 'cctv'
        ? cctvStateLabel(vehicle)
        : formatAge(vehicle.updated_at);
    const mediaHtml = mediaUrl
        ? `<p class="popup-meta"><a class="popup-link" href="${escapeHtml(mediaUrl)}" target="_blank" rel="noopener">${escapeHtml(vehicle.kind === 'cctv' ? cctvMediaActionLabel(vehicle) : t('js.cctv.openLive'))}</a></p>`
        : '';
    const reportHtml = vehicle.kind === 'cctv'
        ? `<p class="popup-meta"><button type="button" class="popup-report-button" data-cctv-report-id="${escapeHtml(vehicle.id || '')}">${t('js.popup.report')}</button></p>`
        : '';
    let motionHtml = `<p class="popup-meta">${escapeHtml(t('js.popup.speedBearing', { speed, bearing: Math.round(vehicle.bearing || 0) }))}</p>`;
    if (vehicle.kind === 'cctv') {
        const direction = cctvDirectionLabel(vehicle);
        motionHtml = `<p class="popup-meta">${escapeHtml(t('js.popup.cctvMeta', { source: cctvSourceLabel(vehicle), direction: direction ? t('js.popup.direction', { direction }) : '', status: cctvMediaStatusLabel(vehicle, Boolean(mediaUrl)) }))}</p>`;
    } else if (vehicle.kind === 'garbage') {
        const routeHtml = vehicle.routing_name
            ? `<p class="popup-meta">${escapeHtml(t('js.popup.garbageRoute', { name: vehicle.routing_name, shift: vehicle.route_shift ? t('js.popup.routeShift', { shift: vehicle.route_shift }) : '' }))}</p>`
            : `<p class="popup-meta">${escapeHtml(t('js.popup.garbageRouteMissing'))}</p>`;
        motionHtml = `<p class="popup-meta">${escapeHtml(t('js.popup.garbageMeta', { type: garbageVehicleType(vehicle), speed }))}</p>${routeHtml}`;
    } else if (vehicle.kind === 'bike') {
        const rent = Number.isFinite(vehicle.available_rent_bikes) ? vehicle.available_rent_bikes : '--';
        const returns = Number.isFinite(vehicle.available_return_bikes) ? vehicle.available_return_bikes : '--';
        motionHtml = `<p class="popup-meta">${escapeHtml(t('js.popup.bikeSupply', { rent, returns }))}</p>`;
    } else if (vehicle.kind === 'rail') {
        motionHtml = vehicle.estimated
            ? `<p class="popup-meta">${escapeHtml(railSystem(vehicle))} ${escapeHtml(t('js.popup.railEst'))}</p>`
            : `<p class="popup-meta">${escapeHtml(railSystem(vehicle))} ${escapeHtml(t('js.popup.railBoard'))}</p>`;
    }

    return `
        <h3 class="popup-title">${escapeHtml(vehicle.route || vehicle.plate || kindLabel(vehicle.kind))}</h3>
        <p class="popup-meta">${escapeHtml(t('js.popup.kindMeta', { kind: kindLabel(vehicle.kind), headsign: vehicle.headsign || vehicle.operator || t('js.popup.noDirection') }))}</p>
        <p class="popup-meta">${escapeHtml(t('js.popup.plate', { plate: vehicle.plate || t('js.notProvided') }))}</p>
        <p class="popup-meta">${escapeHtml(t('js.popup.status', { status: statusText }))}</p>
        ${motionHtml}
        <p class="popup-meta">${escapeHtml(t('js.popup.distance', { distance: formatDistance(distance) }))}</p>
        ${mediaHtml}
        ${reportHtml}
        <span class="popup-state">${escapeHtml(stateBadge)}</span>
    `;
}

function vehicleHoverTitle(vehicle) {
    return vehicle.route || vehicle.plate || kindLabel(vehicle.kind);
}

function vehicleHoverDetail(vehicle) {
    if (vehicle.kind === 'cctv') {
        return `${cctvSourceLabel(vehicle)} · ${cctvPointLabel(vehicle)}${vehicle.plate ? ` · ${vehicle.plate}` : ''}`;
    }
    if (vehicle.kind === 'bike') {
        const rent = Number.isFinite(vehicle.available_rent_bikes) ? vehicle.available_rent_bikes : '--';
        const returns = Number.isFinite(vehicle.available_return_bikes) ? vehicle.available_return_bikes : '--';
        return t('js.popup.bikeShort', { rent, returns });
    }
    if (vehicle.kind === 'rail') {
        return [railSystem(vehicle), railDirectionLabel(vehicle), railDelayLabel(vehicle)].filter(Boolean).join(' · ') || t('js.popup.railData');
    }
    if (vehicle.kind === 'garbage') {
        return [garbageVehicleType(vehicle), vehicle.plate].filter(Boolean).join(' · ');
    }
    const speed = Number.isFinite(vehicle.speed) ? `${Math.round(vehicle.speed)} km/h` : '';
    return [vehicle.headsign || vehicle.operator, vehicle.plate, speed].filter(Boolean).join(' · ');
}

function ensureCanvasHoverTooltip() {
    if (canvasHoverTooltip) return canvasHoverTooltip;
    canvasHoverTooltip = document.createElement('div');
    canvasHoverTooltip.className = 'canvas-hover-tooltip';
    canvasHoverTooltip.hidden = true;
    document.querySelector('.map-stage')?.appendChild(canvasHoverTooltip);
    return canvasHoverTooltip;
}

function hideCanvasHoverTooltip() {
    if (canvasHoverTooltip) {
        canvasHoverTooltip.hidden = true;
    }
}

function showCanvasHoverTooltip(vehicle, containerPoint) {
    const tooltip = ensureCanvasHoverTooltip();
    if (!tooltip) return;
    const detail = vehicleHoverDetail(vehicle);
    tooltip.innerHTML = `
        <strong>${escapeHtml(vehicleHoverTitle(vehicle))}</strong>
        ${detail ? `<small>${escapeHtml(detail)}</small>` : ''}
    `;
    tooltip.hidden = false;
    const mapSize = map.getSize();
    const tooltipWidth = Math.min(260, tooltip.offsetWidth || 180);
    const x = Math.min(Math.max(10, containerPoint.x + 14), Math.max(10, mapSize.x - tooltipWidth - 10));
    const y = Math.max(10, containerPoint.y - 46);
    tooltip.style.transform = `translate(${Math.round(x)}px, ${Math.round(y)}px)`;
}

function nearestVehicleHit(point) {
    let nearest = null;
    let nearestDistance = Infinity;
    drawnVehicleHits.forEach((hit) => {
        const distance = Math.hypot(hit.x - point.x, hit.y - point.y);
        let inside = distance <= hit.radius;
        if (!inside && hit.label) {
            const label = hit.label;
            inside = point.x >= label.left
                && point.x <= label.left + label.width
                && point.y >= label.top
                && point.y <= label.top + label.height;
        }
        const score = inside
            ? (distance <= hit.radius ? distance : hit.radius + 1)
            : Infinity;
        if (score < nearestDistance) {
            nearest = hit.vehicle;
            nearestDistance = score;
        }
    });
    return nearest;
}

function vehicleColor(vehicle) {
    if (vehicle.kind === 'garbage') {
        const type = `${vehicle.car_type || vehicle.route || vehicle.note || ''}`;
        if (type.includes('回收')) return '#2f9e44';
        if (type.includes('曳引')) return '#7c8794';
        return '#e5533d';
    }
    if (vehicle.kind === 'rail') {
        const value = `${vehicle.rail_system || vehicle.operator || vehicle.route || vehicle.note || ''}`.toUpperCase();
        if (value.includes('THSR') || value.includes('高鐵')) return '#e5533d';
        if (value.includes('METRO') || ['TRTC', 'NTDLRT', 'TYMC', 'TMRT', 'KRTC'].some((system) => value.includes(system))) return '#4f8fd6';
        return '#3d80d8';
    }
    if (vehicle.kind === 'cctv') {
        return cctvSourceColor(vehicle);
    }
    return {
        bus: '#2f9e7e',
        bike: '#d4962f',
    }[vehicle.kind] || '#2f9e7e';
}

function roundedRect(ctx, x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
}

function drawArrow(ctx, x, y, bearing, color, size) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(((bearing || 0) * Math.PI) / 180);
    ctx.beginPath();
    ctx.moveTo(0, -size);
    ctx.lineTo(size * .55, size * .7);
    ctx.lineTo(0, size * .35);
    ctx.lineTo(-size * .55, size * .7);
    ctx.closePath();
    ctx.fillStyle = color;
    ctx.fill();
    ctx.restore();
}

function drawCamera(ctx, x, y, color, size, vehicle = null) {
    const group = vehicle ? cctvSourceGroup(vehicle) : 'custom';
    const width = size * 1.8;
    const height = size * 1.22;
    ctx.save();

    if (group === 'freeway') {
        ctx.beginPath();
        ctx.moveTo(x - size * .82, y - size * .68);
        ctx.lineTo(x + size * .82, y - size * .68);
        ctx.lineTo(x + size * .64, y + size * .36);
        ctx.quadraticCurveTo(x, y + size * .86, x - size * .64, y + size * .36);
        ctx.closePath();
        ctx.fillStyle = color;
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#fff';
        ctx.stroke();
        ctx.fillStyle = '#fff';
        ctx.font = `900 ${Math.max(9, size * .62)}px system-ui, -apple-system, "Noto Sans TC", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('國', x, y - size * .02);
    } else if (group === 'highway') {
        roundedRect(ctx, x - width / 2, y - height / 2, width, height, 4);
        ctx.fillStyle = color;
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#fff';
        ctx.stroke();
        ctx.fillStyle = '#fff';
        ctx.font = `900 ${Math.max(9, size * .58)}px system-ui, -apple-system, "Noto Sans TC", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('省', x, y - size * .02);
    } else if (group === 'city') {
        ctx.beginPath();
        ctx.arc(x, y, size * .83, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#fff';
        ctx.stroke();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = Math.max(2, size * .18);
        ctx.beginPath();
        ctx.moveTo(x - size * .52, y);
        ctx.lineTo(x + size * .52, y);
        ctx.moveTo(x, y - size * .52);
        ctx.lineTo(x, y + size * .52);
        ctx.stroke();
    } else if (group === 'scenic') {
        ctx.beginPath();
        ctx.arc(x, y, size * .86, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#fff';
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x - size * .22, y - size * .42);
        ctx.lineTo(x + size * .48, y);
        ctx.lineTo(x - size * .22, y + size * .42);
        ctx.closePath();
        ctx.fillStyle = '#fff';
        ctx.fill();
    } else if (group === 'aggregated') {
        ctx.beginPath();
        ctx.arc(x, y, size * .86, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = '#fff';
        ctx.stroke();
        ctx.fillStyle = '#fff';
        ctx.font = `900 ${Math.max(9, size * .54)}px system-ui, -apple-system, "Noto Sans TC", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('TW', x, y + size * .02);
    } else {
        roundedRect(ctx, x - width / 2, y - height / 2, width, height, 4);
        ctx.fillStyle = color;
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x, y, size * .34, 0, Math.PI * 2);
        ctx.fillStyle = '#fff';
        ctx.fill();
        ctx.beginPath();
        ctx.arc(x, y, size * .16, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
    }

    const bearing = vehicle ? cctvDirectionBearing(vehicle) : null;
    if (bearing !== null) {
        ctx.translate(x + size * .98, y - size * .74);
        ctx.rotate((bearing * Math.PI) / 180);
        ctx.beginPath();
        ctx.moveTo(0, -size * .46);
        ctx.lineTo(size * .26, size * .22);
        ctx.lineTo(0, size * .1);
        ctx.lineTo(-size * .26, size * .22);
        ctx.closePath();
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.lineWidth = 1.3;
        ctx.strokeStyle = color;
        ctx.stroke();
    }
    ctx.restore();
}

function drawBikeStation(ctx, x, y, color, size, vehicle) {
    const rent = Number.isFinite(vehicle.available_rent_bikes) ? vehicle.available_rent_bikes : null;
    ctx.save();
    ctx.beginPath();
    ctx.arc(x, y, size + 3, 0, Math.PI * 2);
    ctx.fillStyle = '#fff';
    ctx.fill();
    ctx.lineWidth = 3;
    ctx.strokeStyle = color;
    ctx.stroke();
    ctx.fillStyle = color;
    ctx.font = '800 10px system-ui, -apple-system, "Noto Sans TC", sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(rent === null ? '站' : String(Math.min(99, rent)), x, y);
    ctx.restore();
}

function drawRailBoard(ctx, x, y, color, size) {
    const width = size * 2.15;
    const height = size * 1.55;
    ctx.save();
    roundedRect(ctx, x - width / 2, y - height / 2, width, height, 5);
    ctx.fillStyle = '#fff';
    ctx.fill();
    ctx.lineWidth = 2.5;
    ctx.strokeStyle = color;
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(x - size * .55, y - size * .18);
    ctx.lineTo(x + size * .55, y - size * .18);
    ctx.moveTo(x - size * .35, y + size * .22);
    ctx.lineTo(x + size * .35, y + size * .22);
    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.stroke();
    ctx.restore();
}

function drawGarbageTruck(ctx, x, y, bearing, color, size) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(((bearing || 0) * Math.PI) / 180);
    const width = size * 1.9;
    const height = size * 1.18;
    roundedRect(ctx, -width / 2, -height / 2, width, height, 4);
    ctx.fillStyle = color;
    ctx.fill();
    ctx.fillStyle = '#fff';
    ctx.fillRect(-width * .32, -height * .28, width * .34, height * .56);
    ctx.beginPath();
    ctx.arc(-width * .34, height * .53, size * .16, 0, Math.PI * 2);
    ctx.arc(width * .34, height * .53, size * .16, 0, Math.PI * 2);
    ctx.fillStyle = '#17212b';
    ctx.fill();
    ctx.restore();
}

function vehicleCanvasLabel(vehicle, zoom) {
    const route = (vehicle.route || markerText(vehicle)).toString().slice(0, 8);
    if (vehicle.kind === 'cctv') {
        const direction = cctvDirectionLabel(vehicle);
        if (zoom >= 16) return [route, direction].filter(Boolean).join(' ').slice(0, 16);
        return direction || route;
    }
    if (vehicle.kind === 'garbage') {
        const plate = (vehicle.plate || '').toString().trim();
        const type = garbageShortLabel(vehicle);
        const routeName = (vehicle.routing_name || '').toString().trim();
        if (zoom >= 18 && routeName !== '' && plate !== '') return `${routeName} ${plate}`.slice(0, 18);
        if (zoom >= 16 && routeName !== '') return routeName.slice(0, 14);
        return zoom >= 17 && plate !== '' ? `${type} ${plate}` : type;
    }
    if (vehicle.kind === 'bike') {
        const rent = Number.isFinite(vehicle.available_rent_bikes) ? vehicle.available_rent_bikes : '--';
        const returns = Number.isFinite(vehicle.available_return_bikes) ? vehicle.available_return_bikes : '--';
        return zoom >= 17 ? t('js.bike.marker', { route, rent, returns }) : route;
    }
    if (vehicle.kind === 'rail') {
        const system = railSystem(vehicle);
        const trainNo = (vehicle.plate || route.replace(system, '')).toString().trim();
        const direction = railDirectionLabel(vehicle);
        const delay = railDelayLabel(vehicle);
        if (zoom >= 17) {
            return [trainNo || system, direction, delay].filter(Boolean).join(' ').slice(0, 18);
        }
        if (zoom >= 15) {
            return [trainNo || system, direction].filter(Boolean).join(' ').slice(0, 14);
        }
        return system;
    }

    const plate = (vehicle.plate || '').toString().trim();
    if (zoom >= 17 && plate !== '') return `${route} ${plate}`;
    return route;
}

function drawVehicle(ctx, vehicle, point, options) {
    const monitored = monitoredVehicles.has(vehicle.id);
    const stale = usesFreshnessFilter(vehicle) && !isFresh(vehicle);
    const color = monitored ? monitorColor(vehicle.id) : (stale ? '#a8aeb4' : vehicleColor(vehicle));
    const selected = vehicle.id === selectedVehicleId;
    const compact = !options.showLabels;
    const radius = compact ? 10 : 12;

    ctx.save();
    ctx.shadowColor = 'rgba(0, 0, 0, .26)';
    ctx.shadowBlur = compact ? 5 : 8;
    ctx.shadowOffsetY = 2;

    if (monitored || selected) {
        ctx.beginPath();
        ctx.arc(point.x, point.y, radius + (selected ? 10 : 7), 0, Math.PI * 2);
        ctx.strokeStyle = color;
        ctx.lineWidth = selected ? 5 : 3;
        ctx.stroke();
    }

    if (vehicle.kind === 'bus' || vehicle.kind === 'cctv' || vehicle.kind === 'garbage') {
        ctx.beginPath();
        ctx.arc(point.x, point.y, radius + 4, 0, Math.PI * 2);
        ctx.fillStyle = '#fff';
        ctx.fill();
    }
    if (vehicle.kind === 'cctv') {
        drawCamera(ctx, point.x, point.y, color, radius + 2, vehicle);
    } else if (vehicle.kind === 'garbage') {
        drawGarbageTruck(ctx, point.x, point.y, vehicle.bearing, color, radius + 2);
    } else if (vehicle.kind === 'bike') {
        drawBikeStation(ctx, point.x, point.y, color, radius + 1, vehicle);
    } else if (vehicle.kind === 'rail') {
        drawRailBoard(ctx, point.x, point.y, color, radius + 1);
    } else {
        drawArrow(ctx, point.x, point.y, vehicle.bearing, color, radius + 1);
    }
    ctx.shadowColor = 'transparent';

    if (options.showLabels) {
        const label = vehicleCanvasLabel(vehicle, options.zoom || 0);
        ctx.font = '700 12px system-ui, -apple-system, "Noto Sans TC", sans-serif';
        const textWidth = Math.min(options.zoom >= 17 ? 126 : 84, Math.ceil(ctx.measureText(label).width) + 14);
        const left = point.x + 13;
        const top = point.y - 15;
        roundedRect(ctx, left, top, textWidth, 28, 7);
        ctx.fillStyle = 'rgba(255, 255, 255, .94)';
        ctx.fill();
        ctx.strokeStyle = 'rgba(21, 32, 38, .12)';
        ctx.lineWidth = 1;
        ctx.stroke();
        ctx.fillStyle = '#17212b';
        ctx.fillText(label, left + 7, top + 18, textWidth - 12);
        options._labelHit = { left, top, width: textWidth, height: 28 };
    }

    ctx.restore();
}

function canvasVehiclesInView(vehicleList) {
    const bounds = map.getBounds().pad(.12);
    return vehicleList
        .filter((vehicle) => bounds.contains(currentVehicleLatLng(vehicle)))
        .slice(0, maxCanvasVehicles);
}

function displayedVehiclePoints(targetMap, vehicleList) {
    const cellSize = 24;
    const cells = new Map();
    const projected = vehicleList.map((vehicle) => {
        const actualPoint = targetMap.latLngToContainerPoint(currentVehicleLatLng(vehicle));
        const key = `${Math.floor(actualPoint.x / cellSize)}:${Math.floor(actualPoint.y / cellSize)}`;
        const item = { vehicle, actualPoint, point: actualPoint, offset: false };
        if (!cells.has(key)) cells.set(key, []);
        cells.get(key).push(item);
        return item;
    });

    cells.forEach((items) => {
        if (items.length <= 1) return;
        items.sort((a, b) => String(a.vehicle.id).localeCompare(String(b.vehicle.id)));
        const radius = Math.min(34, 13 + items.length * 2.4);
        items.forEach((item, index) => {
            const angle = -Math.PI / 2 + (Math.PI * 2 * index) / items.length;
            item.point = L.point(
                item.actualPoint.x + Math.cos(angle) * radius,
                item.actualPoint.y + Math.sin(angle) * radius,
            );
            item.offset = true;
        });
    });

    return projected;
}

function showVehiclePopup(vehicle, monitorStateText = null) {
    selectedVehicleId = vehicle.id;
    const monitored = monitoredVehicles.has(vehicle.id);
    const monitorState = monitorStateText || monitorStateLabel(vehicle, monitored);
    const latLng = currentVehicleLatLng(vehicle);
    L.popup({ offset: [0, -12], maxWidth: 280 })
        .setLatLng(latLng)
        .setContent(`${popupHtml(vehicle)}<span class="popup-state monitor-state">${monitorState}</span>`)
        .openOn(map);
    vehicleCanvasLayer?.redraw();
}

const VehicleCanvasLayer = L.Layer.extend({
    onAdd(targetMap) {
        this._map = targetMap;
        this._canvas = L.DomUtil.create('canvas', 'vehicle-canvas leaflet-layer');
        this._ctx = this._canvas.getContext('2d');
        targetMap.getPanes().overlayPane.appendChild(this._canvas);
        L.DomEvent.on(this._canvas, 'click', this._handleClick, this);
        L.DomEvent.on(this._canvas, 'mousemove', this._handleMouseMove, this);
        L.DomEvent.on(this._canvas, 'mouseleave', this._handleMouseLeave, this);
        targetMap.on('resize move zoom moveend zoomend popupclose', this.redraw, this);
        this.redraw();
    },

    onRemove(targetMap) {
        L.DomEvent.off(this._canvas, 'click', this._handleClick, this);
        L.DomEvent.off(this._canvas, 'mousemove', this._handleMouseMove, this);
        L.DomEvent.off(this._canvas, 'mouseleave', this._handleMouseLeave, this);
        targetMap.off('resize move zoom moveend zoomend popupclose', this.redraw, this);
        this._canvas.remove();
    },

    setVehicles(vehicleList) {
        this._vehicles = vehicleList;
        this.redraw();
        if ((vehicleList || []).some(hasContinuousEstimate)) {
            startMotionLoop();
        }
    },

    redraw() {
        window.cancelAnimationFrame(redrawTimer);
        redrawTimer = window.requestAnimationFrame(() => this._draw());
    },

    _draw() {
        if (!this._map || !this._canvas) return;
        hideCanvasHoverTooltip();

        const size = this._map.getSize();
        const ratio = window.devicePixelRatio || 1;
        const position = this._map.containerPointToLayerPoint([0, 0]);
        L.DomUtil.setPosition(this._canvas, position);
        this._canvas.style.width = `${size.x}px`;
        this._canvas.style.height = `${size.y}px`;

        if (this._canvas.width !== Math.round(size.x * ratio) || this._canvas.height !== Math.round(size.y * ratio)) {
            this._canvas.width = Math.round(size.x * ratio);
            this._canvas.height = Math.round(size.y * ratio);
        }

        const ctx = this._ctx;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, size.x, size.y);

        const vehicleList = canvasVehiclesInView(this._vehicles || []);
        const zoom = this._map.getZoom();
        const labelLimit = zoom >= 17 ? 420 : (zoom >= 15 ? 260 : 130);
        const showLabels = zoom >= 14 && vehicleList.length <= labelLimit;
        drawnVehicleHits = [];

        displayedVehiclePoints(this._map, vehicleList).forEach(({ vehicle, actualPoint, point, offset }) => {
            if (offset) {
                ctx.beginPath();
                ctx.moveTo(actualPoint.x, actualPoint.y);
                ctx.lineTo(point.x, point.y);
                ctx.strokeStyle = 'rgba(23, 33, 43, .26)';
                ctx.lineWidth = 1.25;
                ctx.setLineDash([3, 3]);
                ctx.stroke();
                ctx.setLineDash([]);
            }
            const drawOptions = { showLabels, zoom };
            drawVehicle(ctx, vehicle, point, drawOptions);
            drawnVehicleHits.push({
                vehicle,
                x: point.x,
                y: point.y,
                radius: showLabels ? 26 : 18,
                label: drawOptions._labelHit || null,
            });
        });

        appShell.classList.toggle('canvas-labels', showLabels);
        appShell.classList.toggle('canvas-capped', (this._vehicles || []).length > vehicleList.length);
    },

    _handleMouseMove(event) {
        if (measuringDistance || pickingLocation || window.matchMedia('(hover: none)').matches) {
            hideCanvasHoverTooltip();
            setStreetViewHoverCoverage(false);
            this._canvas.style.cursor = '';
            return;
        }
        const point = this._map.mouseEventToContainerPoint(event);
        const nearest = nearestVehicleHit(point);
        if (nearest) {
            setStreetViewHoverCoverage(false);
            showCanvasHoverTooltip(nearest, point);
            this._canvas.style.cursor = 'pointer';
            return;
        }
        hideCanvasHoverTooltip();
        if (streetViewPicking) {
            scheduleStreetViewHoverCheck(point);
            this._canvas.style.cursor = '';
            return;
        }
        this._canvas.style.cursor = '';
    },

    _handleMouseLeave() {
        hideCanvasHoverTooltip();
        setStreetViewHoverCoverage(false);
        if (this._canvas) {
            this._canvas.style.cursor = '';
        }
    },

    _handleClick(event) {
        const point = this._map.mouseEventToContainerPoint(event);

        if (measuringDistance) {
            addMeasurePoint(this._map.containerPointToLatLng(point));
            L.DomEvent.stop(event);
            return;
        }

        if (pickingLocation) {
            const latLng = this._map.containerPointToLatLng(point);
            setPickedLocation(latLng);
            L.DomEvent.stop(event);
            return;
        }

        // Map layers / monitors always win over street view.
        const nearest = nearestVehicleHit(point);
        if (nearest) {
            const monitored = toggleMonitor(nearest);
            showVehiclePopup(nearest, monitorStateLabel(nearest, monitored, !monitored));
            renderList(visibleVehiclesCache());
            L.DomEvent.stop(event);
            return;
        }

        if (streetViewPicking && isStreetViewCoverageAt(point, 5)) {
            lookupStreetViewAt(this._map.containerPointToLatLng(point));
            L.DomEvent.stop(event);
        }
    },
});

document.addEventListener('click', (event) => {
    const reportButton = event.target.closest('.popup-report-button');
    if (!reportButton) return;
    event.preventDefault();
    event.stopPropagation();
    const cctvId = reportButton.dataset.cctvReportId || '';
    const vehicle = vehicles.find((item) => item.id === cctvId)
        || monitoredVehicles.get(cctvId);
    if (vehicle) {
        reportCctvIssue(vehicle);
    }
});

loadMonitoredVehicles();
loadPinnedMonitors();
loadPinnedMonitorSizes();
vehicleCanvasLayer = new VehicleCanvasLayer().addTo(map);
renderCctvMonitorWindows();

function vehicleMatches(vehicle) {
    const freshnessMatch = !usesFreshnessFilter(vehicle) || staleLayerToggle?.checked || isFresh(vehicle);
    const distance = distanceToUser(vehicle);
    const boundsMatch = map.getBounds().pad(.05).contains(currentVehicleLatLng(vehicle));
    return freshnessMatch && boundsMatch && cctvSourceAllowed(vehicle);
}

function sortedVisibleVehicles() {
    return vehicles
        .filter(vehicleMatches)
        .map((vehicle) => ({ ...vehicle, distance: distanceToUser(vehicle) }))
        .sort((a, b) => {
            if (a.distance === null && b.distance === null) return (a.route || '').localeCompare(b.route || '', 'zh-Hant');
            if (a.distance === null) return 1;
            if (b.distance === null) return -1;
            return a.distance - b.distance;
        });
}

function renderList(visibleVehicles) {
    vehicleList.innerHTML = '';

    if (visibleVehicles.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'status-line';
        empty.textContent = vehicles.length > 0
            ? t('js.list.emptyFiltered')
            : t('js.list.empty');
        vehicleList.appendChild(empty);
        return;
    }

    const listVehicles = visibleVehicles.slice(0, maxListItems);
    const fragment = document.createDocumentFragment();

    listVehicles.forEach((vehicle) => {
        const node = vehicleTemplate.content.firstElementChild.cloneNode(true);
        const button = node.querySelector('button');
        const icon = node.querySelector('.vehicle-type');
        const title = node.querySelector('strong');
        const small = node.querySelector('small');
        const meta = node.querySelector('.vehicle-meta');
        const monitored = monitoredVehicles.has(vehicle.id);

        icon.classList.add(vehicle.kind);
        icon.textContent = markerText(vehicle);
        title.textContent = vehicle.route || vehicle.plate || kindLabel(vehicle.kind);
        small.textContent = vehicle.kind === 'cctv'
            ? `${cctvSourceLabel(vehicle)} · ${cctvPointLabel(vehicle)} · ${vehicle.plate || t('js.list.noId')}`
            : `${vehicle.headsign || vehicle.operator || t('js.popup.noDirection')} · ${vehicle.plate || t('js.list.noPlate')}`;
        meta.textContent = vehicle.kind === 'cctv'
            ? `${formatDistance(vehicle.distance)} · ${cctvMediaStatusLabel(vehicle)} · ${cctvStateLabel(vehicle)}`
            : `${formatDistance(vehicle.distance)} · ${Math.round(vehicle.speed || 0)} km/h · ${vehicle.status || t('js.list.statusUnknown')} · ${formatAge(vehicle.updated_at)}`;
        node.classList.toggle('monitored', monitored);
        if (monitored) {
            node.style.setProperty('--monitor-color', monitorColor(vehicle.id));
        }

        button.addEventListener('click', () => {
            map.setView([vehicle.lat, vehicle.lng], Math.max(map.getZoom(), 16));
            const isMonitored = toggleMonitor(vehicle);
            showVehiclePopup(vehicle, monitorStateLabel(vehicle, isMonitored, !isMonitored));
            document.querySelectorAll('.vehicle-card').forEach((item) => item.classList.remove('active'));
            if (isMonitored) {
                node.classList.add('active');
            }
            renderList(visibleVehiclesCache());
        });

        fragment.appendChild(node);
    });

    vehicleList.appendChild(fragment);

    if (visibleVehicles.length > maxListItems) {
        const note = document.createElement('p');
        note.className = 'status-line list-limit-note';
        note.textContent = t('js.list.truncated', { n: maxListItems });
        vehicleList.appendChild(note);
    }
}

function updateSummary(visibleVehicles) {
    visibleCount.textContent = String(visibleVehicles.length);
    liveCount.textContent = String(visibleVehicles.filter((vehicle) => usesFreshnessFilter(vehicle) && isFresh(vehicle)).length);
    const nearest = visibleVehicles.find((vehicle) => Number.isFinite(vehicle.distance));
    nearestDistance.textContent = nearest ? formatDistance(nearest.distance) : '--';
    if (monitorCount) {
        const visibleMonitored = visibleVehicles.filter((vehicle) => monitoredVehicles.has(vehicle.id)).length;
        monitorCount.textContent = monitoredVehicles.size === visibleMonitored
            ? String(monitoredVehicles.size)
            : `${visibleMonitored}/${monitoredVehicles.size}`;
    }
}

function weatherPopup(station) {
    const rows = selectedWeatherRows(station)
        .map((row) => `<div><dt>${escapeHtml(row.label)}</dt><dd>${escapeHtml(row.value)}</dd></div>`)
        .join('');
    return `
        <strong>${escapeHtml(station.name || t('js.weather.station'))}</strong>
        <dl class="popup-details">
            ${rows || `<div><dt>${escapeHtml(t('js.weather.data'))}</dt><dd>${escapeHtml(t('js.weather.noFields'))}</dd></div>`}
            <div><dt>${escapeHtml(t('js.weather.updated'))}</dt><dd>${escapeHtml(formatAge(station.updated_at))}</dd></div>
        </dl>
    `;
}

function renderWeatherLayer() {
    if (!weatherLayerToggle?.checked) {
        weatherLayer?.clearLayers();
        return;
    }
    if (!weatherLayer) {
        weatherLayer = L.layerGroup().addTo(map);
    }

    weatherLayer.clearLayers();
    if (selectedWeatherFields().length === 0) {
        return;
    }
    const bounds = map.getBounds().pad(.12);
    visibleWeatherStations(bounds)
        .forEach((group) => {
            const stations = group.stations || [group.station];
            const station = group.station;
            const rowCount = Math.min(stations.length, weatherGroupVisibleLimit()) + (stations.length > weatherGroupVisibleLimit() ? 1 : 0);
            L.marker([station.lat, station.lng], {
                pane: 'markerPane',
                title: weatherGroupTitle(stations),
                icon: L.divIcon({
                    className: 'weather-temp-icon',
                    html: weatherGroupHtml(stations),
                    iconSize: [96, Math.max(28, rowCount * 24 + 4)],
                    iconAnchor: [48, Math.max(14, Math.round((rowCount * 24 + 4) / 2))],
                    popupAnchor: [0, -16],
                    tooltipAnchor: [0, -16],
                }),
            })
                .bindPopup(weatherGroupPopup(stations), { maxWidth: 320 })
                .bindTooltip(weatherGroupTooltipHtml(stations), {
                    direction: 'top',
                    offset: [0, -8],
                    className: 'weather-tooltip',
                })
                .addTo(weatherLayer);
        });
}

function clusterSourceAllowed(cluster) {
    if (cctvSourceSelection === null) return true;
    const sources = Array.isArray(cluster?.sources) ? cluster.sources : [];
    return sources.some((source) => cctvSourceSelection.has(String(source.key || '')));
}

function cctvClusterHtml(cluster) {
    const count = Number(cluster.count) || 0;
    const source = Array.isArray(cluster.sources) && cluster.sources[0] ? cluster.sources[0].label : t('js.source.cctv');
    const sizeClass = count >= 1000 ? 'xl' : (count >= 200 ? 'lg' : (count >= 50 ? 'md' : 'sm'));
    return `<span class="cctv-cluster-bubble ${sizeClass}"><strong>${count >= 1000 ? `${Math.round(count / 100) / 10}k` : count}</strong><small>${escapeHtml(source)}</small></span>`;
}

function renderCctvClusters() {
    if (!cctvClusterLayer) {
        cctvClusterLayer = L.layerGroup().addTo(map);
    }
    cctvClusterLayer.clearLayers();
    if (!cctvCacheSourceEnabled()) return;
    const clusters = (cctvClusters || []).filter(clusterSourceAllowed);
    clusters.forEach((cluster) => {
        const lat = Number(cluster.lat);
        const lng = Number(cluster.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
        const marker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'cctv-cluster-icon',
                html: cctvClusterHtml(cluster),
                iconSize: [96, 58],
                iconAnchor: [48, 29],
            }),
            zIndexOffset: 450,
        });
        marker.on('click', () => {
            const bounds = Array.isArray(cluster.bounds) && cluster.bounds.length === 4
                ? [[cluster.bounds[0], cluster.bounds[1]], [cluster.bounds[2], cluster.bounds[3]]]
                : null;
            if (bounds) {
                map.fitBounds(bounds, { padding: [64, 64], maxZoom: Math.max(map.getZoom() + 3, 14) });
            } else {
                map.setView([lat, lng], Math.max(map.getZoom() + 3, 14));
            }
            updateMapLoadStatus('loading', t('js.cctv.zoomCluster'), t('js.cctv.zoomClusterHint', { n: Number(cluster.count) || 0 }));
            scheduleLoadVehicles(80);
        });
        cctvClusterLayer.addLayer(marker);
    });
}

function render() {
    renderCctvSourceFilters();
    const visibleVehicles = sortedVisibleVehicles();
    vehicleCanvasLayer?.setVehicles(visibleVehicles);
    renderCctvClusters();
    renderWeatherLayer();
    renderRealpriceLayer();
    renderList(visibleVehicles);
    updateSummary(visibleVehicles);
    renderCctvMonitorWindows();
}

function visibleVehiclesCache() {
    return sortedVisibleVehicles();
}

function scheduleRender() {
    window.clearTimeout(renderTimer);
    renderTimer = window.setTimeout(render, 80);
}

async function loadVehicles(options = {}) {
    locationStatus.textContent = t('js.load.readingTraffic');
    const cities = mapCities();
    const transitSources = [];
    const selectedLabels = selectedSources().map(sourceLabel).join(t('js.listSep')) || t('js.source.none');
    updateMapLoadStatus('loading', t('js.load.readingBounds'), `${selectedLabels} · ${cities.map((city) => cityAreas[city]?.label || city).join('、')}`);
    const requests = [];

    if (garbageSourceEnabled()) {
        const params = new URLSearchParams({ bounds: mapBoundsParam() });
        updateMapLoadStatus('loading', t('js.load.readingGarbage'), t('js.load.garbageHint'));
        requests.push(fetch(`api/garbage.php?${params.toString()}&ts=${Date.now()}`, { cache: 'no-store' }).then(async (response) => {
            if (!response.ok) throw new Error(t('js.load.garbageFail', { status: response.status }));
            return response.json();
        }));
    }

    if (cctvCacheSourceEnabled()) {
        const params = new URLSearchParams({
            bounds: mapBoundsParam(),
            zoom: String(map.getZoom()),
            limit: String(cctvPayloadLimit),
        });
        updateMapLoadStatus(
            'loading',
            cctvDetailAllowed() ? t('js.load.readingCctvPoints') : t('js.load.readingCctvClusters'),
            cctvDetailAllowed() ? t('js.load.cctvPointsHint') : t('js.load.cctvClustersHint'),
        );
        requests.push(fetch(`api/cctv_cache.php?${params.toString()}&ts=${Date.now()}`, { cache: 'no-store' }).then(async (response) => {
            if (!response.ok) throw new Error(t('js.load.cctvFail', { status: response.status }));
            return response.json();
        }));
    }

    const payloads = requests.length > 0
        ? await Promise.all(requests)
        : [{
            source: t('js.load.noSource'),
            notice: t('js.load.noSourceNotice'),
            generated_at: new Date().toISOString(),
            vehicles: [],
        }];
    const nextVehicles = payloads
        .flatMap((payload) => payload.vehicles || [])
        .concat(userCctvVehiclesInBounds())
        .map(normalizeVehicle)
        .filter((vehicle) => Number.isFinite(vehicle.lat) && Number.isFinite(vehicle.lng));
    cctvClusters = payloads.flatMap((payload) => Array.isArray(payload.clusters) ? payload.clusters : []);
    stagePositionAnimations(nextVehicles);
    vehicles = nextVehicles;
    if (vehicles.some(hasContinuousEstimate)) {
        startMotionLoop();
    }
    syncMonitoredSnapshots();
    updatedAt.textContent = new Date(payloads[0]?.generated_at || Date.now()).toLocaleString('zh-TW');
    sourceName.textContent = payloads.map((payload) => payload.source).filter(Boolean).join(' + ') || '--';
    const motionHint = t('js.load.motionHint');
    const areaHint = t('js.load.areaHint', { cities: cities.map((city) => cityAreas[city]?.label || city).join(t('js.listSep')) });
    const notice = payloads.map((payload) => payload.notice).filter(Boolean).join('；') || (userPosition ? t('js.load.updated') : t('js.load.updatedNoLocate'));
    const visibleAfterLoad = sortedVisibleVehicles();
    const visibleAfterLoadCount = visibleAfterLoad.length;
    const usingTdxFallback = transitSources.length > 0 && (
        /憑證|可用快取|已使用快取|Invalid client credentials/i.test(notice)
        || payloads.some((payload) => Array.isArray(payload.warnings) && payload.warnings.some((warning) => /憑證|快取/.test(String(warning))))
    );
    const payloadSummary = payloads
        .map((payload) => {
            const source = payload.source || t('js.load.data');
            const count = Array.isArray(payload.vehicles) ? payload.vehicles.length : 0;
            if (source.includes('桃園垃圾車') && Number.isFinite(Number(payload.total_count))) {
                return t('js.load.garbageInView', { count, total: Number(payload.total_count) });
            }
            if (payload.limited && Number(payload.filtered_count) > count) {
                const clusterCount = Array.isArray(payload.clusters) ? payload.clusters.length : 0;
                if (clusterCount > 0) {
                    return t('js.load.sourceClusters', { source, clusters: clusterCount, filtered: Number(payload.filtered_count) });
                }
                return t('js.load.sourceShown', { source, count, filtered: Number(payload.filtered_count) });
            }
            return t('js.load.sourceCount', { source, count });
        })
        .join('；');
    locationStatus.textContent = `${areaHint}。${notice} ${motionHint}`;
    const hasWarning = /限流|失敗|冷卻|暫時|未選取/.test(notice);
    const onlyGarbageZero = garbageSourceEnabled()
        && transitSources.length === 0
        && nextVehicles.length === 0
        && payloads.some((payload) => (payload.source || '').includes('桃園垃圾車') && Number(payload.total_count || 0) > 0);
    const statusTitle = (() => {
        if (onlyGarbageZero) return t('js.load.noGarbage');
        if (usingTdxFallback && visibleAfterLoadCount === 0) return t('js.load.credNoCache');
        if (usingTdxFallback) return t('js.load.cacheCount', { n: visibleAfterLoadCount });
        if (hasWarning) return t('js.load.loadedWarn', { n: visibleAfterLoadCount });
        return t('js.load.loaded', { n: visibleAfterLoadCount });
    })();
    const statusDetail = (() => {
        const base = t('js.load.viewBase', { labels: selectedLabels, visible: visibleAfterLoadCount, total: nextVehicles.length });
        if (usingTdxFallback) return `${base}${t('js.load.credCache')}`;
        return `${base} · ${payloadSummary || notice}`;
    })();
    updateMapLoadStatus(
        hasWarning || onlyGarbageZero || usingTdxFallback ? 'warning' : 'idle',
        statusTitle,
        statusDetail,
    );
    lastLoadKey = loadKey();
    render();
    if (options.fitAfterLoad) {
        fitVisible();
    }
}

async function loadWeatherLayer() {
    if (!weatherLayerToggle?.checked || weatherLoading || weatherLoaded) {
        renderWeatherLayer();
        return;
    }

    weatherLoading = true;
    const previousStatus = locationStatus.textContent;
    locationStatus.textContent = t('js.weather.loading');
    try {
        const response = await fetch(`api/weather.php?ts=${Date.now()}`, { cache: 'no-store' });
        if (!response.ok) throw new Error(t('js.weather.failHttp', { status: response.status }));
        const payload = await response.json();
        weatherStations = (payload.stations || [])
            .map((station) => ({
                ...station,
                lat: Number(station.lat),
                lng: Number(station.lng),
            }))
            .filter((station) => Number.isFinite(station.lat) && Number.isFinite(station.lng));
        weatherLoaded = true;
        renderWeatherLayer();
        locationStatus.textContent = t('js.weather.updatedStations', { notice: payload.notice || t('js.weather.updatedDefault'), n: weatherStations.length });
    } catch (error) {
        locationStatus.textContent = error.message || t('js.weather.fail');
        renderWeatherLayer();
    } finally {
        weatherLoading = false;
        if (!weatherLayerToggle.checked && previousStatus) {
            locationStatus.textContent = previousStatus;
        }
    }
}

function clearUserPosition() {
    userPosition = null;
    if (userMarker) {
        map.removeLayer(userMarker);
        userMarker = null;
    }
}

function loadSavedLocations() {
    try {
        const saved = JSON.parse(localStorage.getItem(savedLocationsStorageKey) || '[]');
        return Array.isArray(saved)
            ? saved
                .map((item) => ({
                    id: String(item.id || ''),
                    name: String(item.name || '').trim(),
                    lat: Number(item.lat),
                    lng: Number(item.lng),
                    saved_at: item.saved_at || '',
                }))
                .filter((item) => item.id && item.name && Number.isFinite(item.lat) && Number.isFinite(item.lng))
            : [];
    } catch {
        return [];
    }
}

function saveSavedLocations(locations) {
    localStorage.setItem(savedLocationsStorageKey, JSON.stringify(locations.slice(0, 30)));
}

function saveNamedLocation(name, lat, lng) {
    const cleanName = String(name || '').trim();
    if (!cleanName || !Number.isFinite(Number(lat)) || !Number.isFinite(Number(lng))) return false;
    const locations = loadSavedLocations().filter((item) => item.name !== cleanName);
    locations.unshift({
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        name: cleanName,
        lat: Number(lat),
        lng: Number(lng),
        saved_at: new Date().toISOString(),
    });
    saveSavedLocations(locations);
    renderSavedLocations();
    return true;
}

function parseLatLngText(value) {
    const match = String(value || '').trim().match(/(-?\d+(?:\.\d+)?)\s*[,，\s]\s*(-?\d+(?:\.\d+)?)/u);
    if (!match) return null;
    const lat = Number(match[1]);
    const lng = Number(match[2]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180 || (lat === 0 && lng === 0)) {
        return null;
    }
    return { lat, lng };
}

function loadUserCctvs() {
    try {
        const saved = JSON.parse(localStorage.getItem(userCctvStorageKey) || '[]');
        return Array.isArray(saved)
            ? saved.map(sanitizeUserCctv).filter(Boolean)
            : [];
    } catch {
        return [];
    }
}

function saveUserCctvs(items) {
    localStorage.setItem(userCctvStorageKey, JSON.stringify(items.map(sanitizeUserCctv).filter(Boolean).slice(0, 300)));
}

function sanitizeUserCctv(item) {
    const coord = {
        lat: Number(item?.lat),
        lng: Number(item?.lng),
    };
    const name = String(item?.name || '').trim();
    const url = safeHttpUrl(item?.stream_url || item?.url || '');
    if (!name || !url || !Number.isFinite(coord.lat) || !Number.isFinite(coord.lng)) return null;
    if (coord.lat < -90 || coord.lat > 90 || coord.lng < -180 || coord.lng > 180 || (coord.lat === 0 && coord.lng === 0)) return null;
    return {
        id: String(item?.id || `user-cctv-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`),
        name,
        lat: coord.lat,
        lng: coord.lng,
        stream_url: url,
        direction: String(item?.direction || t('js.userCctv.customDir')).trim() || t('js.userCctv.customDir'),
        saved_at: item?.saved_at || new Date().toISOString(),
    };
}

function userCctvToVehicle(item) {
    return {
        id: `user-${item.id}`,
        kind: 'cctv',
        route: item.name,
        headsign: item.direction || t('js.userCctv.customDir'),
        plate: item.id.replace(/^user-cctv-/, ''),
        operator: t('js.userCctv.operator'),
        lat: item.lat,
        lng: item.lng,
        speed: null,
        bearing: 0,
        status: t('js.userCctv.status'),
        updated_at: item.saved_at || new Date().toISOString(),
        note: t('js.userCctv.note'),
        stream_url: item.stream_url,
        image_url: '',
        source_key: 'user-custom',
        source_label: t('js.userCctv.operator'),
    };
}

function userCctvVehiclesInBounds() {
    if (!cctvCacheSourceEnabled()) return [];
    const bounds = map.getBounds().pad(.12);
    return loadUserCctvs()
        .filter((item) => bounds.contains([item.lat, item.lng]))
        .map(userCctvToVehicle);
}

function clearUserCctvEditor() {
    if (userCctvIdInput) userCctvIdInput.value = '';
    if (userCctvNameInput) userCctvNameInput.value = '';
    if (userCctvUrlInput) userCctvUrlInput.value = '';
    if (userCctvCoordInput) userCctvCoordInput.value = '';
    if (userCctvSaveBtn) userCctvSaveBtn.textContent = t('js.userCctv.add');
}

function fillUserCctvEditor(item) {
    if (userCctvIdInput) userCctvIdInput.value = item.id;
    if (userCctvNameInput) userCctvNameInput.value = item.name;
    if (userCctvUrlInput) userCctvUrlInput.value = item.stream_url;
    if (userCctvCoordInput) userCctvCoordInput.value = `${item.lat.toFixed(6)}, ${item.lng.toFixed(6)}`;
    if (userCctvSaveBtn) userCctvSaveBtn.textContent = t('js.userCctv.save');
    openMenuSection('custom-cctv');
}

function renderUserCctvs() {
    if (!userCctvList) return;
    const items = loadUserCctvs();
    userCctvList.innerHTML = '';
    userCctvList.hidden = items.length === 0;
    items.forEach((item) => {
        const row = document.createElement('div');
        row.className = 'saved-location-row user-cctv-row';
        row.innerHTML = `
            <button type="button" class="saved-location-go">
                <strong></strong>
                <small></small>
            </button>
            <button type="button" class="saved-location-rename user-cctv-submit" title="${t('js.userCctv.submitTitle')}" aria-label="${t('js.userCctv.submitTitle')}">審</button>
            <button type="button" class="saved-location-rename user-cctv-edit" title="${t('js.userCctv.editTitle')}" aria-label="${t('js.userCctv.editTitle')}">改</button>
            <button type="button" class="saved-location-delete" title="${t('js.userCctv.deleteTitle')}" aria-label="${t('js.userCctv.deleteTitle')}">×</button>
        `;
        row.querySelector('strong').textContent = item.name;
        row.querySelector('small').textContent = `${item.lat.toFixed(5)}, ${item.lng.toFixed(5)}`;
        row.querySelector('.saved-location-go')?.addEventListener('click', () => {
            map.setView([item.lat, item.lng], Math.max(map.getZoom(), 16));
            locationStatus.textContent = t('js.userCctv.moved', { name: item.name });
            scheduleLoadVehicles(80);
        });
        row.querySelector('.user-cctv-submit')?.addEventListener('click', () => submitUserCctvForReview(item));
        row.querySelector('.user-cctv-edit')?.addEventListener('click', () => fillUserCctvEditor(item));
        row.querySelector('.saved-location-delete')?.addEventListener('click', async () => {
            const ok = await appConfirm({
                title: t('js.userCctv.deleteConfirmTitle'),
                text: item.name,
                confirmButtonText: t('js.monitor.deleteBtn'),
                icon: 'warning',
            });
            if (!ok) return;
            saveUserCctvs(loadUserCctvs().filter((existing) => existing.id !== item.id));
            renderUserCctvs();
            lastLoadKey = '';
            scheduleLoadVehicles(80);
        });
        userCctvList.appendChild(row);
    });
}

async function submitUserCctvForReview(item) {
    const ok = await appConfirm({
        title: t('js.userCctv.submitConfirmTitle'),
        text: t('js.userCctv.submitConfirmText', { name: item.name }),
        confirmButtonText: t('js.userCctv.submitBtn'),
    });
    if (!ok) return;
    try {
        locationStatus.textContent = t('js.userCctv.submitting');
        const response = await fetch('api/cctv_submit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                name: item.name,
                lat: item.lat,
                lng: item.lng,
                stream_url: item.stream_url,
                direction: item.direction || t('js.userCctv.submitDir'),
                page_url: window.location.href,
            }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || t('js.userCctv.submitFail'));
        }
        locationStatus.textContent = payload.message || t('js.userCctv.submitted');
    } catch (error) {
        locationStatus.textContent = error.message || t('js.userCctv.submitFail');
    }
}

function saveUserCctvFromForm(event) {
    event?.preventDefault();
    const coord = parseLatLngText(userCctvCoordInput?.value || '');
    const url = safeHttpUrl(userCctvUrlInput?.value || '');
    const name = String(userCctvNameInput?.value || '').trim();
    if (!name) {
        locationStatus.textContent = t('js.userCctv.needName');
        userCctvNameInput?.focus();
        return;
    }
    if (!url) {
        locationStatus.textContent = t('js.userCctv.needUrl');
        userCctvUrlInput?.focus();
        return;
    }
    if (!coord) {
        locationStatus.textContent = t('js.userCctv.needCoord');
        userCctvCoordInput?.focus();
        return;
    }
    const items = loadUserCctvs();
    const editingId = userCctvIdInput?.value || '';
    const next = items.filter((item) => item.id !== editingId);
    next.unshift({
        id: editingId || `user-cctv-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        name,
        lat: coord.lat,
        lng: coord.lng,
        stream_url: url,
        direction: t('js.userCctv.customDir'),
        saved_at: new Date().toISOString(),
    });
    saveUserCctvs(next);
    renderUserCctvs();
    clearUserCctvEditor();
    openMenuSection('custom-cctv');
    enableUserCustomCctvVisibility();
    map.setView([coord.lat, coord.lng], Math.max(map.getZoom(), 16));
    lastLoadKey = '';
    scheduleLoadVehicles(80);
    locationStatus.textContent = t('js.userCctv.saved', { name });
}

function exportUserCctvs() {
    const items = loadUserCctvs();
    const payload = {
        type: 'open-live-map-user-cctv',
        version: 1,
        exported_at: new Date().toISOString(),
        cameras: items,
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `open-live-map-cameras-${new Date().toISOString().slice(0, 10)}.json`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    locationStatus.textContent = t('js.userCctv.exported', { n: items.length });
}

async function importUserCctvs(file) {
    if (!file) return;
    try {
        const text = await file.text();
        const payload = JSON.parse(text);
        const incoming = Array.isArray(payload) ? payload : (Array.isArray(payload.cameras) ? payload.cameras : []);
        const clean = incoming.map(sanitizeUserCctv).filter(Boolean);
        if (clean.length === 0) {
            throw new Error(t('js.userCctv.importEmpty'));
        }
        const existing = loadUserCctvs();
        const byId = new Map(existing.map((item) => [item.id, item]));
        clean.forEach((item) => {
            byId.set(item.id, { ...item, saved_at: item.saved_at || new Date().toISOString() });
        });
        saveUserCctvs(Array.from(byId.values()));
        renderUserCctvs();
        openMenuSection('custom-cctv');
        enableUserCustomCctvVisibility();
        lastLoadKey = '';
        scheduleLoadVehicles(80);
        locationStatus.textContent = t('js.userCctv.imported', { n: clean.length });
    } catch (error) {
        locationStatus.textContent = error.message || t('js.userCctv.importFail');
    } finally {
        if (userCctvImportFile) userCctvImportFile.value = '';
    }
}

function openMenuSection(sectionKey) {
    const section = document.querySelector(`.menu-section[data-menu-section="${sectionKey}"]`);
    if (!section) return;
    const mobileMenu = window.matchMedia('(max-width: 920px)').matches;
    if (mobileMenu) {
        document.querySelectorAll('.menu-section[data-menu-section]').forEach((item) => {
            if (item !== section) item.open = false;
        });
    }
    section.open = true;
    try {
        const state = JSON.parse(localStorage.getItem(menuStateStorageKey) || '{}') || {};
        state[sectionKey] = true;
        localStorage.setItem(menuStateStorageKey, JSON.stringify(state));
    } catch {}
    if (!appShell.classList.contains('collapsed')) {
        section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function restoreUserPositionSnapshot(snapshot) {
    if (!snapshot) {
        userPosition = null;
        userPositionMarkerKind = 'gps';
        if (userMarker) {
            map.removeLayer(userMarker);
            userMarker = null;
        }
        return;
    }
    setUserPosition(snapshot.lat, snapshot.lng, snapshot.accuracy ?? 0, {
        preserveZoom: true,
        loadAfterSet: false,
        markerKind: snapshot.markerKind || 'gps',
    });
}

async function renameSavedLocation(locationId) {
    const locations = loadSavedLocations();
    const location = locations.find((item) => item.id === locationId);
    if (!location) return;
    const name = await appPrompt({
        title: t('js.place.renameTitle'),
        inputLabel: `${location.lat.toFixed(6)}, ${location.lng.toFixed(6)}`,
        inputValue: location.name,
        inputPlaceholder: t('js.place.namePh'),
        confirmButtonText: t('js.place.saveName'),
        cancelButtonText: t('js.confirm.cancel'),
    });
    if (!name || name === location.name) return;
    const next = locations
        .filter((item) => item.id === location.id || item.name !== name)
        .map((item) => (item.id === location.id ? { ...item, name, saved_at: new Date().toISOString() } : item));
    saveSavedLocations(next);
    renderSavedLocations();
    locationStatus.textContent = t('js.place.renamed', { name });
}

function renderSavedLocations() {
    if (!savedLocationList) return;
    const locations = loadSavedLocations();
    savedLocationList.innerHTML = '';
    savedLocationList.hidden = locations.length === 0;
    locations.forEach((location) => {
        const row = document.createElement('div');
        row.className = 'saved-location-row';
        row.innerHTML = `
            <button type="button" class="saved-location-go">
                <strong></strong>
                <small></small>
            </button>
            <button type="button" class="saved-location-rename" title="${t('js.place.renameAria')}" aria-label="${t('js.place.renameAria')}">改</button>
            <button type="button" class="saved-location-delete" title="${t('js.place.deleteAria')}" aria-label="${t('js.place.deleteAria')}">×</button>
        `;
        const go = row.querySelector('.saved-location-go');
        const title = row.querySelector('strong');
        const meta = row.querySelector('small');
        if (title) title.textContent = location.name;
        if (meta) meta.textContent = `${location.lat.toFixed(5)}, ${location.lng.toFixed(5)}`;
        go?.addEventListener('click', () => {
            setBookmarkMarker(location.lat, location.lng, location.name, t('js.place.savedLabel'));
            map.setView([location.lat, location.lng], Math.max(map.getZoom(), 16));
            locationStatus.textContent = t('js.place.moved', { name: location.name });
            scheduleLoadVehicles(120);
        });
        row.querySelector('.saved-location-rename')?.addEventListener('click', () => renameSavedLocation(location.id));
        row.querySelector('.saved-location-delete')?.addEventListener('click', () => {
            saveSavedLocations(loadSavedLocations().filter((item) => item.id !== location.id));
            renderSavedLocations();
        });
        savedLocationList.appendChild(row);
    });
}

function finishPickLocationMode() {
    pickingLocation = false;
    pickLocationBtn.classList.remove('active');
    pickLocationBtn.setAttribute('aria-pressed', 'false');
    pickMapBtn?.classList.remove('active');
    pickMapBtn?.setAttribute('aria-pressed', 'false');
    map.getContainer().classList.remove('picking-location');
}

async function setPickedLocation(latLng) {
    const previousPosition = userPosition ? {
        lat: userPosition[0],
        lng: userPosition[1],
        accuracy: userMarker?.options?.accuracy || 0,
        markerKind: userPositionMarkerKind,
    } : null;
    setUserPosition(latLng.lat, latLng.lng, 0, { preserveZoom: true, loadAfterSet: true, markerKind: 'saved-point' });
    finishPickLocationMode();
    const defaultName = t('js.place.defaultName', { time: new Date().toLocaleString(i18nJsLocale, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }) });
    const name = await appPrompt({
        title: t('js.place.nameTitle'),
        inputLabel: `${latLng.lat.toFixed(6)}, ${latLng.lng.toFixed(6)}`,
        inputValue: defaultName,
        inputPlaceholder: t('js.place.namePh'),
        confirmButtonText: t('js.place.saveBtn'),
        cancelButtonText: t('js.confirm.cancel'),
    });
    if (!name) {
        restoreUserPositionSnapshot(previousPosition);
        locationStatus.textContent = t('js.place.cancelSave');
        return;
    }
    if (saveNamedLocation(name, latLng.lat, latLng.lng)) {
        userMarker?.bindPopup(savedPointPopupHtml(name, latLng.lat, latLng.lng)).openPopup();
        openMenuSection('saved-locations');
        locationStatus.textContent = t('js.place.saved', { name });
    }
}

function setUserPosition(lat, lng, accuracy, options = {}) {
    userPosition = [lat, lng];
    const markerKind = options.markerKind || (options.isGps ? 'gps' : userPositionMarkerKind);
    userPositionMarkerKind = markerKind;
    if (options.isGps) {
        currentGpsPosition = [lat, lng];
    }

    if (!userMarker) {
        userMarker = L.marker(userPosition, { icon: userPositionIcon(markerKind), zIndexOffset: 1000 }).addTo(map);
    } else {
        userMarker.setLatLng(userPosition);
        userMarker.setIcon(userPositionIcon(markerKind));
    }
    if (markerKind === 'saved-point' && options.markerTitle) {
        userMarker.bindPopup(savedPointPopupHtml(options.markerTitle, lat, lng));
    } else if (markerKind === 'gps') {
        userMarker.closePopup();
        userMarker.unbindPopup();
    }

    locationStatus.textContent = markerKind === 'saved-point'
        ? t('js.place.picked')
        : t('js.locate.accuracy', { m: Math.round(accuracy || 0) });
    render();
    if (!options.preserveZoom) {
        map.setView(userPosition, 15);
    }
    if (options.loadAfterSet) {
        scheduleLoadVehicles(120);
    }
}

function locateUser(options = {}) {
    if (!navigator.geolocation) {
        locationStatus.textContent = t('js.locate.unsupported');
        if (options.fallbackLoad) {
            loadVehicles().catch((error) => {
                locationStatus.textContent = error.message;
            });
        }
        return;
    }

    locationStatus.textContent = options.auto ? t('js.locate.auto') : t('js.locate.manual');
    navigator.geolocation.getCurrentPosition(
        (position) => {
            setUserPosition(
                position.coords.latitude,
                position.coords.longitude,
                position.coords.accuracy,
                { loadAfterSet: true, isGps: true },
            );
        },
        (error) => {
            locationStatus.textContent = options.auto ? t('js.locate.autoFail', { error: error.message }) : t('js.locate.fail', { error: error.message });
            if (options.fallbackLoad) {
                loadVehicles().catch((loadError) => {
                    locationStatus.textContent = loadError.message;
                });
            }
        },
        {
            enableHighAccuracy: true,
            timeout: 12000,
            maximumAge: 60000,
        },
    );
}

function setPlaceSearchStatus(message) {
    if (placeSearchStatus) placeSearchStatus.textContent = message;
}

function clearPlaceResults() {
    if (!placeSearchResults) return;
    placeSearchResults.innerHTML = '';
    placeSearchResults.hidden = true;
}

function setBookmarkMarker(lat, lng, name, displayName = '') {
    const icon = displayName === t('js.place.savedLabel') ? savedPointIcon() : new L.Icon.Default();
    if (!placeSearchMarker) {
        placeSearchMarker = L.marker([lat, lng], { icon, zIndexOffset: 900 }).addTo(map);
    } else {
        placeSearchMarker.setLatLng([lat, lng]).addTo(map);
        placeSearchMarker.setIcon(icon);
    }
    const popup = displayName === t('js.place.savedLabel')
        ? savedPointPopupHtml(name, lat, lng, displayName)
        : `<strong>${escapeHtml(name)}</strong>${displayName ? `<br><small>${escapeHtml(displayName)}</small>` : ''}`;
    placeSearchMarker.bindPopup(popup).openPopup();
}

function focusPlaceResult(result) {
    const lat = Number(result?.lat);
    const lng = Number(result?.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    const name = result.name || t('js.search.place');
    const displayName = result.display_name || name;
    const bbox = Array.isArray(result.bbox) ? result.bbox.map(Number) : [];
    if (bbox.length === 4 && bbox.every(Number.isFinite) && Math.abs(bbox[0] - bbox[2]) < 2 && Math.abs(bbox[1] - bbox[3]) < 2) {
        map.fitBounds([[bbox[0], bbox[1]], [bbox[2], bbox[3]]], { padding: [40, 40], maxZoom: 17 });
    } else {
        map.setView([lat, lng], 16);
    }

    setBookmarkMarker(lat, lng, name, displayName);
    setPlaceSearchStatus(t('js.search.moved', { name }));
    locationStatus.textContent = t('js.search.movedStatus', { name });
    scheduleLoadVehicles(180);
}

function renderPlaceResults(results) {
    if (!placeSearchResults) return;
    placeSearchResults.innerHTML = '';
    if (!Array.isArray(results) || results.length === 0) {
        placeSearchResults.hidden = true;
        return;
    }
    results.forEach((result, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'place-result';
        button.innerHTML = `
            <strong>${escapeHtml(result.name || t('js.search.resultN', { n: index + 1 }))}</strong>
            <small>${escapeHtml(result.display_name || '')}</small>
        `;
        button.addEventListener('click', () => focusPlaceResult(result));
        placeSearchResults.appendChild(button);
    });
    placeSearchResults.hidden = false;
}

async function searchPlace(event) {
    event?.preventDefault();
    const query = (placeSearchInput?.value || '').trim();
    if (query.length < 2) {
        setPlaceSearchStatus(t('js.search.minChars'));
        clearPlaceResults();
        return;
    }
    setPlaceSearchStatus(t('js.search.working'));
    clearPlaceResults();
    try {
        const center = map.getCenter();
        const params = new URLSearchParams({
            q: query,
            lat: center.lat.toFixed(6),
            lng: center.lng.toFixed(6),
            ts: String(Date.now()),
        });
        const response = await fetch(`api/geocode.php?${params.toString()}`, { cache: 'no-store' });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.ok === false) {
            throw new Error(payload.message || t('js.search.failHttp', { status: response.status }));
        }
        const results = Array.isArray(payload.results) ? payload.results : [];
        if (results.length === 0) {
            setPlaceSearchStatus(t('js.search.none'));
            return;
        }
        setPlaceSearchStatus(t('js.search.found', { n: results.length }));
        renderPlaceResults(results);
        focusPlaceResult(results[0]);
    } catch (error) {
        setPlaceSearchStatus(error.message || t('js.search.fail'));
    }
}

function togglePickLocation() {
    pickingLocation = !pickingLocation;
    if (pickingLocation && measuringDistance) {
        setMeasureMode(false);
    }
    if (pickingLocation && streetViewPicking) {
        setStreetViewMode(false, { silent: true });
    }
    pickLocationBtn.classList.toggle('active', pickingLocation);
    pickLocationBtn.setAttribute('aria-pressed', String(pickingLocation));
    pickMapBtn?.classList.toggle('active', pickingLocation);
    pickMapBtn?.setAttribute('aria-pressed', String(pickingLocation));
    map.getContainer().classList.toggle('picking-location', pickingLocation);
    locationStatus.textContent = pickingLocation ? t('js.pick.on') : t('js.pick.off');
}

function fitVisible() {
    const bounds = [];
    sortedVisibleVehicles().forEach((vehicle) => bounds.push([vehicle.lat, vehicle.lng]));
    if (userPosition) bounds.push(userPosition);
    if (bounds.length === 0) return;
    fitBtn?.classList.add('active');
    fitBtn?.setAttribute('aria-pressed', 'true');
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
    window.setTimeout(() => {
        fitBtn?.classList.remove('active');
        fitBtn?.setAttribute('aria-pressed', 'false');
    }, 900);
}

function setPanelCollapsed(collapsed) {
    appShell.classList.toggle('collapsed', collapsed);
    const expanded = !appShell.classList.contains('collapsed');
    panelToggle?.setAttribute('aria-expanded', String(expanded));
    togglePanelBtn?.setAttribute('aria-expanded', String(expanded));
    panelPeekBtn?.setAttribute('aria-expanded', String(expanded));
    panelPeekBtn?.setAttribute('aria-label', expanded ? t('js.menu.expanded') : t('js.menu.expand'));
    panelPeekBtn?.setAttribute('title', expanded ? t('js.menu.expanded') : t('js.menu.expand'));
    applyMonitorDockLayout();
    setTimeout(() => {
        map.invalidateSize();
        applyMonitorDockLayout();
    }, 200);
}

function togglePanel() {
    setPanelCollapsed(!appShell.classList.contains('collapsed'));
}

function initMenuSections() {
    const sections = Array.from(document.querySelectorAll('.menu-section[data-menu-section]'));
    if (sections.length === 0) return;
    const mobileMenu = window.matchMedia('(max-width: 920px)');
    let bulkMenuToggle = false;
    const saveState = () => {
        const next = {};
        sections.forEach((item) => {
            next[item.dataset.menuSection] = item.open;
        });
        localStorage.setItem(menuStateStorageKey, JSON.stringify(next));
    };
    const updateExpandToggle = () => {
        if (!menuExpandToggle) return;
        const allOpen = sections.every((section) => section.open);
        menuExpandToggle.classList.toggle('is-collapse', allOpen);
        menuExpandToggle.title = allOpen ? t('js.menu.collapseAll') : t('js.menu.expandAll');
        menuExpandToggle.setAttribute('aria-label', allOpen ? t('js.menu.collapseAllAria') : t('js.menu.expandAllAria'));
        menuExpandToggle.setAttribute('aria-expanded', String(allOpen));
    };
    let saved = {};
    try {
        saved = JSON.parse(localStorage.getItem(menuStateStorageKey) || '{}') || {};
    } catch {
        saved = {};
    }
    sections.forEach((section) => {
        const key = section.dataset.menuSection;
        if (Object.prototype.hasOwnProperty.call(saved, key)) {
            section.open = Boolean(saved[key]);
        }
    });
    if (mobileMenu.matches) {
        const opened = sections.filter((section) => section.open);
        opened.slice(1).forEach((section) => {
            section.open = false;
        });
        if (opened.length === 0) {
            const locationSection = sections.find((section) => section.dataset.menuSection === 'location') || sections[0];
            locationSection.open = true;
        }
    }
    sections.forEach((section) => {
        section.addEventListener('toggle', () => {
            if (!bulkMenuToggle && mobileMenu.matches && section.open) {
                sections.forEach((item) => {
                    if (item !== section) item.open = false;
                });
            }
            saveState();
            updateExpandToggle();
        });
    });
    menuExpandToggle?.addEventListener('click', () => {
        const shouldOpen = !sections.every((section) => section.open);
        bulkMenuToggle = true;
        sections.forEach((section) => {
            section.open = shouldOpen;
        });
        bulkMenuToggle = false;
        saveState();
        updateExpandToggle();
    });
    updateExpandToggle();
}

locateBtn.addEventListener('click', () => locateUser());
pickLocationBtn.addEventListener('click', togglePickLocation);
placeSearchForm?.addEventListener('submit', searchPlace);
saveMonitorPresetBtn?.addEventListener('click', saveCurrentMonitorPreset);
monitorPresetName?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        saveCurrentMonitorPreset();
    }
});
userCctvForm?.addEventListener('submit', saveUserCctvFromForm);
userCctvCenterBtn?.addEventListener('click', () => {
    const center = map.getCenter();
    if (userCctvCoordInput) {
        userCctvCoordInput.value = `${center.lat.toFixed(6)}, ${center.lng.toFixed(6)}`;
        userCctvCoordInput.focus();
    }
});
userCctvClearBtn?.addEventListener('click', clearUserCctvEditor);
userCctvExportBtn?.addEventListener('click', exportUserCctvs);
userCctvImportBtn?.addEventListener('click', () => userCctvImportFile?.click());
userCctvImportFile?.addEventListener('change', () => importUserCctvs(userCctvImportFile.files?.[0]));
refreshBtn.addEventListener('click', () => loadVehicles().catch((error) => {
    locationStatus.textContent = error.message;
    updateMapLoadStatus('warning', t('js.load.fail'), error.message || t('js.load.retry'));
}));
pickMapBtn?.addEventListener('click', togglePickLocation);
refreshMapBtn?.addEventListener('click', () => loadVehicles().catch((error) => {
    locationStatus.textContent = error.message;
    updateMapLoadStatus('warning', t('js.load.fail'), error.message || t('js.load.retry'));
}));
pinAllMonitorsBtn?.addEventListener('click', () => setAllPinnedMonitors(true));
unpinAllMonitorsBtn?.addEventListener('click', () => setAllPinnedMonitors(false));
minimapToggleBtn?.addEventListener('click', () => {
    savePinnedMinimapPreference(!pinnedMinimapEnabled);
});
pinnedMinimapFitBtn?.addEventListener('click', () => fitAllPinnedMonitors());
pinnedMinimapCloseBtn?.addEventListener('click', () => {
    savePinnedMinimapPreference(false);
});
pinnedMinimapEnabled = loadPinnedMinimapPreference();
installAppBtn?.addEventListener('click', async () => {
    if (!deferredInstallPrompt) {
        installAppBtn.hidden = true;
        return;
    }
    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice.catch(() => null);
    deferredInstallPrompt = null;
    installAppBtn.hidden = true;
});
basemapBtn?.addEventListener('click', () => {
    if (!basemapMenu) return;
    basemapMenu.hidden = !basemapMenu.hidden;
    basemapBtn.classList.toggle('menu-open', !basemapMenu.hidden);
    basemapBtn.setAttribute('aria-expanded', String(!basemapMenu.hidden));
});
document.addEventListener('click', (event) => {
    if (!basemapMenu || basemapMenu.hidden) return;
    if (event.target.closest('#basemapMenu') || event.target.closest('#basemapBtn')) return;
    basemapMenu.hidden = true;
    basemapBtn?.classList.remove('menu-open');
    basemapBtn?.setAttribute('aria-expanded', 'false');
});
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (basemapMenu && !basemapMenu.hidden) {
        basemapMenu.hidden = true;
        basemapBtn?.classList.remove('menu-open');
        basemapBtn?.setAttribute('aria-expanded', 'false');
        return;
    }
    if (streetViewPicking || (streetViewPanel && !streetViewPanel.hidden)) {
        if (streetViewPicking) setStreetViewMode(false);
        else closeStreetViewPanel();
    }
});
zoomUserBtn.addEventListener('click', () => {
    if (currentGpsPosition) {
        map.setView(currentGpsPosition, 16);
    }
    locateUser({ fallbackLoad: false });
});
measureBtn.addEventListener('click', () => {
    if (measuringDistance || measurePoints.length > 0) {
        clearMeasure();
        setMeasureMode(false);
        return;
    }
    setMeasureMode(true);
});
clearMeasureBtn?.addEventListener('click', () => {
    clearMeasure();
    setMeasureMode(false);
});
streetViewBtn?.addEventListener('click', () => {
    if (!mapillaryEnabled) return;
    setStreetViewMode(!streetViewPicking);
});
streetViewCloseBtn?.addEventListener('click', () => {
    setStreetViewMode(false);
});
streetViewMaxBtn?.addEventListener('click', () => {
    setStreetViewMaximized(!streetViewMaximized);
});
streetViewResizeHandle?.addEventListener('pointerdown', beginStreetViewResize);
togglePanelBtn.addEventListener('click', togglePanel);
panelToggle.addEventListener('click', togglePanel);
panelPeekBtn?.addEventListener('click', () => setPanelCollapsed(false));
fitBtn.addEventListener('click', fitVisible);
citySelect?.addEventListener('change', () => {
    const area = cityAreas[citySelect.value];
    if (area) {
        map.setView(area.center, 13);
    }
    locationStatus.textContent = t('js.city.moved');
    scheduleLoadVehicles(120);
});

map.on('moveend zoomend', () => {
    scheduleRender();
    syncPinnedMinimap();
    if (selectedSources().length > 0) {
        scheduleLoadVehicles();
    } else {
        window.clearTimeout(loadTimer);
        vehicles = [];
        cctvClusters = [];
        lastLoadKey = loadKey();
        render();
        if (realpriceLayerToggle?.checked) {
            updateMapLoadStatus('loading', t('js.load.readingRealprice'), t('js.load.realpriceHint'));
        } else {
            updateMapLoadStatus('idle', t('js.load.noTraffic'), t('js.load.pickLayers'));
        }
    }
    if (realpriceLayerToggle?.checked) {
        scheduleLoadRealprice();
    }
});

window.addEventListener('resize', () => {
    applyMonitorDockLayout();
    applyMonitorDockState();
    scheduleMonitorDockOverflowCheck();
});

weatherLayerToggle?.addEventListener('change', () => {
    if (legendWeatherToggle) {
        legendWeatherToggle.checked = weatherLayerToggle.checked;
    }
    saveSettings();
    renderWeatherFieldPanel();
    if (weatherLayerToggle.checked) {
        loadWeatherLayer();
    } else {
        weatherLayer?.clearLayers();
        render();
    }
});

weatherFieldToggles.forEach((toggle) => {
    toggle.addEventListener('change', () => {
        saveSettings();
        renderWeatherLayer();
    });
});

function setAllWeatherFields(enabled) {
    weatherFieldToggles.forEach((toggle) => {
        toggle.checked = enabled;
    });
    saveSettings();
    renderWeatherLayer();
}

sourceToggles.forEach((toggle) => {
    toggle.addEventListener('change', () => {
        setSourceEnabled(toggle.value, toggle.checked, { reload: true });
    });
});

legendSourceToggles.forEach((toggle) => {
    toggle.addEventListener('change', () => {
        setSourceEnabled(toggle.value, toggle.checked, { reload: true });
    });
});

legendWeatherToggle?.addEventListener('change', () => {
    if (!weatherLayerToggle) return;
    weatherLayerToggle.checked = legendWeatherToggle.checked;
    weatherLayerToggle.dispatchEvent(new Event('change'));
});

realpriceLayerToggle?.addEventListener('change', () => {
    syncRealpriceToggles(realpriceLayerToggle.checked);
    saveSettings();
    if (realpriceLayerToggle.checked) {
        loadRealpriceLayer({ updateStatus: true }).catch(() => {});
    } else if (realpriceStatusText) {
        realpriceClusters = [];
        realpriceItems = [];
        realpriceDistrictSummaries = [];
        realpriceDistrictClusters = [];
        renderRealpriceLayer();
        realpriceStatusText.textContent = t('js.realprice.needZip');
    }
});

legendRealpriceToggle?.addEventListener('change', () => {
    if (!realpriceLayerToggle) return;
    realpriceLayerToggle.checked = legendRealpriceToggle.checked;
    realpriceLayerToggle.dispatchEvent(new Event('change'));
});

[realpricePeriod, realpriceType].forEach((control) => {
    control?.addEventListener('change', () => {
        saveSettings();
        if (realpriceLayerToggle?.checked) {
            realpriceLoaded = false;
            loadRealpriceLayer({ updateStatus: true }).catch(() => {});
        }
    });
});
cctvSourceAllBtn?.addEventListener('click', () => setAllCctvSources(true));
cctvSourceNoneBtn?.addEventListener('click', () => setAllCctvSources(false));
weatherFieldAllBtn?.addEventListener('click', () => setAllWeatherFields(true));
weatherFieldNoneBtn?.addEventListener('click', () => setAllWeatherFields(false));
mapAdClose?.addEventListener('click', (event) => {
    event.stopPropagation();
    event.currentTarget.closest('.map-ad-slot')?.remove();
});

staleLayerToggle?.addEventListener('change', () => setShowStale(staleLayerToggle.checked));
stalePanelToggle?.addEventListener('change', () => setShowStale(stalePanelToggle.checked));

buildBasemapMenu();
applySavedSettings();
syncLegendToggles();
renderWeatherFieldPanel();
renderRealpricePanel();
setPanelCollapsed(appShell.classList.contains('collapsed'));
initMenuSections();
renderSavedLocations();
renderUserCctvs();
renderMonitorPresets();
updateMonitorPresetCurrent();
setMonitorWindowSize(monitorWindowSize().width);
applyMonitorDockState();
if (weatherLayerToggle?.checked) {
    loadWeatherLayer();
}
if (realpriceLayerToggle?.checked) {
    loadRealpriceLayer().catch(() => {});
}

map.on('click', (event) => {
    if (measuringDistance) {
        addMeasurePoint(event.latlng);
        return;
    }
    if (pickingLocation) {
        setPickedLocation(event.latlng);
        return;
    }
    if (streetViewPicking) {
        const point = map.latLngToContainerPoint(event.latlng);
        if (isStreetViewCoverageAt(point, 5)) {
            lookupStreetViewAt(event.latlng);
        }
    }
});
map.on('mousemove', (event) => {
    if (!streetViewPicking || measuringDistance || pickingLocation) return;
    // Canvas layer usually owns hover; this covers gaps when canvas is absent.
    if (vehicleCanvasLayer) return;
    scheduleStreetViewHoverCheck(event.containerPoint);
});
map.on('mouseout', () => {
    if (streetViewHoverCoverage) setStreetViewHoverCoverage(false);
});
map.on('popupclose', () => {
    selectedVehicleId = null;
    vehicleCanvasLayer?.redraw();
});

setInterval(() => loadVehicles().catch(() => render()), 65000);
locateUser({ auto: true, fallbackLoad: true });
