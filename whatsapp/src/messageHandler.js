import { getContentType } from '@whiskeysockets/baileys';

const WRAPPER_TYPES = new Set([
  'ephemeralMessage',
  'viewOnceMessage',
  'viewOnceMessageV2',
  'viewOnceMessageV2Extension',
  'documentWithCaptionMessage',
]);

function unwrapMessage(message) {
  let content = message;
  let type = getContentType(content);

  while (type && WRAPPER_TYPES.has(type) && content[type]?.message) {
    content = content[type].message;
    type = getContentType(content);
  }

  return { content, type };
}

function timestampToSeconds(timestamp) {
  if (timestamp === undefined || timestamp === null) return Math.floor(Date.now() / 1000);
  const value = Number(timestamp.toString());
  return Number.isFinite(value) ? value : Math.floor(Date.now() / 1000);
}

/**
 * Converts one Baileys message into the stable shape consumed by this app.
 * Returns `unsupportedType` so callers can report unsupported messages without
 * serializing the raw (and often very large) Baileys object.
 */
export function parseIncomingMessage(waMessage) {
  const { key, message, messageTimestamp, pushName } = waMessage;
  const { content, type } = unwrapMessage(message || {});
  const isGroup = key.remoteJid?.endsWith('@g.us') ?? false;
  let text = '';

  switch (type) {
    case 'conversation':
      text = content.conversation || '';
      break;
    case 'extendedTextMessage':
      // This includes replies/quoted text. The reply body itself is in `text`.
      text = content.extendedTextMessage?.text || '';
      break;
    case 'imageMessage':
      text = content.imageMessage?.caption || '';
      break;
    case 'videoMessage':
      text = content.videoMessage?.caption || '';
      break;
    default:
      break;
  }

  return {
    messageId: key.id || null,
    chatJid: key.remoteJid || null,
    groupJid: isGroup ? key.remoteJid : null,
    isGroup,
    senderJid: isGroup ? (key.participant || waMessage.participant || null) : key.remoteJid || null,
    senderName: pushName || null,
    groupName: null,
    text,
    timestamp: timestampToSeconds(messageTimestamp),
    unsupportedType: text || ['conversation', 'extendedTextMessage', 'imageMessage', 'videoMessage'].includes(type)
      ? null
      : (type || 'unknown'),
  };
}

export function isRelevantIncomingMessage(waMessage) {
  const jid = waMessage.key?.remoteJid;
  return Boolean(
    jid
      && !waMessage.key?.fromMe
      && !jid.endsWith('@status')
      && !jid.endsWith('@broadcast')
      && waMessage.message,
  );
}
