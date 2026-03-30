<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/status.php';
require_once __DIR__ . '/../lib/privacy.php';

use function App\Auth\{require_login, current_user};
use function App\Status\{from_db, to_db};

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
 * 2) Determine who is asking to join this ride.
 *
 * For an offer:
 * - the owner is offering to drive
 * - joiners are passengers
 *
 * For a request:
 * - the owner is looking for a ride as the passenger
 * - joiners are drivers
 */
$ride['status'] = from_db($ride['status'] ?? 'open');
$joinerUserColumn = ($ride['type'] === 'offer') ? 'passenger_user_id' : 'driver_user_id';

/**
 * 3) List all pending people waiting for the owner to respond.
 *    We join to users for display_name and contact fields.
 */
$pendingJoinersSql = "
SELECT
    m.id                 AS match_id,
    m.status,
    m.created_at,
    u.id                 AS joiner_user_id,
    u.display_name       AS joiner_name,
    /* Optional: include if your users table has these columns */
    u.phone              AS joiner_phone,
    u.whatsapp           AS joiner_whatsapp,
    u.contact_privacy    AS joiner_contact_privacy
FROM ride_matches m
JOIN users u ON u.id = m.$joinerUserColumn
WHERE m.ride_id = :rid
  AND m.status  = 'pending'
ORDER BY m.created_at ASC
";
$pendingJoinersStmt = $pdo->prepare($pendingJoinersSql);
$pendingJoinersStmt->execute([':rid' => $rideId]);
$pendingJoiners = $pendingJoinersStmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * 4) Return the current joined person for this ride, if any.
 *    The payload always exposes the non-owner as the "other" person.
 */
$selectedJoinerUserColumn = ($ride['type'] === 'offer') ? 'passenger_user_id' : 'driver_user_id';

$selectedMatchSql = "
SELECT
    m.id              AS match_id,
    m.status,
    m.created_at,
    m.updated_at,
    m.confirmed_at,
    u.id              AS other_id,
    u.display_name    AS other_name,
    /* Optional contacts from users */
    u.phone           AS other_phone,
    u.whatsapp        AS other_whatsapp,
    u.contact_privacy AS other_contact_privacy
FROM ride_matches m
JOIN users u ON u.id = m.$selectedJoinerUserColumn
WHERE m.ride_id = :rid
  AND m.status IN (" . implode(',', array_map(fn(string $s) => $pdo->quote(to_db($s)), ['accepted','confirmed','in_progress','completed','cancelled'])) . ")
ORDER BY FIELD(m.status," . implode(',', array_map(fn(string $s) => $pdo->quote(to_db($s)), ['confirmed','accepted','in_progress','completed','cancelled'])) . "), m.created_at DESC
LIMIT 1
";
$selectedMatchStmt = $pdo->prepare($selectedMatchSql);
$selectedMatchStmt->execute([':rid' => $rideId]);
$selectedMatch = $selectedMatchStmt->fetch(PDO::FETCH_ASSOC) ?: null;

foreach ($pendingJoiners as &$joiner) {
    $joiner['requester_id'] = (int)$joiner['joiner_user_id'];
    $joiner['requester_name'] = $joiner['joiner_name'];
    $joiner['requester_phone'] = $joiner['joiner_phone'];
    $joiner['requester_whatsapp'] = $joiner['joiner_whatsapp'];
    $joiner['status'] = from_db($joiner['status']);
    $visibility = \App\Privacy\evaluate($me, [
        'privacy' => (int)($joiner['joiner_contact_privacy'] ?? 1),
        'viewer_is_owner' => false,
        'viewer_is_target' => false,
        'viewer_is_admin' => !empty($me['is_admin']),
        'viewer_logged_in' => true,
        'viewer_has_active_match' => false,
        'match_status' => null,
        'match_changed_at' => null,
        'target_has_active_open_ride' => false,
    ]);
    if (empty($visibility['visible'])) {
        $joiner['requester_phone'] = null;
        $joiner['requester_whatsapp'] = null;
    }
    $joiner['requester_contact_visibility'] = $visibility;
    $joiner['requester_contact_notice'] = $visibility['visible'] ? null : ($visibility['reason'] ?? '');
    unset(
        $joiner['joiner_user_id'],
        $joiner['joiner_name'],
        $joiner['joiner_phone'],
        $joiner['joiner_whatsapp'],
        $joiner['joiner_contact_privacy']
    );
}
unset($joiner);

if ($selectedMatch) {
    $selectedMatch['status'] = from_db($selectedMatch['status']);
    $matchChangedAt = $selectedMatch['updated_at'] ?? $selectedMatch['confirmed_at'] ?? $selectedMatch['created_at'] ?? null;
    $visibility = \App\Privacy\evaluate($me, [
        'privacy' => (int)($selectedMatch['other_contact_privacy'] ?? 1),
        'viewer_is_owner' => true,
        'viewer_is_target' => false,
        'viewer_is_admin' => !empty($me['is_admin']),
        'viewer_logged_in' => true,
        'viewer_has_active_match' => true,
        'match_status' => $selectedMatch['status'],
        'match_changed_at' => $matchChangedAt,
        'target_has_active_open_ride' => false,
    ]);
    if (empty($visibility['visible'])) {
        $selectedMatch['other_phone'] = null;
        $selectedMatch['other_whatsapp'] = null;
    }
    $selectedMatch['other_contact_visibility'] = $visibility;
    $selectedMatch['other_contact_notice'] = $visibility['visible'] ? null : ($visibility['reason'] ?? '');
    $selectedMatch['other_display'] = $selectedMatch['other_name'] ?? null;
    unset($selectedMatch['other_contact_privacy']);
}

/** 5) Respond */
echo json_encode([
    'ok'        => true,
    'ride'      => [
        'id'     => (int)$ride['id'],
        'type'   => $ride['type'],
        'status' => $ride['status'],
    ],
    'pending'   => $pendingJoiners,
    'confirmed' => $selectedMatch,
]);
