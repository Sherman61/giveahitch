<?php
require __DIR__ . '/../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
$t = $_ENV['MAILTRAP_TOKEN'] ?? '';
$ok = $t !== '';
header('Content-Type: application/json');
echo json_encode([
    'env_loaded' => $ok,
    'token_suffix' => $ok ? substr($t, -4) : null,
]);
