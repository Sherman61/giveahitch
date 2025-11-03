import logger from './utils/logger.js';

const listEl = document.getElementById('rateList');
const statusEl = document.getElementById('rateStatus');
const API = typeof window.API_BASE === 'string' ? window.API_BASE : '/api';
const csrfToken = window.CSRF_TOKEN || '';
const meId = Number(window.ME_USER_ID || 0) || null;

const formatDateTime = (value) => {
  if (!value) return 'Flexible time';
  const iso = value.includes('T') ? value : value.replace(' ', 'T');
  const date = new Date(/Z$/i.test(iso) ? iso : `${iso}Z`);
  if (Number.isNaN(date.getTime())) return value;
  try {
    return date.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
  } catch (err) {
    logger.warn?.('rate:format_failed', err);
    return date.toISOString();
  }
};

const showStatus = (type, message) => {
  if (!statusEl) return;
  if (!message) {
    statusEl.classList.add('d-none');
    statusEl.textContent = '';
    return;
  }
  statusEl.className = `alert alert-${type}`;
  statusEl.textContent = message;
  statusEl.classList.remove('d-none');
};

const buildCard = (item) => {
  const card = document.createElement('article');
  card.className = 'rating-card card shadow-sm';

  const isDriver = meId && Number(item.driver_user_id) === meId;
  const targetName = isDriver ? item.passenger_display : item.driver_display;
  const targetRole = isDriver ? 'passenger' : 'driver';
  const rideLabel = `${item.from_text} → ${item.to_text}`;
  const rideTime = formatDateTime(item.ride_datetime || item.updated_at || '');
  const alreadyRated = Boolean(item.already_rated);
  const matchId = Number(item.match_id);
  const rideId = Number(item.ride_id);

  const header = `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <div class="d-flex align-items-center gap-2">
        <span class="rating-badge"><i class="bi bi-people"></i>${isDriver ? 'You drove' : 'You rode'}</span>
        <span class="text-secondary small">Completed ride</span>
      </div>
      <span class="text-secondary small"><i class="bi bi-clock-history me-1"></i>${rideTime}</span>
    </div>
    <h2 class="h5 fw-semibold text-primary mb-2">${rideLabel}</h2>
    <p class="text-secondary mb-3">Tell us how ${targetName || 'your match'} did as a ${targetRole}.</p>
  `;

  let body = '';
  if (alreadyRated) {
    body = '<div class="alert alert-success mb-0">Thanks! You already shared feedback for this ride.</div>';
  } else {
    body = `
      <form class="vstack gap-3" data-match="${matchId}" data-ride="${rideId}">
        <div>
          <label class="form-label fw-semibold">Rate this ${targetRole}</label>
          <div class="btn-group stars-group" role="group" aria-label="Rating">
            ${[1, 2, 3, 4, 5].map((star) => `
              <input type="radio" class="btn-check" name="stars" id="rate-${matchId}-${star}" value="${star}" ${star === 5 ? 'checked' : ''}>
              <label class="btn btn-outline-warning" for="rate-${matchId}-${star}">${'★'.repeat(star)}</label>
            `).join('')}
          </div>
        </div>
        <div>
          <label class="form-label">Feedback (optional)</label>
          <textarea class="form-control" name="comment" rows="2" maxlength="1000" placeholder="Share highlights or things to improve"></textarea>
          <div class="form-text">Your comments help future riders know what to expect.</div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <button class="btn btn-success" type="submit">
            <i class="bi bi-send me-1"></i>Submit rating
          </button>
          <span class="text-success small d-none" data-status>Thanks for the feedback!</span>
        </div>
      </form>
    `;
  }

  card.innerHTML = `
    <div class="card-body p-4">
      ${header}
      ${body}
    </div>
  `;

  if (!alreadyRated) {
    const form = card.querySelector('form');
    const status = card.querySelector('[data-status]');
    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form) return;
      const formData = new FormData(form);
      const starsValue = Number(formData.get('stars')) || 0;
      const comment = String(formData.get('comment') || '').trim();

      if (starsValue < 1 || starsValue > 5) {
        alert('Please select a rating between 1 and 5 stars.');
        return;
      }

      const submitBtn = form.querySelector('button');
      const originalButtonHtml = submitBtn?.innerHTML;
      form.querySelectorAll('button,textarea,input').forEach((el) => { el.disabled = true; });
      status?.classList.add('d-none');
      if (submitBtn) {
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting…';
      }

      try {
        const res = await fetch(`${API}/rate_submit.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            csrf: csrfToken,
            ride_id: rideId,
            match_id: matchId,
            stars: starsValue,
            comment,
          })
        });
        const data = await res.json().catch(() => ({ ok: false }));
        if (!res.ok || !data.ok) {
          const message = data?.error === 'not_completed'
            ? 'This ride is not completed yet.'
            : (data?.error === 'already_rated'
              ? 'You already rated this ride.'
              : 'Unable to submit your rating.');
          throw new Error(message);
        }
        const successEl = document.createElement('div');
        successEl.className = 'alert alert-success mb-0';
        successEl.textContent = 'Thanks for the feedback!';
        form.replaceWith(successEl);
      } catch (err) {
        logger.error('rate:submit_failed', err, { matchId });
        alert(err?.message || 'Could not submit your rating.');
      } finally {
        if (submitBtn) {
          submitBtn.innerHTML = originalButtonHtml || '<i class="bi bi-send me-1"></i>Submit rating';
        }
        if (form.isConnected) {
          form.querySelectorAll('button,textarea,input').forEach((el) => { el.disabled = false; });
        }
      }
    });
  }

  return card;
};

const renderMatches = (items) => {
  if (!listEl) return;
  listEl.innerHTML = '';

  if (!items.length) {
    listEl.innerHTML = `
      <div class="card shadow-sm border-0 p-4 text-center bg-white">
        <div class="display-6 text-primary mb-2"><i class="bi bi-emoji-smile"></i></div>
        <p class="lead text-secondary mb-0">No rides need your rating right now. Check back after your next trip!</p>
      </div>`;
    return;
  }

  items.forEach((item) => {
    listEl.appendChild(buildCard(item));
  });
};

const fetchMatches = async () => {
  showStatus('info', 'Loading your completed rides…');
  try {
    const res = await fetch(`${API}/my_matches.php`, { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({ ok: false, items: [] }));
    if (!res.ok || !data.ok) {
      throw new Error(data.error || 'Unable to load rides');
    }
    const items = Array.isArray(data.items) ? data.items : [];
    const completed = items
      .filter((item) => item.match_status === 'completed')
      .sort((a, b) => {
        const aDate = new Date(a.updated_at || a.created_at || 0).getTime();
        const bDate = new Date(b.updated_at || b.created_at || 0).getTime();
        return bDate - aDate;
      });
    renderMatches(completed);
    showStatus('', '');
  } catch (err) {
    logger.error('rate:fetch_failed', err);
    showStatus('danger', 'We could not load your rides. Please try again later.');
  }
};

if (!meId) {
  showStatus('warning', 'Please log in to rate your rides.');
} else {
  fetchMatches();
}
