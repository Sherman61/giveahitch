<?php
declare(strict_types=1);
namespace App\Err;


 
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
use PDO;

function map_errno(int $errno): string {
    $map = [
        E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE', E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING', E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING', E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED'
    ];
    return $map[$errno] ?? (string)$errno;
}

function save_error(array $row): void {
    try {
        $pdo = \db();
        $stmt = $pdo->prepare(
            "INSERT INTO app_errors (page, endpoint, message, errno, file, line, severity, context_snip, user_id)
             VALUES (:page,:endpoint,:message,:errno,:file,:line,:severity,:context_snip,:user_id)"
        );
        foreach ([
            ':page'         => (string)($row['page'] ?? ''),
            ':endpoint'     => (string)($row['endpoint'] ?? ''),
            ':message'      => (string)($row['message'] ?? ''),
            ':errno'        => isset($row['errno']) ? (int)$row['errno'] : null,
            ':file'         => (string)($row['file'] ?? ''),
            ':line'         => isset($row['line']) ? (int)$row['line'] : null,
            ':severity'     => (string)($row['severity'] ?? ''),
            ':context_snip' => (string)($row['context_snip'] ?? ''),
            ':user_id'      => isset($row['user_id']) ? (int)$row['user_id'] : null,
        ] as $k=>$v) {
            $stmt->bindValue($k, $v, ($v===null ? PDO::PARAM_NULL : PDO::PARAM_STR));
        }
        if ($row['errno'] ?? null) $stmt->bindValue(':errno', (int)$row['errno'], PDO::PARAM_INT);
        if ($row['line']  ?? null) $stmt->bindValue(':line',  (int)$row['line'],  PDO::PARAM_INT);
        if ($row['user_id'] ?? null) $stmt->bindValue(':user_id', (int)$row['user_id'], PDO::PARAM_INT);
        $stmt->execute();
    } catch (\Throwable $e) {
        // As a last resort, do nothing (avoid recursion).
    }
}

function init_error_logging(): void {
    // soft include of session/current user (don’t fatal if not available)
    $user = null;
    try { $user = \App\Auth\current_user(); } catch (\Throwable $e) {}

    $page = $_SERVER['SCRIPT_NAME'] ?? '';
    $endpoint = $_SERVER['REQUEST_URI'] ?? '';

    set_error_handler(function($errno, $errstr, $errfile, $errline) use ($user, $page, $endpoint) {
        // Respect @-operator
        if (!(error_reporting() & $errno)) return false;
        save_error([
            'page'     => $page,
            'endpoint' => $endpoint,
            'message'  => (string)$errstr,
            'errno'    => (int)$errno,
            'file'     => (string)$errfile,
            'line'     => (int)$errline,
            'severity' => map_errno((int)$errno),
            'user_id'  => $user['id'] ?? null,
        ]);
        return false; // let PHP’s normal handler continue (and display if display_errors=1)
    });

    register_shutdown_function(function() use ($user, $page, $endpoint) {
        $last = error_get_last();
        if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            save_error([
                'page'     => $page,
                'endpoint' => $endpoint,
                'message'  => (string)$last['message'],
                'errno'    => (int)$last['type'],
                'file'     => (string)$last['file'],
                'line'     => (int)$last['line'],
                'severity' => 'FATAL',
                'context_snip' => 'shutdown handler captured fatal error',
                'user_id'  => $user['id'] ?? null,
            ]);
        }
    });
}
