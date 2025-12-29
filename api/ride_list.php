<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/status.php';
require_once __DIR__ . '/../lib/privacy.php';

use function App\Status\{from_db, to_db};

start_secure_session();

try {
  $pdo = db();

  $type = $_GET['type'] ?? null;                 // 'offer' | 'request' | null
  $query = trim((string) ($_GET['q'] ?? ''));
  $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
  $mine = isset($_GET['mine']) ? 1 : 0;
  $all = isset($_GET['all']) ? 1 : 0;

  $user = \App\Auth\current_user();
  $myId = $user ? (int) $user['id'] : 0;
  $isAdmin = $user && !empty($user['is_admin']);

  $sql = "SELECT
              r.id, r.user_id, r.type, r.from_text, r.to_text, r.ride_datetime, r.ride_end_datetime,
              r.seats, r.package_only, r.note, r.phone, r.whatsapp,
              r.status, r.created_at,
              u.display_name AS owner_display,
              u.contact_privacy AS owner_contact_privacy
            FROM rides r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.deleted = 0";

  $params = [];

  // Only filter to open when NOT mine. If it's mine, show all statuses.
  if (!$mine) {
    if (!$all || !$isAdmin) {
      $sql .= " AND r.status = :status";
      $params[':status'] = to_db('open');
    } else {
      if (isset($_GET['status']) && $_GET['status'] !== '') {
        $sql .= " AND r.status = :status";
        $params[':status'] = to_db((string) $_GET['status']);
      }
    }

    $sql .= " AND (
                      (r.ride_end_datetime IS NOT NULL AND r.ride_end_datetime >= DATE_SUB(NOW(), INTERVAL 48 HOUR))
                      OR (r.ride_end_datetime IS NULL AND r.ride_datetime IS NOT NULL AND r.ride_datetime >= DATE_SUB(NOW(), INTERVAL 48 HOUR))
                      OR (r.ride_end_datetime IS NULL AND r.ride_datetime IS NULL AND r.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR))
                    )";
  }

  if ($type === 'offer' || $type === 'request') {
    $sql .= " AND r.type = :type";
    $params[':type'] = $type;
  }

  if ($query !== '') {
    $sql .= " AND (r.from_text LIKE :q OR r.to_text LIKE :q OR r.note LIKE :q)";
    $params[':q'] = '%' . $query . '%';
  }

  if ($mine === 1) {
    if (!$user) {
      echo json_encode(['ok' => true, 'items' => []]);
      exit;
    }
    $sql .= " AND r.user_id = :uid";
    $params[':uid'] = (int) $user['id'];
  }

  $sql .= " ORDER BY COALESCE(r.ride_datetime, r.ride_end_datetime, r.created_at) ASC LIMIT :lim";

  $stmt = $pdo->prepare($sql);
  foreach ($params as $k => $v)
    $stmt->bindValue($k, $v);
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $rideIds = array_map('intval', array_column($rows, 'id'));
  $matchCounts = [];
  if ($rideIds) {
    $ph = implode(',', array_fill(0, count($rideIds), '?'));
    $countSql = "
        SELECT ride_id, status, COUNT(*) AS total
        FROM ride_matches
        WHERE ride_id IN ($ph)
        GROUP BY ride_id, status";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($rideIds);
    while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
      $rid = (int) $row['ride_id'];
      $status = from_db($row['status']);
      $matchCounts[$rid][$status] = (int) $row['total'];
    }
  }

  // Attach `confirmed` block per ride (if any match is accepted/in_progress/completed)
  $out = [];
  $matchSql = "
      SELECT
        m.id AS match_id, m.status, m.confirmed_at, m.updated_at, m.created_at,
        m.driver_user_id, m.passenger_user_id,
        du.display_name AS driver_display,   du.phone AS driver_phone,   du.whatsapp AS driver_whatsapp,
        pu.display_name AS passenger_display,pu.phone AS passenger_phone,pu.whatsapp AS passenger_whatsapp
      FROM ride_matches m
      JOIN users du ON du.id = m.driver_user_id
      JOIN users pu ON pu.id = m.passenger_user_id
      WHERE m.ride_id = :rid
        AND m.status IN (" . implode(',', array_map(fn(string $s) => $pdo->quote(to_db($s)), ['accepted', 'confirmed', 'in_progress', 'completed'])) . ")
      ORDER BY COALESCE(m.confirmed_at, m.updated_at, m.created_at) DESC
      LIMIT 1";
  $mStmt = $pdo->prepare($matchSql);
  $ratingMatchIds = [];

  foreach ($rows as $r) {
    $confirmed = null;
    $mStmt->execute([':rid' => $r['id']]);
    if ($m = $mStmt->fetch(PDO::FETCH_ASSOC)) {
      $confirmed = [
        'match_id' => (int) $m['match_id'],
        'status' => $m['status'],
        'driver_user_id' => (int) $m['driver_user_id'],
        'driver_display' => $m['driver_display'],
        'driver_phone' => $m['driver_phone'],
        'driver_whatsapp' => $m['driver_whatsapp'],
        'passenger_user_id' => (int) $m['passenger_user_id'],
        'passenger_display' => $m['passenger_display'],
        'passenger_phone' => $m['passenger_phone'],
        'passenger_whatsapp' => $m['passenger_whatsapp'],
        'updated_at' => $m['updated_at'],
        'confirmed_at' => $m['confirmed_at'],
        'created_at' => $m['created_at'],
      ];
      if ($myId && ($confirmed['driver_user_id'] === $myId || $confirmed['passenger_user_id'] === $myId)) {
        $ratingMatchIds[$confirmed['match_id']] = true;
      }
    }

    $r['status'] = from_db($r['status'] ?? 'open');
    $viewerIsOwner = $myId && (int) $r['user_id'] === $myId;
    $viewerHasMatch = false;
    $matchStatus = null;
    $matchChangedAt = null;
    if ($confirmed) {
      $confirmed['status'] = from_db($confirmed['status']);
      $confirmed['match_changed_at'] = $confirmed['updated_at'] ?? $confirmed['confirmed_at'] ?? $confirmed['created_at'] ?? null;
      $matchStatus = $confirmed['status'] ?? null;
      $matchChangedAt = $confirmed['match_changed_at'] ?? null;
      if ($myId && (($confirmed['driver_user_id'] ?? 0) === $myId || ($confirmed['passenger_user_id'] ?? 0) === $myId)) {
        $viewerHasMatch = true;
      }
      $viewerCanSeeMatch = $viewerHasMatch || $viewerIsOwner || $isAdmin;
      if (!$viewerCanSeeMatch) {
        $confirmed['driver_phone'] = null;
        $confirmed['driver_whatsapp'] = null;
        $confirmed['passenger_phone'] = null;
        $confirmed['passenger_whatsapp'] = null;
      }
    }
    $r['confirmed'] = $confirmed;
    $r['already_rated'] = false; // default; updated below if the current user has rated this match
    $r['match_counts'] = $matchCounts[(int) $r['id']] ?? [];
    $r['owner_role'] = $r['type'] === 'offer' ? 'driver' : 'passenger';
    $r['other_role'] = $r['owner_role'] === 'driver' ? 'passenger' : 'driver';

    $ownerPrivacy = (int) ($r['owner_contact_privacy'] ?? 1);

    $visibility = \App\Privacy\evaluate($user, [
      'privacy' => $ownerPrivacy,
      'viewer_is_owner' => $viewerIsOwner,
      'viewer_is_target' => $viewerIsOwner,
      'viewer_is_admin' => $isAdmin,
      'viewer_logged_in' => (bool) $user,
      'viewer_has_active_match' => $viewerHasMatch,
      'match_status' => $matchStatus,
      'match_changed_at' => $matchChangedAt,
      'target_has_active_open_ride' => ($r['status'] === 'open'),
    ]);

    if (empty($visibility['visible'])) {
      $r['phone'] = null;
      $r['whatsapp'] = null;
    }
    $r['contact_visibility'] = $visibility;
    $r['contact_notice'] = $visibility['visible'] ? null : ($visibility['reason'] ?? '');
    unset($r['owner_contact_privacy']);
    $out[] = $r;
  }

  if ($myId && $ratingMatchIds) {
    $matchIds = array_map('intval', array_keys($ratingMatchIds));
    $placeholders = implode(',', array_fill(0, count($matchIds), '?'));
    $sqlRated = "SELECT match_id FROM ride_ratings WHERE rater_user_id=? AND match_id IN ($placeholders)";
    try {
      $ratedStmt = $pdo->prepare($sqlRated);
      $params = array_merge([$myId], $matchIds);
      $ratedStmt->execute($params);
      $rated = array_map('intval', $ratedStmt->fetchAll(PDO::FETCH_COLUMN));
      if ($rated) {
        $ratedSet = array_flip($rated);
        foreach ($out as &$item) {
          if (!empty($item['confirmed'])) {
            $mid = (int) $item['confirmed']['match_id'];
            if (isset($ratedSet[$mid])) {
              $item['already_rated'] = true;
            }
          }
        }
        unset($item);
      }
    } catch (\PDOException $e) {
      if (stripos($e->getMessage(), 'ride_ratings') === false) {
        throw $e;
      }
    }
  }

  echo json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}