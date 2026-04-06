import { FC, ReactNode, RefObject } from 'react';
import { ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import type { Message, MessageThread } from '@/api/messages';
import { ConversationHeader } from '@/components/messages/ConversationHeader';
import { PrimaryButton } from '@/components/PrimaryButton';
import { TypingBubble } from '@/components/messages/TypingBubble';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

dayjs.extend(relativeTime);

interface Props {
  activePresenceText: string;
  activeThread: MessageThread;
  compose: string;
  connectionState: string;
  isActiveUserOnline?: boolean;
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
  isActiveUserOnline = false,
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
}) => {
  return (
    <View style={[styles.section, styles.chatSection]}>
      <ConversationHeader
        activePresenceText={activePresenceText}
        connectionState={connectionState}
        isActiveUserOnline={isActiveUserOnline}
        onBack={onBack}
      />

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
        {!loadingConversation && typing ? <TypingBubble /> : null}
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
          placeholderTextColor={palette.inputPlaceholder}
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
  typingIndicator: {
    color: palette.primary,
    fontWeight: '600',
  },
  subtitle: {
    color: palette.muted,
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
    color: palette.text,
    textAlignVertical: 'top',
  },
  composerButton: {
    alignSelf: 'flex-end',
  },
});
