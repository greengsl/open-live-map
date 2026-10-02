<?php
declare(strict_types=1);

$src = trim((string) ($_GET['src'] ?? ''));
$open = trim((string) ($_GET['open'] ?? ''));
$title = trim((string) ($_GET['title'] ?? '即時影像'));

$allowed = preg_match('#^wss://cctvtraffic\.tycg\.gov\.tw/flv/nvr\d+/camera\d+$#i', $src) === 1
    || preg_match('#^wss://cctvatis[1-6]\.ntpc\.gov\.tw/flv/C\d{6}$#i', $src) === 1;

if (!$allowed) {
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
        html, body { width: 100%; height: 100%; margin: 0; overflow: hidden; background: #050809; color: #dbe8ea; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .stage { position: fixed; inset: 0; display: grid; place-items: center; background: #050809; }
        video { display: block; width: 100%; height: 100%; object-fit: contain; background: #050809; }
        .message { display: grid; gap: 8px; max-width: 88%; padding: 18px; text-align: center; line-height: 1.55; }
        .message strong { color: #fff; font-size: 15px; }
        .message small { color: rgba(219, 232, 234, .74); }
        .message a { justify-self: center; margin-top: 2px; color: #f2c94c; font-weight: 800; text-decoration: none; }
        .notice { position: fixed; right: 8px; bottom: 8px; left: 8px; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 9px; border-radius: 7px; color: rgba(219, 232, 234, .86); background: rgba(5, 8, 9, .72); font-size: 12px; line-height: 1.35; pointer-events: none; }
        .notice a { flex: 0 0 auto; color: #f2c94c; font-weight: 800; text-decoration: none; pointer-events: auto; }
    </style>
</head>
<body>
    <main class="stage">
        <?php if ($src !== ''): ?>
            <video id="video" muted autoplay playsinline></video>
        <?php else: ?>
            <div class="message"><strong><?= h($title) ?></strong></div>
        <?php endif; ?>
    </main>
    <?php if ($src !== ''): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flv.js/1.6.2/flv.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        const streamUrl = <?= json_encode($src, JSON_UNESCAPED_SLASHES) ?>;
        const openUrl = <?= json_encode($open, JSON_UNESCAPED_SLASHES) ?>;
        const video = document.getElementById('video');
        const stage = document.querySelector('.stage');
        let player = null;
        let hasFrame = false;
        let notice = null;

        function showMessage(title, detail) {
            if (hasFrame) return;
            const link = openUrl ? `<a href="${openUrl}" target="_blank" rel="noopener">開啟來源影像</a>` : '';
            stage.innerHTML = `<div class="message"><strong>${title}</strong><small>${detail}</small>${link}</div>`;
        }

        function showNotice(detail) {
            if (hasFrame) return;
            const link = openUrl ? `<a href="${openUrl}" target="_blank" rel="noopener">開啟來源</a>` : '';
            if (!notice) {
                notice = document.createElement('div');
                notice.className = 'notice';
                document.body.appendChild(notice);
            }
            notice.innerHTML = `<span>${detail}</span>${link}`;
        }

        window.addEventListener('error', () => {
            showNotice('內嵌串流暫時無回應，播放器會持續等待。');
        });
        window.addEventListener('unhandledrejection', () => {
            showNotice('內嵌串流暫時無回應，播放器會持續等待。');
        });

        video.addEventListener('loadeddata', () => {
            hasFrame = true;
            notice?.remove();
            notice = null;
        }, { once: true });

        if (!window.flvjs || !flvjs.isSupported()) {
            showMessage('瀏覽器不支援此影像格式', '此來源使用 FLV 即時串流，請改用來源頁查看。');
        } else {
            player = flvjs.createPlayer({
                type: 'flv',
                url: streamUrl,
                isLive: true,
            }, {
                enableWorker: false,
                enableStashBuffer: false,
                autoCleanupSourceBuffer: true,
            });
            player.attachMediaElement(video);
            player.load();
            video.play().catch(() => {});
            player.on(flvjs.Events.ERROR, () => {
                showNotice('內嵌串流連線不穩，來源頁可能仍可播放。');
            });
            setTimeout(() => {
                showNotice('尚未收到第一個影像畫面，仍在等待串流。');
            }, 12000);
            window.addEventListener('beforeunload', () => {
                try {
                    player.pause();
                    player.unload();
                    player.detachMediaElement();
                    player.destroy();
                } catch (error) {}
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>
