<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/status.php';

use function App\Auth\{require_login, current_user, csrf_verify};
use function App\Status\{from_db, to_db};

start_secure_session();
require_login();
$uid = (int) current_user()['id'];

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$rideId  = (int)($in['ride_id']  ?? 0);
$matchId = (int)($in['match_id'] ?? 0);
if ($rideId<=0 || $matchId<=0) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'bad_input']);
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Lock the ride and chosen match together.
    $rideStmt = $pdo->prepare("SELECT id, user_id, status FROM rides WHERE id=:rid AND deleted=0 FOR UPDATE");
    $rideStmt->execute([':rid'=>$rideId]);
    $ride = $rideStmt->fetch(PDO::FETCH_ASSOC);
    if (!$ride) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'ride_not_found']); exit; }
    $ride['status'] = from_db($ride['status']);

    $matchStmt = $pdo->prepare("
        SELECT id, ride_id, driver_user_id, passenger_user_id, status
        FROM ride_matches WHERE id=:mid AND ride_id=:rid FOR UPDATE
    ");
    $matchStmt->execute([':mid'=>$matchId, ':rid'=>$rideId]);
    $match = $matchStmt->fetch(PDO::FETCH_ASSOC);
    if (!$match) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'match_not_found']); exit; }
    $match['status'] = from_db($match['status']);

    // Must be the driver or the passenger to mark complete
    if ($uid !== (int)$match['driver_user_id'] && $uid !== (int)$match['passenger_user_id']) {
        $pdo->rollBack(); http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
    }

    // Acceptable states before completing
    if (!in_array($match['status'], ['confirmed','in_progress'], true)) {
        $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'bad_state']); exit;
    }

    // Mark match & ride completed
    $pdo->prepare("UPDATE ride_matches SET status=:status, updated_at=NOW() WHERE id=:mid")
        ->execute([':status'=>to_db('completed'), ':mid'=>$matchId]);

    $pdo->prepare("UPDATE rides SET status=:status WHERE id=:rid")
        ->execute([':status'=>to_db('completed'), ':rid'=>$rideId]);

    // Increment trip counters at completion.
    $pdo->prepare("UPDATE users SET rides_given_count = rides_given_count + 1 WHERE id=:id")
        ->execute([':id'=>$match['driver_user_id']]);
    $pdo->prepare("UPDATE users SET rides_received_count = rides_received_count + 1 WHERE id=:id")
        ->execute([':id'=>$match['passenger_user_id']]);

    $pdo->commit();
    echo json_encode(['ok'=>true,'status'=>'completed']);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server','detail'=>$e->getMessage()]);
}
