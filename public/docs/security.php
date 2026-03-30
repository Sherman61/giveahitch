<?php declare(strict_types=1);

require_once __DIR__ . '/_render.php';

$navItems = [
    ['title' => 'Schema', 'href' => '/docs/schema.php'],
    ['title' => 'Rides', 'href' => '/docs/rides.php'],
    ['title' => 'Score', 'href' => '/docs/score.php'],
    ['title' => 'Trust', 'href' => '/docs/trust.php'],
    ['title' => 'Security', 'href' => '/docs/security.php', 'active' => true],
];

render_markdown_doc_page(
    pageTitle: 'Security Docs',
    docTitle: 'Security Implementation Guide',
    docLead: 'A developer-facing overview of rate limits, CSRF protection, audit logging, moderation, admin controls, and security monitoring in the app.',
    markdownPath: __DIR__ . '/security.md',
    navItems: $navItems,
    heroBadge: 'Glitch a Hitch Docs'
);
