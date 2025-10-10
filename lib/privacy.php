<?php
declare(strict_types=1);

namespace App\Privacy;

const PRIVACY_ACCEPTED_ONLY = 1;
const PRIVACY_LOGGED_IN = 2;
const PRIVACY_ACTIVE_OPEN = 3;

/**
 * Normalise a stored privacy value into one of the supported constants.
 */
function normalise_level(int $value): int
{
    return in_array($value, [PRIVACY_ACCEPTED_ONLY, PRIVACY_LOGGED_IN, PRIVACY_ACTIVE_OPEN], true)
        ? $value
        : PRIVACY_ACCEPTED_ONLY;
}

/**
 * Determine if contact should remain visible based on the match status/timing.
 *
 * @param string|null $status        Match status in canonical form.
 * @param string|null $statusChanged Timestamp when the status last changed.
 * @return array{visible:bool, reason:string}
 */
function match_visibility(?string $status, ?string $statusChanged): array
{
    $status = $status ? strtolower($status) : '';

    if (in_array($status, ['accepted', 'matched', 'confirmed', 'in_progress'], true)) {
        return ['visible' => true, 'reason' => ''];
    }

    if (in_array($status, ['completed', 'cancelled'], true)) {
        if (within_last_hour($statusChanged)) {
            return ['visible' => true, 'reason' => ''];
        }
        return [
            'visible' => false,
            'reason'  => 'Contact details expire one hour after the ride ends.',
        ];
    }

    return [
        'visible' => false,
        'reason'  => 'Contact becomes visible after a ride is accepted.',
    ];
}

/**
 * Determine if a timestamp is within the last hour relative to "now".
 */
function within_last_hour(?string $timestamp): bool
{
    if (!$timestamp) {
        return false;
    }
    $time = strtotime($timestamp);
    if ($time === false) {
        return false;
    }
    return ($time >= (time() - 3600));
}

/**
 * Evaluate whether contact information for a target user should be visible to
 * the viewer, returning both the boolean flag and a user-friendly reason when
 * hidden.
 *
 * Supported $context keys:
 * - privacy: int                                Required privacy level value.
 * - viewer_is_owner: bool                       Viewer owns the ride/post.
 * - viewer_is_target: bool                      Viewer is the target user.
 * - viewer_is_admin: bool                       Viewer has admin privileges.
 * - viewer_logged_in: bool                      Viewer is authenticated.
 * - viewer_has_active_match: bool               Viewer and target share an active match.
 * - match_status: string|null                   Canonical match status value.
 * - match_changed_at: string|null               Timestamp when match status last changed.
 * - target_has_active_open_ride: bool           Target currently has an open ride.
 *
 * @param array|null $viewer
 * @param array      $context
 * @return array{visible:bool, reason:string, level:int}
 */
function evaluate(?array $viewer, array $context): array
{
    $level = normalise_level((int)($context['privacy'] ?? PRIVACY_ACCEPTED_ONLY));
    $viewerLoggedIn = (bool)($context['viewer_logged_in'] ?? ($viewer && !empty($viewer['id'])));

    if (!empty($context['viewer_is_admin'])) {
        return ['visible' => true, 'reason' => '', 'level' => $level];
    }
    if (!empty($context['viewer_is_target']) || !empty($context['viewer_is_owner'])) {
        return ['visible' => true, 'reason' => '', 'level' => $level];
    }

    if (!empty($context['viewer_has_active_match'])) {
        $match = match_visibility($context['match_status'] ?? null, $context['match_changed_at'] ?? null);
        return ['visible' => $match['visible'], 'reason' => $match['reason'], 'level' => $level];
    }

    switch ($level) {
        case PRIVACY_LOGGED_IN:
            if ($viewerLoggedIn) {
                return ['visible' => true, 'reason' => '', 'level' => $level];
            }
            return [
                'visible' => false,
                'reason'  => 'Log in to view this member’s contact details.',
                'level'   => $level,
            ];

        case PRIVACY_ACTIVE_OPEN:
            if (!empty($context['target_has_active_open_ride'])) {
                return ['visible' => true, 'reason' => '', 'level' => $level];
            }
            if ($viewerLoggedIn) {
                return ['visible' => true, 'reason' => '', 'level' => $level];
            }
            return [
                'visible' => false,
                'reason'  => 'Contact is public while they have an active ride or when you log in.',
                'level'   => $level,
            ];

        case PRIVACY_ACCEPTED_ONLY:
        default:
            return [
                'visible' => false,
                'reason'  => 'Contact becomes visible after a ride is accepted.',
                'level'   => $level,
            ];
    }
}

