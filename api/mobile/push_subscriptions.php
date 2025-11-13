<?php declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/auth.php';

use function App\Auth\{assert_csrf_and_get_input, require_login, start_secure_session};

start_secure_session();
$user = require_login();
$uid = (int)($user['id'] ?? 0);

/**
 * @param array<string,mixed> $payload
 */
function respond(int $status, array $payload): void {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function trim_user_agent(?string $ua): ?string {
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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$input = assert_csrf_and_get_input();
$endpoint = isset($input['endpoint']) ? trim((string)$input['endpoint']) : '';
$keys = isset($input['keys']) && is_array($input['keys']) ? $input['keys'] : [];
$p256dh = isset($keys['p256dh']) ? trim((string)$keys['p256dh']) : '';
$auth = isset($keys['auth']) ? trim((string)$keys['auth']) : '';
$ua = isset($input['ua']) ? (string)$input['ua'] : ($_SERVER['HTTP_USER_AGENT'] ?? null);
$ua = trim_user_agent(is_string($ua) ? $ua : null);

if ($endpoint === '' || $p256dh === '' || $auth === '') {
  respond(422, [
    'ok' => false,
    'error' => 'validation',
    'reason' => 'Missing subscription parameters.',
  ]);
}

$pdo = db();
$stmt = $pdo->prepare(
  'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, ua)
   VALUES (:uid, :endpoint, :p256dh, :auth, :ua)
   ON DUPLICATE KEY UPDATE user_id = VALUES(user_id),
                           p256dh = VALUES(p256dh),
                           auth = VALUES(auth),
                           ua = VALUES(ua)'
);
$stmt->execute([
  ':uid' => $uid > 0 ? $uid : null,
  ':endpoint' => $endpoint,
  ':p256dh' => $p256dh,
  ':auth' => $auth,
  ':ua' => $ua,
]);

respond(200, ['ok' => true]);
