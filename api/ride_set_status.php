<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

use function App\Auth\{require_login, current_user, csrf_verify};

start_secure_session();
require_login();
$uid = (int) current_user()['id'];

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$rideId = (int)($in['ride_id'] ?? 0);
$newStatus = (string)($in['status'] ?? '');

$allowed = ['open','in_progress','completed','cancelled'];
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

  // Update ride status first
  $pdo->prepare("UPDATE rides SET status=:s WHERE id=:id")
      ->execute([':s'=>$newStatus, ':id'=>$rideId]);

  // If completing: close the accepted match (or in_progress) and bump counters
  if ($newStatus === 'completed') {
    // Find most recent accepted/in_progress/confirmed match for this ride
    $mq = $pdo->prepare("
      SELECT * FROM ride_matches
      WHERE ride_id=:rid AND status IN ('accepted','in_progress','completed')
      ORDER BY COALESCE(confirmed_at, updated_at, created_at) DESC
      LIMIT 1
      FOR UPDATE
    ");
    $mq->execute([':rid'=>$rideId]);
    $m = $mq->fetch(PDO::FETCH_ASSOC);
    if ($m) {
      // Mark match completed
      $pdo->prepare("UPDATE ride_matches SET status='completed', updated_at=NOW() WHERE id=:mid")
          ->execute([':mid'=>$m['id']]);

      // Bump driver given & passenger received
      $driverId    = (int)$m['driver_user_id'];
      $passengerId = (int)$m['passenger_user_id'];

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

  $pdo->commit();
  echo json_encode(['ok'=>true, 'status'=>$newStatus]);
} catch (RuntimeException $e) {
  $pdo->rollBack();
  $code = $e->getMessage();
  $map  = ['not_found'=>404,'forbidden'=>403];
  http_response_code($map[$code] ?? 400);
  echo json_encode(['ok'=>false,'error'=>$code]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server']);
}
