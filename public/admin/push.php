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
  <title>Admin — Push notifications · Glitch a Hitch</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f8f9fb; }
    .card { border: 0; border-radius: 1rem; }
    textarea { min-height: 140px; }
  </style>
</head>
<body class="py-4">
  <div class="container">
    <header class="d-flex flex-wrap align-items-center gap-3 mb-4">
      <div>
        <h1 class="h3 mb-1">Admin — Push notifications</h1>
        <p class="text-secondary mb-0">Send a manual push message to every subscribed device.</p>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
        <span class="badge text-bg-light text-secondary">Signed in as <?= htmlspecialchars($admin['display_name'] ?? $admin['email'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
        <a class="btn btn-outline-secondary btn-sm" href="/admin/index.php"><i class="bi bi-arrow-left me-1"></i>Back to admin home</a>
        <a class="btn btn-outline-secondary btn-sm" href="/rides.php"><i class="bi bi-house-door me-1"></i>Back to site</a>
      </div>
    </header>

    <section class="card shadow-sm">
      <div class="card-body p-4">
        <form id="pushForm" class="vstack gap-3">
          <div>
            <label class="form-label fw-semibold" for="pushTitle">Notification title</label>
            <input id="pushTitle" name="title" class="form-control" maxlength="120" required placeholder="Platform update or alert">
          </div>
          <div>
            <label class="form-label fw-semibold" for="pushBody">Notification body <span class="text-secondary fw-normal">(optional)</span></label>
            <textarea id="pushBody" name="body" class="form-control" maxlength="280" placeholder="Share additional details that appear under the title."></textarea>
          </div>
          <div>
            <label class="form-label fw-semibold" for="pushCategory">Notification category</label>
            <select id="pushCategory" name="category" class="form-select">
              <option value="social" selected>Social updates</option>
              <option value="service">Service alerts</option>
              <option value="reminders">Reminders</option>
              <option value="announcements">Announcements</option>
            </select>
            <div class="form-text">Browsers may show this category when users manage site notifications.</div>
          </div>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <div class="d-flex gap-3 align-items-center flex-wrap">
            <button id="pushSubmit" type="submit" class="btn btn-primary">
              <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
              <span>Send notification</span>
            </button>
            <button id="pushReset" type="button" class="btn btn-outline-secondary">Clear</button>
          </div>
          <div id="pushAlert" class="alert d-none" role="alert"></div>
        </form>
        <hr class="my-4">
        <div class="small text-secondary">
          <p class="mb-1"><strong>Tip:</strong> Devices must have previously enabled push notifications on the site to receive this broadcast.</p>
          <p class="mb-0">Invalid or expired subscriptions are automatically removed after each send.</p>
        </div>
      </div>
    </section>
  </div>

  <script type="module">
    const form = document.getElementById('pushForm');
    const submitBtn = document.getElementById('pushSubmit');
    const submitSpinner = submitBtn?.querySelector('.spinner-border');
    const resetBtn = document.getElementById('pushReset');
    const alertEl = document.getElementById('pushAlert');

    const setBusy = (busy) => {
      if (!submitBtn) return;
      submitBtn.disabled = busy;
      submitBtn.classList.toggle('disabled', busy);
      if (submitSpinner) submitSpinner.classList.toggle('d-none', !busy);
    };

    const showAlert = (type, message) => {
      if (!alertEl) return;
      alertEl.className = `alert alert-${type}`;
      alertEl.textContent = message;
      alertEl.classList.remove('d-none');
    };

    const clearAlert = () => {
      if (alertEl) alertEl.classList.add('d-none');
    };

    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearAlert();

      const data = new FormData(form);
      const title = data.get('title')?.toString().trim();
      if (!title) {
        showAlert('warning', 'Please provide a notification title.');
        return;
      }

      const payload = {
        title,
        body: data.get('body')?.toString().trim() || '',
        category: data.get('category')?.toString().trim() || 'social',
        csrf: data.get('csrf'),
      };

      try {
        setBusy(true);
        const resp = await fetch('/api/admin_push.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: JSON.stringify(payload),
        });

        const result = await resp.json().catch(() => ({}));

        if (!resp.ok || !result.ok) {
          const errorCode = result.error || `HTTP ${resp.status}`;
          const friendly = {
            missing_title: 'A notification title is required.',
            no_subscribers: 'No devices are currently subscribed to push notifications.',
            config_missing: 'Push configuration is missing on the server.',
            invalid_vapid_config: 'The VAPID keys are not configured correctly.',
            invalid_category: 'Select a valid notification category.',
          };
          const tone = errorCode === 'missing_title' || errorCode === 'no_subscribers' ? 'warning' : 'danger';
          const message = friendly[errorCode] || `Failed to send notification: ${errorCode}.`;
          showAlert(tone, message);
          if (errorCode === 'missing_title') {
            document.getElementById('pushTitle')?.focus();
          }
          return;
        }

        const { sent = 0, failed = 0, deleted = 0 } = result;
        const summary = [`Sent to ${sent} subscription${sent === 1 ? '' : 's'}`];
        if (failed) summary.push(`${failed} failed`);
        if (deleted) summary.push(`${deleted} removed`);
        showAlert('success', `Push notification sent successfully. ${summary.join(', ')}.`);
        form.reset();
      } catch (error) {
        console.error('admin_push:send_failed', error);
        showAlert('danger', 'An unexpected error occurred while sending.');
      } finally {
        setBusy(false);
      }
    });

    resetBtn?.addEventListener('click', () => {
      form?.reset();
      clearAlert();
      document.getElementById('pushTitle')?.focus();
    });
  </script>
</body>
</html>
