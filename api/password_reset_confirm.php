<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
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

$email = strtolower(trim((string)($input['email'] ?? '')));
$code = preg_replace('/\D+/', '', (string)($input['code'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($code) !== 6 || strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'validation']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, reset_token_hash, reset_token_expires_at, reset_token_attempts FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || empty($user['reset_token_hash']) || empty($user['reset_token_expires_at'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_code']);
    exit;
}

$expiresString = (string)$user['reset_token_expires_at'];
try {
    $expiresAt = new DateTimeImmutable($expiresString);
} catch (Exception $e) {
    $expiresAt = null;
}

if (!$expiresAt || $expiresAt < new DateTimeImmutable('now')) {
    $pdo->prepare('UPDATE users SET reset_token_hash = NULL, reset_token_expires_at = NULL, reset_token_attempts = 0 WHERE id = :id')
        ->execute([':id' => (int)$user['id']]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'expired']);
    exit;
}

$attempts = (int)($user['reset_token_attempts'] ?? 0);
if ($attempts >= 5) {
    $pdo->prepare('UPDATE users SET reset_token_hash = NULL, reset_token_expires_at = NULL, reset_token_attempts = 0 WHERE id = :id')
        ->execute([':id' => (int)$user['id']]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'expired']);
    exit;
}

if (!password_verify($code, (string)$user['reset_token_hash'])) {
    $pdo->prepare('UPDATE users SET reset_token_attempts = reset_token_attempts + 1 WHERE id = :id')
        ->execute([':id' => (int)$user['id']]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_code']);
    exit;
}

$newHash = password_hash($password, PASSWORD_DEFAULT);
if ($newHash === false) {
    error_log('password_reset: failed to hash new password for user ' . $user['id']);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server']);
    exit;
}

$pdo->prepare('UPDATE users SET password_hash = :hash, reset_token_hash = NULL, reset_token_expires_at = NULL, reset_token_attempts = 0 WHERE id = :id')
    ->execute([
        ':hash' => $newHash,
        ':id' => (int)$user['id'],
    ]);

session_regenerate_id(true);
echo json_encode(['ok' => true]);
