<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

\App\Auth\start_secure_session();
$csrf = \App\Auth\csrf_token();
$emailPrefill = isset($_GET['email']) ? (string)$_GET['email'] : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Forgot password - Glitch a Hitch</title>
<meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:linear-gradient(180deg,#f7f9fc,#eef3ff)}
  .auth-card{max-width:520px;margin:8vh auto;padding:2rem;border-radius:1rem;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,0.08)}
  .brand{font-weight:700;letter-spacing:.2px}
</style>
</head>
<body>
<nav class="navbar bg-body-tertiary">
  <div class="container">
    <a class="navbar-brand brand" href="/rides.php">Glitch a Hitch</a>
    <div class="ms-auto">
      <a class="btn btn-outline-secondary btn-sm" href="/login.php">Back to login</a>
    </div>
  </div>
</nav>

<div class="auth-card">
  <h1 class="h3 mb-3">Reset your password</h1>
  <p class="text-secondary mb-4">Enter your email and we will send you a 6-digit reset code.</p>

  <div id="msg" class="d-none alert" role="alert"></div>

  <form id="forgot-form" class="vstack gap-3" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <div>
      <label class="form-label" for="email">Email</label>
      <input type="email" class="form-control" id="email" name="email" required autocomplete="email" value="<?= htmlspecialchars($emailPrefill, ENT_QUOTES) ?>">
    </div>
    <button class="btn btn-primary w-100 py-2">Send reset code</button>
  </form>
</div>

<script src="/assets/js/forgot-password.js"></script>
</body>
</html>
