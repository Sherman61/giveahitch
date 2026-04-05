import { mapMessage, mapThread, markConversationRead, type MessageThread } from '@/api/messages';
import { getChatSocketManager } from '@/lib/socket';
import type {
  ChatConnectionState,
  ChatIncomingMessage,
  ChatMessagesDeleted,
  ChatMessagesRead,
  DmDeletePayload,
  DmNewPayload,
} from '@/types/chat';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface Options {
  userId: number | null;
  activeUserId: number | null;
  activeThreadId?: number | null;
  onIncomingMessage: (payload: ChatIncomingMessage) => void;
  onMessagesRead: (payload: ChatMessagesRead) => void;
  onMessagesDeleted?: (payload: ChatMessagesDeleted) => void;
}

function selectThread(payload: DmNewPayload | DmDeletePayload, userId: number): MessageThread | undefined {
  const isDeletePayload = 'initiator_id' in payload;
  const rawThread = isDeletePayload
    ? payload.initiator_id === userId
      ? payload.thread_for_sender ?? payload.thread_for_recipient
      : payload.thread_for_recipient ?? payload.thread_for_sender
    : payload.sender_id === userId
      ? payload.thread_for_sender ?? payload.thread_for_recipient
      : payload.thread_for_recipient ?? payload.thread_for_sender;

  return rawThread ? mapThread(rawThread) : undefined;
}

export function useChatSocket({
  userId,
  activeUserId,
  activeThreadId = null,
  onIncomingMessage,
  onMessagesRead,
  onMessagesDeleted,
}: Options) {
  const manager = getChatSocketManager();
  const [connectionState, setConnectionState] = useState<ChatConnectionState>('idle');
  const [presenceByUserId, setPresenceByUserId] = useState<Record<number, boolean>>({});
  const [typingByUserId, setTypingByUserId] = useState<Record<number, boolean>>({});
  const typingTimeoutsRef = useRef<Record<number, ReturnType<typeof setTimeout>>>({});
  const callbacksRef = useRef({ onIncomingMessage, onMessagesRead, onMessagesDeleted });

  callbacksRef.current = { onIncomingMessage, onMessagesRead, onMessagesDeleted };

  useEffect(() => {
    if (!userId) {
      manager.disconnect();
      setConnectionState('idle');
      setPresenceByUserId({});
      setTypingByUserId({});
      Object.values(typingTimeoutsRef.current).forEach(clearTimeout);
      typingTimeoutsRef.current = {};
      return;
    }

    manager.connect();

    const unsubscribeConnection = manager.subscribe('connection', (status) => {
      setConnectionState(status.state);
    });

    const unsubscribeNew = manager.subscribe('dm:new', (payload) => {
      if (!payload.target_user_ids.includes(userId)) {
        return;
      }

      const senderId = Number(payload.sender_id);
      const recipientId = Number(payload.recipient_id);
      const otherUserId = senderId === userId ? recipientId : senderId;

      if (otherUserId && senderId !== userId) {
        setTypingByUserId((previous) => ({ ...previous, [otherUserId]: false }));
      }

      callbacksRef.current.onIncomingMessage({
        thread: selectThread(payload, userId),
        message: mapMessage(payload.message),
        otherUserId,
        clientId: payload.client_ref ?? undefined,
      });
    });

    const unsubscribeTyping = manager.subscribe('dm:typing', (payload) => {
      if (Number(payload.recipient_id || 0) !== userId) {
        return;
      }

      const senderId = Number(payload.sender_id || 0);
      if (!senderId) {
        return;
      }

      if (typingTimeoutsRef.current[senderId]) {
        clearTimeout(typingTimeoutsRef.current[senderId]);
      }

      setTypingByUserId((previous) => ({ ...previous, [senderId]: !!payload.typing }));

      if (payload.typing) {
        typingTimeoutsRef.current[senderId] = setTimeout(() => {
          setTypingByUserId((previous) => ({ ...previous, [senderId]: false }));
          delete typingTimeoutsRef.current[senderId];
        }, 4000);
      }
    });

    const unsubscribePresence = manager.subscribe('dm:presence', (payload) => {
      const targetId = Number(payload.user_id || 0);
      if (!targetId) {
        return;
      }

      setPresenceByUserId((previous) => ({ ...previous, [targetId]: !!payload.online }));
    });

    const unsubscribeRead = manager.subscribe('dm:read', (payload) => {
      const readerUserId = Number(payload.reader_id || 0);
      if (!readerUserId) {
        return;
      }

      const messageIds = payload.message_ids
        .map((id) => Number(id))
        .filter((id) => Number.isFinite(id));

      if (!messageIds.length) {
        return;
      }

      const readAt = payload.messages?.find((message) => message.read_at)?.read_at ?? new Date().toISOString();
      callbacksRef.current.onMessagesRead({ userId: readerUserId, messageIds, readAt });
    });

    const unsubscribeDelete = manager.subscribe('dm:delete', (payload) => {
      if (!payload.target_user_ids.includes(userId)) {
        return;
      }

      const deletedMessageIds = payload.deleted_message_ids
        .map((id) => Number(id))
        .filter((id) => Number.isFinite(id) && id > 0);

      callbacksRef.current.onMessagesDeleted?.({
        thread: selectThread(payload, userId),
        deletedMessageIds,
        otherUserId: payload.initiator_id === userId ? activeUserId ?? undefined : payload.initiator_id,
      });
    });

    return () => {
      unsubscribeConnection();
      unsubscribeNew();
      unsubscribeTyping();
      unsubscribePresence();
      unsubscribeRead();
      unsubscribeDelete();
      Object.values(typingTimeoutsRef.current).forEach(clearTimeout);
      typingTimeoutsRef.current = {};
    };
  }, [activeUserId, manager, userId]);

  const setTyping = useCallback(
    async (otherUserId: number, isTyping: boolean) => {
      if (!otherUserId) {
        return;
      }

      manager.connect();
      manager.emitTyping(otherUserId, activeThreadId, isTyping);
    },
    [activeThreadId, manager],
  );

  const markRead = useCallback(async (otherUserId: number, messageIds: number[]) => {
    if (!otherUserId || messageIds.length === 0) {
      return;
    }

    await markConversationRead(otherUserId, messageIds);
  }, []);

  return useMemo(
    () => ({
      connectionState,
      isConnected: connectionState === 'connected',
      presenceByUserId,
      typingByUserId,
      setTyping,
      markRead,
    }),
    [connectionState, markRead, presenceByUserId, setTyping, typingByUserId],
  );
}
