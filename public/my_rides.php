<?php declare(strict_types=1);
require_once __DIR__.'/../lib/auth.php';
$user = \App\Auth\require_login();
$csrf = \App\Auth\csrf_token();
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1'); 
ini_set('log_errors', '1');                 // still log to the PHP error log
error_reporting(E_ALL);
  $me   = \App\Auth\current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>My Rides — Glitch a Hitch</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .badge-status{text-transform:capitalize}
    .view{display:none}
    .view.active{display:block}
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/rides.php">
      <i class="bi bi-steering-wheel"></i> Glitch a Hitch
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="navbar-text small text-muted d-none d-md-inline">
        <?= htmlspecialchars($user['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
       <a class="btn btn-outline-secondary btn-sm" href="user.php?id=<?= (int)$me['id'] ?>"
       title="View profile" aria-label="View profile">
      <i class="bi bi-person-circle me-1"></i>
    </a>
      </span>
       
      <a class="btn btn-outline-secondary btn-sm" href="/rides.php"><i class="bi bi-map me-1"></i>All rides</a>
      <a class="btn btn-outline-danger btn-sm" href="/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</nav>

<div class="container">
  <ul class="nav nav-pills mb-3" id="switcher" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" data-view="posted" type="button">I posted (offers & looking)</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-view="driver" type="button">As Driver (accepted & pending)</button>
    </li>
  </ul>

  <!-- mount points for the two components -->
  <section id="view-posted" class="view active"></section>
  <section id="view-driver" class="view"></section>
</div>
<script>
  window.API_BASE = '/api';
  window.CSRF     = <?= json_encode($csrf) ?>;
  window.MY_ID    = <?= (int)$user['id'] ?>;
  window.ME_NAME  = <?= json_encode($user['display_name'] ?? 'You') ?>;  // <-- add this
</script>



<!-- Load both components and toggle which one is shown -->
<script type="module">
  import { mountPosted, unmountPosted } from '/assets/js/comp/posted.js';
  import { mountDriver, unmountDriver } from '/assets/js/comp/driver.js';

  const btns = document.querySelectorAll('#switcher [data-view]');
  const views = {
    posted: {
      el: document.getElementById('view-posted'),
      mount: () => mountPosted(document.getElementById('view-posted')),
      unmount: () => unmountPosted?.()
    },
    driver: {
      el: document.getElementById('view-driver'),
      mount: () => mountDriver(document.getElementById('view-driver')),
      unmount: () => unmountDriver?.()
    }
  };

  let current = 'posted';
  // initial mount
  views[current].mount();

  function show(which){
    if (which === current) return;
    // unmount current (optional cleanup)
    views[current].unmount && views[current].unmount();
    views[current].el.classList.remove('active');

    // mount new
    current = which;
    views[current].mount();
    views[current].el.classList.add('active');

    // button active state
    btns.forEach(b => b.classList.toggle('active', b.dataset.view===which));

    // remember choice in hash
    history.replaceState(null, '', `#${which}`);
  }

  // click handling
  btns.forEach(b => b.addEventListener('click', () => show(b.dataset.view)));

  // deep link via hash (#driver or #posted)
  const wanted = (location.hash || '').replace('#','');
  if (wanted && views[wanted]) show(wanted);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
