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

$matchId = (int)($in['match_id'] ?? 0);
if ($matchId <= 0) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'bad_input']); exit;
}

$pdo = db();
$pdo->beginTransaction();

try {
  // Only allow withdrawal if I'm a participant AND it's still pending
  $q = $pdo->prepare("
    SELECT m.id, m.ride_id, m.status, m.driver_user_id, m.passenger_user_id, r.user_id AS owner_id
    FROM ride_matches m
    JOIN rides r ON r.id = m.ride_id AND r.deleted=0
    WHERE m.id = :mid FOR UPDATE
  ");
  $q->execute([':mid'=>$matchId]);
  $m = $q->fetch(PDO::FETCH_ASSOC);

  if (!$m) { throw new RuntimeException('not_found'); }
  if ($m['status'] !== 'pending') { throw new RuntimeException('not_pending'); }
  if ($uid !== (int)$m['driver_user_id'] && $uid !== (int)$m['passenger_user_id']) {
    throw new RuntimeException('forbidden');
  }

  $pdo->prepare("UPDATE ride_matches SET status='cancelled', updated_at=NOW() WHERE id=:mid")
      ->execute([':mid'=>$matchId]);

  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch (RuntimeException $e) {
  $pdo->rollBack();
  $code = $e->getMessage();
  $map  = ['not_found'=>404,'forbidden'=>403,'not_pending'=>409];
  http_response_code($map[$code] ?? 400);
  echo json_encode(['ok'=>false,'error'=>$code]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server']);
}
