<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/security_events.php';
use function App\Auth\{assert_csrf_and_get_input, require_admin};

$admin = require_admin();
$pdo = db();
\App\Security\rate_limit($pdo, 'admin_reports', 60, 900);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $in = assert_csrf_and_get_input();

    $reportId = (int)($in['report_id'] ?? 0);
    $status = trim((string)($in['status'] ?? ''));
    $adminNotes = trim((string)($in['admin_notes'] ?? ''));
    $deleteRide = !empty($in['delete_ride']);

    $allowedStatuses = ['open', 'reviewing', 'closed', 'dismissed'];
    if ($reportId <= 0 || !in_array($status, $allowedStatuses, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'bad_input']);
        exit;
    }

    if (mb_strlen($adminNotes) > 4000) {
        $adminNotes = mb_substr($adminNotes, 0, 4000);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('
            SELECT rr.id, rr.ride_id, r.deleted
            FROM ride_reports AS rr
            JOIN rides AS r ON r.id = rr.ride_id
            WHERE rr.id = :id
            FOR UPDATE
        ');
        $stmt->execute([':id' => $reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$report) {
            throw new RuntimeException('not_found');
        }

        $update = $pdo->prepare('
            UPDATE ride_reports
            SET status = :status,
                admin_notes = :admin_notes,
                reviewed_by_user_id = :reviewed_by_user_id,
                reviewed_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $update->execute([
            ':status' => $status,
            ':admin_notes' => $adminNotes !== '' ? $adminNotes : null,
            ':reviewed_by_user_id' => (int)$admin['id'],
            ':id' => $reportId,
        ]);

        if ($deleteRide && empty($report['deleted'])) {
            $deleteStmt = $pdo->prepare("
                UPDATE rides
                SET deleted = 1,
                    status = 'cancelled',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :ride_id
            ");
            $deleteStmt->execute([':ride_id' => (int)$report['ride_id']]);
        }

        \App\SecurityEvents\log_event($pdo, 'admin_report_reviewed', 'warning', [
            'actor_user_id' => (int)$admin['id'],
            'details' => $adminNotes !== '' ? $adminNotes : 'Admin updated a ride report.',
            'metadata' => [
                'report_id' => $reportId,
                'ride_id' => (int)$report['ride_id'],
                'status' => $status,
                'ride_deleted' => $deleteRide && empty($report['deleted']),
            ],
        ]);

        $pdo->commit();
        echo json_encode([
            'ok' => true,
            'ride_deleted' => $deleteRide && empty($report['deleted']),
        ]);
        exit;
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code($e->getMessage() === 'not_found' ? 404 : 400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'server']);
        exit;
    }
}

$status = trim((string)($_GET['status'] ?? ''));
$reason = trim((string)($_GET['reason'] ?? ''));
$query = trim((string)($_GET['q'] ?? ''));
$limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));

$where = [];
$params = [];

if ($status !== '') {
    $where[] = 'rr.status = :status';
    $params[':status'] = $status;
}
if ($reason !== '') {
    $where[] = 'rr.reason_key = :reason_key';
    $params[':reason_key'] = $reason;
}
if ($query !== '') {
    $where[] = '(
        rr.details LIKE :query
        OR rr.admin_notes LIKE :query
        OR r.from_text LIKE :query
        OR r.to_text LIKE :query
        OR COALESCE(owner.display_name, owner.email, "") LIKE :query
        OR COALESCE(reporter.display_name, reporter.email, "") LIKE :query
    )';
    $params[':query'] = '%' . $query . '%';
}

$sql = '
    SELECT
        rr.id,
        rr.ride_id,
        rr.reporter_user_id,
        rr.reported_user_id,
        rr.reason_key,
        rr.details,
        rr.status,
        rr.admin_notes,
        rr.reviewed_by_user_id,
        rr.reviewed_at,
        rr.created_at,
        r.type AS ride_type,
        r.from_text,
        r.to_text,
        r.status AS ride_status,
        r.deleted AS ride_deleted,
        r.user_id AS owner_user_id,
        owner.display_name AS owner_display_name,
        owner.email AS owner_email,
        reporter.display_name AS reporter_display_name,
        reporter.email AS reporter_email,
        reviewed_by.display_name AS reviewed_by_display_name
    FROM ride_reports AS rr
    JOIN rides AS r ON r.id = rr.ride_id
    LEFT JOIN users AS owner ON owner.id = r.user_id
    LEFT JOIN users AS reporter ON reporter.id = rr.reporter_user_id
    LEFT JOIN users AS reviewed_by ON reviewed_by.id = rr.reviewed_by_user_id
';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY
    CASE rr.status
        WHEN "open" THEN 0
        WHEN "reviewing" THEN 1
        WHEN "closed" THEN 2
        ELSE 3
    END,
    rr.created_at DESC
    LIMIT ' . $limit;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode(['ok' => true, 'items' => $items]);
} catch (Throwable $e) {
    if ($e instanceof \PDOException && stripos($e->getMessage(), 'ride_reports') !== false) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'reporting_unavailable']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server']);
}
