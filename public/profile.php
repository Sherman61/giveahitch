<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';
require_once __DIR__ . '/../lib/auth.php';

start_secure_session();
$me = \App\Auth\require_login();
$csrf = \App\Auth\csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Profile Settings — Glitch A Hitch</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
    body { background: #f7f9fc; }
    .stat-card { border-radius: 0.75rem; border: 1px solid rgba(13, 110, 253, 0.1); background: #fff; }
    .stat-card .value { font-size: 1.6rem; font-weight: 700; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/header.php'; ?>

  <div class="container py-4">
    <div class="row justify-content-center">
      <div class="col-xl-8 col-lg-9">
        <div class="card shadow-sm mb-4">
          <div class="card-body p-4 p-lg-5">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
              <div>
                <h1 class="h3 mb-1">Profile settings</h1>
                <p class="text-secondary mb-0">Update the contact details that appear on the rides you post.</p>
              </div>
              <div class="text-md-end">
                <div class="text-secondary small">Member since</div>
                <div class="fw-semibold" id="memberSince">—</div>
              </div>
            </div>

            <form id="profileForm" class="mt-4">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Display name</label>
                  <input type="text" class="form-control" name="display_name" required minlength="2" maxlength="100">
                  <div class="invalid-feedback">Display name must be between 2 and 100 characters.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" value="<?= htmlspecialchars($me['email'] ?? '') ?>" disabled>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="tel" class="form-control" name="phone" placeholder="+1 718 555 1234" pattern="^\+?[0-9\s\-\(\)]{7,32}$">
                  <div class="invalid-feedback">Enter a valid phone number or leave blank.</div>
                  <div class="form-text">Provide the number you want riders to call.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">WhatsApp</label>
                  <input type="tel" class="form-control" name="whatsapp" placeholder="+1 347 555 7890" pattern="^\+?[0-9\s\-\(\)]{7,32}$">
                  <div class="invalid-feedback">Enter a valid WhatsApp number or leave blank.</div>
                  <div class="form-text">Optional but recommended if you prefer messaging.</div>
                </div>
                <div class="col-12">
                  <div class="alert alert-info d-none" id="contactPreview"></div>
                </div>
              </div>
              <div id="profileAlert" class="mt-4"></div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="submit" class="btn btn-primary">Save changes</button>
                <a class="btn btn-outline-secondary" href="/create.php"><i class="bi bi-plus-circle me-1"></i>Create a ride</a>
                <a class="btn btn-outline-secondary" href="/user.php?id=<?= (int)$me['id'] ?>">View public profile</a>
              </div>
            </form>
          </div>
        </div>

        <div class="row g-3" id="profileStats">
          <div class="col-sm-6 col-lg-3">
            <div class="stat-card p-3 h-100">
              <div class="text-secondary small text-uppercase">Rides offered</div>
              <div class="value" id="statOffered">0</div>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="stat-card p-3 h-100">
              <div class="text-secondary small text-uppercase">Rides requested</div>
              <div class="value" id="statRequested">0</div>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="stat-card p-3 h-100">
              <div class="text-secondary small text-uppercase">Rides given</div>
              <div class="value" id="statGiven">0</div>
            </div>
          </div>
          <div class="col-sm-6 col-lg-3">
            <div class="stat-card p-3 h-100">
              <div class="text-secondary small text-uppercase">Rides received</div>
              <div class="value" id="statReceived">0</div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mt-4">
          <div class="card-body">
            <h2 class="h5 mb-3">Ratings</h2>
            <div class="row g-3">
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100">
                  <div class="text-secondary small text-uppercase">Driver rating</div>
                  <div class="fs-4 fw-semibold" id="driverRating">—</div>
                  <div class="text-secondary small" id="driverRatingCount">No driver ratings yet</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="border rounded-3 p-3 h-100">
                  <div class="text-secondary small text-uppercase">Passenger rating</div>
                  <div class="fs-4 fw-semibold" id="passengerRating">—</div>
                  <div class="text-secondary small" id="passengerRatingCount">No passenger ratings yet</div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const form = document.getElementById('profileForm');
    const alertBox = document.getElementById('profileAlert');
    const contactPreview = document.getElementById('contactPreview');
    const memberSinceEl = document.getElementById('memberSince');
    const statOffered = document.getElementById('statOffered');
    const statRequested = document.getElementById('statRequested');
    const statGiven = document.getElementById('statGiven');
    const statReceived = document.getElementById('statReceived');
    const driverRating = document.getElementById('driverRating');
    const driverRatingCount = document.getElementById('driverRatingCount');
    const passengerRating = document.getElementById('passengerRating');
    const passengerRatingCount = document.getElementById('passengerRatingCount');
    const fieldMap = {
      display_name: form.display_name,
      phone: form.phone,
      whatsapp: form.whatsapp,
    };

    function showAlert(type, message) {
      alertBox.className = `alert alert-${type}`;
      alertBox.textContent = message;
    }

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function clearValidation() {
      Object.values(fieldMap).forEach((input) => {
        input.classList.remove('is-invalid');
      });
    }

    function formatDate(dateString) {
      if (!dateString) return '—';
      const date = new Date(dateString.replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) return dateString;
      return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    async function loadProfile() {
      try {
        const res = await fetch('/api/profile.php', { credentials: 'same-origin' });
        if (!res.ok) throw new Error('Unable to load profile');
        const data = await res.json();
        if (!data?.ok) throw new Error(data?.error || 'Unable to load profile');
        const user = data.user;
        clearValidation();
        form.display_name.value = user.display_name || '';
        form.phone.value = user.contact?.phone || '';
        form.whatsapp.value = user.contact?.whatsapp || '';

        memberSinceEl.textContent = formatDate(user.created_at);
        statOffered.textContent = user.stats?.rides_offered_count ?? 0;
        statRequested.textContent = user.stats?.rides_requested_count ?? 0;
        statGiven.textContent = user.stats?.rides_given_count ?? 0;
        statReceived.textContent = user.stats?.rides_received_count ?? 0;

        if (user.contact?.phone || user.contact?.whatsapp) {
          contactPreview.classList.remove('d-none');
          const parts = [];
          if (user.contact.phone) parts.push(`<strong>Phone:</strong> ${escapeHtml(user.contact.phone)}`);
          if (user.contact.whatsapp) parts.push(`<strong>WhatsApp:</strong> ${escapeHtml(user.contact.whatsapp)}`);
          contactPreview.innerHTML = `These details will autofill when you create rides: ${parts.join(' · ')}`;
        } else {
          contactPreview.classList.add('d-none');
        }

        if (user.ratings?.driver?.count) {
          driverRating.textContent = `${user.ratings.driver.average?.toFixed(1) ?? user.ratings.driver.average}`;
          driverRatingCount.textContent = `${user.ratings.driver.count} rating${user.ratings.driver.count === 1 ? '' : 's'}`;
        } else {
          driverRating.textContent = '—';
          driverRatingCount.textContent = 'No driver ratings yet';
        }

        if (user.ratings?.passenger?.count) {
          passengerRating.textContent = `${user.ratings.passenger.average?.toFixed(1) ?? user.ratings.passenger.average}`;
          passengerRatingCount.textContent = `${user.ratings.passenger.count} rating${user.ratings.passenger.count === 1 ? '' : 's'}`;
        } else {
          passengerRating.textContent = '—';
          passengerRatingCount.textContent = 'No passenger ratings yet';
        }
      } catch (err) {
        console.error(err);
        showAlert('danger', err.message || 'Unable to load profile');
      }
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearValidation();
      const payload = {
        csrf: form.csrf.value,
        display_name: form.display_name.value.trim(),
        phone: form.phone.value.trim(),
        whatsapp: form.whatsapp.value.trim(),
      };
      try {
        showAlert('info', 'Saving changes…');
        const res = await fetch('/api/profile.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok || !data?.ok) {
          const msg = data?.fields ? 'Please correct the highlighted fields.' : (data?.error || 'Save failed');
          throw Object.assign(new Error(msg), { fields: data?.fields });
        }
        showAlert('success', 'Profile updated successfully.');
        form.display_name.value = data.user.display_name || '';
        form.phone.value = data.user.contact?.phone || '';
        form.whatsapp.value = data.user.contact?.whatsapp || '';
        loadProfile();
      } catch (err) {
        if (err.fields) {
          Object.entries(err.fields).forEach(([key, message]) => {
            const input = fieldMap[key];
            if (!input) return;
            input.classList.add('is-invalid');
            const feedback = input.parentElement.querySelector('.invalid-feedback');
            if (feedback && message) feedback.textContent = message;
          });
          showAlert('warning', err.message || 'Please correct the highlighted fields.');
        } else {
          showAlert('danger', err.message || 'Save failed');
        }
      }
    });

    loadProfile();
  </script>
</body>
</html>
