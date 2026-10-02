<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require dirname(__DIR__) . '/lib/cctv_submissions.php';

function respond(array $payload, int $status = 200): void
{
    if (ob_get_length() !== false && ob_get_length() > 0) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$input = $_POST;
if ($input === []) {
    $raw = (string) file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $input = $json;
    }
}

try {
    $submission = addCctvSubmission($input, $_SERVER);
    respond([
        'ok' => true,
        'message' => '已送出公開投稿，待管理員審核後才會顯示在網站上。',
        'id' => $submission['id'],
    ]);
} catch (Throwable $e) {
    respond([
        'ok' => false,
        'message' => $e->getMessage(),
    ], 422);
}
