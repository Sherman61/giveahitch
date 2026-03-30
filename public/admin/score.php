<?php declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';

$admin = \App\Auth\require_admin();
$csrf = \App\Auth\csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — Score Adjustments · Glitch a Hitch</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f8f9fb; }
    .card { border: 0; border-radius: 1rem; }
    .score-pill-positive { color: #198754; }
    .score-pill-negative { color: #dc3545; }
  </style>
</head>
<body class="py-4">
  <div class="container">
    <header class="d-flex flex-wrap align-items-center gap-3 mb-4">
      <div>
        <h1 class="h3 mb-1">Score Adjustments</h1>
        <p class="text-secondary mb-0">Manually adjust a member score only when there is a clear reason you want visible in their score history.</p>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center flex-wrap justify-content-end">
        <span class="badge text-bg-light text-secondary">Signed in as <?= htmlspecialchars($admin['display_name'] ?? $admin['email'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
        <a class="btn btn-outline-primary btn-sm" href="/admin/index.php"><i class="bi bi-grid me-1"></i>Admin home</a>
        <a class="btn btn-outline-danger btn-sm" href="/admin/reports.php"><i class="bi bi-flag me-1"></i>Reports</a>
        <a class="btn btn-outline-secondary btn-sm" href="/rides.php"><i class="bi bi-arrow-left me-1"></i>Back to site</a>
      </div>
    </header>

    <section class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-8">
            <label class="form-label text-uppercase small text-secondary" for="scoreSearch">Find member</label>
            <input id="scoreSearch" class="form-control" placeholder="Search by name, email, username, or exact user ID">
          </div>
          <div class="col-md-2">
            <label class="form-label text-uppercase small text-secondary" for="scoreLimit">Rows</label>
            <select id="scoreLimit" class="form-select">
              <option value="10">10</option>
              <option value="20" selected>20</option>
              <option value="50">50</option>
            </select>
          </div>
          <div class="col-md-2">
            <button id="scoreRefresh" type="button" class="btn btn-outline-primary w-100">Refresh</button>
          </div>
        </div>
      </div>
    </section>

    <div class="alert alert-warning mb-4" role="alert">
      Manual changes should be rare. Every change made here is logged into the member's score history with your written reason.
    </div>

    <div id="scoreAlert" class="alert alert-danger d-none" role="alert"></div>
    <div id="scoreSuccess" class="alert alert-success d-none" role="status"></div>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Member</th>
                <th>Current score</th>
                <th style="min-width: 140px;">Points change</th>
                <th style="min-width: 300px;">Reason</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody id="scoreTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="scoreEmpty" class="card shadow-sm d-none mt-4">
      <div class="card-body text-center py-5 text-secondary">
        <i class="bi bi-people display-5 text-primary"></i>
        <p class="lead mt-3 mb-1">No members match that search.</p>
        <p class="mb-0">Try a broader name, email, username, or exact user ID.</p>
      </div>
    </div>
  </div>

  <script type="module">
    import logger from '/assets/js/utils/logger.js';

    const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>;
    const searchInput = document.getElementById('scoreSearch');
    const limitSelect = document.getElementById('scoreLimit');
    const refreshBtn = document.getElementById('scoreRefresh');
    const tableBody = document.getElementById('scoreTableBody');
    const emptyEl = document.getElementById('scoreEmpty');
    const alertEl = document.getElementById('scoreAlert');
    const successEl = document.getElementById('scoreSuccess');

    let items = [];
    let fetchTimer;

    const escapeMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    const escapeHtml = (value) => value == null ? '' : String(value).replace(/[&<>"']/g, (m) => escapeMap[m] || m);
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

    const render = () => {
      tableBody.innerHTML = '';
      if (!items.length) {
        emptyEl.classList.remove('d-none');
        return;
      }
      emptyEl.classList.add('d-none');
      tableBody.innerHTML = items.map((item) => `
        <tr data-user-id="${item.id}">
          <td>
            <div class="fw-semibold">${escapeHtml(item.display_name || 'Member')}</div>
            <div class="small text-secondary">
              #${escapeHtml(item.id)}
              ${item.username ? ` · @${escapeHtml(item.username)}` : ''}
              ${item.email ? ` · ${escapeHtml(item.email)}` : ''}
            </div>
          </td>
          <td>
            <span class="fw-semibold">${escapeHtml(item.score)}</span>
          </td>
          <td>
            <input class="form-control" data-field="points" data-id="${item.id}" type="number" step="1" placeholder="+100 or -50">
          </td>
          <td>
            <input class="form-control" data-field="reason" data-id="${item.id}" type="text" maxlength="1000" placeholder="Explain exactly why this score changed">
          </td>
          <td class="text-end">
            <div class="d-flex justify-content-end gap-2">
              <a class="btn btn-sm btn-outline-secondary" href="/user.php?id=${encodeURIComponent(item.id)}">Profile</a>
              <button class="btn btn-sm btn-primary" type="button" data-action="apply" data-id="${item.id}">Apply</button>
            </div>
          </td>
        </tr>
      `).join('');
    };

    const fetchUsers = async () => {
      clearError();
      const params = new URLSearchParams();
      params.set('limit', limitSelect.value || '20');
      const q = searchInput.value.trim();
      if (q) params.set('q', q);

      try {
        const res = await fetch(`/api/admin_score.php?${params.toString()}`, { credentials: 'same-origin' });
        const data = await res.json().catch(() => ({ ok: false, items: [] }));
        if (!res.ok || !data.ok) {
          throw new Error(data.error || 'Failed to load members');
        }
        items = Array.isArray(data.items) ? data.items : [];
        render();
      } catch (error) {
        logger.error('admin_score:fetch_failed', error, { params: Object.fromEntries(params.entries()) });
        showError('Unable to load members right now.');
      }
    };

    const applyChange = async (userId) => {
      const pointsEl = document.querySelector(`[data-field="points"][data-id="${userId}"]`);
      const reasonEl = document.querySelector(`[data-field="reason"][data-id="${userId}"]`);
      const points = Number(pointsEl?.value || 0);
      const reason = (reasonEl?.value || '').trim();

      if (!points || !reason) {
        showError('Both a non-zero point change and a clear reason are required.');
        return;
      }

      try {
        const res = await fetch('/api/admin_score.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            csrf: CSRF,
            user_id: userId,
            points,
            reason,
          }),
        });
        const data = await res.json().catch(() => ({ ok: false }));
        if (!res.ok || !data.ok) {
          throw new Error(data.error || 'Update failed');
        }

        items = items.map((item) => item.id === userId ? { ...item, score: data.user?.score ?? item.score } : item);
        render();
        showSuccess(`Updated ${data.user?.display_name || 'member'} to ${data.user?.score ?? ''} points.`);
      } catch (error) {
        logger.error('admin_score:apply_failed', error, { userId, points, reason });
        showError('Could not update that score.');
      }
    };

    refreshBtn?.addEventListener('click', fetchUsers);
    limitSelect?.addEventListener('change', fetchUsers);
    searchInput?.addEventListener('input', () => {
      clearTimeout(fetchTimer);
      fetchTimer = setTimeout(fetchUsers, 250);
    });
    tableBody?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-action="apply"]');
      if (!button) return;
      const userId = Number(button.dataset.id || 0);
      if (!userId) return;
      applyChange(userId);
    });

    fetchUsers();
  </script>
</body>
</html>
