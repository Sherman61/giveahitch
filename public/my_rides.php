<?php declare(strict_types=1);
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/ws.php';
$user = \App\Auth\require_login();
$csrf = \App\Auth\csrf_token();
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1'); 
ini_set('log_errors', '1');                 // still log to the PHP error log
error_reporting(E_ALL);
  $me   = \App\Auth\current_user();
  $wsToken = \App\WS\generate_token((int)($user['id'] ?? 0));
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
<?php include __DIR__ . '/header.php'; ?>

<div class="container">
  <div class="mb-4">
    <h1 class="h3 mb-1">My Rides</h1>
    <p class="text-secondary mb-0">Track rides you posted and trips you joined, whether you are driving or riding along.</p>
  </div>
  <ul class="nav nav-pills mb-3" id="switcher" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" data-view="posted" type="button">Rides you posted</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-view="driver" type="button">Trips you joined</button>
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
  window.WS_AUTH  = <?= json_encode($wsToken ? ['userId' => (int)$user['id'], 'token' => $wsToken] : null, JSON_UNESCAPED_SLASHES) ?>;
</script>

<script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>

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
  let refreshTimer = null;

  const refreshCurrent = () => {
    views[current].mount();
  };

  const scheduleRefresh = () => {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(refreshCurrent, 120);
  };

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

  const auth = window.WS_AUTH || null;
  if (typeof io === 'function' && auth?.token) {
    try {
      const socket = io({ path: '/socket.io', transports: ['websocket', 'polling'] });
      socket.on('connect', () => {
        socket.emit('auth', { token: auth.token });
      });
      socket.on('ride:updated', () => {
        scheduleRefresh();
      });
      socket.on('notification:new', (payload) => {
        if (payload?.notification?.ride_id) {
          scheduleRefresh();
        }
      });
    } catch (error) {
      console.warn('my_rides:socket_init_failed', error);
    }
  }
</script>
</body>
</html>
