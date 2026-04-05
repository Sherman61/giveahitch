import { FC } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text } from 'react-native';
import type { Message, MessageThread } from '@/api/messages';
import { MessageConversationPanel } from '@/components/messages/MessageConversationPanel';
import { palette } from '@/constants/colors';

interface Props {
  activePresenceText: string;
  activeThread: MessageThread;
  canMessage: boolean;
  compose: string;
  connectionState: string;
  loadingConversation: boolean;
  messages: Message[];
  messagingError: string | null;
  onBack: () => void;
  onChangeCompose: (value: string) => void;
  onSend: () => void;
  renderReceipt: (message: Message) => React.ReactNode;
  scrollRef: React.RefObject<ScrollView | null>;
  sending: boolean;
  typing: boolean;
  userId: number;
}

export const MessageScreen: FC<Props> = ({
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
  <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.container}>
    {messagingError ? <Text style={styles.error}>{messagingError}</Text> : null}
    <MessageConversationPanel
      activePresenceText={activePresenceText}
      activeThread={activeThread}
      canMessage={canMessage}
      compose={compose}
      connectionState={connectionState}
      loadingConversation={loadingConversation}
      messages={messages}
      messagingError={messagingError}
      onBack={onBack}
      onChangeCompose={onChangeCompose}
      onSend={onSend}
      renderReceipt={renderReceipt}
      scrollRef={scrollRef}
      sending={sending}
      typing={typing}
      userId={userId}
    />
  </KeyboardAvoidingView>
);

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: palette.background,
  },
  error: {
    color: palette.danger,
  },
});
