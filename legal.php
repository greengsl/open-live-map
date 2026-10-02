<?php
declare(strict_types=1);

$siteName = 'Open Live Map';
$page = (string) ($_GET['page'] ?? 'privacy');
$pages = [
    'privacy' => [
        'title' => '隱私權政策',
        'eyebrow' => 'PRIVACY',
        'body' => [
            '本網站提供公開資料地圖、監視器點位、氣象與交通相關資訊查詢。使用定位功能時，瀏覽器可能會詢問是否允許取得目前位置；位置資料主要用於地圖定位與距離計算。',
            '自訂定位點、監控記錄、圖層偏好等設定會優先儲存在你的瀏覽器本機。若未來加入會員或雲端同步，會另行標示資料保存方式。',
            '若網站啟用第三方廣告、分析或贊助服務，該服務可能依其政策使用 Cookie、裝置資訊或瀏覽資料。未啟用相關設定時，本網站不會載入該第三方廣告腳本。',
        ],
    ],
    'terms' => [
        'title' => '服務條款與免責聲明',
        'eyebrow' => 'TERMS',
        'body' => [
            '本網站整合公開資料與第三方影像來源，提供查詢、監控與地圖視覺化輔助。資料可能延遲、缺漏、異常或來源暫停提供。',
            '交通、天氣、監視器、環保車等資訊僅供參考，不應作為唯一決策依據。實際狀況請以主管機關、現場指示或原始資料來源公告為準。',
            '使用者自行新增的監視器、地點或監控記錄，應確認來源合法且不侵犯他人權利。本網站可基於維運、安全或合法性移除不適當資料。',
        ],
    ],
    'sources' => [
        'title' => '資料來源與授權聲明',
        'eyebrow' => 'SOURCES',
        'body' => [
            '本網站可能使用政府開放資料、地方政府公開資訊、公開監視器頁面、氣象資料與使用者自行新增資料。各資料來源之權利、更新頻率與可用性仍以原提供單位公告為準。',
            '公開資料之加值應用會盡量保留來源標示。資料若包含機關名稱、平台名稱或商標，僅作來源識別，不代表該單位為本網站背書。',
            '若你是資料或影像權利人，認為本站呈現方式需要調整，請由網站管理者提供的聯絡方式通知。',
        ],
    ],
];

if (!isset($pages[$page])) {
    $page = 'privacy';
}
$current = $pages[$page];
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17212b">
    <title><?= htmlspecialchars($current['title'] . ' - ' . $siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="assets/images/favicon-32.png" type="image/png" sizes="32x32">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="legal-page">
    <main class="legal-shell">
        <a class="legal-back" href="./">返回地圖</a>
        <section class="legal-card">
            <small><?= htmlspecialchars($current['eyebrow'], ENT_QUOTES, 'UTF-8') ?></small>
            <h1><?= htmlspecialchars($current['title'], ENT_QUOTES, 'UTF-8') ?></h1>
            <?php foreach ($current['body'] as $paragraph): ?>
            <p><?= htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
            <nav class="legal-tabs" aria-label="網站政策">
                <a href="legal.php?page=privacy">隱私權</a>
                <a href="legal.php?page=terms">服務條款</a>
                <a href="legal.php?page=sources">資料來源</a>
            </nav>
        </section>
    </main>
</body>
</html>
