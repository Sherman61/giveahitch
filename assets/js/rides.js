import logger from './utils/logger.js';

const list = document.getElementById('ridesList');
const filterType = document.getElementById('filterType');
const searchBox = document.getElementById('searchBox');
const sortOrder = document.getElementById('sortOrder');
const refreshBtn = document.getElementById('refreshBtn');
const refreshSpinner = refreshBtn?.querySelector('.spinner-border');
const liveBadge = document.getElementById('liveBadge');
const activeFilters = document.getElementById('activeFilters');
const errorAlert = document.getElementById('errorAlert');
const emptyState = document.getElementById('emptyState');
const skeleton = document.getElementById('loadingSkeleton');
const resultsMeta = document.getElementById('resultsMeta');
const totalCountEl = document.getElementById('countTotal');
const offersCountEl = document.getElementById('countOffers');
const requestsCountEl = document.getElementById('countRequests');
const lastUpdatedEl = document.getElementById('lastUpdated');

const meId = Number(window.ME_USER_ID || 0) || null;
const API = typeof window.API_BASE === 'string' ? window.API_BASE : '/api';

const STORAGE_KEY_ACCEPT_INTENT = 'ga_accept_ride_intent_v1';
const ACCEPT_INTENT_TTL = 24 * 60 * 60 * 1000; // 24 hours

const getStorage = () => {
  try {
    return window.localStorage || null;
  } catch (err) {
    logger.warn?.('rides:storage_unavailable', err);
    return null;
  }
};

const storage = getStorage();

const clearAcceptQueryParam = () => {
  try {
    const url = new URL(window.location.href);
    const hadParam = url.searchParams.has('acceptRide') || url.searchParams.has('accept');
    if (!hadParam) return;
    url.searchParams.delete('acceptRide');
    url.searchParams.delete('accept');
    const newSearch = url.searchParams.toString();
    const newUrl = `${url.pathname}${newSearch ? `?${newSearch}` : ''}${url.hash}`;
    window.history.replaceState(window.history.state, document.title, newUrl);
  } catch (err) {
    logger.warn?.('rides:clear_accept_param_failed', err);
  }
};

const readAcceptIntent = () => {
  if (!storage) return null;
  try {
    const raw = storage.getItem(STORAGE_KEY_ACCEPT_INTENT);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    const rideId = Number(parsed?.rideId || 0);
    const ts = Number(parsed?.ts || 0);
    if (!rideId) {
      storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
      return null;
    }
    if (ts && Date.now() - ts > ACCEPT_INTENT_TTL) {
      storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
      return null;
    }
    return { rideId, ts };
  } catch (err) {
    logger.warn?.('rides:read_intent_failed', err);
    storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
    return null;
  }
};

const rememberAcceptIntent = (rideId) => {
  if (!storage) return;
  try {
    storage.setItem(STORAGE_KEY_ACCEPT_INTENT, JSON.stringify({ rideId, ts: Date.now() }));
  } catch (err) {
    logger.warn?.('rides:store_intent_failed', err, { rideId });
  }
};

let acceptPromptShownFor = null;

const clearAcceptIntent = () => {
  acceptPromptShownFor = null;
  clearAcceptQueryParam();
  if (!storage) return;
  try {
    storage.removeItem(STORAGE_KEY_ACCEPT_INTENT);
  } catch (err) {
    logger.warn?.('rides:clear_intent_failed', err);
  }
};

let rawItems = [];
let isFetching = false;
let autoRefreshTimer = null;
let pendingAcceptIntent = meId ? readAcceptIntent() : null;

const rtf = (typeof Intl !== 'undefined' && Intl.RelativeTimeFormat)
  ? new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })
  : null;

const escapeHtml = (value) => value
  ? String(value).replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m] || m))
  : '';

const parseDate = (value) => {
  if (!value) return null;
  const iso = value.includes('T') ? value : value.replace(' ', 'T');
  const date = new Date(/Z$/i.test(iso) ? iso : `${iso}Z`);
  return Number.isNaN(date.getTime()) ? null : date;
};

const formatDateTime = (date, { fallback = 'Flexible timing' } = {}) => {
  if (!date) return fallback;
  try {
    return date.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
  } catch (err) {
    logger.warn?.('rides:format_datetime_failed', err);
    return date.toISOString();
  }
};

const relativeTime = (date) => {
  if (!date || !rtf) return '';
  const diff = date.getTime() - Date.now();
  const minutes = Math.round(diff / 60000);
  const hours = Math.round(diff / 3600000);
  const days = Math.round(diff / 86400000);
  if (Math.abs(minutes) < 60) return rtf.format(minutes, 'minute');
  if (Math.abs(hours) < 48) return rtf.format(hours, 'hour');
  return rtf.format(days, 'day');
};

const SIX_HOURS_MS = 6 * 60 * 60 * 1000;

const isExpired = (item) => {
  const now = Date.now();
  const rideEnd = parseDate(item?.ride_end_datetime || '');
  if (rideEnd) {
    return rideEnd.getTime() < now - SIX_HOURS_MS;
  }
  const rideStart = parseDate(item?.ride_datetime || '');
  if (rideStart) {
    return rideStart.getTime() < now - SIX_HOURS_MS;
  }
  return false;
};

const buildContactLinks = (item) => {
  const visibility = item.contact_visibility || {};
  if (!visibility.visible) {
    const message = visibility.reason || 'Contact becomes visible after a ride is accepted.';
    return `<span class="text-secondary">${escapeHtml(message)}</span>`;
  }
  const parts = [];
  if (item.phone) {
    parts.push(`<a class="d-inline-flex align-items-center gap-1 text-decoration-none" href="tel:${encodeURIComponent(item.phone)}"><i class="bi bi-telephone-outbound"></i>${escapeHtml(item.phone)}</a>`);
  }
  if (item.whatsapp) {
    const wa = String(item.whatsapp).replace(/\D+/g, '');
    if (wa) {
      parts.push(`<a class="d-inline-flex align-items-center gap-1 text-decoration-none" target="_blank" rel="noopener" href="https://wa.me/${wa}"><i class="bi bi-whatsapp"></i>WhatsApp</a>`);
    }
  }
  if (!parts.length) return '<span class="text-secondary">No contact details provided yet.</span>';
  return parts.join('');
};

const setLiveStatus = (online, label) => {
  if (!liveBadge) return;
  liveBadge.textContent = label;
  liveBadge.className = `badge rounded-pill ${online ? 'text-bg-success' : 'text-bg-secondary'}`;
};

const setLoading = (loading) => {
  if (!skeleton || !list) return;
  if (loading) {
    skeleton.classList.remove('d-none');
    list.classList.add('d-none');
    emptyState?.classList.add('d-none');
    resultsMeta?.classList.add('invisible');
  } else {
    skeleton.classList.add('d-none');
    list.classList.remove('d-none');
    resultsMeta?.classList.remove('invisible');
  }
};

const setRefreshing = (refreshing) => {
  if (!refreshBtn) return;
  refreshBtn.disabled = refreshing;
  if (refreshSpinner) {
    refreshSpinner.classList.toggle('d-none', !refreshing);
  }
};

const showError = (message) => {
  if (!errorAlert) return;
  errorAlert.textContent = message;
  errorAlert.classList.remove('d-none');
};

const hideError = () => {
  if (!errorAlert) return;
  errorAlert.classList.add('d-none');
};

const updateSummary = (items) => {
  const total = items.length;
  const offers = items.filter((item) => item.type === 'offer').length;
  const requests = total - offers;

  if (totalCountEl) totalCountEl.textContent = String(total);
  if (offersCountEl) offersCountEl.textContent = String(offers);
  if (requestsCountEl) requestsCountEl.textContent = String(requests);
  if (resultsMeta) {
    resultsMeta.innerHTML = total
      ? `<span class="fw-semibold">${total}</span> ride${total === 1 ? '' : 's'} available right now`
      : 'No rides match your filters yet.';
  }
  if (lastUpdatedEl) {
    try {
      lastUpdatedEl.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch (err) {
      lastUpdatedEl.textContent = new Date().toISOString();
    }
  }
};

const renderActiveFilters = () => {
  if (!activeFilters) return;
  const chips = [];
  if (filterType && filterType.value) {
    const label = filterType.options[filterType.selectedIndex]?.textContent || 'Filtered';
    chips.push(`<button type="button" class="filter-chip" data-action="remove-type"><i class="bi bi-funnel"></i>${escapeHtml(label)}<i class="bi bi-x-circle ms-1"></i></button>`);
  }
  const search = (searchBox?.value || '').trim();
  if (search) {
    chips.push(`<button type="button" class="filter-chip" data-action="remove-search"><i class="bi bi-search"></i>${escapeHtml(search)}<i class="bi bi-x-circle ms-1"></i></button>`);
  }
  const sort = sortOrder && sortOrder.value === 'latest';
  if (sort) {
    chips.push(`<span class="filter-chip"><i class="bi bi-arrow-down-up"></i>Latest first</span>`);
  }

  if (!chips.length) {
    activeFilters.innerHTML = '<span class="text-secondary">Showing every open ride.</span>';
    return;
  }

  activeFilters.innerHTML = `
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="text-secondary">Active filters:</span>
      ${chips.join('')}
      <button type="button" class="btn btn-link btn-sm p-0" data-action="clear-all">Clear filters</button>
    </div>`;
};

const sortItems = (items) => {
  const order = sortOrder?.value || 'soonest';
  const sorted = [...items];
  sorted.sort((a, b) => {
    const dateA = parseDate(a.ride_datetime || a.ride_end_datetime || a.created_at);
    const dateB = parseDate(b.ride_datetime || b.ride_end_datetime || b.created_at);
    const tsA = dateA ? dateA.getTime() : Infinity;
    const tsB = dateB ? dateB.getTime() : Infinity;
    return order === 'latest' ? tsB - tsA : tsA - tsB;
  });
  return sorted;
};

const render = (items) => {
  if (!list) return;
  list.innerHTML = '';

  if (!items.length) {
    emptyState?.classList.remove('d-none');
    return;
  }
  emptyState?.classList.add('d-none');

  items.forEach((item) => {
    const cls = item.type === 'request' ? 'request' : 'offer';
    const rideStart = parseDate(item.ride_datetime || '');
    const rideEnd = parseDate(item.ride_end_datetime || '');
    const createdAt = parseDate(item.created_at || '');
    const startLabel = rideStart ? escapeHtml(formatDateTime(rideStart, { fallback: '' })) : '';
    const endLabel = rideEnd ? escapeHtml(formatDateTime(rideEnd, { fallback: '' })) : '';
    const scheduleSegments = [];
    if (rideStart) {
      scheduleSegments.push(`<span><i class="bi bi-calendar-event me-1"></i>Starts ${startLabel}</span>`);
    }
    if (rideEnd) {
      scheduleSegments.push(`<span><i class="bi bi-flag me-1"></i>Ends ${endLabel}</span>`);
    }
    if (!scheduleSegments.length) {
      scheduleSegments.push('<span><i class="bi bi-calendar-event me-1"></i>Flexible timing</span>');
    }
    const scheduleHtml = scheduleSegments.join('');
    const seatsLabel = (item.package_only || item.seats === 0)
      ? 'Package only'
      : `${item.seats} seat${item.seats === 1 ? '' : 's'}`;
    const ownerName = item.owner_display || 'Community member';
    const ownerHtml = item.user_id
      ? `<a class="fw-semibold text-decoration-none" href="/user.php?id=${item.user_id}">${escapeHtml(ownerName)}</a>`
      : `<span class="fw-semibold">${escapeHtml(ownerName)}</span>`;
    const isOwn = meId && item.user_id && Number(item.user_id) === meId;
    const showAccept = item.status === 'open' && (!item.user_id || Number(item.user_id) !== meId);
    const note = item.note ? `<div class="text-body mt-2"><i class="bi bi-chat-dots me-2 text-primary"></i>${escapeHtml(item.note)}</div>` : '';

    const card = document.createElement('article');
    card.className = `ride-card card shadow-sm ${cls} ${isOwn ? 'border-primary-subtle' : ''}`;
    card.dataset.rideId = String(item.id);
    if (pendingAcceptIntent && Number(pendingAcceptIntent.rideId) === Number(item.id)) {
      card.classList.add('shadow-lg', 'border-success');
    }
    card.innerHTML = `
      <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-4">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
              <span class="ride-pill ${cls}"><i class="bi ${cls === 'request' ? 'bi-hand-index-thumb' : 'bi-steering-wheel'}"></i>${item.type === 'offer' ? 'Offer' : 'Request'}</span>
              <span class="text-secondary small">by ${ownerHtml}${isOwn ? ' · <span class="badge text-bg-primary-subtle text-primary">Your post</span>' : ''}</span>
            </div>
            <div class="fw-semibold fs-5 text-primary mb-2">${escapeHtml(item.from_text)} <span class="text-body-secondary">→</span> ${escapeHtml(item.to_text)}</div>
            <div class="ride-meta d-flex flex-wrap text-secondary small">
              ${scheduleHtml}
              <span><i class="bi bi-people me-1"></i>${seatsLabel}</span>
              ${createdAt ? `<span><i class="bi bi-clock-history me-1"></i>Posted ${relativeTime(createdAt) || createdAt.toLocaleDateString()}</span>` : ''}
            </div>
            ${note}
          </div>
          <div class="ride-actions text-md-end">
            <div class="contact-links mb-3">${buildContactLinks(item)}</div>
            ${showAccept ? `<button class="btn btn-success" data-accept="${item.id}"><i class="bi bi-check2-circle me-1"></i>Accept ride</button>` : ''}
          </div>
        </div>
      </div>`;

    const btn = card.querySelector('[data-accept]');
    if (btn) {
      btn.addEventListener('click', () => {
        const rideId = Number(btn.getAttribute('data-accept'));
        if (!rideId) return;
        const confirmMessage = meId
          ? 'Accept this ride?'
          : 'You need to log in or sign up to accept this ride. Continue?';
        if (!confirm(confirmMessage)) return;
        acceptRide(rideId, btn);
      });
    }

    list.appendChild(card);
  });
};

const acceptRide = async (rideId, btn = null) => {
  if (!rideId) return;

  if (!meId) {
    rememberAcceptIntent(rideId);
    const loginUrl = new URL('/login.php', window.location.origin);
    loginUrl.searchParams.set('acceptRide', String(rideId));
    window.location.href = loginUrl.toString();
    return;
  }

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Accepting…';
  }

  try {
    const res = await fetch(`${API}/match_create.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ ride_id: rideId, csrf: window.CSRF_TOKEN || '' })
    });
    const data = await res.json().catch(() => ({ ok: false }));
    if (!res.ok || !data.ok) {
      throw new Error(data.error || 'Unable to accept ride');
    }
    alert('Accepted! Once complete, remember to confirm the ride.');
    clearAcceptIntent();
    pendingAcceptIntent = null;
    await fetchRides({ showLoading: false, reason: 'accept' });
  } catch (err) {
    logger.error('rides:accept_failed', err, { rideId });
    alert(`Error accepting ride: ${err?.message || err}`);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Accept ride';
    }
  }
};

const maybePromptPendingAccept = () => {
  if (!meId) return;
  if (!pendingAcceptIntent || !pendingAcceptIntent.rideId) {
    const params = new URLSearchParams(window.location.search);
    const qpRide = Number(params.get('acceptRide') || params.get('accept') || 0) || 0;
    if (qpRide) {
      pendingAcceptIntent = { rideId: qpRide, ts: Date.now() };
      rememberAcceptIntent(qpRide);
      clearAcceptQueryParam();
    }
    if (!pendingAcceptIntent) return;
  }

  const rideId = Number(pendingAcceptIntent.rideId);
  if (!rideId) {
    clearAcceptIntent();
    pendingAcceptIntent = null;
    return;
  }

  const ride = rawItems.find((item) => Number(item.id) === rideId);
  if (!ride || ride.status !== 'open') {
    clearAcceptIntent();
    pendingAcceptIntent = null;
    return;
  }

  if (ride.user_id && Number(ride.user_id) === meId) {
    clearAcceptIntent();
    pendingAcceptIntent = null;
    return;
  }

  if (acceptPromptShownFor === rideId) return;
  acceptPromptShownFor = rideId;

  const message = `You wanted to accept the ride from ${ride.from_text} to ${ride.to_text}. Accept it now?`;
  if (confirm(message)) {
    const btn = list?.querySelector(`[data-accept="${rideId}"]`);
    acceptRide(rideId, btn || null);
  } else {
    clearAcceptIntent();
    pendingAcceptIntent = null;
  }
};

const fetchRides = async ({ showLoading = true, reason = 'manual' } = {}) => {
  if (isFetching) return;
  isFetching = true;

  if (showLoading) {
    setLoading(true);
  } else {
    setRefreshing(true);
  }

  const params = new URLSearchParams();
  if (filterType && filterType.value) params.set('type', filterType.value);
  const q = (searchBox?.value || '').trim();
  if (q) params.set('q', q);

  try {
    const res = await fetch(`${API}/ride_list.php?${params.toString()}`, { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({ ok: false, items: [] }));
    if (!res.ok || !data.ok) {
      throw new Error(data.error || 'Failed to load rides');
    }
    rawItems = Array.isArray(data.items) ? data.items.filter((item) => !isExpired(item)) : [];
    render(sortItems(rawItems));
    maybePromptPendingAccept();
    updateSummary(rawItems);
    hideError();
  } catch (err) {
    logger.error('rides:fetch_failed', err, { reason, params: Object.fromEntries(params.entries()) });
    showError('We couldn\'t load rides right now. Please try again shortly.');
  } finally {
    if (showLoading) {
      setLoading(false);
    } else {
      setRefreshing(false);
    }
    renderActiveFilters();
    isFetching = false;
  }
};

const scheduleAutoRefresh = () => {
  if (autoRefreshTimer) clearInterval(autoRefreshTimer);
  autoRefreshTimer = setInterval(() => {
    if (!document.hidden) fetchRides({ showLoading: false, reason: 'interval' });
  }, 60000);
};

const initEvents = () => {
  refreshBtn?.addEventListener('click', () => fetchRides({ showLoading: false, reason: 'refresh-button' }));

  filterType?.addEventListener('change', () => {
    renderActiveFilters();
    fetchRides({ reason: 'filter-change' });
  });

  sortOrder?.addEventListener('change', () => {
    renderActiveFilters();
    render(sortItems(rawItems));
  });

  if (searchBox) {
    let timer = null;
    searchBox.addEventListener('input', () => {
      renderActiveFilters();
      clearTimeout(timer);
      timer = setTimeout(() => fetchRides({ reason: 'search' }), 350);
    });
  }

  activeFilters?.addEventListener('click', (event) => {
    const target = event.target.closest('[data-action]');
    if (!target) return;
    const action = target.dataset.action;
    if (action === 'remove-type' && filterType) {
      filterType.value = '';
      renderActiveFilters();
      fetchRides({ reason: 'filter-remove-type' });
    } else if (action === 'remove-search' && searchBox) {
      searchBox.value = '';
      renderActiveFilters();
      fetchRides({ reason: 'filter-remove-search' });
    } else if (action === 'clear-all') {
      if (filterType) filterType.value = '';
      if (searchBox) searchBox.value = '';
      if (sortOrder) sortOrder.value = 'soonest';
      renderActiveFilters();
      render(sortItems(rawItems));
      fetchRides({ reason: 'filter-clear' });
    }
  });

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      fetchRides({ showLoading: false, reason: 'visibility' });
      scheduleAutoRefresh();
    }
  });
};

const initSocket = () => {
  if (typeof io !== 'function') {
    setLiveStatus(false, 'Offline');
    return;
  }
  try {
    const socket = io({ path: '/socket.io', transports: ['websocket', 'polling'], timeout: 20000 });
    socket.on('connect', () => setLiveStatus(true, 'Live'));
    socket.on('disconnect', () => setLiveStatus(false, 'Offline'));
    socket.on('connect_error', (err) => {
      logger.warn?.('rides:socket_connect_error', err);
      setLiveStatus(false, 'Retrying…');
    });
    socket.on('ride:new', () => fetchRides({ showLoading: false, reason: 'live-update' }));
  } catch (err) {
    logger.error('rides:socket_init_failed', err);
    setLiveStatus(false, 'Offline');
  }
};

const init = () => {
  setLoading(true);
  initEvents();
  renderActiveFilters();
  scheduleAutoRefresh();
  initSocket();
  fetchRides({ showLoading: true, reason: 'initial' });
};

init();
