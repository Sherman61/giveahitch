<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';

start_secure_session();
$viewer = \App\Auth\current_user();

$uid = (int)($_GET['user_id'] ?? 0);
if ($uid <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id, display_name, username, email, created_at, score, is_admin,
                               rides_offered_count, rides_requested_count, rides_given_count, rides_received_count,
                               driver_rating_sum, driver_rating_count, passenger_rating_sum, passenger_rating_count,
                               phone, whatsapp
                        FROM users WHERE id=:id");
$stmt->execute([':id' => $uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$driverAvg = ($user['driver_rating_count'] ?? 0) > 0
    ? round((float)$user['driver_rating_sum'] / (int)$user['driver_rating_count'], 2)
    : null;
$passengerAvg = ($user['passenger_rating_count'] ?? 0) > 0
    ? round((float)$user['passenger_rating_sum'] / (int)$user['passenger_rating_count'], 2)
    : null;

$fb = $pdo->prepare("SELECT
                            rr.id,
                            rr.stars AS rating,
                            rr.comment,
                            rr.rated_role AS role,
                            rr.created_at,
                            rr.match_id,
                            rr.rater_role,
                            rater.display_name AS rater_name,
                            rides.type         AS ride_type,
                            rides.from_text,
                            rides.to_text
                     FROM ride_ratings rr
                     JOIN users rater    ON rater.id    = rr.rater_user_id
                     JOIN ride_matches m ON m.id        = rr.match_id
                     JOIN rides          ON rides.id    = m.ride_id
                     WHERE rr.rated_user_id = :id
                     ORDER BY rr.created_at DESC
                     LIMIT 50");
$fb->execute([':id' => $uid]);
$feedback = $fb->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'rides_offered_count' => (int)$user['rides_offered_count'],
    'rides_requested_count' => (int)$user['rides_requested_count'],
    'rides_given_count' => (int)$user['rides_given_count'],
    'rides_received_count' => (int)$user['rides_received_count'],
];

$response = [
    'ok' => true,
    'user' => [
        'id' => (int)$user['id'],
        'display_name' => $user['display_name'],
        'username' => $user['username'],
        'score' => (int)$user['score'],
        'created_at' => $user['created_at'],
        'contact' => [
            'phone' => $user['phone'],
            'whatsapp' => $user['whatsapp'],
        ],
        'stats' => $stats,
        'public_counts' => ['rides_given_count' => $stats['rides_given_count']],
        'driver_rating_avg' => $driverAvg,
        'driver_rating_count' => (int)$user['driver_rating_count'],
        'passenger_rating_avg' => $passengerAvg,
        'passenger_rating_count' => (int)$user['passenger_rating_count'],
        'ratings' => [
            'driver' => [
                'count'   => (int)$user['driver_rating_count'],
                'average' => $driverAvg,
            ],
            'passenger' => [
                'count'   => (int)$user['passenger_rating_count'],
                'average' => $passengerAvg,
            ],
        ],
    ],
    'feedback' => array_map(static function(array $row): array {
        return [
            'id'          => (int)$row['id'],
            'rating'      => (int)$row['rating'],
            'comment'     => $row['comment'],
            'role'        => $row['role'],
            'rater_role'  => $row['rater_role'],
            'match_id'    => (int)$row['match_id'],
            'ride_type'   => $row['ride_type'],
            'from_text'   => $row['from_text'],
            'to_text'     => $row['to_text'],
            'rater_name'  => $row['rater_name'],
            'created_at'  => $row['created_at'],
        ];
    }, $feedback),
    'is_self' => $viewer && (int)$viewer['id'] === (int)$user['id'],
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>