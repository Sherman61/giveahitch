<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
$user = \App\Auth\require_login();
// if (!$user['is_admin']) { http_response_code(403); exit('Forbidden'); }
$csrf = \App\Auth\csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin — Manage Rides</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
<h1 class="h4 mb-4">Admin — Manage Rides</h1>
<div id="msg"></div>
<div id="adminList" class="vstack gap-3"></div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editForm" class="modal-body">
        <input type="hidden" id="rideId">
        <div class="mb-2">
          <label class="form-label">Type</label>
          <select id="rideType" class="form-select">
            <option value="offer">Offer</option>
            <option value="request">Request</option>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label">From</label>
          <input id="rideFrom" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">To</label>
          <input id="rideTo" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Date/Time</label>
          <input type="datetime-local" id="rideDatetime" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Seats</label>
          <input type="number" id="rideSeats" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Note</label>
          <textarea id="rideNote" class="form-control"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </form>
    </div>
  </div>
</div>

<script>const API_BASE='/glitchahitch/api'; const CSRF='<?= $csrf ?>';</script>
<script>
async function fetchAll(){
  const res=await fetch(`${API_BASE}/ride_list.php?all=1`,{credentials:'same-origin'});
  const data=await res.json().catch(()=>({ok:false,items:[]}));
  const list=document.getElementById('adminList');
  list.innerHTML='';
  if(!data.ok||!data.items.length){list.innerHTML='<div class="alert alert-info">No rides.</div>';return;}
  for(const item of data.items){
    const card=document.createElement('div');
    card.className='card shadow-sm';
    card.innerHTML=`<div class="card-body">
      <h5>${item.type} — User #${item.user_id||'?'}</h5>
      <p>${item.from_text} → ${item.to_text}</p>
      <p><small>${item.ride_datetime||'Any time'}</small></p>
      <button class="btn btn-sm btn-outline-primary me-2" onclick="openEdit(${item.id}, ${JSON.stringify(item).replace(/"/g,'&quot;')})">Edit</button>
      <button class="btn btn-sm btn-outline-danger" onclick="deleteRide(${item.id})">Delete</button>
    </div>`;
    list.appendChild(card);
  }
}

function openEdit(id,item){
  document.getElementById('rideId').value=id;
  document.getElementById('rideType').value=item.type;
  document.getElementById('rideFrom').value=item.from_text;
  document.getElementById('rideTo').value=item.to_text;
  document.getElementById('rideDatetime').value=item.ride_datetime?item.ride_datetime.replace(' ','T'):'';
  document.getElementById('rideSeats').value=item.seats||0;
  document.getElementById('rideNote').value=item.note||'';
  new bootstrap.Modal(document.getElementById('editModal')).show();
}

document.getElementById('editForm').addEventListener('submit',async e=>{
  e.preventDefault();
  const id=document.getElementById('rideId').value;
  const payload={
    id,csrf:CSRF,
    type:document.getElementById('rideType').value,
    from_text:document.getElementById('rideFrom').value,
    to_text:document.getElementById('rideTo').value,
    ride_datetime:document.getElementById('rideDatetime').value,
    seats:document.getElementById('rideSeats').value,
    note:document.getElementById('rideNote').value
  };
  const res=await fetch(`${API_BASE}/ride_update.php`,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    credentials:'same-origin',
    body:JSON.stringify(payload)
  });
  const data=await res.json();
  if(data.ok){
    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
    fetchAll();
  }else alert("Update failed");
});

async function deleteRide(id){
  if(!confirm("Delete ride #"+id+"?"))return;
  const res=await fetch(`${API_BASE}/ride_delete.php`,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    credentials:'same-origin',
    body:JSON.stringify({id,csrf:CSRF,admin:1})
  });
  const data=await res.json();
  if(data.ok) fetchAll(); else alert("Delete failed");
}

fetchAll();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
