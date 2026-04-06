import type { Message, MessageThread, ServerMessage, ServerThread } from '@/api/messages';

export type ChatConnectionState = 'idle' | 'connecting' | 'connected' | 'disconnected' | 'error';

export interface WsAuthPayload {
  userId: number;
  token: string;
}

export interface WsAuthResponse {
  ok: true;
  ws_auth: WsAuthPayload;
  ws_url: string;
}

export interface SocketAuthAck {
  ok: boolean;
  userId?: number;
  rooms?: string[];
  online_user_ids?: number[];
}

export interface ChatConnectionStatus {
  state: ChatConnectionState;
  authenticated: boolean;
  presenceSnapshotLoaded?: boolean;
  error?: string;
}

export interface DmNewPayload {
  thread_id: number;
  message: ServerMessage;
  sender_id: number;
  recipient_id: number;
  target_user_ids: number[];
  thread_for_sender?: ServerThread | null;
  thread_for_recipient?: ServerThread | null;
  client_ref?: string | null;
}

export interface DmTypingPayload {
  sender_id: number;
  recipient_id: number;
  thread_id?: number | null;
  typing: boolean;
  timestamp?: string;
}

export interface DmPresencePayload {
  user_id: number;
  online: boolean;
  last_seen_at?: string | null;
}

export interface DmReadMessagePayload {
  id: number;
  read_at: string | null;
}

export interface DmReadPayload {
  thread_id: number;
  reader_id: number;
  recipient_id: number;
  message_ids: number[];
  messages?: DmReadMessagePayload[];
}

export interface DmDeletePayload {
  thread_id?: number | null;
  deleted_message_ids: number[];
  initiator_id: number;
  reason?: string;
  target_user_ids: number[];
  thread_for_sender?: ServerThread | null;
  thread_for_recipient?: ServerThread | null;
}

export interface ChatIncomingMessage {
  thread?: MessageThread;
  message: Message;
  otherUserId?: number;
  clientId?: string;
}

export interface ChatMessagesRead {
  userId: number;
  messageIds: number[];
  readAt: string;
}

export interface ChatMessagesDeleted {
  thread?: MessageThread;
  deletedMessageIds: number[];
  otherUserId?: number;
}

export interface ChatSocketEventMap {
  connection: ChatConnectionStatus;
  'dm:new': DmNewPayload;
  'dm:typing': DmTypingPayload;
  'dm:presence': DmPresencePayload;
  'dm:read': DmReadPayload;
  'dm:delete': DmDeletePayload;
}
