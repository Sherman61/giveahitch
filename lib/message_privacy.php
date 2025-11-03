<?php
declare(strict_types=1);

namespace App\MessagePrivacy;

use PDO;

const PRIVACY_EVERYONE      = 1;
const PRIVACY_CONNECTIONS   = 2;
const PRIVACY_NO_ONE        = 3;

/**
 * Ensure the stored privacy level falls within the supported set.
 */
function normalise(int $value): int
{
    return in_array($value, [PRIVACY_EVERYONE, PRIVACY_CONNECTIONS, PRIVACY_NO_ONE], true)
        ? $value
        : PRIVACY_EVERYONE;
}

/**
 * Determine whether two users have any ride relationship (match) history.
 */
function users_have_relationship(PDO $pdo, int $userId, int $otherUserId): bool
{
    if ($userId === $otherUserId) {
        return true;
    }

    $sql = "SELECT 1
            FROM ride_matches
            WHERE ((driver_user_id = :u AND passenger_user_id = :o)
                   OR (driver_user_id = :o AND passenger_user_id = :u))
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':u' => $userId,
        ':o' => $otherUserId,
    ]);

    return (bool)$stmt->fetchColumn();
}

/**
 * Evaluate whether a viewer is allowed to message a target user.
 *
 * @param PDO        $pdo
 * @param array|null $viewer Viewer session array (expects id/is_admin keys when present)
 * @param array      $target Target user row (expects id/message_privacy)
 *
 * @return array{allowed:bool, reason:string, level:int, has_relationship:bool}
 */
function can_message(PDO $pdo, ?array $viewer, array $target): array
{
    $level = normalise((int)($target['message_privacy'] ?? PRIVACY_EVERYONE));
    $targetId = (int)($target['id'] ?? 0);
    $viewerId = (int)($viewer['id'] ?? 0);
    $isAdmin = (bool)($viewer['is_admin'] ?? false);

    if ($targetId <= 0) {
        return [
            'allowed' => false,
            'reason' => 'Recipient not found.',
            'level' => $level,
            'has_relationship' => false,
        ];
    }

    if ($isAdmin) {
        return [
            'allowed' => true,
            'reason' => '',
            'level' => $level,
            'has_relationship' => true,
        ];
    }

    if ($viewerId === $targetId && $viewerId > 0) {
        return [
            'allowed' => true,
            'reason' => '',
            'level' => $level,
            'has_relationship' => true,
        ];
    }

    if ($viewerId <= 0) {
        return [
            'allowed' => false,
            'reason' => 'Log in to message this member.',
            'level' => $level,
            'has_relationship' => false,
        ];
    }

    $hasRelationship = users_have_relationship($pdo, $viewerId, $targetId);

    switch ($level) {
        case PRIVACY_EVERYONE:
            return [
                'allowed' => true,
                'reason' => '',
                'level' => $level,
                'has_relationship' => $hasRelationship,
            ];

        case PRIVACY_CONNECTIONS:
            if ($hasRelationship) {
                return [
                    'allowed' => true,
                    'reason' => '',
                    'level' => $level,
                    'has_relationship' => true,
                ];
            }

            return [
                'allowed' => false,
                'reason' => 'You can message this member after you share a ride or match.',
                'level' => $level,
                'has_relationship' => false,
            ];

        case PRIVACY_NO_ONE:
        default:
            return [
                'allowed' => false,
                'reason' => 'This member is not accepting direct messages.',
                'level' => $level,
                'has_relationship' => $hasRelationship,
            ];
    }
}
