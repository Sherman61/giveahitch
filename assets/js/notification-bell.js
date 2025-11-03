import logger from './utils/logger.js';

const badge = document.getElementById('notificationsBadge');
const userId = Number(window.ME_USER_ID || 0);
const API_BASE = typeof window.API_BASE === 'string' ? window.API_BASE : '/api';

if (!badge || !userId) {
  logger.debug?.('notifications:bell_init_skip', { hasBadge: !!badge, userId });
} else {
  const state = {
    unread: 0,
    socket: null,
    socketAuthed: false,
    loading: false,
  };

  const updateBadge = (count) => {
    state.unread = Math.max(0, Number.isFinite(count) ? Number(count) : 0);
    if (state.unread > 0) {
      badge.classList.remove('d-none');
      badge.textContent = state.unread > 99 ? '99+' : String(state.unread);
    } else {
      badge.classList.add('d-none');
      badge.textContent = '0';
    }
  };

  const fetchSummary = async () => {
    if (state.loading) return;
    state.loading = true;
    try {
      const resp = await fetch(`${API_BASE}/notifications.php?summary=1`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const data = await resp.json();
      if (data && typeof data.unread_count === 'number') {
        updateBadge(data.unread_count);
      }
    } catch (err) {
      logger.warn?.('notifications:summary_fetch_failed', err);
    } finally {
      state.loading = false;
    }
  };

  const emitLocalEvent = (payload) => {
    try {
      const event = new CustomEvent('notifications:new', { detail: payload });
      window.dispatchEvent(event);
    } catch (err) {
      logger.warn?.('notifications:event_dispatch_failed', err);
    }
  };

  const authenticateSocket = (socket) => {
    const auth = window.WS_AUTH || null;
    const token = auth && typeof auth.token === 'string' ? auth.token : null;
    if (!token) {
      state.socketAuthed = false;
      return;
    }
    try {
      socket.emit('auth', { token }, (response) => {
        if (response && response.ok) {
          state.socketAuthed = true;
        } else {
          state.socketAuthed = false;
          logger.warn?.('notifications:socket_auth_failed', response);
        }
      });
    } catch (err) {
      logger.warn?.('notifications:socket_auth_error', err);
      state.socketAuthed = false;
    }
  };

  const initSocket = () => {
    if (state.socket || typeof io !== 'function') {
      return;
    }
    const options = { path: '/socket.io', transports: ['websocket', 'polling'] };
    const wsUrl = typeof window.WS_URL === 'string' && window.WS_URL !== '' ? window.WS_URL : undefined;
    const socket = wsUrl ? io(wsUrl, options) : io(options);
    state.socket = socket;

    socket.on('connect', () => {
      logger.info?.('notifications:socket_connected', { id: socket.id });
      authenticateSocket(socket);
    });

    socket.on('disconnect', (reason) => {
      logger.info?.('notifications:socket_disconnected', { reason });
      state.socketAuthed = false;
    });

    socket.on('notification:new', (payload) => {
      logger.debug?.('notifications:event_received', payload);
      if (payload && typeof payload.unread_count === 'number') {
        updateBadge(payload.unread_count);
      }
      emitLocalEvent(payload);
    });

    socket.on('connect_error', (err) => {
      logger.warn?.('notifications:socket_connect_error', err);
    });
  };

  fetchSummary();
  initSocket();

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      fetchSummary();
    }
  });

  window.addEventListener('notifications:count', (event) => {
    const detail = event.detail || {};
    const count = typeof detail.unread === 'number' ? detail.unread : Number(detail);
    if (Number.isFinite(count)) {
      updateBadge(count);
    }
  });
}
 
