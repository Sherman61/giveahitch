<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/status.php';

use function App\Auth\{require_login, current_user};
use function App\Status\from_db;

start_secure_session();
require_login();
$uid = (int) current_user()['id'];

$matchId = (int)($_GET['match_id'] ?? 0);
if ($matchId <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_input']); exit; }

$pdo = db();

try {
  $q = $pdo->prepare("
    SELECT m.*, r.deleted, du.display_name AS driver_name, pu.display_name AS passenger_name
    FROM ride_matches m
    JOIN rides r ON r.id=m.ride_id
    JOIN users du ON du.id=m.driver_user_id
    JOIN users pu ON pu.id=m.passenger_user_id
    WHERE m.id=:mid
  ");
  $q->execute([':mid'=>$matchId]);
  $m = $q->fetch(PDO::FETCH_ASSOC);
  if (!$m || (int)$m['deleted'] === 1) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

  $isDriver    = ($uid === (int)$m['driver_user_id']);
  $isPassenger = ($uid === (int)$m['passenger_user_id']);
  if (!$isDriver && !$isPassenger) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }

  // Only after completion
  $matchStatus = from_db($m['status']);
  $canRate = ($matchStatus === 'completed');

  // Did I already rate?
  $rq = $pdo->prepare("SELECT stars, comment, created_at FROM ride_ratings WHERE match_id=:mid AND rater_user_id=:u LIMIT 1");
  $rq->execute([':mid'=>$matchId, ':u'=>$uid]);
  $existing = $rq->fetch(PDO::FETCH_ASSOC) ?: null;

  // Who am I rating, label for UI
  $targetRole = $isDriver ? 'passenger' : 'driver';
  $targetUser = $isDriver ? ['id'=>(int)$m['passenger_user_id'],'name'=>$m['passenger_name']]
                          : ['id'=>(int)$m['driver_user_id'],'name'=>$m['driver_name']];

  echo json_encode([
    'ok'=>true,
    'can_rate'=> $canRate && !$existing,
    'already_rated'=> (bool)$existing,
    'existing'=> $existing ? [
      'stars'      => (int)$existing['stars'],
      'comment'    => $existing['comment'],
      'created_at' => $existing['created_at'],
    ] : null,
    'target_role'=> $targetRole,
    'target_user'=> $targetUser,
    'match_status'=> $matchStatus,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server']);
}
