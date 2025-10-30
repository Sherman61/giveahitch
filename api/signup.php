<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/session.php';
require_once __DIR__.'/../lib/mailer.php';
use function App\Auth\{find_user_by_email, create_user, csrf_verify};
use function App\Mailer\send_signup_confirmation;

\start_secure_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }

$input = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
$token = $input['csrf'] ?? '';
csrf_verify($token);

$email   = filter_var((string)($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$pass    = (string)($input['password'] ?? '');
$display = trim((string)($input['display_name'] ?? ''));

if (!$email || strlen($pass) < 8 || $display === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'validation']);
    exit;
}

if (find_user_by_email($email)) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'exists']);
    exit;
}

$user = create_user($email, $pass, $display);
send_signup_confirmation($user['email'] ?? $email, $user['display_name'] ?? $display);
$_SESSION['user'] = $user;
echo json_encode(['ok'=>true,'user'=>$user]);
?>
