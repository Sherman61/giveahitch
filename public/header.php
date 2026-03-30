
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
  $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
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

  $isActivePath = static function (array $paths) use ($currentPath): bool {
      return in_array($currentPath, $paths, true);
  };

  $primaryNav = [
      [
          'label' => 'Browse',
          'href' => '/rides.php',
          'icon' => 'bi-compass-fill',
          'active' => $isActivePath(['/rides.php', '/']),
      ],
      [
          'label' => 'About',
          'href' => '/about.php',
          'icon' => 'bi-info-circle-fill',
          'active' => $isActivePath(['/about.php']),
      ],
  ];

  if ($currentUser) {
      $primaryNav[] = [
          'label' => 'My Rides',
          'href' => '/my_rides.php',
          'icon' => 'bi-bag-check-fill',
          'active' => $isActivePath(['/my_rides.php', '/manage_ride.php', '/edit_ride.php']),
      ];
      $primaryNav[] = [
          'label' => 'Ratings',
          'href' => '/rate_rides.php',
          'icon' => 'bi-star-fill',
          'active' => $isActivePath(['/rate_rides.php']),
      ];
  }
?>

<meta name="vapid-public-key" content="<?= htmlspecialchars($config['vapid']['public'], ENT_QUOTES) ?>">
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">

<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" data-bootstrap-bundle></script>

<!-- Client push script (we’ll add this file next) -->
<script defer type="module" src="/assets/js/notification-bell.js"></script>

  <style>
    .site-header {
      position: relative;
      z-index: 1200;
      isolation: isolate;
      backdrop-filter: blur(18px);
      background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(246,249,255,0.9));
      border-bottom: 1px solid rgba(13, 110, 253, 0.08);
    }
    .site-header .container,
    .site-header .navbar-collapse,
    .site-header .dropdown-menu {
      position: relative;
      z-index: 1201;
    }
    .site-nav-link {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.55rem 0.9rem;
      border-radius: 999px;
      color: #495057;
      font-weight: 600;
      text-decoration: none;
      transition: background-color 0.18s ease, color 0.18s ease;
    }
    .site-nav-link:hover,
    .site-nav-link:focus-visible {
      background-color: rgba(13, 110, 253, 0.08);
      color: #0d6efd;
    }
    .site-nav-link.active {
      background-color: rgba(13, 110, 253, 0.12);
      color: #0d6efd;
    }
    .site-brand-mark {
      width: 2.5rem;
      height: 2.5rem;
      border-radius: 0.85rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #0d6efd, #4dabf7);
      color: #fff;
      box-shadow: 0 10px 24px rgba(13, 110, 253, 0.22);
    }
    .site-utility {
      width: 2.75rem;
      height: 2.75rem;
      border-radius: 999px;
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .site-account-toggle {
      border-radius: 999px;
      padding: 0.35rem 0.5rem 0.35rem 0.35rem;
    }
    .site-account-toggle::after {
      margin-left: 0.6rem;
    }
    @media (max-width: 991.98px) {
      .site-header .navbar-collapse {
        margin-top: 0.9rem;
        padding-top: 0.9rem;
        border-top: 1px solid rgba(13, 110, 253, 0.08);
        background: rgba(255, 255, 255, 0.98);
        border-radius: 1rem;
      }
      .site-nav-link {
        width: 100%;
        justify-content: flex-start;
      }
    }
  </style>

  <nav class="navbar navbar-expand-lg site-header sticky-top mb-3">
    <div class="container align-items-center">
      <a class="navbar-brand d-flex align-items-center gap-3 me-3" href="/rides.php">
        <span class="site-brand-mark">
          <i class="bi bi-car-front-fill fs-5"></i>
        </span>
        <span class="d-flex flex-column lh-sm">
          <span class="fw-bold text-dark">Glitch a Hitch</span>
          <span class="small text-secondary">Cleaner ride matching</span>
        </span>
      </a>

      <button class="navbar-toggler border-0 shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#siteHeaderNav" aria-controls="siteHeaderNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="siteHeaderNav">
        <div class="navbar-nav gap-2 gap-lg-1 align-items-lg-center">
          <?php foreach ($primaryNav as $item): ?>
            <a
              class="site-nav-link<?= $item['active'] ? ' active' : '' ?>"
              href="<?= htmlspecialchars($item['href'], ENT_QUOTES) ?>"
              aria-current="<?= $item['active'] ? 'page' : 'false' ?>"
            >
              <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES) ?>"></i>
              <span><?= htmlspecialchars($item['label'], ENT_QUOTES) ?></span>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="ms-lg-auto mt-3 mt-lg-0 d-flex flex-column flex-lg-row align-items-lg-center gap-2">
          <a class="btn btn-primary rounded-pill px-3" href="/create.php">
            <i class="bi bi-plus-circle-fill me-2"></i>Post a Ride
          </a>

          <?php if ($currentUser): ?>
            <div class="d-flex align-items-center gap-2">
              <a class="btn btn-light border shadow-sm site-utility" href="/messages.php" title="Messages" aria-label="Messages">
                <i class="bi bi-chat-dots-fill text-primary"></i>
              </a>
              <a class="btn btn-light border shadow-sm site-utility" href="/notifications.php" title="Notifications" aria-label="Notifications">
                <i class="bi bi-bell-fill text-primary"></i>
                <span id="notificationsBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none">0<span class="visually-hidden"> unread notifications</span></span>
              </a>
              <div class="dropdown">
                <button class="btn btn-light border shadow-sm dropdown-toggle d-inline-flex align-items-center gap-2 site-account-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <span class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fw-semibold" style="width: 2.15rem; height: 2.15rem;">
                    <?= htmlspecialchars($nameInitial, ENT_QUOTES) ?>
                  </span>
                  <span class="d-none d-md-flex flex-column align-items-start lh-sm text-start">
                    <span class="small text-secondary">Account</span>
                    <span class="fw-semibold text-dark"><?= htmlspecialchars($displayName, ENT_QUOTES) ?></span>
                  </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                  <li><a class="dropdown-item" href="<?= htmlspecialchars($profileUrl, ENT_QUOTES) ?>"><i class="bi bi-person me-2 text-primary"></i>View profile</a></li>
                  <li><a class="dropdown-item" href="/profile.php"><i class="bi bi-sliders2-vertical me-2 text-primary"></i>Profile settings</a></li>
                  <li><a class="dropdown-item" href="/my_rides.php"><i class="bi bi-bag-check me-2 text-primary"></i>My rides</a></li>
                  <li><a class="dropdown-item" href="/score_history.php"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Score history</a></li>
                  <li><a class="dropdown-item" href="/rate_rides.php"><i class="bi bi-star me-2 text-warning"></i>Ratings</a></li>
                  <?php if (!empty($currentUser['is_admin'])): ?>
                    <li><a class="dropdown-item" href="/admin/index.php"><i class="bi bi-shield-lock me-2 text-danger"></i>Admin tools</a></li>
                  <?php endif; ?>
                  <li><hr class="dropdown-divider"></li>
                  <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
                </ul>
              </div>
            </div>
          <?php else: ?>
            <div class="d-flex flex-column flex-sm-row gap-2">
              <a class="btn btn-light border shadow-sm rounded-pill px-3" href="/login.php" title="Log in" aria-label="Log in">
                <i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Log in
              </a>
              <a class="btn btn-outline-primary rounded-pill px-3" href="/signup.php" title="Sign up" aria-label="Sign up">
                <i class="bi bi-person-plus-fill me-2"></i>Join now
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
