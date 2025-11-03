<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once __DIR__ . '/../lib/validate.php';
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
start_secure_session();

$user = \App\Auth\current_user();
if (!$user) { header('Location: /login.php'); exit; }
$csrf = \App\Auth\csrf_token();
?> 

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Create Ride — Glitch A Hitch</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; connect-src 'self' https://shiyaswebsite.com;">
<style>body{background:#f7f9fc}</style>
</head>
<body class="pb-5">
<nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
  <div class="container">
    <a class="navbar-brand" href="/rides.php">Glitch A Hitch</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <span class="text-secondary small">Logged in as <strong><?= htmlspecialchars($user['display_name'] ?? $user['email']) ?></strong></span>
      <a class="btn btn-outline-secondary btn-sm" href="/profile.php">Profile</a>
      <a class="btn btn-outline-secondary btn-sm" href="/rides.php">View rides</a>
    </div>
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
              <div class="col-md-4">
                <label class="form-label">Start date &amp; time (optional)</label>
                <input type="datetime-local" class="form-control" name="ride_datetime">
                <div class="form-text">Leave empty if flexible.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label">End date &amp; time (optional)</label>
                <input type="datetime-local" class="form-control" name="ride_end_datetime">
                <div class="form-text">Used to auto-hide the ride after it ends.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">From</label>
                <input type="text" class="form-control" name="from_text" placeholder="Borough Park, Brooklyn, NY" required minlength="2" maxlength="255">
                <div class="invalid-feedback">Enter a valid origin. Origin and destination must be different.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">To</label>
                <input type="text" class="form-control" name="to_text" placeholder="Monsey, NY" required minlength="2" maxlength="255">
                <div class="invalid-feedback">Enter a valid destination. Origin and destination must be different.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Seats (0 = package)</label>
                <input type="number" class="form-control" name="seats" min="0" max="12" value="1" required>
                <div class="invalid-feedback">Seats 0–12.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="tel" class="form-control" name="phone" placeholder="+1 718 555 1234" inputmode="tel" pattern="^\+?[0-9\s\-\(\)]{7,20}$">
                <div class="invalid-feedback">Enter a valid phone number (7-15 digits, optional spaces, parentheses or dashes).</div>
              </div>
              <div class="col-12">
                <label class="form-label">WhatsApp (optional)</label>
                <input type="tel" class="form-control" name="whatsapp" placeholder="+1 347 555 7890" inputmode="tel" pattern="^\+?[0-9\s\-\(\)]{7,20}$">
                <div class="invalid-feedback">Enter a valid WhatsApp number (7-15 digits, optional spaces, parentheses or dashes).</div>
              </div>
              <div class="col-12">
                <div id="savedContactHint" class="d-none small text-secondary">
                  <span>Use your saved contact details from your profile?</span>
                  <button type="button" id="fillFromProfile" class="btn btn-link btn-sm p-0 align-baseline">Fill contact info</button>
                  <span class="ms-1">· <a href="/profile.php">Edit profile</a></span>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Note (optional)</label>
                <textarea class="form-control" name="note" rows="3" maxlength="1000" placeholder="Details…"></textarea>
              </div>
            </div>
            <div id="formAlert" class="mt-3"></div>
            <div class="d-flex gap-2 mt-2">
              <button class="btn btn-primary" type="submit">Post Ride</button>
              <a class="btn btn-outline-secondary" href="/rides.php">View Rides</a>
            </div>
          </form>
        </div>
      </div>
      <p class="text-muted small mt-2">Tip: provide at least one contact method (Phone or WhatsApp).</p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API_BASE='/api';
const form = document.getElementById('rideForm');
const alertBox = document.getElementById('formAlert');
const savedContactHint = document.getElementById('savedContactHint');
const fillBtn = document.getElementById('fillFromProfile');
const fromInput = form.elements['from_text'];
const toInput = form.elements['to_text'];
const phoneInput = form.elements['phone'];
const whatsappInput = form.elements['whatsapp'];
const startInput = form.elements['ride_datetime'];
const endInput = form.elements['ride_end_datetime'];
let savedContact = null;

async function loadSavedContact(){
  try {
    const res = await fetch(`${API_BASE}/profile.php`, {credentials:'same-origin'});
    if (!res.ok) return;
    const data = await res.json();
    const contact = data?.user?.contact;
    if (contact && (contact.phone || contact.whatsapp)) {
      savedContact = contact;
      savedContactHint?.classList.remove('d-none');
    }
  } catch (err) {
    console.warn('profile contact fetch failed', err);
  }
}

fillBtn?.addEventListener('click', (event)=>{
  event.preventDefault();
  if (!savedContact) return;
  if (savedContact.phone) phoneInput.value = savedContact.phone;
  if (savedContact.whatsapp) whatsappInput.value = savedContact.whatsapp;
  form.classList.remove('was-validated');
  alertBox.className = '';
  alertBox.textContent = '';
});

loadSavedContact();
 
function hasContact(f){
  return (f.phone.value.trim() !== '' || f.whatsapp.value.trim() !== '');
}

function normalizeDigits(value){
  return value.replace(/\D+/g, '');
}

function validatePhoneField(field){
  if (!field) return true;
  const value = field.value.trim();
  field.setCustomValidity('');
  if (value === '') return true;
  const patternOk = /^\+?[0-9\s\-()]+$/.test(value);
  const digits = normalizeDigits(value);
  const digitsOk = digits.length >= 7 && digits.length <= 15;
  if (!patternOk || !digitsOk) {
    field.setCustomValidity('Please enter a valid phone number with 7-15 digits.');
    return false;
  }
  return true;
}

function validateLocations(){
  if (!fromInput || !toInput) return true;
  const fromVal = fromInput.value.trim();
  const toVal = toInput.value.trim();
  fromInput.setCustomValidity('');
  toInput.setCustomValidity('');
  if (!fromVal || !toVal) return true;
  const same = fromVal.localeCompare(toVal, undefined, {sensitivity: 'accent'}) === 0;
  if (same) {
    const msg = 'Origin and destination must be different.';
    fromInput.setCustomValidity(msg);
    toInput.setCustomValidity(msg);
    return false;
  }
  return true;
}

function validateDateOrder(){
  if (!startInput || !endInput) return true;
  endInput.setCustomValidity('');
  const startVal = startInput.value.trim();
  const endVal = endInput.value.trim();
  if (!startVal || !endVal) return true;
  const startDate = new Date(startVal);
  const endDate = new Date(endVal);
  if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
    return true;
  }
  if (endDate <= startDate) {
    endInput.setCustomValidity('End time must be after the start time.');
    return false;
  }
  return true;
}

startInput?.addEventListener('change', validateDateOrder);
endInput?.addEventListener('change', () => {
  const ok = validateDateOrder();
  if (!ok) endInput.reportValidity();
});

fromInput?.addEventListener('input', validateLocations);
toInput?.addEventListener('input', validateLocations);
phoneInput?.addEventListener('input', () => validatePhoneField(phoneInput));
whatsappInput?.addEventListener('input', () => validatePhoneField(whatsappInput));

form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  alertBox.className=''; alertBox.textContent='';

  // Built-in + custom contact requirement
  const contactOk = hasContact(form);
  const datesOk = validateDateOrder();
  const locationsOk = validateLocations();
  const phoneOk = validatePhoneField(phoneInput);
  const whatsappOk = validatePhoneField(whatsappInput);

  if (!form.checkValidity() || !contactOk || !datesOk || !locationsOk || !phoneOk || !whatsappOk) {
    e.stopPropagation();
    form.classList.add('was-validated');
    if (!contactOk) {
      alertBox.className='alert alert-warning';
      alertBox.textContent='Please provide at least one contact method (Phone or WhatsApp).';
    } else if (!locationsOk) {
      alertBox.className='alert alert-warning';
      alertBox.textContent='The origin and destination must be different.';
      fromInput?.reportValidity();
    } else if (!phoneOk || !whatsappOk) {
      alertBox.className='alert alert-warning';
      alertBox.textContent='Phone numbers must contain 7-15 digits and can include spaces, parentheses or dashes.';
    } else if (!datesOk) {
      alertBox.className='alert alert-warning';
      alertBox.textContent='The end date & time must be after the start date & time.';
    }
    return;
  }

  // honeypot
  const fd = new FormData(form);
  if (fd.get('website')) return;

  const payload = Object.fromEntries(fd.entries());
  try {
    const res = await fetch(`${API_BASE}/ride_create.php`, {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify(payload)
    });
    const data = await res.json().catch(()=>({ok:false}));
    if (!res.ok || !data.ok) throw new Error(data.error || 'Create failed');
    alertBox.className='alert alert-success';
    alertBox.textContent='Ride posted!';
    setTimeout(()=> location.href='/rides.php', 600);
  } catch(err){
    alertBox.className='alert alert-danger';
    alertBox.textContent='Error: '+(err.message||err);
  }
});
</script>

</body>
</html>
