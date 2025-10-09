<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

use function App\Auth\{require_login, current_user, csrf_verify};

start_secure_session();
require_login();
$uid = (int) current_user()['id'];

$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($in['csrf'] ?? ''));

$type = (string)($in['type'] ?? '');
$from = trim((string)($in['from_text'] ?? ''));
$to   = trim((string)($in['to_text']   ?? ''));
$rideDtRaw = (string)($in['ride_datetime'] ?? '');
$rideEndRaw = (string)($in['ride_end_datetime'] ?? '');
$seats  = isset($in['seats']) ? max(0, (int)$in['seats']) : 1;
$pkg    = !empty($in['package_only']) ? 1 : 0;
$note   = trim((string)($in['note'] ?? ''));
$phone  = trim((string)($in['phone'] ?? ''));
$wa     = trim((string)($in['whatsapp'] ?? ''));

$parseInputDate = static function (string $value): ?DateTimeImmutable {
  $value = trim($value);
  if ($value === '') {
    return null;
  }
  $value = str_replace('T', ' ', $value);
  $value = (string)preg_replace('/\.(\d+)$/', '', $value);
  $formats = ['Y-m-d H:i:s', 'Y-m-d H:i'];
  foreach ($formats as $fmt) {
    $dt = \DateTimeImmutable::createFromFormat($fmt, $value);
    if ($dt instanceof \DateTimeImmutable) {
      return $dt;
    }
  }
  return null;
};

$rideDtObj = $parseInputDate($rideDtRaw);
if ($rideDtRaw !== '' && !$rideDtObj) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'validation']);
  exit;
}

$rideEndObj = $parseInputDate($rideEndRaw);
if ($rideEndRaw !== '' && !$rideEndObj) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'validation']);
  exit;
}

if ($rideDtObj && $rideEndObj && $rideEndObj <= $rideDtObj) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'validation']);
  exit;
}

$rideDt = $rideDtObj ? $rideDtObj->format('Y-m-d H:i:s') : null;
$rideEndDt = $rideEndObj ? $rideEndObj->format('Y-m-d H:i:s') : null;

if (!in_array($type, ['offer','request'], true) || $from==='' || $to==='') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'validation']); exit;
}

$pdo = db();
$pdo->beginTransaction();

try {
  $stmt = $pdo->prepare("
    INSERT INTO rides (user_id,type,from_text,to_text,ride_datetime,ride_end_datetime,seats,package_only,note,phone,whatsapp,status,deleted,created_at,updated_at)
    VALUES (:u,:t,:f,:to,:dt,:dt_end,:seats,:pkg,:note,:ph,:wa,'open',0,NOW(),NOW())
  ");
  $stmt->execute([
    ':u'=>$uid, ':t'=>$type, ':f'=>$from, ':to'=>$to,
    ':dt'=> $rideDt,
    ':dt_end'=> $rideEndDt,
    ':seats'=>$seats, ':pkg'=>$pkg, ':note'=>($note!==''?$note:null),
    ':ph'=>($phone!==''?$phone:null), ':wa'=>($wa!==''?$wa:null),
  ]);
  $rideId = (int)$pdo->lastInsertId();

  // bump counters
  if ($type === 'offer') {
    $pdo->prepare("UPDATE users SET rides_offered_count = rides_offered_count + 1 WHERE id=:u")
        ->execute([':u'=>$uid]);
  } else {
    $pdo->prepare("UPDATE users SET rides_requested_count = rides_requested_count + 1 WHERE id=:u")
        ->execute([':u'=>$uid]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'id'=>$rideId]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server']);
}
