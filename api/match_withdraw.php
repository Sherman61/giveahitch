<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/status.php';
require_once __DIR__ . '/../lib/notifications.php';

use function App\Auth\{require_login, csrf_verify};
use function App\Status\{from_db, to_db};

start_secure_session();
$me = require_login();
$uid = (int)($me['id'] ?? 0);

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$matchId = (int)($in['match_id'] ?? 0);
if ($matchId <= 0) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'bad_input']); exit;
}

$pdo = db();
$pdo->beginTransaction();

try {
  // Only allow withdrawal if I'm a participant AND it's still pending
  $q = $pdo->prepare("SELECT m.id, m.ride_id, m.status, m.driver_user_id, m.passenger_user_id, r.user_id AS owner_id, r.type AS ride_type, r.from_text, r.to_text FROM ride_matches m JOIN rides r ON r.id = m.ride_id AND r.deleted=0 WHERE m.id = :mid FOR UPDATE");
  $q->execute([':mid'=>$matchId]);
  $m = $q->fetch(PDO::FETCH_ASSOC);

  if (!$m) { throw new RuntimeException('not_found'); }
  $m['status'] = from_db($m['status']);
  if ($m['status'] !== 'pending') { throw new RuntimeException('not_pending'); }
  if ($uid !== (int)$m['driver_user_id'] && $uid !== (int)$m['passenger_user_id']) {
    throw new RuntimeException('forbidden');
  }

  $pdo->prepare("UPDATE ride_matches SET status=:status, updated_at=NOW() WHERE id=:mid")
      ->execute([':status'=>to_db('cancelled'), ':mid'=>$matchId]);

  $pdo->commit();

  echo json_encode(['ok'=>true]);

  try {
    $rideInfo = [
      'id' => (int)$m['ride_id'],
      'user_id' => (int)$m['owner_id'],
      'type' => $m['ride_type'] ?? null,
      'from_text' => $m['from_text'] ?? null,
      'to_text' => $m['to_text'] ?? null,
    ];
    $actorName = trim((string)($me['display_name'] ?? ''));
    $from = trim((string)($rideInfo['from_text'] ?? ''));
    $to   = trim((string)($rideInfo['to_text'] ?? ''));
    $summary = $from && $to ? "$from → $to" : ($from ?: $to ?: 'your ride');
    $title = 'Ride request withdrawn';
    $body  = ($actorName !== '' ? $actorName : 'A member') . " withdrew their request for $summary.";
    \App\Notifications\notify_ride_owner($pdo, $rideInfo, $me, 'ride_match_withdrawn', $title, $body, [
      'match_id' => $matchId,
      'status' => 'cancelled',
    ]);
  } catch (\Throwable $notifyError) {
    error_log('notifications:match_withdraw ' . $notifyError->getMessage());
  }
} catch (RuntimeException $e) {
  $pdo->rollBack();
  $code = $e->getMessage();
  $map  = ['not_found'=>404,'forbidden'=>403,'not_pending'=>409];
  http_response_code($map[$code] ?? 400);
  echo json_encode(['ok'=>false,'error'=>$code]);
} catch (\Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server']);
}
