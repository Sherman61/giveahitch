import { FC, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ScrollView, StyleSheet, Text } from 'react-native';
import type { Message, MessageThread } from '@/api/messages';
import { fetchConversation, fetchThreads, sendMessage } from '@/api/messages';
import { ChatsScreen } from '@/screens/ChatsScreen';
import { MessageScreen } from '@/screens/MessageScreen';
import { useChatSocket } from '@/hooks/useChatSocket';
import { useMyMatches } from '@/hooks/useMyMatches';
import { useNotifications } from '@/hooks/useNotifications';
import type { UserProfile } from '@/types/user';

interface Props {
  user?: UserProfile | null;
  onRequestLogin: () => void;
  onOpenAccount: () => void;
}

export const MessagesScreen: FC<Props> = ({ user, onRequestLogin, onOpenAccount }) => {
  const { matchesList, refreshMatches } = useMyMatches(user ?? null);
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
  const [presenceResolvedByUserId, setPresenceResolvedByUserId] = useState<Record<number, boolean>>({});
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

      setThreads((prev) => {
        const existingThread = prev.find((item) => item.otherUser.id === counterpartId);
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
          return prev;
        }

        const filtered = prev.filter((item) => item.otherUser.id !== nextThread.otherUser.id);

        return [
          {
            ...existingThread,
            ...nextThread,
            lastMessage: message,
            lastMessageAt: message.createdAt ?? nextThread.lastMessageAt,
            unreadCount:
              message.senderId === userId || isActiveConversation ? 0 : (nextThread.unreadCount ?? 0) + 1,
          },
          ...filtered,
        ];
      });

      if (clientId || isActiveConversation) {
        setMessages((prev) => mergeIncomingMessage(prev, message, clientId));
      }

      if (isActiveConversation) {
        setActiveThread((prev) => {
          if (thread) {
            return thread;
          }
          if (!prev) {
            return prev;
          }
          return {
            ...prev,
            lastMessage: message,
            lastMessageAt: message.createdAt ?? prev.lastMessageAt,
          };
        });
      }
    },
    [activeThread, activeUserId, mergeIncomingMessage, userId],
  );

  const handleMessagesRead = useCallback(
    ({ userId: readerUserId, messageIds, readAt }: { userId: number; messageIds: number[]; readAt: string }) => {
      setMessages((prev) =>
        prev.map((item) =>
          item.senderId === userId && messageIds.includes(item.id)
            ? { ...item, readAt, deliveryState: 'read' }
            : item,
        ),
      );

      setThreads((prev) =>
        prev.map((thread) =>
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
    ({ thread, deletedMessageIds, otherUserId }: { thread?: MessageThread; deletedMessageIds: number[]; otherUserId?: number }) => {
      if (deletedMessageIds.length === 0) {
        return;
      }

      setMessages((prev) => prev.filter((item) => !deletedMessageIds.includes(item.id)));

      if (thread) {
        setThreads((prev) => {
          const filtered = prev.filter((item) => item.otherUser.id !== thread.otherUser.id);
          return [thread, ...filtered];
        });

        if (activeUserId === thread.otherUser.id) {
          setActiveThread(thread);
        }
        return;
      }

      if (!otherUserId) {
        return;
      }

      setThreads((prev) =>
        prev.map((item) => {
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

  const { connectionState, presenceByUserId, typingByUserId, setTyping, markRead } = useChatSocket({
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
    setMessages((prev) => [...prev, optimisticMessage]);
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
      setMessages((prev) =>
        prev.map((item) => (item.clientId === clientId ? { ...item, deliveryState: 'failed' } : item)),
      );
      setMessagingError(error instanceof Error ? error.message : 'Unable to send message.');
    } finally {
      setSending(false);
      void setTyping(activeUserId, false);
    }
  }, [activeUserId, canMessage, compose, handleIncomingMessage, sending, setTyping, userId]);

  const activePresenceText = useMemo(
    () => {
      if (activeUserId && typingByUserId[activeUserId]) {
        return 'Typing...';
      }

      if (!activeUserId) {
        return connectionState === 'connected' ? 'Select a conversation' : 'Status unavailable';
      }

      if (Object.prototype.hasOwnProperty.call(presenceByUserId, activeUserId)) {
        return presenceByUserId[activeUserId] ? 'Online' : 'Offline';
      }

      if (presenceResolvedByUserId[activeUserId]) {
        return 'Offline';
      }

      if (typeof activeThread?.otherUser.isOnline === 'boolean') {
        return activeThread.otherUser.isOnline ? 'Online' : 'Offline';
      }

      return connectionState === 'idle' ? 'Status unavailable' : 'Checking status...';
    },
    [activeThread, activeUserId, connectionState, presenceByUserId, presenceResolvedByUserId, typingByUserId],
  );

  const renderReceipt = useCallback(
    (message: Message) => {
      if (message.senderId !== userId) {
        return null;
      }
      if (message.deliveryState === 'sending') {
        return <Text style={styles.messageMetaPending}>...</Text>;
      }
      if (message.deliveryState === 'failed') {
        return <Text style={styles.messageMetaFailed}>!</Text>;
      }

      return (
        <Text style={message.readAt ? styles.messageMetaRead : styles.messageMetaDelivered}>
          {'\u2713\u2713'}
        </Text>
      );
    },
    [userId],
  );

  useEffect(() => {
    const resolvedPresenceEntries = Object.keys(presenceByUserId);
    if (resolvedPresenceEntries.length === 0) {
      return;
    }

    setPresenceResolvedByUserId((previous) => {
      const next = { ...previous };
      let changed = false;

      resolvedPresenceEntries.forEach((key) => {
        const userIdFromPresence = Number(key);
        if (!Number.isFinite(userIdFromPresence) || next[userIdFromPresence]) {
          return;
        }
        next[userIdFromPresence] = true;
        changed = true;
      });

      return changed ? next : previous;
    });
  }, [presenceByUserId]);

  useEffect(() => {
    if (!activeUserId) {
      return;
    }

    if (Object.prototype.hasOwnProperty.call(presenceByUserId, activeUserId) || presenceResolvedByUserId[activeUserId]) {
      return;
    }

    if (connectionState !== 'connected') {
      return;
    }

    const timeout = setTimeout(() => {
      setPresenceResolvedByUserId((previous) => {
        if (previous[activeUserId]) {
          return previous;
        }
        return { ...previous, [activeUserId]: true };
      });
    }, 1200);

    return () => {
      clearTimeout(timeout);
    };
  }, [activeUserId, connectionState, presenceByUserId, presenceResolvedByUserId]);

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
    setThreads((prev) =>
      prev.map((thread) => (thread.otherUser.id === activeUserId ? { ...thread, unreadCount: 0 } : thread)),
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
    if (!activeThread || (messages.length === 0 && !(activeUserId && typingByUserId[activeUserId]))) {
      return;
    }

    requestAnimationFrame(() => {
      scrollRef.current?.scrollToEnd({ animated: true });
    });
  }, [activeThread, activeUserId, messages, typingByUserId]);

  if (activeThread) {
    return (
      <MessageScreen
        activePresenceText={activePresenceText}
        activeThread={activeThread}
        canMessage={canMessage}
        compose={compose}
        connectionState={connectionState}
        loadingConversation={loadingConversation}
        messages={messages}
        messagingError={messagingError}
        onBack={() => {
          setActiveThread(null);
          setActiveUserId(null);
          setMessages([]);
          setCompose('');
        }}
        onChangeCompose={setCompose}
        onSend={handleSend}
        renderReceipt={renderReceipt}
        scrollRef={scrollRef}
        sending={sending}
        typing={Boolean(activeUserId && typingByUserId[activeUserId])}
        userId={userId}
      />
    );
  }

  return (
    <ChatsScreen
      user={user}
      matchesList={matchesList}
      messagingError={messagingError}
      loadingThreads={loadingThreads}
      onOpenAccount={onOpenAccount}
      onOpenConversation={(otherUserId) => {
        void openConversation(otherUserId);
      }}
      onRefreshMatches={() => {
        void refreshMatches();
      }}
      onRefreshThreads={() => {
        void loadThreads();
      }}
      onRequestLogin={onRequestLogin}
      threads={threads}
    />
  );
};

const styles = StyleSheet.create({
  messageMetaDelivered: {
    color: '#d8e7ff',
    fontSize: 11,
    fontWeight: '700',
  },
  messageMetaRead: {
    color: '#8ed0ff',
    fontSize: 11,
    fontWeight: '700',
  },
  messageMetaPending: {
    color: '#d8e7ff',
    fontSize: 11,
  },
  messageMetaFailed: {
    color: '#ffd1d1',
    fontSize: 11,
    fontWeight: '700',
  },
});
