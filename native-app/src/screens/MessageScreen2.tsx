import { FC, ReactNode, RefObject } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import type { Message, MessageThread } from '@/api/messages';
import { MessageConversationPanel } from '@/components/messages/MessageConversationPanel';
import { palette } from '@/constants/colors';

interface Props {
  activePresenceText: string;
  activeThread: MessageThread;
  canMessage: boolean;
  compose: string;
  connectionState: string;
  isActiveUserOnline: boolean;
  loadingConversation: boolean;
  messages: Message[];
  messagingError: string | null;
  onBack: () => void;
  onChangeCompose: (value: string) => void;
  onSend: () => void;
  renderReceipt: (message: Message) => ReactNode;
  scrollRef: RefObject<ScrollView | null>;
  sending: boolean;
  typing: boolean;
  userId: number;
}

export const MessageScreen: FC<Props> = (props) => (
  <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.container}>
    <View style={styles.container}>
      <MessageConversationPanel {...props} />
    </View>
  </KeyboardAvoidingView>
);

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: palette.background,
  },
});
