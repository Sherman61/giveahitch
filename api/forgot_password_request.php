<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}
require_once __DIR__ . '/../config/db.php'; // must define $pdo (PDO)
require_once __DIR__ . '/../lib/mailer.php';

use function App\Mailer\send_password_reset_code;

function json_out($arr, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

try {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim((string) ($body['email'] ?? ''));
    if ($email === '')
        json_out(['ok' => false, 'error' => 'Email is required'], 422);

    // Basic rate-limit (per email/IP)
    $ip = null;
    $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        $packed = @inet_pton($remoteAddr);
        if ($packed !== false) {
            $ip = $packed;
        }
    }
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // find user
    $stmt = $pdo->prepare('SELECT id, email, display_name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    // Always respond 200 to avoid email enumeration
    if (!$user)
        json_out(['ok' => true, 'sent' => true]);

    // Create code (6 digits, not starting with 0)
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = (new DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:s');

    // Insert record
    $ins = $pdo->prepare('INSERT INTO password_resets (user_id, email, code, ip, ua, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
    $ins->execute([$user['id'], $user['email'], $code, $ip, $ua, $expiresAt]);

    $sent = send_password_reset_code($user['email'], $user['display_name'] ?? '', $code);
    if (!$sent) {
        error_log('forgot_password_request: failed to dispatch password reset email');
        json_out([
            'ok' => false,
            'sent' => false,
            'error' => 'Unable to send reset email. Please try again later.'
        ]);
    }

    json_out(['ok' => true, 'sent' => true]);

} catch (Throwable $e) {
    error_log('forgot_password_request: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Server error'], 500);
}
