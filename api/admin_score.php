<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/scoring.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/security_events.php';
use function App\Auth\{assert_csrf_and_get_input, require_admin};
use function App\Scoring\award;

$admin = require_admin();
$pdo = db();
\App\Security\rate_limit($pdo, 'admin_score', 30, 900);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $in = assert_csrf_and_get_input();

    $userId = (int)($in['user_id'] ?? 0);
    $points = (int)($in['points'] ?? 0);
    $reason = trim((string)($in['reason'] ?? ''));

    if ($userId <= 0 || $points === 0 || $reason === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'bad_input']);
        exit;
    }

    if (mb_strlen($reason) > 1000) {
        $reason = mb_substr($reason, 0, 1000);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, display_name, email, score FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('not_found');
        }

        award($pdo, $userId, $points, 'admin_adjustment', [
            'details' => $reason,
            'actor_user_id' => (int)$admin['id'],
            'metadata' => [
                'source' => 'admin_score_page',
                'adjustment_points' => $points,
            ],
        ]);

        $freshStmt = $pdo->prepare('SELECT score FROM users WHERE id = :id LIMIT 1');
        $freshStmt->execute([':id' => $userId]);
        $freshScore = (int)($freshStmt->fetchColumn() ?: 0);

        \App\SecurityEvents\log_event($pdo, 'admin_score_adjusted', 'warning', [
            'actor_user_id' => (int)$admin['id'],
            'target_user_id' => $userId,
            'details' => $reason,
            'metadata' => [
                'points' => $points,
                'new_score' => $freshScore,
            ],
        ]);

        $pdo->commit();
        echo json_encode([
            'ok' => true,
            'user' => [
                'id' => (int)$user['id'],
                'display_name' => (string)($user['display_name'] ?? ''),
                'email' => (string)($user['email'] ?? ''),
                'score' => $freshScore,
            ],
        ]);
        exit;
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code($e->getMessage() === 'not_found' ? 404 : 400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'server']);
        exit;
    }
}

$query = trim((string)($_GET['q'] ?? ''));
$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));

$params = [];
$where = '';
if ($query !== '') {
    $where = '
        WHERE (
            CAST(id AS CHAR) = :exact_id
            OR display_name LIKE :query
            OR email LIKE :query
            OR username LIKE :query
        )
    ';
    $params[':exact_id'] = $query;
    $params[':query'] = '%' . $query . '%';
}

$sql = "
    SELECT id, display_name, email, username, score, created_at
    FROM users
    {$where}
    ORDER BY created_at DESC, id DESC
    LIMIT {$limit}
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['ok' => true, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server']);
}
