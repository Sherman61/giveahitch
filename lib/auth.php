<?php
declare(strict_types=1);

namespace App\Auth;

use PDO;

require_once __DIR__ . '/../config/db.php';   // provides global \db()
require_once __DIR__ . '/../lib/session.php'; // provides global \start_secure_session()

/** Reuse your existing db() */
function db(): PDO { return \db(); }

/** Session helpers */
function start_secure_session(): void { \start_secure_session(); }

/** CSRF: create once per session, don’t rotate on every request */
function csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Parse body (JSON or form) and enforce CSRF.
 * Looks in header `X-CSRF-Token`, JSON body `csrf`, or form `csrf`.
 * Returns the parsed input as an array on success; exits with 403 JSON on fail.
 */
function assert_csrf_and_get_input(): array {
    start_secure_session();

    $ct   = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw  = file_get_contents('php://input') ?: '';
    $json = (stripos($ct, 'application/json') === 0) ? json_decode($raw, true) : null;

    $hdr  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $in   = $hdr ?: ($json['csrf'] ?? ($_POST['csrf'] ?? ''));
    $sess = $_SESSION['csrf'] ?? '';

    if (!is_string($in) || !is_string($sess) || $sess === '' || !hash_equals($sess, $in)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }
    return is_array($json) ? $json : $_POST;
}

/** Back-compat: simple CSRF verify if you already extracted token elsewhere */
function csrf_verify(string $token): void {
    start_secure_session();
    $sess = $_SESSION['csrf'] ?? '';
    if ($sess === '' || !hash_equals($sess, $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'csrf']);
        exit;
    }
}

/** User lookup helpers */
function find_user_by_email(string $email): ?array {
    $stmt = db()->prepare('SELECT id,email,password_hash,display_name,is_admin FROM users WHERE email=:e LIMIT 1');
    $stmt->execute([':e' => strtolower(trim($email))]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    return $u ?: null;
}

function user_by_id(int $id): ?array {
    $stmt = db()->prepare('SELECT id,email,display_name,is_admin FROM users WHERE id=:i LIMIT 1');
    $stmt->execute([':i' => $id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    return $u ?: null;
}

function create_user(string $email, string $password, string $display): array {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    return create_user_with_hash($email, $hash, $display);
}

function create_user_with_hash(string $email, string $password_hash, string $display): array {
    $stmt = db()->prepare('INSERT INTO users(email,password_hash,display_name) VALUES(:e,:p,:d)');
    $stmt->execute([':e' => strtolower(trim($email)), ':p' => $password_hash, ':d' => $display]);
    $id = (int)db()->lastInsertId();
    return ['id'=>$id,'email'=>strtolower(trim($email)),'display_name'=>$display,'is_admin'=>0];
}

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/** Session → user */
function current_user(): ?array {
    start_secure_session();
    // Prefer uid, but support legacy $_SESSION['user']
    if (!empty($_SESSION['uid'])) {
        $uid = (int)$_SESSION['uid'];
        // Cache user in session for speed
        if (isset($_SESSION['user']) && (int)($_SESSION['user']['id'] ?? 0) === $uid) {
            return $_SESSION['user'];
        }
        $u = user_by_id($uid);
        if ($u) $_SESSION['user'] = $u;
        return $u;
    }
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool { return current_user() !== null; }
function is_admin(): bool { return (bool)(current_user()['is_admin'] ?? false); }

/** Login / logout */
function login_user(int $user_id): void {
    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = $user_id;
    // refresh cached user
    $_SESSION['user'] = user_by_id($user_id);
}

function logout(): void {
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/** Decide if request is an API call (so we return JSON instead of redirect) */
function is_api_request(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $sn  = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_starts_with($uri, '/api/') || str_starts_with($sn, '/api/')) return true;
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($accept, 'application/json') !== false;
}

/** Require login; returns the user. Redirects for pages, JSON 401 for APIs. */
function require_login(): array {
    $u = current_user();
    if ($u) return $u;

    if (is_api_request()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'auth']);
        exit;
    }
    header('Location: /login.php');
    exit;
}

/** Require admin */
function require_admin(): array {
    $u = require_login();
    if (!($u['is_admin'] ?? false)) {
        if (is_api_request()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false,'error'=>'forbidden']);
            exit;
        }
        header('Location: /');
        exit;
    }
    return $u;
}
