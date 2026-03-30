<?php declare(strict_types=1);

require_once __DIR__ . '/_render.php';

$navItems = [
    ['title' => 'Schema', 'href' => '/docs/schema.php'],
    ['title' => 'Rides', 'href' => '/docs/rides.php'],
    ['title' => 'Score', 'href' => '/docs/score.php'],
    ['title' => 'Trust', 'href' => '/docs/trust.php', 'active' => true],
    ['title' => 'Security', 'href' => '/docs/security.php'],
];

render_markdown_doc_page(
    pageTitle: 'Trust Guide',
    docTitle: 'Trust, Score, And Reviews',
    docLead: 'A plain-English guide for riders and drivers explaining what builds trust, what changes score, and how reviews and reports are used.',
    markdownPath: __DIR__ . '/trust.md',
    navItems: $navItems,
    heroBadge: 'Glitch a Hitch Docs'
);
