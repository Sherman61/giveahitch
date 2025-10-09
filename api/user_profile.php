<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

$uid = (int)($_GET['user_id'] ?? 0);
if ($uid<=0) { http_response_code(422); echo json_encode(['ok'=>false]); exit; }

$pdo = db();
$u = $pdo->prepare("SELECT id, display_name, score, rides_offered_count, rides_requested_count, rides_given_count, rides_received_count,
                           driver_rating_sum, driver_rating_count, passenger_rating_sum, passenger_rating_count
                    FROM users WHERE id=:id");
$u->execute([':id'=>$uid]);
$user = $u->fetch(PDO::FETCH_ASSOC);
if(!$user){ http_response_code(404); echo json_encode(['ok'=>false]); exit; }

$driver_avg = ($user['driver_rating_count']>0) ? round($user['driver_rating_sum']/$user['driver_rating_count'],2) : null;
$pass_avg   = ($user['passenger_rating_count']>0) ? round($user['passenger_rating_sum']/$user['passenger_rating_count'],2) : null;

$fb = $pdo->prepare("SELECT f.id, f.rating, f.comment, f.role, f.created_at,
                            rater.display_name AS rater_name
                     FROM feedback f
                     JOIN users rater ON rater.id = f.rater_user_id
                     WHERE f.ratee_user_id = :id
                     ORDER BY f.id DESC LIMIT 50");
$fb->execute([':id'=>$uid]);
$feedback = $fb->fetchAll(PDO::FETCH_ASSOC);

// Publicly expose only rides_given_count (as requested)
$public_counts = [
  'rides_given_count' => (int)$user['rides_given_count']
];

echo json_encode([
  'ok'=>true,
  'user'=>[
    'id'=>$user['id'],
    'display_name'=>$user['display_name'],
    'score'=>(int)$user['score'],
    'public_counts'=>$public_counts,
    'driver_rating_avg'=>$driver_avg,
    'driver_rating_count'=>(int)$user['driver_rating_count'],
    'passenger_rating_avg'=>$pass_avg,
    'passenger_rating_count'=>(int)$user['passenger_rating_count'],
  ],
  'feedback'=>$feedback
], JSON_UNESCAPED_UNICODE);
?>