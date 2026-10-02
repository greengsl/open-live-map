## Recent Change: 2026-10-02

接上 TGOS 批次門牌比對（匯出／匯入）作為本機門牌後的備援。

- 新 `lib/tgos.php`：匯出官方格式 CSV（每日上限約 1 萬）、匯入結果寫入 `geocode-cache`（provider=`tgos`）、選用 QueryAddr 即時 API
- `admin/index.php`：實價分頁新增 TGOS 匯出／匯入區塊；覆蓋表新增 TGOS 欄
- `config.example.php`：可選 `tgos_app_id` / `tgos_api_key`（全國門牌定位，與批次 KEY 不同）
- 申請頁：https://www.tgos.tw/tgos/Addr

Backup: `backup/open-live-map-20261002-105434-before-tgos-batch.zip`

## Recent Change: 2026-10-01

Added local open-data doorplate geocoding to replace Nominatim as primary locator.

Sources (auto-download):

- 臺北市：https://data.gov.tw/dataset/155472
- 新北市：https://data.gov.tw/dataset/168887
- 臺南市：https://data.gov.tw/dataset/120044

Manual upload supported for 桃園／臺中／高雄 when portals require login or Google Drive.

Changed:

- `lib/doorplate.php`（新）
  - Download CSV、TWD97→WGS84、串流比對實價地址、寫入 `geocode-cache.json`
- `admin/index.php`
  - 新增下載／上傳／本機比對操作；Nominatim 改標為備援
- 已下載臺南門牌 CSV（約 67MB）並本機比對寫入約 474 筆門牌座標（掃描約 84 萬列，約 13 秒）

Backup:

- `backup/open-live-map-20261001-095629-before-doorplate-local-geocode.zip`

## Recent Change: 2026-10-01

Fixed realprice geocode poisoning that stacked many deals on one district centroid.

Evidence:

- Index addresses are real door numbers (e.g. `桃園市桃園區同德七街39號`).
- Geocode cache had ~9k+ entries where those door addresses were stored with query=`桃園市桃園區` at the same district centroid (~24.9939, 121.3017), near 桃園市政府警察局.
- Map then showed many different 萬/坪 labels jittered on one point.

Changed:

- `lib/realprice.php`
  - Stop writing district fallback coordinates under address keys.
  - Add `realpriceRepairPoisonedGeocodeCache()` and district-summary query output.
  - Door markers only for true address/manual hits; district-only deals become one summary marker per district.
- `api/realprice.php` / `assets/js/main.js` / `assets/css/style.css`
  - Render `district_summaries` as a single dashed aggregate marker.
- `admin/index.php`
  - Add「清理誤標座標並重建佇列」action.
- Cache repaired in place: removed ~12.8k poisoned address→district entries; kept true door hits + shared district keys.

Backup created before this change:

- `backup/open-live-map-20261001-094350-before-realprice-geocode-poison-fix.zip`

Verification:

- `node --check assets/js/main.js` passed.
- Live API around 桃園區 no longer returns dozens of individual district-jitter points at the police-station centroid.

## Recent Change: 2026-10-01

Improved realprice map visibility after geocode precision guard emptied the layer.

Root cause:

- Backend dropped all `precision=district` hits from `items`, while the frontend already supported district markers (jitter + warning).
- Most geocode cache entries were reclassified as district, so zoom ≥ 14 showed almost no points.
- `cache/data-sources.json` had `realprice.enabled=false`, hiding the layer toggle.
- `loadRealpriceLayer` ignored new requests while loading, so panning could stick on a stale extent.

Changed:

- `lib/realprice.php`
  - Include district-approximate points in query results.
  - Prefer address/manual points in the display quota, then fill with district.
  - Return `address_*` / `district_*` / `missing_geocode_count` stats and `cluster_hide_zoom_min`.
- `api/realprice.php`
  - Pass through the new stats; wrap query in try/catch with `ok:false` on failure.
- `assets/js/main.js`
  - Latest-wins request sequencing for realprice loads.
  - Hide city cluster bubbles at detail zoom when item markers exist.
  - Clearer status text for door vs district vs missing geocode.
- `cache/data-sources.json`
  - Re-enabled the realprice layer (`enabled: true`, still unchecked by default).
- `admin/index.php`
  - Clarify that batch size includes cache applies; external Nominatim cap remains 3/request.

Backup created before this change:

- `backup/open-live-map-20261001-091452-before-realprice-visibility-fix.zip`

Verification:

- `node --check assets/js/main.js` passed.
- Live `api/realprice.php` for Taoyuan-ish bounds at zoom 14 returned HTTP 200 with district items populated (previously near-empty).
- Local PHP CLI still unavailable on this Windows host.

## Recent Change: 2026-09-30

Fixed hidden stale toggle still displaying as disabled.

Changed:

- `assets/css/style.css`
  - Added a global `[hidden] { display: none !important; }` rule.
  - This prevents component display rules such as `.legend-toggle { display: inline-flex; }` and `.toggle-row { display: flex; }` from overriding the `hidden` attribute.

Backup created before this change:

- `backup/open-live-map-20260930-134059-before-hidden-attribute-css-fix.zip`

Verification:

- `https://info.green.myds.me/assets/css/style.css?v=hidden-attribute-fix-20260930` returned HTTP 200 and contained the global hidden rule.
- `https://info.green.myds.me/?v=hidden-attribute-fix-20260930` returned HTTP 200.

## Recent Change: 2026-09-30

Hide stale mobile-data toggle when no freshness-filtered moving layer is enabled.

Changed:

- `index.php`
  - Added classes to the side-panel and map-legend stale controls so the front end can hide/show them together.
- `assets/js/main.js`
  - Added `freshnessFilteredSourceEnabled()` for moving layers that use stale filtering (`bus`, `garbage`, `rail`).
  - Added `updateStaleToggleVisibility()` and call it after source sync/source changes.
  - The `較舊移動資料` controls are hidden/disabled unless a related moving layer is enabled; CCTV, weather, and real-price-only views no longer show that control.

Backup created before this change:

- `backup/open-live-map-20260930-133531-before-stale-toggle-visibility.zip`

Verification:

- `node --check assets/js/main.js` passed.
- `https://info.green.myds.me/assets/js/main.js?v=stale-toggle-visibility-20260930` returned HTTP 200 and contained the new visibility functions.
- `https://info.green.myds.me/?v=stale-toggle-visibility-20260930` returned HTTP 200 and contained `stale-toggle-row` / `stale-legend-toggle`.

## Recent Change: 2026-09-30

Fixed monitor dock scrollbar after transparent hitbox change.

Changed:

- `assets/css/style.css`
  - Restored `pointer-events: auto` on `.cctv-monitor-strip` so the scrollbar and wheel scrolling work.
  - Kept the dock shell `pointer-events: none`.
  - Added auto-height rules for non-overflow monitor docks so one/few monitor cards do not leave a large transparent hitbox over the map.
- `assets/js/main.js`
  - Updated monitor overflow detection to compare strip content height against available dock height, so multi-camera docks still get `can-resize` and remain scrollable.

Backups created before this change:

- `backup/open-live-map-20260930-120121-before-monitor-scrollbar-fix.zip`
- `backup/open-live-map-20260930-120201-before-monitor-overflow-detect-fix.zip`

Verification:

- `node --check assets/js/main.js` passed.
- `https://info.green.myds.me/assets/css/style.css?v=monitor-scrollbar-fix-20260930b` returned HTTP 200 and contained the auto-height/pointer-event rules.
- `https://info.green.myds.me/assets/js/main.js?v=monitor-overflow-detect-20260930` returned HTTP 200 and contained `availableStripHeight`.
- `https://info.green.myds.me/` returned HTTP 200.

## Recent Change: 2026-09-30

Fixed monitor dock transparent hitbox blocking map clicks.

Changed:

- `assets/css/style.css`
  - `.cctv-monitor-dock` and `.cctv-monitor-strip` now use `pointer-events: none`, including the mobile layout override.
  - `.cctv-monitor-controls`, `.cctv-monitor-window`, and `.cctv-monitor-resize` keep `pointer-events: auto`.
  - This lets map markers under the empty lower part of the monitor dock be clicked, while the visible monitor controls/cards remain interactive.

Backup created before this change:

- `backup/open-live-map-20260930-115853-before-monitor-dock-hitbox-fix.zip`

Verification:

- `https://info.green.myds.me/assets/css/style.css?v=monitor-dock-hitbox-20260930` returned HTTP 200 and contained the updated pointer-event rules.
- `https://info.green.myds.me/` returned HTTP 200 and referenced `assets/css/style.css`.

## Recent Change: 2026-09-30

Enabled AdSense site verification script.

Changed:

- `config/monetization.php`
  - Set AdSense `enabled` to `true`.
  - Set AdSense `client` to `ca-pub-3959446002615528`.
  - Left `sidebar` and `map` slot IDs blank, so the page loads the AdSense verification/auto-ads script without adding fixed ad units yet.

Backup created before this change:

- `backup/open-live-map-20260930-152641-before-adsense-client-enable.zip`

Verification:

- `https://gslai.win/` returned HTTP 200 and included `ca-pub-3959446002615528` plus the Google AdSense script URL.
- `https://www.gslai.win/` returned HTTP 200 and included `ca-pub-3959446002615528` plus the Google AdSense script URL.
- `https://gslai.win/ads.txt` currently returns 404; add the exact AdSense-provided ads.txt line when Google shows it.
- Local `php -l` was not available because this Windows environment has no `php` command in PATH.

## Recent Change: 2026-09-30

Added Google AdSense ads.txt.

Changed:

- `ads.txt`
  - Added `google.com, pub-3959446002615528, DIRECT, f08c47fec0942fa0`.

Backup created before this change:

- `backup/open-live-map-20260930-153800-before-ads-txt.zip`

Verification:

- `https://gslai.win/ads.txt?check=202609301538` returned HTTP 200 with the expected Google AdSense line.
- `https://www.gslai.win/ads.txt?check=202609301538` returned HTTP 200 with the expected Google AdSense line.

## Recent Change: 2026-10-02

Added busy feedback for the real-price coverage recalculation button.

Changed:

- `admin/index.php`
  - Added `js-busy-submit` to the `refresh_realprice_coverage` form.
  - Added an inline `form-busy-status` live status message next to the button.
  - Added a generic submit handler before page-specific early returns so the button immediately changes to `統計中…` and shows `正在重新統計覆蓋，請稍候…` while the synchronous request runs.
- `assets/css/admin.css`
  - Styled `.form-busy-status` as a compact yellow status pill.

Backup created before this change:

- `backup/open-live-map-20261002-114957-before-realprice-coverage-busy-ui.zip`

Verification:

- `D:\xampp\php\php.exe -n -l admin\index.php` reported no syntax errors.
- Static checks found the new `js-busy-submit`, `form-busy-status`, and busy labels in `admin/index.php`.
