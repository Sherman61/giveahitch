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

$rideId  = (int)($in['ride_id']  ?? 0);
$matchId = (int)($in['match_id'] ?? 0);
$stars   = (int)($in['stars']    ?? 0);

if ($rideId<=0 || $matchId<=0 || $stars<1 || $stars>5) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'bad_input']);
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Lock match row
    $mq = $pdo->prepare("
        SELECT id, ride_id, driver_user_id, passenger_user_id, status
        FROM ride_matches
        WHERE id=:mid AND ride_id=:rid
        FOR UPDATE
    ");
    $mq->execute([':mid'=>$matchId, ':rid'=>$rideId]);
    $m = $mq->fetch(PDO::FETCH_ASSOC);

    if (!$m) { $pdo->rollBack(); http_response_code(404); echo json_encode(['ok'=>false,'error'=>'match_not_found']); exit; }
    if ($uid !== (int)$m['passenger_user_id']) { $pdo->rollBack(); http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit; }
    if ($m['status'] !== 'completed') { $pdo->rollBack(); http_response_code(409); echo json_encode(['ok'=>false,'error'=>'not_completed']); exit; }

    // OPTIONAL: ensure one rating per passenger per match via a small table
    // Schema suggestion:
    // CREATE TABLE IF NOT EXISTS ride_ratings (
    //   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    //   match_id BIGINT UNSIGNED NOT NULL,
    //   rater_user_id BIGINT UNSIGNED NOT NULL,
    //   rating TINYINT NOT NULL,
    //   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    //   UNIQUE KEY uq_match_rater (match_id, rater_user_id)
    // );
    $already = false;
    try {
        $pdo->prepare("
            INSERT INTO ride_ratings (match_id, rater_user_id, rating)
            VALUES (:mid, :uid, :r)
        ")->execute([':mid'=>$matchId, ':uid'=>$uid, ':r'=>$stars]);
    } catch (\PDOException $e) {
        if (stripos($e->getMessage(), 'ride_ratings') !== false && stripos($e->getMessage(), 'exists') !== false) {
            $already = true;
        } else if (stripos($e->getMessage(), 'Table') !== false && stripos($e->getMessage(), 'doesn\'t exist') !== false) {
            // If you didn't create ride_ratings, just proceed (but duplicates are possible).
        } else {
            throw $e;
        }
    }
    if ($already) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok'=>false,'error'=>'already_rated']);
        exit;
    }

    // Update driver's aggregate rating
    $pdo->prepare("
        UPDATE users
        SET driver_rating_sum = driver_rating_sum + :s,
            driver_rating_count = driver_rating_count + 1
        WHERE id = :driver
    ")->execute([':s'=>$stars, ':driver'=>$m['driver_user_id']]);

    // Bonus score +50 for a perfect 5★
    if ($stars === 5) {
        $pdo->prepare("UPDATE users SET score = score + 50 WHERE id = :driver")
            ->execute([':driver'=>$m['driver_user_id']]);
    }

    $pdo->commit();
    echo json_encode(['ok'=>true,'rating'=>$stars, 'driver_id'=>(int)$m['driver_user_id']]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server','detail'=>$e->getMessage()]);
}
