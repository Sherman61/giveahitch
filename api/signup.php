<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/session.php';
require_once __DIR__.'/../lib/mailer.php';
use function App\Auth\{find_user_by_email, create_user_with_hash, csrf_verify, login_user};
use function App\Mailer\{send_signup_confirmation, send_signup_verification_pin};

\start_secure_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }

$input = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
$token = $input['csrf'] ?? '';
csrf_verify($token);

$emailInput = filter_var((string)($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);

// Step 2: verify PIN
if (isset($input['pin'])) {
    $pin = preg_replace('/\D+/', '', (string)($input['pin'] ?? ''));
    if (!$emailInput || strlen($pin) !== 6) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'validation']);
        exit;
    }

    $pending = $_SESSION['pending_signup'] ?? null;
    if (!is_array($pending)) {
        http_response_code(409);
        echo json_encode(['ok'=>false,'error'=>'no_pending']);
        exit;
    }

    $pendingEmail = strtolower((string)($pending['email'] ?? ''));
    $normalizedEmail = strtolower($emailInput);
    if ($pendingEmail === '' || $pendingEmail !== $normalizedEmail) {
        http_response_code(409);
        echo json_encode(['ok'=>false,'error'=>'email_mismatch']);
        exit;
    }

    $expiresAt = (int)($pending['expires_at'] ?? 0);
    if ($expiresAt < time()) {
        unset($_SESSION['pending_signup']);
        http_response_code(410);
        echo json_encode(['ok'=>false,'error'=>'pin_expired']);
        exit;
    }

    $pinHash = (string)($pending['pin_hash'] ?? '');
    if ($pinHash === '' || !password_verify($pin, $pinHash)) {
        $pending['attempts'] = (int)($pending['attempts'] ?? 0) + 1;
        $_SESSION['pending_signup'] = $pending;
        http_response_code(401);
        echo json_encode(['ok'=>false,'error'=>'pin_invalid']);
        exit;
    }

    if (find_user_by_email($normalizedEmail)) {
        unset($_SESSION['pending_signup']);
        http_response_code(409);
        echo json_encode(['ok'=>false,'error'=>'exists']);
        exit;
    }

    $display = (string)($pending['display_name'] ?? '');
    $passwordHash = (string)($pending['password_hash'] ?? '');
    if ($display === '' || $passwordHash === '') {
        unset($_SESSION['pending_signup']);
        http_response_code(409);
        echo json_encode(['ok'=>false,'error'=>'no_pending']);
        exit;
    }

    unset($_SESSION['pending_signup']);

    $user = create_user_with_hash($normalizedEmail, $passwordHash, $display);
    send_signup_confirmation($user['email'] ?? $normalizedEmail, $user['display_name'] ?? $display);
    login_user((int)$user['id']);
    echo json_encode(['ok'=>true,'user'=>$user]);
    exit;
}

// Step 1: start signup and send PIN
$pass    = (string)($input['password'] ?? '');
$display = trim((string)($input['display_name'] ?? ''));

if (!$emailInput || strlen($pass) < 8 || $display === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'validation']);
    exit;
}

$normalizedEmail = strtolower($emailInput);
if (find_user_by_email($normalizedEmail)) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'exists']);
    exit;
}

$pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$pending = [
    'email' => $normalizedEmail,
    'display_name' => $display,
    'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
    'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
    'expires_at' => time() + 15 * 60,
    'attempts' => 0,
];

$_SESSION['pending_signup'] = $pending;

if (!send_signup_verification_pin($emailInput, $display, $pin)) {
    unset($_SESSION['pending_signup']);
    http_response_code(502);
    echo json_encode(['ok'=>false,'error'=>'pin_send_failed']);
    exit;
}

echo json_encode(['ok'=>true,'step'=>'pin_required']);
?>
