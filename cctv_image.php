<?php
declare(strict_types=1);

$src = trim((string) ($_GET['src'] ?? ''));
$title = trim((string) ($_GET['title'] ?? '即時影像'));
$isMjpeg = preg_match('#^https://trafficvideo\d*\.tainan\.gov\.tw/[A-Za-z0-9_-]+$#i', $src) === 1;
$ok = preg_match('/^https:\/\/cctv[a-z0-9-]*\.freeway\.gov\.tw\/abs2mjpg\/(?:b?m?jpg|jpg)\?camera=\d+$/i', $src) === 1
    || preg_match('#^https://cctv-ss\d+\.thb\.gov\.tw(?::443)?/[A-Za-z0-9+()._\-/%]+(?:/snapshot)?$#i', $src) === 1
    || $isMjpeg;
if (!$ok) {
    http_response_code(400);
    echo 'Invalid CCTV image source.';
    exit;
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
        html, body { width: 100%; height: 100%; margin: 0; overflow: hidden; background: #050809; }
        img { display: block; width: 100%; height: 100%; object-fit: contain; border: 0; background: #050809; }
    </style>
</head>
<body>
    <img id="cctvImage" src="<?= h($src) ?>" alt="<?= h($title) ?>">
    <script>
        const baseSrc = <?= json_encode($src, JSON_UNESCAPED_SLASHES) ?>;
        const isMjpeg = <?= $isMjpeg ? 'true' : 'false' ?>;
        const image = document.getElementById('cctvImage');
        if (!isMjpeg) {
            setInterval(() => {
                image.src = `${baseSrc}${baseSrc.includes('?') ? '&' : '?'}_=${Date.now()}`;
            }, 3500);
        }
    </script>
</body>
</html>
