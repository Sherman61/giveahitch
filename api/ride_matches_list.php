<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

use function App\Auth\{require_login, current_user};

start_secure_session();
require_login();
$me  = current_user();
$uid = (int)$me['id'];

$rideId = (int)($_GET['ride_id'] ?? 0);
if ($rideId <= 0) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'bad_id']);
    exit;
}

$pdo = db();

/** 1) Fetch ride & enforce ownership */
$rideStmt = $pdo->prepare("
    SELECT id, user_id, type, status
    FROM rides
    WHERE id = :id AND deleted = 0
    LIMIT 1
");
$rideStmt->execute([':id' => $rideId]);
$ride = $rideStmt->fetch(PDO::FETCH_ASSOC);

if (!$ride) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'not_found']);
    exit;
}
if ((int)$ride['user_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']);
    exit;
}

/**
 * 2) Determine which side is the requester for *this* ride:
 *    - If the ride is an OFFER, then requesters are PASSENGERS (passenger_user_id).
 *    - If the ride is a REQUEST (looking), then requesters are DRIVERS (driver_user_id).
 */
$whoCol = ($ride['type'] === 'offer') ? 'passenger_user_id' : 'driver_user_id';

/**
 * 3) List all PENDING matches for the manage modal.
 *    We join to users for display_name (and phone/whatsapp if your users table has them).
 *    If you don’t store phone/whatsapp in users, these will just come back null.
 */
$pendingSql = "
SELECT
    m.id                 AS match_id,
    m.status,
    m.created_at,
    u.id                 AS requester_id,
    u.display_name       AS requester_name,
    /* Optional: include if your users table has these columns */
    u.phone              AS requester_phone,
    u.whatsapp           AS requester_whatsapp
FROM ride_matches m
JOIN users u ON u.id = m.$whoCol
WHERE m.ride_id = :rid
  AND m.status  = 'pending'
ORDER BY m.created_at ASC
";
$pendingStmt = $pdo->prepare($pendingSql);
$pendingStmt->execute([':rid' => $rideId]);
$pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * 4) Return current confirmed/accepted match (if any) for context.
 *    We show the “other party” (the non-owner) consistently in the payload.
 */
$otherCol = ($ride['type'] === 'offer') ? 'passenger_user_id' : 'driver_user_id';

$confirmedSql = "
SELECT
    m.id              AS match_id,
    m.status,
    m.created_at,
    u.id              AS other_id,
    u.display_name    AS other_name,
    /* Optional contacts from users */
    u.phone           AS other_phone,
    u.whatsapp        AS other_whatsapp
FROM ride_matches m
JOIN users u ON u.id = m.$otherCol
WHERE m.ride_id = :rid
  AND m.status IN ('accepted','confirmed','in_progress','completed')
ORDER BY FIELD(m.status,'confirmed','accepted','in_progress','completed'), m.created_at DESC
LIMIT 1
";
$confStmt = $pdo->prepare($confirmedSql);
$confStmt->execute([':rid' => $rideId]);
$confirmed = $confStmt->fetch(PDO::FETCH_ASSOC) ?: null;

/** 5) Respond */
echo json_encode([
    'ok'        => true,
    'ride'      => [
        'id'     => (int)$ride['id'],
        'type'   => $ride['type'],
        'status' => $ride['status'],
    ],
    'pending'   => $pending,
    'confirmed' => $confirmed,
]);
