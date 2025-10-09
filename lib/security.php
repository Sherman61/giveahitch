<?php
declare(strict_types=1);

namespace App\Security;

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

function csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(string $token): void {
    start_secure_session();
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

function sanitize_html(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function client_ip_binary(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $bin = @inet_pton($ip);
    return $bin !== false ? $bin : inet_pton('0.0.0.0');
}

function rate_limit(\PDO $pdo, string $endpoint, int $limit, int $windowSeconds): void {
    $ipBin = client_ip_binary();
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $windowStart = $now->setTime((int)$now->format('H'), (int)floor((int)$now->format('i') / ($windowSeconds/60)) * ($windowSeconds/60), 0);
    // Roll to an exact multiple of windowSeconds relative to the hour:
    $epoch = (int)$now->format('U');
    $windowStart = (new \DateTimeImmutable('@' . ($epoch - ($epoch % $windowSeconds))))->setTimezone(new \DateTimeZone('UTC'));

    $pdo->beginTransaction();
    $sel = $pdo->prepare("SELECT id, hits FROM rate_limits WHERE ip=? AND endpoint=? AND window_start=? FOR UPDATE");
    $sel->execute([$ipBin, $endpoint, $windowStart->format('Y-m-d H:i:s')]);
    $row = $sel->fetch();
    if ($row) {
        if ((int)$row['hits'] >= $limit) {
            $pdo->rollBack();
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Rate limit exceeded. Try again later.']);
            exit;
        }
        $upd = $pdo->prepare("UPDATE rate_limits SET hits = hits + 1 WHERE id=?");
        $upd->execute([$row['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO rate_limits (ip, endpoint, hits, window_start) VALUES (?,?,1,?)");
        $ins->execute([$ipBin, $endpoint, $windowStart->format('Y-m-d H:i:s')]);
    }
    $pdo->commit();
}
?>