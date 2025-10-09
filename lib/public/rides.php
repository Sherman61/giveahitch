<?php
declare(strict_types=1);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>View Rides — Glitch A Hitch</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net https://cdn.socket.io; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net https://cdn.socket.io 'unsafe-inline'; connect-src 'self' wss://shiyaswebsite.com https://shiyaswebsite.com http://127.0.0.1:4001;">
<style>body{background:#f7f9fc}.ride-card{border-left:5px solid #0d6efd}.ride-card.request{border-left-color:#20c997}</style>
</head>
<body class="pb-5">
<nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
  <div class="container">
    <div class="ms-auto d-flex gap-2">
  <a class="btn btn-outline-secondary" href="/glitchahitch/my_rides.php">My rides</a>
  <a class="btn btn-outline-secondary" href="/glitchahitch/login.php">Login</a>
  <a class="btn btn-outline-secondary" href="/glitchahitch/signup.php">Sign up</a>
</div>

    <a class="navbar-brand" href="/glitchahitch/rides.php">GlitchaHitch</a>
    <div class="ms-auto"><a class="btn btn-primary" href="/glitchahitch/create.php">Post a Ride</a></div>
  </div>
</nav>

<div class="container">
  <div class="d-flex gap-2 mb-3">
    <select id="filterType" class="form-select" style="max-width:220px">
      <option value="">All</option>
      <option value="offer">Offers</option>
      <option value="request">Requests</option>
    </select>
    <input id="searchBox" class="form-control" placeholder="Search (e.g., Brooklyn, Monsey)">
    <button id="refreshBtn" class="btn btn-outline-secondary">Refresh</button>
    <span class="ms-auto small text-muted" id="liveBadge">Live: <strong>off</strong></span>
  </div>

  <div id="ridesList" class="vstack gap-3"></div>
</div>

<script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>
<script>
const API_BASE = '/glitchahitch/api';
const WS_URL = (location.origin.replace(/^http/,'ws')) + '/socket.io';
</script>
<script src="/glitchahitch/assets/js/rides.js"></script>
</body>
</html>
