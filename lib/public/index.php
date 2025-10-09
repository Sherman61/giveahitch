<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1'); 
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/security.php';

use function App\Security\csrf_token;
use function App\Security\sanitize_html;

$csrf = csrf_token(); 
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NY Rides — Offer & Request</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net https://cdn.socket.io; connect-src 'self' <?php echo htmlspecialchars($_ENV['WS_URL'] ?? '', ENT_QUOTES); ?> http://127.0.0.1:4001 https://cdn.socket.io; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net https://cdn.socket.io 'unsafe-inline';">
<style>
  body { background:#f8fafc; }
  .ride-card { border-left:5px solid #0d6efd; }
  .ride-card.request { border-left-color:#20c997; }
  .whatsapp-link { text-decoration:none; }
</style>
</head>
<body class="pb-5">
<nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
  <div class="container">
    <a class="navbar-brand" href="#">NY Rides</a>
  </div>
</nav>

<div class="container">
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">Post a Ride</h5>
          <form id="rideForm" class="needs-validation" novalidate>
            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
            <div class="mb-3">
              <label class="form-label">Type</label>
              <select class="form-select" name="type" required>
                <option value="offer">Offer (I have seats)</option>
                <option value="request">Request (I need a ride)</option>
              </select>
              <div class="invalid-feedback">Please select a type.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">From</label>
              <input type="text" class="form-control" name="from_text" placeholder="e.g., Borough Park, Brooklyn, NY" required>
              <div class="invalid-feedback">Please enter a valid origin.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">To</label>
              <input type="text" class="form-control" name="to_text" placeholder="e.g., Monsey, NY" required>
              <div class="invalid-feedback">Please enter a valid destination.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Date & Time (optional)</label>
              <input type="datetime-local" class="form-control" name="ride_datetime">
            </div>
            <div class="mb-3">
              <label class="form-label">Seats (0 = package only)</label>
              <input type="number" class="form-control" name="seats" min="0" max="12" value="1" required>
              <div class="invalid-feedback">Seats must be 0–12.</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Note (optional)</label>
              <textarea class="form-control" name="note" rows="3" maxlength="1000" placeholder="Any details…"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Phone (at least one contact)</label>
              <input type="tel" class="form-control" name="phone" placeholder="+1 718 555 1234">
            </div>
            <div class="mb-3">
              <label class="form-label">WhatsApp (optional)</label>
              <input type="tel" class="form-control" name="whatsapp" placeholder="+1 347 555 7890">
            </div>
            <button class="btn btn-primary w-100" type="submit">Post</button>
            <div id="formAlert" class="mt-3"></div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="d-flex align-items-center gap-2 mb-2">
        <select id="filterType" class="form-select" style="max-width:220px">
          <option value="">All</option>
          <option value="offer">Offers</option>
          <option value="request">Requests</option>
        </select>
        <input id="searchBox" class="form-control" placeholder="Search From/To/Note (try 'Brooklyn' or 'Monsey')">
        <button id="refreshBtn" class="btn btn-outline-secondary">Refresh</button>
        <span class="ms-auto small text-muted" id="liveBadge">Live updates: <strong>connected</strong></span>
      </div>

      <div id="ridesList" class="vstack gap-3"></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>
<script>
const WS_URL = <?php echo json_encode($_ENV['WS_URL'] ?? ''); ?>;
</script>
<script src="/glitchahitch/assets/js/app.js"></script>
</body>  
</html>
