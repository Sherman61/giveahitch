import logger from './utils/logger.js';

const threadsList = document.getElementById('threadsList');
const threadsEmpty = document.getElementById('threadsEmpty');
const refreshThreadsBtn = document.getElementById('refreshThreads');
const chatHeader = document.getElementById('chatHeader');
const chatStatus = document.getElementById('chatStatus');
const messagesArea = document.getElementById('messagesArea');
const messagesList = document.getElementById('messagesList');
const messagesEmpty = document.getElementById('messagesEmpty');
const messageForm = document.getElementById('messageForm');
const messageInput = document.getElementById('messageInput');
const messageAlert = document.getElementById('messageFormAlert');

const state = {
  meId: Number(window.ME_USER_ID || 0) || null,
  csrf: window.CSRF_TOKEN || '',
  apiBase: typeof window.API_BASE === 'string' ? window.API_BASE : '/api',
  threads: [],
  activeUserId: window.INITIAL_TARGET_ID ? Number(window.INITIAL_TARGET_ID) : null,
  activeThreadId: null,
  activeOtherUser: null,
  messaging: { allowed: false, reason: '' },
  messages: [],
  socket: null,
  socketAuthed: false,
};

const escapeHtml = (value) => (value === null || value === undefined)
  ? ''
  : String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

const summarise = (value, max = 80) => {
  if (!value) return '';
  const str = String(value).trim();
  return str.length > max ? `${str.slice(0, max - 1)}…` : str;
};

const parseDate = (value) => {
  if (!value) return null;
  const iso = value.includes('T') ? value : value.replace(' ', 'T');
  const date = new Date(/Z$/i.test(iso) ? iso : `${iso}Z`);
  return Number.isNaN(date.getTime()) ? null : date;
};

const formatRelative = (value) => {
  const date = parseDate(value);
  if (!date) return '';
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.round(diffMs / 60000);
  if (Math.abs(diffMins) < 1) return 'just now';
  if (Math.abs(diffMins) < 60) return `${Math.abs(diffMins)} min${Math.abs(diffMins) === 1 ? '' : 's'} ago`;
  const diffHours = Math.round(diffMs / 3600000);
  if (Math.abs(diffHours) < 24) return `${Math.abs(diffHours)} hour${Math.abs(diffHours) === 1 ? '' : 's'} ago`;
  return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
};

const clearMessageAlert = () => {
  if (!messageAlert) return;
  messageAlert.classList.add('d-none');
  messageAlert.textContent = '';
};

const showMessageAlert = (text) => {
  if (!messageAlert) return;
  messageAlert.classList.remove('d-none');
  messageAlert.textContent = text;
};

const scrollMessagesToBottom = () => {
  if (!messagesArea) return;
  requestAnimationFrame(() => {
    messagesArea.scrollTop = messagesArea.scrollHeight;
  });
};

const renderThreads = () => {
  if (!threadsList) return;
  if (!state.threads.length) {
    threadsEmpty?.classList.remove('d-none');
  } else {
    threadsEmpty?.classList.add('d-none');
  }

  const items = state.threads.map((thread) => {
    const other = thread.other_user || {};
    const last = thread.last_message || null;
    const isActive = state.activeThreadId === thread.id || state.activeUserId === other.id;
    const unread = Number(thread.unread_count || 0);
    const bodyPreview = last ? summarise(last.body || '') : 'No messages yet';
    const timestamp = last?.created_at || thread.last_message_at || thread.updated_at || thread.created_at;
    return `
      <button type="button" class="list-group-item list-group-item-action conversation-item ${isActive ? 'active' : ''}" data-thread-id="${thread.id}" data-user-id="${other.id || ''}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-semibold">${escapeHtml(other.display_name || 'Member')}</div>
            <div class="text-secondary small">${escapeHtml(bodyPreview || '')}</div>
          </div>
          <div class="text-end small">
            ${timestamp ? `<div class="text-secondary">${escapeHtml(formatRelative(timestamp))}</div>` : ''}
            ${unread > 0 ? `<span class="badge rounded-pill text-bg-primary mt-1">${unread}</span>` : ''}
          </div>
        </div>
      </button>`;
  });

  threadsList.innerHTML = items.join('');
};

const renderMessages = () => {
  if (!messagesList) return;
  if (!state.messages.length) {
    messagesEmpty?.classList.remove('d-none');
  } else {
    messagesEmpty?.classList.add('d-none');
  }

  const bubbles = state.messages.map((msg) => {
    const mine = msg.sender_user_id === state.meId;
    const classes = ['message-bubble'];
    if (mine) classes.push('me');
    const dt = parseDate(msg.created_at || '');
    const created = dt ? escapeHtml(dt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })) : '';
    return `
      <div class="message-row ${mine ? 'align-items-end' : 'align-items-start'}">
        <div class="${classes.join(' ')}">${escapeHtml(msg.body || '')}</div>
        <div class="message-meta">${created}</div>
      </div>`;
  });

  messagesList.innerHTML = bubbles.join('');
  scrollMessagesToBottom();
};

const updateComposerState = () => {
  if (!messageForm || !messageInput) return;
  const allowed = !!(state.messaging?.allowed);
  messageInput.disabled = !allowed;
  const submitButton = messageForm.querySelector('button[type="submit"]');
  if (submitButton) submitButton.disabled = !allowed;
  if (!allowed) {
    messageInput.placeholder = state.messaging?.reason ? state.messaging.reason : 'You cannot send a message to this member.';
  } else {
    messageInput.placeholder = 'Type a message';
  }
};

const renderConversationHeader = () => {
  if (!chatHeader) return;
  const other = state.activeOtherUser;
  if (!other) {
    chatHeader.innerHTML = `
      <h2 class="h5 mb-0">Select a conversation</h2>
      <div class="text-secondary small" id="chatStatus">Pick someone from the left to start messaging.</div>`;
    return;
  }

  const reason = state.messaging?.reason || '';
  const statusText = state.messaging?.allowed
    ? 'You can send a message using the box below.'
    : (reason || 'Messaging is disabled for this member.');
  chatHeader.innerHTML = `
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
      <div>
        <h2 class="h5 mb-0">Chat with ${escapeHtml(other.display_name || 'Member')}</h2>
        ${other.username ? `<div class="text-secondary">@${escapeHtml(other.username)}</div>` : ''}
      </div>
      ${state.messaging?.allowed
        ? ''
        : `<span class="badge text-bg-secondary">Messaging restricted</span>`}
    </div>
    <div class="text-secondary small" id="chatStatus">${escapeHtml(statusText)}</div>`;
};

const upsertThread = (thread) => {
  if (!thread || !thread.id) return;
  const otherId = thread.other_user?.id;
  const existingIndex = state.threads.findIndex((t) => t.id === thread.id);
  if (existingIndex >= 0) {
    state.threads.splice(existingIndex, 1);
  } else {
    // If we didn't find by id but user matches, remove that entry.
    const byUser = state.threads.findIndex((t) => t.other_user?.id === otherId);
    if (byUser >= 0) {
      state.threads.splice(byUser, 1);
    }
  }
  state.threads.unshift(thread);
};

const fetchThreads = async ({ keepSelection = true } = {}) => {
  try {
    const res = await fetch(`${state.apiBase}/messages.php`, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Unable to load conversations');
    const data = await res.json();
    if (!data?.ok) throw new Error(data?.error || 'Unable to load conversations');
    state.threads = Array.isArray(data.threads) ? data.threads : [];
    if (keepSelection && state.activeUserId) {
      const match = state.threads.find((t) => t.other_user?.id === state.activeUserId);
      state.activeThreadId = match?.id ?? state.activeThreadId;
    }
    renderThreads();
  } catch (err) {
    logger.error('messages:fetch_threads_failed', err);
  }
};

const fetchConversation = async (userId) => {
  if (!userId) return;
  try {
    clearMessageAlert();
    const res = await fetch(`${state.apiBase}/messages.php?user_id=${encodeURIComponent(userId)}`, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Unable to load conversation');
    const data = await res.json();
    if (!data?.ok) throw new Error(data?.error || 'Unable to load conversation');

    state.activeUserId = userId;
    state.activeOtherUser = data.other_user || null;
    state.messaging = data.messaging || { allowed: false, reason: '' };
    state.messages = Array.isArray(data.messages) ? data.messages : [];
    state.activeThreadId = data.thread?.id || null;

    if (data.thread) {
      upsertThread(data.thread);
    }

    renderThreads();
    renderConversationHeader();
    renderMessages();
    updateComposerState();
  } catch (err) {
    logger.error('messages:fetch_conversation_failed', err);
    showMessageAlert(err.message || 'Unable to load conversation.');
  }
};

const sendMessage = async (body) => {
  if (!state.activeUserId) {
    showMessageAlert('Select someone to message first.');
    return;
  }
  try {
    const payload = {
      csrf: state.csrf,
      recipient_id: state.activeUserId,
      body,
    };
    const res = await fetch(`${state.apiBase}/messages.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!res.ok || !data?.ok) {
      const reason = data?.reason || data?.error || 'Unable to send message.';
      throw new Error(reason);
    }

    if (data.thread) {
      upsertThread(data.thread);
      state.activeThreadId = data.thread.id;
      renderThreads();
    }

    if (data.message) {
      state.messages.push(data.message);
      renderMessages();
    } else {
      await fetchConversation(state.activeUserId);
    }
    clearMessageAlert();
    messageInput.value = '';
    updateComposerState();
  } catch (err) {
    logger.error('messages:send_failed', err);
    showMessageAlert(err.message || 'Unable to send message.');
  }
};

const handleThreadClick = (event) => {
  const button = event.target.closest('.conversation-item');
  if (!button) return;
  const userId = Number(button.dataset.userId || 0);
  if (!userId) return;
  fetchConversation(userId);
};

const authenticateSocket = (socket) => {
  if (!socket || !window.WS_AUTH) return;
  try {
    socket.emit('auth', window.WS_AUTH, (response) => {
      if (!response || !response.ok) {
        logger.warn('messages:socket_auth_failed', response);
        return;
      }
      state.socketAuthed = true;
    });
  } catch (err) {
    logger.warn('messages:socket_auth_error', err);
  }
};

const applyThreadFromPayload = (payload) => {
  if (!payload) return null;
  const fromSender = payload.thread_for_sender;
  const fromRecipient = payload.thread_for_recipient;
  if (payload.sender_id === state.meId) {
    return fromSender || fromRecipient || null;
  }
  return fromRecipient || fromSender || null;
};

const handleSocketMessage = (payload) => {
  if (!payload?.target_user_ids || !Array.isArray(payload.target_user_ids)) return;
  if (!payload.target_user_ids.includes(state.meId)) return;

  const thread = applyThreadFromPayload(payload);
  if (thread) {
    upsertThread(thread);
    renderThreads();
  }

  const message = payload.message;
  if (!message) return;
  const otherId = payload.sender_id === state.meId ? payload.recipient_id : payload.sender_id;
  if (state.activeUserId === otherId) {
    state.messages.push(message);
    renderMessages();
    if (payload.sender_id !== state.meId) {
      fetchConversation(state.activeUserId);
    }
  }
};

const initSocket = () => {
  if (typeof io !== 'function') return;
  try {
    const options = { path: '/socket.io', transports: ['websocket', 'polling'] };
    const wsUrl = typeof window.WS_URL === 'string' && window.WS_URL ? window.WS_URL : undefined;
    const socket = wsUrl ? io(wsUrl, options) : io(options);
    state.socket = socket;

    socket.on('connect', () => {
      authenticateSocket(socket);
    });

    socket.on('dm:new', (payload) => {
      handleSocketMessage(payload);
    });

    socket.on('connect_error', (err) => {
      logger.warn('messages:socket_connect_error', err);
    });
  } catch (err) {
    logger.error('messages:socket_init_failed', err);
  }
};

if (threadsList) {
  threadsList.addEventListener('click', handleThreadClick);
}

if (refreshThreadsBtn) {
  refreshThreadsBtn.addEventListener('click', () => fetchThreads({ keepSelection: true }));
}

if (messageForm) {
  messageForm.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!state.messaging?.allowed) {
      updateComposerState();
      return;
    }
    const body = messageInput.value.trim();
    if (!body) {
      showMessageAlert('Type a message first.');
      return;
    }
    sendMessage(body);
  });
}

const init = async () => {
  await fetchThreads({ keepSelection: true });
  if (state.activeUserId) {
    await fetchConversation(state.activeUserId);
  } else {
    renderConversationHeader();
    updateComposerState();
  }
  initSocket();
};

init();
