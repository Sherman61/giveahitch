import { FC } from 'react';
import { ChatsScreen } from '@/screens/ChatsScreen';
import { MessageScreen } from '@/screens/MessageScreen';
import { useMessagesController } from '@/hooks/useMessagesController';
import { useMyMatches } from '@/hooks/useMyMatches';
import { useScreenAnalytics } from '@/hooks/useScreenAnalytics';
import type { UserProfile } from '@/types/user';

interface Props {
  user?: UserProfile | null;
  onRequestLogin: () => void;
  onOpenAccount: () => void;
}

export const MessagesScreen: FC<Props> = ({ user, onRequestLogin, onOpenAccount }) => {
  const { matchesList, refreshMatches } = useMyMatches(user ?? null);
  const {
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
  } = useMessagesController({
    user,
    refreshMatches,
  });

  useScreenAnalytics(activeThread ? 'MessageConversationScreen' : 'MessagesInboxScreen');

  if (activeThread) {
    return (
      <MessageScreen
        activePresenceText={activePresenceText}
        activeThread={activeThread}
        canMessage={canMessage}
        compose={compose}
        connectionState={connectionState}
        isActiveUserOnline={isActiveUserOnline}
        loadingConversation={loadingConversation}
        messages={messages}
        messagingError={messagingError}
        onBack={closeConversation}
        onChangeCompose={setCompose}
        onSend={handleSend}
        renderReceipt={renderReceipt}
        scrollRef={scrollRef}
        sending={sending}
        typing={isTyping}
        userId={user?.id ?? 0}
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
