<?php declare(strict_types=1);
require_once __DIR__.'/../../../glitchahitch/lib/auth.php';
require_once __DIR__.'/../../../glitchahitch/lib/session.php';
\start_secure_session();
if (!\App\Auth\is_admin()) { http_response_code(403); echo 'forbidden'; exit; }
$csrf = \App\Auth\csrf_token();
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<title>Admin — Rides</title>
</head><body class="container py-4">
<h1 class="mb-3">Admin — Rides</h1>
<div id="list" class="vstack gap-3"></div>
<script>
const CSRF = <?= json_encode($csrf) ?>;
async function load(){
  const r = await fetch('/glitchahitch/api/ride_list.php?limit=200');
  const j = await r.json();
  const c = document.getElementById('list'); c.innerHTML='';
  (j.items||[]).forEach(item=>{
    const e = document.createElement('div'); e.className='card p-3';
    e.innerHTML = `
      <div class="d-flex justify-content-between">
        <div><strong>#${item.id}</strong> ${item.type} — ${item.from_text} → ${item.to_text} — owner: ${item.user_id ?? 'anon'}</div>
        <div class="btn-group">
          <button class="btn btn-sm btn-outline-primary" onclick="edit(${item.id})">Edit</button>
          <button class="btn btn-sm btn-outline-danger" onclick="delR(${item.id})">Delete</button>
        </div>
      </div>`;
    c.appendChild(e);
  });
}
function edit(id){
  const note = prompt('New note:'); if (note===null) return;
  fetch('/glitchahitch/api/ride_update.php',{method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id, note, csrf: CSRF})}).then(()=>load());
}
function delR(id){
  if(!confirm('Delete ride?')) return;
  fetch('/glitchahitch/api/ride_delete.php',{method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id, csrf: CSRF})}).then(()=>load());
}
load();
</script>
</body></html>
