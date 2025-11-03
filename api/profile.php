<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';
use function App\Auth\{require_login, assert_csrf_and_get_input};

start_secure_session();
$me = require_login();
$uid = (int)($me['id'] ?? 0);
$pdo = db();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function format_profile(array $row): array {
    $driverAvg = ($row['driver_rating_count'] ?? 0) ? round((float)$row['driver_rating_sum'] / (int)$row['driver_rating_count'], 2) : null;
    $passengerAvg = ($row['passenger_rating_count'] ?? 0) ? round((float)$row['passenger_rating_sum'] / (int)$row['passenger_rating_count'], 2) : null;

    return [
        'id' => (int)$row['id'],
        'email' => $row['email'],
        'display_name' => $row['display_name'],
        'username' => $row['username'],
        'score' => (int)$row['score'],
        'created_at' => $row['created_at'],
        'is_admin' => (bool)$row['is_admin'],
        'contact_privacy' => (int)($row['contact_privacy'] ?? 1),
        'message_privacy' => (int)($row['message_privacy'] ?? 1),
        'contact' => [
            'phone' => $row['phone'],
            'whatsapp' => $row['whatsapp'],
        ],
        'stats' => [
            'rides_offered_count' => (int)$row['rides_offered_count'],
            'rides_requested_count' => (int)$row['rides_requested_count'],
            'rides_given_count' => (int)$row['rides_given_count'],
            'rides_received_count' => (int)$row['rides_received_count'],
        ],
        'ratings' => [
            'driver' => [
                'average' => $driverAvg,
                'count' => (int)$row['driver_rating_count'],
            ],
            'passenger' => [
                'average' => $passengerAvg,
                'count' => (int)$row['passenger_rating_count'],
            ],
        ],
    ];
}

function fetch_profile(\PDO $pdo, int $uid): array {
    $stmt = $pdo->prepare('SELECT id,email,display_name,username,phone,whatsapp,score,created_at,is_admin,
                                   rides_offered_count,rides_requested_count,rides_given_count,rides_received_count,
                                   driver_rating_sum,driver_rating_count,passenger_rating_sum,passenger_rating_count,
                                   contact_privacy,
                                   message_privacy
                            FROM users WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        exit;
    }
    return $user;
}

if ($method === 'GET') {
    $row = fetch_profile($pdo, $uid);
    echo json_encode(['ok' => true, 'user' => format_profile($row)]);
    exit;
}

if ($method === 'POST' || $method === 'PATCH') {
    $input = assert_csrf_and_get_input();
    $current = fetch_profile($pdo, $uid);

    $display = trim((string)($input['display_name'] ?? ''));
    if ($display === '') {
        $display = (string)$me['display_name'];
    }

    $errors = [];
    if (mb_strlen($display) < 2 || mb_strlen($display) > 100) {
        $errors['display_name'] = 'Display name must be between 2 and 100 characters.';
    }

    $phone = trim((string)($input['phone'] ?? ''));
    $whatsapp = trim((string)($input['whatsapp'] ?? ''));
    $privacy = isset($input['contact_privacy']) ? (int)$input['contact_privacy'] : (int)($current['contact_privacy'] ?? 1);
    if (!in_array($privacy, [1, 2, 3], true)) {
        $errors['contact_privacy'] = 'Select a valid privacy option.';
    }
    $messagePrivacy = isset($input['message_privacy']) ? (int)$input['message_privacy'] : (int)($current['message_privacy'] ?? 1);
    if (!in_array($messagePrivacy, [1, 2, 3], true)) {
        $errors['message_privacy'] = 'Select who can message you.';
    }
    $re = '/^\+?[0-9\s\-\(\)]{7,32}$/';
    if ($phone !== '' && !preg_match($re, $phone)) {
        $errors['phone'] = 'Invalid phone number format.';
    }
    if ($whatsapp !== '' && !preg_match($re, $whatsapp)) {
        $errors['whatsapp'] = 'Invalid WhatsApp number format.';
    }

    if ($errors) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'validation', 'fields' => $errors]);
        exit;
    }

    $upd = $pdo->prepare('UPDATE users
                           SET display_name=:display,
                               phone=:phone,
                               whatsapp=:whatsapp,
                               contact_privacy=:privacy,
                               message_privacy=:msg_privacy
                           WHERE id=:id');
    $upd->bindValue(':display', $display, PDO::PARAM_STR);
    if ($phone === '') {
        $upd->bindValue(':phone', null, PDO::PARAM_NULL);
    } else {
        $upd->bindValue(':phone', $phone, PDO::PARAM_STR);
    }
    if ($whatsapp === '') {
        $upd->bindValue(':whatsapp', null, PDO::PARAM_NULL);
    } else {
        $upd->bindValue(':whatsapp', $whatsapp, PDO::PARAM_STR);
    }
    $upd->bindValue(':privacy', $privacy, PDO::PARAM_INT);
    $upd->bindValue(':msg_privacy', $messagePrivacy, PDO::PARAM_INT);
    $upd->bindValue(':id', $uid, PDO::PARAM_INT);
    $upd->execute();

    $row = fetch_profile($pdo, $uid);
    $_SESSION['user'] = [
        'id' => (int)$row['id'],
        'email' => $row['email'],
        'display_name' => $row['display_name'],
        'is_admin' => (bool)$row['is_admin'],
    ];

    echo json_encode(['ok' => true, 'user' => format_profile($row)]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'method']);
