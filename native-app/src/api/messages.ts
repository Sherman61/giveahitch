import { apiClient, getCsrfToken } from './client';

export interface MessageThread {
  id: number;
  otherUser: {
    id: number;
    displayName?: string | null;
    username?: string | null;
  };
  lastMessageAt?: string | null;
  lastMessage?: Message;
  unreadCount?: number;
}

export interface Message {
  id: number;
  senderId: number;
  body: string;
  createdAt?: string | null;
  readAt?: string | null;
}

export interface MessagingAccess {
  allowed: boolean;
  reason?: string;
  level?: number;
  hasRelationship?: boolean;
}

type ThreadsResponse = {
  ok: boolean;
  threads: ServerThread[];
};

type ServerThread = {
  id: number;
  other_user?: {
    id: number;
    display_name?: string | null;
    username?: string | null;
  };
  last_message_at?: string | null;
  unread_count?: number;
  last_message?: {
    id: number;
    sender_user_id: number;
    body: string;
    created_at: string | null;
    read_at?: string | null;
  };
};

type ThreadResponse = {
  ok: boolean;
  thread: ServerThread | null;
  messages: ServerMessage[];
  messaging: {
    allowed: boolean;
    reason?: string;
    level?: number;
    has_relationship?: boolean;
  };
  other_user?: {
    id: number;
    display_name?: string | null;
    username?: string | null;
  };
};

type ServerMessage = {
  id: number;
  sender_user_id: number;
  body: string;
  created_at: string | null;
  read_at?: string | null;
};

type SendResponse = {
  ok: boolean;
  thread: ServerThread;
  message: ServerMessage;
  recipient?: {
    id: number;
    display_name?: string | null;
    username?: string | null;
  };
};

function mapMessage(row: ServerMessage): Message {
  return {
    id: row.id,
    senderId: row.sender_user_id,
    body: row.body,
    createdAt: row.created_at,
    readAt: row.read_at,
  };
}

function mapThread(row: ServerThread): MessageThread {
  return {
    id: row.id,
    otherUser: {
      id: row.other_user?.id ?? 0,
      displayName: row.other_user?.display_name ?? null,
      username: row.other_user?.username ?? null,
    },
    lastMessageAt: row.last_message_at,
    unreadCount: row.unread_count ?? 0,
    lastMessage: row.last_message
      ? mapMessage({
          id: row.last_message.id,
          sender_user_id: row.last_message.sender_user_id,
          body: row.last_message.body,
          created_at: row.last_message.created_at,
          read_at: row.last_message.read_at,
        })
      : undefined,
  };
}

export async function fetchThreads(): Promise<MessageThread[]> {
  const response = await apiClient.get<ThreadsResponse>('/messages.php');
  if (!response.ok) {
    return [];
  }
  return response.threads.map(mapThread);
}

export async function fetchConversation(
  otherUserId: number,
): Promise<{ thread: MessageThread | null; messages: Message[]; messaging: MessagingAccess; otherUser?: MessageThread['otherUser'] }> {
  const response = await apiClient.get<ThreadResponse>(`/messages.php?user_id=${otherUserId}`);
  if (!response.ok) {
    return { thread: null, messages: [], messaging: { allowed: false, reason: 'Unable to load conversation.' } };
  }

  return {
    thread: response.thread ? mapThread(response.thread) : null,
    messages: response.messages.map(mapMessage),
    messaging: {
      allowed: response.messaging.allowed,
      reason: response.messaging.reason,
      level: response.messaging.level,
      hasRelationship: response.messaging.has_relationship,
    },
    otherUser: response.other_user
      ? {
          id: response.other_user.id,
          displayName: response.other_user.display_name ?? null,
          username: response.other_user.username ?? null,
        }
      : undefined,
  };
}

export async function sendMessage(recipientId: number, body: string): Promise<{ thread: MessageThread; message: Message }> {
  const csrf = getCsrfToken();
  if (!csrf) {
    throw new Error('Please log in to send messages.');
  }

  const response = await apiClient.post<SendResponse>('/messages.php', {
    recipient_id: recipientId,
    body,
    csrf,
  });

  return {
    thread: mapThread(response.thread),
    message: mapMessage(response.message),
  };
}
