<?php declare(strict_types=1);

require_once __DIR__ . '/_render.php';

$navItems = [
    ['title' => 'Schema', 'href' => '/docs/schema.php', 'active' => true],
    ['title' => 'Rides', 'href' => '/docs/rides.php'],
    ['title' => 'Score', 'href' => '/docs/score.php'],
    ['title' => 'Trust', 'href' => '/docs/trust.php'],
    ['title' => 'Security', 'href' => '/docs/security.php'],
];

render_markdown_doc_page(
    pageTitle: 'Schema Docs',
    docTitle: 'Developer Schema Guide',
    docLead: 'The readable names, role mapping, and developer-friendly SQL views behind rides, matches, score, reports, and security.',
    markdownPath: __DIR__ . '/schema.md',
    navItems: $navItems,
    heroBadge: 'Glitch a Hitch Docs'
);
