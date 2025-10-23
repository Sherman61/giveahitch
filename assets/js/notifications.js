const getLogger = () => {
  const globalScope = typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : {});
  const existing = globalScope && typeof globalScope.AppLogger === 'object' ? globalScope.AppLogger : null;
  if (existing) {
    return existing;
  }

  const consoleRef = typeof console !== 'undefined' ? console : {
    log() {},
    info() {},
    warn() {},
    error() {},
    debug() {},
  };

  const createHandler = (method, fallback = 'log') => (...args) => {
    const handler = consoleRef[method] || consoleRef[fallback] || (() => {});
    handler.apply(consoleRef, args);
  };

  const fallbackLogger = {
    debug: createHandler('debug'),
    info: createHandler('info'),
    warn: createHandler('warn'),
    error: createHandler('error', 'warn'),
    subscribe: () => () => {},
  };

  if (globalScope && typeof globalScope === 'object') {
    globalScope.AppLogger = fallbackLogger;
  }

  return fallbackLogger;
};

const logger = getLogger();

const listEl = document.getElementById('notificationList');
const emptyEl = document.getElementById('notificationEmpty');
const loadMoreBtn = document.getElementById('loadMore');
const markAllBtn = document.getElementById('markAllRead');
const settingsForm = document.getElementById('notificationSettings');
const settingsSaved = document.getElementById('settingsSaved');
const subtitleEl = document.getElementById('notificationSubtitle');

const API_BASE = typeof window.API_BASE === 'string' ? window.API_BASE : '/api';
const csrfToken = window.CSRF_TOKEN || null;

const bootstrap = window.NOTIFICATIONS_BOOTSTRAP || {};

const state = {
  items: Array.isArray(bootstrap.items) ? bootstrap.items.slice() : [],
  hasMore: !!bootstrap.has_more,
  nextCursor: bootstrap.next_cursor || null,
  unreadCount: Number(bootstrap.unread_count || 0),
  settings: bootstrap.settings || {},
  loadingMore: false,
  savingSettings: false,
  marking: false,
};

const relativeFormatter = (typeof Intl !== 'undefined' && Intl.RelativeTimeFormat)
  ? new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })
  : null;

const formatRelativeTime = (isoString) => {
  if (!isoString) return '';
  try {
    const date = new Date(isoString);
    if (Number.isNaN(date.getTime())) return '';
    if (!relativeFormatter) return date.toLocaleString();
    const diff = (date.getTime() - Date.now()) / 1000;
    const absDiff = Math.abs(diff);
    if (absDiff < 60) return relativeFormatter.format(Math.round(diff), 'second');
    if (absDiff < 3600) return relativeFormatter.format(Math.round(diff / 60), 'minute');
    if (absDiff < 86400 * 2) return relativeFormatter.format(Math.round(diff / 3600), 'hour');
    return relativeFormatter.format(Math.round(diff / 86400), 'day');
  } catch (err) {
    logger.warn('notifications:relative_time_failed', err);
    return '';
  }
};

const escapeHtml = (value) => {
  if (!value && value !== 0) return '';
  return String(value).replace(/[&<>"']/g, (match) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[match] || match));
};

const renderNotification = (item) => {
  const unreadClass = item.is_read ? '' : ' unread';
  const actor = item.actor_display_name ? `<span class="fw-semibold">${escapeHtml(item.actor_display_name)}</span>` : '';
  const when = formatRelativeTime(item.created_at);
  const metaParts = [];
  if (actor) metaParts.push(actor);
  if (when) metaParts.push(escapeHtml(when));
  const meta = metaParts.length ? `<div class="notification-meta">${metaParts.join(' • ')}</div>` : '';
  const body = item.body ? `<p class="mb-0 text-secondary">${escapeHtml(item.body)}</p>` : '';
  const markBtn = item.is_read ? '' : `<button class="btn btn-link btn-sm px-0" data-action="mark-read" data-id="${item.id}" type="button">Mark as read</button>`;
  return `
    <div class="notification-item${unreadClass}" data-id="${item.id}">
      <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
          <h2 class="h6 mb-1">${escapeHtml(item.title)}</h2>
          ${body}
          ${meta}
        </div>
        ${markBtn ? `<div>${markBtn}</div>` : ''}
      </div>
    </div>
  `;
};

const updateSubtitle = () => {
  if (!subtitleEl) return;
  if (state.unreadCount > 0) {
    subtitleEl.textContent = state.unreadCount === 1
      ? 'You have 1 unread notification.'
      : `You have ${state.unreadCount} unread notifications.`;
  } else {
    subtitleEl.textContent = 'Stay up to date with your rides.';
  }
};

const renderList = () => {
  if (!listEl) return;
  if (state.items.length) {
    listEl.innerHTML = state.items.map(renderNotification).join('');
    listEl.classList.remove('d-none');
    emptyEl?.classList.add('d-none');
  } else {
    listEl.innerHTML = '';
    listEl.classList.add('d-none');
    emptyEl?.classList.remove('d-none');
  }
  if (loadMoreBtn) {
    if (state.hasMore) {
      loadMoreBtn.classList.remove('d-none');
      loadMoreBtn.disabled = state.loadingMore;
    } else {
      loadMoreBtn.classList.add('d-none');
    }
  }
  updateSubtitle();
};

const mergeNotification = (incoming) => {
  if (!incoming || typeof incoming !== 'object') return;
  const existingIndex = state.items.findIndex((n) => Number(n.id) === Number(incoming.id));
  if (existingIndex >= 0) {
    state.items[existingIndex] = { ...state.items[existingIndex], ...incoming };
  } else {
    state.items.unshift(incoming);
  }
};

const markNotifications = async ({ ids = [], all = false }) => {
  if (state.marking) return;
  state.marking = true;
  try {
    const payload = { action: 'mark_read' };
    if (all) {
      payload.all = true;
    } else {
      payload.ids = ids;
    }
    const resp = await fetch(`${API_BASE}/notifications.php`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken || '',
      },
      body: JSON.stringify(payload),
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const data = await resp.json();
    if (typeof data.unread_count === 'number') {
      state.unreadCount = data.unread_count;
      window.dispatchEvent(new CustomEvent('notifications:count', { detail: { unread: state.unreadCount } }));
    }
    if (all) {
      state.items = state.items.map((item) => ({ ...item, is_read: true, read_at: item.read_at || new Date().toISOString() }));
    } else if (ids && ids.length) {
      const set = new Set(ids.map((id) => Number(id)));
      state.items = state.items.map((item) => set.has(Number(item.id)) ? { ...item, is_read: true, read_at: item.read_at || new Date().toISOString() } : item);
    }
    renderList();
  } catch (err) {
    logger.warn('notifications:mark_failed', err);
  } finally {
    state.marking = false;
  }
};

const fetchMore = async () => {
  if (!state.hasMore || state.loadingMore) return;
  state.loadingMore = true;
  loadMoreBtn?.setAttribute('aria-busy', 'true');
  try {
    const params = new URLSearchParams();
    params.set('limit', '20');
    if (state.nextCursor) params.set('after', String(state.nextCursor));
    const resp = await fetch(`${API_BASE}/notifications.php?${params.toString()}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const data = await resp.json();
    if (Array.isArray(data.items)) {
      state.items = state.items.concat(data.items);
    }
    state.hasMore = !!data.has_more;
    state.nextCursor = data.next_cursor || null;
    renderList();
  } catch (err) {
    logger.warn('notifications:load_more_failed', err);
  } finally {
    state.loadingMore = false;
    loadMoreBtn?.removeAttribute('aria-busy');
  }
};

const applySettingsToForm = () => {
  if (!settingsForm) return;
  const rideInput = settingsForm.querySelector('input[name="ride_activity"]');
  const matchInput = settingsForm.querySelector('input[name="match_activity"]');
  if (rideInput) rideInput.checked = !!state.settings?.ride_activity;
  if (matchInput) matchInput.checked = !!state.settings?.match_activity;
};

const saveSettings = async () => {
  if (!settingsForm || state.savingSettings) return;
  state.savingSettings = true;
  settingsSaved?.classList.add('d-none');
  const rideInput = settingsForm.querySelector('input[name="ride_activity"]');
  const matchInput = settingsForm.querySelector('input[name="match_activity"]');
  const payload = {
    action: 'settings',
    ride_activity: rideInput && rideInput.checked,
    match_activity: matchInput && matchInput.checked,
  };
  try {
    const resp = await fetch(`${API_BASE}/notifications.php`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrfToken || '',
      },
      body: JSON.stringify(payload),
    });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const data = await resp.json();
    if (data && data.settings) {
      state.settings = data.settings;
      applySettingsToForm();
    }
    if (settingsSaved) {
      settingsSaved.classList.remove('d-none');
      setTimeout(() => settingsSaved.classList.add('d-none'), 2000);
    }
  } catch (err) {
    logger.warn('notifications:settings_failed', err);
  } finally {
    state.savingSettings = false;
  }
};

if (listEl) {
  listEl.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const action = target.getAttribute('data-action');
    if (action === 'mark-read') {
      const id = target.getAttribute('data-id');
      if (id) {
        markNotifications({ ids: [id] });
      }
    }
  });
}

loadMoreBtn?.addEventListener('click', () => fetchMore());
markAllBtn?.addEventListener('click', () => markNotifications({ all: true }));

if (settingsForm) {
  settingsForm.addEventListener('change', () => saveSettings());
}

window.addEventListener('notifications:new', (event) => {
  const detail = event.detail || {};
  if (detail && detail.notification) {
    mergeNotification(detail.notification);
    if (typeof detail.unread_count === 'number') {
      state.unreadCount = detail.unread_count;
    } else if (!detail.notification.is_read) {
      state.unreadCount += 1;
    }
    renderList();
  }
});

window.addEventListener('notifications:count', (event) => {
  const count = Number(event.detail?.unread || 0);
  state.unreadCount = count;
  renderList();
});

applySettingsToForm();
renderList();
