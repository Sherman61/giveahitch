<?php
declare(strict_types=1);

namespace App\AdminPages;

/**
 * @return list<array{
 *   key:string,
 *   title:string,
 *   path:string,
 *   icon:string,
 *   description:string,
 *   category:string
 * }>
 */
function all(): array
{
    return [
        [
            'key' => 'rides',
            'title' => 'Ride Moderation',
            'path' => '/admin/index.php#ride-moderation',
            'icon' => 'bi-car-front-fill',
            'description' => 'Review live rides, edit notes, and remove broken or unsafe listings.',
            'category' => 'Operations',
        ],
        [
            'key' => 'reports',
            'title' => 'Ride Reports',
            'path' => '/admin/reports.php',
            'icon' => 'bi-flag-fill',
            'description' => 'Review user-submitted ride reports, add admin notes, and close or dismiss cases.',
            'category' => 'Safety',
        ],
        [
            'key' => 'score',
            'title' => 'Score Adjustments',
            'path' => '/admin/score.php',
            'icon' => 'bi-graph-up-arrow',
            'description' => 'Search members, adjust score up or down, and log a visible reason.',
            'category' => 'Trust',
        ],
        [
            'key' => 'security',
            'title' => 'Security Dashboard',
            'path' => '/admin/security.php',
            'icon' => 'bi-shield-exclamation',
            'description' => 'Review failed logins, admin-sensitive actions, reports, and recent application errors.',
            'category' => 'Safety',
        ],
        [
            'key' => 'push',
            'title' => 'Push Notifications',
            'path' => '/admin/push.php',
            'icon' => 'bi-broadcast-pin',
            'description' => 'Send push notifications to users who have active subscriptions.',
            'category' => 'Operations',
        ],
        [
            'key' => 'email_test',
            'title' => 'Email Test',
            'path' => '/admin/email-test.php',
            'icon' => 'bi-envelope-check-fill',
            'description' => 'Send a test email through the current mail setup.',
            'category' => 'Diagnostics',
        ],
        [
            'key' => 'mailtrap_health',
            'title' => 'Mailtrap Health',
            'path' => '/admin/mailtrap_health.php',
            'icon' => 'bi-heart-pulse-fill',
            'description' => 'Check the Mailtrap integration and delivery configuration.',
            'category' => 'Diagnostics',
        ],
        [
            'key' => 'test_mailer',
            'title' => 'Mailer Debug',
            'path' => '/admin/test_mailer.php',
            'icon' => 'bi-bug-fill',
            'description' => 'Run the lower-level mailer debug page used during troubleshooting.',
            'category' => 'Diagnostics',
        ],
        [
            'key' => 'push_debug',
            'title' => 'Push Debug',
            'path' => '/admin/push_debug.php',
            'icon' => 'bi-wrench-adjustable-circle-fill',
            'description' => 'Open the push troubleshooting page for subscription and delivery debugging.',
            'category' => 'Diagnostics',
        ],
    ];
}

/**
 * @return list<array{
 *   key:string,
 *   title:string,
 *   path:string,
 *   icon:string,
 *   description:string,
 *   category:string
 * }>
 */
function by_category(string $category): array
{
    return array_values(array_filter(all(), static fn(array $page): bool => $page['category'] === $category));
}
