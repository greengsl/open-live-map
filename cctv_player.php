<?php
declare(strict_types=1);

$src = trim((string) ($_GET['src'] ?? ''));
$open = trim((string) ($_GET['open'] ?? ''));
$title = trim((string) ($_GET['title'] ?? '即時影像'));
$allowed = $src !== '' && (
    preg_match('#^https://trafficcctv\.nantou\.gov\.tw/cctv/[0-9]{3}/[0-9]{3}\.m3u8$#', $src) === 1
    || preg_match('#^https://cctvtraffic\.tycg\.gov\.tw/hls/nvr\d+/camera\d+/live\.m3u8$#i', $src) === 1
);
if ($src !== '' && !$allowed) {
    http_response_code(400);
    $src = '';
    $title = '影像來源不允許';
}
if ($open === '' || preg_match('#^https?://#i', $open) !== 1) {
    $open = '';
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="icon" href="assets/images/favicon-32.png" type="image/png" sizes="32x32">
    <style>
        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
            overflow: hidden;
            background: #05090d;
        }
        video {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #05090d;
        }
        .message {
            display: grid;
            gap: 10px;
            place-items: center;
            align-content: center;
            width: 100%;
            height: 100%;
            padding: 12px;
            color: #eaf4f7;
            font: 14px system-ui, -apple-system, "Noto Sans TC", sans-serif;
            text-align: center;
        }
        .message a {
            color: #f2c94c;
            font-weight: 800;
            text-decoration: none;
        }
    </style>
</head>
<body>
<?php if ($src !== ''): ?>
    <video id="player" controls autoplay muted playsinline></video>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.18/dist/hls.min.js"></script>
    <script>
        const src = <?= json_encode($src, JSON_UNESCAPED_SLASHES) ?>;
        const openUrl = <?= json_encode($open, JSON_UNESCAPED_SLASHES) ?>;
        const video = document.querySelector('#player');
        function showMessage(text) {
            const link = openUrl ? `<a href="${openUrl}" target="_blank" rel="noopener">開啟來源影像</a>` : '';
            document.body.innerHTML = `<div class="message"><strong>${text}</strong>${link}</div>`;
        }
        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = src;
        } else if (window.Hls && Hls.isSupported()) {
            const hls = new Hls({ lowLatencyMode: true });
            hls.loadSource(src);
            hls.attachMedia(video);
            hls.on(Hls.Events.ERROR, (event, data) => {
                if (data?.fatal) showMessage('影像來源暫時無法內嵌播放。');
            });
        } else {
            showMessage('此瀏覽器不支援 HLS 影像。');
        }
        video.addEventListener('error', () => showMessage('影像來源暫時無法內嵌播放。'));
    </script>
<?php else: ?>
    <div class="message">
        <strong><?= h($title) ?></strong>
        <?php if ($open !== ''): ?><a href="<?= h($open) ?>" target="_blank" rel="noopener">開啟來源影像</a><?php endif; ?>
    </div>
<?php endif; ?>
</body>
</html>
