<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}
require_once __DIR__ . '/../config/db.php';

function out($a, $c = 200)
{
    http_response_code($c);
    echo json_encode($a, JSON_UNESCAPED_SLASHES);
    exit;
}
function parse_token(string $token, string $key): ?array
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2)
        return null;
    [$payload, $sig] = $parts;
    $calc = hash_hmac('sha256', $payload, $key);
    if (!hash_equals($calc, $sig))
        return null;
    $data = json_decode(base64_decode($payload, true) ?: '', true);
    if (!is_array($data) || empty($data['uid']) || empty($data['ts']))
        return null;
    // expire after 15 minutes
    if ((time() - (int) $data['ts']) > 900)
        return null;
    return $data;
}

try {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = trim((string) ($b['reset_token'] ?? ''));
    $password = (string) ($b['password'] ?? '');
    if ($token === '' || $password === '')
        out(['ok' => false, 'error' => 'reset_token and password required'], 422);
    if (strlen($password) < 8)
        out(['ok' => false, 'error' => 'Password must be at least 8 characters'], 422);

    $key = $_ENV['APP_KEY'] ?? bin2hex(random_bytes(16));
    $data = parse_token($token, $key);
    if (!$data)
        out(['ok' => false, 'error' => 'Invalid or expired token'], 400);

    $uid = (int) $data['uid'];
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Update user password
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $uid]);

    out(['ok' => true]);
} catch (Throwable $e) {
    out(['ok' => false, 'error' => 'Server error'], 500);
}
