<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/notifications.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/security_events.php';
use function App\Auth\{assert_csrf_and_get_input, require_admin};
use function App\Notifications\{broadcast_notification, deliver_push_notification, store_notification, unread_count};

try {
    $admin = require_admin();
    $input = assert_csrf_and_get_input();

    $title = trim((string)($input['title'] ?? ''));
    $body = trim((string)($input['body'] ?? ''));

    if ($title === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'missing_title']);
        exit;
    }

    $pdo = db();
    \App\Security\rate_limit($pdo, 'admin_push', 20, 900);
    $stmt = $pdo->query("
        SELECT DISTINCT user_id
        FROM push_subscriptions
        WHERE user_id IS NOT NULL
          AND user_id > 0
    ");
    $userIds = $stmt ? array_values(array_unique(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []))) : [];

    if (!$userIds) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'no_subscribers']);
        exit;
    }

    $usersNotified = 0;
    $pushSent = 0;
    $pushFailed = 0;
    $deleted = 0;
    $errors = [];

    foreach ($userIds as $userId) {
        $notification = store_notification($pdo, [
            'user_id' => $userId,
            'type' => 'system_announcement',
            'title' => $title,
            'body' => $body !== '' ? $body : null,
            'actor_user_id' => (int)$admin['id'],
            'actor_display_name' => (string)($admin['display_name'] ?? $admin['email'] ?? 'Admin'),
            'metadata' => [
                'url' => '/notifications.php',
                'source' => 'admin_push',
            ],
        ]);

        if (!$notification) {
            continue;
        }

        $usersNotified++;
        $unread = unread_count($pdo, $userId);
        broadcast_notification($notification, $unread);

        $delivery = deliver_push_notification($pdo, $notification);
        $pushSent += (int)($delivery['sent'] ?? 0);
        $pushFailed += (int)($delivery['failed'] ?? 0);
        $deleted += (int)($delivery['deleted'] ?? 0);
        if (!empty($delivery['errors']) && count($errors) < 5) {
            $remaining = 5 - count($errors);
            $errors = array_merge($errors, array_slice((array)$delivery['errors'], 0, $remaining));
        }
    }

    \App\SecurityEvents\log_event($pdo, 'admin_push_sent', 'warning', [
        'actor_user_id' => (int)$admin['id'],
        'details' => $title,
        'metadata' => [
            'users_notified' => $usersNotified,
            'push_sent' => $pushSent,
            'push_failed' => $pushFailed,
            'deleted_subscriptions' => $deleted,
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'users_notified' => $usersNotified,
        'sent' => $pushSent,
        'failed' => $pushFailed,
        'deleted' => $deleted,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('admin_push:error ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
