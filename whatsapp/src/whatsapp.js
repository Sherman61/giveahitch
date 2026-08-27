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
let workflow = { trigger_mention: true, trigger_all_group: false, trigger_confirm: true, trigger_private_mention: true, bot_mention: '@ridebot', condition_format: true, condition_confirm: true, action_private_dm: true, action_queue: true, action_post_group: true, action_create_ride: true };
function hasBotMention(text) { return text.toLowerCase().includes((workflow.bot_mention || '@ridebot').toLowerCase()); }
function removeBotMention(text) { return text.replace(new RegExp((workflow.bot_mention || '@ridebot').replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'ig'), '').trim(); }

async function intakeApi(action, body = {}) {
  const response = await fetch(`${config.internalWebsiteUrl}/api/internal/whatsapp_intake.php`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Bridge-Token': config.internalApiToken }, body: JSON.stringify({ action, ...body }) });
  const data = await response.json();
  if (!response.ok || !data.ok) throw new Error(data.error || 'Intake service error');
  return data;
}

function parseRideText(text) {
  const value = text.trim();
  const defaults = { looking: 'looking,need,request', offering: 'offering,offer,drive,available', from: 'from', to: 'to', going_to: 'going to,going-to', call: 'call', whatsapp: 'whatsapp', note: 'note' };
  const words = { ...defaults, ...(workflow.keywords || {}) };
  const alternatives = (key) => words[key].split(',').map((item) => item.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).filter(Boolean).join('|');
  const type = new RegExp(`\\b(?:${alternatives('offering')})\\b`, 'i').test(value) ? 'offer' : new RegExp(`\\b(?:${alternatives('looking')})\\b`, 'i').test(value) ? 'request' : null;
  const destination = `(?:${alternatives('to')}|${alternatives('going_to')})`;
  const boundary = `(?=\\s+(?:${alternatives('call')}|${alternatives('whatsapp')}|${alternatives('note')})\\b|$|[,.])`;
  const route = value.match(new RegExp(`\\b(?:${alternatives('from')})\\s+([\\p{L} .'-]{2,}?)\\s+${destination}\\s+([\\p{L} .'-]{2,}?)${boundary}`, 'iu'))
    || value.match(new RegExp(`\\b([\\p{L} .'-]{2,}?)\\s*(?:→|->)\\s*([\\p{L} .'-]{2,}?)(?=\\s*(?:${alternatives('call')}|${alternatives('whatsapp')}|${alternatives('note')})\\b|$|[,.])`, 'iu'));
  const phoneMatch = value.match(new RegExp(`\\b(?:${alternatives('call')})\\s*:?\\s*(\\+?[\\d()\\s-]{7,})(?=\\s*(?:,|\\b(?:${alternatives('whatsapp')}|${alternatives('note')})\\b|$))`, 'i'));
  const whatsappMatch = value.match(new RegExp(`\\b(?:${alternatives('whatsapp')})\\s*:?\\s*(\\+?[\\d()\\s-]{7,})(?=\\s*(?:,|\\b(?:${alternatives('call')}|${alternatives('note')})\\b|$))`, 'i'));
  const allNumbers = [...value.matchAll(/\+?[\d()\s-]{7,}/g)].map((match) => match[0].trim());
  const phone = phoneMatch?.[1].trim() || allNumbers[0] || null;
  const whatsapp = whatsappMatch?.[1].trim() || null;
  const noteMatch = value.match(new RegExp(`\\b(?:${alternatives('note')})\\s*:?\\s*(.+)$`, 'is'));
  const lastContact = Math.max(phoneMatch ? phoneMatch.index + phoneMatch[0].length : -1, whatsappMatch ? whatsappMatch.index + whatsappMatch[0].length : -1);
  const trailingNote = lastContact >= 0 ? value.slice(lastContact).replace(/^[\s,.;:—-]+/, '').trim() : '';
  const note = (noteMatch?.[1] || trailingNote || '').trim() || null;
  return { type, from_text: route?.[1]?.trim() || null, to_text: route?.[2]?.trim() || null, note, phone, whatsapp };
}

async function dm(jid, text) { await socket.sendMessage(jid, { text }); }

async function handleGroupIntake(message) {
  const text = removeBotMention(message.text);
  const suggested = parseRideText(text);
  const result = await intakeApi('intake', { ride: { ...message, text, ...suggested } });
  workflow = result.workflow || workflow;
  const format = workflow.format_help || 'Format: @ridebot Looking from Kingston to Montego Bay call: 876-555-1234 whatsapp: 876-555-9999 note: Need to arrive before 5 PM.';
  if (result.mode === 'manual') { if (workflow.action_private_dm) await dm(message.senderJid, `Status: received — pending admin review.\n\n${format}`); return; }
  if (workflow.condition_format && !result.complete) {
    const missing = [!suggested.type && 'ride type (Looking or Offering)', !suggested.from_text && 'departure city (From)', !suggested.to_text && 'destination (To or Going to)', !suggested.phone && !suggested.whatsapp && 'contact number (Call or WhatsApp)'].filter(Boolean);
    if (workflow.action_private_dm) await dm(message.senderJid, `Status: more details needed.\nMissing: ${missing.join(', ')}.\n\n${format}\n\nPlease send the missing details in a direct reply.`);
    return;
  }
  if (workflow.condition_confirm) { if (workflow.action_private_dm) await dm(message.senderJid, `Status: awaiting your confirmation.\n\nRide preview:\n${formatRide({ ...suggested })}\n\nReply CONFIRM to post it, or CANCEL to stop.`); return; }
  if (workflow.action_post_group) { const messageId = await publishRideToWhatsApp({ ...suggested, groupJid: message.groupJid, source_sender_jid: message.senderJid }); await intakeApi('mark_posted', { id: result.id, messageId, createRide: workflow.action_create_ride }); }
}

function formatRide(ride) {
  const contact = (ride.whatsapp || ride.phone || '').replace(/\D/g, '');
  return [`🚗 *${ride.type === 'offer' ? 'RIDE OFFER' : 'RIDE REQUEST'}*`, `From: ${ride.from_text}`, `To: ${ride.to_text}`, ride.note ? `Note: ${ride.note}` : null, ride.phone ? `Call: ${ride.phone}` : null, contact ? `WhatsApp: https://wa.me/${contact}` : null].filter(Boolean).join('\n');
}

async function handlePrivateIntake(message) {
  const command = removeBotMention(message.text).toUpperCase();
  const requiresTag = workflow.trigger_private_mention && !['CONFIRM', 'CANCEL'].includes(command);
  if (requiresTag && !hasBotMention(message.text)) return;
  if (command === 'START' || command === 'HELP') {
    await dm(message.senderJid, workflow.format_help || 'Send: Looking from City to City call: 1234567890 note: Optional note');
    return;
  }
  if (!['CONFIRM', 'CANCEL'].includes(command)) {
    const text = removeBotMention(message.text);
    const suggested = parseRideText(text);
    const result = await intakeApi('intake', { ride: { ...message, groupJid: config.trackedGroupJids[0], text, ...suggested } });
    workflow = result.workflow || workflow;
    const format = workflow.format_help || 'Send: Looking from City to City call: 1234567890 note: Optional note';
    if (result.mode === 'manual') { if (workflow.action_private_dm) await dm(message.senderJid, `Status: received — pending admin review.\n\n${format}`); return; }
    if (workflow.condition_format && !result.complete) {
      const missing = [!suggested.type && 'ride type (Looking or Offering)', !suggested.from_text && 'departure city (From)', !suggested.to_text && 'destination (To or Going to)', !suggested.phone && !suggested.whatsapp && 'contact number (Call or WhatsApp)'].filter(Boolean);
      if (workflow.action_private_dm) await dm(message.senderJid, `Status: more details needed.\nMissing: ${missing.join(', ')}.\n\n${format}`);
      return;
    }
    if (workflow.condition_confirm) { if (workflow.action_private_dm) await dm(message.senderJid, `Status: awaiting your confirmation.\n\nRide preview:\n${formatRide(suggested)}\n\nReply CONFIRM to post it, or CANCEL to stop.`); return; }
    if (workflow.action_post_group) { const messageId = await publishRideToWhatsApp({ ...suggested, groupJid: config.trackedGroupJids[0], source_sender_jid: message.senderJid }); await intakeApi('mark_posted', { id: result.id, messageId, createRide: workflow.action_create_ride }); }
    return;
  }
  if (!workflow.trigger_confirm) return;
  const pending = await intakeApi('pending_for_sender', { senderJid: message.senderJid });
  const intake = pending.intake;
  workflow = pending.workflow || workflow;
  if (!intake) { await dm(message.senderJid, 'I do not have a pending ride for you. Post a ride request in Test rides first.'); return; }
  if (command === 'CANCEL') { await intakeApi('cancel', { id: intake.id }); await dm(message.senderJid, 'Your ride request was cancelled.'); return; }
  if (pending.mode !== 'automatic') { if (workflow.action_private_dm) await dm(message.senderJid, 'Your ride is awaiting admin review.'); return; }
  if (!workflow.action_post_group) return;
  const messageId = await publishRideToWhatsApp({ ...intake, groupJid: intake.source_group_jid });
  await intakeApi('mark_posted', { id: intake.id, messageId, createRide: workflow.action_create_ride });
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
    if (!workflow.trigger_all_group && workflow.trigger_mention && !hasBotMention(normalized.text)) continue;
    if (!workflow.trigger_mention && !workflow.trigger_all_group) continue;
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
  try { workflow = (await intakeApi('workflow')).workflow || workflow; } catch (error) { logger.warn({ error: error.message }, 'Could not load workflow controls'); }
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
  if (ride.source_sender_jid) {
    try { await dm(ride.source_sender_jid, `Status: posted. Your ride is now live in Test rides.\n\n${formatRide(ride)}`); }
    catch (error) { logger.warn({ error: error.message, senderJid: ride.source_sender_jid }, 'Could not send ride status update'); }
  }
  return sent.key.id;
}

export async function sendDirectWhatsAppNotification(notification) {
  const digits = String(notification.whatsapp || '').replace(/\D/g, '');
  if (!socket || digits.length < 7) throw new Error('No valid WhatsApp number or active connection');
  const sent = await socket.sendMessage(`${digits}@s.whatsapp.net`, { text: `Glitch a Hitch update\n\n${notification.text}` });
  return sent.key.id;
}
