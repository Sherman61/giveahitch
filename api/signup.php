<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/session.php';
require_once __DIR__.'/../lib/mailer.php';
use function App\Auth\{find_user_by_email, create_user, csrf_verify};
use function App\Mailer\{send_signup_confirmation, send_signup_pin};

\start_secure_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }

$input = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
$token = $input['csrf'] ?? '';
csrf_verify($token);

$email   = filter_var((string)($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$pass    = (string)($input['password'] ?? '');
$display = trim((string)($input['display_name'] ?? ''));
$pin     = trim((string)($input['pin'] ?? ''));

$pendingKey = 'signup_pending';

if ($pin === '') {
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

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION[$pendingKey] = [
        'email' => $email,
        'password' => $pass,
        'display_name' => $display,
        'pin' => $code,
        'expires' => time() + 600,
    ];

    $sent = send_signup_pin($email, $display, $code);
    if (!$sent) {
        unset($_SESSION[$pendingKey]);
        http_response_code(502);
        echo json_encode(['ok'=>false,'error'=>'pin_send_failed']);
        exit;
    }

    echo json_encode(['ok'=>true,'step'=>'pin_required']);
    exit;
}

$pending = $_SESSION[$pendingKey] ?? null;
if (!$pending || !is_array($pending)) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'no_pending']);
    exit;
}

$pendingEmail = $pending['email'] ?? '';
if (!$email || !hash_equals(strtolower((string)$pendingEmail), strtolower((string)$email))) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'email_mismatch']);
    exit;
}

$expires = (int)($pending['expires'] ?? 0);
if ($expires !== 0 && $expires < time()) {
    unset($_SESSION[$pendingKey]);
    http_response_code(410);
    echo json_encode(['ok'=>false,'error'=>'pin_expired']);
    exit;
}

$pinIsValid = preg_match('/^\d{6}$/', $pin) === 1;

$expectedPin = (string)($pending['pin'] ?? '');
if (!$pinIsValid || $expectedPin === '' || !hash_equals($expectedPin, $pin)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'pin_invalid']);
    exit;
}

if (find_user_by_email($email)) {
    unset($_SESSION[$pendingKey]);
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'exists']);
    exit;
}

$user = create_user($pending['email'], $pending['password'], $pending['display_name']);
unset($_SESSION[$pendingKey]);
session_regenerate_id(true);
$_SESSION['user'] = $user;
$_SESSION['uid'] = $user['id'] ?? null;

send_signup_confirmation($user['email'] ?? $email, $user['display_name'] ?? $display);

$message = sprintf('Congratulations you have successfully created your account %s', $user['display_name'] ?? '');
echo json_encode(['ok'=>true,'user'=>$user,'message'=>$message]);
?>
