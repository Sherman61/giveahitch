<?php declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

start_secure_session();
$me = \App\Auth\require_login();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Score History</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background: linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%); }
    .score-shell { max-width: 980px; }
    .score-hero { border: 0; border-radius: 1.4rem; background: linear-gradient(135deg, #0d6efd 0%, #5aa6ff 100%); color: #fff; }
    .score-card { border: 0; border-radius: 1.2rem; }
    .score-event { border: 1px solid rgba(13,110,253,.08); border-radius: 1rem; }
    .score-pill { border-radius: 999px; font-weight: 700; }
  </style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="container py-4 score-shell">
  <section class="card shadow score-hero mb-4">
    <div class="card-body p-4 p-lg-5">
      <div class="text-uppercase small fw-semibold mb-2">Your reputation</div>
      <h1 class="display-6 fw-bold mb-2">Score history</h1>
      <p class="lead mb-0">See which actions changed your score and which older points cannot be explained yet.</p>
    </div>
  </section>

  <div id="scoreSummary" class="row g-3 mb-4"></div>
  <div id="scoreStatus" class="alert d-none"></div>
  <section class="card shadow-sm score-card">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
          <h2 class="h4 mb-1">Why your score changed</h2>
          <p class="text-secondary mb-0">Every tracked score change appears here with the reason, date, and related ride details when available.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-primary" href="/docs/score.php"><i class="bi bi-info-circle me-1"></i>How score works</a>
          <a class="btn btn-outline-secondary" href="/docs/trust.php"><i class="bi bi-shield-check me-1"></i>Trust guide</a>
        </div>
      </div>
      <div id="scoreEvents" class="vstack gap-3"></div>
    </div>
  </section>
</main>
<script>
const summaryWrap = document.getElementById('scoreSummary');
const statusWrap = document.getElementById('scoreStatus');
const eventsWrap = document.getElementById('scoreEvents');

function escapeHtml(value){
  return String(value ?? '')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}

function formatDate(value){
  if(!value) return '—';
  const dt = new Date(String(value).replace(' ','T'));
  if(Number.isNaN(dt.getTime())) return escapeHtml(value);
  return dt.toLocaleString([], { dateStyle:'medium', timeStyle:'short' });
}

function renderSummary(data){
  const currentScore = Number(data.user?.score || 0);
  const trackedTotal = Number(data.tracked_total || 0);
  const unexplained = Number(data.unexplained_balance || 0);
  summaryWrap.innerHTML = `
    <div class="col-md-4">
      <div class="card shadow-sm score-card h-100"><div class="card-body">
        <div class="text-secondary small text-uppercase">Current score</div>
        <div class="display-6 fw-bold text-primary">${currentScore}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm score-card h-100"><div class="card-body">
        <div class="text-secondary small text-uppercase">Tracked points</div>
        <div class="display-6 fw-bold text-success">+${trackedTotal}</div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm score-card h-100"><div class="card-body">
        <div class="text-secondary small text-uppercase">Not itemized yet</div>
        <div class="display-6 fw-bold text-secondary">${unexplained}</div>
      </div></div>
    </div>`;
}

function renderEvents(data){
  const events = Array.isArray(data.events) ? data.events : [];
  if (!events.length) {
    eventsWrap.innerHTML = '<div class="alert alert-info mb-0">No tracked score events yet. New score changes will appear here once they happen.</div>';
    return;
  }

  eventsWrap.innerHTML = '';
  events.forEach((event) => {
    const delta = Number(event.points_delta || 0);
    const deltaLabel = delta > 0 ? `+${delta}` : `${delta}`;
    const badgeClass = delta >= 0 ? 'text-bg-success' : 'text-bg-danger';
    const rideText = event.related_ride_id ? `Ride #${event.related_ride_id}` : '';
    const matchText = event.related_match_id ? `Match #${event.related_match_id}` : '';
    const details = event.details ? `<div class="text-secondary mt-2">${escapeHtml(event.details)}</div>` : '';
    const metaParts = [];
    if (rideText) metaParts.push(escapeHtml(rideText));
    if (matchText) metaParts.push(escapeHtml(matchText));
    const metaLine = metaParts.length ? `<div class="small text-secondary mt-2">${metaParts.join(' · ')}</div>` : '';
    const card = document.createElement('div');
    card.className = 'score-event p-3';
    card.innerHTML = `
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
        <div class="flex-grow-1">
          <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
            <span class="badge ${badgeClass} score-pill">${escapeHtml(deltaLabel)}</span>
            <span class="fw-semibold">${escapeHtml(event.reason_label || event.reason_key || 'Score update')}</span>
          </div>
          ${details}
          ${metaLine}
        </div>
        <div class="text-secondary small">${formatDate(event.created_at)}</div>
      </div>`;
    eventsWrap.appendChild(card);
  });
}

(async() => {
  try {
    const res = await fetch('/api/score_history.php', { credentials:'same-origin' });
    const data = await res.json();
    if (!res.ok || !data?.ok) throw new Error(data?.error || 'Unable to load score history');
    renderSummary(data);
    renderEvents(data);
    if (!data.tracking_available) {
      statusWrap.className = 'alert alert-warning';
      statusWrap.textContent = 'Score event tracking is not available yet on this database. Your current score is shown, but older reasons cannot be listed until the migration is applied.';
      statusWrap.classList.remove('d-none');
    } else if (Number(data.unexplained_balance || 0) !== 0) {
      statusWrap.className = 'alert alert-secondary';
      statusWrap.textContent = 'Some older score changes were made before score event tracking was added, so they cannot be fully itemized yet.';
      statusWrap.classList.remove('d-none');
    }
  } catch (err) {
    statusWrap.className = 'alert alert-danger';
    statusWrap.textContent = err?.message || 'Unable to load score history.';
    statusWrap.classList.remove('d-none');
  }
})();
</script>
</body>
</html>
