<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
use function App\Auth\{require_login, current_user, csrf_verify, is_admin};

start_secure_session();
require_login();

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));
$rideId = (int)($in['ride_id'] ?? 0);
if ($rideId<=0) { http_response_code(422); echo json_encode(['ok'=>false]); exit; }

$pdo = db(); 
$pdo->beginTransaction();

$stmt = $pdo->prepare("SELECT rm.id, rm.driver_user_id, rm.passenger_user_id, rm.status, r.status as ride_status
                       FROM ride_matches rm
                       JOIN rides r ON r.id = rm.ride_id
                       WHERE rm.ride_id = :rid FOR UPDATE");
$stmt->execute([':rid'=>$rideId]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$m){ $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error':'no_match']); exit; }
if(!in_array($m['status'], ['accepted','in_progress'], true)){ $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error':'bad_state']); exit; }

$me = current_user();
// Only the passenger or admin can confirm
if (!is_admin() && (int)$me['id'] !== (int)$m['passenger_user_id']) {
  $pdo->rollBack(); http_response_code(403); echo json_encode(['ok'=>false,'error':'forbidden']); exit;
}

// mark completed
$pdo->prepare("UPDATE ride_matches SET status='completed' WHERE id=:id")->execute([':id'=>$m['id']]);
$pdo->prepare("UPDATE rides SET status='completed' WHERE id=:rid")->execute([':rid'=>$rideId]);

// bump counters/scores
$pdo->prepare("UPDATE users SET rides_given_count = rides_given_count + 1, score = score + 500 WHERE id=:d")
    ->execute([':d'=>$m['driver_user_id']]);
$pdo->prepare("UPDATE users SET rides_received_count = rides_received_count + 1 WHERE id=:p")
    ->execute([':p'=>$m['passenger_user_id']]);

$pdo->commit();
echo json_encode(['ok'=>true]);
