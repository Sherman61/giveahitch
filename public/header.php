 <nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
    <div class="container align-items-center">

      <a class="navbar-brand d-flex align-items-center gap-2" href="/rides.php">
  <i class="bi bi-car-front-fill fs-4"></i>
  <span class="fw-bold">Glitch a Hitch</span>
</a>
      <div class="ms-4 d-none d-md-flex align-items-center gap-3">
        <a class="nav-link px-0" href="/about.php">About</a>
        
      </div>
      <div class="ms-auto d-flex align-items-center gap-3 flex-wrap justify-content-end">
        <div class="d-flex d-md-none align-items-center gap-2">
          <a class="btn btn-link btn-sm px-1" href="/about.php">About</a>
          <a class="btn btn-link btn-sm px-1" href="/rate_rides.php">Rate rides</a>
        </div>
        <div class="d-flex align-items-center gap-2">
          <?php if ($me): ?>
            <span class="navbar-text text-secondary d-none d-lg-inline">Hi, <a class="text-decoration-none" href="/user.php?id=<?= (int)$me['id'] ?>"><?= htmlspecialchars($me['display_name']) ?></a></span>

            <a class="btn btn-outline-secondary btn-sm" href="/user.php?id=<?= (int)$me['id'] ?>"
               title="View profile" aria-label="View profile">
              <i class="bi bi-person-circle"></i>
            </a>

            <a class="btn btn-outline-secondary btn-sm" href="/profile.php"
               title="Profile settings" aria-label="Profile settings">
              <i class="bi bi-gear"></i>
            </a>

            <a class="btn btn-outline-secondary btn-sm" href="/my_rides.php"
               title="My rides" aria-label="My rides">
              <i class="bi bi-car-front"></i>
            </a>

            <a class="btn btn-outline-secondary btn-sm" href="/logout.php"
               title="Logout" aria-label="Logout">
              <i class="bi bi-box-arrow-right"></i>
            </a>
          <?php else: ?>
            <a class="btn btn-outline-secondary btn-sm" href="/login.php"
               title="Log in" aria-label="Log in">
              <i class="bi bi-box-arrow-in-right"></i>
            </a>

            <a class="btn btn-outline-primary btn-sm" href="/signup.php"
               title="Sign up" aria-label="Sign up">
              <i class="bi bi-person-plus"></i>
            </a>
          <?php endif; ?>
        </div>
        <div><a class="btn btn-primary" href="/create.php">Post a Ride</a></div>
      </div>
    </div>
  </nav>