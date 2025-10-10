<?php declare(strict_types=1);
error_reporting(error_level: E_ALL);
ini_set('display_errors', '1');  
require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

start_secure_session();
$viewer = \App\Auth\current_user();
$uid = (int)($_GET['id'] ?? 0);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>User Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
  body { background: #f5f7fb; }
  .profile-card { border-radius: 1rem; }
  .stat-pill { border-radius: 0.75rem; background: rgba(13, 110, 253, 0.08); color: #0d6efd; padding: 0.75rem; }
  .stat-pill .value { font-size: 1.4rem; font-weight: 700; }
  .feedback-card { border: 1px solid rgba(13, 110, 253, 0.08); border-radius: 0.75rem; }
</style>
</head>
<body>
<?php $me = $viewer; include __DIR__ . '/header.php'; ?>
<div class="container py-4">
  <div id="profile" class="mb-4"></div>
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 class="h5 mb-0">Ratings &amp; comments from riders</h2>
          <p class="text-secondary small mb-0">Star ratings capture the overall ride experience. Comments are optional notes riders left alongside their rating.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="/rides.php"><i class="bi bi-arrow-left"></i> Back to rides</a>
      </div>
      <div id="fb" class="vstack gap-3"></div>
      <div id="fbEmpty" class="text-center text-secondary py-4 d-none">No one has rated this rider yet.</div>
    </div>
  </div>
</div>
<script>
const profileRoot = document.getElementById('profile');
const feedbackList = document.getElementById('fb');
const feedbackEmpty = document.getElementById('fbEmpty');

function escapeHtml(str){
  return String(str)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}

function formatDate(str){
  if(!str) return '—';
  const d = new Date(str.replace(' ','T'));
  if(Number.isNaN(d.getTime())) return str;
  return d.toLocaleDateString(undefined,{year:'numeric',month:'short',day:'numeric'});
}

function contactRows(contact){
  const rows = [];
  if(contact?.phone){
    rows.push(`<div><i class="bi bi-telephone-outbound me-2"></i><a href="tel:${encodeURIComponent(contact.phone)}" class="text-decoration-none">${escapeHtml(contact.phone)}</a></div>`);
  }
  if(contact?.whatsapp){
    const wa = contact.whatsapp;
    rows.push(`<div><i class="bi bi-whatsapp me-2 text-success"></i><a href="https://wa.me/${encodeURIComponent(wa.replace(/\D+/g,''))}" class="text-decoration-none" target="_blank" rel="noopener">${escapeHtml(wa)}</a></div>`);
  }
  return rows.length ? rows.join('') : '<div class="text-secondary">No contact information shared yet.</div>';
}

function starDisplay(avg){
  if(avg == null || Number.isNaN(Number(avg))){
    return '<div class="fs-4 fw-semibold">—</div>';
  }
  const value = Number(avg);
  const rounded = Math.round(value * 2) / 2;
  const full = Math.floor(rounded);
  const half = (rounded - full) >= 0.5 ? 1 : 0;
  const empty = Math.max(0, 5 - full - half);
  const icons = [];
  for(let i=0;i<full;i++) icons.push('<i class="bi bi-star-fill"></i>');
  if(half) icons.push('<i class="bi bi-star-half"></i>');
  for(let i=0;i<empty;i++) icons.push('<i class="bi bi-star"></i>');
  return `<div class="d-flex align-items-center gap-2">`+
         `<span class="text-warning fs-5">${icons.join('')}</span>`+
         `<span class="fs-4 fw-semibold">${value.toFixed(1)}</span>`+
         `<span class="text-secondary">/5</span>`+
         `</div>`;
}

function starsOnly(value){
  const rating = Math.max(0, Math.min(5, Number(value) || 0));
  const icons = [];
  for(let i=1;i<=5;i++){
    icons.push(`<i class="bi ${i <= rating ? 'bi-star-fill' : 'bi-star'}"></i>`);
  }
  return `<div class="text-warning fs-5">${icons.join('')}</div>`;
}

function ratingBlock(label, rating){
  if((rating?.count ?? 0) > 0){
    const avg = rating.average != null ? Number(rating.average) : null;
    return `<div class="border rounded-3 p-3 h-100">
      <div class="text-secondary small text-uppercase">${label}</div>
      <div class="mt-1">${starDisplay(avg)}</div>
      <div class="text-secondary small">${rating.count} rating${rating.count === 1 ? '' : 's'}</div>
    </div>`;
  }
  return `<div class="border rounded-3 p-3 h-100">
    <div class="text-secondary small text-uppercase">${label}</div>
    <div class="fs-4 fw-semibold">—</div>
    <div class="text-secondary small">No ratings yet</div>
  </div>`;
}

(async()=>{
  try {
    const res = await fetch(`/api/user_profile.php?user_id=<?= $uid ?>`, {credentials:'same-origin'});
    if(!res.ok) throw new Error('Unable to load profile');
    const data = await res.json();
    if(!data?.ok){ throw new Error('User not found'); }
    const u = data.user;
    const initial = (u.display_name || '?').trim().charAt(0)?.toUpperCase() || '?';
    const driverRatingData = data.user?.ratings?.driver ?? { count: u.driver_rating_count, average: u.driver_rating_avg };
    const passengerRatingData = data.user?.ratings?.passenger ?? { count: u.passenger_rating_count, average: u.passenger_rating_avg };

    profileRoot.innerHTML = `
      <div class="card shadow-sm profile-card">
        <div class="card-body p-4 p-lg-5">
          <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
            <div>
              <div class="d-flex align-items-center gap-3">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-size:1.5rem;">
                  ${escapeHtml(initial)}
                </div>
                <div>
                  <h1 class="h4 mb-0">${escapeHtml(u.display_name)}</h1>
                  ${u.username ? `<div class="text-secondary">@${escapeHtml(u.username)}</div>` : ''}
                  <div class="text-secondary small">Member since ${formatDate(u.created_at)}</div>
                </div>
              </div>
            </div>
            <div class="text-lg-end w-100 w-lg-auto">
              <span class="badge text-bg-primary fs-6">Score ${u.score}</span>
              ${data.is_self ? `<div class="mt-2"><a class="btn btn-outline-primary btn-sm" href="/profile.php"><i class="bi bi-gear me-1"></i>Edit profile</a></div>` : ''}
            </div>
          </div>
          <div class="row g-3 mt-4">
            <div class="col-lg-7">
              <section class="border rounded-3 p-3 h-100 bg-body-tertiary">
                <h2 class="h6 text-uppercase text-secondary mb-3">Ride activity</h2>
                <div class="row g-3">
                  <div class="col-sm-6">
                    <div class="stat-pill h-100">
                      <div class="text-uppercase small">Rides offered</div>
                      <div class="value">${u.stats.rides_offered_count}</div>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="stat-pill h-100">
                      <div class="text-uppercase small">Rides requested</div>
                      <div class="value">${u.stats.rides_requested_count}</div>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="stat-pill h-100">
                      <div class="text-uppercase small">Rides given</div>
                      <div class="value">${u.stats.rides_given_count}</div>
                    </div>
                  </div>
                  <div class="col-sm-6">
                    <div class="stat-pill h-100">
                      <div class="text-uppercase small">Rides received</div>
                      <div class="value">${u.stats.rides_received_count}</div>
                    </div>
                  </div>
                </div>
                <div class="mt-3 small text-secondary">
                  <strong>How to read these:</strong>
                  <ul class="ps-3 mb-0">
                    <li><strong>Rides offered</strong> — trips you posted as a driver.</li>
                    <li><strong>Rides requested</strong> — trips you asked others to drive.</li>
                    <li><strong>Rides given</strong> — completed trips where you drove someone.</li>
                    <li><strong>Rides received</strong> — completed trips where another driver gave you a lift.</li>
                  </ul>
                </div>
              </section>
            </div>
            <div class="col-lg-5">
              <section class="border rounded-3 p-3 h-100">
                <h2 class="h6 text-uppercase text-secondary mb-3">Ratings overview</h2>
                <div class="d-grid gap-3">
                  ${ratingBlock('As a driver', driverRatingData)}
                  ${ratingBlock('As a passenger', passengerRatingData)}
                </div>
                <p class="small text-secondary mb-0 mt-3">Ratings are 1–5 stars summarising the ride. Riders can also leave an optional written comment for extra context.</p>
              </section>
            </div>
          </div>
          <div class="mt-4">
            <h2 class="h6 text-uppercase text-secondary mb-2">Contact</h2>
            <div class="d-grid gap-2">
              ${(() => {
                const visibility = data.user.contact_visibility || {};
                if (visibility.visible) {
                  return contactRows(u.contact);
                }
                const reason = visibility.reason || 'Contact becomes visible after a ride is accepted.';
                return `<div class="text-secondary">${escapeHtml(reason)}</div>`;
              })()}
            </div>
          </div>
        </div>
      </div>`;

    if(data.feedback.length === 0){
      feedbackEmpty.classList.remove('d-none');
    } else {
      feedbackEmpty.classList.add('d-none');
      data.feedback.forEach((f)=>{
        const card = document.createElement('div');
        card.className = 'feedback-card p-3';
        const date = new Date((f.created_at || '').replace(' ','T'));
        const when = Number.isNaN(date.getTime()) ? escapeHtml(f.created_at || '') : date.toLocaleString();
        const roleLabel = f.role === 'driver' ? 'You drove' : 'You rode';
        const raterRoleLabel = f.rater_role === 'driver' ? 'driver' : 'passenger';
        const tripLabel = (f.from_text || f.to_text)
          ? `${escapeHtml(f.from_text ?? '')} → ${escapeHtml(f.to_text ?? '')}`
          : '';
        const summaryLine = f.role === 'driver'
          ? `${escapeHtml(f.rater_name)} rode with you as the passenger and rated your driving.`
          : `${escapeHtml(f.rater_name)} drove this trip and rated you as their passenger.`;
        const commentHtml = f.comment
          ? `<div class="mt-2">${escapeHtml(f.comment)}</div>`
          : '<div class="mt-2 text-secondary fst-italic">No written comment.</div>';

        card.innerHTML = `
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge text-bg-primary">${roleLabel}</span>
                ${tripLabel ? `<span class="text-secondary small">${tripLabel}</span>` : ''}
              </div>
              <div class="text-secondary small">${summaryLine} They left this feedback while riding as the ${escapeHtml(raterRoleLabel)}.</div>
              ${commentHtml}
            </div>
            <div class="text-end" style="min-width:110px;">
              ${starsOnly(f.rating)}
              <div class="fw-semibold">${f.rating}/5</div>
              <div class="small text-muted">${when}</div>
            </div>
          </div>`;
        feedbackList.appendChild(card);
      });
    }
  } catch (err) {
    console.error(err);
    feedbackList.innerHTML = '';
    feedbackEmpty.classList.add('d-none');
    const message = err?.message ? escapeHtml(err.message) : 'Unable to load user profile.';
    profileRoot.innerHTML = `<div class="alert alert-danger">${message}</div>`;
  }
})();
</script>
</body>
</html>
