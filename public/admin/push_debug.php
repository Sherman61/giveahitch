<?php
// /public/admin/push_debug.php  (protect or remove after testing)
declare(strict_types=1);

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

session_start();
header('Content-Type: text/plain; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autoload + config (.env)
require_once __DIR__ . '/../../vendor/autoload.php';
$config = require __DIR__ . '/../../config/config.php';

// ---- DB bootstrap ----
// Try your project's DB bootstrap (adjust path if your file is elsewhere)
// This file should define $pdo = new PDO(...);
$pdo = null;
$maybeDbPaths = [
    __DIR__ . '/../../db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../db.php',
];

foreach ($maybeDbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        if (isset($pdo) && $pdo instanceof PDO) {
            break;
        }
    }
}


// ---- Input ----
$endpoint = isset($_GET['endpoint']) ? (string) $_GET['endpoint'] : null;
if (!$endpoint) {
    http_response_code(400);
    echo "Provide ?endpoint=... (the exact endpoint URL from the device subscription)\n";
    exit;
}

// ---- Lookup subscription ----
try {
    $stmt = $pdo->prepare('SELECT id, endpoint, p256dh, auth, ua FROM push_subscriptions WHERE endpoint = ?');
    $stmt->execute([$endpoint]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo "No subscription found for that endpoint.\n";
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB error: " . $e->getMessage() . "\n";
    exit;
}

// ---- Send test push ----
$webPush = new WebPush([
    'VAPID' => [
        'subject' => $config['vapid']['subject'],
        'publicKey' => $config['vapid']['public'],
        'privateKey' => $config['vapid']['private'],
    ],
]);
$webPush->setDefaultOptions(['TTL' => 60]);

$sub = Subscription::create([
    'endpoint' => $row['endpoint'],
    'keys' => ['p256dh' => $row['p256dh'], 'auth' => $row['auth']],
]);

$payload = json_encode([
    'title' => 'Android test',
    'body' => 'If you see this, Chrome Android works.',
    'url' => '/',
]);

$report = $webPush->sendOneNotification($sub, $payload);

// ---- Output ----
echo "Endpoint: {$row['endpoint']}\nUA: {$row['ua']}\n";
if ($report) {
    echo "Success? " . ($report->isSuccess() ? 'YES' : 'NO') . "\n";
    if (method_exists($report, 'getReason')) {
        echo "Reason: " . $report->getReason() . "\n";
    }
    if (method_exists($report, 'getResponse') && $report->getResponse()) {
        echo "HTTP: " . $report->getResponse()->getStatusCode() . "\n";
    }
    if (method_exists($report, 'isSubscriptionExpired') && $report->isSubscriptionExpired()) {
        echo "Subscription is expired/invalid (delete it & re-subscribe).\n";
        // Optional cleanup:
        // $pdo->prepare('DELETE FROM push_subscriptions WHERE id=?')->execute([$row['id']]);
    }
} else {
    echo "No report object returned.\n";
}
