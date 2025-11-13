<?php declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../../vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class)) {
  Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/error_log.php';

\App\Err\init_error_logging();

/**
 * Consistent JSON output helper.
 *
 * @param array<string,mixed> $payload
 */
function json_out(array $payload, int $status = 200): void {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * Guarantee api_tokens schema exists and matches expectations.
 */
function ensure_api_tokens_table(PDO $pdo): void {
  static $ensured = false;
  if ($ensured) {
    return;
  }

  try {
    $columnsStmt = $pdo->query('SHOW COLUMNS FROM api_tokens');
    if ($columnsStmt === false) {
      throw new RuntimeException('Could not inspect api_tokens columns');
    }

    $columns = [];
    while ($col = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
      if (!isset($col['Field'])) {
        continue;
      }
      $columns[$col['Field']] = strtolower((string)($col['Type'] ?? ''));
    }

    if (!array_key_exists('token_hash', $columns)) {
      if (array_key_exists('token', $columns)) {
        $pdo->exec('ALTER TABLE api_tokens CHANGE COLUMN token token_hash CHAR(64) NOT NULL');
      } else {
        $pdo->exec('ALTER TABLE api_tokens ADD COLUMN token_hash CHAR(64) NOT NULL AFTER user_id');
      }
    }

    if (isset($columns['user_id']) && strpos($columns['user_id'], 'bigint') === false) {
      $pdo->exec('ALTER TABLE api_tokens MODIFY COLUMN user_id BIGINT UNSIGNED NOT NULL');
    }

    $indexStmt = $pdo->query('SHOW INDEX FROM api_tokens');
    $hasTokenHashUnique = false;
    if ($indexStmt !== false) {
      while ($idx = $indexStmt->fetch(PDO::FETCH_ASSOC)) {
        if (($idx['Column_name'] ?? '') === 'token_hash' && (int)($idx['Non_unique'] ?? 1) === 0) {
          $hasTokenHashUnique = true;
          break;
        }
      }
    }
    if (!$hasTokenHashUnique) {
      $pdo->exec('ALTER TABLE api_tokens ADD UNIQUE KEY idx_token_hash (token_hash)');
    }

    $ensured = true;
    return;
  } catch (PDOException $e) {
    if ($e->getCode() !== '42S02') {
      throw $e;
    }
  }

  $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS api_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_expires (user_id, expires_at),
  UNIQUE KEY idx_token_hash (token_hash),
  CONSTRAINT fk_api_tokens_users FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

  $ensured = true;
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['error' => 'Method not allowed'], 405);
  }

  $rawInput = file_get_contents('php://input') ?: '';
  $payload = json_decode($rawInput, true);
  if (!is_array($payload)) {
    json_out(['error' => 'Invalid JSON body'], 422);
  }

  $email = filter_var(strtolower(trim((string)($payload['email'] ?? ''))), FILTER_VALIDATE_EMAIL);
  $password = $payload['password'] ?? '';
  if (!$email || !is_string($password) || $password === '') {
    json_out(['error' => 'Email and password are required'], 422);
  }

  $pdo = db();
  $stmt = $pdo->prepare('SELECT id, display_name AS name, email, password_hash FROM users WHERE email = :email LIMIT 1');
  $stmt->execute([':email' => $email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user || !password_verify($password, $user['password_hash'])) {
    json_out(['error' => 'Invalid credentials'], 401);
  }

  ensure_api_tokens_table($pdo);

  $token = bin2hex(random_bytes(32));
  $tokenHash = hash('sha256', $token);
  $expiresAt = (new DateTimeImmutable('+60 minutes'))->format('Y-m-d H:i:s');

  $insert = $pdo->prepare(
    'INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
  );
  $insert->execute([
    ':user_id' => (int) $user['id'],
    ':token_hash' => $tokenHash,
    ':expires_at' => $expiresAt,
  ]);

  json_out([
    'token' => $token,
    'expires_at' => $expiresAt,
    'user' => [
      'id' => (int) $user['id'],
      'name' => $user['name'],
      'email' => $user['email'],
    ],
  ]);
} catch (Throwable $e) {
  error_log('mobile login failure: ' . $e->getMessage());
  json_out(['error' => 'Server error'], 500);
}
