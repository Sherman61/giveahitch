<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

use function App\Auth\{require_login, current_user, csrf_verify, is_admin};

start_secure_session();
require_login();

$input = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($input['csrf'] ?? ''));

$id = (int)($input['id'] ?? 0);
if ($id <= 0) { http_response_code(422); echo json_encode(['ok'=>false,'error'=>'bad_id']); exit; }

$pdo = db();
$stmt = $pdo->prepare("SELECT id, user_id FROM rides WHERE id = :id AND deleted = 0");
$stmt->execute([':id'=>$id]);
$ride = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ride) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }

$u = current_user();
if (!is_admin() && (int)($ride['user_id'] ?? 0) !== (int)$u['id']) {
    http_response_code(403); echo json_encode(['ok'=>false,'error'=>'forbidden']); exit;
}

$pdo->prepare("UPDATE rides SET deleted = 1, status = 'cancelled' WHERE id = :id")->execute([':id'=>$id]);

echo json_encode(['ok'=>true]);
?>