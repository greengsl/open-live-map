<?php
declare(strict_types=1);

$device = strtoupper(trim((string) ($_GET['device'] ?? '')));
$title = trim((string) ($_GET['title'] ?? '新北即時影像'));

if (preg_match('/^C\d{6}$/', $device) !== 1) {
    http_response_code(400);
    echo 'Invalid NTPC CCTV device.';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function fetch_ntpc_stream_url(string $device): string
{
    $api = 'https://apiatis.ntpc.gov.tw/atis-api/device/queryCCTVURL/' . rawurlencode($device);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "Accept: application/json\r\nUser-Agent: OpenLiveMap/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $json = @file_get_contents($api, false, $context);
    if (!is_string($json) || $json === '') {
        return '';
    }
    $data = json_decode($json, true);
    $url = $data['data']['url'] ?? $data['data'] ?? '';
    if (!is_string($url)) {
        return '';
    }
    $url = trim($url);
    if (preg_match('#^https://cctvatis[1-6]\.ntpc\.gov\.tw/flv/C\d{6}$#i', $url) !== 1) {
        return '';
    }
    return preg_replace('#^https://#i', 'wss://', $url);
}

$streamUrl = fetch_ntpc_stream_url($device);
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
        video { width: 100%; height: 100%; object-fit: contain; background: #050809; }
        .message { padding: 18px; text-align: center; line-height: 1.6; }
        .message strong { display: block; margin-bottom: 6px; color: #fff; }
        .message small { color: rgba(219, 232, 234, .72); }
        .notice { position: fixed; right: 8px; bottom: 8px; left: 8px; padding: 7px 9px; border-radius: 7px; color: rgba(219, 232, 234, .86); background: rgba(5, 8, 9, .72); font-size: 12px; line-height: 1.35; }
    </style>
</head>
<body>
    <main class="stage">
        <?php if ($streamUrl !== ''): ?>
            <video id="video" muted autoplay playsinline controls></video>
        <?php else: ?>
            <div class="message">
                <strong>影像暫時無法取得</strong>
                <small>來源 API 未回傳可播放串流，請稍後再試。</small>
            </div>
        <?php endif; ?>
    </main>
    <?php if ($streamUrl !== ''): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flv.js/1.6.2/flv.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        const streamUrl = <?= json_encode($streamUrl, JSON_UNESCAPED_SLASHES) ?>;
        const video = document.getElementById('video');
        const stage = document.querySelector('.stage');
        let hasFrame = false;
        let notice = null;

        function showMessage(title, detail) {
            stage.innerHTML = `<div class="message"><strong>${title}</strong><small>${detail}</small></div>`;
        }

        function showNotice(detail) {
            if (hasFrame) return;
            if (!notice) {
                notice = document.createElement('div');
                notice.className = 'notice';
                document.body.appendChild(notice);
            }
            notice.textContent = detail;
        }

        video.addEventListener('loadeddata', () => {
            hasFrame = true;
            notice?.remove();
            notice = null;
        }, { once: true });

        if (!window.flvjs || !flvjs.isSupported()) {
            showMessage('瀏覽器不支援此影像格式', '請用「開啟影像」在來源頁查看。');
        } else {
            const player = flvjs.createPlayer({
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
                showNotice('內嵌串流連線不穩，來源可能仍可播放。');
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
