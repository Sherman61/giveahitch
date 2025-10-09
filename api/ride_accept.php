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

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$rideId = (int)($in['ride_id'] ?? 0);
if ($rideId <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

$pdo = db();
$pdo->beginTransaction();

// lock the ride to avoid races
$stmt = $pdo->prepare("SELECT id, user_id, type, status FROM rides WHERE id=:id AND deleted=0 FOR UPDATE");
$stmt->execute([':id' => $rideId]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
$r['status'] = from_db($r['status'] ?? 'open');
if ($r['status'] !== 'open') { $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'not_open']); exit; }

$me = current_user();
if ((int)$me['id'] === (int)$r['user_id']) { $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'own_ride']); exit; }

// role mapping: if ride is an offer → owner is driver; if request → owner is passenger
if ($r['type'] === 'offer') {
  $driver = (int)$r['user_id'];
  $pass   = (int)$me['id'];
} else {
  $driver = (int)$me['id'];
  $pass   = (int)$r['user_id'];
}

// prevent duplicate active matches
    $statusList = ['accepted','completed','in_progress'];
    $quotedStatuses = array_map(function (string $status) use ($pdo) {
        return $pdo->quote(to_db($status));
    }, $statusList);

    $ex = $pdo->prepare("SELECT id FROM ride_matches WHERE ride_id=:rid AND status IN (" . implode(',', $quotedStatuses) . ") LIMIT 1");
$ex->execute([':rid' => $rideId]);
if ($ex->fetch()) { $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'already_matched']); exit; }

$ins = $pdo->prepare("INSERT INTO ride_matches(ride_id,driver_user_id,passenger_user_id,status) VALUES(:rid,:d,:p,:status)");
$ins->execute([
  ':rid' => $rideId,
  ':d'   => $driver,
  ':p'   => $pass,
  ':status' => to_db('accepted'),
]);

$pdo->prepare("UPDATE rides SET status=:status WHERE id=:id")->execute([':status'=>to_db('matched'), ':id' => $rideId]);

$pdo->commit();
echo json_encode(['ok'=>true]);
?>