<?php declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

start_secure_session();
\App\Auth\require_login();
$me   = \App\Auth\current_user();
$csrf = \App\Auth\csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Rate rides — Glitch a Hitch</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body {
      background: linear-gradient(180deg, #f8faff 0%, #eef2ff 100%);
    }
    .rating-card {
      border: 0;
      border-radius: 1rem;
    }
    .rating-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.25rem 0.75rem;
      border-radius: 999px;
      background: rgba(13, 110, 253, 0.12);
      color: #0a58ca;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }
    .stars-group .btn {
      min-width: 3rem;
    }
  </style>
  <script>
    window.ME_USER_ID = <?= (int) $me['id'] ?>;
    window.CSRF_TOKEN = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>;
    window.API_BASE   = '/api';
  </script>
</head>
<body class="pb-5">
  <?php include __DIR__ . '/header.php'; ?>

  <div class="container py-4 py-lg-5">
    <header class="mb-4">
      <h1 class="fw-bold display-6 text-primary mb-2">Share your ride experience</h1>
      <p class="text-secondary mb-0">Rate completed rides to help fellow drivers and passengers know who is reliable and responsive. Your feedback keeps the community strong.</p>
    </header>

    <div id="rateStatus" class="alert alert-info d-none" role="alert"></div>
    <div id="rateList" class="vstack gap-3"></div>
  </div>

  <script src="/assets/js/rate_rides.js" type="module"></script>
</body>
</html>
