<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}
require_once __DIR__ . '/../config/db.php'; // must define $pdo (PDO)
require_once __DIR__ . '/../lib/mailer.php';

// Built-in classes referenced explicitly below.

use function App\Mailer\send_password_reset_code;
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
/**
 * Output JSON and terminate the script.
 *
 * @param array<string,mixed> $arr
 */
function json_out(array $arr, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Ensure the password_resets table exists and return its column list.
 *
 * @return string[]
 */
function password_reset_columns(PDO $pdo): array
{
    try {
        $columnStmt = $pdo->query('SHOW COLUMNS FROM password_resets');
        if ($columnStmt !== false) {
            return $columnStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (PDOException $schemaErr) {
        // Table missing – create it using the expected schema and retry.
        if ($schemaErr->getCode() === '42S02') { // base table or view not found
            $createSql = <<<SQL
CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    code CHAR(6) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ip VARBINARY(16) DEFAULT NULL,
    ua VARCHAR(255) DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_expires (email, expires_at),
    KEY idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
            $pdo->exec($createSql);
            $columnStmt = $pdo->query('SHOW COLUMNS FROM password_resets');
            if ($columnStmt !== false) {
                return $columnStmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }

        error_log('forgot_password_request: failed to introspect password_resets: ' . $schemaErr->getMessage());
    } catch (Throwable $schemaErr) {
        error_log('forgot_password_request: unexpected schema error: ' . $schemaErr->getMessage());
    }

    return [];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        json_out([
            'ok' => false,
            'error' => 'Method not allowed',
        ], 405);
    }

    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody ?: '[]', true);
    if (!is_array($body)) {
        json_out(['ok' => false, 'error' => 'Invalid request body'], 400);
    }
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

    // Insert record. Older databases might not have the optional ip/ua columns yet,
    // so build the insert dynamically based on the existing schema.
    $columns = ['user_id', 'email', 'code'];
    $values = [$user['id'], $user['email'], $code];

    try {
        $columnStmt = $pdo->query('SHOW COLUMNS FROM password_resets');
        $existingColumns = $columnStmt !== false ? $columnStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Throwable $schemaErr) {
        $existingColumns = [];
    }

    if (in_array('ip', $existingColumns, true)) {
        $columns[] = 'ip';
        $values[] = $ip;
    }
    if (in_array('ua', $existingColumns, true)) {
        $columns[] = 'ua';
        $values[] = $ua;
    }

    $columns[] = 'expires_at';
    $values[] = $expiresAt;

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = sprintf('INSERT INTO password_resets (%s) VALUES (%s)', implode(', ', $columns), $placeholders);
    $pdo->prepare($sql)->execute($values);

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
