# Open Live Map — 系統文件

> 產出日期：2026-10-01  
> 專案根目錄：`Z:\info`  
> 主要部署：`https://info.green.myds.me/`  
> 廣告／品牌網域：`https://gslai.win/`、`https://www.gslai.win/`

本文件描述 Open Live Map 的架構、目錄、設定、API、資料流、快取、前端能力與營運注意事項。  
較簡短的部署說明見 `README.md`；近期改動交接見 `CURSOR_HANDOFF.md`；備份紀錄見 `backup/BACKUP_LOG.md`。

---

## 1. 系統概述

Open Live Map 是一套以 **台灣公開資料** 為主的即時地圖站台，部署於 Synology **Web Station + PHP 8.2 + Nginx**。

### 1.1 核心能力

| 能力 | 說明 |
|------|------|
| 監視器地圖 | 本機快取點位 + 自訂／投稿監視器；點擊後依協議開啟播放器 |
| 桃園環保車 | 垃圾車／回收車 GPS，短 TTL 快取 |
| 氣象測站 | 中央氣象署 CWA 公開觀測 |
| 實價登錄 | 內政部批次 ZIP → 本機索引／本機門牌／TGOS 批次／Nominatim／覆寫 |
| 使用者功能 | 自訂定位點、監控清單、本機自訂監視器、問題回報、公開投稿 |
| 管理後台 | 圖層開關、匯入、審核、實價維護、收益設定、造訪統計 |
| 變現 | Google AdSense（驗證／auto ads）+ 贊助區塊 |

### 1.2 技術棧

- **後端**：PHP 8.2（需 `curl`）、JSON 檔案儲存（無獨立資料庫）
- **前端**：Leaflet、SweetAlert2、原生 Canvas 繪製大量點位、`assets/js/main.js`
- **播放**：hls.js / flv.js / MJPEG／靜態圖／YouTube embed
- **PWA**：`manifest.webmanifest`（`service-worker.js` 目前為空檔）

### 1.3 設計原則

1. **本機快取優先**：開圖盡量讀 `cache/`，避免每次打遠端 API。
2. **導入與查詢分離**：CCTV／實價等大批資料由後台批次匯入；前端只查本機結果。
3. **機密分離**：`config.local.php` 放金鑰與後台密碼，勿公開、勿提交。
4. **改版前備份**：修改前壓縮至 `backup/`，並寫入 `backup/BACKUP_LOG.md`。

---

## 2. 目錄結構

```
Z:\info\
├── index.php                 # 地圖主頁
├── admin\index.php           # 管理後台（單檔）
├── api\                      # 前端 JSON API
├── lib\                      # 共用函式庫（匯入、設定、分析、實價）
├── assets\
│   ├── css\style.css
│   ├── js\main.js
│   ├── images\               # favicon / PWA icons
│   └── data\transit-sample.json   # TDX 失敗時樣本回退
├── config\
│   ├── data-sources.json     # 圖層種子設定
│   ├── monetization.php      # 廣告／贊助實際設定
│   └── monetization.example.php
├── config.example.php        # 本機機密範本
├── config.local.php          # 本機機密（勿公開）
├── cache\                    # 執行期快取與持久 JSON（需可寫）
├── backup\                   # 改版 ZIP + BACKUP_LOG.md
├── cctv_player.php           # HLS 播放頁
├── flv_cctv_player.php       # FLV/WSS 播放頁
├── ntpc_cctv_player.php      # 新北 CCTV 播放頁
├── cctv_image.php            # 國道／省道 snapshot、台南 MJPEG 等
├── legal.php                 # 隱私／條款／資料來源
├── ads.txt                   # AdSense 授權列
├── manifest.webmanifest
├── service-worker.js         # 目前為空
├── README.md
├── CURSOR_HANDOFF.md
└── SYSTEM.md                 # 本文件
```

非執行路徑：`#recycle\`（回收／舊檔）、`tmp-video-frames\`（暫存）。

---

## 3. 執行與請求流

```
瀏覽器 (Leaflet + main.js)
  │
  ├─ GET  api/garbage.php       → 桃園 TYOEM + cache/
  ├─ GET  api/cctv_cache.php    → tdx-cctv-*.json + custom-cctv.json
  ├─ GET  api/weather.php       → CWA + cache/
  ├─ GET  api/realprice.php     → cache/realprice/
  ├─ GET  api/geocode.php       → Nominatim
  ├─ GET/POST api/cctv_report.php
  ├─ POST api/cctv_submit.php
  └─ 開啟 cctv_*_player.php / cctv_image.php → 遠端串流／影像

管理後台 admin/index.php
  └─ lib/*_import.php、realprice、monetization、source_config
       └─ 寫入 cache/ 與 config/

api/transit.php（TDX）
  └─ 後端完整可用；現行 main.js 的 loadVehicles() 未呼叫
```

造訪時 `index.php` 會載入 `lib/analytics.php` 並呼叫 `analyticsLogVisit()`，寫入匿名日檔。

---

## 4. 設定系統

### 4.1 本機機密：`config.local.php`

範本：`config.example.php`

| 鍵 | 用途 |
|----|------|
| `tdx_client_id` / `tdx_client_secret` | 交通部 TDX OAuth（`api/transit.php`） |
| `cwa_authorization_key` | 中央氣象署授權碼（`api/weather.php`） |
| `admin_password` | 後台登入；空字串則無需登入 |

環境變數可覆寫 TDX／CWA（API 內 `getenv`）：`TDX_CLIENT_ID`、`TDX_CLIENT_SECRET` 等。

### 4.2 圖層設定

| 檔案 | 角色 |
|------|------|
| `config/data-sources.json` | 種子（預設） |
| `cache/data-sources.json` | 後台儲存後的執行期覆寫 |

合併邏輯在 `lib/source_config.php`：種子 + 快取覆寫，缺鍵補預設。

目前圖層鍵：

| 鍵 | 預設勾選 | 說明 |
|----|----------|------|
| `garbage` | true | 桃園環保車 |
| `cctv-cache` | false | 監視器點位 |
| `weather` | false | 氣象測站 |
| `realprice` | false | 實價登錄 |
| `stale` | true | 「較舊移動資料」顯示開關（見 §8.3） |

### 4.3 收益設定：`config/monetization.php`

由 `lib/monetization_config.php` 讀寫。

目前狀態（2026-09-30）：

- AdSense **enabled = true**
- client：`ca-pub-3959446002615528`
- `slots.sidebar` / `slots.map`：**空白**（頁面只載入驗證／auto-ads 腳本，不渲染固定 `<ins>` 單元）
- 贊助：`https://buymeacoffee.com/greenai`
- 聯絡：`gsl.greenai@gmail.com`

根目錄 `ads.txt`：

```text
google.com, pub-3959446002615528, DIRECT, f08c47fec0942fa0
```

已在 `gslai.win` / `www.gslai.win` 驗證可存取。

---

## 5. 入口頁與播放器

### 5.1 `index.php`

- `require`：`source_config`、`monetization_config`、`analytics`
- 注入圖層設定、AdSense 腳本（若啟用）、側欄／地圖廣告槽（有 slot 才渲染）
- 靜態資源以 `?v=filemtime` 做快取破壞

主要 UI 區塊：側欄（定位、自訂點、監控記錄、自訂監視器、搜尋、圖層、統計）、地圖工具列、CCTV monitor dock、圖例、載入狀態。

### 5.2 `admin/index.php`

- Session + `admin_password`
- Tabs：`sources` / `analytics` / `realprice` / `monetization` / `reports` / `submissions` / `cctv`
- 診斷：`/admin/?diagnose=1` → JSON（可寫路徑、計數等）
- 登出：`?logout=1`

### 5.3 `legal.php`

查詢參數 `page=`：`privacy` | `terms` | `sources`

### 5.4 CCTV 播放器

| 檔案 | 協議／用途 | 備註 |
|------|------------|------|
| `cctv_player.php` | HLS（m3u8）+ hls.js | allowlist（如南投、桃園） |
| `flv_cctv_player.php` | FLV over WSS + flv.js | 桃園／新北等 |
| `ntpc_cctv_player.php` | 新北 `device=C######` → 查 API 得 WSS | |
| `cctv_image.php` | 國道／省道 snapshot、台南 MJPEG 輪詢 | |

**iOS 注意**：不支援 FLV 內嵌；需 HLS／MP4／MJPEG，或導向來源頁。前端有 iOS FLV fallback 文案與連結。

---

## 6. API 一覽（`api/`）

共通：多數回傳 `Content-Type: application/json`，`Cache-Control: no-store`。

### 6.1 `api/garbage.php` — 桃園環保車

| 項目 | 內容 |
|------|------|
| 方法 | GET |
| 參數 | `bounds=south,west,north,east`；`diagnose` |
| 遠端 | `route.tyoem.gov.tw`（cookie + GPS 查詢） |
| 快取 | `cache/taoyuan-garbage-v1.json`（約 **20s**）；路班 `taoyuan-garbage-routes-v1.json`；cookie `tyoem-cookie.txt` |
| 回應 | `source`, `notice`, `generated_at`, `vehicles[]`, `total_count`, `filtered_count` |
| 車輛 | `kind: garbage`，含車牌、路線、`updated_at` 等 |

### 6.2 `api/cctv_cache.php` — 監視器點位

| 項目 | 內容 |
|------|------|
| 參數 | `bounds`；`zoom`；`limit`（約 100–1200，預設 800） |
| 資料 | `cache/tdx-cctv-*.json` + `cache/custom-cctv.json` |
| 行為 | 低 zoom 或點過多 → `cluster_mode` 聚合泡泡 |
| 回應 | `vehicles[]`, `clusters[]`, `total_count`, `filtered_count`, `returned_count`, `cluster_mode`, `limited`, `min_zoom`, `filtered_source_breakdown` 等 |

### 6.3 `api/weather.php` — CWA 測站

| 項目 | 內容 |
|------|------|
| 遠端 | opendata.cwa.gov.tw，資料集 `O-A0003-001` |
| 快取 | `weather-cwa-observation-v2.json`（TTL 約 **600s**）；失敗可讀較舊快取 |
| 回應 | `stations[]`：temperature / rain / humidity / wind_speed / weather / updated_at |
| 缺測 | `-99` / `-999` 等需當無效值處理（前後端已有修正） |

### 6.4 `api/realprice.php` — 實價登錄查詢

| 項目 | 內容 |
|------|------|
| 參數 | `south,west,north,east` 或 `bounds`；`period`（預設 12 月）；`type`（sale/presale/rent）；`zoom` |
| 資料 | `cache/realprice/`（索引、geocode、overrides） |
| 回應 | `ok`, `clusters`, `items`, `item_count`, `item_limited`, `address_item_count`, `district_item_count`, `missing_geocode_count`, `period`, `type`, zoom 門檻等 |
| Zoom | `item_zoom_min=14` 開始回明細點（含行政區近似）；`item_full_zoom_min=16` 提高上限；`cluster_hide_zoom_min=14` 有明細時前端隱藏縣市泡泡 |
| 精度 | `manual` / `address` 優先佔顯示名額；`district` 為近似點（前端 jitter + 警告文案） |

### 6.5 `api/geocode.php` — 地名搜尋

| 項目 | 內容 |
|------|------|
| 參數 | `q`（建議 ≥2 字）；可選 `lat`,`lng` 做距離排序 |
| 遠端 | Nominatim（`countrycodes=tw`） |
| 回應 | `results[{name, display_name, type, lat, lng, bbox, distance_from_center}]` |

### 6.6 `api/cctv_report.php` — 監視器問題回報

| 方法 | 行為 |
|------|------|
| GET | `{ ok, types }` 啟用中的回報類型 |
| POST | `type`, 相機 id／標題、描述、聯絡、座標／URL… → `{ ok, message, id }` |
| 限流 | 依 IP hash |

儲存：`cache/cctv-reports.json`；類型：`cache/cctv-report-types.json`。

### 6.7 `api/cctv_submit.php` — 公開投稿監視器

| 項目 | 內容 |
|------|------|
| 方法 | POST |
| 欄位 | `name`, `stream_url`/`url`, 座標, `direction`, `contact`, `note`… |
| 狀態 | `pending`，後台核准後寫入 `custom-cctv.json` |
| 限流 | 同 IP 約 1 小時 ≤ 8 次 |

### 6.8 `api/transit.php` — TDX 交通／CCTV 聚合（**現行 UI 未接線**）

| 項目 | 內容 |
|------|------|
| 參數 | `city` / `cities`；`kind`；`sources`（`bus,bike,rail,rail-tra,rail-metro,rail-thsr,cctv`；`none`=空）；`bounds`；`diagnose` |
| 城市 | Taipei, NewTaipei, Taoyuan, Taichung, Tainan, Kaohsiung |
| 快取 | `cache/transit-v17-*.json`，TTL 約 **90s**；限流退避約 **600s** |
| 失敗 | 舊快取或 `assets/data/transit-sample.json` |
| 現狀 | `main.js` → `loadVehicles()` 中 `transitSources` 恒為空，**不呼叫本 API**；繪製 bus/bike/rail 的程式仍保留 |

---

## 7. 函式庫（`lib/`）

| 檔案 | 職責 |
|------|------|
| `source_config.php` | 圖層設定讀寫與合併 |
| `monetization_config.php` | AdSense／贊助設定與 helpers（`adsenseEnabled`、`adsenseSlot`、`supportEnabled`） |
| `analytics.php` | 匿名造訪 JSONL（`cache/analytics/YYYY-MM-DD.jsonl`）、鹽雜湊 IP、後台報表 |
| `cctv_custom.php` | `custom-cctv.json` CRUD、正規化、來源推斷 |
| `cctv_reports.php` | 回報儲存與類型設定 |
| `cctv_submissions.php` | 投稿佇列；核准寫入自訂監視器 |
| `realprice.php` | ZIP 下載、索引、geocode 佇列、overrides、範圍查詢 |
| `freeway_cctv_import.php` | 高公局 MOTC CCTV XML → custom |
| `highway1968_import.php` | 1968services 省道影像匯入 |
| `twlive_import.php` | tw.live 國道列表匯入 |
| `gov_taiwan_import.php` | gov.tw 影音列表 + 本機 geocode（`gov-geocode.json`） |
| `tainan_cctv_import.php` | 台南 ITMS CCTV API 匯入 |

---

## 8. 前端（`assets/js/main.js`）

### 8.1 地圖與底圖

Leaflet；底圖可切換：light / standard / transport / dark / satellite（OSM、HOT、Esri 等）。

### 8.2 現行會請求的 API

在 `loadVehicles()` 中：

- 勾選環保車 → `api/garbage.php`
- 勾選監視器 → `api/cctv_cache.php`

獨立圖層／功能：

- `api/weather.php`、`api/realprice.php`
- `api/geocode.php`、`api/cctv_report.php`、`api/cctv_submit.php`

使用者本機 CCTV 會併入 `vehicles` 顯示（`userCctvVehiclesInBounds()`）。

### 8.3 「較舊移動資料」（stale）

- 以 `updated_at` 與約 **10 分鐘** 門檻區分 fresh / stale。
- 受 freshness 過濾的 kind：`bus`、`garbage`、`rail`。
- `updateStaleToggleVisibility()`：僅當相關移動圖層啟用時顯示／啟用「較舊移動資料」控件；僅 CCTV／氣象／實價時隱藏。
- CSS 全域 `[hidden] { display: none !important; }`，避免 `.legend-toggle` / `.toggle-row` 的 `display` 蓋掉 `hidden`。

> 現行 UI 圖層勾選實質主要是 `garbage` + `cctv-cache`；`bus`/`rail` 為保留邏輯。

### 8.4 監視器體驗

- 來源過濾（國道、省道、縣市、YouTube、自訂等）
- 低 zoom 聚合泡泡；足夠放大後才載入細節點
- **Monitor dock**：多視窗監看、拖曳／縮放／釘到地圖、預設清單
- Dock 空區 `pointer-events: none`，避免透明 hitbox 擋地圖點擊；控件與卡片保持可點；可捲動時恢復 strip 滾輪

### 8.5 其他互動

定位／點選定位、量測、自訂定位點、PWA 安裝提示、側欄收合與 peek 把手、設定存 **localStorage**。

### 8.6 渲染

Canvas 車輛／點位層（約有數量上限）、氣象標籤、實價聚合／明細、CCTV cluster。

---

## 9. 快取策略（`cache/`）

| 類型 | 檔案／前綴 | TTL／特性 |
|------|------------|-----------|
| Transit 回應 | `transit-v17-*.json` | ~90s；失敗可讀更舊 |
| TDX CCTV 點位 | `tdx-cctv-*.json` | 由 transit／匯入寫入；`cctv_cache` 讀取 |
| 環保車 | `taoyuan-garbage-v1.json` | ~20s |
| 氣象 | `weather-cwa-observation-v2.json` | ~600s |
| 實價 | `realprice/`（zip、index、geocode-*、overrides、metadata） | 批次持久 |
| UGC／自訂 | `custom-cctv.json`、`cctv-reports.json`、`cctv-submissions.json`、`cctv-report-types.json` | 持久 |
| 圖層覆寫 | `data-sources.json` | 後台儲存 |
| 分析 | `analytics/*.jsonl`、`salt.txt` | 按日追加 |
| gov geocode | `gov-geocode.json` | 匯入用 |

原則：短 TTL 即時層 + 長存點位／實價索引；遇限流寫標記並退避。

**部署注意**：Web Station 對 `cache/` 必須可寫；權限不足時部分 API 會落到系統暫存目錄（見 `transit.php` 邏輯）。

---

## 10. 資料來源總表

| 來源 | 用途 | 接入 |
|------|------|------|
| 桃園 TYOEM | 環保車 GPS／路班 | `api/garbage.php` |
| TDX | 公車／公共自行車／鐵道／部分 CCTV | `api/transit.php`（UI 暫未接） |
| CWA O-A0003-001 | 氣象觀測 | `api/weather.php` |
| 內政部實價 ZIP | 成交案件 | `lib/realprice.php` + admin |
| 縣市門牌開放資料 CSV | 本機門牌座標（優先於 Nominatim） | `lib/doorplate.php` + admin |
| 高公局 XML | 國道 CCTV | `freeway_cctv_import.php` |
| 1968services | 省道影像 | `highway1968_import.php` |
| tw.live | 國道直播列表 | `twlive_import.php` |
| gov.tw | 政府影音／景點 | `gov_taiwan_import.php` |
| 台南 ITMS | 市 CCTV | `tainan_cctv_import.php` |
| Nominatim | 地名搜尋 | `api/geocode.php` |
| 本機 custom | 審核後監視器 | `custom-cctv.json` |
| 瀏覽器 localStorage | 定位點／監控／設定／使用者 CCTV | `main.js` |

外部資料應遵守來源條款與合理使用（限流、快取、批次匯入）。

---

## 11. 管理後台操作（POST `action`）

| Tab | action | 說明 |
|-----|--------|------|
| 資料來源 | `save` | 各層 `enabled` / `default_checked` |
| 收益 | `save_monetization` | AdSense／贊助欄位 |
| 實價 | `download_realprice` | 下載官方 ZIP |
| 實價 | `check_realprice` | 檢查狀態 |
| 實價 | `rebuild_realprice` | 重建索引 |
| 實價 | `prepare_realprice_geocode` / `process_realprice_geocode` | 地理編碼佇列 |
| 實價 | `save_realprice_override` / `save_realprice_custom` | 座標覆寫／自訂 |
| 自訂監視器 | `save_cctv` / `delete_cctv` | CRUD |
| 回報 | `update_cctv_report` / `save_cctv_report_types` | 處理回報與類型 |
| 投稿 | `approve_cctv_submission` / `reject_cctv_submission` | 審核 |
| 匯入 | `import_twlive` | tw.live |
| 匯入 | `import_freeway_cctv` | 高公局 |
| 匯入 | `import_1968_highways` | 公路局 1968 |
| 匯入 | `import_gov_taiwan` | gov.tw |
| 匯入 | `import_tainan_cctv` | 台南 |
| 登入 | `login` | 密碼驗證 |

造訪報表由 analytics 模組依日期區間彙總（後台可選天數約 1–90）。

---

## 12. 部署清單

1. 專案置於網站根目錄（例：`Z:\info` → `https://info.green.myds.me/`）。
2. PHP Profile：**8.2**，啟用 **curl**。
3. Nginx 索引檔包含 `index.php`。
4. 確認可寫：`cache/`（及實價子目錄）。
5. 複製 `config.example.php` → `config.local.php`，填入 TDX／CWA／後台密碼。
6. （可選）調整 `config/data-sources.json`、`config/monetization.php`。
7. 後台：`https://info.green.myds.me/admin/`；診斷：`?diagnose=1`。
8. AdSense 網域若用 `gslai.win`，確保 `ads.txt` 可從該網域根路徑存取。

本機開發環境若無 `php` CLI，無法跑 `php -l`；可用線上 HTTP 與 `node --check assets/js/main.js` 做基本驗證。

---

## 13. 營運與安全

### 13.1 必守

- **不要公開或提交** `config.local.php`、`cache/analytics/salt.txt`、含個資的回報／投稿內容。
- 實價官方批次視為唯讀來源；人工座標修正放在 overrides，勿改原始 ZIP 內容語意。
- 改版前備份 ZIP 到 `backup/`，並更新 `BACKUP_LOG.md`。

### 13.2 已知限制／現況

1. **TDX 交通層**：API 與前端繪製殘碼存在，現行圖層面板**未提供** bus/bike/rail 勾選，`loadVehicles` 也不打 `transit.php`。
2. **`stale` 文案**仍提及公車；目前實際影響以環保車為主。
3. **`service-worker.js` 為空**：離線能力有限，安裝主要靠 manifest。
4. **CORS／防盜連／iframe**：部分官方影像無法內嵌，需「開啟來源」或站內轉址代理（依來源實作）。
5. **Windows 本機無 PHP CLI** 時，語法檢查改依線上與 node。

### 13.3 相關網域（文件／分析中出現過）

| 網域 | 用途 |
|------|------|
| `info.green.myds.me` | 主地圖站 |
| `gslai.win` / `www.gslai.win` | AdSense／ads.txt |
| `openlivemap.gslai.win` | 分析 ref_host 曾出現 |
| `green.myds.me` | referrer |

---

## 14. 驗證常用指令／URL

```text
# 前端語法
node --check assets/js/main.js

# 線上健康檢查（示例）
https://info.green.myds.me/
https://info.green.myds.me/admin/?diagnose=1
https://info.green.myds.me/api/weather.php?diagnose=1
https://info.green.myds.me/api/garbage.php?diagnose=1
https://info.green.myds.me/api/transit.php?diagnose=1
https://gslai.win/ads.txt
```

靜態資源驗證時可加 `?v=...` 避開 CDN／瀏覽器快取（見 `CURSOR_HANDOFF.md`）。

---

## 15. 文件維護

| 文件 | 何時更新 |
|------|----------|
| `SYSTEM.md`（本檔） | 架構、API、設定、資料流變更時 |
| `README.md` | 部署步驟或高層功能變更時 |
| `CURSOR_HANDOFF.md` | 每次可交接的具體改動 |
| `backup/BACKUP_LOG.md` | 每次建立備份 ZIP 時 |

若本文件與程式碼衝突，以程式碼與線上行為為準，並應回寫本文件。

### 16. 網站優化
以 Awwwards、Webby Awards、FWA 獲獎級網站為品質標準，完成後從排版、留白、視覺層級、色彩、動效、微互動、響應式和原創性上自檢並持續優化，直到沒有明顯可提升之處