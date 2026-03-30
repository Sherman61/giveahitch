<?php declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/reports.php';

$admin = \App\Auth\require_admin();
$csrf = \App\Auth\csrf_token();
$reportReasons = \App\Reports\ride_report_reasons();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — Ride Reports · Glitch a Hitch</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f8f9fb; }
    .card { border: 0; border-radius: 1rem; }
    .report-card { border-left: 4px solid #dee2e6; }
    .report-card[data-status="open"] { border-left-color: #dc3545; }
    .report-card[data-status="reviewing"] { border-left-color: #fd7e14; }
    .report-card[data-status="closed"] { border-left-color: #198754; }
    .report-card[data-status="dismissed"] { border-left-color: #6c757d; }
  </style>
</head>
<body class="py-4">
  <div class="container">
    <header class="d-flex flex-wrap align-items-center gap-3 mb-4">
      <div>
        <h1 class="h3 mb-1">Ride Reports</h1>
        <p class="text-secondary mb-0">Review flagged rides, record admin notes, and remove bad listings when needed.</p>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center flex-wrap justify-content-end">
        <span class="badge text-bg-light text-secondary">Signed in as <?= htmlspecialchars($admin['display_name'] ?? $admin['email'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
        <a class="btn btn-outline-primary btn-sm" href="/admin/index.php"><i class="bi bi-grid me-1"></i>Admin home</a>
        <a class="btn btn-outline-success btn-sm" href="/admin/score.php"><i class="bi bi-graph-up-arrow me-1"></i>Score tools</a>
        <a class="btn btn-outline-secondary btn-sm" href="/rides.php"><i class="bi bi-arrow-left me-1"></i>Back to site</a>
      </div>
    </header>

    <section class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label text-uppercase small text-secondary" for="reportStatus">Status</label>
            <select id="reportStatus" class="form-select">
              <option value="">All statuses</option>
              <option value="open">Open</option>
              <option value="reviewing">Reviewing</option>
              <option value="closed">Closed</option>
              <option value="dismissed">Dismissed</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label text-uppercase small text-secondary" for="reportReason">Reason</label>
            <select id="reportReason" class="form-select">
              <option value="">All reasons</option>
              <?php foreach ($reportReasons as $key => $reason): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($reason['label'], ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label text-uppercase small text-secondary" for="reportSearch">Search</label>
            <input id="reportSearch" class="form-control" placeholder="Route, reporter, owner, note">
          </div>
          <div class="col-md-2">
            <button id="reportRefresh" type="button" class="btn btn-outline-primary w-100">Refresh</button>
          </div>
        </div>
      </div>
    </section>

    <div id="reportAlert" class="alert alert-danger d-none" role="alert"></div>
    <div id="reportSuccess" class="alert alert-success d-none" role="status"></div>

    <div id="reportList" class="vstack gap-3"></div>

    <div id="reportEmpty" class="card shadow-sm d-none">
      <div class="card-body text-center py-5 text-secondary">
        <i class="bi bi-flag display-5 text-danger"></i>
        <p class="lead mt-3 mb-1">No reports match the current filters.</p>
        <p class="mb-0">Try a different filter or refresh for the latest cases.</p>
      </div>
    </div>
  </div>

  <script type="module">
    import logger from '/assets/js/utils/logger.js';

    const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>;
    const REPORT_REASONS = <?= json_encode($reportReasons, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    const statusFilter = document.getElementById('reportStatus');
    const reasonFilter = document.getElementById('reportReason');
    const searchInput = document.getElementById('reportSearch');
    const refreshBtn = document.getElementById('reportRefresh');
    const listEl = document.getElementById('reportList');
    const emptyEl = document.getElementById('reportEmpty');
    const alertEl = document.getElementById('reportAlert');
    const successEl = document.getElementById('reportSuccess');

    let items = [];
    let fetchTimer;

    const escapeMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    const escapeHtml = (value) => value == null ? '' : String(value).replace(/[&<>"']/g, (m) => escapeMap[m] || m);
    const reasonLabel = (key) => REPORT_REASONS[key]?.label || key || 'Unknown reason';
    const showError = (message) => {
      alertEl.textContent = message;
      alertEl.classList.remove('d-none');
    };
    const clearError = () => alertEl.classList.add('d-none');
    const showSuccess = (message) => {
      successEl.textContent = message;
      successEl.classList.remove('d-none');
      setTimeout(() => successEl.classList.add('d-none'), 2500);
    };

    const buildCard = (item) => {
      const statusBadgeClass = {
        open: 'text-bg-danger',
        reviewing: 'text-bg-warning',
        closed: 'text-bg-success',
        dismissed: 'text-bg-secondary',
      }[item.status] || 'text-bg-light text-secondary';
      const rideDeleted = Number(item.ride_deleted || 0) === 1;
      return `
        <div class="card shadow-sm report-card" data-status="${escapeHtml(item.status || '')}">
          <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge ${statusBadgeClass}">${escapeHtml(item.status || 'open')}</span>
                <span class="badge text-bg-light text-secondary">${escapeHtml(reasonLabel(item.reason_key))}</span>
                ${rideDeleted ? '<span class="badge text-bg-dark">Ride removed</span>' : ''}
              </div>
              <div class="text-secondary small">Reported ${escapeHtml(item.created_at || '')}</div>
            </div>

            <div class="row g-3">
              <div class="col-lg-5">
                <div class="fw-semibold mb-1">${escapeHtml(item.from_text)} <i class="bi bi-arrow-right"></i> ${escapeHtml(item.to_text)}</div>
                <div class="small text-secondary mb-2">Ride #${escapeHtml(item.ride_id)} · ${escapeHtml(item.ride_type || '')} · ${escapeHtml(item.ride_status || '')}</div>
                <div class="small mb-1">Owner: <a href="/user.php?id=${encodeURIComponent(item.owner_user_id)}">${escapeHtml(item.owner_display_name || item.owner_email || `User #${item.owner_user_id}`)}</a></div>
                <div class="small mb-2">Reporter: <a href="/user.php?id=${encodeURIComponent(item.reporter_user_id)}">${escapeHtml(item.reporter_display_name || item.reporter_email || `User #${item.reporter_user_id}`)}</a></div>
                ${item.details ? `<div class="bg-light rounded-3 p-3 small"><div class="text-uppercase text-secondary fw-semibold mb-1">Report details</div>${escapeHtml(item.details)}</div>` : '<div class="text-secondary small">No extra details were submitted.</div>'}
              </div>

              <div class="col-lg-7">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label small text-uppercase text-secondary">Admin status</label>
                    <select class="form-select" data-field="status" data-id="${item.id}">
                      <option value="open"${item.status === 'open' ? ' selected' : ''}>Open</option>
                      <option value="reviewing"${item.status === 'reviewing' ? ' selected' : ''}>Reviewing</option>
                      <option value="closed"${item.status === 'closed' ? ' selected' : ''}>Closed</option>
                      <option value="dismissed"${item.status === 'dismissed' ? ' selected' : ''}>Dismissed</option>
                    </select>
                  </div>
                  <div class="col-md-8">
                    <label class="form-label small text-uppercase text-secondary">Admin note</label>
                    <textarea class="form-control" rows="3" data-field="admin_notes" data-id="${item.id}" placeholder="What happened, what you checked, and why you took action.">${escapeHtml(item.admin_notes || '')}</textarea>
                  </div>
                </div>

                <div class="form-check mt-3">
                  <input class="form-check-input" type="checkbox" value="1" id="deleteRide-${item.id}" data-field="delete_ride" data-id="${item.id}">
                  <label class="form-check-label" for="deleteRide-${item.id}">
                    Remove this ride from the site while handling this report
                  </label>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                  <button class="btn btn-primary" type="button" data-action="save" data-id="${item.id}">
                    <i class="bi bi-check2-circle me-1"></i>Save action
                  </button>
                  <a class="btn btn-outline-secondary" href="/manage_ride.php?id=${encodeURIComponent(item.ride_id)}">
                    <i class="bi bi-eye me-1"></i>Open ride
                  </a>
                  ${item.reviewed_at ? `<span class="small text-secondary">Last reviewed ${escapeHtml(item.reviewed_at)}${item.reviewed_by_display_name ? ` by ${escapeHtml(item.reviewed_by_display_name)}` : ''}</span>` : ''}
                </div>
              </div>
            </div>
          </div>
        </div>
      `;
    };

    const render = () => {
      listEl.innerHTML = '';
      if (!items.length) {
        emptyEl.classList.remove('d-none');
        return;
      }
      emptyEl.classList.add('d-none');
      listEl.innerHTML = items.map(buildCard).join('');
    };

    const fetchReports = async () => {
      clearError();
      const params = new URLSearchParams();
      params.set('limit', '100');
      if (statusFilter.value) params.set('status', statusFilter.value);
      if (reasonFilter.value) params.set('reason', reasonFilter.value);
      const q = searchInput.value.trim();
      if (q) params.set('q', q);

      try {
        const res = await fetch(`/api/admin_reports.php?${params.toString()}`, { credentials: 'same-origin' });
        const data = await res.json().catch(() => ({ ok: false, items: [] }));
        if (!res.ok || !data.ok) {
          throw new Error(data.error || 'Failed to load reports');
        }
        items = Array.isArray(data.items) ? data.items : [];
        render();
      } catch (error) {
        logger.error('admin_reports:fetch_failed', error, { params: Object.fromEntries(params.entries()) });
        showError('Unable to load ride reports right now.');
      }
    };

    const saveReport = async (reportId) => {
      const statusEl = document.querySelector(`[data-field="status"][data-id="${reportId}"]`);
      const notesEl = document.querySelector(`[data-field="admin_notes"][data-id="${reportId}"]`);
      const deleteEl = document.querySelector(`[data-field="delete_ride"][data-id="${reportId}"]`);

      try {
        const res = await fetch('/api/admin_reports.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            csrf: CSRF,
            report_id: reportId,
            status: statusEl?.value || 'open',
            admin_notes: notesEl?.value || '',
            delete_ride: !!deleteEl?.checked,
          }),
        });
        const data = await res.json().catch(() => ({ ok: false }));
        if (!res.ok || !data.ok) {
          throw new Error(data.error || 'Save failed');
        }
        showSuccess(data.ride_deleted ? 'Report updated and ride removed.' : 'Report updated.');
        await fetchReports();
      } catch (error) {
        logger.error('admin_reports:save_failed', error, { reportId });
        showError('Could not save that report action.');
      }
    };

    refreshBtn?.addEventListener('click', fetchReports);
    statusFilter?.addEventListener('change', fetchReports);
    reasonFilter?.addEventListener('change', fetchReports);
    searchInput?.addEventListener('input', () => {
      clearTimeout(fetchTimer);
      fetchTimer = setTimeout(fetchReports, 250);
    });
    listEl?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action="save"]');
      if (!button) return;
      const reportId = Number(button.dataset.id || 0);
      if (!reportId) return;
      saveReport(reportId);
    });

    fetchReports();
  </script>
</body>
</html>
