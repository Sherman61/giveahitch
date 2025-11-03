<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/message_privacy.php';
require_once __DIR__ . '/../lib/messages.php';
require_once __DIR__ . '/../lib/ws.php';

use function App\Auth\{require_login, assert_csrf_and_get_input};

start_secure_session();
$me = require_login();
$uid = (int)($me['id'] ?? 0);
$pdo = db();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function send_json(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function fetch_target(\PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, display_name, username, message_privacy FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function resolve_sender_name(array $user): string
{
    if (!empty($user['display_name'])) {
        return (string)$user['display_name'];
    }
    if (!empty($user['username'])) {
        return (string)$user['username'];
    }
    if (!empty($user['email'])) {
        return (string)$user['email'];
    }
    return 'Someone';
}

function message_preview(string $body, int $limit = 120): string
{
    $trimmed = trim($body);
    if ($trimmed === '') {
        return 'Open your messages to read it.';
    }

    $collapsed = preg_replace('/\s+/u', ' ', $trimmed);
    if (is_string($collapsed) && $collapsed !== '') {
        $trimmed = $collapsed;
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($trimmed, 'UTF-8') <= $limit) {
            return $trimmed;
        }
        $snippet = mb_substr($trimmed, 0, $limit, 'UTF-8');
        return rtrim($snippet) . '…';
    }

    if (strlen($trimmed) <= $limit) {
        return $trimmed;
    }

    $snippet = substr($trimmed, 0, $limit);
    return rtrim($snippet) . '…';
}

if ($method === 'GET') {
    $otherId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($otherId > 0) {
        $target = fetch_target($pdo, $otherId);
        if (!$target) {
            send_json(404, ['ok' => false, 'error' => 'not_found']);
        }

        $messaging = \App\MessagePrivacy\can_message($pdo, $me, $target);

        $thread = \App\Messages\find_thread($pdo, $uid, $otherId);
        if (!$thread && ($messaging['allowed'] ?? false)) {
            $thread = \App\Messages\ensure_thread($pdo, $uid, $otherId, true);
        }

        $formattedThread = null;
        $messages = [];
        $readBroadcast = null;

        if ($thread) {
            $formattedThread = \App\Messages\hydrate_thread($pdo, $thread, $uid);
            $messages = \App\Messages\fetch_messages($pdo, (int)$thread['id'], 100);
            $readInfo = \App\Messages\mark_thread_read($pdo, $thread, $uid);
            if (!empty($readInfo['messages'])) {
                $readMessages = array_map(static function (array $msg): array {
                    return [
                        'id' => (int)$msg['id'],
                        'read_at' => $msg['read_at'],
                    ];
                }, $readInfo['messages']);
                $readBroadcast = [
                    'thread_id' => (int)$thread['id'],
                    'reader_id' => $uid,
                    'recipient_id' => (int)$target['id'],
                    'message_ids' => array_map(static fn (array $msg): int => (int)$msg['id'], $readMessages),
                    'messages' => $readMessages,
                ];
            }
        }

        if ($readBroadcast) {
            \App\WS\broadcast('dm:read', $readBroadcast, [
                'user:' . $uid,
                'user:' . (int)$target['id'],
            ]);
        }

        send_json(200, [
            'ok' => true,
            'thread' => $formattedThread,
            'messages' => $messages,
            'messaging' => [
                'allowed' => (bool)($messaging['allowed'] ?? false),
                'reason' => (string)($messaging['reason'] ?? ''),
                'level' => (int)($messaging['level'] ?? 1),
                'has_relationship' => (bool)($messaging['has_relationship'] ?? false),
            ],
            'other_user' => [
                'id' => (int)$target['id'],
                'display_name' => $target['display_name'],
                'username' => $target['username'],
            ],
        ]);
    }

    $threads = \App\Messages\list_threads($pdo, $uid);
    send_json(200, ['ok' => true, 'threads' => $threads]);
}

if ($method === 'POST') {
    $input = assert_csrf_and_get_input();
    $recipientId = (int)($input['recipient_id'] ?? $input['user_id'] ?? 0);
    $body = (string)($input['body'] ?? '');
    $clientRef = isset($input['client_ref']) ? (string)$input['client_ref'] : null;
    if ($clientRef !== null && strlen($clientRef) > 100) {
        $clientRef = substr($clientRef, 0, 100);
    }

    if ($recipientId <= 0) {
        send_json(422, ['ok' => false, 'error' => 'validation', 'fields' => ['recipient_id' => 'Select someone to message.']]);
    }
    if ($recipientId === $uid) {
        send_json(422, ['ok' => false, 'error' => 'validation', 'fields' => ['recipient_id' => 'You cannot message yourself.']]);
    }

    $target = fetch_target($pdo, $recipientId);
    if (!$target) {
        send_json(404, ['ok' => false, 'error' => 'not_found']);
    }

    $messaging = \App\MessagePrivacy\can_message($pdo, $me, $target);
    if (empty($messaging['allowed'])) {
        send_json(403, ['ok' => false, 'error' => 'forbidden', 'reason' => $messaging['reason'] ?? 'Messaging is disabled.']);
    }

    try {
        $result = \App\Messages\send_message($pdo, $uid, $recipientId, $body);
    } catch (\RuntimeException $e) {
        send_json(422, ['ok' => false, 'error' => 'validation', 'reason' => $e->getMessage()]);
    } catch (\PDOException $e) {
        send_json(500, ['ok' => false, 'error' => 'db', 'reason' => 'Unable to send message.']);
    }

    $threadRow = $result['thread'];
    $message = $result['message'];
    $threadForSender = \App\Messages\hydrate_thread($pdo, $threadRow, $uid);
    $threadForRecipient = \App\Messages\hydrate_thread($pdo, $threadRow, $recipientId);

    try {
        $senderName = resolve_sender_name($me);
        $hasRealName = !empty($me['display_name']) || !empty($me['username']) || !empty($me['email']);
        $titleName = $hasRealName && $senderName !== '' ? $senderName : 'someone';
        $preview = message_preview($message['body'] ?? '');
        $metadata = [
            'thread_id' => (int)($threadRow['id'] ?? 0),
            'message_id' => (int)($message['id'] ?? 0),
            'url' => '/messages.php?user_id=' . $uid,
        ];

        \App\Notifications\create($pdo, [
            'user_id' => $recipientId,
            'type' => 'message_received',
            'title' => sprintf('New message from %s', $titleName),
            'body' => $preview,
            'actor_user_id' => $uid,
            'actor_display_name' => $hasRealName ? $senderName : null,
            'metadata' => $metadata,
        ]);
    } catch (\Throwable $notifyError) {
        error_log('messages:notify_failed ' . $notifyError->getMessage());
    }

    \App\WS\broadcast('dm:new', [
        'thread_id' => $threadForSender['id'],
        'message' => $message,
        'sender_id' => $uid,
        'recipient_id' => $recipientId,
        'target_user_ids' => [$uid, $recipientId],
        'thread_for_sender' => $threadForSender,
        'thread_for_recipient' => $threadForRecipient,
        'client_ref' => $clientRef,
    ], [
        'user:' . $uid,
        'user:' . $recipientId,
    ]);

    send_json(200, [
        'ok' => true,
        'thread' => $threadForSender,
        'message' => $message,
        'client_ref' => $clientRef,
        'recipient' => [
            'id' => (int)$target['id'],
            'display_name' => $target['display_name'],
            'username' => $target['username'],
        ],
    ]);
}

if ($method === 'DELETE') {
    $input = assert_csrf_and_get_input();
    $messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;
    $otherId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $action = isset($input['action']) ? (string)$input['action'] : '';

    if ($messageId > 0) {
        try {
            $result = \App\Messages\delete_message($pdo, $messageId, $uid, 30);
        } catch (RuntimeException $e) {
            send_json(422, ['ok' => false, 'error' => 'validation', 'reason' => $e->getMessage()]);
        } catch (PDOException $e) {
            send_json(500, ['ok' => false, 'error' => 'db', 'reason' => 'Unable to delete message.']);
        }

        $threadRow = $result['thread'] ?? null;
        $otherUserId = (int)($result['other_user_id'] ?? 0);

        $threadForSender = $threadRow ? \App\Messages\hydrate_thread($pdo, $threadRow, $uid) : null;
        $threadForRecipient = ($threadRow && $otherUserId)
            ? \App\Messages\hydrate_thread($pdo, $threadRow, $otherUserId)
            : null;

        if ($otherUserId > 0) {
            \App\WS\broadcast('dm:delete', [
                'thread_id' => $threadRow['id'] ?? null,
                'deleted_message_ids' => [$messageId],
                'initiator_id' => $uid,
                'target_user_ids' => [$uid, $otherUserId],
                'thread_for_sender' => $threadForSender,
                'thread_for_recipient' => $threadForRecipient,
            ], [
                'user:' . $uid,
                'user:' . $otherUserId,
            ]);
        }

        send_json(200, [
            'ok' => true,
            'deleted_message_ids' => [$messageId],
            'thread' => $threadForSender,
        ]);
    }

    if ($action === 'clear' && $otherId > 0) {
        $target = fetch_target($pdo, $otherId);
        if (!$target) {
            send_json(404, ['ok' => false, 'error' => 'not_found']);
        }

        try {
            $result = \App\Messages\clear_user_messages($pdo, $uid, $otherId);
        } catch (RuntimeException $e) {
            send_json(422, ['ok' => false, 'error' => 'validation', 'reason' => $e->getMessage()]);
        } catch (PDOException $e) {
            send_json(500, ['ok' => false, 'error' => 'db', 'reason' => 'Unable to clear messages.']);
        }

        $deletedIds = array_map(static fn ($value): int => (int)$value, $result['deleted_ids'] ?? []);
        $threadRow = $result['thread'] ?? null;

        $threadForSender = $threadRow ? \App\Messages\hydrate_thread($pdo, $threadRow, $uid) : null;
        $threadForRecipient = ($threadRow && $otherId)
            ? \App\Messages\hydrate_thread($pdo, $threadRow, $otherId)
            : null;

        if ($deletedIds) {
            \App\WS\broadcast('dm:delete', [
                'thread_id' => $threadRow['id'] ?? null,
                'deleted_message_ids' => $deletedIds,
                'initiator_id' => $uid,
                'reason' => 'clear',
                'target_user_ids' => [$uid, $otherId],
                'thread_for_sender' => $threadForSender,
                'thread_for_recipient' => $threadForRecipient,
            ], [
                'user:' . $uid,
                'user:' . $otherId,
            ]);
        }

        send_json(200, [
            'ok' => true,
            'deleted_message_ids' => $deletedIds,
            'thread' => $threadForSender,
        ]);
    }

    send_json(422, ['ok' => false, 'error' => 'validation', 'reason' => 'Invalid delete request.']);
}

send_json(405, ['ok' => false, 'error' => 'method']);
