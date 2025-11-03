<?php
declare(strict_types=1);

namespace App\Notifications;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

require_once __DIR__ . '/../lib/ws.php';

/**
 * Map notification types to the settings flag that controls them.
 */
const TYPE_SETTINGS_MAP = [
    'ride_match_requested' => 'ride_activity',
    'ride_match_joined'    => 'ride_activity',
    'ride_match_withdrawn' => 'ride_activity',
];

/** Default notification preferences. */
function default_settings(): array
{
    return [
        'ride_activity'  => true,
        'match_activity' => true,
    ];
}

/** Fetch persisted notification settings or fall back to defaults. */
function get_settings(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return default_settings();
    }

    $stmt = $pdo->prepare('SELECT ride_activity, match_activity FROM notification_settings WHERE user_id = :uid LIMIT 1');
    $stmt->execute([':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return default_settings();
    }

    return [
        'ride_activity'  => (bool)$row['ride_activity'],
        'match_activity' => (bool)$row['match_activity'],
    ];
}

/** Persist notification preferences. */
function update_settings(PDO $pdo, int $userId, array $settings): array
{
    if ($userId <= 0) {
        return default_settings();
    }

    $defaults = default_settings();
    $rideActivity = (bool)($settings['ride_activity'] ?? $defaults['ride_activity']);
    $matchActivity = (bool)($settings['match_activity'] ?? $defaults['match_activity']);

    $sql = 'INSERT INTO notification_settings (user_id, ride_activity, match_activity, updated_at)
            VALUES (:uid, :ride, :match, NOW())
            ON DUPLICATE KEY UPDATE ride_activity = VALUES(ride_activity), match_activity = VALUES(match_activity), updated_at = VALUES(updated_at)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':uid'   => $userId,
        ':ride'  => $rideActivity ? 1 : 0,
        ':match' => $matchActivity ? 1 : 0,
    ]);

    return [
        'ride_activity'  => $rideActivity,
        'match_activity' => $matchActivity,
    ];
}

/** Determine whether a notification type is enabled under the provided settings. */
function type_enabled(string $type, array $settings): bool
{
    $key = TYPE_SETTINGS_MAP[$type] ?? null;
    if (!$key) {
        return true; // unknown types default to on
    }
    return (bool)($settings[$key] ?? true);
}

/**
 * Convert a notification DB row into the public shape used by the API/UI.
 *
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function format_row(array $row): array
{
    $metadata = null;
    if (isset($row['metadata']) && $row['metadata'] !== null && $row['metadata'] !== '') {
        try {
            $decoded = json_decode((string)$row['metadata'], true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        } catch (RuntimeException) {
            $metadata = null;
        }
    }

    return [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'type' => (string)$row['type'],
        'title' => (string)$row['title'],
        'body' => $row['body'] !== null ? (string)$row['body'] : null,
        'ride_id' => isset($row['ride_id']) ? (int)$row['ride_id'] : null,
        'match_id' => isset($row['match_id']) ? (int)$row['match_id'] : null,
        'actor_user_id' => isset($row['actor_user_id']) ? (int)$row['actor_user_id'] : null,
        'actor_display_name' => $row['actor_display_name'] !== null ? (string)$row['actor_display_name'] : null,
        'is_read' => (bool)$row['is_read'],
        'created_at' => (string)$row['created_at'],
        'read_at' => $row['read_at'] !== null ? (string)$row['read_at'] : null,
        'metadata' => $metadata,
    ];
}

/**
 * Fetch a single notification row by ID for formatting/broadcasting.
 */
function fetch_by_id(PDO $pdo, int $id, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = :id AND user_id = :uid LIMIT 1');
    $stmt->execute([':id' => $id, ':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? format_row($row) : null;
}

/**
 * @return array{items: list<array<string,mixed>>, has_more: bool, next_cursor: int|null}
 */
function list_recent(PDO $pdo, int $userId, int $limit = 20, ?int $afterId = null): array
{
    if ($userId <= 0) {
        return ['items' => [], 'has_more' => false, 'next_cursor' => null];
    }

    $limit = max(0, min(100, $limit));
    if ($limit === 0) {
        return ['items' => [], 'has_more' => false, 'next_cursor' => null];
    }

    $sql = 'SELECT * FROM notifications WHERE user_id = :uid';
    $params = [':uid' => $userId];

    if ($afterId !== null && $afterId > 0) {
        $sql .= ' AND id < :after';
        $params[':after'] = $afterId;
    }

    $sql .= ' ORDER BY id DESC LIMIT :lim';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':after') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $items = array_map(static fn(array $row): array => format_row($row), $rows);
    $hasMore = count($items) === $limit;
    $nextCursor = $hasMore ? (int)($items[array_key_last($items)]['id'] ?? 0) : null;

    return ['items' => $items, 'has_more' => $hasMore, 'next_cursor' => $nextCursor ?: null];
}

function unread_count(PDO $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0');
    $stmt->execute([':uid' => $userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Mark the provided notifications read for a user. Returns the number affected.
 */
function mark_read(PDO $pdo, int $userId, array $ids): int
{
    if ($userId <= 0 || !$ids) {
        return 0;
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND id IN ($placeholders) AND is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId], $ids));
    return $stmt->rowCount();
}

function mark_all_read(PDO $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :uid AND is_read = 0');
    $stmt->execute([':uid' => $userId]);
    return $stmt->rowCount();
}

/**
 * Internal helper to emit websocket updates after a new notification is stored.
 */
function broadcast_notification(array $notification, int $unreadCount): void
{
    if (empty($notification['user_id'])) {
        return;
    }
    try {
        \App\WS\broadcast('notification:new', [
            'notification' => $notification,
            'unread_count' => $unreadCount,
        ], [
            'user:' . (int)$notification['user_id'],
        ]);
    } catch (RuntimeException | PDOException $e) {
        error_log('notifications:broadcast_failed ' . $e->getMessage());
    }
}

/**
 * Attempt to load VAPID credentials for push notifications.
 *
 * @return array{public:string,private:string,subject:string}|null
 */
function push_vapid_credentials(): ?array
{
    static $cache;
    if ($cache === false) {
        return null;
    }
    if ($cache !== null) {
        return $cache;
    }

    $configPath = __DIR__ . '/../config/config.php';
    if (!is_file($configPath)) {
        $cache = false;
        return null;
    }

    $config = require $configPath;
    $vapid = $config['vapid'] ?? null;

    if (!is_array($vapid)
        || empty($vapid['public'])
        || empty($vapid['private'])
        || empty($vapid['subject'])
    ) {
        $cache = false;
        return null;
    }

    $cache = [
        'public' => (string)$vapid['public'],
        'private' => (string)$vapid['private'],
        'subject' => (string)$vapid['subject'],
    ];

    return $cache;
}

/** Ensure the WebPush dependencies are available before attempting delivery. */
function ensure_push_dependencies_loaded(): bool
{
    static $loaded;
    if ($loaded === true) {
        return true;
    }
    if ($loaded === false) {
        return false;
    }

    if (!class_exists(WebPush::class) || !class_exists(Subscription::class)) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }

    $loaded = class_exists(WebPush::class) && class_exists(Subscription::class);
    return $loaded;
}

/**
 * Determine the most appropriate target URL for a notification.
 */
function notification_target_url(array $notification): string
{
    $metadata = $notification['metadata'] ?? null;
    if (is_array($metadata)) {
        foreach (['url', 'target_url', 'href'] as $key) {
            if (!empty($metadata[$key]) && is_string($metadata[$key])) {
                return (string)$metadata[$key];
            }
        }
    }

    if (!empty($notification['ride_id'])) {
        return '/manage_ride.php?id=' . (int)$notification['ride_id'];
    }

    return '/notifications.php';
}

/**
 * Broadcast a web push notification mirroring the in-app notification.
 */
function send_push_notification(PDO $pdo, array $notification): void
{
    $userId = (int)($notification['user_id'] ?? 0);
    if ($userId <= 0) {
        return;
    }

    $vapid = push_vapid_credentials();
    if (!$vapid || !ensure_push_dependencies_loaded()) {
        return;
    }

    try {
        $stmt = $pdo->prepare('SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = :uid');
        $stmt->execute([':uid' => $userId]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('notifications:push_fetch_failed ' . $e->getMessage());
        return;
    }

    if (!$subscriptions) {
        return;
    }

    $url = notification_target_url($notification);
    $body = isset($notification['body']) && $notification['body'] !== null
        ? (string)$notification['body']
        : '';

    $payloadData = [
        'title' => (string)($notification['title'] ?? 'GlitchaHitch'),
        'body' => $body,
        'icon' => '/assets/img/icon-192.png',
        'badge' => '/assets/img/badge-72.png',
        'url' => $url,
        'tag' => 'notification-' . (int)($notification['id'] ?? 0),
        'data' => [
            'url' => $url,
            'notification_id' => (int)($notification['id'] ?? 0),
            'type' => $notification['type'] ?? null,
            'ride_id' => $notification['ride_id'] ?? null,
            'match_id' => $notification['match_id'] ?? null,
        ],
    ];

    if (!empty($notification['metadata']) && is_array($notification['metadata'])) {
        $payloadData['data']['metadata'] = $notification['metadata'];
    }

    $payload = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return;
    }

    try {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $vapid['subject'],
                'publicKey' => $vapid['public'],
                'privateKey' => $vapid['private'],
            ],
        ]);
        $webPush->setReuseVAPIDHeaders(true);
    } catch (Throwable $e) {
        error_log('notifications:push_init_failed ' . $e->getMessage());
        return;
    }

    foreach ($subscriptions as $row) {
        $endpoint = (string)($row['endpoint'] ?? '');
        $p256dh = (string)($row['p256dh'] ?? '');
        $auth = (string)($row['auth'] ?? '');

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            continue;
        }

        try {
            $subscription = Subscription::create([
                'endpoint' => $endpoint,
                'publicKey' => $p256dh,
                'authToken' => $auth,
                'contentEncoding' => 'aes128gcm',
            ]);
        } catch (Throwable $e) {
            error_log('notifications:push_subscription_invalid ' . $e->getMessage());
            continue;
        }

        try {
            $webPush->queueNotification($subscription, $payload);
        } catch (Throwable $e) {
            error_log('notifications:push_queue_failed ' . $e->getMessage());
        }
    }

    $invalidEndpoints = [];

    try {
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $endpoint = $report->getEndpoint();
            $status = null;
            $response = $report->getResponse();
            if ($response) {
                $status = $response->getStatusCode();
            }

            if (in_array($status, [404, 410], true) && $endpoint) {
                $invalidEndpoints[$endpoint] = true;
            }

            error_log(sprintf(
                'notifications:push_failed endpoint=%s status=%s reason=%s',
                $endpoint ?: 'unknown',
                $status !== null ? (string)$status : 'n/a',
                $report->getReason()
            ));
        }
    } catch (Throwable $e) {
        error_log('notifications:push_flush_failed ' . $e->getMessage());
    }

    if ($invalidEndpoints) {
        try {
            $placeholders = implode(',', array_fill(0, count($invalidEndpoints), '?'));
            $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint IN ($placeholders)");
            if ($stmt) {
                $stmt->execute(array_keys($invalidEndpoints));
            }
        } catch (PDOException $e) {
            error_log('notifications:push_cleanup_failed ' . $e->getMessage());
        }
    }
}

/**
 * Create a notification for a user. Returns the formatted notification or null if skipped.
 *
 * @param array<string,mixed> $data
 */
function create(PDO $pdo, array $data): ?array
{
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $type = isset($data['type']) ? (string)$data['type'] : '';
    $title = isset($data['title']) ? trim((string)$data['title']) : '';

    if ($userId <= 0 || $type === '' || $title === '') {
        return null;
    }

    $settings = get_settings($pdo, $userId);
    if (!type_enabled($type, $settings)) {
        return null;
    }

    $body = isset($data['body']) && $data['body'] !== null ? trim((string)$data['body']) : null;
    $rideId = isset($data['ride_id']) ? (int)$data['ride_id'] : null;
    $matchId = isset($data['match_id']) ? (int)$data['match_id'] : null;
    $actorId = isset($data['actor_user_id']) ? (int)$data['actor_user_id'] : null;
    $actorName = isset($data['actor_display_name']) ? trim((string)$data['actor_display_name']) : null;
    $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null;

    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, ride_id, match_id, actor_user_id, actor_display_name, metadata) VALUES (:uid, :type, :title, :body, :ride, :match, :actor, :actor_name, :metadata)');
    $stmt->execute([
        ':uid' => $userId,
        ':type' => $type,
        ':title' => $title,
        ':body' => $body,
        ':ride' => $rideId,
        ':match' => $matchId,
        ':actor' => $actorId,
        ':actor_name' => $actorName,
        ':metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
    ]);

    $id = (int)$pdo->lastInsertId();
    $notification = fetch_by_id($pdo, $id, $userId);
    if (!$notification) {
        return null;
    }

    $unread = unread_count($pdo, $userId);
    broadcast_notification($notification, $unread);
    send_push_notification($pdo, $notification);

    return $notification;
}

/**
 * Convenience helper to notify a ride owner about an event triggered by someone else.
 *
 * @param array<string,mixed> $rideRow  Ride data that must include `id` and `user_id`.
 * @param array<string,mixed> $actor    Actor information (expects `id` and optional `display_name`).
 * @param array<string,mixed> $payload  Additional metadata stored alongside the notification.
 */
function notify_ride_owner(PDO $pdo, array $rideRow, array $actor, string $type, string $title, string $body, array $payload = []): ?array
{
    $userId = isset($rideRow['user_id']) ? (int)$rideRow['user_id'] : 0;
    $rideId = isset($rideRow['id']) ? (int)$rideRow['id'] : null;
    $actorId = isset($actor['id']) ? (int)$actor['id'] : null;

    if ($userId <= 0 || ($actorId !== null && $actorId === $userId)) {
        return null;
    }

    $actorName = null;
    if (isset($actor['display_name']) && $actor['display_name'] !== '') {
        $actorName = (string)$actor['display_name'];
    } elseif (isset($actor['username']) && $actor['username'] !== '') {
        $actorName = (string)$actor['username'];
    }

    $payload['ride_type'] = $payload['ride_type'] ?? ($rideRow['type'] ?? null);
    if (!isset($payload['url']) && $rideId) {
        $payload['url'] = '/manage_ride.php?id=' . $rideId;
    }

    return create($pdo, [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'ride_id' => $rideId,
        'match_id' => isset($payload['match_id']) ? (int)$payload['match_id'] : null,
        'actor_user_id' => $actorId,
        'actor_display_name' => $actorName,
        'metadata' => $payload,
    ]);
}
