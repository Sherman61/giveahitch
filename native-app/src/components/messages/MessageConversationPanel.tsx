import { FC, ReactNode, RefObject } from 'react';
import { ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import type { Message, MessageThread } from '@/api/messages';
import { PrimaryButton } from '@/components/PrimaryButton';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

dayjs.extend(relativeTime);

interface Props {
  activePresenceText: string;
  activeThread: MessageThread;
  compose: string;
  connectionState: string;
  loadingConversation: boolean;
  messages: Message[];
  messagingError: string | null;
  onBack: () => void;
  onChangeCompose: (value: string) => void;
  onSend: () => void;
  renderReceipt: (message: Message) => ReactNode;
  scrollRef: RefObject<ScrollView | null>;
  sending: boolean;
  canMessage: boolean;
  typing: boolean;
  userId: number;
}

export const MessageConversationPanel: FC<Props> = ({
  activePresenceText,
  activeThread,
  canMessage,
  compose,
  connectionState,
  loadingConversation,
  messages,
  messagingError,
  onBack,
  onChangeCompose,
  onSend,
  renderReceipt,
  scrollRef,
  sending,
  typing,
  userId,
}) => (
  <View style={[styles.section, styles.chatSection]}>
    <View style={styles.sectionHeader}>
      <View style={styles.headerCopy}>
        <Text style={styles.sectionTitle}>Conversation</Text>
        <View style={styles.statusRow}>
          <View
            style={[
              styles.statusDot,
              activePresenceText === 'Online'
                ? styles.statusDotOnline
                : activePresenceText === 'Offline'
                  ? styles.statusDotOffline
                  : styles.statusDotUnknown,
            ]}
          />
          <Text style={styles.subtitle}>{activePresenceText}</Text>
          <ConnectionBar activePresenceText={activePresenceText} state={connectionState} />
        </View>
      </View>
      <TouchableOpacity onPress={onBack} activeOpacity={0.82}>
        <Text style={styles.link}>Back to inbox</Text>
      </TouchableOpacity>
    </View>

    {typing ? (
      <Text style={styles.typingIndicator}>
        {activeThread.otherUser.displayName ?? activeThread.otherUser.username ?? 'Someone'} is typing...
      </Text>
    ) : null}

    <ScrollView
      ref={scrollRef}
      style={styles.messagesList}
      contentContainerStyle={styles.messagesContent}
      keyboardShouldPersistTaps="handled"
    >
      {loadingConversation && <Text style={styles.subtitle}>Loading conversation...</Text>}
      {!loadingConversation &&
        messages.map((message) => {
          const isMine = message.senderId === userId;
          return (
            <View
              key={`${message.id}-${message.clientId ?? 'server'}`}
              style={[styles.messageRow, isMine ? styles.messageRight : styles.messageLeft]}
            >
              <View style={[styles.messageBubble, isMine ? styles.messageBubbleMine : styles.messageBubbleTheirs]}>
                <Text style={[styles.messageText, isMine && styles.messageTextMine]}>{message.body}</Text>
                <View style={styles.messageFooter}>
                  <Text style={[styles.messageMeta, isMine && styles.messageMetaMine]}>
                    {message.createdAt ? dayjs(message.createdAt).fromNow() : ''}
                  </Text>
                  {renderReceipt(message)}
                </View>
              </View>
            </View>
          );
        })}
      {!loadingConversation && typing ? (
        <View style={[styles.messageRow, styles.messageLeft]}>
          <View style={[styles.messageBubble, styles.messageBubbleTheirs, styles.typingBubble]}>
            <View style={styles.typingDots}>
              <View style={styles.typingDot} />
              <View style={styles.typingDot} />
              <View style={styles.typingDot} />
            </View>
          </View>
        </View>
      ) : null}
      {!loadingConversation && messages.length === 0 && !typing && (
        <Text style={styles.empty}>Say hi to start this conversation.</Text>
      )}
      {!loadingConversation && !canMessage && messagingError && (
        <Text style={styles.error}>{messagingError}</Text>
      )}
    </ScrollView>

    <View style={styles.composer}>
      <TextInput
        style={styles.input}
        placeholder={canMessage ? 'Type a message' : 'Messaging unavailable'}
        value={compose}
        onChangeText={onChangeCompose}
        editable={canMessage}
        multiline
      />
      <View style={styles.composerButton}>
        <PrimaryButton
          label={!canMessage ? 'Messaging disabled' : sending ? 'Sending...' : 'Send'}
          onPress={onSend}
        />
      </View>
    </View>
  </View>
);

const ConnectionBar: FC<{ activePresenceText: string; state: string }> = ({ activePresenceText, state }) => {
  const isPeerConnected = activePresenceText === 'Online' || activePresenceText === 'Typing...';
  const isChecking = activePresenceText === 'Checking status...' || state === 'connecting';
  const strength = state === 'connected' ? (isPeerConnected ? 3 : isChecking ? 2 : 0) : state === 'connecting' ? 2 : 0;
  const bars = [8, 12, 16];
  const connectionLabel =
    state !== 'connected'
      ? state === 'connecting'
        ? 'Checking'
        : 'Offline'
      : isPeerConnected
        ? 'Connected'
        : isChecking
          ? 'Checking'
          : 'Offline';

  return (
    <View style={styles.connectionBarWrapper}>
      <View style={styles.signalBars}>
        {bars.map((height, index) => (
          <View
            key={index}
            style={[
              styles.signalBar,
              { height },
              index < strength ? styles.signalBarFilled : styles.signalBarEmpty,
            ]}
          />
        ))}
      </View>
      <Text style={styles.connectionText}>{connectionLabel}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  section: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.lg,
    gap: spacing.md,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  chatSection: {
    flex: 1,
    minHeight: 0,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: spacing.sm,
  },
  headerCopy: {
    flex: 1,
    gap: spacing.xs,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
  },
  statusRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    flexWrap: 'wrap',
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 999,
  },
  statusDotOnline: {
    backgroundColor: '#2e9f6b',
  },
  statusDotOffline: {
    backgroundColor: '#adb5bd',
  },
  statusDotUnknown: {
    backgroundColor: '#d6dde5',
  },
  subtitle: {
    color: palette.muted,
  },
  link: {
    color: palette.primary,
    fontWeight: '600',
  },
  typingIndicator: {
    color: palette.primary,
    fontWeight: '600',
  },
  typingDots: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  typingDot: {
    width: 8,
    height: 8,
    borderRadius: 999,
    backgroundColor: '#9aa8b8',
  },
  messagesList: {
    flex: 1,
    minHeight: 0,
  },
  messagesContent: {
    gap: spacing.sm,
    paddingBottom: spacing.xs,
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
    maxWidth: '84%',
    borderRadius: 16,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    gap: 4,
  },
  messageBubbleMine: {
    backgroundColor: palette.primary,
  },
  messageBubbleTheirs: {
    backgroundColor: '#eef2f6',
  },
  typingBubble: {
    minWidth: 60,
    paddingVertical: spacing.md,
  },
  messageText: {
    color: palette.text,
  },
  messageTextMine: {
    color: '#fff',
  },
  messageFooter: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    alignItems: 'center',
    gap: spacing.xs,
  },
  messageMeta: {
    color: palette.muted,
    fontSize: 11,
  },
  messageMetaMine: {
    color: '#d8e7ff',
  },
  empty: {
    color: palette.muted,
  },
  error: {
    color: palette.danger,
  },
  composer: {
    gap: spacing.sm,
    flexShrink: 0,
  },
  input: {
    minHeight: 52,
    maxHeight: 110,
    borderWidth: 1,
    borderColor: '#ced4da',
    borderRadius: 12,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: '#fff',
    textAlignVertical: 'top',
  },
  composerButton: {
    alignSelf: 'flex-end',
  },
  connectionBarWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
  },
  signalBars: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: spacing.xs,
    marginRight: spacing.xs,
  },
  signalBar: {
    width: 5,
    borderRadius: 2,
    backgroundColor: '#e6eefb',
  },
  signalBarFilled: {
    backgroundColor: palette.primary,
  },
  signalBarEmpty: {
    backgroundColor: '#e6eefb',
  },
  connectionText: {
    color: palette.muted,
    fontSize: 12,
  },
});
