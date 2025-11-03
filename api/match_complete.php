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

    // Lock ride & match
    $ride = $pdo->prepare("SELECT id, user_id, status FROM rides WHERE id=:rid AND deleted=0 FOR UPDATE");
    $ride->execute([':rid'=>$rideId]);
    $r = $ride->fetch(PDO::FETCH_ASSOC);
    if (!$r) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'ride_not_found']); exit; }
    $r['status'] = from_db($r['status']);

    $match = $pdo->prepare("
        SELECT id, ride_id, driver_user_id, passenger_user_id, status
        FROM ride_matches WHERE id=:mid AND ride_id=:rid FOR UPDATE
    ");
    $match->execute([':mid'=>$matchId, ':rid'=>$rideId]);
    $m = $match->fetch(PDO::FETCH_ASSOC);
    if (!$m) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'match_not_found']); exit; }
    $m['status'] = from_db($m['status']);

    // Must be the driver or the passenger to mark complete
    if ($uid !== (int)$m['driver_user_id'] && $uid !== (int)$m['passenger_user_id']) {
        $pdo->rollBack(); http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
    }

    // Acceptable states before completing
    if (!in_array($m['status'], ['confirmed','in_progress'], true)) {
        $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'bad_state']); exit;
    }

    // Mark match & ride completed
    $pdo->prepare("UPDATE ride_matches SET status=:status, updated_at=NOW() WHERE id=:mid")
        ->execute([':status'=>to_db('completed'), ':mid'=>$matchId]);

    $pdo->prepare("UPDATE rides SET status=:status WHERE id=:rid")
        ->execute([':status'=>to_db('completed'), ':rid'=>$rideId]);

    // Optionally increment given/received counters *at completion*:
    $pdo->prepare("UPDATE users SET rides_given_count = rides_given_count + 1 WHERE id=:id")
        ->execute([':id'=>$m['driver_user_id']]);
    $pdo->prepare("UPDATE users SET rides_received_count = rides_received_count + 1 WHERE id=:id")
        ->execute([':id'=>$m['passenger_user_id']]);

    $pdo->commit();
    echo json_encode(['ok'=>true,'status'=>'completed']);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server','detail'=>$e->getMessage()]);
}
