<?php declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/notifications.php';

use App\Notifications;
use function App\Auth\{assert_csrf_and_get_input, require_admin, start_secure_session};

start_secure_session();
$user = require_admin();

/**
 * @param array<string,mixed> $payload
 */
function respond(int $status, array $payload): void {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

try {
  $input = assert_csrf_and_get_input();
  $message = isset($input['message']) ? trim((string)$input['message']) : '';
  if ($message === '') {
    $message = 'Push notification test from the admin console.';
  }

  $pdo = db();
  $result = Notifications\create($pdo, [
    'user_id' => (int)($user['id'] ?? 0),
    'type' => 'system_push_test',
    'title' => 'Push notification test',
    'body' => $message,
    'metadata' => [
      'url' => '/notifications.php',
      'source' => 'mobile_push_test',
    ],
  ]);

  if (!$result) {
    respond(500, ['ok' => false, 'error' => 'notification_failed']);
  }

  respond(200, ['ok' => true, 'message' => 'Test notification queued.']);
} catch (\Throwable $e) {
  error_log('mobile push-test failure: ' . $e->getMessage());
  respond(500, ['ok' => false, 'error' => 'server_error']);
}
