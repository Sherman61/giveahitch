<?php declare(strict_types=1);
require_once __DIR__.'/../../lib/auth.php';
$user = \App\Auth\require_login();
$csrf = \App\Auth\csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Posted Rides — Glitch a Hitch</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  .ride-card{cursor:pointer}
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
      <a class="btn btn-outline-secondary btn-sm" href="/my_rides_driver.php" title="As Driver"><i class="bi bi-person-gear me-1"></i>As driver</a>
      <a class="btn btn-outline-secondary btn-sm" href="/rides.php"><i class="bi bi-map me-1"></i>All rides</a>
      <span class="navbar-text small text-muted d-none d-md-inline">
        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
      </span>
      <a class="btn btn-outline-danger btn-sm" href="/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>
</nav>

<div class="container">
  <h1 class="h4 mb-3">My posted rides</h1>
  <div id="postedMsg" class="d-none alert"></div>
  <div id="myRidesList" class="vstack gap-3"></div>
</div>

<!-- Manage Pending Requests Modal -->
<div class="modal fade" id="manageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pending requests</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="pendingList"></div>
      </div>
    </div>
  </div>
</div>

<script>
const API_BASE = '/api';
const CSRF = <?= json_encode($csrf) ?>;

function esc(s){return s?String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])):'';}
function badge(status){
  const map = {open:'secondary',pending:'warning',matched:'primary',in_progress:'info',completed:'success',cancelled:'dark',rejected:'dark'};
  const cls = map[status] || 'light';
  return `<span class="badge badge-status text-bg-${cls}">${esc(status||'open')}</span>`;
}

async function fetchPosted(){
  const res = await fetch(`${API_BASE}/ride_list.php?mine=1`, {credentials:'same-origin'});
  const data = await res.json().catch(()=>({ok:false,items:[]}));
  const list = document.getElementById('myRidesList');
  const msg  = document.getElementById('postedMsg');
  list.innerHTML = '';
  msg.classList.add('d-none');

  if(!data.ok || !data.items.length){
    list.innerHTML = '<div class="alert alert-info">No rides yet. <a href="/create.php" class="alert-link">Create one</a>.</div>';
    return;
  }

  for(const item of data.items){
    const dt = item.ride_datetime ? new Date(item.ride_datetime.replace(' ','T')+'Z').toLocaleString() : 'Any time';
    const seats = (item.package_only || item.seats===0) ? 'Package only' : `${item.seats} seat(s)`;
    const st = item.status || 'open';
    const canManage = (st==='open');

    const card = document.createElement('div');
    card.className = 'card ride-card shadow-sm';
    card.innerHTML = `
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-1">${item.type==='offer'?'🚗 Offer':'🙋 Request'}</h5>
            ${badge(st)}
          </div>
          <span class="badge text-bg-light">${esc(dt)}</span>
        </div>
        <div class="row mt-2">
          <div class="col-md-8">
            <div><strong>From:</strong> ${esc(item.from_text)}</div>
            <div><strong>To:</strong> ${esc(item.to_text)}</div>
            <div><strong>Seats:</strong> ${esc(seats)}</div>
          </div>
          <div class="col-md-4 text-end">
            <a href="/edit_ride.php?id=${item.id}" class="btn btn-sm btn-outline-primary me-2">
              <i class="bi bi-pencil-square me-1"></i>Edit
            </a>
            <button class="btn btn-sm btn-outline-danger me-2" data-del="${item.id}">
              <i class="bi bi-trash3 me-1"></i>Delete
            </button>
            ${canManage ? `<button class="btn btn-sm btn-outline-success" data-manage="${item.id}">
              <i class="bi bi-people me-1"></i>Manage
            </button>` : ''}
          </div>
        </div>
      </div>`;

    // Delete
    card.querySelector('button[data-del]')?.addEventListener('click', async ()=>{
      if(!confirm('Delete this ride?')) return;
      const res=await fetch(`${API_BASE}/ride_delete.php`,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'same-origin',
        body:JSON.stringify({id:item.id, csrf:CSRF})
      });
      const j=await res.json().catch(()=>({ok:false}));
      if(j.ok){ fetchPosted(); } else { alert('Failed to delete'); }
    });

    // Manage (list pending + confirm)
    card.querySelector('button[data-manage]')?.addEventListener('click', ()=>{
      openManageModal(item.id);
    });

    list.appendChild(card);
  }
}

async function openManageModal(rideId){
  const wrap = document.getElementById('pendingList');
  wrap.innerHTML = 'Loading...';
  const modalEl = document.getElementById('manageModal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();

  const res = await fetch(`${API_BASE}/ride_matches_list.php?ride_id=${rideId}`, {credentials:'same-origin'});
  const data = await res.json().catch(()=>({ok:false,pending:[]}));
  if(!data.ok){
    wrap.innerHTML = '<div class="alert alert-danger">Could not load pending requests.</div>';
    return;
  }
  if(!data.pending.length){
    wrap.innerHTML = '<div class="alert alert-info">No pending requests yet.</div>';
    return;
  }
  wrap.innerHTML = '';
  data.pending.forEach(p=>{
    const row = document.createElement('div');
    row.className = 'd-flex justify-content-between align-items-center border rounded p-2 mb-2';
    row.innerHTML = `
      <div>
        <div class="fw-semibold"><i class="bi bi-person-check me-1"></i>${esc(p.requester_name)}</div>
        <div class="text-muted small">Requested at ${new Date(p.created_at.replace(' ','T')+'Z').toLocaleString()}</div>
      </div>
      <div>
        <button class="btn btn-sm btn-success" data-confirm="${p.match_id}" data-ride="${data.ride.id}">
          <i class="bi bi-check2-circle me-1"></i>Confirm
        </button>
      </div>`;
    row.querySelector('[data-confirm]')?.addEventListener('click', async (e)=>{
      const mid = +e.currentTarget.getAttribute('data-confirm');
      const rid = +e.currentTarget.getAttribute('data-ride');
      e.currentTarget.disabled = true;
      const res = await fetch(`${API_BASE}/match_confirm.php`,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        credentials:'same-origin',
        body: JSON.stringify({ ride_id: rid, match_id: mid, csrf: CSRF })
      });
      const j = await res.json().catch(()=>({ok:false}));
      if(j.ok){
        wrap.innerHTML = '<div class="alert alert-success">Confirmed. Ride is now matched.</div>';
        setTimeout(()=>{ modal.hide(); fetchPosted(); }, 600);
      }else{
        alert('Confirm failed');
        e.currentTarget.disabled = false;
      }
    });
    wrap.appendChild(row);
  });
}

document.addEventListener('DOMContentLoaded', fetchPosted);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
