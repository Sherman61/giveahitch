<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';

\App\Auth\start_secure_session();
$user = \App\Auth\require_login();
$uid = (int)($user['id'] ?? 0);
$pdo = db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/**
 * Emit a JSON response and terminate.
 */
function push_send_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function push_trim_user_agent(?string $ua): ?string
{
    if ($ua === null) {
        return null;
    }
    $ua = trim($ua);
    if ($ua === '') {
        return null;
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($ua, 'UTF-8') > 255) {
            return mb_substr($ua, 0, 255, 'UTF-8');
        }
        return $ua;
    }
    if (strlen($ua) > 255) {
        return substr($ua, 0, 255);
    }
    return $ua;
}

try {
    if ($method === 'POST') {
        $input = \App\Auth\assert_csrf_and_get_input();
        $endpoint = isset($input['endpoint']) ? trim((string)$input['endpoint']) : '';
        $keys = isset($input['keys']) && is_array($input['keys']) ? $input['keys'] : [];
        $p256dh = isset($keys['p256dh']) ? trim((string)$keys['p256dh']) : '';
        $auth = isset($keys['auth']) ? trim((string)$keys['auth']) : '';
        $ua = isset($input['ua']) ? (string)$input['ua'] : ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $ua = push_trim_user_agent(is_string($ua) ? $ua : null);

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            push_send_json(422, [
                'ok' => false,
                'error' => 'validation',
                'reason' => 'Missing subscription fields.',
            ]);
        }

        $sql = 'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, ua)
                VALUES (:uid, :endpoint, :p256dh, :auth, :ua)
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id),
                                        p256dh = VALUES(p256dh),
                                        auth = VALUES(auth),
                                        ua = VALUES(ua)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $uid > 0 ? $uid : null,
            ':endpoint' => $endpoint,
            ':p256dh' => $p256dh,
            ':auth' => $auth,
            ':ua' => $ua,
        ]);

        push_send_json(200, ['ok' => true]);
    }

    if ($method === 'DELETE') {
        $input = \App\Auth\assert_csrf_and_get_input();
        $endpoint = isset($input['endpoint']) ? trim((string)$input['endpoint']) : '';
        if ($endpoint === '') {
            push_send_json(422, [
                'ok' => false,
                'error' => 'validation',
                'reason' => 'Missing endpoint.',
            ]);
        }

        $stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint AND (user_id = :uid OR user_id IS NULL)');
        $stmt->execute([
            ':endpoint' => $endpoint,
            ':uid' => $uid,
        ]);

        push_send_json(200, [
            'ok' => true,
            'deleted' => (int)$stmt->rowCount(),
        ]);
    }

    header('Allow: POST, DELETE');
    push_send_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
} catch (Throwable $e) {
    error_log('push_subscription:error ' . $e->getMessage());
    push_send_json(500, ['ok' => false, 'error' => 'server_error']);
}
