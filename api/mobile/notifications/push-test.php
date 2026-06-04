<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../lib/session.php';
require_once __DIR__ . '/../../../lib/auth.php';

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

assert_csrf_and_get_input();

$pdo = db();
$stmt = $pdo->prepare(
    "SELECT endpoint
     FROM push_subscriptions
     WHERE user_id = :uid
       AND endpoint LIKE 'ExponentPushToken[%'
     ORDER BY updated_at DESC, id DESC
     LIMIT 1"
);
$stmt->execute([':uid' => $uid]);
$token = $stmt->fetchColumn();

if (!$token) {
    respond(404, ['ok' => false, 'error' => 'no_expo_push_token']);
}

$payload = json_encode([
    'to' => $token,
    'title' => 'Glitch A Hitch',
    'body' => 'Push test successful.',
    'sound' => 'default',
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://exp.host/--/api/v2/push/send');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);

$result = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($result === false) {
    respond(502, ['ok' => false, 'error' => 'curl_failed', 'details' => $curlError]);
}

if ($httpCode >= 400) {
    respond(502, ['ok' => false, 'error' => 'expo_push_failed', 'details' => $result]);
}

respond(200, ['ok' => true, 'message' => 'Test notification queued successfully.', 'details' => json_decode($result, true)]);
