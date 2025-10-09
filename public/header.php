 <nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
    <div class="container">

      <a class="navbar-brand d-flex align-items-center gap-2" href="/rides.php">
  <i class="bi bi-car-front-fill fs-4"></i>
  <span class="fw-bold">Glitch a Hitch</span>
</a>
      <div class="ms-auto d-flex align-items-center gap-2">
  <?php if ($me): ?>
    <span class="navbar-text me-1">
      <i class="bi bi-person-circle me-1" onclick="location.href='/user.php?id=<?= (int)$me['id'] ?>'"></i><?= htmlspecialchars($me['display_name']) ?>
    </span>

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
      <div class="ms-auto"><a class="btn btn-primary" href="/create.php">Post a Ride</a></div>
    </div>
  </nav>