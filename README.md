# Open Live Map

Live site: **[https://gslai.win/](https://gslai.win/)**

A real-time public-data map for Taiwan, deployed on Synology Web Station / PHP 8.2 / Nginx.

The site combines traffic CCTV, weather stations, real-price transactions (實價登錄), custom saved places, a monitor dock, on-demand Mapillary street view, and an admin console. Data comes mainly from open government sources and local caches; respect each provider’s rate limits and terms of use.

---

## English

### Features

- Live map with selectable layers (CCTV, weather, real-price, older/stale motion data)
- Pin monitors, save presets, custom CCTV, place search / saved locations
- Optional Mapillary street view (admin toggle; coverage only while street-view mode is on)
- Traditional Chinese / English UI (`?lang=` or language switch)
- Admin tools for data import, diagnostics, appearance, monetization, Mapillary

### Key paths

| Path | Role |
|------|------|
| `index.php` | Main map |
| `admin/index.php` | Admin UI |
| `api/` | JSON APIs for the frontend |
| `lib/` | Import, cache, real-price, analytics, config helpers |
| `assets/` | CSS, JS, i18n, icons |
| `config.example.php` | Config template |
| `config/*.example.php` | Feature config templates (copy locally; do not commit secrets) |
| `cache/` | Local cache / imported data (not in git) |
| `config.local.php` | Local secrets — **never publish** |

### Deploy

1. Put the project at the web root so it serves **[https://gslai.win/](https://gslai.win/)**.
2. Use PHP 8.2 with `curl` enabled.
3. Nginx index should include `index.php`.
4. Admin: [https://gslai.win/admin/](https://gslai.win/admin/)

### Data sources

- **CCTV**: public city/highway feeds and user-submitted cameras (after review)
- **Weather**: CWA open station data
- **Real-price**: MOI transaction batch ZIPs, indexed locally
- **Geocoding**: local/admin-area cache first; external geocoders only when needed
- **Street view**: Mapillary (server-side token; on-demand lookup + coverage overlay)

Prefer admin batch jobs, caching, incremental updates, and throttling over hitting remote APIs on every map move.

### Useful URLs

- Map: [https://gslai.win/](https://gslai.win/)
- Admin diagnose: [https://gslai.win/admin/?diagnose=1](https://gslai.win/admin/?diagnose=1)
- Real-price API: `api/realprice.php`
- CCTV report API: `api/cctv_report.php`

### Notes

- Do not commit `config.local.php`, `config/mapillary.php`, or `config/monetization.php`.
- Live video may be limited by browser codecs, CORS, hotlink protection, or iframe rules.
- iOS often cannot embed FLV; prefer HLS / MP4 / MJPEG or open the source page.
- Treat official real-price batches as read-only; keep manual overrides in local overlay data.

---

## 中文

公開資料即時地圖，部署於 Synology Web Station / PHP 8.2 / Nginx。

線上網站：**[https://gslai.win/](https://gslai.win/)**

整合交通監視器、氣象測站、實價登錄、自訂定位點、監控清單、Mapillary 街景與管理後台。資料以公開來源與本機快取為主；外部服務請遵守來源限制與使用條款。

### 主要檔案

- `index.php`：地圖主頁
- `admin/index.php`：管理介面
- `api/`：前端 JSON API
- `lib/`：匯入、快取、實價登錄、分析與設定
- `assets/`：樣式、腳本、i18n、圖示
- `config.local.php`：本機設定，**請勿公開**
- `config.example.php` / `config/*.example.php`：設定範本

### 部署

1. 網站根目錄對應 [https://gslai.win/](https://gslai.win/)
2. PHP 8.2，啟用 `curl`
3. Nginx 索引含 `index.php`
4. 後台：[https://gslai.win/admin/](https://gslai.win/admin/)

### 資料來源

- 監視器、氣象（CWA）、實價登錄批次、地址定位快取、Mapillary 街景（按需）
- 匯入請走後台批次與快取，避免每次開圖大量打遠端

### 注意

- 勿提交含密鑰的本機設定
- 部分即時影像受格式／CORS／防盜連限制；iOS 不適合 FLV 內嵌
- 實價登錄官方批次視為唯讀，人工修正放本機覆寫資料

修改重要流程前，建議備份到 `backup/` 並記錄於 `backup/BACKUP_LOG.md`。
