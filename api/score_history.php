<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/scoring.php';

use function App\Auth\require_login;
use function App\Scoring\rules;

start_secure_session();
$viewer = require_login();
$uid = (int)$viewer['id'];

$pdo = db();

try {
    $userStmt = $pdo->prepare('SELECT id, display_name, score, created_at FROM users WHERE id = :user_id LIMIT 1');
    $userStmt->execute([':user_id' => $uid]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        throw new RuntimeException('not_found');
    }

    $events = [];
    $trackedTotal = 0;
    $trackingAvailable = true;

    try {
        $eventStmt = $pdo->prepare("
            SELECT
                id,
                points_delta,
                reason_key,
                reason_label,
                details,
                related_ride_id,
                related_match_id,
                related_rating_id,
                actor_user_id,
                metadata,
                created_at
            FROM score_events
            WHERE user_id = :user_id
            ORDER BY created_at DESC, id DESC
            LIMIT 200
        ");
        $eventStmt->execute([':user_id' => $uid]);
        while ($row = $eventStmt->fetch(PDO::FETCH_ASSOC)) {
            $trackedTotal += (int)$row['points_delta'];
            $metadata = null;
            if (!empty($row['metadata'])) {
                $decoded = json_decode((string)$row['metadata'], true);
                if (is_array($decoded)) {
                    $metadata = $decoded;
                }
            }
            $events[] = [
                'id' => (int)$row['id'],
                'points_delta' => (int)$row['points_delta'],
                'reason_key' => (string)$row['reason_key'],
                'reason_label' => (string)$row['reason_label'],
                'details' => $row['details'],
                'related_ride_id' => $row['related_ride_id'] !== null ? (int)$row['related_ride_id'] : null,
                'related_match_id' => $row['related_match_id'] !== null ? (int)$row['related_match_id'] : null,
                'related_rating_id' => $row['related_rating_id'] !== null ? (int)$row['related_rating_id'] : null,
                'actor_user_id' => $row['actor_user_id'] !== null ? (int)$row['actor_user_id'] : null,
                'metadata' => $metadata,
                'created_at' => $row['created_at'],
            ];
        }
    } catch (\PDOException $e) {
        if (stripos($e->getMessage(), 'score_events') === false) {
            throw $e;
        }
        $trackingAvailable = false;
    }

    echo json_encode([
        'ok' => true,
        'user' => [
            'id' => (int)$user['id'],
            'display_name' => (string)$user['display_name'],
            'score' => (int)$user['score'],
            'created_at' => $user['created_at'],
        ],
        'tracking_available' => $trackingAvailable,
        'tracked_total' => $trackedTotal,
        'unexplained_balance' => (int)$user['score'] - $trackedTotal,
        'rules' => rules(),
        'events' => $events,
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code($e->getMessage() === 'not_found' ? 404 : 400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server']);
}
