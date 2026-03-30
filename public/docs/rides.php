<?php declare(strict_types=1);

require_once __DIR__ . '/_render.php';

$navItems = [
    ['title' => 'Rides', 'href' => '/docs/rides.php', 'active' => true],
    ['title' => 'Score', 'href' => '/docs/score.php'],
    ['title' => 'Trust', 'href' => '/docs/trust.php'],
];

render_markdown_doc_page(
    pageTitle: 'Rides Docs',
    docTitle: 'Ride System Guide',
    docLead: 'How ride ownership, matching, status changes, visibility, and ratings work across the app.',
    markdownPath: __DIR__ . '/rides.md',
    navItems: $navItems,
    heroBadge: 'Glitch a Hitch Docs'
);
