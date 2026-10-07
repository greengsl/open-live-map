# Open Live Map

Live demo: **[https://gslai.win/](https://gslai.win/)**

A Taiwan-focused live map built with PHP + Leaflet, self-hosted (e.g. Synology Web Station). It layers open traffic CCTV, weather stations, real-estate transaction data (實價登錄), custom places, a multi-camera monitor dock, and optional Mapillary street view.

---

## English

### What you can do

- Browse live CCTV, weather, and real-price layers on one map
- Pin cameras, arrange a monitor dock, save presets
- Search places and keep custom locations
- Switch Traditional Chinese / English UI
- Turn on Mapillary street view when coverage is available
- Run an admin console for imports, appearance, and site settings

### Live demo

- Map: [https://gslai.win/](https://gslai.win/)

### Self-host (short)

1. Copy the project to your PHP 8.2 web root (`curl` enabled; Nginx/Apache index → `index.php`).
2. Copy `config.example.php` → `config.local.php` and fill in local settings.
3. Copy any needed `config/*.example.php` templates the same way (do not commit files that contain tokens or passwords).
4. Open the site; use `/admin/` after you set an admin password in local config.

`cache/` holds runtime data and is not part of the git tree.

### Data sources (overview)

| Layer | Typical source |
|-------|----------------|
| CCTV | Public city / highway feeds; optional user submissions after review |
| Weather | CWA open station data |
| Real-price | MOI batch ZIPs, indexed on the server |
| Street view | Mapillary (optional; needs your own token) |

Please follow each provider’s terms and rate limits. The app is built around local caches and batch import so the map does not hammer remote APIs on every pan/zoom.

### Notes

- Keep secrets in `config.local.php` (and related local config files) — never commit them.
- Some camera streams may fail inside an iframe (codec, CORS, or hotlink rules). iOS often cannot play FLV embeds; HLS / MP4 / MJPEG or “open source page” works better.

---

## 中文

線上試用：**[https://gslai.win/](https://gslai.win/)**

以 PHP + Leaflet 自架的台灣公開資料即時地圖（可跑在 Synology Web Station）。整合交通監視器、氣象測站、實價登錄、自訂地點、多路監控清單，以及可選的 Mapillary 街景。

### 功能

- 地圖圖層：監視器、氣象、實價登錄等
- 釘選監視器、監控清單、預設組合
- 地點搜尋與自訂定位
- 繁中／英文介面
- 可選街景（Mapillary）
- 管理後台：資料匯入、外觀與網站設定

### 自架（簡要）

1. 放到 PHP 8.2 網站根目錄（需 `curl`；索引指向 `index.php`）。
2. 複製 `config.example.php` → `config.local.php` 並填入本機設定。
3. 依需要複製 `config/*.example.php`；含密鑰的檔案不要提交到 git。
4. 開啟網站；在本機設定好管理員密碼後使用 `/admin/`。

執行期資料放在 `cache/`（不在版本庫內）。

### 資料來源概要

監視器（縣市／國道公開影像）、氣象（中央氣象署開放資料）、實價登錄（內政部批次 ZIP 本機建索引）、街景（可選 Mapillary，需自備 token）。請遵守各來源條款與流量限制。

### 注意

- 密鑰只放本機設定檔，勿推上公開庫。
- 部分影像受格式／CORS／防盜連限制；iOS 較不適合 FLV 內嵌。
