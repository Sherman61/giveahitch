<?php declare(strict_types=1);
require_once __DIR__.'/../../lib/auth.php';
require_once __DIR__.'/../../lib/session.php';
\start_secure_session();
$u = \App\Auth\current_user();
if (!$u) { header('Location: /glitchahitch/login.php'); exit; }
$csrf = \App\Auth\csrf_token();
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<title>My Rides</title>
</head><body class="container py-4">
<h1 class="mb-3">My Rides</h1>
<div id="mine" class="vstack gap-3"></div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
async function fetchMine(){
  const r = await fetch('/glitchahitch/api/ride_list.php?mine=1');
  const j = await r.json();
  const c = document.getElementById('mine'); c.innerHTML='';
  (j.items||[]).forEach(item=>{
    const e = document.createElement('div'); e.className='card p-3';
    e.innerHTML = `
      <div class="d-flex justify-content-between">
        <div><strong>${item.type.toUpperCase()}</strong> ${item.from_text} → ${item.to_text}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-outline-primary" onclick="editRide(${item.id})">Edit</button>
          <button class="btn btn-sm btn-outline-danger" onclick="delRide(${item.id})">Delete</button>
        </div>
      </div>`;
    c.appendChild(e);
  });
}
async function delRide(id){
  if(!confirm('Delete this ride?')) return;
  const r = await fetch('/glitchahitch/api/ride_delete.php',{method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id, csrf: CSRF})});
  const j = await r.json(); if(j.ok){ fetchMine(); }
}
function editRide(id){
  const to = prompt('New destination (leave blank to skip)'); if(to===null) return;
  const note = prompt('New note (leave blank to skip)'); if(note===null) return;
  const payload = {id, csrf: CSRF};
  if (to!=='') payload.to_text = to;
  if (note!=='') payload.note = note;
  fetch('/glitchahitch/api/ride_update.php',{method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)})
    .then(r=>r.json()).then(()=>fetchMine());
}
fetchMine();
</script>
</body></html>
