<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/reports.php';

use function App\Auth\{require_login, current_user, csrf_verify};
use function App\Reports\is_valid_ride_report_reason;

start_secure_session();
require_login();

$reporter = current_user();
$reporterId = (int)$reporter['id'];

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$rideId = (int)($in['ride_id'] ?? 0);
$reasonKey = trim((string)($in['reason'] ?? ''));
$details = trim((string)($in['details'] ?? ''));

if ($rideId <= 0 || !is_valid_ride_report_reason($reasonKey)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'bad_input']);
    exit;
}

if (mb_strlen($details) > 1000) {
    $details = mb_substr($details, 0, 1000);
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $rideStmt = $pdo->prepare("
        SELECT id, user_id, from_text, to_text, deleted
        FROM rides
        WHERE id = :ride_id
        FOR UPDATE
    ");
    $rideStmt->execute([':ride_id' => $rideId]);
    $ride = $rideStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ride || !empty($ride['deleted'])) {
        throw new RuntimeException('not_found');
    }

    $reportedUserId = (int)($ride['user_id'] ?? 0);
    if ($reportedUserId === $reporterId) {
        throw new RuntimeException('own_ride');
    }

    $existingStmt = $pdo->prepare("
        SELECT id
        FROM ride_reports
        WHERE ride_id = :ride_id
          AND reporter_user_id = :reporter_user_id
          AND reason_key = :reason_key
          AND status IN ('open', 'reviewing')
        LIMIT 1
    ");
    $existingStmt->execute([
        ':ride_id' => $rideId,
        ':reporter_user_id' => $reporterId,
        ':reason_key' => $reasonKey,
    ]);
    if ($existingStmt->fetch()) {
        throw new RuntimeException('already_reported');
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO ride_reports (
            ride_id,
            reporter_user_id,
            reported_user_id,
            reason_key,
            details,
            status
        ) VALUES (
            :ride_id,
            :reporter_user_id,
            :reported_user_id,
            :reason_key,
            :details,
            'open'
        )
    ");
    $insertStmt->execute([
        ':ride_id' => $rideId,
        ':reporter_user_id' => $reporterId,
        ':reported_user_id' => $reportedUserId ?: null,
        ':reason_key' => $reasonKey,
        ':details' => $details !== '' ? $details : null,
    ]);

    $reportId = (int)$pdo->lastInsertId();

    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'report_id' => $reportId,
    ]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $map = [
        'not_found' => 404,
        'own_ride' => 409,
        'already_reported' => 409,
    ];
    http_response_code($map[$e->getMessage()] ?? 400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e instanceof \PDOException && stripos($e->getMessage(), 'ride_reports') !== false) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'error' => 'reporting_unavailable']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server']);
}
