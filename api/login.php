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
    echo json_encode(['ok'=>false,'error'=>'method']);
    exit;
}

$body = \App\Auth\assert_csrf_and_get_input();

$email = strtolower(trim((string)($body['email'] ?? '')));
$pass  = (string)($body['password'] ?? '');

if ($email === '' || $pass === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'validation']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, email, password_hash FROM users WHERE email=:e LIMIT 1');
$stmt->execute([':e'=>$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($pass, $user['password_hash'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'invalid']);
    exit;
}

$_SESSION['uid'] = (int)$user['id'];
session_regenerate_id(true);

echo json_encode(['ok'=>true]);
