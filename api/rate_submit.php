<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/status.php';

use function App\Auth\{require_login, current_user, csrf_verify};
use function App\Status\from_db;

start_secure_session();
require_login();

$uid = (int) current_user()['id'];

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$rideId   = (int)($in['ride_id']  ?? 0);
$matchId  = (int)($in['match_id'] ?? 0);
$stars    = (int)($in['stars']    ?? 0);
$comment  = trim((string)($in['comment'] ?? ''));

if ($rideId <= 0 || $matchId <= 0 || $stars < 1 || $stars > 5) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'bad_input']); exit;
}
if (mb_strlen($comment) > 1000) {
  $comment = mb_substr($comment, 0, 1000);
}

$pdo = db();
$pdo->beginTransaction();

try {
  // 1) Load the match & ride; ensure rater is part of the match and ride is completed.
  $st = $pdo->prepare("
    SELECT m.id, m.ride_id, m.driver_user_id, m.passenger_user_id, m.status AS match_status,
           r.status AS ride_status
    FROM ride_matches m
    JOIN rides r ON r.id = m.ride_id
    WHERE m.id = :mid AND m.ride_id = :rid
    FOR UPDATE
  ");
  $st->execute([':mid'=>$matchId, ':rid'=>$rideId]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    throw new RuntimeException('not_found'); // 404
  }

  $rideStatus  = from_db($row['ride_status'] ?? 'open');
  if ($rideStatus !== 'completed') {
    throw new RuntimeException('not_completed'); // 409
  }

  $driverId    = (int) $row['driver_user_id'];
  $passengerId = (int) $row['passenger_user_id'];

  if ($uid !== $driverId && $uid !== $passengerId) {
    throw new RuntimeException('forbidden'); // 403
  }

  // 2) Determine the perspective of the rater and the role of the person being rated.
  // If the rater is the passenger, they rate the driver (rated_role='driver').
  // If the rater is the driver, they rate the passenger (rated_role='passenger').
  $raterRole   = ($uid === $driverId) ? 'driver' : 'passenger';
  $ratedUserId = ($uid === $driverId) ? $passengerId : $driverId;
  $ratedRole   = ($raterRole === 'driver') ? 'passenger' : 'driver';

  // 3) Prevent duplicate rating by same rater for this match.
  // Table has UNIQUE (match_id, rater_user_id) – this query lets us return a friendly error.
  $chk = $pdo->prepare("SELECT id FROM ride_ratings WHERE match_id = :mid AND rater_user_id = :uid LIMIT 1");
  $chk->execute([':mid'=>$matchId, ':uid'=>$uid]);
  if ($chk->fetch()) {
    throw new RuntimeException('already_rated'); // 409
  }

  // 4) Insert rating row.
  $ins = $pdo->prepare("
    INSERT INTO ride_ratings (ride_id, match_id, rater_user_id, rated_user_id, rater_role, rated_role, stars, comment)
    VALUES (:rid, :mid, :rater, :rated, :rater_role, :rated_role, :stars, :comment)
  ");
  $ins->execute([
    ':rid'    => $rideId,
    ':mid'    => $matchId,
    ':rater'  => $uid,
    ':rated'  => $ratedUserId,
    ':rater_role' => $raterRole,
    ':rated_role' => $ratedRole,
    ':stars'  => $stars,
    ':comment'=> ($comment !== '' ? $comment : null),
  ]);

  // 5) Update aggregates for the RATED user.
  if ($raterRole === 'passenger') {
    // passenger rated the driver → bump driver's driver_* stats
    $upd = $pdo->prepare("
      UPDATE users
      SET driver_rating_sum   = driver_rating_sum   + :s,
          driver_rating_count = driver_rating_count + 1,
          score               = score + :bonus
      WHERE id = :u
    ");
  } else {
    // driver rated the passenger → bump passenger's passenger_* stats
    $upd = $pdo->prepare("
      UPDATE users
      SET passenger_rating_sum   = passenger_rating_sum   + :s,
          passenger_rating_count = passenger_rating_count + 1,
          score                  = score + :bonus
      WHERE id = :u
    ");
  }
  $bonus = ($stars === 5) ? 100 : 0; // +100 score for a 5-star rating
  $upd->execute([':s'=>$stars, ':u'=>$ratedUserId, ':bonus'=>$bonus]);

  $pdo->commit();
  echo json_encode(['ok'=>true, 'bonus'=>$bonus]);

} catch (RuntimeException $e) {
  $pdo->rollBack();
  $map = [
    'not_found'      => 404,
    'forbidden'      => 403,
    'not_completed'  => 409,
    'already_rated'  => 409,
  ];
  http_response_code($map[$e->getMessage()] ?? 400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server']);
}
