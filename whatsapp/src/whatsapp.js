import fs from 'node:fs/promises';
import makeWASocket, {
  Browsers,
  DisconnectReason,
  fetchLatestBaileysVersion,
  makeCacheableSignalKeyStore,
  useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import pino from 'pino';
import { config } from './config.js';
import { isRelevantIncomingMessage, parseIncomingMessage } from './messageHandler.js';
import { dashboard } from './dashboard.js';

const logger = dashboard.log;
const baileysLogger = pino({ level: 'silent' });
const groupCache = new Map();
let socket;
let reconnectTimer;
let stopping = false;

async function groupNameFor(groupJid) {
  const cached = groupCache.get(groupJid);
  if (cached && cached.expiresAt > Date.now()) return cached.name;

  try {
    const metadata = await socket.groupMetadata(groupJid);
    const name = metadata.subject || null;
    groupCache.set(groupJid, { name, expiresAt: Date.now() + 5 * 60_000 });
    return name;
  } catch (error) {
      logger.warn({ error: error.message, groupJid }, 'Could not retrieve group metadata');
    return null;
  }
}

async function handleMessages({ messages, type }) {
  if (type !== 'notify') return;

  for (const waMessage of messages) {
    if (!isRelevantIncomingMessage(waMessage)) continue;

    const normalized = parseIncomingMessage(waMessage);
    if (!normalized.isGroup || !config.trackedGroupJids.includes(normalized.groupJid)) continue;
    if (normalized.isGroup) normalized.groupName = await groupNameFor(normalized.groupJid);

    if (normalized.unsupportedType) {
      logger.info({ messageId: normalized.messageId, chatJid: normalized.chatJid, type: normalized.unsupportedType }, 'Ignoring unsupported message type');
      continue;
    }

    dashboard.addGroupMessage(normalized);
    logger.info({ messageId: normalized.messageId, groupJid: normalized.groupJid }, 'Incoming tracked group message');
  }
}

function scheduleReconnect() {
  if (stopping || reconnectTimer) return;
  logger.info({ delayMs: config.reconnectDelayMs }, 'Scheduling WhatsApp reconnect');
  reconnectTimer = setTimeout(() => {
    reconnectTimer = undefined;
    startWhatsApp();
  }, config.reconnectDelayMs);
}

export async function startWhatsApp() {
  if (stopping) return;
  await fs.mkdir(config.authDir, { recursive: true, mode: 0o700 });
  const { state, saveCreds } = await useMultiFileAuthState(config.authDir);
  const { version } = await fetchLatestBaileysVersion();
  dashboard.setTrackedGroups(config.trackedGroupJids);

  socket = makeWASocket({
    version,
    auth: { creds: state.creds, keys: makeCacheableSignalKeyStore(state.keys, baileysLogger) },
    logger: baileysLogger,
    browser: Browsers.macOS(config.browserName),
    markOnlineOnConnect: false,
    syncFullHistory: false,
    generateHighQualityLinkPreview: false,
  });

  socket.ev.on('creds.update', saveCreds);
  socket.ev.on('messages.upsert', (update) => {
    handleMessages(update).catch((error) => logger.error({ error: error.message }, 'Message handling failed'));
  });
  socket.ev.on('connection.update', async ({ connection, lastDisconnect, qr }) => {
    if (qr && !config.pairingPhoneNumber) dashboard.setQr(qr).catch((error) => logger.error({ error: error.message }, 'Could not render linking QR code'));

    if (connection) dashboard.setConnection(connection);
    if (connection === 'open') {
      dashboard.setQr(null).catch(() => {});
      logger.info({}, 'WhatsApp connection established');
      for (const jid of config.trackedGroupJids) {
        try {
          const metadata = await socket.groupMetadata(jid);
          const inviteCode = await socket.groupInviteCode(jid);
          dashboard.updateGroup({ jid, name: metadata.subject || jid, inviteUrl: inviteCode ? `https://chat.whatsapp.com/${inviteCode}` : null });
        } catch (error) { logger.warn({ groupJid: jid, error: error.message }, 'Could not load tracked group details'); }
      }
    }

    if (connection === 'close') {
      const statusCode = lastDisconnect?.error?.output?.statusCode || lastDisconnect?.error?.statusCode;
      const loggedOut = statusCode === DisconnectReason.loggedOut;
      logger.warn({ statusCode, loggedOut }, 'WhatsApp connection closed');
      if (loggedOut) {
        logger.error({}, 'Session logged out. Delete the auth directory and link the account again.');
      } else {
        scheduleReconnect();
      }
    }
  });

  if (config.pairingPhoneNumber && !state.creds.registered) {
    try {
      const code = await socket.requestPairingCode(config.pairingPhoneNumber);
      dashboard.setPairingCode(code);
      logger.info({}, 'Pairing code generated; enter it in WhatsApp Linked Devices');
    } catch (error) {
      logger.error({ error: error.message }, 'Could not request pairing code');
    }
  }

  return socket;
}

export function stopWhatsApp() {
  stopping = true;
  if (reconnectTimer) clearTimeout(reconnectTimer);
  socket?.ws?.close();
}

export async function publishRideToWhatsApp(ride) {
  if (!socket || !config.trackedGroupJids[0]) throw new Error('WhatsApp is not connected');
  const contact = (ride.whatsapp || ride.phone || '').replace(/\D/g, '');
  const lines = [
    `🚗 *${ride.type === 'offer' ? 'RIDE OFFER' : 'RIDE REQUEST'}*`,
    `From: ${ride.from_text}`,
    `To: ${ride.to_text}`,
    ride.note ? `Note: ${ride.note}` : null,
    ride.phone ? `Call: ${ride.phone}` : null,
    contact ? `WhatsApp: https://wa.me/${contact}` : null,
  ].filter(Boolean);
  const sent = await socket.sendMessage(config.trackedGroupJids[0], { text: lines.join('\n') });
  return sent.key.id;
}
