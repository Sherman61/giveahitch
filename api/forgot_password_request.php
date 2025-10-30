<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
$configLoaded = false;
if (class_exists(Dotenv\Dotenv::class)) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    $configLoaded = true;
}
require_once __DIR__ . '/../config/db.php'; // must define $pdo (PDO)

use Symfony\Component\HttpClient\HttpClient;

function json_out($arr, int $code = 200): never
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
    $ip = inet_pton($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // find user
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
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

    // Send email via Mailtrap HTTP API
    $token = trim((string) ($_ENV['MAILTRAP_TOKEN'] ?? ''));
    if ($token === '')
        json_out(['ok' => false, 'error' => 'Server email token not configured'], 500);

    $from = $_ENV['TEST_EMAIL_FROM'] ?? 'no-reply@glitchahitch.com'; // your from
    $client = HttpClient::create();

    $text = "Your GlitchaHitch password reset code is: $code\n\nThis code expires in 15 minutes.";
    $html = '<p>Your GlitchaHitch password reset code is: <strong style="font-size:18px;letter-spacing:2px;">'
        . htmlspecialchars($code, ENT_QUOTES) . '</strong></p><p>This code expires in 15 minutes.</p>';

    $resp = $client->request('POST', 'https://send.api.mailtrap.io/api/send', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'from' => ['email' => $from, 'name' => 'GlitchaHitch'],
            'to' => [['email' => $user['email']]],
            'subject' => 'Your password reset code',
            'text' => $text,
            'html' => $html,
        ],
        'timeout' => 15,
    ]);

    $ok = $resp->getStatusCode() >= 200 && $resp->getStatusCode() < 300;
    json_out(['ok' => $ok, 'sent' => $ok]);

} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'Server error'], 500);
}
