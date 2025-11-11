<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
  Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}
require_once __DIR__ . '/../config/db.php';

$pdo = db();

function out($a,$c=200){http_response_code($c);echo json_encode($a,JSON_UNESCAPED_SLASHES);exit;}

try {
  $b = json_decode(file_get_contents('php://input'), true) ?? [];
  $email = trim((string)($b['email'] ?? ''));
  $code  = trim((string)($b['code']  ?? ''));

  if ($email==='' || $code==='') out(['ok'=>false,'error'=>'Email and code are required'], 422);

  // fetch latest unused, unexpired code
  $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE email=? AND used_at IS NULL AND expires_at>=NOW() ORDER BY id DESC LIMIT 1');
  $stmt->execute([$email]);
  $pr = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$pr) out(['ok'=>false,'error'=>'Invalid or expired code'], 400);

  // track attempts
  if ((int)$pr['attempts'] >= 6) out(['ok'=>false,'error'=>'Too many attempts'], 429);

  if (hash_equals($pr['code'], $code)) {
    // issue a short-lived reset token (server-only, HMAC)
    $secret = $_ENV['APP_KEY'] ?? bin2hex(random_bytes(16));
    $payload = base64_encode(json_encode(['uid'=>$pr['user_id'], 'ts'=>time()]));
    $sig = hash_hmac('sha256', $payload, $secret);
    $reset_token = $payload.'.'.$sig;

    // mark the code as used
    $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')->execute([$pr['id']]);

    out(['ok'=>true, 'reset_token'=>$reset_token]);
  } else {
    $pdo->prepare('UPDATE password_resets SET attempts=attempts+1 WHERE id=?')->execute([$pr['id']]);
    out(['ok'=>false,'error'=>'Incorrect code'], 400);
  }
} catch (Throwable $e) {
  out(['ok'=>false,'error'=>'Server error'], 500);
}
