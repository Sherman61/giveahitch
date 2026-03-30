<?php
declare(strict_types=1);

namespace App\SecurityEvents;

use PDO;

/**
 * @param array<string,mixed> $context
 */
function log_event(PDO $pdo, string $eventKey, string $severity = 'info', array $context = []): void
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO security_events (
                event_key,
                severity,
                actor_user_id,
                target_user_id,
                ip_address,
                user_agent,
                details,
                metadata
            ) VALUES (
                :event_key,
                :severity,
                :actor_user_id,
                :target_user_id,
                :ip_address,
                :user_agent,
                :details,
                :metadata
            )
        ");
        $stmt->execute([
            ':event_key' => $eventKey,
            ':severity' => $severity,
            ':actor_user_id' => isset($context['actor_user_id']) ? (int)$context['actor_user_id'] : null,
            ':target_user_id' => isset($context['target_user_id']) ? (int)$context['target_user_id'] : null,
            ':ip_address' => isset($context['ip_address']) ? trim((string)$context['ip_address']) : ($_SERVER['REMOTE_ADDR'] ?? null),
            ':user_agent' => isset($context['user_agent']) ? trim((string)$context['user_agent']) : ($_SERVER['HTTP_USER_AGENT'] ?? null),
            ':details' => isset($context['details']) ? trim((string)$context['details']) : null,
            ':metadata' => !empty($context['metadata']) ? json_encode($context['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (\Throwable $e) {
        error_log('security_events:log_failed ' . $e->getMessage());
    }
}
