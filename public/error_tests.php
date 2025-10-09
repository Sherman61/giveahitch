<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/error_log.php';
require_once __DIR__ . '/../config/db.php';

start_secure_session();
\App\Err\init_error_logging();

// Only admins can view (adjust to your is_admin flag)
$me = \App\Auth\require_login();
$is_admin = !empty($me['is_admin']);
if (!$is_admin) { http_response_code(403); echo "Admins only."; exit; }

$pdo = db();

$pageSel = isset($_GET['page']) ? trim((string)$_GET['page']) : '';
$limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Error Dashboard — Glitch a Hitch</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f6f8fb}
.card:hover{box-shadow:0 .5rem 1rem rgba(0,0,0,.08)}
pre{white-space:pre-wrap; word-break:break-word;}
</style>
</head>
<body class="pb-5">
<nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/glitchahitch/rides.php">Glitch a Hitch</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/glitchahitch/error_tests.php">Errors</a>
      <a class="btn btn-outline-secondary btn-sm" href="/glitchahitch/my_rides.php">My rides</a>
      <a class="btn btn-outline-secondary btn-sm" href="/glitchahitch/create.php">Create</a>
      <a class="btn btn-outline-secondary btn-sm" href="/glitchahitch/admin.php">Admin</a>
      <a class="btn btn-outline-danger btn-sm" href="/glitchahitch/logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="container">
<?php if ($pageSel === ''): ?>
  <div class="d-flex align-items-center mb-3">
    <h1 class="h4 mb-0">Errors by Page</h1>
    <a class="btn btn-sm btn-outline-danger ms-auto" href="?clear=1" onclick="return confirm('Clear ALL app errors?')">Clear All</a>
  </div>
  <?php
  if (isset($_GET['clear'])) {
      $pdo->exec("TRUNCATE TABLE app_errors");
      echo '<div class="alert alert-success">Cleared.</div>';
  }
  $q = $pdo->query("SELECT page, COUNT(*) AS cnt, MAX(created_at) AS last_time
                    FROM app_errors GROUP BY page ORDER BY cnt DESC, last_time DESC");
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) echo '<div class="alert alert-info">No errors logged yet.</div>';
  ?>
  <div class="row g-3">
    <?php foreach ($rows as $r): ?>
      <div class="col-md-6 col-lg-4">
        <a class="text-decoration-none" href="?page=<?= urlencode($r['page']) ?>">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title mb-2"><?= h($r['page']) ?></h5>
              <p class="mb-1"><strong>Total:</strong> <?= (int)$r['cnt'] ?></p>
              <p class="text-muted small mb-0"><strong>Last:</strong> <?= h($r['last_time']) ?></p>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <?php
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_errors WHERE page = :p");
  $stmt->execute([':p'=>$pageSel]);
  $total = (int)$stmt->fetchColumn();

  $stmt2 = $pdo->prepare("SELECT id,endpoint,message,errno,file,line,severity,context_snip,user_id,created_at
                          FROM app_errors WHERE page=:p
                          ORDER BY created_at DESC LIMIT :lim OFFSET :off");
  $stmt2->bindValue(':p', $pageSel, PDO::PARAM_STR);
  $stmt2->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt2->bindValue(':off', $offset, PDO::PARAM_INT);
  $stmt2->execute();
  $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <div class="d-flex align-items-center mb-3">
    <h1 class="h5 mb-0">Errors — <?= h($pageSel) ?></h1>
    <a class="btn btn-sm btn-outline-secondary ms-2" href="/glitchahitch/error_tests.php">Back</a>
    <div class="ms-auto small text-muted">Total: <strong><?= $total ?></strong></div>
  </div>

  <?php if (!$items): ?>
    <div class="alert alert-info">No entries.</div>
  <?php else: foreach ($items as $e): ?>
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <h6 class="mb-0">#<?= (int)$e['id'] ?> — <?= h($e['severity'] ?? '') ?></h6>
          <span class="badge text-bg-light"><?= h($e['created_at']) ?></span>
        </div>
        <p class="mb-1"><strong>Endpoint:</strong> <?= h($e['endpoint']) ?></p>
        <p class="mb-1"><strong>Message:</strong> <?= h($e['message']) ?></p>
        <p class="mb-1"><strong>Location:</strong> <?= h($e['file']) ?> : <?= (int)$e['line'] ?></p>
        <p class="mb-1"><strong>Errno:</strong> <?= (int)$e['errno'] ?></p>
        <?php if (!empty($e['context_snip'])): ?>
          <p class="mb-1"><strong>Context:</strong></p>
          <pre class="mb-0"><?= h($e['context_snip']) ?></pre>
        <?php endif; ?>
        <?php if (!empty($e['user_id'])): ?>
          <p class="mt-2 mb-0"><strong>User ID:</strong> <?= (int)$e['user_id'] ?></p>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <nav>
    <ul class="pagination">
      <?php if ($offset > 0): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= urlencode($pageSel) ?>&offset=<?= max(0,$offset-$limit) ?>&limit=<?= $limit ?>">Prev</a></li>
      <?php endif; ?>
      <?php if ($offset + $limit < $total): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= urlencode($pageSel) ?>&offset=<?= $offset+$limit ?>&limit=<?= $limit ?>">Next</a></li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>
</div>
</body>
</html>
