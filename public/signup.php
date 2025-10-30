<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/auth.php';
$csrf = \App\Auth\csrf_token();
$intentRideId = isset($_GET['acceptRide']) ? max(0, (int)$_GET['acceptRide']) : 0;
$intentQuery  = $intentRideId ? ('?acceptRide=' . $intentRideId) : '';
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
      <a class="btn btn-outline-secondary btn-sm" href="/login.php<?= $intentQuery ?>">Log in</a>
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
    <div id="pinGroup" class="d-none">
      <label class="form-label">Verification PIN</label>
      <input type="text" class="form-control" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code">
      <div class="form-text">Enter the 6-digit PIN we emailed to you.</div>
    </div>
    <button class="btn btn-primary w-100 py-2">Create account</button>
    <div class="text-center text-secondary">Already have an account? <a href="/login.php<?= $intentQuery ?>">Log in</a></div>
  </form>
</div>

<script>
const form = document.getElementById('form');
const msg  = document.getElementById('msg');
const displayInput = form.querySelector('[name="display_name"]');
const emailInput = form.querySelector('[name="email"]');
const passwordInput = form.querySelector('[name="password"]');
const pinGroup = document.getElementById('pinGroup');
const pinInput = form.querySelector('[name="pin"]');
const csrfInput = form.querySelector('input[name="csrf"]');
const submitBtn = form.querySelector('button[type="submit"]');

let awaitingPin = false;
let pendingSignup = null;

const STORAGE_KEY_ACCEPT_INTENT = 'ga_accept_ride_intent_v1';
const ACCEPT_INTENT_TTL = 24 * 60 * 60 * 1000;

const getStorage = () => {
  try {
    return window.localStorage || null;
  } catch (err) {
    console.warn('signup:storage_unavailable', err);
    return null;
  }
};

const storage = getStorage();

const rememberAcceptIntent = (rideId) => {
  if (!storage || !rideId) return;
  try {
    storage.setItem(STORAGE_KEY_ACCEPT_INTENT, JSON.stringify({ rideId, ts: Date.now() }));
  } catch (err) {
    console.warn('signup:remember_intent_failed', err);
  }
};

const readAcceptIntent = () => {
  if (!storage) return null;
  try {
    const raw = storage.getItem(STORAGE_KEY_ACCEPT_INTENT);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    const rideId = Number(parsed?.rideId || 0);
    const ts = Number(parsed?.ts || 0);
    if (!rideId) {
      storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
      return null;
    }
    if (ts && Date.now() - ts > ACCEPT_INTENT_TTL) {
      storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
      return null;
    }
    return { rideId, ts };
  } catch (err) {
    console.warn('signup:read_intent_failed', err);
    storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
    return null;
  }
};

const params = new URLSearchParams(location.search);
const acceptRideId = Number(params.get('acceptRide') || params.get('accept') || 0) || 0;
if (acceptRideId) {
  rememberAcceptIntent(acceptRideId);
  msg.className = 'alert alert-info';
  msg.textContent = 'Sign up to accept your selected ride.';
  msg.classList.remove('d-none');
}

function show(type, text){
  msg.className = 'alert alert-'+type;
  msg.textContent = text;
  msg.classList.remove('d-none');
}

function resetPinFlow({ clearPassword = false } = {}) {
  awaitingPin = false;
  pendingSignup = null;
  pinGroup.classList.add('d-none');
  pinInput.value = '';
  displayInput.removeAttribute('readonly');
  emailInput.removeAttribute('readonly');
  passwordInput.removeAttribute('disabled');
  if (clearPassword) {
    passwordInput.value = '';
  }
  submitBtn.textContent = 'Create account';
  submitBtn.disabled = false;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  msg.classList.add('d-none');

  const csrfToken = csrfInput.value;
  let payload;

  if (awaitingPin) {
    const pin = (pinInput.value || '').replace(/\D/g, '');
    if (!pin || pin.length !== 6) {
      show('warning','Please enter the 6-digit PIN we sent to your email.');
      return;
    }
    payload = {
      csrf: csrfToken,
      email: pendingSignup?.email || emailInput.value,
      pin,
    };
  } else {
    const displayName = displayInput.value.trim();
    const email = emailInput.value.trim();
    const password = passwordInput.value;

    if (!displayName || !email || !password) {
      show('warning','Please fill in all fields.');
      return;
    }
    if (password.length < 8) {
      show('warning','Password must be at least 8 characters.');
      return;
    }

    payload = {
      csrf: csrfToken,
      display_name: displayName,
      email,
      password,
    };
  }

  submitBtn.disabled = true;
  submitBtn.textContent = awaitingPin ? 'Verifying…' : 'Submitting…';

  try {
    const res = await fetch('/api/signup.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const data = await res.json().catch(() => ({}));

    if (data?.step === 'pin_required') {
      awaitingPin = true;
      pendingSignup = {
        email: payload.email,
        displayName: payload.display_name,
      };
      show('info', `Enter the 6-digit PIN we sent to ${payload.email}.`);
      pinGroup.classList.remove('d-none');
      pinInput.value = '';
      pinInput.focus();
      displayInput.setAttribute('readonly','readonly');
      emailInput.setAttribute('readonly','readonly');
      passwordInput.value = '';
      passwordInput.setAttribute('disabled','disabled');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Verify & create account';
      return;
    }

    if (!res.ok || !data.ok) {
      if (!awaitingPin) {
        if (data.error === 'exists') {
          show('danger','That email is already registered.');
        } else if (data.error === 'validation') {
          show('danger','Please check your inputs.');
        } else if (data.error === 'pin_send_failed') {
          show('danger','We could not send your PIN email. Please try again later.');
        } else {
          show('danger','Could not start signup. Please try again.');
        }
      } else {
        if (data.error === 'pin_invalid') {
          show('danger','That PIN was incorrect. Please try again.');
        } else if (data.error === 'pin_expired') {
          show('danger','Your PIN expired. Please start the signup again.');
          resetPinFlow({ clearPassword: true });
        } else if (data.error === 'no_pending' || data.error === 'email_mismatch') {
          show('danger','Your signup session has expired. Please try again.');
          resetPinFlow({ clearPassword: true });
        } else if (data.error === 'exists') {
          show('danger','That email is already registered.');
          resetPinFlow();
        } else {
          show('danger','Could not verify your PIN.');
        }
      }
      submitBtn.disabled = false;
      submitBtn.textContent = awaitingPin ? 'Verify & create account' : 'Create account';
      return;
    }

    const successMessage = data.message
      || `Congratulations you have successfully created your account ${data.user?.display_name || pendingSignup?.displayName || ''}`.trim();
    show('success', successMessage);
    const intent = readAcceptIntent();
    const target = intent?.rideId
      ? (() => {
          const url = new URL('/rides.php', location.origin);
          url.searchParams.set('acceptRide', String(intent.rideId));
          return url.toString();
        })()
      : '/my_rides.php';
    setTimeout(()=> location.href = target, 700);
  } catch(err){
    show('danger','Network error. Please try again.');
    submitBtn.disabled = false;
    submitBtn.textContent = awaitingPin ? 'Verify & create account' : 'Create account';
    return;
  }
  resetPinFlow();
  submitBtn.disabled = false;
  submitBtn.textContent = 'Create account';
});
</script>
</body>
</html>
