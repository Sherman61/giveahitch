<?php
declare(strict_types=1);

namespace App\Scoring;

use PDO;

const RULES = [
    'match_confirmed' => [
        'label' => 'Ride owner confirms a match',
        'points' => 100,
        'awarded_to' => 'Both the driver and the rider',
        'description' => 'When the ride owner chooses a pending match and confirms the trip.',
    ],
    'perfect_rating_received' => [
        'label' => 'Receive a 5-star rating',
        'points' => 100,
        'awarded_to' => 'The person who receives the 5-star rating',
        'description' => 'Applied after a completed ride when the other person submits a perfect rating.',
    ],
    'admin_adjustment' => [
        'label' => 'Manual admin score adjustment',
        'points' => 0,
        'awarded_to' => 'The member whose score was adjusted',
        'description' => 'Used when an admin manually raises or lowers a score and records the reason.',
    ],
];

const ZERO_POINT_EVENTS = [
    'Posting a ride',
    'Requesting a ride',
    'Joining a ride',
    'Completing a ride',
    'Receiving a rating below 5 stars',
    'Cancelling a ride or match',
];

/**
 * @return array<string,array{label:string,points:int,awarded_to:string,description:string}>
 */
function rules(): array
{
    return RULES;
}

function points_for(string $event): int
{
    return (int)(RULES[$event]['points'] ?? 0);
}

function rating_bonus_for(int $stars): int
{
    return $stars === 5 ? points_for('perfect_rating_received') : 0;
}

/**
 * @return list<string>
 */
function zero_point_events(): array
{
    return ZERO_POINT_EVENTS;
}

/**
 * @param array<string,mixed> $context
 */
function log_event(PDO $pdo, int $userId, int $points, string $reasonKey, array $context = []): void
{
    if ($userId <= 0 || $points === 0) {
        return;
    }

    $rule = RULES[$reasonKey] ?? null;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO score_events (
                user_id,
                points_delta,
                reason_key,
                reason_label,
                details,
                related_ride_id,
                related_match_id,
                related_rating_id,
                actor_user_id,
                metadata
            ) VALUES (
                :user_id,
                :points_delta,
                :reason_key,
                :reason_label,
                :details,
                :related_ride_id,
                :related_match_id,
                :related_rating_id,
                :actor_user_id,
                :metadata
            )
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':points_delta' => $points,
            ':reason_key' => $reasonKey,
            ':reason_label' => $rule['label'] ?? $reasonKey,
            ':details' => isset($context['details']) ? (string)$context['details'] : null,
            ':related_ride_id' => isset($context['ride_id']) ? (int)$context['ride_id'] : null,
            ':related_match_id' => isset($context['match_id']) ? (int)$context['match_id'] : null,
            ':related_rating_id' => isset($context['rating_id']) ? (int)$context['rating_id'] : null,
            ':actor_user_id' => isset($context['actor_user_id']) ? (int)$context['actor_user_id'] : null,
            ':metadata' => !empty($context['metadata']) ? json_encode($context['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (\PDOException $e) {
        if (stripos($e->getMessage(), 'score_events') === false) {
            throw $e;
        }
    }
}

/**
 * @param array<string,mixed> $context
 */
function award(PDO $pdo, int $userId, int $points, string $reasonKey, array $context = []): void
{
    if ($userId <= 0 || $points === 0) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE users SET score = score + :points WHERE id = :user_id');
    $stmt->execute([
        ':points' => $points,
        ':user_id' => $userId,
    ]);

    log_event($pdo, $userId, $points, $reasonKey, $context);
}

/**
 * @param list<int> $userIds
 * @param array<string,mixed> $context
 */
function award_many(PDO $pdo, array $userIds, int $points, string $reasonKey, array $context = []): void
{
    if ($points === 0) {
        return;
    }

    $uniqueUserIds = array_values(array_unique(array_map('intval', $userIds)));
    foreach ($uniqueUserIds as $userId) {
        award($pdo, $userId, $points, $reasonKey, $context);
    }
}
