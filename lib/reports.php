<?php
declare(strict_types=1);

namespace App\Reports;

const RIDE_REPORT_REASONS = [
    'scam' => [
        'label' => 'Scam or fraud',
        'description' => 'Money scam, fake payment request, fake identity, or another attempt to trick people.',
    ],
    'fake_listing' => [
        'label' => 'Fake ride listing',
        'description' => 'The ride does not appear to be real or was posted with misleading details.',
    ],
    'unsafe_behavior' => [
        'label' => 'Unsafe behavior',
        'description' => 'Reckless driving, threatening behavior, or anything that feels unsafe.',
    ],
    'harassment' => [
        'label' => 'Harassment or abuse',
        'description' => 'Harassment, intimidation, hate speech, or repeated unwanted contact.',
    ],
    'no_show' => [
        'label' => 'No-show',
        'description' => 'The rider or driver never showed up and did not communicate clearly.',
    ],
    'spam' => [
        'label' => 'Spam',
        'description' => 'Repeated junk posts, advertising, or unrelated content.',
    ],
    'misleading_details' => [
        'label' => 'Misleading details',
        'description' => 'The route, timing, seats, or other details were intentionally misleading.',
    ],
    'payment_issue' => [
        'label' => 'Payment issue',
        'description' => 'Unexpected charges, payment pressure, or a fare dispute.',
    ],
    'other' => [
        'label' => 'Other',
        'description' => 'Something else went wrong and you want the team to review it.',
    ],
];

/**
 * @return array<string,array{label:string,description:string}>
 */
function ride_report_reasons(): array
{
    return RIDE_REPORT_REASONS;
}

function is_valid_ride_report_reason(string $reasonKey): bool
{
    return isset(RIDE_REPORT_REASONS[$reasonKey]);
}
