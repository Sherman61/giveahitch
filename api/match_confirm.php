<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

use function App\Auth\{require_login, current_user, csrf_verify};

start_secure_session();
require_login();
$me = current_user();
$uid = (int)$me['id'];

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$rideId  = (int)($in['ride_id']  ?? 0);
$matchId = (int)($in['match_id'] ?? 0);
if ($rideId <= 0 || $matchId <= 0) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'bad_input']);
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Lock ride first
    $rq = $pdo->prepare("
        SELECT id, user_id, type, status
        FROM rides
        WHERE id = :id AND deleted = 0
        FOR UPDATE
    ");
    $rq->execute([':id'=>$rideId]);
    $ride = $rq->fetch(PDO::FETCH_ASSOC);

    if (!$ride) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'not_found']);
        exit;
    }
    if ((int)$ride['user_id'] !== $uid) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'forbidden']);
        exit;
    }
    if ($ride['status'] !== 'open') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok'=>false,'error'=>'not_open']);
        exit;
    }

    // Lock chosen match
    $mq = $pdo->prepare("
        SELECT id, ride_id, driver_user_id, passenger_user_id, status
        FROM ride_matches
        WHERE id = :mid AND ride_id = :rid
        FOR UPDATE
    ");
    $mq->execute([':mid'=>$matchId, ':rid'=>$rideId]);
    $match = $mq->fetch(PDO::FETCH_ASSOC);

    if (!$match) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'match_not_found']);
        exit;
    }
    if ($match['status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok'=>false,'error'=>'not_pending']);
        exit;
    }

    // Accept chosen match
    $pdo->prepare("
        UPDATE ride_matches
        SET status = 'confirmed', confirmed_at = NOW()
        WHERE id = :mid
    ")->execute([':mid'=>$matchId]);

    // Reject all other pending matches for this ride
    $pdo->prepare("
        UPDATE ride_matches
        SET status = 'rejected'
        WHERE ride_id = :rid AND status = 'pending' AND id <> :mid
    ")->execute([':rid'=>$rideId, ':mid'=>$matchId]);

    // Update ride status and (if exists) confirmed_match_id
    try {
        $pdo->prepare("
            UPDATE rides
            SET status = 'matched', confirmed_match_id = :mid
            WHERE id = :rid
        ")->execute([':mid'=>$matchId, ':rid'=>$rideId]);
    } catch (\PDOException $e) {
        if (stripos($e->getMessage(), 'Unknown column') !== false) {
            $pdo->prepare("
                UPDATE rides SET status = 'matched' WHERE id = :rid
            ")->execute([':rid'=>$rideId]);
        } else {
            throw $e;
        }
    }

    // ===== NEW: bump both users' scores by +100 on confirm =====
    $driverId    = (int)$match['driver_user_id'];
    $passengerId = (int)$match['passenger_user_id'];

    // Both participants get +100
    $bump = $pdo->prepare("UPDATE users SET score = score + 100 WHERE id IN (:a, :b)");
    $bump->execute([':a'=>$driverId, ':b'=>$passengerId]);

    $pdo->commit();

    echo json_encode([
        'ok'     => true,
        'status' => 'confirmed',
        'ride'   => ['id'=>(int)$rideId, 'status'=>'matched'],
        'match'  => ['id'=>(int)$matchId, 'status'=>'confirmed'],
        'bumped_users' => [$driverId, $passengerId],
        'score_delta'  => 100
    ]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server','detail'=>$e->getMessage()]);
}
