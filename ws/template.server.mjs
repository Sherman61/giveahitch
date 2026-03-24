// ws/template.server.mjs
// This template mirrors the production-ready implementation in ws/server.mjs.
// Copy this file to server.mjs (or compare side-by-side) to create a custom
// websocket server instance.  The real server includes full auth validation,
// broadcast hooks, and graceful shutdown logic.  See ws/server.mjs for the
// authoritative version.

import 'dotenv/config';
import express from 'express';
import http from 'http';
import { Server as SocketIOServer } from 'socket.io';
import crypto from 'crypto';
import process from 'process';

// ---------- Env helpers ----------
function getEnvArray(name, def = []) {
  const v = process.env[name];
  if (!v) return def;
  return v.split(',').map(s => s.trim()).filter(Boolean);
}
function getEnv(name, def = '') {
  const v = process.env[name];
  return (v === undefined || v === null || v === '') ? def : v;
}
function getEnvInt(name, def) {
  const n = parseInt(getEnv(name, ''), 10);
  return Number.isFinite(n) ? n : def;
}

// ---------- Config ----------
const WS_ORIGINS = getEnvArray('WS_ORIGINS', [ getEnv('ORIGIN', 'https://glitchahitch.com') ]);
const WS_HOST    = getEnv('WS_HOST', '127.0.0.1');
const WS_PORT    = getEnvInt('WS_PORT', 4001);

const HK_HOST    = getEnv('WS_HOOK_HOST', '127.0.0.1');
const HK_PORT    = getEnvInt('WS_HOOK_PORT', 4002);

const SECRET     = getEnv('WS_BROADCAST_SECRET');

// ---------- Socket.IO HTTP server ----------
const app = express();
const server = http.createServer(app);

const io = new SocketIOServer(server, {
  path: '/socket.io',
  cors: {
    origin: (origin, cb) => {
      if (!origin || WS_ORIGINS.includes(origin)) return cb(null, true);
      return cb(new Error(`CORS blocked: ${origin}`));
    },
    methods: ['GET', 'POST'],
    credentials: true,
  },
  transports: ['websocket','polling'],
  allowEIO3: false,
});

const socketsByUserId = new Map();

function onlineUserIds() {
  return Array.from(socketsByUserId.entries())
    .filter(([, sockets]) => sockets && sockets.size > 0)
    .map(([userId]) => Number(userId))
    .filter((userId) => Number.isFinite(userId) && userId > 0);
}

function broadcastPresence(userId, online) {
  const normalizedUserId = Number(userId);
  if (!Number.isFinite(normalizedUserId) || normalizedUserId <= 0) return;
  io.emit('dm:presence', {
    user_id: normalizedUserId,
    online: !!online,
    timestamp: new Date().toISOString(),
  });
}

function registerAuthenticatedSocket(socket, userId) {
  const normalizedUserId = Number(userId);
  if (!Number.isFinite(normalizedUserId) || normalizedUserId <= 0) return false;
  const previousUserId = Number(socket.data.userId || 0);
  if (previousUserId && previousUserId !== normalizedUserId) {
    unregisterAuthenticatedSocket(socket);
  }

  let sockets = socketsByUserId.get(normalizedUserId);
  const wasOffline = !sockets || sockets.size === 0;
  if (!sockets) {
    sockets = new Set();
    socketsByUserId.set(normalizedUserId, sockets);
  }
  sockets.add(socket.id);
  socket.data.userId = normalizedUserId;
  socket.join(`user:${normalizedUserId}`);

  if (wasOffline) {
    broadcastPresence(normalizedUserId, true);
  }
  return true;
}

function unregisterAuthenticatedSocket(socket) {
  const userId = Number(socket.data.userId || 0);
  if (!Number.isFinite(userId) || userId <= 0) return;

  const sockets = socketsByUserId.get(userId);
  if (!sockets) return;

  sockets.delete(socket.id);
  if (sockets.size === 0) {
    socketsByUserId.delete(userId);
    broadcastPresence(userId, false);
  }
}

function verifyToken(token) {
  if (!token || typeof token !== 'string' || !SECRET) return null;
  let decoded;
  try {
    decoded = Buffer.from(token, 'base64').toString('utf8');
  } catch (err) {
    return null;
  }
  const parts = decoded.split('.');
  if (parts.length !== 3) return null;
  const [userIdStr, expiresStr, signature] = parts;
  const userId = parseInt(userIdStr, 10);
  const expires = parseInt(expiresStr, 10);
  if (!Number.isFinite(userId) || userId <= 0 || !Number.isFinite(expires)) return null;
  if (expires < Math.floor(Date.now() / 1000)) return null;

  const expected = crypto
    .createHmac('sha256', SECRET)
    .update(`${userId}.${expires}`)
    .digest('hex');
  const expectedBuf = Buffer.from(expected, 'utf8');
  const providedBuf = Buffer.from(signature, 'utf8');
  if (expectedBuf.length !== providedBuf.length) return null;
  if (!crypto.timingSafeEqual(expectedBuf, providedBuf)) {
    return null;
  }
  return { userId, expires };
}

io.on('connection', (socket) => {
  console.log(`[io] connect ${socket.id} origin=${socket.handshake.headers.origin || 'n/a'} ip=${socket.handshake.address || 'n/a'}`);

  socket.on('auth', (payload = {}, ack) => {
    const respond = typeof ack === 'function' ? ack : () => {};
    const token = payload.token || payload;
    const info = verifyToken(token);
    if (!info) {
      respond({ ok: false });
      return;
    }
    registerAuthenticatedSocket(socket, info.userId);
    respond({
      ok: true,
      userId: info.userId,
      online_user_ids: onlineUserIds(),
    });
  });

  socket.on('dm:typing', (payload = {}) => {
    const senderId = socket.data.userId;
    if (!senderId) return;
    const recipientId = Number(payload.recipient_id || payload.user_id || 0);
    if (!Number.isFinite(recipientId) || recipientId <= 0) return;
    const threadId = Number(payload.thread_id || 0);
    const typingPayload = {
      sender_id: senderId,
      recipient_id: recipientId,
      thread_id: Number.isFinite(threadId) && threadId > 0 ? threadId : null,
      typing: !!payload.typing,
      timestamp: new Date().toISOString(),
    };
    socket.to(`user:${recipientId}`).emit('dm:typing', typingPayload);
  });

  socket.on('disconnect', (reason) => {
    unregisterAuthenticatedSocket(socket);
    console.log(`[io] disconnect ${socket.id} (${reason})`);
  });
});

app.get('/healthz', (_req, res) => res.json({ ok: true, role: 'ws', origins: WS_ORIGINS }));

server.listen(WS_PORT, WS_HOST, () => {
  console.log(`[ws] listening on ${WS_HOST}:${WS_PORT}`);
  console.log(`[ws] allowed origins: ${WS_ORIGINS.join(', ') || '(none)'}`);
});

// ---------- Internal broadcast hook (HTTP) ----------
const hook = express();
hook.use(express.json());

hook.post('/broadcast', (req, res) => {
  const key = req.get('X-WS-SECRET') || '';
  if (key !== SECRET) return res.status(403).json({ ok: false, error: 'Forbidden' });

  const { event, payload, rooms } = req.body || {};
  if (!event) return res.status(400).json({ ok: false, error: 'Missing event' });

  if (Array.isArray(rooms) && rooms.length > 0) {
    rooms
      .map((room) => (typeof room === 'string' ? room : null))
      .filter(Boolean)
      .forEach((room) => io.to(room).emit(event, payload));
  } else {
    io.emit(event, payload);
  }
  res.json({ ok: true });
});

hook.get('/healthz', (_req, res) => res.json({ ok: true, role: 'hook' }));

hook.listen(HK_PORT, HK_HOST, () => {
  console.log(`[hook] listening on ${HK_HOST}:${HK_PORT}`);
});

// ---------- graceful shutdown ----------
const bye = (sig) => () => {
  console.log(`[signal] ${sig} → shutting down`);
  server.close(() => {
    console.log('[ws] closed');
    process.exit(0);
  });
  setTimeout(() => process.exit(0), 5000).unref();
};
['SIGINT', 'SIGTERM'].forEach(s => process.on(s, bye(s)));

export { app, server, io, hook };
