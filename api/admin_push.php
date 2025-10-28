<?php
declare(strict_types=1);

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

use function App\Auth\{assert_csrf_and_get_input, require_admin};

try {
    require_admin();
    $input = assert_csrf_and_get_input();

    $title = isset($input['title']) ? trim((string)$input['title']) : '';
    $body  = isset($input['body']) ? trim((string)$input['body']) : '';
    $category = isset($input['category']) ? strtolower(trim((string)$input['category'])) : '';

    if ($category === '') {
        $category = 'social';
    }

    if (!preg_match('/^[a-z0-9_-]{3,32}$/', $category)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'invalid_category']);
        return;
    }

    if ($title === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'missing_title']);
        return;
    }

    $pdo = db();
    $stmt = $pdo->query('SELECT endpoint, p256dh, auth FROM push_subscriptions');
    $subscriptions = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

    if (!$subscriptions) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'no_subscribers']);
        return;
    }

    $configPath = __DIR__ . '/../config/config.php';
    if (!is_file($configPath)) {
        throw new \RuntimeException('config_missing');
    }
    $config = require $configPath;
    $vapid = $config['vapid'] ?? null;

    if (!is_array($vapid)
        || empty($vapid['public'])
        || empty($vapid['private'])
        || empty($vapid['subject'])
    ) {
        throw new \RuntimeException('invalid_vapid_config');
    }

    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => '/assets/img/icon-192.png',
        'badge' => '/assets/img/badge-72.png',
        'url' => '/notifications.php',
        'category' => $category,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        throw new \RuntimeException('payload_encoding_failed');
    }

    $webPush = new WebPush([
        'VAPID' => [
            'subject' => $vapid['subject'],
            'publicKey' => $vapid['public'],
            'privateKey' => $vapid['private'],
        ],
    ]);
    $webPush->setReuseVAPIDHeaders(true);

    $sent = 0;
    $failed = 0;
    $invalidEndpoints = [];
    $reports = [];

    foreach ($subscriptions as $row) {
        $endpoint = (string)($row['endpoint'] ?? '');
        $p256dh = (string)($row['p256dh'] ?? '');
        $auth = (string)($row['auth'] ?? '');

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            $failed++;
            continue;
        }

        try {
            $subscription = Subscription::create([
                'endpoint' => $endpoint,
                'publicKey' => $p256dh,
                'authToken' => $auth,
                'contentEncoding' => 'aes128gcm',
            ]);
        } catch (\Throwable $e) {
            error_log('admin_push:subscription_invalid ' . $e->getMessage());
            $failed++;
            continue;
        }

        $webPush->queueNotification($subscription, $payload);
    }

    foreach ($webPush->flush() as $report) {
        $endpoint = $report->getEndpoint();
        if ($report->isSuccess()) {
            $sent++;
            continue;
        }

        $failed++;
        $status = null;
        $response = $report->getResponse();
        if ($response) {
            $status = $response->getStatusCode();
        }
        if (in_array($status, [404, 410], true) && $endpoint) {
            $invalidEndpoints[$endpoint] = true;
        }

        if (count($reports) < 5) {
            $reports[] = [
                'endpoint' => $endpoint,
                'reason' => $report->getReason(),
                'status' => $status,
            ];
        }
    }

    if ($invalidEndpoints) {
        $placeholders = implode(',', array_fill(0, count($invalidEndpoints), '?'));
        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint IN ($placeholders)");
        if ($stmt) {
            $stmt->execute(array_keys($invalidEndpoints));
        }
    }

    echo json_encode([
        'ok' => true,
        'sent' => $sent,
        'failed' => $failed,
        'total' => $sent + $failed,
        'errors' => $reports,
        'deleted' => count($invalidEndpoints),
    ], JSON_UNESCAPED_UNICODE);
} catch (\RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
