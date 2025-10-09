<?php declare(strict_types=1);
require_once __DIR__.'/../lib/auth.php';
require_once __DIR__.'/../lib/security.php';
require_once __DIR__.'/../config/db.php';

$user = \App\Auth\require_login();
$csrf = \App\Auth\csrf_token();

$pdo = db();

// 1) get & validate id
$rid = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($rid <= 0) { header('Location: /my_rides.php'); exit; }

// 2) normalize user id key
$uid = (int)($user['id'] ?? $user['user_id'] ?? 0);
if ($uid <= 0) { header('Location: /my_rides.php'); exit; }

// 3) allow NULL or 0 for deleted
$stmt = $pdo->prepare("SELECT * FROM rides WHERE id = :id AND COALESCE(deleted,0) = 0 LIMIT 1");
$stmt->execute([':id' => $rid]);
$ride = $stmt->fetch(PDO::FETCH_ASSOC);

// 4) ownership check
if (!$ride || (int)$ride['user_id'] !== $uid) {
  header('Location: /my_rides.php'); exit;
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Edit Ride — Glitch a Hitch</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="pb-5">
<nav class="navbar navbar-expand-lg bg-body-tertiary mb-3">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/rides.php">Glitch a Hitch</a>
    <div class="ms-auto"><a class="btn btn-outline-secondary btn-sm" href="/my_rides.php">My rides</a></div>
  </div>
</nav>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="mb-3">Edit Ride</h4>
          <form id="editForm" class="needs-validation" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <input type="hidden" name="id" value="<?= (int)$ride['id'] ?>">

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Type</label>
                <select class="form-select" name="type" required>
                  <option value="offer" <?= $ride['type']==='offer'?'selected':'' ?>>Offer</option>
                  <option value="request" <?= $ride['type']==='request'?'selected':'' ?>>Request</option>
                </select>
                <div class="invalid-feedback">Select a type.</div>
              </div>
              <div class="col-md-8">
                <label class="form-label">Date & Time (optional)</label>
                <input type="datetime-local" class="form-control" name="ride_datetime"
                       value="<?= $ride['ride_datetime'] ? htmlspecialchars(str_replace(' ','T',$ride['ride_datetime'])) : '' ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">From</label>
                <input type="text" class="form-control" name="from_text" required minlength="2" maxlength="255"
                       value="<?= htmlspecialchars($ride['from_text']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">To</label>
                <input type="text" class="form-control" name="to_text" required minlength="2" maxlength="255"
                       value="<?= htmlspecialchars($ride['to_text']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Seats (0 = package)</label>
                <input type="number" class="form-control" name="seats" min="0" max="12" required
                       value="<?= (int)$ride['seats'] ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="tel" class="form-control" name="phone"
                  value="<?= htmlspecialchars($ride['phone']) ?>"
                  placeholder="+1 718 555 1234" pattern="^\+?[0-9\s\-\(\)]{7,20}$">
              </div>
              <div class="col-12">
                <label class="form-label">WhatsApp (optional)</label>
                <input type="tel" class="form-control" name="whatsapp"
                  value="<?= htmlspecialchars($ride['whatsapp']) ?>"
                  placeholder="+1 347 555 7890" pattern="^\+?[0-9\s\-\(\)]{7,20}$">
              </div>
              <div class="col-12">
                <label class="form-label">Note (optional)</label>
                <textarea class="form-control" name="note" rows="3" maxlength="1000"><?= htmlspecialchars((string)$ride['note']) ?></textarea>
              </div>
            </div>

            <div id="formAlert" class="mt-3"></div>
            <div class="d-flex gap-2 mt-2">
              <button class="btn btn-primary" type="submit">Save changes</button>
              <a class="btn btn-outline-secondary" href="/my_rides.php">Cancel</a>
            </div>
          </form>
        </div>
      </div>
      <p class="text-muted small mt-2">Provide at least one contact method (Phone or WhatsApp).</p>
    </div>
  </div>
</div>

<script>
const API_BASE='/api';
const form=document.getElementById('editForm');
const alertBox=document.getElementById('formAlert');

// require at least phone or whatsapp
function hasContact(f){ return (f.phone.value.trim()!=='' || f.whatsapp.value.trim()!==''); }

form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  if (!form.checkValidity() || !hasContact(form)) {
    e.stopPropagation();
    form.classList.add('was-validated');
    if(!hasContact(form)){
      alertBox.className='alert alert-warning';
      alertBox.textContent='Please provide at least one contact method (Phone or WhatsApp).';
    }
    return;
  }
  alertBox.className='alert alert-info'; alertBox.textContent='Saving...';
  const payload = Object.fromEntries(new FormData(form).entries());
  try{
    const res = await fetch(`${API_BASE}/ride_update.php`,{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body: JSON.stringify(payload)
    });
    const j = await res.json();
    if(!res.ok || !j.ok) throw new Error(j.error||'Failed');
    alertBox.className='alert alert-success'; alertBox.textContent='Saved!';
    setTimeout(()=>location.href='/my_rides.php',600);
  }catch(err){
    alertBox.className='alert alert-danger'; alertBox.textContent='Error: '+(err.message||err);
  }
});
</script>
</body>
</html>
