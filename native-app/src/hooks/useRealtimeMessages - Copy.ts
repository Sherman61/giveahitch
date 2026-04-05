import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getCsrfToken, getMessagesWebSocketUrl } from '@/api/client';
import { markConversationRead, Message, MessageThread, sendTypingIndicator } from '@/api/messages';

type ConnectionState = 'idle' | 'connecting' | 'connected' | 'disconnected';

type RealtimeEvent =
  | {
      type: 'presence';
      userId: number;
      online: boolean;
    }
  | {
      type: 'typing';
      userId: number;
      isTyping: boolean;
    }
  | {
      type: 'message:new' | 'message:sent';
      thread?: MessageThread;
      message: Message;
      otherUserId?: number;
      clientId?: string;
    }
  | {
      type: 'message:read';
      userId: number;
      messageIds: number[];
      readAt: string;
    };

interface RealtimePayload {
  type: string;
  [key: string]: unknown;
}

interface Options {
  userId: number | null;
  activeUserId: number | null;
  onIncomingMessage: (payload: { thread?: MessageThread; message: Message; otherUserId?: number; clientId?: string }) => void;
  onMessagesRead: (payload: { userId: number; messageIds: number[]; readAt: string }) => void;
}

export function useRealtimeMessages({ userId, activeUserId, onIncomingMessage, onMessagesRead }: Options) {
  const [connectionState, setConnectionState] = useState<ConnectionState>('idle');
  const [presenceByUserId, setPresenceByUserId] = useState<Record<number, boolean>>({});
  const [typingByUserId, setTypingByUserId] = useState<Record<number, boolean>>({});
  const socketRef = useRef<WebSocket | null>(null);
  const reconnectTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const typingClearTimersRef = useRef<Record<number, ReturnType<typeof setTimeout>>>({});
  const subscribedUserIdsRef = useRef<Set<number>>(new Set());
  const callbacksRef = useRef({ onIncomingMessage, onMessagesRead });

  callbacksRef.current = { onIncomingMessage, onMessagesRead };

  const sendPayload = useCallback((payload: RealtimePayload) => {
    if (socketRef.current?.readyState === WebSocket.OPEN) {
      socketRef.current.send(JSON.stringify(payload));
      return true;
    }
    return false;
  }, []);

  const subscribeToUser = useCallback(
    (otherUserId: number | null) => {
      if (!otherUserId || !userId) {
        return;
      }
      subscribedUserIdsRef.current.add(otherUserId);
      sendPayload({ type: 'subscribe', userId: otherUserId });
    },
    [sendPayload, userId],
  );

  useEffect(() => {
    if (!userId) {
      setConnectionState('idle');
      setPresenceByUserId({});
      setTypingByUserId({});
      socketRef.current?.close();
      socketRef.current = null;
      return;
    }

    let cancelled = false;

    const connect = () => {
      setConnectionState('connecting');
      const csrfToken = getCsrfToken();
      const ws = new WebSocket(getMessagesWebSocketUrl());
      socketRef.current = ws;

      ws.onopen = () => {
        if (cancelled) {
          ws.close();
          return;
        }
        setConnectionState('connected');
        ws.send(
          JSON.stringify({
            type: 'auth',
            userId,
            csrfToken,
          }),
        );

        subscribedUserIdsRef.current.forEach((id) => {
          ws.send(JSON.stringify({ type: 'subscribe', userId: id }));
        });
      };

      ws.onmessage = (event) => {
        try {
          const payload = JSON.parse(String(event.data)) as RealtimeEvent;

          if (payload.type === 'presence') {
            setPresenceByUserId((prev) => ({ ...prev, [payload.userId]: payload.online }));
            return;
          }

          if (payload.type === 'typing') {
            setTypingByUserId((prev) => ({ ...prev, [payload.userId]: payload.isTyping }));
            if (typingClearTimersRef.current[payload.userId]) {
              clearTimeout(typingClearTimersRef.current[payload.userId]);
            }
            if (payload.isTyping) {
              typingClearTimersRef.current[payload.userId] = setTimeout(() => {
                setTypingByUserId((prev) => ({ ...prev, [payload.userId]: false }));
              }, 2500);
            }
            return;
          }

          if (payload.type === 'message:new' || payload.type === 'message:sent') {
            callbacksRef.current.onIncomingMessage({
              thread: payload.thread,
              message: payload.message,
              otherUserId: payload.otherUserId,
              clientId: payload.clientId,
            });
            return;
          }

          if (payload.type === 'message:read') {
            callbacksRef.current.onMessagesRead({
              userId: payload.userId,
              messageIds: payload.messageIds,
              readAt: payload.readAt,
            });
          }
        } catch (error) {
          console.warn('Failed to parse realtime message payload', error);
        }
      };

      ws.onclose = () => {
        if (cancelled) {
          return;
        }
        setConnectionState('disconnected');
        reconnectTimerRef.current = setTimeout(connect, 3000);
      };

      ws.onerror = () => {
        setConnectionState('disconnected');
      };
    };

    connect();

    return () => {
      cancelled = true;
      if (reconnectTimerRef.current) {
        clearTimeout(reconnectTimerRef.current);
      }
      Object.values(typingClearTimersRef.current).forEach(clearTimeout);
      socketRef.current?.close();
      socketRef.current = null;
    };
  }, [userId]);

  useEffect(() => {
    subscribeToUser(activeUserId);
  }, [activeUserId, subscribeToUser]);

  const setTyping = useCallback(
    async (otherUserId: number, isTyping: boolean) => {
      const sentOverSocket = sendPayload({ type: 'typing', userId: otherUserId, isTyping });
      if (!sentOverSocket) {
        try {
          await sendTypingIndicator(otherUserId, isTyping);
        } catch (error) {
          console.warn('Typing indicator fallback failed', error);
        }
      }
    },
    [sendPayload],
  );

  const markRead = useCallback(
    async (otherUserId: number, messageIds: number[]) => {
      if (messageIds.length === 0) {
        return;
      }

      const sentOverSocket = sendPayload({ type: 'read', userId: otherUserId, messageIds });
      try {
        await markConversationRead(otherUserId, messageIds);
      } catch (error) {
        if (!sentOverSocket) {
          console.warn('Mark read failed', error);
        }
      }
    },
    [sendPayload],
  );

  return useMemo(
    () => ({
      connectionState,
      isConnected: connectionState === 'connected',
      presenceByUserId,
      typingByUserId,
      subscribeToUser,
      setTyping,
      markRead,
    }),
    [connectionState, markRead, presenceByUserId, setTyping, subscribeToUser, typingByUserId],
  );
}
