<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/status.php';
require_once __DIR__ . '/../lib/notifications.php';

use function App\Auth\{require_login, current_user, csrf_verify};
use function App\Status\{from_db, to_db};

start_secure_session();
require_login();

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$rideId = (int)($in['ride_id'] ?? 0);
if ($rideId <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

$pdo = db();
$pdo->beginTransaction();

/* lock the ride row */
$stmt = $pdo->prepare("SELECT id, user_id, type, status, from_text, to_text FROM rides WHERE id=:id AND deleted=0 FOR UPDATE");
$stmt->execute([':id' => $rideId]);
$ride = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ride) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
$ride['status'] = from_db($ride['status'] ?? 'open');
if ($ride['status'] !== 'open') { $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }

$me = current_user();
if ((int)$me['id'] === (int)$ride['user_id']) {
  $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'own_ride']); exit;
}

/* map roles */
if ($ride['type'] === 'offer') {
  $driver = (int)$ride['user_id'];
  $pass   = (int)$me['id'];
} else {
  $driver = (int)$me['id'];
  $pass   = (int)$ride['user_id'];
}

/* if already fully matched, block; but allow multiple pendings */
$final = $pdo->prepare("SELECT id FROM ride_matches WHERE ride_id=:rid AND status IN (" . implode(',', array_map(fn(string $s) => $pdo->quote(to_db($s)), ['accepted','in_progress','completed'])) . ") LIMIT 1");
$final->execute([':rid' => $rideId]);
if ($final->fetch()) { $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'already_final']); exit; }

/* prevent duplicate requests from the same pair */
$dupe = $pdo->prepare("SELECT id, status FROM ride_matches
                       WHERE ride_id=:rid AND driver_user_id=:d AND passenger_user_id=:p
                       ORDER BY id DESC LIMIT 1");
$dupe->execute([':rid'=>$rideId, ':d'=>$driver, ':p'=>$pass]);
if ($row = $dupe->fetch(PDO::FETCH_ASSOC)) {
  $row['status'] = from_db($row['status']);
  if (in_array($row['status'], ['pending','accepted','in_progress','completed'], true)) {
    $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'already_requested']); exit;
  }
}

/* create PENDING */
$ins = $pdo->prepare("INSERT INTO ride_matches(ride_id,driver_user_id,passenger_user_id,status)
                      VALUES(:rid,:d,:p,:status)");
$ins->execute([':rid'=>$rideId, ':d'=>$driver, ':p'=>$pass, ':status'=>to_db('pending')]);

$matchId = (int)$pdo->lastInsertId();

/* DO NOT flip ride status here */
$pdo->commit();

try {
    $actorName = trim((string)($me['display_name'] ?? ''));
    $from = trim((string)($ride['from_text'] ?? ''));
    $to   = trim((string)($ride['to_text'] ?? ''));
    $summary = $from && $to ? "$from → $to" : ($from ?: $to ?: 'your ride');
    $title = 'New request for your ride';
    $body  = ($actorName !== '' ? $actorName : 'A member') . " asked to join $summary.";
    \App\Notifications\notify_ride_owner($pdo, $ride, $me, 'ride_match_requested', $title, $body, [
        'match_id' => $matchId,
        'status' => 'pending',
    ]);
} catch (\Throwable $e) {
    error_log('notifications:match_request ' . $e->getMessage());
}

echo json_encode(['ok'=>true,'status'=>'pending','match_id'=>$matchId]);
