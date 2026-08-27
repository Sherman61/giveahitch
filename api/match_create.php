<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/notifications.php';
require_once __DIR__ . '/../lib/rides.php';

use function App\Auth\{require_login, current_user, csrf_verify};
use function App\Rides\{map_joiner_roles, summarize_route};

start_secure_session();
require_login();

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));
$rideId = (int)($in['ride_id'] ?? 0);
if ($rideId <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

$pdo = db();
$pdo->beginTransaction();

/* Lock ride and read it */
$stmt = $pdo->prepare("SELECT id,user_id,type,status,from_text,to_text FROM rides WHERE id=:id AND deleted=0 FOR UPDATE");
$stmt->execute([':id'=>$rideId]);
$ride = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ride) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

$me = current_user();
$meId = (int)$me['id'];
if ($meId === (int)$ride['user_id']) { $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'own_ride']); exit; }

$roleMap = map_joiner_roles($ride, $meId);

/*
 * One row is kept per ride/member pair. A second click (or a browser retry)
 * must therefore be safe: return the already-pending request instead of
 * surfacing MySQL's unique-key "duplicate" error to the member.
 */
$existing = $pdo->prepare("SELECT id, status FROM ride_matches
  WHERE ride_id=:rid AND driver_user_id=:d AND passenger_user_id=:p
  LIMIT 1 FOR UPDATE");
$existing->execute([
  ':rid'=>$rideId,
  ':d'=>$roleMap['driver_user_id'],
  ':p'=>$roleMap['passenger_user_id'],
]);
$match = $existing->fetch(PDO::FETCH_ASSOC);

if ($match) {
  $status = strtolower((string)$match['status']);
  if (in_array($status, ['pending', 'accepted', 'confirmed', 'inprogress', 'completed'], true)) {
    $pdo->commit();
    echo json_encode([
      'ok'=>true,
      'status'=>$status === 'inprogress' ? 'in_progress' : $status,
      'match_id'=>(int)$match['id'],
      'already_requested'=>true,
    ]);
    exit;
  }

  // A withdrawn or rejected request may be submitted again; reuse the row so
  // it continues to satisfy the unique ride/member-pair constraint.
  $pdo->prepare("UPDATE ride_matches
    SET status='pending', confirmed_at=NULL, updated_at=NOW() WHERE id=:id")
    ->execute([':id'=>(int)$match['id']]);
  $matchId = (int)$match['id'];
} else {
  $ins = $pdo->prepare("
    INSERT INTO ride_matches(ride_id,driver_user_id,passenger_user_id,status)
    VALUES(:rid,:d,:p,'pending')
  ");
  try {
    $ins->execute([
      ':rid'=>$rideId,
      ':d'=>$roleMap['driver_user_id'],
      ':p'=>$roleMap['passenger_user_id'],
    ]);
    $matchId = (int)$pdo->lastInsertId();
  } catch (\PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode()==='23000') {
      /*
       * Two tabs (or the post-login retry and a click) can both see no row,
       * then race to insert it. The losing request should report the request
       * which the winner just created, never an opaque duplicate error.
       */
      $race = $pdo->prepare("SELECT id, status FROM ride_matches
        WHERE ride_id=:rid AND driver_user_id=:d AND passenger_user_id=:p
        LIMIT 1");
      $race->execute([
        ':rid'=>$rideId,
        ':d'=>$roleMap['driver_user_id'],
        ':p'=>$roleMap['passenger_user_id'],
      ]);
      if ($created = $race->fetch(PDO::FETCH_ASSOC)) {
        $createdStatus = strtolower((string)$created['status']);
        echo json_encode([
          'ok'=>true,
          'status'=>$createdStatus === 'inprogress' ? 'in_progress' : $createdStatus,
          'match_id'=>(int)$created['id'],
          'already_requested'=>true,
        ]);
        exit;
      }
      http_response_code(409); echo json_encode(['ok'=>false,'error'=>'request_conflict']); exit;
    }
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db']); exit;
  }
}

$pdo->commit();

try {
    $actorName = trim((string)($me['display_name'] ?? ''));
    $summary = summarize_route($ride);
    $title = 'New request for your ride';
    $body  = ($actorName !== '' ? $actorName : 'A member') . " asked to join $summary.";
    \App\Notifications\notify_ride_owner($pdo, $ride, $me, 'ride_match_requested', $title, $body, [
        'match_id' => $matchId ?? null,
        'status' => 'pending',
    ]);
} catch (\Throwable $e) {
    error_log('notifications:match_create ' . $e->getMessage());
}

echo json_encode(['ok'=>true]);
