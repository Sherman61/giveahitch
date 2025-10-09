<?php
declare(strict_types=1);

/**
 * GET /glitchahitch/api/ride_list.php?type=offer|request&q=brooklyn&limit=50
 * Returns JSON: { ok: true, items: [...] }
 *
 * Security:
 * - Read-only endpoint
 * - Uses prepared statements
 * - Adds strict headers
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: geolocation=(), microphone=()');
// CORS is optional; enable only if you must call this from a different origin
// header('Access-Control-Allow-Origin: https://shiyaswebsite.com');

$root = dirname(__DIR__); // /var/www/shiyaswebsite.com/glitchahitch
$configPath = $root . '/config/db.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB config not found', 'hint' => $configPath]);
    exit;
}

require_once $configPath; // must define function db(): PDO

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection failed']);
    exit;
}

// Parse and clamp inputs
$type  = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : null;   // offer|request|null
$q     = isset($_GET['q'])    ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit < 1)  $limit = 1;
if ($limit > 100) $limit = 100;

// Build SQL safely
$sql = "SELECT id, type, from_text, to_text, ride_datetime, seats, package_only, note, phone, whatsapp, status, created_at
        FROM rides
        WHERE status = :status AND deleted = 0";
$params = [':status' => 'open'];

if ($type === 'offer' || $type === 'request') {
    $sql .= " AND type = :type";
    $params[':type'] = $type;
}

if ($q !== '') {
    // Simple contains search on from/to/note
    $sql .= " AND (from_text LIKE :q OR to_text LIKE :q OR note LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

// Sort: upcoming first (use ride time when provided, else created_at)
$sql .= " ORDER BY COALESCE(ride_datetime, created_at) ASC LIMIT :lim";

try {
    $stmt = $pdo->prepare($sql);

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return raw DB values (frontend should escape for display)
    echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Query failed']);
}
