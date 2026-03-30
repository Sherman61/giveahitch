<?php declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * @param array<int,array{title:string,href:string,active?:bool}> $navItems
 */
function render_markdown_doc_page(
    string $pageTitle,
    string $docTitle,
    string $docLead,
    string $markdownPath,
    array $navItems = [],
    string $heroBadge = 'Docs',
    string $afterContentHtml = ''
): void {
    if (!is_file($markdownPath) || !is_readable($markdownPath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo basename($markdownPath) . ' not found.';
        exit;
    }

    $markdown = file_get_contents($markdownPath);
    if ($markdown === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unable to read documentation file.';
        exit;
    }

    $parsedown = new Parsedown();
    $parsedown->setSafeMode(true);
    $contentHtml = $parsedown->text($markdown);

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --docs-ink: #132238;
      --docs-accent: #0d6efd;
      --docs-accent-soft: rgba(13, 110, 253, 0.12);
      --docs-surface: #ffffff;
      --docs-page: linear-gradient(180deg, #f5f9ff 0%, #eef3ff 100%);
    }
    body {
      background: var(--docs-page);
      color: var(--docs-ink);
    }
    .docs-shell {
      max-width: 1040px;
    }
    .docs-hero {
      background:
        radial-gradient(circle at top right, rgba(255,255,255,0.38), transparent 35%),
        linear-gradient(135deg, #0d6efd 0%, #1f7bf2 55%, #7cb7ff 100%);
      color: #fff;
      border-radius: 1.5rem;
      padding: 2rem;
      box-shadow: 0 24px 60px rgba(13, 62, 140, 0.18);
    }
    .docs-badge {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      border-radius: 999px;
      background: rgba(255,255,255,0.18);
      padding: .4rem .8rem;
      font-size: .82rem;
      letter-spacing: .04em;
      text-transform: uppercase;
      font-weight: 700;
    }
    .docs-nav {
      background: rgba(255,255,255,0.9);
      backdrop-filter: blur(12px);
      border-radius: 1rem;
      padding: .6rem;
      box-shadow: 0 12px 30px rgba(15, 47, 95, 0.08);
    }
    .docs-nav a {
      border-radius: .8rem;
      padding: .65rem .9rem;
      color: var(--docs-ink);
      text-decoration: none;
      font-weight: 600;
    }
    .docs-nav a.active {
      background: var(--docs-accent-soft);
      color: var(--docs-accent);
    }
    .docs-card {
      background: var(--docs-surface);
      border: 0;
      border-radius: 1.25rem;
      box-shadow: 0 18px 40px rgba(15, 47, 95, 0.08);
    }
    .docs-content {
      line-height: 1.7;
      font-size: 1.02rem;
    }
    .docs-content h1,
    .docs-content h2,
    .docs-content h3 {
      color: #10233e;
      margin-top: 2rem;
      margin-bottom: .8rem;
      font-weight: 700;
    }
    .docs-content h1 { font-size: 2rem; }
    .docs-content h2 { font-size: 1.45rem; }
    .docs-content h3 { font-size: 1.15rem; }
    .docs-content p,
    .docs-content li {
      color: #314662;
    }
    .docs-content code {
      background: #f3f7ff;
      color: #11439a;
      padding: .15rem .35rem;
      border-radius: .35rem;
    }
    .docs-content pre code {
      display: block;
      padding: 1rem;
      overflow-x: auto;
    }
    .docs-content table {
      width: 100%;
      margin: 1rem 0;
    }
    .docs-content th,
    .docs-content td {
      padding: .75rem;
      border-bottom: 1px solid #e7edf7;
      vertical-align: top;
    }
    .docs-content th {
      color: #10233e;
      background: #f7faff;
    }
  </style>
</head>
<body>
  <main class="container py-4 py-lg-5 docs-shell">
    <section class="docs-hero mb-4">
      <div class="docs-badge mb-3"><?= htmlspecialchars($heroBadge, ENT_QUOTES) ?></div>
      <h1 class="display-6 fw-bold mb-3"><?= htmlspecialchars($docTitle, ENT_QUOTES) ?></h1>
      <p class="lead mb-0"><?= htmlspecialchars($docLead, ENT_QUOTES) ?></p>
    </section>

    <?php if ($navItems): ?>
      <nav class="docs-nav d-flex flex-wrap gap-2 mb-4" aria-label="Documentation navigation">
        <?php foreach ($navItems as $item): ?>
          <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>" class="<?= !empty($item['active']) ? 'active' : '' ?>">
            <?= htmlspecialchars($item['title'], ENT_QUOTES) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <section class="docs-card p-4 p-lg-5">
      <article class="docs-content">
        <?= $contentHtml ?>
      </article>
      <?= $afterContentHtml ?>
    </section>
  </main>
</body>
</html>
<?php
}
