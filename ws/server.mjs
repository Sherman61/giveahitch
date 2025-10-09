// server.mjs
import express from 'express';
import http from 'http';
import { Server as SocketIOServer } from 'socket.io';
import cors from 'cors';
import process from 'process';

// --- config (env overridable) ---
const ORIGIN   = process.env.ORIGIN || 'https://hitch.shiyaswebsite.com';
const WS_HOST  = process.env.WS_HOST || '127.0.0.1';
const WS_PORT  = parseInt(process.env.WS_PORT  || '4001', 10); // Socket.IO server
const HK_HOST  = process.env.WS_HOOK_HOST || '127.0.0.1';
const HK_PORT  = parseInt(process.env.WS_HOOK_PORT || '4002', 10); // internal hook
const SECRET   = process.env.WS_BROADCAST_SECRET || 'CHANGE_ME_SUPER_SECRET';

// --- Socket.IO HTTP server ---
const app = express();
const server = http.createServer(app);

const io = new SocketIOServer(server, {
  path: '/socket.io',
  cors: {
    origin: [ORIGIN],
    methods: ['GET', 'POST'],
    credentials: true
  },
  transports: ['websocket', 'polling'],
  allowEIO3: false
});

io.on('connection', (socket) => {
  console.log(`[io] connect ${socket.id} from ${socket.handshake.address || 'n/a'}`);
  socket.on('disconnect', (reason) => {
    console.log(`[io] disconnect ${socket.id} (${reason})`);
  });
});

// tiny health check for the WS app
app.get('/healthz', (_req, res) => res.json({ ok: true, role: 'ws' }));

server.listen(WS_PORT, WS_HOST, () => {
  console.log(`[ws] listening on ${WS_HOST}:${WS_PORT} (origin allowed: ${ORIGIN})`);
});

// --- Internal broadcast hook (HTTP) ---
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

// health check for the hook app
hook.get('/healthz', (_req, res) => res.json({ ok: true, role: 'hook' }));

hook.listen(HK_PORT, HK_HOST, () => {
  console.log(`[hook] listening on ${HK_HOST}:${HK_PORT}`);
});

// --- graceful shutdown ---
const bye = (sig) => () => {
  console.log(`[signal] ${sig} → shutting down`);
  server.close(() => {
    console.log('[ws] closed');
    process.exit(0);
  });
  setTimeout(() => process.exit(0), 5000).unref();
};
['SIGINT', 'SIGTERM'].forEach(s => process.on(s, bye(s)));
