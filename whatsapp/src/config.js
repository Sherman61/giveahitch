import 'dotenv/config';
import path from 'node:path';

function positiveInteger(value, fallback) {
  const parsed = Number.parseInt(value, 10);
  return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : fallback;
}
function jidList(value) { return [...new Set((value || '').split(',').map((jid) => jid.trim()).filter((jid) => jid.endsWith('@g.us')))]; }

export const config = Object.freeze({
  authDir: path.resolve(process.cwd(), process.env.AUTH_DIR || 'auth'),
  pairingPhoneNumber: (process.env.PAIRING_PHONE_NUMBER || '').replace(/\D/g, ''),
  reconnectDelayMs: positiveInteger(process.env.RECONNECT_DELAY_MS, 5_000),
  browserName: process.env.WHATSAPP_BROWSER_NAME || 'Website Bridge',
  host: process.env.HOST || '127.0.0.1',
  port: positiveInteger(process.env.PORT, 3_000),
  trackedGroupJids: jidList(process.env.TRACKED_GROUP_JIDS),
});
