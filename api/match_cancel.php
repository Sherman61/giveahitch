<!-- this is if a driver wants to remove there offer -->

<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

use function App\Auth\{require_login, current_user, csrf_verify};

start_secure_session();
require_login();

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$rideId  = (int)($in['ride_id']  ?? 0);
$matchId = (int)($in['match_id'] ?? 0);

if ($rideId<=0 || $matchId<=0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_input']); exit; }

$pdo = db();
$pdo->beginTransaction();

/* lock ride */
$rq = $pdo->prepare("SELECT id,user_id,type,status FROM rides WHERE id=:id AND deleted=0 FOR UPDATE");
$rq->execute([':id'=>$rideId]);
$ride = $rq->fetch(PDO::FETCH_ASSOC);
if (!$ride) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

/* only owner can confirm; owner is always rides.user_id */
$me = current_user();
if ((int)$me['id'] !== (int)$ride['user_id']) {
  $pdo->rollBack(); http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}
if ($ride['status'] !== 'open') {
  $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'not_open']); exit;
}

/* chosen pending must exist and belong to this ride */
$mq = $pdo->prepare("SELECT id,status FROM ride_matches WHERE id=:mid AND ride_id=:rid FOR UPDATE");
$mq->execute([':mid'=>$matchId, ':rid'=>$rideId]);
$match = $mq->fetch(PDO::FETCH_ASSOC);
if (!$match || $match['status'] !== 'pending') {
  $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'not_pending']); exit;
}

/* accept chosen + reject the rest */
$pdo->prepare("UPDATE ride_matches SET status='accepted', confirmed_at=NOW() WHERE id=:mid")->execute([':mid'=>$matchId]);
$pdo->prepare("UPDATE ride_matches SET status='rejected' WHERE ride_id=:rid AND status='pending' AND id<>:mid")->execute([':rid'=>$rideId, ':mid'=>$matchId]);

/* mark ride matched */
$pdo->prepare("UPDATE rides SET status='matched' WHERE id=:rid")->execute([':rid'=>$rideId]);

$pdo->commit();
echo json_encode(['ok'=>true,'status'=>'accepted','match_id'=>$matchId]);
