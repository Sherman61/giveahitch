<?php
// api/push_subscriptions.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/config.php';

// Your app's DB bootstrap should provide $pdo (PDO connected to your DB).
// If your project uses a different include, change this next line accordingly.
require_once __DIR__ . '/../db.php';

// Basic CSRF util
function csrf_valid(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$csrf  = $input['csrf'] ?? ($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));

if (!csrf_valid($csrf)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

try {
    if ($method === 'POST') {
        // Expect: {endpoint, keys:{p256dh, auth}, ua?}
        $endpoint = $input['endpoint'] ?? null;
        $keys     = $input['keys'] ?? [];
        $p256dh   = $keys['p256dh'] ?? null;
        $auth     = $keys['auth'] ?? null;
        $ua       = $_SERVER['HTTP_USER_AGENT'] ?? ($input['ua'] ?? null);

        if (!$endpoint || !$p256dh || !$auth) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing subscription fields']);
            exit;
        }

        $sql = 'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, ua)
                VALUES (:user_id, :endpoint, :p256dh, :auth, :ua)
                ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), p256dh=VALUES(p256dh), auth=VALUES(auth), ua=VALUES(ua)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id'  => $userId,
            ':endpoint' => $endpoint,
            ':p256dh'   => $p256dh,
            ':auth'     => $auth,
            ':ua'       => $ua,
        ]);

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'DELETE') {
        $endpoint = $input['endpoint'] ?? null;
        if (!$endpoint) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing endpoint']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?');
        $stmt->execute([$endpoint]);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(405);
    header('Allow: POST, DELETE');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
