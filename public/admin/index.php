<?php declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';

$admin = \App\Auth\require_admin();
$csrf  = \App\Auth\csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — Rides · Glitch a Hitch</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f8f9fb; }
    .card { border: 0; border-radius: 1rem; }
    .admin-table thead th { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.08em; }
    .status-badge { text-transform: capitalize; }
    .table-actions .btn { min-width: 110px; }
    @media (max-width: 767.98px) {
      .table-responsive { overflow-x: auto; }
    }
  </style>
</head>
<body class="py-4">
  <div class="container">
    <header class="d-flex flex-wrap align-items-center gap-3 mb-4">
      <div>
        <h1 class="h3 mb-1">Admin — Rides</h1>
        <p class="text-secondary mb-0">Manage live ride offers and requests across the platform.</p>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center">
        <span class="badge text-bg-light text-secondary">Signed in as <?= htmlspecialchars($admin['display_name'] ?? $admin['email'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
        <a class="btn btn-outline-secondary btn-sm" href="/rides.php"><i class="bi bi-arrow-left me-1"></i>Back to site</a>
      </div>
    </header>

    <section class="card shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-sm-4">
            <label class="form-label text-uppercase small text-secondary" for="adminStatus">Status</label>
            <select id="adminStatus" class="form-select">
              <option value="open">Open</option>
              <option value="matched">Matched</option>
              <option value="in_progress">In progress</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
              <option value="">All statuses</option>
            </select>
          </div>
          <div class="col-sm-3">
            <label class="form-label text-uppercase small text-secondary" for="adminType">Type</label>
            <select id="adminType" class="form-select">
              <option value="">All types</option>
              <option value="offer">Offers</option>
              <option value="request">Requests</option>
            </select>
          </div>
          <div class="col-sm-3">
            <label class="form-label text-uppercase small text-secondary" for="adminSearch">Search</label>
            <input id="adminSearch" class="form-control" placeholder="Location or note">
          </div>
          <div class="col-sm-2 text-sm-end">
            <button id="adminRefresh" type="button" class="btn btn-outline-primary w-100">
              <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
              <span>Refresh</span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <div id="adminAlert" class="alert alert-danger d-none" role="alert"></div>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 admin-table">
            <thead class="table-light">
              <tr>
                <th scope="col">Ride</th>
                <th scope="col">Route</th>
                <th scope="col">Owner</th>
                <th scope="col">Schedule</th>
                <th scope="col" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="adminTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="adminEmpty" class="text-center py-5 text-secondary d-none">
      <i class="bi bi-inboxes display-5 mb-3 text-primary"></i>
      <p class="lead mb-1">No rides match the current filters.</p>
      <p class="mb-0">Try adjusting filters above or refresh for the latest data.</p>
    </div>
  </div>

  <script type="module">
    import logger from '/assets/js/utils/logger.js';

    const API_BASE = '/api';
    const CSRF = <?= json_encode($csrf, JSON_UNESCAPED_SLASHES) ?>;

    const statusFilter = document.getElementById('adminStatus');
    const typeFilter = document.getElementById('adminType');
    const searchInput = document.getElementById('adminSearch');
    const refreshBtn = document.getElementById('adminRefresh');
    const refreshSpinner = refreshBtn?.querySelector('.spinner-border');
    const alertEl = document.getElementById('adminAlert');
    const tableBody = document.getElementById('adminTableBody');
    const emptyState = document.getElementById('adminEmpty');

    let items = [];
    let fetchTimer;

    const escapePairs = [
      ['&', '&amp;'],
      ['<', '&lt;'],
      ['>', '&gt;'],
      ['"', '&quot;'],
      ['\'', '&#039;']
    ];
    const escapeMap = Object.fromEntries(escapePairs);
    const escapeHtml = (value) => value
      ? String(value).replace(/[&<>"']/g, (m) => escapeMap[m] || m)
      : '';

    const setRefreshing = (busy) => {
      if (!refreshBtn) return;
      refreshBtn.disabled = busy;
      if (refreshSpinner) refreshSpinner.classList.toggle('d-none', !busy);
    };

    const showAlert = (message) => {
      alertEl.textContent = message;
      alertEl.classList.remove('d-none');
    };

    const hideAlert = () => alertEl.classList.add('d-none');

    const buildStatusBadge = (item) => {
      const typeBadge = item.type === 'offer'
        ? '<span class="badge text-bg-primary-subtle text-primary me-1">Offer</span>'
        : '<span class="badge text-bg-success-subtle text-success me-1">Request</span>';
      const statusClasses = {
        open: 'text-bg-secondary',
        matched: 'text-bg-primary',
        in_progress: 'text-bg-info',
        completed: 'text-bg-success',
        cancelled: 'text-bg-dark',
      };
      const status = item.status || 'open';
      const statusBadge = `<span class="badge status-badge ${statusClasses[status] || 'text-bg-light text-secondary'}">${escapeHtml(status)}</span>`;
      return `${typeBadge}${statusBadge}`;
    };

    const renderTable = () => {
      tableBody.innerHTML = '';
      if (!items.length) {
        emptyState?.classList.remove('d-none');
        return;
      }
      emptyState?.classList.add('d-none');

      items.forEach((item) => {
        const tr = document.createElement('tr');
        const rideTime = item.ride_datetime ? item.ride_datetime : 'Flexible';
        tr.innerHTML = `
          <td>
            <div class="fw-semibold">#${item.id}</div>
            <div>${buildStatusBadge(item)}</div>
          </td>
          <td>
            <div class="fw-semibold">${escapeHtml(item.from_text)} <i class="bi bi-arrow-right"></i> ${escapeHtml(item.to_text)}</div>
            ${item.note ? `<div class="text-secondary small mt-1">${escapeHtml(item.note)}</div>` : ''}
          </td>
          <td>
            ${item.user_id
              ? `<a class="text-decoration-none" href="/user.php?id=${item.user_id}">${escapeHtml(item.owner_display || 'User')}</a>`
              : `<span>${escapeHtml(item.owner_display || 'User')}</span>`}
          </td>
          <td>
            <div>${escapeHtml(rideTime)}</div>
            <div class="text-secondary small">Created ${escapeHtml(item.created_at || '')}</div>
          </td>
          <td class="text-end table-actions">
            <div class="btn-group">
              <button class="btn btn-sm btn-outline-primary" data-action="edit" data-id="${item.id}"><i class="bi bi-pencil me-1"></i>Edit note</button>
              <button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${item.id}"><i class="bi bi-trash me-1"></i>Delete</button>
            </div>
          </td>`;
        tableBody.appendChild(tr);
      });
    };

    const fetchRides = async () => {
      setRefreshing(true);
      hideAlert();
      const params = new URLSearchParams();
      params.set('all', '1');
      params.set('limit', '200');

      const status = statusFilter?.value || '';
      if (status) params.set('status', status);
      const type = typeFilter?.value || '';
      if (type) params.set('type', type);
      const query = (searchInput?.value || '').trim();
      if (query) params.set('q', query);

      try {
        const res = await fetch(`${API_BASE}/ride_list.php?${params.toString()}`, { credentials: 'same-origin' });
        const data = await res.json().catch(() => ({ ok: false, items: [] }));
        if (!res.ok || !data.ok) {
          throw new Error(data.error || 'Failed to load rides');
        }
        items = Array.isArray(data.items) ? data.items : [];
        renderTable();
      } catch (err) {
        logger.error('admin:fetch_failed', err, { params: Object.fromEntries(params.entries()) });
        showAlert('Unable to load rides. Please try again in a moment.');
      } finally {
        setRefreshing(false);
      }
    };

    const updateRideNote = async (id) => {
      const ride = items.find((r) => Number(r.id) === id);
      const current = ride?.note || '';
      const note = prompt('Update ride note', current);
      if (note === null) return;
      try {
        const res = await fetch(`${API_BASE}/ride_update.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id, note, csrf: CSRF })
        });
        const data = await res.json().catch(() => ({ ok: false }));
        if (!res.ok || !data.ok) {
          throw new Error(data.error || 'Update failed');
        }
        await fetchRides();
      } catch (err) {
        logger.error('admin:update_failed', err, { id, note });
        showAlert('Could not update the ride.');
      }
    };

    const deleteRide = async (id) => {
      if (!confirm('Delete this ride? This will hide it from the public feed.')) return;
      try {
        const res = await fetch(`${API_BASE}/ride_delete.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ id, csrf: CSRF })
        });
        const data = await res.json().catch(() => ({ ok: false }));
        if (!res.ok || !data.ok) {
          throw new Error(data.error || 'Delete failed');
        }
        await fetchRides();
      } catch (err) {
        logger.error('admin:delete_failed', err, { id });
        showAlert('Unable to delete ride.');
      }
    };

    refreshBtn?.addEventListener('click', () => fetchRides());
    statusFilter?.addEventListener('change', () => fetchRides());
    typeFilter?.addEventListener('change', () => fetchRides());
    searchInput?.addEventListener('input', () => {
      clearTimeout(fetchTimer);
      fetchTimer = setTimeout(() => fetchRides(), 300);
    });

    tableBody?.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-action]');
      if (!button) return;
      const id = Number(button.dataset.id);
      if (!id) return;
      if (button.dataset.action === 'edit') {
        updateRideNote(id);
      } else if (button.dataset.action === 'delete') {
        deleteRide(id);
      }
    });

    fetchRides();
  </script>
</body>
</html>
