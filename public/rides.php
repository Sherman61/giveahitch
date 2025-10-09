<?php declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
start_secure_session();
$me = \App\Auth\current_user();
$csrf = \App\Auth\csrf_token();
?>
<script>

  window.ME_USER_ID = <?= $me ? (int) $me['id'] : 'null' ?>;
  window.CSRF_TOKEN = <?= json_encode($csrf) ?>;


</script>


<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>View Rides — Glitch A Hitch</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <!-- <meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net https://cdn.socket.io; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net https://cdn.socket.io 'unsafe-inline'; connect-src 'self' wss://shiyaswebsite.com https://shiyaswebsite.com http://127.0.0.1:4001;"> -->

  <style>
    body {
      background: #f7f9fc
    }

    .ride-card {
      border-left: 5px solid #0d6efd
    }

    .ride-card.request {
      border-left-color: #20c997
    }
  </style>
</head>

<body class="pb-5">
 <?php include'header.php' ?>

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
    const socket = io("https://hitch.shiyaswebsite.com", {
      path: "/socket.io",             // no trailing slash
      transports: ["websocket", "polling"]
    });
    socket.on("notice", d => console.log("notice:", d));
  </script>


  <script>
    const API_BASE = '/api';
    
  </script>
  <script src="/assets/js/rides.js"></script>
</body>

</html>