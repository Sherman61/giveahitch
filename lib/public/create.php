<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1'); 
require_once'../lib/security.php';
use function App\Security\csrf_token;
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Create Ride — Glitch A Hitch</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; connect-src 'self' https://shiyaswebsite.com https://127.0.0.1:4001;">
<style>body{background:#f7f9fc}</style>
</head>
<body class="pb-5">
<nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
  <div class="container">
    <a class="navbar-brand" href="/glitchahitch/rides.php">Glitch A Hitch</a>
  </div>
</nav>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="mb-3">Post a Ride</h4>
          <form id="rideForm" class="needs-validation" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <!-- honeypot -->
            <input type="text" name="website" autocomplete="off" style="position:absolute;left:-10000px;top:-10000px" tabindex="-1" aria-hidden="true">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Type</label>
                <select class="form-select" name="type" required>
                  <option value="offer">Offer</option>
                  <option value="request">Request</option>
                </select>
                <div class="invalid-feedback">Select a type.</div>
              </div>
              <div class="col-md-8">
                <label class="form-label">Date & Time (optional)</label>
                <input type="datetime-local" class="form-control" name="ride_datetime">
                <div class="form-text">Leave empty if flexible.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">From</label>
                <input type="text" class="form-control" name="from_text" placeholder="Borough Park, Brooklyn, NY" required minlength="2" maxlength="255">
                <div class="invalid-feedback">Enter a valid origin.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">To</label>
                <input type="text" class="form-control" name="to_text" placeholder="Monsey, NY" required minlength="2" maxlength="255">
                <div class="invalid-feedback">Enter a valid destination.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Seats (0 = package)</label>
                <input type="number" class="form-control" name="seats" min="0" max="12" value="1" required>
                <div class="invalid-feedback">Seats 0–12.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="tel" class="form-control" name="phone" placeholder="+1 718 555 1234" pattern="^\+?[0-9\s\-\(\)]{7,32}$">
                <div class="invalid-feedback">Invalid phone.</div>
              </div>
              <div class="col-12">
                <label class="form-label">WhatsApp (optional)</label>
                <input type="tel" class="form-control" name="whatsapp" placeholder="+1 347 555 7890" pattern="^\+?[0-9\s\-\(\)]{7,32}$">
                <div class="invalid-feedback">Invalid WhatsApp.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Note (optional)</label>
                <textarea class="form-control" name="note" rows="3" maxlength="1000" placeholder="Details…"></textarea>
              </div>
            </div>
            <div id="formAlert" class="mt-3"></div>
            <div class="d-flex gap-2 mt-2">
              <button class="btn btn-primary" type="submit">Post Ride</button>
              <a class="btn btn-outline-secondary" href="rides.php">View Rides</a>
            </div>
          </form>
        </div>
      </div>
      <p class="text-muted small mt-2">Tip: provide at least one contact method (Phone or WhatsApp).</p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>const API_BASE='/glitchahitch/api';</script>
<script src="/glitchahitch/assets/js/create.js"></script>
</body>
</html>
