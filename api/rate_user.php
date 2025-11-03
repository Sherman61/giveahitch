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

$rideId  = (int)($in['ride_id'] ?? 0);
$rating  = (int)($in['rating'] ?? 0);
$comment = trim((string)($in['comment'] ?? ''));
$role    = ($in['role'] ?? '') === 'driver' ? 'driver' : 'passenger'; // ratee's role

if ($rideId<=0 || $rating<1 || $rating>5) { http_response_code(422); echo json_encode(['ok'=>false]); exit; }

$pdo = db();
$me  = current_user();

// find completed match
$stmt = $pdo->prepare("SELECT id, driver_user_id, passenger_user_id, status FROM ride_matches WHERE ride_id=:rid");
$stmt->execute([':rid'=>$rideId]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$m || $m['status']!=='completed'){ http_response_code(409); echo json_encode(['ok'=>false,'error'=>'not_completed']); exit; }

// rater must be one of the parties, ratee is the other party by role
if ($role === 'driver') {
  $ratee = (int)$m['driver_user_id'];
} else {
  $ratee = (int)$m['passenger_user_id'];
}
if ((int)$me['id'] !== (int)$m['driver_user_id'] && (int)$me['id'] !== (int)$m['passenger_user_id']) {
  http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}
if ((int)$me['id'] === $ratee) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'self_rate']); exit; }

$pdo->beginTransaction();

// prevent duplicate ratings for same match + rater
$chk = $pdo->prepare("SELECT id FROM ride_ratings WHERE match_id = :mid AND rater_user_id = :uid LIMIT 1");
$chk->execute([':mid' => $m['id'], ':uid' => $me['id']]);
if ($chk->fetch()) {
    $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'already_rated']);
    exit;
}

$raterRole = ($me['id'] === $m['driver_user_id']) ? 'driver' : 'passenger';
$ratedRole = ($raterRole === 'driver') ? 'passenger' : 'driver';

$ins = $pdo->prepare("INSERT INTO ride_ratings (ride_id, match_id, rater_user_id, rated_user_id, rater_role, rated_role, stars, comment)
                      VALUES (:ride,:match,:rater,:rated,:rater_role,:rated_role,:stars,:comment)");
$ins->execute([
    ':ride'        => $m['ride_id'],
    ':match'       => $m['id'],
    ':rater'       => $me['id'],
    ':rated'       => $ratee,
    ':rater_role'  => $raterRole,
    ':rated_role'  => $ratedRole,
    ':stars'       => $rating,
    ':comment'     => $comment !== '' ? $comment : null,
]);

if ($ratedRole === 'driver') {
    $pdo->prepare("UPDATE users SET driver_rating_sum = driver_rating_sum + :r, driver_rating_count = driver_rating_count + 1 WHERE id = :u")
        ->execute([':r'=>$rating, ':u'=>$ratee]);
} else {
    $pdo->prepare("UPDATE users SET passenger_rating_sum = passenger_rating_sum + :r, passenger_rating_count = passenger_rating_count + 1 WHERE id = :u")
        ->execute([':r'=>$rating, ':u'=>$ratee]);
}

$pdo->commit();
echo json_encode(['ok'=>true]);
?>