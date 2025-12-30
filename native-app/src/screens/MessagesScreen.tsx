import { FC, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ScrollView, StyleSheet, Text, View, TextInput, TouchableOpacity, KeyboardAvoidingView, Platform } from 'react-native';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { useMyMatches } from '@/hooks/useMyMatches';
import { UserProfile } from '@/types/user';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { MatchCard } from '@/components/MatchCard';
import { PrimaryButton } from '@/components/PrimaryButton';
import { useNotifications } from '@/hooks/useNotifications';
import { fetchConversation, fetchThreads, Message, MessageThread, sendMessage } from '@/api/messages';

dayjs.extend(relativeTime);

interface Props {
  user?: UserProfile | null;
  onRequestLogin: () => void;
}

export const MessagesScreen: FC<Props> = ({ user, onRequestLogin }) => {
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
  const scrollRef = useRef<ScrollView | null>(null);

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
      if (!otherUserId) return;
      setActiveUserId(otherUserId);
      setLoadingConversation(true);
      setMessagingError(null);
      setCanMessage(true);
      try {
        const data = await fetchConversation(otherUserId);
        setActiveThread(
          data.thread ?? {
            id: Date.now(),
            otherUser: data.otherUser ?? { id: otherUserId, displayName: 'Member', username: null },
            lastMessageAt: null,
            unreadCount: 0,
          },
        );
        setMessages(data.messages);
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
    [],
  );

  const handleSend = useCallback(async () => {
    if (!activeUserId || compose.trim() === '' || sending || !canMessage) return;
    setSending(true);
    setMessagingError(null);
    try {
      const { thread, message } = await sendMessage(activeUserId, compose.trim());
      setActiveThread(thread);
      setMessages((prev) => [...prev, message]);
      setCompose('');
      setThreads((prev) => {
        const filtered = prev.filter((t) => t.otherUser.id !== activeUserId);
        return [{ ...thread }, ...filtered];
      });
      requestAnimationFrame(() => {
        scrollRef.current?.scrollToEnd({ animated: true });
      });
    } catch (error) {
      setMessagingError(error instanceof Error ? error.message : 'Unable to send message.');
    } finally {
      setSending(false);
    }
  }, [activeUserId, canMessage, compose, sending]);

  const activeTitle = useMemo(() => {
    if (!activeThread) return 'Messages';
    const preferredName =
      activeThread.otherUser.displayName && activeThread.otherUser.displayName.trim() !== ''
        ? activeThread.otherUser.displayName
        : activeThread.otherUser.username;
    return preferredName ? `Chat with ${preferredName}` : 'Conversation';
  }, [activeThread]);

  const renderMessage = useCallback(
    (message: Message) => {
      const isMine = message.senderId === userId;
      return (
        <View key={message.id} style={[styles.messageRow, isMine ? styles.messageRight : styles.messageLeft]}>
          <View style={[styles.messageBubble, isMine ? styles.messageBubbleMine : styles.messageBubbleTheirs]}>
            <Text style={[styles.messageText, isMine && styles.messageTextMine]}>{message.body}</Text>
            <Text style={[styles.messageMeta, isMine && styles.messageMetaMine]}>
              {message.createdAt ? dayjs(message.createdAt).fromNow() : ''}
            </Text>
          </View>
        </View>
      );
    },
    [userId],
  );

  useEffect(() => {
    if (!user) return;
    loadThreads().catch(() => {});
  }, [loadThreads, user]);

  useEffect(() => {
    if (!lastNotification || !user) {
      return;
    }
    refreshMatches();
    loadThreads().catch(() => {});
    if (activeUserId) {
      openConversation(activeUserId);
    }
  }, [activeUserId, lastNotification, loadThreads, openConversation, refreshMatches, user]);

  if (!user) {
    return (
      <View style={[styles.container, styles.centered]}>
        <Text style={styles.title}>Sign in to view messages</Text>
        <Text style={styles.subtitle}>Chat with drivers or passengers after you accept a ride.</Text>
        <PrimaryButton label="Log In" onPress={onRequestLogin} />
      </View>
    );
  }

  return (
    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.container}>
      <ScrollView style={styles.container} contentContainerStyle={styles.content}>
        <Text style={styles.title}>{activeTitle}</Text>
        <Text style={styles.subtitle}>Conversations with your ride matches.</Text>

        {messagingError && <Text style={styles.error}>{messagingError}</Text>}

        {!activeThread && (
          <View style={styles.section}>
            <View style={styles.sectionHeader}>
              <Text style={styles.sectionTitle}>Recent conversations</Text>
              <TouchableOpacity onPress={loadThreads} disabled={loadingThreads}>
                <Text style={styles.link}>{loadingThreads ? 'Refreshing…' : 'Refresh'}</Text>
              </TouchableOpacity>
            </View>
            {threads.length === 0 && (
              <Text style={styles.empty}>No conversations yet. Start by messaging a ride match.</Text>
            )}
            {threads.map((thread) => (
              <TouchableOpacity key={thread.id} style={styles.threadRow} onPress={() => openConversation(thread.otherUser.id)}>
                <View style={styles.threadText}>
                  <Text style={styles.threadName}>{thread.otherUser.displayName ?? thread.otherUser.username ?? 'Member'}</Text>
                  <Text style={styles.threadPreview}>
                    {thread.lastMessage?.body ? thread.lastMessage.body.slice(0, 80) : 'No messages yet.'}
                  </Text>
                </View>
                <View style={styles.threadMeta}>
                  {thread.unreadCount ? <Text style={styles.unreadBadge}>{thread.unreadCount}</Text> : null}
                  {thread.lastMessageAt && <Text style={styles.threadTime}>{dayjs(thread.lastMessageAt).fromNow()}</Text>}
                </View>
              </TouchableOpacity>
            ))}
          </View>
        )}

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Ride matches</Text>
            <TouchableOpacity onPress={refreshMatches}>
              <Text style={styles.link}>Refresh</Text>
            </TouchableOpacity>
          </View>

          {matchesList.length === 0 && <Text style={styles.empty}>You don't have any ride matches yet.</Text>}

          {matchesList.map((match) => (
            <View key={match.matchId} style={styles.card}>
              <MatchCard match={match} />
              <PrimaryButton
                label={match.otherUserName ? `Message ${match.otherUserName}` : 'Open chat'}
                onPress={() => match.otherUserId && openConversation(match.otherUserId)}
              />
            </View>
          ))}
        </View>

        {activeThread && (
          <View style={styles.section}>
            <View style={styles.sectionHeader}>
              <Text style={styles.sectionTitle}>Conversation</Text>
              <TouchableOpacity onPress={() => setActiveThread(null)}>
                <Text style={styles.link}>Back to inbox</Text>
              </TouchableOpacity>
            </View>
            <ScrollView ref={scrollRef} style={styles.messagesList} contentContainerStyle={styles.messagesContent}>
              {loadingConversation && <Text style={styles.subtitle}>Loading conversation…</Text>}
              {!loadingConversation && messages.map(renderMessage)}
              {!loadingConversation && messages.length === 0 && (
                <Text style={styles.empty}>Say hi to start this conversation.</Text>
              )}
              {!loadingConversation && !canMessage && messagingError && (
                <Text style={styles.error}>{messagingError}</Text>
              )}
            </ScrollView>
            <View style={styles.composer}>
              <TextInput
                style={styles.input}
                placeholder="Type a message"
                value={compose}
                onChangeText={setCompose}
                multiline
              />
              <PrimaryButton label={!canMessage ? 'Messaging disabled' : sending ? 'Sending…' : 'Send'} onPress={handleSend} />
            </View>
          </View>
        )}
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: palette.background,
  },
  content: {
    padding: spacing.lg,
    gap: spacing.md,
    paddingBottom: spacing.xl,
  },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.lg,
    gap: spacing.md,
  },
  title: {
    fontSize: 26,
    fontWeight: '700',
  },
  subtitle: {
    color: palette.muted,
  },
  empty: {
    color: palette.muted,
    fontStyle: 'italic',
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.md,
    gap: spacing.sm,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  section: {
    gap: spacing.sm,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
  },
  link: {
    color: palette.primary,
    fontWeight: '600',
  },
  threadRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    padding: spacing.md,
    borderRadius: 12,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
    marginBottom: spacing.sm,
    gap: spacing.md,
  },
  threadText: {
    flex: 1,
  },
  threadName: {
    fontWeight: '700',
  },
  threadPreview: {
    color: palette.muted,
  },
  threadMeta: {
    alignItems: 'flex-end',
    gap: spacing.xs,
  },
  unreadBadge: {
    backgroundColor: palette.primary,
    color: '#fff',
    paddingHorizontal: spacing.sm,
    paddingVertical: 4,
    borderRadius: 999,
    fontWeight: '700',
  },
  threadTime: {
    color: palette.muted,
    fontSize: 12,
  },
  messagesList: {
    maxHeight: 320,
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.md,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  messagesContent: {
    gap: spacing.sm,
  },
  messageRow: {
    flexDirection: 'row',
  },
  messageLeft: {
    justifyContent: 'flex-start',
  },
  messageRight: {
    justifyContent: 'flex-end',
  },
  messageBubble: {
    maxWidth: '85%',
    padding: spacing.sm,
    borderRadius: 12,
  },
  messageBubbleMine: {
    backgroundColor: palette.primary,
    borderBottomRightRadius: 2,
  },
  messageBubbleTheirs: {
    backgroundColor: '#f4f5f7',
    borderBottomLeftRadius: 2,
  },
  messageText: {
    color: palette.text,
  },
  messageTextMine: {
    color: '#fff',
  },
  messageMeta: {
    color: palette.muted,
    fontSize: 10,
    marginTop: 4,
    textAlign: 'right',
  },
  messageMetaMine: {
    color: '#f0f4ff',
  },
  composer: {
    gap: spacing.sm,
    marginTop: spacing.sm,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ced4da',
    borderRadius: 10,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: '#fff',
    minHeight: 48,
  },
  error: {
    color: palette.danger,
  },
});
