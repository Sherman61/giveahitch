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
$stars   = (int)($in['stars'] ?? 0);
$note    = trim((string)($in['note'] ?? ''));

if ($matchId<=0 || $stars<1 || $stars>5) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'bad_input']); exit;
}

$pdo = db();
$pdo->beginTransaction();

try {
  // Ensure table exists (idempotent)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS ride_ratings (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      match_id BIGINT UNSIGNED NOT NULL,
      rater_user_id BIGINT UNSIGNED NOT NULL,
      ratee_user_id BIGINT UNSIGNED NOT NULL,
      stars TINYINT NOT NULL,
      note VARCHAR(1000) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_match_rater (match_id, rater_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  ");

  // Lock the match and ensure I participated and it is completed
  $mq = $pdo->prepare("
    SELECT m.*, r.deleted
    FROM ride_matches m
    JOIN rides r ON r.id = m.ride_id
    WHERE m.id=:mid FOR UPDATE
  ");
  $mq->execute([':mid'=>$matchId]);
  $m = $mq->fetch(PDO::FETCH_ASSOC);

  if (!$m || (int)$m['deleted'] === 1) { throw new RuntimeException('not_found'); }
  if ($m['status'] !== 'completed') { throw new RuntimeException('not_completed'); }
  $isDriver    = ($uid === (int)$m['driver_user_id']);
  $isPassenger = ($uid === (int)$m['passenger_user_id']);
  if (!$isDriver && !$isPassenger) { throw new RuntimeException('forbidden'); }

  // Determine who I'm rating (the other side)
  $rateeId = $isDriver ? (int)$m['passenger_user_id'] : (int)$m['driver_user_id'];

  // Prevent double rating
  $chk = $pdo->prepare("SELECT id FROM ride_ratings WHERE match_id=:mid AND rater_user_id=:u LIMIT 1");
  $chk->execute([':mid'=>$matchId, ':u'=>$uid]);
  if ($chk->fetch()) { throw new RuntimeException('already_rated'); }

  // Insert rating
  $ins = $pdo->prepare("
    INSERT INTO ride_ratings(match_id, rater_user_id, ratee_user_id, stars, note)
    VALUES(:mid,:rater,:ratee,:stars,:note)
  ");
  $ins->execute([
    ':mid'   => $matchId,
    ':rater' => $uid,
    ':ratee' => $rateeId,
    ':stars' => $stars,
    ':note'  => ($note !== '' ? $note : null),
  ]);

  // Update aggregates on users
  if ($isPassenger) {
    // I am passenger -> I rate the driver
    $pdo->prepare("UPDATE users
                   SET driver_rating_sum = driver_rating_sum + :s,
                       driver_rating_count = driver_rating_count + 1,
                       score = score + IF(:s5=1, 50, 0)
                   WHERE id=:u")
        ->execute([':s'=>$stars, ':s5'=>($stars===5?1:0), ':u'=>$rateeId]);
  } else {
    // I am driver -> I rate the passenger
    $pdo->prepare("UPDATE users
                   SET passenger_rating_sum = passenger_rating_sum + :s,
                       passenger_rating_count = passenger_rating_count + 1,
                       score = score + IF(:s5=1, 50, 0)
                   WHERE id=:u")
        ->execute([':s'=>$stars, ':s5'=>($stars===5?1:0), ':u'=>$rateeId]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch (RuntimeException $e) {
  $pdo->rollBack();
  $code = $e->getMessage();
  $map  = ['not_found'=>404,'forbidden'=>403,'already_rated'=>409,'not_completed'=>409];
  http_response_code($map[$code] ?? 400);
  echo json_encode(['ok'=>false,'error'=>$code]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server']);
}
