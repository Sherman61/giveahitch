<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/auth.php';
$csrf = \App\Auth\csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Sign up — Glitch a Hitch</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:linear-gradient(180deg,#f7f9fc,#eef3ff)}
  .auth-card{max-width:520px;margin:6vh auto;padding:2rem;border-radius:1rem;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.08)}
  .brand{font-weight:700;letter-spacing:.2px}
</style>
</head>
<body>
<nav class="navbar bg-body-tertiary">
  <div class="container">
    <a class="navbar-brand brand" href="/rides.php">Glitch a Hitch</a>
    <div class="ms-auto">
      <a class="btn btn-outline-secondary btn-sm" href="/login.php">Log in</a>
    </div>
  </div>
</nav>

<div class="auth-card">
  <h1 class="h3 mb-3">Create your account</h1>
  <p class="text-secondary mb-4">Post offers or requests, and manage your rides.</p>

  <div id="msg" class="d-none alert" role="alert"></div>

  <form id="form" class="vstack gap-3" novalidate>
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div>
      <label class="form-label">Display name</label>
      <input class="form-control" name="display_name" maxlength="100" required>
      <div class="form-text">How other riders will see you.</div>
    </div>
    <div>
      <label class="form-label">Email</label>
      <input type="email" class="form-control" name="email" required autocomplete="email">
    </div>
    <div>
      <label class="form-label">Password</label>
      <input type="password" class="form-control" name="password" minlength="8" required autocomplete="new-password">
      <div class="form-text">At least 8 characters.</div>
    </div>
    <button class="btn btn-primary w-100 py-2">Create account</button>
    <div class="text-center text-secondary">Already have an account? <a href="/login.php">Log in</a></div>
  </form>
</div>

<script>
const form = document.getElementById('form');
const msg  = document.getElementById('msg');

function show(type, text){
  msg.className = 'alert alert-'+type;
  msg.textContent = text;
  msg.classList.remove('d-none');
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  msg.classList.add('d-none');

  const fd = new FormData(form);
  const payload = Object.fromEntries(fd.entries());

  // simple front-end checks
  if (!payload.display_name || !payload.email || !payload.password) {
    show('warning','Please fill in all fields.');
    return;
  }
  if (payload.password.length < 8) {
    show('warning','Password must be at least 8 characters.');
    return;
  }

  try {
    const res = await fetch('/api/signup.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok || !data.ok) {
      if (data.error === 'exists') return show('danger','That email is already registered.');
      if (data.error === 'validation') return show('danger','Please check your inputs.');
      return show('danger','Could not create account.');
    }
    show('success','Account created! Redirecting…');
    setTimeout(()=> location.href = '/my_rides.php', 700);
  } catch(err){
    show('danger','Network error. Please try again.');
  }
});
</script>
</body>
</html>
