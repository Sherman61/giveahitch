<?php
declare(strict_types=1);

namespace App\Rides;

require_once __DIR__ . '/status.php';
require_once __DIR__ . '/ws.php';

use PDO;

use function App\Status\{from_db, to_db};

/**
 * Map a ride owner and a joining user into driver/passenger roles.
 *
 * The owner is always the user who posted the ride.
 * The ride type decides which role that owner is playing:
 * - offer: owner is the driver, joiner is the passenger
 * - request: owner is the passenger, joiner is the driver
 *
 * @param array<string,mixed> $ride
 * @return array{
 *   owner_user_id:int,
 *   joiner_user_id:int,
 *   owner_role:string,
 *   joiner_role:string,
 *   driver_user_id:int,
 *   passenger_user_id:int
 * }
 */
function map_joiner_roles(array $ride, int $joinerUserId): array
{
    $ownerUserId = (int)($ride['user_id'] ?? 0);
    $rideType = (string)($ride['type'] ?? '');

    if ($rideType === 'offer') {
        return [
            'owner_user_id' => $ownerUserId,
            'joiner_user_id' => $joinerUserId,
            'owner_role' => 'driver',
            'joiner_role' => 'passenger',
            'driver_user_id' => $ownerUserId,
            'passenger_user_id' => $joinerUserId,
        ];
    }

    return [
        'owner_user_id' => $ownerUserId,
        'joiner_user_id' => $joinerUserId,
        'owner_role' => 'passenger',
        'joiner_role' => 'driver',
        'driver_user_id' => $joinerUserId,
        'passenger_user_id' => $ownerUserId,
    ];
}

/**
 * Build a short readable route summary for notifications and logs.
 *
 * @param array<string,mixed> $ride
 */
function summarize_route(array $ride): string
{
    $from = trim((string)($ride['from_text'] ?? ''));
    $to = trim((string)($ride['to_text'] ?? ''));
    return $from && $to ? "$from → $to" : ($from ?: $to ?: 'your ride');
}

/**
 * Quote canonical statuses for a SQL IN (...) clause.
 *
 * @param list<string> $statuses
 * @return list<string>
 */
function quote_statuses(PDO $pdo, array $statuses): array
{
    return array_map(static fn(string $status): string => $pdo->quote(to_db($status)), $statuses);
}

/**
 * Load the most recent match in one of the requested states.
 *
 * @param list<string> $statuses canonical status names
 * @return array<string,mixed>|null
 */
function find_latest_match(PDO $pdo, int $rideId, array $statuses, bool $forUpdate = false): ?array
{
    $quotedStatuses = quote_statuses($pdo, $statuses);
    $sql = "
        SELECT *
        FROM ride_matches
        WHERE ride_id = :ride_id
          AND status IN (" . implode(',', $quotedStatuses) . ")
        ORDER BY COALESCE(confirmed_at, updated_at, created_at) DESC
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':ride_id' => $rideId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$match) {
        return null;
    }

    $match['status'] = from_db($match['status'] ?? null);
    return $match;
}

/**
 * Broadcast a ride lifecycle update to the ride owner and participants.
 *
 * @param list<int> $userIds
 */
function broadcast_ride_update(int $rideId, array $userIds = [], ?string $reason = null): void
{
    $rooms = [];
    foreach (array_values(array_unique(array_map('intval', $userIds))) as $userId) {
        if ($userId > 0) {
            $rooms[] = 'user:' . $userId;
        }
    }

    \App\WS\broadcast('ride:updated', [
        'ride_id' => $rideId,
        'reason' => $reason,
        'updated_at' => gmdate('c'),
    ], $rooms);
}
