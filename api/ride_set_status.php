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

$rideId = (int)($in['ride_id'] ?? 0);
$newStatus = (string)($in['status'] ?? '');

$allowed = ['open','matched','in_progress','completed','cancelled'];
if ($rideId<=0 || !in_array($newStatus, $allowed, true)) {
  http_response_code(422);
  echo json_encode(['ok'=>false, 'error'=>'bad_input']); exit;
}

$pdo = db();
$pdo->beginTransaction();

try {
  // Lock the ride row
  $rq = $pdo->prepare("SELECT * FROM rides WHERE id=:id AND deleted=0 FOR UPDATE");
  $rq->execute([':id'=>$rideId]);
  $ride = $rq->fetch(PDO::FETCH_ASSOC);
  if (!$ride) { throw new RuntimeException('not_found'); }
  if ((int)$ride['user_id'] !== $uid) { throw new RuntimeException('forbidden'); }

  $currentStatus = from_db($ride['status'] ?? 'open');

  if ($newStatus === $currentStatus) {
    $pdo->commit();
    echo json_encode(['ok'=>true, 'status'=>$newStatus]);
    return;
  }

  $transitions = [
    'open'        => ['cancelled'],
    'matched'     => ['in_progress','completed','cancelled'],
    'in_progress' => ['completed','cancelled'],
  ];
  if (!isset($transitions[$currentStatus]) || !in_array($newStatus, $transitions[$currentStatus], true)) {
    throw new RuntimeException('illegal_transition');
  }
  // If moving to in_progress or completed, ensure there's an active match
  $driverId    = (int)$ride['user_id'];
  $passengerId = 0;
  //does this belong here?
$pdo->prepare("UPDATE users SET score = score + 50 WHERE id IN (:d,:p)")
    ->execute([':d'=>$driverId, ':p'=>$passengerId]);

  
  $activeMatch = null;
  if (in_array($newStatus, ['in_progress','completed'], true)) {
    $statusesForSelect = ['accepted','confirmed','in_progress','completed'];
    $quoted = array_map(fn(string $s) => $pdo->quote(to_db($s)), $statusesForSelect);
    $matchSql = "
      SELECT * FROM ride_matches
      WHERE ride_id=:rid AND status IN (" . implode(',', $quoted) . ")
      ORDER BY COALESCE(confirmed_at, updated_at, created_at) DESC
      LIMIT 1
      FOR UPDATE
    ";
    $mq = $pdo->prepare($matchSql);
    $mq->execute([':rid'=>$rideId]);
    $activeMatch = $mq->fetch(PDO::FETCH_ASSOC);
    if ($activeMatch) {
      $activeMatch['status'] = from_db($activeMatch['status']);
    }
    if (!$activeMatch || ($activeMatch['status'] === 'completed' && $newStatus !== 'completed')) {
      throw new RuntimeException('no_active_match');
    }
  }

  // Update ride status first
  $pdo->prepare("UPDATE rides SET status=:s WHERE id=:id")
      ->execute([':s'=>to_db($newStatus), ':id'=>$rideId]);

  if ($newStatus === 'in_progress' && $activeMatch && $activeMatch['status'] !== 'in_progress') {
    $pdo->prepare("UPDATE ride_matches SET status=:s, updated_at=NOW() WHERE id=:mid")
        ->execute([':s'=>to_db('in_progress'), ':mid'=>$activeMatch['id']]);
  }

  // If completing: close the accepted/confirmed match and bump counters
  if ($newStatus === 'completed' && $activeMatch) {
    if ($activeMatch['status'] !== 'completed') {
      $pdo->prepare("UPDATE ride_matches SET status=:s, updated_at=NOW() WHERE id=:mid")
          ->execute([':s'=>to_db('completed'), ':mid'=>$activeMatch['id']]);

      $driverId    = (int)$activeMatch['driver_user_id'];
      $passengerId = (int)$activeMatch['passenger_user_id'];

      if ($driverId > 0) {
        $pdo->prepare("UPDATE users SET rides_given_count = rides_given_count + 1 WHERE id=:u")
            ->execute([':u'=>$driverId]);
      }
      if ($passengerId > 0) {
        $pdo->prepare("UPDATE users SET rides_received_count = rides_received_count + 1 WHERE id=:u")
            ->execute([':u'=>$passengerId]);
      }
    }
  }

  if ($newStatus === 'cancelled') {
    $statusesToCancel = ['pending','accepted','confirmed','in_progress'];
    $quotedCancel = array_map(fn(string $s) => $pdo->quote(to_db($s)), $statusesToCancel);
    $inList = implode(',', $quotedCancel);
    $pdo->prepare("UPDATE ride_matches SET status=:cancelled, updated_at=NOW() WHERE ride_id=:rid AND status IN ($inList)")
        ->execute([':cancelled'=>to_db('cancelled'), ':rid'=>$rideId]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true, 'status'=>$newStatus]);
} catch (RuntimeException $e) {
  $pdo->rollBack();
  $code = $e->getMessage();
  $map  = ['not_found'=>404,'forbidden'=>403,'illegal_transition'=>409,'no_active_match'=>409];
  http_response_code($map[$code] ?? 400);
  echo json_encode(['ok'=>false,'error'=>$code]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server']);
}
