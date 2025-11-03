<?php declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

start_secure_session();
$me = \App\Auth\current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>About Glitch a Hitch</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body {
      background: linear-gradient(180deg, #f8faff 0%, #eef2ff 100%);
    }
    .section-card {
      border: 0;
      border-radius: 1rem;
      box-shadow: 0 18px 45px rgba(15, 64, 128, 0.1);
    }
    .icon-circle {
      width: 3rem;
      height: 3rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      background: rgba(13, 110, 253, 0.15);
      color: #0a58ca;
      font-size: 1.5rem;
    }
  </style>
</head>
<body class="pb-5">
  <?php include __DIR__ . '/header.php'; ?>

  <div class="container py-4 py-lg-5">
    <section class="row align-items-center g-4 mb-4">
      <div class="col-lg-6">
        <h1 class="display-5 fw-bold text-primary mb-3">Your community powered ride network.</h1>
        <p class="lead text-secondary">Glitch a Hitch connects neighbors, classmates, and coworkers who are headed in the same direction. Post a request or offer a seat and we will help you coordinate the rest.</p>
        <div class="d-flex flex-wrap gap-3 mt-4">
          <a class="btn btn-primary" href="/rides.php"><i class="bi bi-search me-2"></i>Browse rides</a>
          <a class="btn btn-outline-primary" href="/create.php"><i class="bi bi-plus-circle me-2"></i>Post a ride</a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="section-card card p-4 h-100">
          <h2 class="h4 fw-semibold mb-3">How it works</h2>
          <ol class="list-group list-group-numbered list-group-flush">
            <li class="list-group-item bg-transparent px-0">Create a ride request or offer with your timing and pickup details.</li>
            <li class="list-group-item bg-transparent px-0">Chat securely with fellow riders once a match is confirmed.</li>
            <li class="list-group-item bg-transparent px-0">Confirm your ride, meet up, and share feedback when the trip is done.</li>
          </ol>
        </div>
      </div>
    </section>

    <section class="row g-4 mb-5">
      <div class="col-md-4">
        <div class="section-card card h-100 p-4">
          <div class="icon-circle mb-3"><i class="bi bi-people"></i></div>
          <h3 class="h5 fw-semibold">Built for trust</h3>
          <p class="text-secondary">Profile verification, ride confirmations, and community ratings help keep every trip safe and reliable.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="section-card card h-100 p-4">
          <div class="icon-circle mb-3"><i class="bi bi-lightning-charge"></i></div>
          <h3 class="h5 fw-semibold">Fast coordination</h3>
          <p class="text-secondary">Realtime updates let you see new rides as they go live. Accept, confirm, and get moving without the noisy group chats.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="section-card card h-100 p-4">
          <div class="icon-circle mb-3"><i class="bi bi-heart"></i></div>
          <h3 class="h5 fw-semibold">Community impact</h3>
          <p class="text-secondary">Sharing rides saves money, reduces traffic, and builds connections with people travelling your way.</p>
        </div>
      </div>
    </section>

    <section class="section-card card p-4 p-lg-5">
      <div class="row align-items-center g-4">
        <div class="col-lg-7">
          <h2 class="h4 fw-semibold text-primary mb-3">Have ideas or feedback?</h2>
          <p class="text-secondary mb-0">We are continuously improving Glitch a Hitch. Drop us a note from your profile page or rate your recent rides so other riders know what to expect.</p>
        </div>
        <div class="col-lg-5 text-lg-end">
          <a class="btn btn-success" href="/rate_rides.php"><i class="bi bi-star-half me-2"></i>Rate recent rides</a>
        </div>
      </div>
    </section>
  </div>

  <!-- include in header in future updates -->
   <script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>
  <script type="module" src="/assets/js/notification-bell.js"></script>
  <script src="/assets/js/rides.js" type="module"></script>
</body>
</html>
