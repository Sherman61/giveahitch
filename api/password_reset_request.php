<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/../config/db.php';

\App\Auth\start_secure_session();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$input = \App\Auth\assert_csrf_and_get_input();

$email = (string)($input['email'] ?? '');
$email = strtolower(trim($email));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'validation']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, email, display_name FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Always respond with success to avoid leaking which emails exist.
if (!$user) {
    echo json_encode(['ok' => true]);
    exit;
}

try {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
} catch (Throwable $e) {
    error_log('password_reset: failed generating code: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server']);
    exit;
}

$hash = password_hash($code, PASSWORD_DEFAULT);
if ($hash === false) {
    error_log('password_reset: failed hashing code for user ' . $user['id']);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server']);
    exit;
}

$expiresAt = (new DateTimeImmutable('now'))
    ->add(new DateInterval('PT15M'))
    ->format('Y-m-d H:i:s');

$update = $pdo->prepare('UPDATE users SET reset_token_hash = :hash, reset_token_expires_at = :expires, reset_token_attempts = 0 WHERE id = :id');
$update->execute([
    ':hash' => $hash,
    ':expires' => $expiresAt,
    ':id' => (int)$user['id'],
]);

$sent = \App\Mailer\send_password_reset_code($user['email'], (string)($user['display_name'] ?? ''), $code);
if (!$sent) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail']);
    exit;
}

echo json_encode(['ok' => true]);
