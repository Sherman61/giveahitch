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
const typingIndicator = document.getElementById('typingIndicator');

const TYPING_IDLE_TIMEOUT_MS = 4000;
const TYPING_DISPLAY_TIMEOUT_MS = 6000;
const TYPING_BROADCAST_THROTTLE_MS = 1200;
const POLL_THREADS_INTERVAL_MS = 15000;
const POLL_CONVERSATION_INTERVAL_MS = 6000;
const MESSAGE_DELETE_WINDOW_MS = 30000;

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
  typingByUser: new Map(),
  typingTimers: new Map(),
  selfTyping: { active: false, timeout: null, lastSentAt: 0 },
  pendingMessages: new Map(),
  polling: { active: false, threadsTimer: null, conversationTimer: null },
  fetchingThreads: false,
  fetchingConversation: new Set(),
  deleteExpiryTimers: new Map(),
  clearingConversation: false,
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

const messageKey = (value) => {
  if (value === null || value === undefined) return null;
  return String(value);
};

const clearAllDeleteTimers = () => {
  state.deleteExpiryTimers.forEach((timer) => clearTimeout(timer));
  state.deleteExpiryTimers.clear();
};

const canDeleteMessageForEveryone = (msg) => {
  if (!msg || Number(msg.sender_user_id) !== state.meId) return false;
  const numericId = Number(msg.id);
  if (!Number.isFinite(numericId) || numericId <= 0) return false;
  const created = parseDate(msg.created_at || '');
  if (!created) return false;
  const ageMs = Date.now() - created.getTime();
  return ageMs <= MESSAGE_DELETE_WINDOW_MS;
};

const scheduleDeleteExpiryCheck = (msg, key) => {
  if (!key || !msg) return;
  const created = parseDate(msg.created_at || '');
  if (!created) return;
  const expiryAt = created.getTime() + MESSAGE_DELETE_WINDOW_MS;
  const now = Date.now();
  if (expiryAt <= now) {
    const existing = state.deleteExpiryTimers.get(key);
    if (existing) {
      clearTimeout(existing);
      state.deleteExpiryTimers.delete(key);
    }
    return;
  }
  if (state.deleteExpiryTimers.has(key)) {
    return;
  }
  const delay = Math.max(50, expiryAt - now + 50);
  const timer = setTimeout(() => {
    state.deleteExpiryTimers.delete(key);
    renderMessages();
  }, delay);
  state.deleteExpiryTimers.set(key, timer);
};

const getChatStatusElement = () => {
  if (!chatHeader) return null;
  return chatHeader.querySelector('#chatStatus');
};

const computeConversationStatus = () => {
  if (!state.activeOtherUser) {
    return { text: 'Pick someone from the left to start messaging.', highlight: false };
  }

  const otherId = Number(state.activeOtherUser.id || 0);
  if (otherId && state.typingByUser.has(otherId)) {
    const name = state.activeOtherUser.display_name || 'Member';
    return { text: `${name} is typing…`, highlight: true };
  }

  const allowed = !!(state.messaging?.allowed);
  const reason = state.messaging?.reason || '';
  const text = allowed
    ? 'You can send a message using the box below.'
    : (reason || 'Messaging is disabled for this member.');

  return { text, highlight: false };
};

const updateConversationStatusText = () => {
  const statusEl = getChatStatusElement();
  if (!statusEl) return;
  const { text, highlight } = computeConversationStatus();
  statusEl.textContent = text;
  statusEl.classList.toggle('text-primary', highlight);
  statusEl.classList.toggle('text-secondary', !highlight);
};

const clearTypingTimerForUser = (userId) => {
  const timer = state.typingTimers.get(userId);
  if (timer) {
    clearTimeout(timer);
    state.typingTimers.delete(userId);
  }
};

const updateTypingIndicator = () => {
  if (!typingIndicator) return;
  const other = state.activeOtherUser;
  if (!other) {
    typingIndicator.classList.add('d-none');
    typingIndicator.textContent = '';
    return;
  }

  const otherId = Number(other.id || 0);
  if (otherId && state.typingByUser.has(otherId)) {
    typingIndicator.classList.remove('d-none');
    const name = other.display_name || 'Member';
    typingIndicator.textContent = `${name} is typing…`;
  } else {
    typingIndicator.classList.add('d-none');
    typingIndicator.textContent = '';
  }
};

const setTypingForUser = (userId, isTyping) => {
  if (!userId) return;
  const currentlyTyping = state.typingByUser.has(userId);
  if (isTyping) {
    if (currentlyTyping) {
      state.typingByUser.set(userId, Date.now());
      clearTypingTimerForUser(userId);
      const timeout = setTimeout(() => {
        state.typingByUser.delete(userId);
        state.typingTimers.delete(userId);
        updateTypingIndicator();
        updateConversationStatusText();
        renderThreads();
      }, TYPING_DISPLAY_TIMEOUT_MS);
      state.typingTimers.set(userId, timeout);
      return;
    }
    state.typingByUser.set(userId, Date.now());
    clearTypingTimerForUser(userId);
    const timeout = setTimeout(() => {
      state.typingByUser.delete(userId);
      state.typingTimers.delete(userId);
      updateTypingIndicator();
      updateConversationStatusText();
      renderThreads();
    }, TYPING_DISPLAY_TIMEOUT_MS);
    state.typingTimers.set(userId, timeout);
  } else {
    if (!currentlyTyping) return;
    state.typingByUser.delete(userId);
    clearTypingTimerForUser(userId);
  }
  updateTypingIndicator();
  updateConversationStatusText();
  renderThreads();
};

const emitTyping = (isTyping) => {
  if (!state.socket || !state.socketAuthed || !state.activeUserId) return;
  try {
    state.socket.emit('dm:typing', {
      thread_id: state.activeThreadId,
      recipient_id: state.activeUserId,
      typing: !!isTyping,
    });
  } catch (err) {
    logger.warn('messages:typing_emit_failed', err);
  }
};

const stopSelfTyping = (broadcast = true) => {
  if (state.selfTyping.timeout) {
    clearTimeout(state.selfTyping.timeout);
    state.selfTyping.timeout = null;
  }
  if (state.selfTyping.active) {
    state.selfTyping.active = false;
    if (broadcast) emitTyping(false);
  }
  state.selfTyping.lastSentAt = 0;
};

const handleComposerTyping = () => {
  if (!state.messaging?.allowed) return;
  const now = Date.now();
  if (!state.selfTyping.active || (now - state.selfTyping.lastSentAt) >= TYPING_BROADCAST_THROTTLE_MS) {
    emitTyping(true);
    state.selfTyping.lastSentAt = now;
  }
  state.selfTyping.active = true;
  if (state.selfTyping.timeout) {
    clearTimeout(state.selfTyping.timeout);
  }
  state.selfTyping.timeout = setTimeout(() => {
    state.selfTyping.timeout = null;
    state.selfTyping.active = false;
    emitTyping(false);
  }, TYPING_IDLE_TIMEOUT_MS);
};

const getMessageNumericId = (value) => {
  const num = Number(value);
  return Number.isFinite(num) ? num : 0;
};

const sortMessagesChronologically = () => {
  state.messages.sort((a, b) => {
    const aDate = parseDate(a?.created_at || '')?.getTime() ?? 0;
    const bDate = parseDate(b?.created_at || '')?.getTime() ?? 0;
    if (aDate !== bDate) {
      return aDate - bDate;
    }
    return getMessageNumericId(a?.id) - getMessageNumericId(b?.id);
  });
};

const renderMessageStatus = (msg) => {
  if (!msg || msg.sender_user_id !== state.meId) return '';
  const pending = !!msg.pending;
  const readAt = msg.read_at ? parseDate(msg.read_at) : null;
  const statusClass = pending ? 'pending' : (readAt ? 'seen' : 'delivered');
  const icon = pending ? 'bi-check2' : 'bi-check2-all';
  const label = pending ? 'Sending' : (readAt ? 'Seen' : 'Delivered');
  const escapedLabel = escapeHtml(label);
  return `<span class="message-status ${statusClass}" title="${escapedLabel}"><i class="bi ${icon}"></i><span class="visually-hidden">${escapedLabel}</span></span>`;
};

const buildMessageMeta = (msg, createdText) => {
  const parts = [];
  if (createdText) {
    parts.push(`<span>${escapeHtml(createdText)}</span>`);
  }
  const status = renderMessageStatus(msg);
  if (status) {
    parts.push(status);
  }
  if (!parts.length) return '';
  if (parts.length === 1) return parts[0];
  return parts.join('<span class="message-meta-sep">•</span>');
};

const buildMessageActions = (msg) => {
  if (!canDeleteMessageForEveryone(msg)) return '';
  const numericId = Number(msg.id);
  if (!Number.isFinite(numericId) || numericId <= 0) return '';
  return `
    <div class="message-actions">
      <button type="button" class="message-action-btn message-action-delete" data-message-id="${numericId}" title="Delete message for everyone">
        <i class="bi bi-trash"></i>
        <span class="visually-hidden">Delete message for everyone</span>
      </button>
    </div>`;
};

const createPendingMessage = (body, clientRef) => {
  if (!clientRef) return;
  const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
  const nowIso = new Date().toISOString();
  const message = {
    id: tempId,
    client_ref: clientRef,
    thread_id: state.activeThreadId,
    sender_user_id: state.meId,
    body,
    created_at: nowIso,
    pending: true,
  };
  state.messages.push(message);
  state.pendingMessages.set(clientRef, tempId);
  sortMessagesChronologically();
  renderMessages();
};

const replacePendingMessage = (clientRef, message) => {
  if (!clientRef) return false;
  const tempId = state.pendingMessages.get(clientRef);
  let replaced = false;
  if (tempId) {
    const index = state.messages.findIndex((m) => m.id === tempId);
    if (index >= 0) {
      state.messages[index] = { ...message, client_ref: clientRef, pending: false };
      replaced = true;
    }
    state.pendingMessages.delete(clientRef);
  } else {
    const index = state.messages.findIndex((m) => m.client_ref === clientRef);
    if (index >= 0) {
      state.messages[index] = { ...message, client_ref: clientRef, pending: false };
      replaced = true;
    }
  }
  return replaced;
};

const removePendingMessage = (clientRef) => {
  if (!clientRef) return false;
  const tempId = state.pendingMessages.get(clientRef);
  if (!tempId) return false;
  const index = state.messages.findIndex((m) => m.id === tempId);
  if (index >= 0) {
    state.messages.splice(index, 1);
  }
  state.pendingMessages.delete(clientRef);
  return true;
};

const upsertMessage = (message) => {
  if (!message || typeof message.id === 'undefined') return false;
  const index = state.messages.findIndex((m) => m.id === message.id);
  if (index >= 0) {
    state.messages[index] = { ...state.messages[index], ...message, pending: false };
  } else {
    state.messages.push({ ...message, pending: false });
  }
  sortMessagesChronologically();
  return true;
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
    const otherTyping = other?.id && state.typingByUser.has(other.id);
    const previewText = otherTyping ? 'Typing…' : bodyPreview;
    const previewClass = otherTyping ? 'text-primary small fw-semibold' : 'text-secondary small';
    const timestamp = last?.created_at || thread.last_message_at || thread.updated_at || thread.created_at;
    return `
      <button type="button" class="list-group-item list-group-item-action conversation-item ${isActive ? 'active' : ''}" data-thread-id="${thread.id}" data-user-id="${other.id || ''}">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-semibold">${escapeHtml(other.display_name || 'Member')}</div>
            <div class="${previewClass}">${escapeHtml(previewText || '')}</div>
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
  sortMessagesChronologically();

  const eligibleKeys = new Set();
  state.messages.forEach((msg) => {
    if (canDeleteMessageForEveryone(msg)) {
      const key = messageKey(msg.id);
      if (key) {
        eligibleKeys.add(key);
        scheduleDeleteExpiryCheck(msg, key);
      }
    }
  });

  state.deleteExpiryTimers.forEach((timer, key) => {
    if (!eligibleKeys.has(key)) {
      clearTimeout(timer);
      state.deleteExpiryTimers.delete(key);
    }
  });

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
    const created = dt ? dt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '';
    const metaHtml = buildMessageMeta(msg, created);
    const metaBlock = metaHtml ? `<div class="message-meta">${metaHtml}</div>` : '';
    const actionsHtml = buildMessageActions(msg);
    const contentClass = mine ? 'message-content message-content-me' : 'message-content message-content-other';
    const rowDataId = escapeHtml(messageKey(msg.id) || '');
    return `
      <div class="message-row ${mine ? 'align-items-end' : 'align-items-start'}" data-message-id="${rowDataId}">
        <div class="${contentClass}">
          ${actionsHtml}
          <div class="${classes.join(' ')}">${escapeHtml(msg.body || '')}</div>
          ${metaBlock}
        </div>
      </div>`;
  });

  messagesList.innerHTML = bubbles.join('');
  scrollMessagesToBottom();
};

const removeMessagesByIds = (ids) => {
  if (!Array.isArray(ids) || !ids.length) return false;
  const keys = new Set(ids.map((id) => messageKey(id)).filter((key) => key));
  if (!keys.size) return false;

  const beforeLength = state.messages.length;
  state.messages = state.messages.filter((msg) => {
    const key = messageKey(msg?.id);
    return !key || !keys.has(key);
  });

  state.pendingMessages.forEach((value, clientRef) => {
    const key = messageKey(value);
    if (key && keys.has(key)) {
      state.pendingMessages.delete(clientRef);
    }
  });

  keys.forEach((key) => {
    const timer = state.deleteExpiryTimers.get(key);
    if (timer) {
      clearTimeout(timer);
      state.deleteExpiryTimers.delete(key);
    }
  });

  const changed = state.messages.length !== beforeLength;
  if (changed) {
    renderMessages();
    updateConversationStatusText();
  }
  return changed;
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
    updateConversationStatusText();
    updateTypingIndicator();
    return;
  }

  const { text: statusText } = computeConversationStatus();
  const controls = [];
  const hasThread = !!state.activeThreadId || state.messages.length > 0;
  if (hasThread) {
    controls.push(`
      <button type="button" class="btn btn-outline-secondary btn-sm" id="clearConversationBtn">
        <i class="bi bi-eraser"></i>
        <span class="d-none d-sm-inline ms-1">Clear my messages</span>
      </button>`);
  }
  if (!state.messaging?.allowed) {
    controls.push('<span class="badge text-bg-secondary">Messaging restricted</span>');
  }
  const controlsHtml = controls.length
    ? `<div class="d-flex align-items-center gap-2 flex-wrap justify-content-md-end">${controls.join('')}</div>`
    : '';
  chatHeader.innerHTML = `
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
      <div>
        <h2 class="h5 mb-0">Chat with ${escapeHtml(other.display_name || 'Member')}</h2>
        ${other.username ? `<div class="text-secondary">@${escapeHtml(other.username)}</div>` : ''}
      </div>
      ${controlsHtml}
    </div>
    <div class="text-secondary small" id="chatStatus">${escapeHtml(statusText)}</div>`;
  updateConversationStatusText();
  updateTypingIndicator();
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

const fetchThreads = async ({ keepSelection = true, skipIfBusy = false } = {}) => {
  if (skipIfBusy && state.fetchingThreads) {
    return;
  }
  state.fetchingThreads = true;
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
  } finally {
    state.fetchingThreads = false;
  }
};

const fetchConversation = async (userId, options = {}) => {
  const targetId = Number(userId);
  if (!Number.isFinite(targetId) || targetId <= 0) return;
  const skipIfBusy = options.skipIfBusy ?? false;
  if (skipIfBusy && state.fetchingConversation.has(targetId)) {
    return;
  }
  try {
    state.fetchingConversation.add(targetId);
    clearMessageAlert();
    const preserveTyping = !!options.preserveTyping;
    const preservePending = !!options.preservePending;
    const pendingSnapshot = preservePending
      ? state.messages.filter((msg) => msg.pending && msg.sender_user_id === state.meId)
      : [];
    if (!preserveTyping) {
      stopSelfTyping();
    }
    const res = await fetch(`${state.apiBase}/messages.php?user_id=${encodeURIComponent(targetId)}`, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('Unable to load conversation');
    const data = await res.json();
    if (!data?.ok) throw new Error(data?.error || 'Unable to load conversation');

    state.pendingMessages.clear();
    clearAllDeleteTimers();
    state.activeUserId = targetId;
    state.activeOtherUser = data.other_user || null;
    state.messaging = data.messaging || { allowed: false, reason: '' };
    state.messages = Array.isArray(data.messages) ? data.messages.map((msg) => ({ ...msg, pending: false })) : [];
    if (preservePending && pendingSnapshot.length) {
      pendingSnapshot.forEach((pendingMsg) => {
        const clone = { ...pendingMsg };
        state.messages.push(clone);
        if (clone.client_ref) {
          state.pendingMessages.set(clone.client_ref, clone.id);
        }
      });
    }
    state.activeThreadId = data.thread?.id || null;

    if (data.thread) {
      upsertThread(data.thread);
    }

    renderThreads();
    renderConversationHeader();
    renderMessages();
    updateComposerState();
    if (state.activeOtherUser?.id) {
      setTypingForUser(Number(state.activeOtherUser.id), false);
    } else {
      updateTypingIndicator();
    }
  } catch (err) {
    logger.error('messages:fetch_conversation_failed', err);
    showMessageAlert(err.message || 'Unable to load conversation.');
  } finally {
    state.fetchingConversation.delete(targetId);
  }
};

const sendMessage = async (body) => {
  if (!state.activeUserId) {
    showMessageAlert('Select someone to message first.');
    return;
  }
  const originalBody = body;
  const clientRef = `msg-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
  stopSelfTyping();
  createPendingMessage(body, clientRef);
  if (messageInput) {
    messageInput.value = '';
  }
  try {
    const payload = {
      csrf: state.csrf,
      recipient_id: state.activeUserId,
      body,
      client_ref: clientRef,
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

    const responseRef = data.client_ref || clientRef;

    if (data.message) {
      const messagePayload = { ...data.message, client_ref: responseRef, pending: false };
      const replaced = replacePendingMessage(responseRef, messagePayload);
      if (!replaced) {
        upsertMessage(messagePayload);
      }
      renderMessages();
    } else {
      const removed = removePendingMessage(responseRef);
      if (removed) {
        renderMessages();
      }
      await fetchConversation(state.activeUserId);
    }
    clearMessageAlert();
    stopSelfTyping(false);
    updateComposerState();
  } catch (err) {
    logger.error('messages:send_failed', err);
    const removed = removePendingMessage(clientRef);
    if (removed) {
      renderMessages();
    }
    if (messageInput) {
      messageInput.value = originalBody;
      messageInput.focus();
    }
    showMessageAlert(err.message || 'Unable to send message.');
  }
};

const deleteMessageById = async (messageId) => {
  const numericId = Number(messageId);
  if (!Number.isFinite(numericId) || numericId <= 0) return;
  if (!state.activeUserId) return;
  const confirmed = window.confirm('Delete this message for everyone? You can only undo a message within 30 seconds.');
  if (!confirmed) return;

  try {
    const res = await fetch(`${state.apiBase}/messages.php`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ csrf: state.csrf, message_id: numericId }),
    });
    const data = await res.json();
    if (!res.ok || !data?.ok) {
      const reason = data?.reason || data?.error || 'Unable to delete message.';
      throw new Error(reason);
    }

    const deletedIds = Array.isArray(data.deleted_message_ids) ? data.deleted_message_ids : [];
    let removed = false;
    if (deletedIds.length) {
      removed = removeMessagesByIds(deletedIds);
    }
    if (!removed) {
      await fetchConversation(state.activeUserId, { preserveTyping: true, preservePending: true, skipIfBusy: true });
    }

    if (data.thread) {
      upsertThread(data.thread);
      renderThreads();
      renderConversationHeader();
    }
    clearMessageAlert();
  } catch (err) {
    logger.error('messages:delete_failed', err);
    showMessageAlert(err.message || 'Unable to delete message.');
  }
};

const clearMyMessages = async () => {
  if (!state.activeUserId || state.clearingConversation) {
    return;
  }

  const hasMine = state.messages.some((msg) => msg.sender_user_id === state.meId);
  if (!hasMine) {
    showMessageAlert('You have no messages to clear in this conversation.');
    return;
  }

  const confirmClear = window.confirm('Clear all messages you have sent in this conversation? This removes them for everyone.');
  if (!confirmClear) return;

  state.clearingConversation = true;
  const clearButton = document.getElementById('clearConversationBtn');
  if (clearButton) {
    clearButton.disabled = true;
  }
  try {
    const res = await fetch(`${state.apiBase}/messages.php`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ csrf: state.csrf, action: 'clear', user_id: state.activeUserId }),
    });
    const data = await res.json();
    if (!res.ok || !data?.ok) {
      const reason = data?.reason || data?.error || 'Unable to clear your messages.';
      throw new Error(reason);
    }

    const deletedIds = Array.isArray(data.deleted_message_ids) ? data.deleted_message_ids : [];
    const removed = deletedIds.length ? removeMessagesByIds(deletedIds) : false;
    if (!removed) {
      await fetchConversation(state.activeUserId, { preserveTyping: true, preservePending: true, skipIfBusy: true });
    }

    if (data.thread) {
      upsertThread(data.thread);
      renderThreads();
    }
    renderConversationHeader();
    clearMessageAlert();
  } catch (err) {
    logger.error('messages:clear_failed', err);
    showMessageAlert(err.message || 'Unable to clear your messages.');
  } finally {
    state.clearingConversation = false;
    if (clearButton) {
      clearButton.disabled = false;
    }
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
        state.socketAuthed = false;
        startPolling('auth_failed');
        return;
      }
      state.socketAuthed = true;
      stopPolling();
      logger.info('messages:socket_auth_ok', {
        rooms: Array.isArray(response.rooms) ? response.rooms : undefined,
      });
    });
  } catch (err) {
    logger.warn('messages:socket_auth_error', err);
    state.socketAuthed = false;
    startPolling('auth_error');
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
  const clientRef = payload.client_ref || null;
  const senderId = Number(payload.sender_id);
  const recipientId = Number(payload.recipient_id);
  const otherId = senderId === state.meId ? recipientId : senderId;
  const otherNumericId = Number.isFinite(otherId) ? otherId : null;

  if (otherNumericId && senderId !== state.meId) {
    setTypingForUser(otherNumericId, false);
  }
  if (otherNumericId && state.activeUserId === otherNumericId) {
    const messagePayload = { ...message, client_ref: clientRef, pending: false };
    let changed = false;
    if (clientRef) {
      changed = replacePendingMessage(clientRef, messagePayload);
    }
    if (!changed) {
      changed = upsertMessage(messagePayload);
    }
    if (changed) {
      renderMessages();
    }
    if (payload.sender_id !== state.meId) {
      fetchConversation(state.activeUserId, { preserveTyping: true, preservePending: true });
    }
  }
};

const handleSocketTyping = (payload) => {
  if (!payload) return;
  const recipientId = Number(payload.recipient_id || 0);
  if (recipientId !== state.meId) return;
  const senderId = Number(payload.sender_id || 0);
  if (!senderId) return;
  setTypingForUser(senderId, !!payload.typing);
};

const handleSocketRead = (payload) => {
  if (!payload) return;
  const readerId = Number(payload.reader_id || 0);
  if (!readerId || readerId !== state.activeUserId) return;
  const messageIds = Array.isArray(payload.message_ids)
    ? payload.message_ids.map((id) => Number(id)).filter((id) => Number.isFinite(id))
    : [];
  if (!messageIds.length) return;

  const readMap = new Map();
  if (Array.isArray(payload.messages)) {
    payload.messages.forEach((msg) => {
      const idNum = Number(msg?.id);
      if (Number.isFinite(idNum)) {
        readMap.set(idNum, msg.read_at || null);
      }
    });
  }

  let changed = false;
  messageIds.forEach((idNum) => {
    const index = state.messages.findIndex((msg) => Number(msg.id) === idNum && msg.sender_user_id === state.meId);
    if (index >= 0) {
      const existing = state.messages[index];
      const nextReadAt = readMap.has(idNum)
        ? readMap.get(idNum)
        : (existing.read_at || new Date().toISOString());
      if (existing.read_at !== nextReadAt || existing.pending) {
        state.messages[index] = { ...existing, read_at: nextReadAt, pending: false };
        changed = true;
      }
    }
  });

  if (changed) {
    renderMessages();
  }
};

const handleSocketDelete = async (payload) => {
  if (!payload?.target_user_ids || !Array.isArray(payload.target_user_ids)) return;
  if (!payload.target_user_ids.includes(state.meId)) return;

  const ids = Array.isArray(payload.deleted_message_ids)
    ? payload.deleted_message_ids.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0)
    : [];
  let removed = false;
  if (ids.length) {
    removed = removeMessagesByIds(ids);
  }

  const thread = applyThreadFromPayload(payload);
  if (thread) {
    upsertThread(thread);
    renderThreads();
  }

  const payloadThreadId = Number(payload.thread_id || 0);
  if (payloadThreadId && payloadThreadId === state.activeThreadId) {
    renderConversationHeader();
    if (!removed) {
      await fetchConversation(state.activeUserId, { preserveTyping: true, preservePending: true, skipIfBusy: true });
    }
  }
};

const stopPolling = () => {
  if (!state.polling.active) return;
  logger.info('messages:polling_stop', {});
  if (state.polling.threadsTimer) {
    clearInterval(state.polling.threadsTimer);
    state.polling.threadsTimer = null;
  }
  if (state.polling.conversationTimer) {
    clearInterval(state.polling.conversationTimer);
    state.polling.conversationTimer = null;
  }
  state.polling.active = false;
};

const startPolling = (reason = 'unknown') => {
  if (state.polling.active) return;
  logger.info('messages:polling_start', { reason });
  state.polling.active = true;
  fetchThreads({ keepSelection: true, skipIfBusy: true });
  if (state.activeUserId) {
    fetchConversation(state.activeUserId, {
      preserveTyping: true,
      preservePending: true,
      skipIfBusy: true,
    });
  }
  state.polling.threadsTimer = setInterval(() => {
    fetchThreads({ keepSelection: true, skipIfBusy: true });
  }, POLL_THREADS_INTERVAL_MS);
  state.polling.conversationTimer = setInterval(() => {
    if (!state.activeUserId) return;
    fetchConversation(state.activeUserId, {
      preserveTyping: true,
      preservePending: true,
      skipIfBusy: true,
    });
  }, POLL_CONVERSATION_INTERVAL_MS);
};

const initSocket = () => {
  if (typeof io !== 'function') {
    startPolling('socket_io_unavailable');
    return;
  }
  try {
    const options = { path: '/socket.io', transports: ['websocket', 'polling'] };
    const wsUrl = typeof window.WS_URL === 'string' && window.WS_URL ? window.WS_URL : undefined;
    const socket = wsUrl ? io(wsUrl, options) : io(options);
    state.socket = socket;

    socket.on('connect', () => {
      logger.info('messages:socket_connected', { id: socket.id || null });
      authenticateSocket(socket);
    });

    socket.on('dm:new', (payload) => {
      handleSocketMessage(payload);
    });

    socket.on('dm:typing', (payload) => {
      handleSocketTyping(payload);
    });

    socket.on('dm:read', (payload) => {
      handleSocketRead(payload);
    });

    socket.on('dm:delete', (payload) => {
      handleSocketDelete(payload);
    });

    socket.on('connect_error', (err) => {
      logger.warn('messages:socket_connect_error', err);
      startPolling('connect_error');
    });

    socket.on('disconnect', (reason) => {
      logger.info('messages:socket_disconnected', { reason });
      state.socketAuthed = false;
      startPolling('disconnect');
    });
  } catch (err) {
    logger.error('messages:socket_init_failed', err);
    startPolling('init_exception');
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

if (messageInput) {
  messageInput.addEventListener('input', () => {
    if (!state.messaging?.allowed) return;
    if (messageInput.value.trim()) {
      handleComposerTyping();
    } else {
      stopSelfTyping();
    }
  });
  messageInput.addEventListener('blur', () => {
    stopSelfTyping();
  });
}

if (messagesList) {
  messagesList.addEventListener('click', (event) => {
    const button = event.target.closest('.message-action-delete');
    if (!button) return;
    event.preventDefault();
    const messageId = Number(button.dataset.messageId || 0);
    if (!Number.isFinite(messageId) || messageId <= 0) return;
    deleteMessageById(messageId);
  });
}

document.addEventListener('click', (event) => {
  const clearBtn = event.target.closest('#clearConversationBtn');
  if (!clearBtn) return;
  event.preventDefault();
  clearMyMessages();
});

const init = async () => {
  await fetchThreads({ keepSelection: true });
  if (state.activeUserId) {
    await fetchConversation(state.activeUserId);
  } else {
    renderConversationHeader();
    updateComposerState();
    updateConversationStatusText();
    updateTypingIndicator();
  }
  if (!window.WS_AUTH) {
    startPolling('no_auth_token');
  }
  initSocket();
};

init();
