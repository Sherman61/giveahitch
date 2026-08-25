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

async function intakeApi(action, body = {}) {
  const response = await fetch(`${config.internalWebsiteUrl}/api/internal/whatsapp_intake.php`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Bridge-Token': config.internalApiToken }, body: JSON.stringify({ action, ...body }) });
  const data = await response.json();
  if (!response.ok || !data.ok) throw new Error(data.error || 'Intake service error');
  return data;
}

function parseRideText(text) {
  const value = text.trim();
  const type = /\b(offer(?:ing)?|drive|available)\b/i.test(value) ? 'offer' : /\b(looking|need|request)\b/i.test(value) ? 'request' : null;
  const route = value.match(/(?:from\s+)?([\p{L} .'-]{2,}?)\s*(?:→|->|to)\s*([\p{L} .'-]{2,})/iu);
  const numbers = value.match(/\+?[\d()\s-]{7,}/g) || [];
  return { type, from_text: route?.[1]?.trim() || null, to_text: route?.[2]?.trim() || null, note: value, phone: numbers[0]?.trim() || null, whatsapp: numbers[1]?.trim() || null };
}

async function dm(jid, text) { await socket.sendMessage(jid, { text }); }

async function handleGroupIntake(message) {
  const suggested = parseRideText(message.text);
  const result = await intakeApi('intake', { ride: { ...message, ...suggested } });
  if (result.mode === 'manual') { await dm(message.senderJid, 'Thanks — your ride was sent privately to the admin team for review. They may contact you if details are needed.'); return; }
  if (!result.complete) { await dm(message.senderJid, 'I can help finish your ride privately. Reply with: LOOKING or OFFERING, then “From City to City”, plus a phone or WhatsApp number.'); return; }
  await dm(message.senderJid, `Ride preview:\n${formatRide({ ...suggested })}\n\nReply CONFIRM to post it, or CANCEL to stop.`);
}

function formatRide(ride) {
  const contact = (ride.whatsapp || ride.phone || '').replace(/\D/g, '');
  return [`🚗 *${ride.type === 'offer' ? 'RIDE OFFER' : 'RIDE REQUEST'}*`, `From: ${ride.from_text}`, `To: ${ride.to_text}`, ride.note ? `Note: ${ride.note}` : null, ride.phone ? `Call: ${ride.phone}` : null, contact ? `WhatsApp: https://wa.me/${contact}` : null].filter(Boolean).join('\n');
}

async function handlePrivateIntake(message) {
  const command = message.text.trim().toUpperCase();
  if (!['CONFIRM', 'CANCEL'].includes(command)) return;
  const pending = await intakeApi('pending_for_sender', { senderJid: message.senderJid });
  const intake = pending.intake;
  if (!intake) { await dm(message.senderJid, 'I do not have a pending ride for you. Post a ride request in Test rides first.'); return; }
  if (command === 'CANCEL') { await intakeApi('cancel', { id: intake.id }); await dm(message.senderJid, 'Your ride request was cancelled.'); return; }
  if (pending.mode !== 'automatic') { await dm(message.senderJid, 'Your ride is awaiting admin review.'); return; }
  const messageId = await publishRideToWhatsApp({ ...intake, groupJid: intake.source_group_jid });
  await intakeApi('mark_posted', { id: intake.id, messageId });
  await dm(message.senderJid, 'Your formatted ride has been posted to Test rides.');
}

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
    if (!normalized.isGroup) { await handlePrivateIntake(normalized); continue; }
    if (!config.trackedGroupJids.includes(normalized.groupJid)) continue;
    if (normalized.isGroup) normalized.groupName = await groupNameFor(normalized.groupJid);

    if (normalized.unsupportedType) {
      logger.info({ messageId: normalized.messageId, chatJid: normalized.chatJid, type: normalized.unsupportedType }, 'Ignoring unsupported message type');
      continue;
    }

    dashboard.addGroupMessage(normalized);
    await handleGroupIntake(normalized);
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
  const groupJid = config.trackedGroupJids.includes(ride.groupJid) ? ride.groupJid : config.trackedGroupJids[0];
  if (!socket || !groupJid) throw new Error('WhatsApp is not connected');
  const sent = await socket.sendMessage(groupJid, { text: formatRide(ride) });
  return sent.key.id;
}
