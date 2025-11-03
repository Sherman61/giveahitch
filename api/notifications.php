<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/notifications.php';

use function App\Auth\{require_login, assert_csrf_and_get_input};

start_secure_session();
$user = require_login();
$uid = (int)($user['id'] ?? 0);
$pdo = db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function send(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'GET') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $cursor = isset($_GET['after']) ? (int)$_GET['after'] : null;
    $summaryOnly = isset($_GET['summary']);

    $result = \App\Notifications\list_recent($pdo, $uid, $limit, $cursor);
    $unread = \App\Notifications\unread_count($pdo, $uid);
    $settings = \App\Notifications\get_settings($pdo, $uid);

    $payload = [
        'ok' => true,
        'unread_count' => $unread,
        'settings' => $settings,
    ];

    if (!$summaryOnly) {
        $payload['items'] = $result['items'];
        $payload['has_more'] = $result['has_more'];
        $payload['next_cursor'] = $result['next_cursor'];
    }

    send(200, $payload);
}

if ($method === 'POST') {
    $input = assert_csrf_and_get_input();
    $action = isset($input['action']) ? (string)$input['action'] : '';

    if ($action === 'mark_read') {
        if (!empty($input['all'])) {
            \App\Notifications\mark_all_read($pdo, $uid);
        } else {
            $ids = [];
            if (isset($input['ids']) && is_array($input['ids'])) {
                $ids = $input['ids'];
            } elseif (isset($input['id'])) {
                $ids = [$input['id']];
            }
            \App\Notifications\mark_read($pdo, $uid, $ids);
        }

        $unread = \App\Notifications\unread_count($pdo, $uid);
        send(200, ['ok' => true, 'unread_count' => $unread]);
    }

    if ($action === 'settings') {
        $settings = [
            'ride_activity' => !empty($input['ride_activity']),
            'match_activity' => !empty($input['match_activity']),
        ];
        $saved = \App\Notifications\update_settings($pdo, $uid, $settings);
        send(200, ['ok' => true, 'settings' => $saved]);
    }

    send(400, ['ok' => false, 'error' => 'unknown_action']);
}

send(405, ['ok' => false, 'error' => 'method_not_allowed']);
