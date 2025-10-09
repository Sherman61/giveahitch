<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

use function App\Auth\{require_login, current_user};

start_secure_session();
require_login();
$uid  = (int) current_user()['id'];
$role = $_GET['role'] ?? ''; // optional: 'driver' | 'passenger' | ''(both)

try {
    $pdo = db();

    // Unified shape the frontend expects: { ok:true, items:[ ... ] }
    // One row per match where I am driver OR passenger.
    // Provide both parties’ names + contact, plus status and ride basics.
    $sql = "
      SELECT
        m.id                 AS match_id,
        m.ride_id,
        m.status             AS match_status,
        m.created_at,
        m.updated_at,
        m.confirmed_at,

        r.type,
        r.from_text,
        r.to_text,
        r.ride_datetime,

        m.driver_user_id,
        m.passenger_user_id,

        du.display_name      AS driver_display,
        du.phone             AS driver_phone,
        du.whatsapp          AS driver_whatsapp,

        pu.display_name      AS passenger_display,
        pu.phone             AS passenger_phone,
        pu.whatsapp          AS passenger_whatsapp
      FROM ride_matches m
      JOIN rides r ON r.id = m.ride_id AND r.deleted = 0
      JOIN users du ON du.id = m.driver_user_id
      JOIN users pu ON pu.id = m.passenger_user_id
      WHERE (
        (:role = ''      AND (m.driver_user_id = :uid OR m.passenger_user_id = :uid))
        OR (:role = 'driver'    AND m.driver_user_id = :uid)
        OR (:role = 'passenger' AND m.passenger_user_id = :uid)
      )
      ORDER BY COALESCE(m.confirmed_at, m.updated_at, m.created_at) DESC
    ";

    $st = $pdo->prepare($sql);
    $st->execute([
        ':role' => $role,
        ':uid'  => $uid,
    ]);

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // Frontend expects `already_rated` boolean. If you don’t have a ratings table yet,
    // return false for now. You can wire this to ride_ratings later.
    foreach ($rows as &$row) {
        $row['already_rated'] = false;
        // For convenience: also expose the two “other party” fields used in driver.js layout
        $row['other_display_driver']    = $row['driver_display'];
        $row['other_display_passenger'] = $row['passenger_display'];
    }

    echo json_encode(['ok'=>true, 'items'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
