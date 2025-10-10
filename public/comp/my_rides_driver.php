<?php declare(strict_types=1);
require_once __DIR__.'/../../lib/auth.php';
$user = \App\Auth\require_login();
$csrf = \App\Auth\csrf_token();
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');                 // still log to the PHP error log
error_reporting(E_ALL);
?>
<!doctype html> 
<html lang="en">
<head>
<meta charset="utf-8">
<title>As Driver — Glitch a Hitch</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  .badge-status{text-transform:capitalize}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/rides.php">
      <i class="bi bi-steering-wheel"></i> Glitch a Hitch
    </a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/my_rides_owner.php" title="My posted rides"><i class="bi bi-journal-check me-1"></i>My posted</a>
      <a class="btn btn-outline-secondary btn-sm" href="/rides.php"><i class="bi bi-map me-1"></i>All rides</a>
      <span class="navbar-text small text-muted d-none d-md-inline">
        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
      </span>
      <a class="btn btn-outline-danger btn-sm" href="/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</nav>

<div class="container">
  <h1 class="h4 mb-3">As driver</h1>

  <h2 class="h6 text-success d-flex align-items-center gap-2">
    <i class="bi bi-check2-circle"></i> Accepted
  </h2>
  <div id="acceptedList" class="vstack gap-3 mb-4"></div>

  <h2 class="h6 text-warning d-flex align-items-center gap-2">
    <i class="bi bi-hourglass-split"></i> Pending
  </h2>
  <div id="pendingList" class="vstack gap-3"></div>

  <div id="msg" class="d-none alert mt-3"></div>
</div>

<script>
    if (!window.CSRF) {
        // throw new Error('CSRF token not set');
        const CSRF = <?= json_encode($csrf) ?>;
    }
    if (!window.MY_ID) {
        // throw new Error('MY_ID not set');
        const MY_ID = <?= (int)$user['id'] ?>;
    }
    if(!window.API_BASE) window.API_BASE = '/api';
// const API_BASE = '/api';


function esc(s){return s?String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])):'';}
function badge(status){
  const map = {pending:'warning',accepted:'primary',in_progress:'info',completed:'success',rejected:'dark',cancelled:'dark'};
  const cls = map[status] || 'light';
  return `<span class="badge badge-status text-bg-${cls}">${esc(status||'pending')}</span>`;
}

async function fetchAsDriver(){
  const res = await fetch(`${API_BASE}/my_matches.php?role=driver`, {credentials:'same-origin'});
  const data = await res.json().catch(()=>({ok:false,accepted:[],pending:[]}));

  const accWrap = document.getElementById('acceptedList');
  const penWrap = document.getElementById('pendingList');
  const msg     = document.getElementById('msg');
  accWrap.innerHTML = penWrap.innerHTML = '';
  msg.classList.add('d-none');

  if(!data.ok){
    msg.className='alert alert-danger';
    msg.textContent='Failed to load your driver matches.';
    msg.classList.remove('d-none');
    return;
  }

  renderGroup(accWrap, (data.accepted||[]), 'No accepted rides yet.');
  renderGroup(penWrap, (data.pending||[]), 'No pending requests you sent yet.');
}

function renderGroup(container, arr, emptyHtml){
  if(!arr.length){
    container.innerHTML = `<div class="alert alert-info">${emptyHtml}</div>`;
    return;
  }
  for(const m of arr){
    const dt = m.ride_datetime ? new Date(m.ride_datetime.replace(' ','T')+'Z').toLocaleString() : 'Any time';
    const contact = buildContact(m.other_phone, m.other_whatsapp);
    const notice = m.other_contact_notice ? `<div class="mt-1 text-secondary small">${esc(m.other_contact_notice)}</div>` : '';
    const card = document.createElement('div');
    card.className = 'card shadow-sm';
    card.innerHTML = `
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <div>
            <div class="fw-semibold">${m.type==='offer'?'🚗 Offer':'🙋 Request'} — ${esc(m.from_text)} → ${esc(m.to_text)}</div>
            <div class="text-muted small">Passenger: ${esc(m.other_display||'')}</div>
            ${contact ? `<div class="mt-1">${contact}</div>` : notice}
          </div>
          <div class="text-end">
            ${badge(m.status)}
            <div><span class="badge text-bg-light">${esc(dt)}</span></div>
          </div>
        </div>
      </div>`;
    container.appendChild(card);
  }
}

function buildContact(phone, whatsapp){
  const parts = [];
  if (phone) {
    parts.push(`<a class="me-2" href="tel:${encodeURIComponent(phone)}"><i class="bi bi-telephone-forward me-1"></i>${esc(phone)}</a>`);
  }
  if (whatsapp) {
    const wa = String(whatsapp).replace(/\D+/g,'');
    if (wa) parts.push(`<a target="_blank" rel="noopener" href="https://wa.me/${wa}"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>`);
  }
  return parts.join(' ') || '';
}

document.addEventListener('DOMContentLoaded', fetchAsDriver);
</script>
</body>
</html>
