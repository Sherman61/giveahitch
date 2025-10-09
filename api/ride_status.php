<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/security.php';

use function App\Security\verify_csrf;
use function App\Security\rate_limit;

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
rate_limit($pdo, 'ride_status', 30, 60); // 30 actions/min per IP

$id = (int)($_POST['id'] ?? 0);
$newStatus = $_POST['status'] ?? '';
verify_csrf($_POST['csrf'] ?? '');

if ($id < 1 || !in_array($newStatus, ['open','matched','cancelled','deleted'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

$stmt = $pdo->prepare("UPDATE rides SET status=:s, deleted = IF(:s='deleted',1,deleted), updated_at=NOW() WHERE id=:id");
$stmt->execute([':s' => $newStatus, ':id' => $id]);

echo json_encode(['ok' => true]);
?>