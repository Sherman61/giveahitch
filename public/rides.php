<?php declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

start_secure_session();
$me   = \App\Auth\current_user();
$csrf = \App\Auth\csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>View Rides — Glitch A Hitch</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script>
    window.ME_USER_ID = <?= $me ? (int) $me['id'] : 'null' ?>;
    window.CSRF_TOKEN = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>;
    window.API_BASE   = '/api';
  </script>
  <style>
    body {
      background: linear-gradient(180deg, #f8faff 0%, #eef2ff 100%);
    }

    .hero-card {
      border: 0;
      background: #0d6efd;
      color: #fff;
      border-radius: 1rem;
      overflow: hidden;
      position: relative;
    }

    .hero-card::after {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.25), transparent 55%);
      pointer-events: none;
    }

    .hero-card h1 {
      font-size: clamp(1.9rem, 2.6vw, 2.6rem);
    }

    .summary-card {
      border: 0;
      border-radius: 1rem;
    }

    .stat-value {
      font-size: 1.75rem;
      font-weight: 700;
      line-height: 1.1;
    }

    .ride-card {
      border: 0;
      border-left: 6px solid #0d6efd;
      border-radius: 1rem;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      background: #fff;
    }

    .ride-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(15, 64, 128, 0.12);
    }

    .ride-card.request {
      border-left-color: #20c997;
    }

    .ride-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
      font-size: 0.75rem;
      letter-spacing: 0.02em;
      text-transform: uppercase;
      font-weight: 600;
    }

    .ride-pill.offer {
      background: rgba(13, 110, 253, 0.12);
      color: #0a58ca;
    }

    .ride-pill.request {
      background: rgba(32, 201, 151, 0.12);
      color: #128868;
    }

    .ride-meta {
      font-size: 0.9rem;
      gap: 1rem;
    }

    .ride-meta .bi {
      color: rgba(13, 110, 253, 0.7);
    }

    .ride-actions {
      min-width: 230px;
    }

    .contact-links a {
      font-weight: 600;
    }

    .contact-links a + a::before {
      content: "·";
      margin: 0 0.5rem;
      color: #adb5bd;
    }

    .filter-chip {
      border-radius: 999px;
      border: none;
      padding: 0.25rem 0.85rem;
      background: #e7f1ff;
      color: #0d6efd;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
    }

    .filter-chip .bi {
      font-size: 0.95rem;
    }

    .empty-state {
      background: #fff;
      border-radius: 1rem;
      border: 0;
    }

    .placeholder-card {
      border-radius: 1rem;
      border: 0;
      background: rgba(255, 255, 255, 0.6);
    }

    @media (max-width: 767.98px) {
      .ride-actions {
        width: 100%;
      }
      .ride-actions .btn {
        width: 100%;
      }
    }
  </style>
</head>
<body class="pb-5">
  <?php include __DIR__ . '/header.php'; ?>

  <div class="container py-4">
    <section class="row g-4 align-items-stretch mb-4">
      <div class="col-lg-8">
        <div class="hero-card h-100 p-4 p-lg-5 shadow-sm">
          <div class="position-relative" style="z-index: 1;">
            <h1 class="fw-bold mb-3">Find a ride without the endless group chats.</h1>
            <p class="lead mb-4">Discover live ride offers and requests from the Glitch a Hitch community. Search, filter, and connect instantly.</p>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-light text-primary fw-semibold" href="/create.php"><i class="bi bi-plus-circle me-1"></i>Post a ride</a>
              <a class="btn btn-outline-light" href="/my_rides.php"><i class="bi bi-journal-text me-1"></i>My rides</a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="summary-card card shadow-sm h-100">
          <div class="card-body p-4 d-flex flex-column gap-3">
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-uppercase small text-secondary fw-semibold">Live updates</span>
              <span class="badge rounded-pill text-bg-secondary" id="liveBadge">Connecting…</span>
            </div>
            <div class="row text-center g-0">
              <div class="col-6 border-end">
                <div class="stat-value" id="countTotal">0</div>
                <div class="text-secondary small">Open rides</div>
              </div>
              <div class="col-6">
                <div class="stat-value text-primary" id="countOffers">0</div>
                <div class="text-secondary small">Offers</div>
              </div>
            </div>
            <div class="d-flex justify-content-between text-secondary small">
              <span>Requests: <span class="fw-semibold" id="countRequests">0</span></span>
              <span>Updated <span class="fw-semibold" id="lastUpdated">—</span></span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="card shadow-sm border-0 mb-4">
      <div class="card-body p-4">
        <form class="row g-3 align-items-end" id="filtersForm" novalidate>
          <div class="col-md-3">
            <label class="form-label text-uppercase small text-secondary" for="filterType">Ride type</label>
            <select id="filterType" class="form-select">
              <option value="">All rides</option>
              <option value="offer">Offers</option>
              <option value="request">Requests</option>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label text-uppercase small text-secondary" for="searchBox">Search</label>
            <div class="position-relative">
              <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-secondary"></i>
              <input id="searchBox" class="form-control ps-5" placeholder="Neighborhood, city, or keyword">
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label text-uppercase small text-secondary" for="sortOrder">Sort</label>
            <select id="sortOrder" class="form-select">
              <option value="soonest">Soonest first</option>
              <option value="latest">Latest first</option>
            </select>
          </div>
          <div class="col-md-2 text-md-end">
            <button id="refreshBtn" type="button" class="btn btn-outline-primary w-100">
              <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
              <span>Refresh</span>
            </button>
          </div>
        </form>
        <div id="activeFilters" class="d-flex flex-wrap gap-2 align-items-center mt-3 small text-secondary"></div>
      </div>
    </section>

    <div id="errorAlert" class="alert alert-danger d-none" role="alert"></div>
    <div id="resultsMeta" class="d-flex justify-content-between align-items-center text-secondary small mb-3"></div>

    <div id="loadingSkeleton" class="vstack gap-3" aria-hidden="true">
      <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="placeholder-card card shadow-sm">
          <div class="card-body p-4">
            <div class="placeholder-glow">
              <span class="placeholder col-2 mb-3"></span>
              <span class="placeholder col-8 mb-2"></span>
              <span class="placeholder col-6 mb-2"></span>
              <span class="placeholder col-4"></span>
            </div>
          </div>
        </div>
      <?php endfor; ?>
    </div>

    <div id="emptyState" class="empty-state card shadow-sm text-center p-5 d-none">
      <div class="display-6 text-primary mb-3">No rides yet</div>
      <p class="lead text-secondary mb-4">Try adjusting your filters or check back shortly. New rides appear here live as the community posts them.</p>
      <a class="btn btn-primary" href="/create.php"><i class="bi bi-plus-circle me-1"></i>Create a ride</a>
    </div>

    <div id="ridesList" class="vstack gap-3"></div>
  </div>

  <script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>
  <script src="/assets/js/rides.js" type="module"></script>
</body>
</html>
