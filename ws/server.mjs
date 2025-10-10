// ws/server.mjs
import 'dotenv/config';                // loads .env if present
import express from 'express';
import http from 'http';
import { Server as SocketIOServer } from 'socket.io';
import process from 'process';

// ---------- Env helpers ----------
function getEnvArray(name, def = []) {
  const v = process.env[name];
  if (!v) return def;
  // split on commas, trim, filter out empties
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
      // Allow same-origin (no Origin header) and any value in WS_ORIGINS
      if (!origin || WS_ORIGINS.includes(origin)) return cb(null, true);
      return cb(new Error(`CORS blocked: ${origin}`));
    },
    methods: ['GET', 'POST'],
    credentials: true,
  },
  transports: ['websocket','polling'],
  allowEIO3: false,
});

io.on('connection', (socket) => {
  console.log(`[io] connect ${socket.id} origin=${socket.handshake.headers.origin || 'n/a'} ip=${socket.handshake.address || 'n/a'}`);
  socket.on('disconnect', (reason) => {
    console.log(`[io] disconnect ${socket.id} (${reason})`);
  });
});

// tiny health check for the WS app
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

  const { event, payload } = req.body || {};
  if (!event) return res.status(400).json({ ok: false, error: 'Missing event' });

  io.emit(event, payload);
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
 