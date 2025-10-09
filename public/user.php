<?php declare(strict_types=1);
error_reporting(error_level: E_ALL);
ini_set('display_errors', '1');  
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';
$uid = (int)($_GET['id'] ?? 0);
// if($uid<=0){ header('Location:/glitchahitch/rides.php'); exit; }
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>User Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
<a href="/rides.php" class="btn btn-link">&larr; Back</a>
<div id="profile"></div>
<div class="mt-4">
  <h5>Feedback</h5>
  <div id="fb" class="vstack gap-2"></div>
</div>
<script>
(async()=>{
  const r = await fetch(`/api/user_profile.php?user_id=<?= $uid ?>`);
  const j = await r.json();
  if(!j.ok) { document.getElementById('profile').innerHTML = '<div class="alert alert-danger">User not found</div>'; return; }
  const u = j.user;
  document.getElementById('profile').innerHTML = `
    <div class="card p-3">
      <h3 class="mb-1">${u.display_name}</h3>
      <div class="text-secondary">Score: <strong>${u.score}</strong></div>
      <div class="mt-2">Rides given: <strong>${u.public_counts.rides_given_count}</strong></div>
      <div class="mt-2">Driver rating: ${u.driver_rating_avg ?? 'N/A'} (${u.driver_rating_count})</div>
      <div class="mt-1">Passenger rating: ${u.passenger_rating_avg ?? 'N/A'} (${u.passenger_rating_count})</div>
    </div>`;
  const fb = document.getElementById('fb');
  for(const f of j.feedback){
    const e = document.createElement('div'); e.className='card p-2';
    e.innerHTML = `<div><strong>${f.rater_name}</strong> rated ${f.role} ${f.rating}/5</div>
      ${f.comment? `<div class="text-secondary">${f.comment}</div>`:''}
      <div class="small text-muted">${new Date(f.created_at.replace(' ','T')+'Z').toLocaleString()}</div>`;
    fb.appendChild(e);
  }
})();
</script>
</body>
</html>
