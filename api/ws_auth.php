<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ws.php';

\App\Auth\start_secure_session();
$me = \App\Auth\require_login();

function ws_auth_send_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method !== 'GET') {
    header('Allow: GET');
    ws_auth_send_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$userId = (int)($me['id'] ?? 0);
$wsUrl = \App\WS\env('WS_URL');
$token = \App\WS\generate_token($userId);

if (!$wsUrl || !$token) {
    ws_auth_send_json(500, [
        'ok' => false,
        'error' => 'ws_auth_unavailable',
        'reason' => 'Missing websocket configuration or unable to generate auth token.',
    ]);
}

ws_auth_send_json(200, [
    'ok' => true,
    'ws_url' => $wsUrl,
    'ws_auth' => [
        'userId' => $userId,
        'token' => $token,
    ],
]);
