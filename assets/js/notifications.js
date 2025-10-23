import logger from './utils/logger.js';

const listEl = document.getElementById('notificationList');
const emptyEl = document.getElementById('notificationEmpty');
const loadMoreBtn = document.getElementById('loadMore');
const markAllBtn = document.getElementById('markAllRead');
const settingsForm = document.getElementById('notificationSettings');
const settingsSaved = document.getElementById('settingsSaved');
const subtitleEl = document.getElementById('notificationSubtitle');
const pushButton = document.querySelector('[data-notification-bell]');
const pushStatusEl = document.querySelector('[data-push-status]');
const pushButtonLabel = pushButton ? pushButton.querySelector('[data-label]') : null;
const pushButtonIcon = pushButton ? pushButton.querySelector('[data-icon]') : null;
const vapidMeta = typeof document !== 'undefined'
  ? document.querySelector('meta[name="vapid-public-key"]')
  : null;
const vapidPublicKey = vapidMeta?.content?.trim() || '';
const pushSupported = typeof window !== 'undefined'
  && typeof Notification !== 'undefined'
  && typeof navigator !== 'undefined'
  && 'serviceWorker' in navigator
  && 'PushManager' in window;

const pushState = {
  supported: pushSupported,
  enabling: false,
  subscribed: false,
  permission: pushSupported && typeof Notification !== 'undefined'
    ? Notification.permission
    : 'denied',
};

let vapidKeyBytes = null;

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

const setPushStatus = (message, tone = 'secondary') => {
  if (!pushStatusEl) return;
  const text = message || '';
  pushStatusEl.textContent = text;
  pushStatusEl.classList.remove('text-secondary', 'text-success', 'text-danger');
  const toneClass = tone === 'success'
    ? 'text-success'
    : tone === 'danger'
      ? 'text-danger'
      : 'text-secondary';
  pushStatusEl.classList.add(toneClass);
  pushStatusEl.classList.toggle('d-none', text === '');
};

const setPushButtonLabel = (text) => {
  if (pushButtonLabel) {
    pushButtonLabel.textContent = text;
  } else if (pushButton) {
    pushButton.textContent = text;
  }
};

const setPushButtonIcon = (icon) => {
  if (!pushButtonIcon) return;
  const name = icon.startsWith('bi-') ? icon : `bi-${icon}`;
  pushButtonIcon.className = `bi ${name}`;
};

const updatePushButtonState = () => {
  if (!pushButton) return;

  pushButton.classList.add('btn', 'btn-sm', 'd-inline-flex', 'align-items-center', 'gap-2');

  if (!pushState.supported) {
    pushButton.disabled = true;
    pushButton.classList.remove('btn-outline-primary', 'btn-success');
    pushButton.classList.add('btn-outline-secondary');
    setPushButtonLabel('Push not supported');
    setPushButtonIcon('bi-slash-circle');
    return;
  }

  const permission = pushState.permission;

  if (pushState.subscribed || permission === 'granted') {
    pushButton.disabled = true;
    pushButton.classList.remove('btn-outline-primary', 'btn-outline-secondary');
    pushButton.classList.add('btn-success');
    setPushButtonLabel('Push notifications on');
    setPushButtonIcon('bi-check2-circle');
    return;
  }

  if (permission === 'denied') {
    pushButton.disabled = true;
    pushButton.classList.remove('btn-outline-primary', 'btn-success');
    pushButton.classList.add('btn-outline-secondary');
    setPushButtonLabel('Push blocked');
    setPushButtonIcon('bi-slash-circle');
    return;
  }

  pushButton.disabled = pushState.enabling;
  pushButton.classList.remove('btn-success', 'btn-outline-secondary');
  pushButton.classList.add('btn-outline-primary');
  setPushButtonLabel(pushState.enabling ? 'Enabling…' : 'Enable push notifications');
  setPushButtonIcon('bi-bell');
};

const urlBase64ToUint8Array = (base64String) => {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = typeof atob === 'function' ? atob(base64) : window.atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i += 1) {
    output[i] = raw.charCodeAt(i);
  }
  return output;
};

const getVapidApplicationServerKey = () => {
  if (!vapidPublicKey) {
    return null;
  }
  if (vapidKeyBytes) {
    return vapidKeyBytes;
  }
  try {
    vapidKeyBytes = urlBase64ToUint8Array(vapidPublicKey);
  } catch (err) {
    vapidKeyBytes = null;
    logger.warn?.('notifications:vapid_key_invalid', err);
  }
  return vapidKeyBytes;
};

const encodeKey = (keyData) => {
  if (!keyData) return '';
  const bytes = keyData instanceof ArrayBuffer ? new Uint8Array(keyData) : keyData;
  let binary = '';
  for (let i = 0; i < bytes.length; i += 1) {
    binary += String.fromCharCode(bytes[i]);
  }
  return typeof btoa === 'function' ? btoa(binary) : window.btoa(binary);
};

const saveSubscription = async (subscription) => {
  if (!subscription) {
    throw new Error('subscription_missing');
  }
  const json = typeof subscription.toJSON === 'function' ? subscription.toJSON() : null;
  const endpoint = json?.endpoint || subscription.endpoint || '';
  const keys = json?.keys || {};
  let p256dh = keys.p256dh || '';
  let auth = keys.auth || '';

  if ((!p256dh || !auth) && typeof subscription.getKey === 'function') {
    try {
      if (!p256dh) {
        const rawP256dh = subscription.getKey('p256dh');
        if (rawP256dh) p256dh = encodeKey(new Uint8Array(rawP256dh));
      }
      if (!auth) {
        const rawAuth = subscription.getKey('auth');
        if (rawAuth) auth = encodeKey(new Uint8Array(rawAuth));
      }
    } catch (err) {
      logger.warn?.('notifications:subscription_key_encode_failed', err);
    }
  }

  if (!endpoint || !p256dh || !auth) {
    throw new Error('subscription_missing_fields');
  }

  const userAgent = typeof navigator !== 'undefined' && navigator.userAgent
    ? navigator.userAgent.slice(0, 255)
    : null;

  const payload = {
    endpoint,
    keys: {
      p256dh,
      auth,
    },
  };
  if (userAgent) {
    payload.ua = userAgent;
  }

  const resp = await fetch(`${API_BASE}/push_subscription.php`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-Token': csrfToken || '',
    },
    body: JSON.stringify(payload),
  });

  if (!resp.ok) {
    let detail = '';
    try {
      const data = await resp.json();
      detail = data?.error || '';
    } catch (err) {
      detail = '';
    }
    throw new Error(`subscription_save_failed:${detail || resp.status}`);
  }
};

const requestNotificationPermission = () => new Promise((resolve) => {
  if (typeof Notification === 'undefined' || typeof Notification.requestPermission !== 'function') {
    resolve('default');
    return;
  }
  let settled = false;
  const finish = (permission) => {
    if (settled) return;
    settled = true;
    resolve(permission);
  };
  try {
    const maybePromise = Notification.requestPermission((permission) => finish(permission));
    if (maybePromise && typeof maybePromise.then === 'function') {
      maybePromise.then(finish).catch(() => finish('denied'));
    }
  } catch (err) {
    logger.warn?.('notifications:permission_request_failed', err);
    finish('denied');
  }
});

const enablePushNotifications = async ({ requestPermission = true, silent = false } = {}) => {
  if (!pushState.supported) {
    return;
  }

  if (!silent) {
    pushState.enabling = true;
    updatePushButtonState();
    setPushStatus('Enabling push notifications…', 'secondary');
  }

  try {
    await navigator.serviceWorker.register('/notification-sw.js');
    const registration = await navigator.serviceWorker.ready;

    let permission = typeof Notification !== 'undefined' ? Notification.permission : 'default';

    if (permission !== 'granted') {
      if (!requestPermission) {
        pushState.permission = permission;
        return;
      }
      permission = await requestNotificationPermission();
    }

    pushState.permission = permission;

    if (permission !== 'granted') {
      if (!silent) {
        const tone = permission === 'denied' ? 'danger' : 'secondary';
        const message = permission === 'denied'
          ? 'Notifications are blocked in your browser settings.'
          : 'You can enable browser notifications to stay up to date.';
        setPushStatus(message, tone);
      }
      return;
    }

    const applicationServerKey = getVapidApplicationServerKey();
    if (!applicationServerKey) {
      throw new Error('missing_vapid_key');
    }

    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey,
      });
    }

    if (!subscription) {
      throw new Error('subscription_unavailable');
    }

    await saveSubscription(subscription);
    pushState.subscribed = true;
    pushState.permission = 'granted';

    if (!silent) {
      setPushStatus('Push notifications are enabled. We will alert you about new activity.', 'success');
    }
  } catch (err) {
    if (err && err.name === 'NotAllowedError') {
      pushState.permission = 'denied';
    }
    pushState.subscribed = false;
    if (!silent) {
      setPushStatus('We could not enable push notifications. Please try again later.', 'danger');
    }
    logger.warn?.('notifications:push_enable_failed', err);
  } finally {
    if (!silent) {
      pushState.enabling = false;
    }
    updatePushButtonState();
  }
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
    logger.warn?.('notifications:relative_time_failed', err);
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
    logger.warn?.('notifications:mark_failed', err);
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
    logger.warn?.('notifications:load_more_failed', err);
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
    logger.warn?.('notifications:settings_failed', err);
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

if (pushButton) {
  updatePushButtonState();
  if (!pushState.supported) {
    setPushStatus('Push notifications are not supported by this browser.', 'danger');
  } else if (pushState.permission === 'denied') {
    setPushStatus('Notifications are blocked in your browser settings.', 'danger');
  } else {
    setPushStatus('');
  }

  pushButton.addEventListener('click', () => {
    if (pushState.subscribed || pushState.enabling) {
      return;
    }
    enablePushNotifications({ requestPermission: true, silent: false });
  });

  if (pushState.supported && pushState.permission === 'granted') {
    enablePushNotifications({ requestPermission: false, silent: true }).catch((err) => {
      logger.warn?.('notifications:push_sync_failed', err);
    });
  }
} else if (!pushState.supported && pushStatusEl) {
  setPushStatus('Push notifications are not supported by this browser.', 'danger');
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
