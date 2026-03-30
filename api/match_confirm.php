<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/status.php';
require_once __DIR__ . '/../lib/scoring.php';
require_once __DIR__ . '/../lib/rides.php';
use function App\Auth\{require_login, current_user, csrf_verify};
use function App\Rides\broadcast_ride_update;
use function App\Scoring\{award_many, points_for};
use function App\Status\{from_db, to_db};

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
    $ride['status'] = from_db($ride['status'] ?? 'open');
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

    // Lock the chosen pending join request.
    $matchStmt = $pdo->prepare("
        SELECT id, ride_id, driver_user_id, passenger_user_id, status
        FROM ride_matches
        WHERE id = :mid AND ride_id = :rid
        FOR UPDATE
    ");
    $matchStmt->execute([':mid'=>$matchId, ':rid'=>$rideId]);
    $match = $matchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$match) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'match_not_found']);
        exit;
    }
    $match['status'] = from_db($match['status']);
    if ($match['status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok'=>false,'error'=>'not_pending']);
        exit;
    }

    // Confirm the chosen match.
    $pdo->prepare("
        UPDATE ride_matches
        SET status = :status, confirmed_at = NOW()
        WHERE id = :mid
    ")->execute([':status'=>to_db('confirmed'), ':mid'=>$matchId]);

    // Reject the other pending join requests for this ride.
    $pdo->prepare("
        UPDATE ride_matches
        SET status = :status
        WHERE ride_id = :rid AND status = :pending AND id <> :mid
    ")->execute([':status'=>to_db('rejected'), ':pending'=>to_db('pending'), ':rid'=>$rideId, ':mid'=>$matchId]);

    // Update the ride to point at the confirmed match.
    try {
        $pdo->prepare("
            UPDATE rides
            SET status = :status, confirmed_match_id = :mid
            WHERE id = :rid
        ")->execute([':status'=>to_db('matched'), ':mid'=>$matchId, ':rid'=>$rideId]);
    } catch (\PDOException $e) {
        if (stripos($e->getMessage(), 'Unknown column') !== false) {
            $pdo->prepare("
                UPDATE rides SET status = :status WHERE id = :rid
            ")->execute([':status'=>to_db('matched'), ':rid'=>$rideId]);
        } else {
            throw $e;
        }
    }

    $driverId    = (int)$match['driver_user_id'];
    $passengerId = (int)$match['passenger_user_id'];

    $scoreDelta = points_for('match_confirmed');
    award_many($pdo, [$driverId, $passengerId], $scoreDelta, 'match_confirmed', [
        'ride_id' => $rideId,
        'match_id' => $matchId,
        'actor_user_id' => $uid,
        'details' => 'Ride owner confirmed this match.',
        'metadata' => [
            'driver_user_id' => $driverId,
            'passenger_user_id' => $passengerId,
        ],
    ]);

    $pdo->commit();
    broadcast_ride_update($rideId, [(int)$ride['user_id'], $driverId, $passengerId], 'match_confirmed');

    echo json_encode([
        'ok'     => true,
        'status' => 'confirmed',
        'ride'   => ['id'=>(int)$rideId, 'status'=>'matched'],
        'match'  => ['id'=>(int)$matchId, 'status'=>'confirmed'],
        'bumped_users' => array_values(array_unique([$driverId, $passengerId])),
        'score_delta'  => $scoreDelta
    ]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server','detail'=>$e->getMessage()]);
}
