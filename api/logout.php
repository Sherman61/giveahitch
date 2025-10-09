<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/../lib/session.php';
require_once __DIR__.'/../lib/auth.php';
use function App\Auth\csrf_verify;

\start_secure_session();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }

$input = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: [];
csrf_verify((string)($input['csrf'] ?? ''));

$_SESSION = [];
session_destroy();
echo json_encode(['ok'=>true]);
?>