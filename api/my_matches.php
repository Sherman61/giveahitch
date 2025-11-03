<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/status.php';
require_once __DIR__ . '/../lib/privacy.php';

use function App\Auth\{require_login, current_user};
use function App\Status\from_db;

start_secure_session();
$viewerUser = require_login();

$uid  = (int) current_user()['id'];
$role = $_GET['role'] ?? ''; // '', 'driver', or 'passenger'

try {
    $pdo = db();

    // Base SELECT
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
        du.contact_privacy   AS driver_contact_privacy,

        pu.display_name      AS passenger_display,
        pu.phone             AS passenger_phone,
        pu.whatsapp          AS passenger_whatsapp,
        pu.contact_privacy   AS passenger_contact_privacy
      FROM ride_matches m
      JOIN rides r ON r.id = m.ride_id AND r.deleted = 0
      JOIN users du ON du.id = m.driver_user_id
      JOIN users pu ON pu.id = m.passenger_user_id
      WHERE 1=1
    ";

    $params = [];

    // Add WHERE depending on the requested role (no placeholder reuse)
    if ($role === 'driver') {
        $sql .= " AND m.driver_user_id = :uid_driver";
        $params[':uid_driver'] = $uid;
    } elseif ($role === 'passenger') {
        $sql .= " AND m.passenger_user_id = :uid_passenger";
        $params[':uid_passenger'] = $uid;
    } else {
        // both roles
        $sql .= " AND (m.driver_user_id = :uid_d OR m.passenger_user_id = :uid_p)";
        $params[':uid_d'] = $uid;
        $params[':uid_p'] = $uid;
    }

    $sql .= " ORDER BY COALESCE(m.confirmed_at, m.updated_at, m.created_at) DESC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // Mark already-rated matches (ignore if table doesn’t exist)
    $ratedSet = [];
    if ($rows) {
        $matchIds = array_values(array_unique(array_map('intval', array_column($rows, 'match_id'))));
        if ($matchIds) {
            $ph = implode(',', array_fill(0, count($matchIds), '?'));
            $sqlRated = "SELECT match_id FROM ride_ratings WHERE rater_user_id = ? AND match_id IN ($ph)";
            try {
                $ratedStmt = $pdo->prepare($sqlRated);
                $ratedStmt->execute(array_merge([$uid], $matchIds));
                $ratedMatches = array_map('intval', $ratedStmt->fetchAll(PDO::FETCH_COLUMN));
                if ($ratedMatches) $ratedSet = array_flip($ratedMatches);
            } catch (\PDOException $e) {
                // swallow only if table truly missing
                if (stripos($e->getMessage(), 'ride_ratings') === false) {
                    throw $e;
                }
            }
        }
    }

    foreach ($rows as &$row) {
        $row['match_status']  = from_db($row['match_status']);
        $row['already_rated'] = isset($ratedSet[(int)$row['match_id']]);
        // handy aliases used by your driver UI
        $row['other_display_driver']    = $row['driver_display'];
        $row['other_display_passenger'] = $row['passenger_display'];

        $isDriver = (int)$row['driver_user_id'] === $uid;
        $otherPrivacy = $isDriver ? (int)($row['passenger_contact_privacy'] ?? 1) : (int)($row['driver_contact_privacy'] ?? 1);
        $matchChangedAt = $row['updated_at'] ?? $row['confirmed_at'] ?? $row['created_at'] ?? null;
        $viewerHasMatch = in_array($row['match_status'], ['accepted','matched','confirmed','in_progress','completed','cancelled'], true);

        $visibility = \App\Privacy\evaluate($viewerUser, [
            'privacy' => $otherPrivacy,
            'viewer_is_owner' => false,
            'viewer_is_target' => false,
            'viewer_is_admin' => !empty($viewerUser['is_admin']),
            'viewer_logged_in' => true,
            'viewer_has_active_match' => $viewerHasMatch,
            'match_status' => $row['match_status'],
            'match_changed_at' => $matchChangedAt,
            'target_has_active_open_ride' => false,
        ]);

        if (empty($visibility['visible'])) {
            if ($isDriver) {
                $row['passenger_phone'] = null;
                $row['passenger_whatsapp'] = null;
            } else {
                $row['driver_phone'] = null;
                $row['driver_whatsapp'] = null;
            }
        }

        $row['other_contact_visibility'] = $visibility;
        $row['other_contact_notice'] = $visibility['visible'] ? null : ($visibility['reason'] ?? '');
        $row['other_phone'] = $isDriver ? $row['passenger_phone'] : $row['driver_phone'];
        $row['other_whatsapp'] = $isDriver ? $row['passenger_whatsapp'] : $row['driver_whatsapp'];
        $row['other_display'] = $isDriver ? $row['passenger_display'] : $row['driver_display'];

        unset($row['driver_contact_privacy'], $row['passenger_contact_privacy']);
    }
    unset($row);

    echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
