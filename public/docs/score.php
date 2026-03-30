<?php declare(strict_types=1);

require_once __DIR__ . '/_render.php';
require_once __DIR__ . '/../../lib/scoring.php';

use function App\Scoring\{rules, zero_point_events};

$navItems = [
    ['title' => 'Schema', 'href' => '/docs/schema.php'],
    ['title' => 'Rides', 'href' => '/docs/rides.php'],
    ['title' => 'Score', 'href' => '/docs/score.php', 'active' => true],
    ['title' => 'Trust', 'href' => '/docs/trust.php'],
    ['title' => 'Security', 'href' => '/docs/security.php'],
];

$cards = '';
foreach (rules() as $rule) {
    $cards .= sprintf(
        '<div class="col-md-6">
            <div class="h-100 rounded-4 p-4" style="background:#f7faff;border:1px solid #e2ecff;">
              <div class="d-flex align-items-start justify-content-between gap-3">
                <div>
                  <div class="text-uppercase small fw-semibold text-primary mb-2">%s</div>
                  <div class="fw-semibold fs-5 mb-2">%s</div>
                  <div class="text-secondary small">%s</div>
                </div>
                <div class="text-end">
                  <div class="badge rounded-pill text-bg-primary fs-6">+%d</div>
                </div>
              </div>
            </div>
          </div>',
        htmlspecialchars($rule['awarded_to'], ENT_QUOTES),
        htmlspecialchars($rule['label'], ENT_QUOTES),
        htmlspecialchars($rule['description'], ENT_QUOTES),
        (int)$rule['points']
    );
}

$zeroList = '';
foreach (zero_point_events() as $event) {
    $zeroList .= sprintf(
        '<li class="list-group-item border-0 px-0 py-2 bg-transparent">%s</li>',
        htmlspecialchars($event, ENT_QUOTES)
    );
}

$afterContentHtml = '
  <section class="mt-5">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h2 class="h4 mb-0">Quick Reference</h2>
      <span class="text-secondary small">Rendered from the live scoring rules in <code>lib/scoring.php</code></span>
    </div>
    <div class="row g-3">' . $cards . '</div>
  </section>
  <section class="mt-5">
    <div class="rounded-4 p-4" style="background:#fff8e7;border:1px solid #f6df9b;">
      <h2 class="h4 mb-3">No Score Awarded</h2>
      <p class="text-secondary">These actions still matter operationally, but they do not currently increase <code>score</code>.</p>
      <ul class="list-group list-group-flush">' . $zeroList . '</ul>
    </div>
  </section>';

render_markdown_doc_page(
    pageTitle: 'Score Docs',
    docTitle: 'Score And Reputation Rules',
    docLead: 'A clear reference for which ride events award points, which ones do not, and where those rules live in code.',
    markdownPath: __DIR__ . '/score.md',
    navItems: $navItems,
    heroBadge: 'Glitch a Hitch Docs',
    afterContentHtml: $afterContentHtml
);
