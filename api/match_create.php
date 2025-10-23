<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/notifications.php';

use function App\Auth\{require_login, current_user, csrf_verify};

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

/* Map roles: offer => owner is driver; request => owner is passenger */
if ($ride['type'] === 'offer') {           // owner is driver, me is passenger
    $driver = (int)$ride['user_id'];
    $pass   = $meId;
} else {                                    // 'request' => owner is passenger, me is driver
    $driver = $meId;
    $pass   = (int)$ride['user_id'];
}

/* Insert PENDING; unique index prevents duplicates from same pair */
$ins = $pdo->prepare("
  INSERT INTO ride_matches(ride_id,driver_user_id,passenger_user_id,status)
  VALUES(:rid,:d,:p,'pending')
");
try {
  $ins->execute([':rid'=>$rideId, ':d'=>$driver, ':p'=>$pass]);
  $matchId = (int)$pdo->lastInsertId();
} catch (\PDOException $e) {
  // duplicate or other error
  $pdo->rollBack();
  if ($e->getCode()==='23000') { // duplicate
    http_response_code(409); echo json_encode(['ok'=>false,'error'=>'duplicate']); exit;
  }
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db']); exit;
}

$pdo->commit();

try {
    $actorName = trim((string)($me['display_name'] ?? ''));
    $from = trim((string)($ride['from_text'] ?? ''));
    $to   = trim((string)($ride['to_text'] ?? ''));
    $summary = $from && $to ? "$from → $to" : ($from ?: $to ?: 'your ride');
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
