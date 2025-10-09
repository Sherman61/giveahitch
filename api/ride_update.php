<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors','1');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/validate.php';

use function App\Auth\{require_login, current_user, csrf_verify};

try {
    start_secure_session();
    require_login();
    $me = current_user();

    // Accept JSON or form
    $raw = file_get_contents('php://input');
    $in = $_POST;
    if ($raw && (!$in || count($in) === 0)) {
        $tmp = json_decode($raw, true);
        if (is_array($tmp)) { $in = $tmp; }
    }

    // CSRF
    $csrf = isset($in['csrf']) ? (string)$in['csrf'] : '';
    csrf_verify($csrf);

    // Ride id
    $rideId = isset($in['id']) ? (int)$in['id'] : 0;
    if ($rideId <= 0) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'bad_id']);
        exit;
    }

    // Validate payload
    list($errors, $data) = validate_ride($in);
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'errors'=>$errors,'error'=>'validation_failed']);
        exit;
    }

    $pdo = db();
    $pdo->beginTransaction();

    // Lock & owner check
    $stmt = $pdo->prepare("SELECT id,user_id,status FROM rides WHERE id=:id AND deleted=0 FOR UPDATE");
    $stmt->bindValue(':id', $rideId, PDO::PARAM_INT);
    $stmt->execute();
    $ride = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ride) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok'=>false,'error'=>'not_found']);
        exit;
    }
    if ((int)$ride['user_id'] !== (int)$me['id']) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'not_owner']);
        exit;
    }

    // Update record
    $sql = "UPDATE rides SET
              type = :type,
              from_text = :from_text,
              to_text = :to_text,
              ride_datetime = :ride_datetime,
              seats = :seats,
              package_only = :package_only,
              note = :note,
              phone = :phone,
              whatsapp = :whatsapp
            WHERE id = :id";
    $upd = $pdo->prepare($sql);
    $upd->bindValue(':type', $data['type'], PDO::PARAM_STR);
    $upd->bindValue(':from_text', $data['from_text'], PDO::PARAM_STR);
    $upd->bindValue(':to_text', $data['to_text'], PDO::PARAM_STR);
    // ride_datetime may be null
    if ($data['ride_datetime'] === null) {
        $upd->bindValue(':ride_datetime', null, PDO::PARAM_NULL);
    } else {
        $upd->bindValue(':ride_datetime', $data['ride_datetime'], PDO::PARAM_STR);
    }
    $upd->bindValue(':seats', (int)$data['seats'], PDO::PARAM_INT);
    $upd->bindValue(':package_only', (int)$data['package_only'], PDO::PARAM_INT);
    $upd->bindValue(':note', $data['note'], PDO::PARAM_STR);
    $upd->bindValue(':phone', $data['phone'], PDO::PARAM_STR);
    $upd->bindValue(':whatsapp', $data['whatsapp'], PDO::PARAM_STR);
    $upd->bindValue(':id', $rideId, PDO::PARAM_INT);
    $upd->execute();

    $pdo->commit();
    echo json_encode(['ok'=>true]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
