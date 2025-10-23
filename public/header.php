
<?php


// Ensure session (for CSRF)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Load config (reads .env)
$config = require __DIR__ . '/../config/config.php';


  $currentUser = isset($me) && is_array($me) ? $me : null;
  $displayName = isset($currentUser['display_name']) && $currentUser['display_name'] !== ''
    ? (string)$currentUser['display_name']
    : 'Member';
  $profileUrl = '/user.php?id=' . (int)($currentUser['id'] ?? 0);
  $nameInitial = '';

  if ($displayName !== '') {
      if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
          $nameInitial = mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8');
      } else {
          $nameInitial = strtoupper(substr($displayName, 0, 1));
      }
  }
  if ($nameInitial === '') {
      $nameInitial = 'U';
  }
?>

<meta name="vapid-public-key" content="<?= htmlspecialchars($config['vapid']['public'], ENT_QUOTES) ?>">
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

<!-- Client push script (we’ll add this file next) -->
<script defer type="module" src="/assets/js/notification-bell.js"></script>

  <nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
    <div class="container align-items-center">
      <a class="navbar-brand d-flex align-items-center gap-2" href="/rides.php">
        <i class="bi bi-car-front-fill fs-4 text-primary"></i>
        <span class="fw-bold">Glitch a Hitch</span>
      </a>
      <div class="ms-4 d-none d-md-flex align-items-center gap-3">
        <a class="nav-link px-0" href="/about.php">About</a>
        <?php if ($currentUser): ?>
          <a class="nav-link px-0" href="/rate_rides.php">Rate rides</a>
        <?php endif; ?>
      </div>
      <div class="ms-auto d-flex align-items-center gap-3 flex-wrap justify-content-end">
        <div class="d-flex d-md-none align-items-center gap-2">
          <a class="btn btn-link btn-sm px-1" href="/about.php">About</a>
          <?php if ($currentUser): ?>
            <a class="btn btn-link btn-sm px-1" href="/rate_rides.php">Rate rides</a>
          <?php endif; ?>
        </div>
        <?php if ($currentUser): ?>
          <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <div class="d-flex align-items-center gap-2 px-2 py-1 rounded-pill bg-light border shadow-sm">
              <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fw-semibold" style="width: 2.25rem; height: 2.25rem;">
                <?= htmlspecialchars($nameInitial, ENT_QUOTES) ?>
              </span>
              <div class="d-flex flex-column lh-sm">
                <span class="small text-secondary">Signed in as</span>
                <a class="fw-semibold text-decoration-none" href="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>"><?= htmlspecialchars($displayName, ENT_QUOTES) ?></a>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <a class="btn btn-light border-0 shadow-sm rounded-circle p-0 position-relative d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;" href="/notifications.php" title="Notifications" aria-label="Notifications">
                <i class="bi bi-bell-fill text-primary fs-5"></i>
                <span id="notificationsBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none">0<span class="visually-hidden"> unread notifications</span></span>
              </a>
              <a class="btn btn-light border-0 shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;" href="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>" title="View profile" aria-label="View profile">
                <i class="bi bi-person-fill text-primary fs-5"></i>
              </a>
              <a class="btn btn-light border-0 shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;" href="/profile.php" title="Profile settings" aria-label="Profile settings">
                <i class="bi bi-sliders2-vertical text-primary fs-5"></i>
              </a>
              <a class="btn btn-light border-0 shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;" href="/my_rides.php" title="My rides" aria-label="My rides">
                <i class="bi bi-bag-check-fill text-primary fs-5"></i>
              </a>
              <a class="btn btn-light border-0 shadow-sm rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;" href="/logout.php" title="Logout" aria-label="Logout">
                <i class="bi bi-box-arrow-right text-danger fs-5"></i>
              </a>
            </div>
          </div>
        <?php else: ?>
          <div class="d-flex align-items-center gap-2">
            <a class="btn btn-light border-0 shadow-sm rounded-pill d-flex align-items-center gap-2 px-3 py-2" href="/login.php" title="Log in" aria-label="Log in">
              <i class="bi bi-box-arrow-in-right text-primary fs-5"></i>
              <span class="fw-semibold text-primary">Log in</span>
            </a>
            <a class="btn btn-primary rounded-pill" href="/signup.php" title="Sign up" aria-label="Sign up">
              <i class="bi bi-person-plus-fill me-2"></i>
              <span class="fw-semibold">Join now</span>
            </a>
          </div>
        <?php endif; ?>
        <div>
          <a class="btn btn-primary" href="/create.php">Post a Ride</a>
        </div>
      </div>
    </div>
  </nav>
