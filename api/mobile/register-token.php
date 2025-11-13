<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/auth.php';

use function App\Auth\{require_login, assert_csrf_and_get_input, start_secure_session};

start_secure_session();
$user = require_login();
$uid = (int)($user['id'] ?? 0);

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$input = assert_csrf_and_get_input();
$endpoint = isset($input['expo_push_token']) ? trim((string)$input['expo_push_token']) : '';
$deviceId = isset($input['device_id']) ? trim((string)$input['device_id']) : '';
$platform = isset($input['platform']) ? trim((string)$input['platform']) : '';

if ($endpoint === '') {
    respond(422, ['ok' => false, 'error' => 'validation', 'reason' => 'Missing Expo push token']);
}

$deviceLabel = $deviceId !== '' ? $deviceId : 'unknown-device';
$platformLabel = $platform !== '' ? $platform : 'unknown-platform';
$ua = sprintf('expo-native %s %s', $platformLabel, $deviceLabel);

$p256dh = substr(base64_encode(hash('sha256', $endpoint . $deviceLabel, true)), 0, 255);
$auth = substr(hash('sha1', $endpoint . $platformLabel, false), 0, 255);

$pdo = db();
$stmt = $pdo->prepare(
    'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, ua)
     VALUES (:uid, :endpoint, :p256dh, :auth, :ua)
     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id),
                             p256dh = VALUES(p256dh),
                             auth = VALUES(auth),
                             ua = VALUES(ua)
    '
);
$stmt->execute([
    ':uid' => $uid,
    ':endpoint' => $endpoint,
    ':p256dh' => $p256dh,
    ':auth' => $auth,
    ':ua' => $ua,
]);

respond(200, ['ok' => true]);
