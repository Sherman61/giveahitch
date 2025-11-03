<?php
declare(strict_types=1);

namespace App\Messages;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Sort a pair of user identifiers so user_a_id is always the smaller value.
 *
 * @return array{0:int,1:int}
 */
function normalise_pair(int $userId, int $otherUserId): array
{
    if ($userId === $otherUserId) {
        return [$userId, $otherUserId];
    }

    return ($userId < $otherUserId)
        ? [$userId, $otherUserId]
        : [$otherUserId, $userId];
}

function find_thread(PDO $pdo, int $userId, int $otherUserId): ?array
{
    [$a, $b] = normalise_pair($userId, $otherUserId);
    $stmt = $pdo->prepare('SELECT * FROM user_message_threads WHERE user_a_id = :a AND user_b_id = :b LIMIT 1');
    $stmt->execute([':a' => $a, ':b' => $b]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function thread_by_id(PDO $pdo, int $threadId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM user_message_threads WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $threadId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function ensure_thread(PDO $pdo, int $userId, int $otherUserId, bool $create = false): ?array
{
    if ($userId <= 0 || $otherUserId <= 0) {
        return null;
    }

    $thread = find_thread($pdo, $userId, $otherUserId);
    if ($thread || !$create) {
        return $thread;
    }

    [$a, $b] = normalise_pair($userId, $otherUserId);

    try {
        $stmt = $pdo->prepare('INSERT INTO user_message_threads (user_a_id, user_b_id) VALUES (:a, :b)');
        $stmt->execute([':a' => $a, ':b' => $b]);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        // Duplicate pair inserted concurrently – safe to proceed.
    }

    $threadId = (int)$pdo->lastInsertId();
    if ($threadId > 0) {
        return thread_by_id($pdo, $threadId);
    }

    return find_thread($pdo, $userId, $otherUserId);
}

function message_by_id(PDO $pdo, int $messageId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM user_messages WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $messageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function latest_message(PDO $pdo, int $threadId): ?array
{
    $stmt = $pdo->prepare('SELECT *
                            FROM user_messages
                            WHERE thread_id = :tid
                            ORDER BY id DESC
                            LIMIT 1');
    $stmt->execute([':tid' => $threadId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function fetch_messages(PDO $pdo, int $threadId, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare('SELECT id, thread_id, sender_user_id, body, created_at, read_at
                            FROM user_messages
                            WHERE thread_id = :tid
                            ORDER BY id ASC
                            LIMIT :lim');
    $stmt->bindValue(':tid', $threadId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn (array $row): array => format_message_row($row), $rows);
}

function format_message_row(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'thread_id' => (int)$row['thread_id'],
        'sender_user_id' => (int)$row['sender_user_id'],
        'body' => (string)$row['body'],
        'created_at' => $row['created_at'],
        'read_at' => $row['read_at'],
    ];
}

function mark_thread_read(PDO $pdo, array $thread, int $userId): array
{
    $threadId = (int)($thread['id'] ?? 0);
    if ($threadId <= 0) {
        return ['messages' => [], 'message_ids' => []];
    }

    $col = null;
    if ((int)$thread['user_a_id'] === $userId) {
        $col = 'user_a_unread';
    } elseif ((int)$thread['user_b_id'] === $userId) {
        $col = 'user_b_unread';
    }

    if ($col) {
        $pdo->prepare("UPDATE user_message_threads SET {$col} = 0 WHERE id = :id")
            ->execute([':id' => $threadId]);
    }

    $stmt = $pdo->prepare('SELECT id
                            FROM user_messages
                            WHERE thread_id = :tid
                              AND sender_user_id <> :uid
                              AND read_at IS NULL');
    $stmt->execute([':tid' => $threadId, ':uid' => $userId]);
    $ids = array_map(static fn ($value): int => (int)$value, $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$ids) {
        return ['messages' => [], 'message_ids' => []];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $updateSql = sprintf('UPDATE user_messages SET read_at = NOW() WHERE id IN (%s)', $placeholders);
    $update = $pdo->prepare($updateSql);
    foreach ($ids as $index => $id) {
        $update->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    $update->execute();

    $fetchSql = sprintf('SELECT id, read_at FROM user_messages WHERE id IN (%s)', $placeholders);
    $fetch = $pdo->prepare($fetchSql);
    foreach ($ids as $index => $id) {
        $fetch->bindValue($index + 1, $id, PDO::PARAM_INT);
    }
    $fetch->execute();

    $rows = $fetch->fetchAll(PDO::FETCH_ASSOC);

    $messages = array_map(static fn (array $row): array => [
        'id' => (int)$row['id'],
        'read_at' => $row['read_at'],
    ], $rows);

    return [
        'messages' => $messages,
        'message_ids' => $ids,
    ];
}

function list_threads(PDO $pdo, int $userId): array
{
    $sql = 'SELECT t.*, other.id   AS other_user_id,
                   other.display_name AS other_display_name,
                   other.username AS other_username,
                   other.message_privacy AS other_message_privacy,
                   lm.id        AS last_message_id,
                   lm.sender_user_id AS last_message_sender,
                   lm.body      AS last_message_body,
                   lm.created_at AS last_message_created_at
            FROM user_message_threads t
            JOIN users other ON other.id = CASE WHEN t.user_a_id = :uid THEN t.user_b_id ELSE t.user_a_id END
            LEFT JOIN user_messages lm ON lm.id = t.last_message_id
            WHERE t.user_a_id = :uid OR t.user_b_id = :uid
            ORDER BY COALESCE(t.last_message_at, t.updated_at, t.created_at) DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static fn (array $row): array => format_thread_row($row, $userId), $rows);
}

function format_thread_row(array $row, int $viewerId): array
{
    $threadId = (int)$row['id'];
    $userA = (int)$row['user_a_id'];
    $userB = (int)$row['user_b_id'];
    $otherId = ($userA === $viewerId) ? $userB : $userA;
    $unread = ($userA === $viewerId) ? (int)$row['user_a_unread'] : (int)$row['user_b_unread'];

    $thread = [
        'id' => $threadId,
        'user_a_id' => $userA,
        'user_b_id' => $userB,
        'other_user' => [
            'id' => (int)($row['other_user_id'] ?? $otherId),
            'display_name' => $row['other_display_name'] ?? $row['display_name'] ?? '',
            'username' => $row['other_username'] ?? null,
            'message_privacy' => isset($row['other_message_privacy']) ? (int)$row['other_message_privacy'] : null,
        ],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'last_message_at' => $row['last_message_at'] ?? $row['last_message_created_at'] ?? null,
        'unread_count' => max(0, $unread),
    ];

    if (!empty($row['last_message_id'])) {
        $thread['last_message'] = [
            'id' => (int)$row['last_message_id'],
            'sender_user_id' => (int)($row['last_message_sender'] ?? 0),
            'body' => (string)($row['last_message_body'] ?? ''),
            'created_at' => $row['last_message_created_at'] ?? null,
        ];
    }

    return $thread;
}

function hydrate_thread(PDO $pdo, array $thread, int $viewerId): array
{
    $threadId = (int)($thread['id'] ?? 0);
    if ($threadId <= 0) {
        throw new RuntimeException('Thread missing identifier.');
    }

    $userA = (int)$thread['user_a_id'];
    $userB = (int)$thread['user_b_id'];
    $otherId = ($userA === $viewerId) ? $userB : $userA;

    $row = $thread;
    if ($otherId > 0) {
        $stmt = $pdo->prepare('SELECT id, display_name, username, message_privacy FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $otherId]);
        if ($other = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['other_user_id'] = (int)$other['id'];
            $row['other_display_name'] = $other['display_name'];
            $row['other_username'] = $other['username'];
            $row['other_message_privacy'] = $other['message_privacy'];
        }
    }

    if (!empty($thread['last_message_id'])) {
        $last = message_by_id($pdo, (int)$thread['last_message_id']);
        if ($last) {
            $row['last_message_id'] = $last['id'];
            $row['last_message_body'] = $last['body'];
            $row['last_message_sender'] = $last['sender_user_id'];
            $row['last_message_created_at'] = $last['created_at'];
            $row['last_message_at'] = $last['created_at'];
        }
    } else {
        $last = latest_message($pdo, $threadId);
        if ($last) {
            $row['last_message_id'] = $last['id'];
            $row['last_message_body'] = $last['body'];
            $row['last_message_sender'] = $last['sender_user_id'];
            $row['last_message_created_at'] = $last['created_at'];
            $row['last_message_at'] = $last['created_at'];
        }
    }

    return format_thread_row($row, $viewerId);
}

function send_message(PDO $pdo, int $senderId, int $recipientId, string $body): array
{
    if ($senderId <= 0 || $recipientId <= 0) {
        throw new RuntimeException('Invalid sender or recipient.');
    }

    $content = trim($body);
    if ($content === '') {
        throw new RuntimeException('Message cannot be empty.');
    }

    if (mb_strlen($content) > 2000) {
        throw new RuntimeException('Message is too long.');
    }

    $pdo->beginTransaction();
    try {
        $thread = ensure_thread($pdo, $senderId, $recipientId, true);
        if (!$thread) {
            throw new RuntimeException('Unable to load or create conversation.');
        }

        $stmt = $pdo->prepare('INSERT INTO user_messages (thread_id, sender_user_id, body)
                                VALUES (:tid, :sid, :body)');
        $stmt->execute([
            ':tid' => (int)$thread['id'],
            ':sid' => $senderId,
            ':body' => $content,
        ]);

        $messageId = (int)$pdo->lastInsertId();
        $message = message_by_id($pdo, $messageId);
        if (!$message) {
            throw new RuntimeException('Failed to load saved message.');
        }

        $messageCreatedAt = $message['created_at'] ?? null;
        $col = ((int)$thread['user_a_id'] === $senderId) ? 'user_b_unread' : 'user_a_unread';

        $updateSql = "UPDATE user_message_threads
                       SET last_message_id = :mid,
                           last_message_at = :created,
                           updated_at = NOW(),
                           {$col} = {$col} + 1
                       WHERE id = :tid";
        $pdo->prepare($updateSql)->execute([
            ':mid' => $message['id'],
            ':created' => $messageCreatedAt,
            ':tid' => (int)$thread['id'],
        ]);

        $pdo->commit();

        $freshThread = thread_by_id($pdo, (int)$thread['id']);
        if ($freshThread) {
            $thread = $freshThread;
        }

        return [
            'thread' => $thread,
            'message' => format_message_row($message),
        ];
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        throw $e;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Recalculate cached metadata for a thread after mutations.
 *
 * @throws RuntimeException when the thread cannot be refreshed.
 */
function refresh_thread(PDO $pdo, array $thread): array
{
    $threadId = (int)($thread['id'] ?? 0);
    if ($threadId <= 0) {
        throw new RuntimeException('Thread missing identifier.');
    }

    $userA = (int)$thread['user_a_id'];
    $userB = (int)$thread['user_b_id'];

    $last = latest_message($pdo, $threadId);
    $lastId = $last['id'] ?? null;
    $lastCreatedAt = $last['created_at'] ?? null;

    $stmt = $pdo->prepare('SELECT sender_user_id, COUNT(*) AS unread
                             FROM user_messages
                             WHERE thread_id = :tid
                               AND read_at IS NULL
                             GROUP BY sender_user_id');
    $stmt->execute([':tid' => $threadId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = [];
    foreach ($rows as $row) {
        $counts[(int)$row['sender_user_id']] = (int)$row['unread'];
    }

    $userAUnread = $counts[$userB] ?? 0;
    $userBUnread = $counts[$userA] ?? 0;

    $update = $pdo->prepare('UPDATE user_message_threads
                                SET last_message_id = :mid,
                                    last_message_at = :mat,
                                    updated_at = NOW(),
                                    user_a_unread = :ua,
                                    user_b_unread = :ub
                                WHERE id = :tid');

    if ($lastId !== null) {
        $update->bindValue(':mid', (int)$lastId, PDO::PARAM_INT);
    } else {
        $update->bindValue(':mid', null, PDO::PARAM_NULL);
    }

    if ($lastCreatedAt !== null) {
        $update->bindValue(':mat', $lastCreatedAt, PDO::PARAM_STR);
    } else {
        $update->bindValue(':mat', null, PDO::PARAM_NULL);
    }

    $update->bindValue(':ua', $userAUnread, PDO::PARAM_INT);
    $update->bindValue(':ub', $userBUnread, PDO::PARAM_INT);
    $update->bindValue(':tid', $threadId, PDO::PARAM_INT);
    $update->execute();

    $fresh = thread_by_id($pdo, $threadId);
    if (!$fresh) {
        throw new RuntimeException('Unable to refresh conversation.');
    }

    return $fresh;
}

/**
 * Delete a message authored by the acting user, enforcing a grace period.
 *
 * @return array{thread:array|null,other_user_id:int}
 */
function delete_message(PDO $pdo, int $messageId, int $actorId, int $graceSeconds = 30): array
{
    if ($messageId <= 0) {
        throw new RuntimeException('Invalid message.');
    }

    $message = message_by_id($pdo, $messageId);
    if (!$message) {
        throw new RuntimeException('Message not found.');
    }

    if ((int)$message['sender_user_id'] !== $actorId) {
        throw new RuntimeException('You can only delete your own messages.');
    }

    $createdAt = $message['created_at'] ?? null;
    if ($createdAt) {
        $createdTs = strtotime($createdAt);
        if ($createdTs !== false && $createdTs < (time() - max(1, $graceSeconds))) {
            throw new RuntimeException('You can only delete a message shortly after sending it.');
        }
    }

    $thread = thread_by_id($pdo, (int)$message['thread_id']);
    if (!$thread) {
        throw new RuntimeException('Conversation missing.');
    }

    $userA = (int)$thread['user_a_id'];
    $userB = (int)$thread['user_b_id'];
    if ($actorId !== $userA && $actorId !== $userB) {
        throw new RuntimeException('You are not part of this conversation.');
    }
    $otherUserId = ($actorId === $userA) ? $userB : $userA;

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM user_messages WHERE id = :id')
            ->execute([':id' => $messageId]);

        $updatedThread = refresh_thread($pdo, $thread);

        $pdo->commit();

        return [
            'thread' => $updatedThread,
            'other_user_id' => $otherUserId,
        ];
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        throw $e;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Remove all messages authored by the acting user in a conversation.
 *
 * @return array{thread:array|null,deleted_ids:int[],other_user_id:int}
 */
function clear_user_messages(PDO $pdo, int $actorId, int $otherUserId): array
{
    if ($actorId <= 0 || $otherUserId <= 0) {
        throw new RuntimeException('Invalid conversation.');
    }

    $thread = find_thread($pdo, $actorId, $otherUserId);
    if (!$thread) {
        return [
            'thread' => null,
            'deleted_ids' => [],
            'other_user_id' => $otherUserId,
        ];
    }

    $threadId = (int)$thread['id'];

    $stmt = $pdo->prepare('SELECT id FROM user_messages WHERE thread_id = :tid AND sender_user_id = :uid');
    $stmt->execute([':tid' => $threadId, ':uid' => $actorId]);
    $ids = array_map(static fn ($value): int => (int)$value, $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$ids) {
        $fresh = refresh_thread($pdo, $thread);
        return [
            'thread' => $fresh,
            'deleted_ids' => [],
            'other_user_id' => $otherUserId,
        ];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare(sprintf('DELETE FROM user_messages WHERE id IN (%s)', $placeholders));
        foreach ($ids as $index => $id) {
            $delete->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $delete->execute();

        $updatedThread = refresh_thread($pdo, $thread);

        $pdo->commit();

        return [
            'thread' => $updatedThread,
            'deleted_ids' => $ids,
            'other_user_id' => $otherUserId,
        ];
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        throw $e;
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}
