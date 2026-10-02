# Open Live Map

公開資料即時地圖，部署於 Synology Web Station / PHP 8.2 / Nginx。

目前網站整合交通監視器、氣象測站、實價登錄、使用者自訂定位點、監控清單與管理後台。資料來源以公開資料與本機快取為主；外部服務呼叫需注意來源限制與使用條款。

## 主要檔案

- `index.php`：地圖主頁。
- `admin/index.php`：管理介面。
- `api/`：前端 JSON API。
- `lib/`：資料匯入、快取、實價登錄、分析與設定輔助。
- `assets/css/style.css`：地圖與介面樣式。
- `assets/js/main.js`：地圖圖層、監控 Docker、搜尋、定位、量測與互動邏輯。
- `manifest.webmanifest`：PWA / 安裝資訊。
- `cache/`：本機快取與匯入資料。
- `backup/`：改版前備份與備份記錄。
- `config.local.php`：本機設定，請勿公開。
- `config.example.php`：設定範本。

## 部署

1. 將專案放在網站根目錄，例如 `Z:\info` 對應 `https://info.green.myds.me/`。
2. 確認 Web Station 的 PHP Profile 使用 PHP 8.2。
3. 確認 PHP 已啟用 `curl`。
4. Nginx 的索引檔需包含 `index.php`。
5. 後台請使用 `https://info.green.myds.me/admin/`。

## 資料來源

網站資料來源分成幾類：

- 監視器：官方公開 CCTV、縣市道路影像、國道/快速道路、使用者自訂監視器。
- 氣象：中央氣象署 CWA 公開測站資料。
- 實價登錄：內政部不動產成交案件實際資訊批次 ZIP，下載後建立本機索引。
- 地址定位：優先使用本機/行政區快取，必要時才呼叫外部地理編碼服務。
- 使用者資料：自訂定位點、監控記錄、監控視窗設定等存在瀏覽器或本機快取。

外部資料匯入應盡量使用後台批次、快取、差異更新與限流保護，避免每次開圖都大量呼叫遠端服務。

## 管理與驗證

- 後台診斷：`https://info.green.myds.me/admin/?diagnose=1`
- 地圖首頁：`https://info.green.myds.me/`
- 實價登錄 API：`api/realprice.php`
- 監視器回報 API：`api/cctv_report.php`

每次修改資料流程或介面前，建議先壓縮備份到 `backup/`，並把備份目的寫入 `backup/BACKUP_LOG.md`。

## 注意事項

- 不要提交或公開 `config.local.php`。
- 外部即時影像可能受瀏覽器格式、CORS、來源防盜連或 iframe 限制影響。
- iOS / iPhone 瀏覽器不支援 FLV 即時串流內嵌播放，這類來源需改由來源頁開啟或使用可支援的 HLS/MP4/MJPEG 來源。
- 實價登錄官方資料應視為原始來源；人工座標修正、隱藏或自訂紀錄應保存在本機覆寫資料，不直接改官方批次資料。
