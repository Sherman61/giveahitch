import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { ScrollView, Text } from 'react-native';
import type { Message, MessageThread } from '@/api/messages';
import { fetchConversation, fetchThreads, sendMessage } from '@/api/messages';
import { getConversationPresenceText } from '@/components/messages/messageStatus';
import { useChatSocket } from '@/hooks/useChatSocket';
import { useNotifications } from '@/hooks/useNotifications';
import type { UserProfile } from '@/types/user';

dayjs.extend(relativeTime);

interface Options {
  user?: UserProfile | null;
  refreshMatches: () => void | Promise<void>;
}

export function useMessagesController({ user, refreshMatches }: Options) {
  const { lastNotification } = useNotifications();
  const userId = user?.id ?? 0;
  const [threads, setThreads] = useState<MessageThread[]>([]);
  const [activeThread, setActiveThread] = useState<MessageThread | null>(null);
  const [activeUserId, setActiveUserId] = useState<number | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [canMessage, setCanMessage] = useState(true);
  const [messagingError, setMessagingError] = useState<string | null>(null);
  const [loadingThreads, setLoadingThreads] = useState(false);
  const [loadingConversation, setLoadingConversation] = useState(false);
  const [sending, setSending] = useState(false);
  const [compose, setCompose] = useState('');
  const scrollRef = useRef<ScrollView | null>(null);
  const typingStopTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const mergeIncomingMessage = useCallback((previous: Message[], message: Message, clientId?: string) => {
    const nextMessage: Message = {
      ...message,
      deliveryState: message.readAt ? 'read' : 'sent',
    };
    const existingIndex = previous.findIndex(
      (item) => item.id === message.id || (!!clientId && item.clientId === clientId),
    );

    if (existingIndex === -1) {
      return [...previous, nextMessage];
    }

    return previous.map((item, index) => (index === existingIndex ? { ...item, ...nextMessage } : item));
  }, []);

  const loadThreads = useCallback(async () => {
    setLoadingThreads(true);
    try {
      const data = await fetchThreads();
      setThreads(data);
    } catch (error) {
      setMessagingError(error instanceof Error ? error.message : 'Unable to load messages.');
    } finally {
      setLoadingThreads(false);
    }
  }, []);

  const openConversation = useCallback(
    async (otherUserId: number) => {
      if (!otherUserId) {
        return;
      }

      setActiveUserId(otherUserId);
      setLoadingConversation(true);
      setMessagingError(null);
      setCanMessage(true);

      try {
        const data = await fetchConversation(otherUserId);
        setActiveThread(
          data.thread ?? {
            id: Date.now(),
            otherUser: {
              id: otherUserId,
              displayName: data.otherUser?.displayName ?? 'Member',
              username: data.otherUser?.username ?? null,
            },
            lastMessageAt: null,
            unreadCount: 0,
          },
        );
        setMessages(
          data.messages.map((message) => ({
            ...message,
            deliveryState:
              message.senderId === userId ? (message.readAt ? 'read' : 'sent') : message.deliveryState,
          })),
        );
        setCanMessage(Boolean(data.messaging.allowed));

        if (!data.messaging.allowed) {
          setMessagingError(data.messaging.reason ?? 'Messaging is disabled for this member.');
        }
      } catch (error) {
        setMessagingError(error instanceof Error ? error.message : 'Unable to open conversation.');
      } finally {
        setLoadingConversation(false);
      }
    },
    [userId],
  );

  const handleIncomingMessage = useCallback(
    ({
      thread,
      message,
      otherUserId,
      clientId,
    }: {
      thread?: MessageThread;
      message: Message;
      otherUserId?: number;
      clientId?: string;
    }) => {
      const counterpartId = otherUserId ?? thread?.otherUser.id ?? activeUserId ?? 0;
      const isActiveConversation = counterpartId !== 0 && counterpartId === activeUserId;

      setThreads((previousThreads) => {
        const existingThread = previousThreads.find((item) => item.otherUser.id === counterpartId);
        const nextThread =
          thread ??
          existingThread ??
          (counterpartId
            ? {
                id: activeThread?.id ?? Date.now(),
                otherUser:
                  activeThread?.otherUser.id === counterpartId
                    ? activeThread.otherUser
                    : {
                        id: counterpartId,
                        displayName: 'Member',
                        username: null,
                      },
                unreadCount: 0,
              }
            : null);

        if (!nextThread) {
          return previousThreads;
        }

        const filteredThreads = previousThreads.filter((item) => item.otherUser.id !== nextThread.otherUser.id);

        return [
          {
            ...existingThread,
            ...nextThread,
            lastMessage: message,
            lastMessageAt: message.createdAt ?? nextThread.lastMessageAt,
            unreadCount:
              message.senderId === userId || isActiveConversation ? 0 : (nextThread.unreadCount ?? 0) + 1,
          },
          ...filteredThreads,
        ];
      });

      if (clientId || isActiveConversation) {
        setMessages((previousMessages) => mergeIncomingMessage(previousMessages, message, clientId));
      }

      if (isActiveConversation) {
        setActiveThread((previousThread) => {
          if (thread) {
            return thread;
          }

          if (!previousThread) {
            return previousThread;
          }

          return {
            ...previousThread,
            lastMessage: message,
            lastMessageAt: message.createdAt ?? previousThread.lastMessageAt,
          };
        });
      }
    },
    [activeThread, activeUserId, mergeIncomingMessage, userId],
  );

  const handleMessagesRead = useCallback(
    ({ userId: readerUserId, messageIds, readAt }: { userId: number; messageIds: number[]; readAt: string }) => {
      setMessages((previousMessages) =>
        previousMessages.map((item) =>
          item.senderId === userId && messageIds.includes(item.id)
            ? { ...item, readAt, deliveryState: 'read' }
            : item,
        ),
      );

      setThreads((previousThreads) =>
        previousThreads.map((thread) =>
          thread.otherUser.id === readerUserId && thread.lastMessage && messageIds.includes(thread.lastMessage.id)
            ? {
                ...thread,
                lastMessage: {
                  ...thread.lastMessage,
                  readAt,
                  deliveryState: 'read',
                },
              }
            : thread,
        ),
      );
    },
    [userId],
  );

  const handleMessagesDeleted = useCallback(
    ({
      thread,
      deletedMessageIds,
      otherUserId,
    }: {
      thread?: MessageThread;
      deletedMessageIds: number[];
      otherUserId?: number;
    }) => {
      if (deletedMessageIds.length === 0) {
        return;
      }

      setMessages((previousMessages) => previousMessages.filter((item) => !deletedMessageIds.includes(item.id)));

      if (thread) {
        setThreads((previousThreads) => {
          const filteredThreads = previousThreads.filter((item) => item.otherUser.id !== thread.otherUser.id);
          return [thread, ...filteredThreads];
        });

        if (activeUserId === thread.otherUser.id) {
          setActiveThread(thread);
        }
        return;
      }

      if (!otherUserId) {
        return;
      }

      setThreads((previousThreads) =>
        previousThreads.map((item) => {
          if (
            item.otherUser.id !== otherUserId ||
            !item.lastMessage ||
            !deletedMessageIds.includes(item.lastMessage.id)
          ) {
            return item;
          }

          return {
            ...item,
            lastMessage: undefined,
          };
        }),
      );
    },
    [activeUserId],
  );

  const { connectionState, hasPresenceSnapshot, presenceByUserId, lastSeenAtByUserId, typingByUserId, setTyping, markRead } =
    useChatSocket({
      userId: user?.id ?? null,
      activeUserId,
      activeThreadId: activeThread?.id ?? null,
      onIncomingMessage: handleIncomingMessage,
      onMessagesRead: handleMessagesRead,
      onMessagesDeleted: handleMessagesDeleted,
    });

  const handleSend = useCallback(async () => {
    if (!activeUserId || compose.trim() === '' || sending || !canMessage) {
      return;
    }

    const clientId = `local-${Date.now()}`;
    const optimisticMessage: Message = {
      id: -Date.now(),
      clientId,
      senderId: userId,
      body: compose.trim(),
      createdAt: new Date().toISOString(),
      deliveryState: 'sending',
      readAt: null,
    };

    setSending(true);
    setMessagingError(null);
    setMessages((previousMessages) => [...previousMessages, optimisticMessage]);
    setCompose('');

    try {
      const { thread, message, clientRef } = await sendMessage(activeUserId, optimisticMessage.body, clientId);
      handleIncomingMessage({
        thread,
        message: { ...message, clientId, deliveryState: message.readAt ? 'read' : 'sent' },
        otherUserId: activeUserId,
        clientId: clientRef ?? clientId,
      });
      setActiveThread(thread);
      requestAnimationFrame(() => {
        scrollRef.current?.scrollToEnd({ animated: true });
      });
    } catch (error) {
      setMessages((previousMessages) =>
        previousMessages.map((item) => (item.clientId === clientId ? { ...item, deliveryState: 'failed' } : item)),
      );
      setMessagingError(error instanceof Error ? error.message : 'Unable to send message.');
    } finally {
      setSending(false);
      void setTyping(activeUserId, false);
    }
  }, [activeUserId, canMessage, compose, handleIncomingMessage, sending, setTyping, userId]);

  const isTyping = Boolean(activeUserId && typingByUserId[activeUserId]);
  const isActiveUserOnline = Boolean(activeUserId && presenceByUserId[activeUserId] === true);
  const activeLastSeenAt = activeUserId
    ? lastSeenAtByUserId[activeUserId] ?? activeThread?.otherUser.lastOnline ?? null
    : null;

  const activePresenceText = useMemo(
    () =>
      getConversationPresenceText({
        activeUserId,
        activeLastSeenAt,
        connectionState,
        hasPresenceSnapshot,
        isActiveUserOnline,
        isTyping,
      }),
    [activeLastSeenAt, activeUserId, connectionState, hasPresenceSnapshot, isActiveUserOnline, isTyping],
  );

  const logConversationSignalSnapshot = useCallback(
    (reason: 'loaded' | 'interval') => {
      if (!activeUserId) {
        return;
      }

      const rawPresence = presenceByUserId[activeUserId];
      const rawTyping = typingByUserId[activeUserId];
      const threadLastOnline = activeThread?.otherUser.lastOnline ?? null;
      const summary = {
        reason,
        activeUserId,
        connectionState,
        hasPresenceSnapshot,
        rawPresenceOnline: rawPresence ?? null,
        resolvedOnlineState: isActiveUserOnline ? 'online' : 'not_confirmed_online',
        rawTyping: rawTyping ?? false,
        resolvedTyping: isTyping,
        lastSeenFromSocket: lastSeenAtByUserId[activeUserId] ?? null,
        lastOnlineFromThread: threadLastOnline,
        resolvedLastOnline: activeLastSeenAt,
        resolvedPresenceText: activePresenceText,
        loadingConversation,
        canMessage,
        messageCount: messages.length,
      };

      console.log('[messages-debug] conversation-signals', summary);
    },
    [
      activeLastSeenAt,
      activePresenceText,
      activeThread,
      activeUserId,
      canMessage,
      connectionState,
      hasPresenceSnapshot,
      isActiveUserOnline,
      isTyping,
      lastSeenAtByUserId,
      loadingConversation,
      messages.length,
      presenceByUserId,
      typingByUserId,
    ],
  );

  const renderReceipt = useCallback(
    (message: Message) => {
      if (message.senderId !== userId) {
        return null;
      }

      if (message.deliveryState === 'sending') {
        return <Text style={receiptStyles.pending}>...</Text>;
      }

      if (message.deliveryState === 'failed') {
        return <Text style={receiptStyles.failed}>!</Text>;
      }

      return <Text style={message.readAt ? receiptStyles.read : receiptStyles.delivered}>{'\u2713\u2713'}</Text>;
    },
    [userId],
  );

  const closeConversation = useCallback(() => {
    setActiveThread(null);
    setActiveUserId(null);
    setMessages([]);
    setCompose('');
  }, []);

  useEffect(() => {
    if (!user) {
      return;
    }

    loadThreads().catch(() => {});
  }, [loadThreads, user]);

  useEffect(() => {
    if (!lastNotification || !user) {
      return;
    }

    refreshMatches();
    loadThreads().catch(() => {});

    if (activeUserId) {
      void openConversation(activeUserId);
    }
  }, [activeUserId, lastNotification, loadThreads, openConversation, refreshMatches, user]);

  useEffect(() => {
    if (!activeUserId || !userId) {
      return;
    }

    const unreadIncomingIds = messages
      .filter((message) => message.senderId !== userId && !message.readAt && message.id > 0)
      .map((message) => message.id);

    if (unreadIncomingIds.length === 0) {
      return;
    }

    void markRead(activeUserId, unreadIncomingIds);
    setThreads((previousThreads) =>
      previousThreads.map((thread) =>
        thread.otherUser.id === activeUserId ? { ...thread, unreadCount: 0 } : thread,
      ),
    );
  }, [activeUserId, markRead, messages, userId]);

  useEffect(() => {
    if (!activeUserId || !canMessage) {
      return;
    }

    const targetUserId = activeUserId;

    if (typingStopTimerRef.current) {
      clearTimeout(typingStopTimerRef.current);
    }

    if (compose.trim() === '') {
      void setTyping(targetUserId, false);
      return;
    }

    void setTyping(targetUserId, true);
    typingStopTimerRef.current = setTimeout(() => {
      void setTyping(targetUserId, false);
    }, 1500);

    return () => {
      if (typingStopTimerRef.current) {
        clearTimeout(typingStopTimerRef.current);
      }

      void setTyping(targetUserId, false);
    };
  }, [activeUserId, canMessage, compose, setTyping]);

  useEffect(() => {
    if (!activeThread || (messages.length === 0 && !isTyping)) {
      return;
    }

    requestAnimationFrame(() => {
      scrollRef.current?.scrollToEnd({ animated: true });
    });
  }, [activeThread, isTyping, messages]);

  useEffect(() => {
    if (!activeUserId || loadingConversation) {
      return;
    }

    logConversationSignalSnapshot('loaded');

    const interval = setInterval(() => {
      logConversationSignalSnapshot('interval');
    }, 3000);

    return () => {
      clearInterval(interval);
    };
  }, [activeUserId, loadingConversation, logConversationSignalSnapshot]);

  return {
    activePresenceText,
    activeThread,
    canMessage,
    closeConversation,
    compose,
    connectionState,
    handleSend,
    isActiveUserOnline,
    isTyping,
    loadThreads,
    loadingConversation,
    loadingThreads,
    messages,
    messagingError,
    openConversation,
    renderReceipt,
    scrollRef,
    sending,
    setCompose,
    threads,
  };
}

const receiptStyles = {
  delivered: {
    color: '#d8e7ff',
    fontSize: 11,
    fontWeight: '700',
  },
  failed: {
    color: '#ffd1d1',
    fontSize: 11,
    fontWeight: '700',
  },
  pending: {
    color: '#d8e7ff',
    fontSize: 11,
  },
  read: {
    color: '#8ed0ff',
    fontSize: 11,
    fontWeight: '700',
  },
} as const;
