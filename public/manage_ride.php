<?php declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';

$user = \App\Auth\require_login();
$csrf = \App\Auth\csrf_token();

$rideId = (int)($_GET['id'] ?? 0);
if ($rideId <= 0) {
  header('Location: /my_rides.php'); exit;
}

// Verify ownership now to fail fast (page-level guard)
$pdo = db();
$stmt = $pdo->prepare("SELECT id, user_id, type, status, from_text, to_text, ride_datetime
                       FROM rides
                       WHERE id = :id AND deleted = 0");
$stmt->execute([':id' => $rideId]);
$ride = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ride || (int)$ride['user_id'] !== (int)$user['id']) {
  header('Location: /my_rides.php'); exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manage Ride — Glitch a Hitch</title>
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
      <a class="btn btn-outline-secondary btn-sm" href="/my_rides.php"><i class="bi bi-arrow-left me-1"></i>My rides</a>
      <a class="btn btn-outline-danger btn-sm" href="/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</nav>

<div class="container">
  <h1 class="h4 mb-2">Manage ride</h1>
  <div class="text-muted mb-3">
    <strong><?= htmlspecialchars($ride['type'] === 'offer' ? 'Offer' : 'Request') ?></strong> —
    <?= htmlspecialchars($ride['from_text']) ?> → <?= htmlspecialchars($ride['to_text']) ?> ·
    <?= htmlspecialchars($ride['ride_datetime'] ?: 'Any time') ?>
  </div>

  <div id="notice" class="d-none alert"></div>

  <div id="acceptedWrap" class="mb-4"></div>

  <h2 class="h6 d-flex align-items-center gap-2">
    <i class="bi bi-hourglass-split"></i> Pending requests
  </h2>
  <div id="pendingWrap" class="vstack gap-2"></div>
</div>

<script>
  const API_BASE = '/api';
  const CSRF = <?= json_encode($csrf) ?>;
  const RIDE_ID = <?= (int)$ride['id'] ?>;

  const esc = s => s ? String(s).replace(/[&<>"']/g, m => (
    {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]
  )) : '';

  function badge(status){
    const map = {open:'secondary',pending:'warning',accepted:'primary',matched:'primary',in_progress:'info',completed:'success',cancelled:'dark',rejected:'dark'};
    const cls = map[status] || 'light';
    return `<span class="badge badge-status text-bg-${cls}">${esc(status||'pending')}</span>`;
  }

  async function load(){
    const notice = document.getElementById('notice');
    const acceptedWrap = document.getElementById('acceptedWrap');
    const pendingWrap  = document.getElementById('pendingWrap');

    notice.classList.add('d-none');
    acceptedWrap.innerHTML = '';
    pendingWrap.innerHTML  = '<div class="text-muted">Loading…</div>';

    let data;
    try {
      const res = await fetch(`${API_BASE}/ride_matches_list.php?ride_id=${RIDE_ID}`, { credentials:'same-origin' });
      data = await res.json();
    } catch (e) {
      pendingWrap.innerHTML = '';
      notice.className = 'alert alert-danger';
      notice.textContent = 'Could not load matches.';
      notice.classList.remove('d-none');
      return;
    }

    pendingWrap.innerHTML = '';

    if (!data?.ok) {
      notice.className = 'alert alert-danger';
      notice.textContent = 'Could not load matches.';
      notice.classList.remove('d-none');
      return;
    }

    // Accepted (if any)
    const activeMatch = data.accepted || data.confirmed;
    if (activeMatch) {
      const a = activeMatch;
      const contactHtml = buildContact(a.other_phone, a.other_whatsapp);
      const contactNotice = a.other_contact_notice ? `<div class="mt-2 text-secondary small">${esc(a.other_contact_notice)}</div>` : '';
      const contactBlock = contactHtml
        ? `<div class="mt-2">${contactHtml}</div>`
        : contactNotice;
      acceptedWrap.innerHTML = `
        <div class="card shadow-sm">
          <div class="card-body d-flex justify-content-between">
            <div>
              <div class="fw-semibold">Accepted match</div>
              <div class="text-muted small">Other party: ${esc(a.other_display || 'User')}</div>
              <div class="mt-1">${badge(a.status || 'accepted')}</div>
              ${contactBlock || ''}
            </div>
            <div class="text-end">
              <span class="badge text-bg-light">${esc(a.ride_datetime || 'Any time')}</span>
            </div>
          </div>
        </div>`;
    }

    // Pending list
    const pend = Array.isArray(data.pending) ? data.pending : [];
    if (!pend.length) {
      pendingWrap.innerHTML = '<div class="alert alert-info">No pending requests.</div>';
      return;
    }

    for (const p of pend) {
      const contact = buildContact(p.requester_phone, p.requester_whatsapp);
      const notice = p.requester_contact_notice ? `<div class="mt-1 text-secondary small">${esc(p.requester_contact_notice)}</div>` : '';
      const row = document.createElement('div');
      row.className = 'border rounded p-2 d-flex justify-content-between align-items-center';
      row.innerHTML = `
        <div>
          <div class="fw-semibold">${esc(p.requester_name || 'User')}</div>
          <div class="text-muted small">Requested: ${esc(p.created_at)}</div>
          ${contact ? `<div class="mt-1">${contact}</div>` : notice}
        </div>
        <div>
          <button class="btn btn-sm btn-success" data-confirm="${p.match_id}">Confirm</button>
        </div>
      `;
      row.querySelector('[data-confirm]')?.addEventListener('click', () => confirmMatch(p.match_id));
      pendingWrap.appendChild(row);
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
    return parts.join(' ');
  }

  async function confirmMatch(matchId){
    const notice = document.getElementById('notice');
    notice.classList.add('d-none');

    try {
      const res = await fetch(`${API_BASE}/match_confirm.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ csrf: CSRF, ride_id: RIDE_ID, match_id: matchId })
      });
      const j = await res.json().catch(() => ({ok:false}));
      if (!j.ok) throw new Error(j.error || 'Failed');

      notice.className = 'alert alert-success';
      notice.textContent = 'Confirmed. Ride is now matched.';
      notice.classList.remove('d-none');
      load();
    } catch (err) {
      notice.className = 'alert alert-danger';
      notice.textContent = 'Confirm failed.';
      notice.classList.remove('d-none');
    }
  }

  document.addEventListener('DOMContentLoaded', load);
</script>
</body>
</html>
