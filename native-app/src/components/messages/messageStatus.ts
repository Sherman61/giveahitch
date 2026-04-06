import dayjs from 'dayjs';

type ConversationPresenceOptions = {
  activeUserId: number | null;
  activeLastSeenAt: string | null;
  connectionState: string;
  hasPresenceSnapshot: boolean;
  isActiveUserOnline: boolean;
  isTyping: boolean;
};

export function getConversationPresenceText({
  activeUserId,
  activeLastSeenAt,
  connectionState,
  hasPresenceSnapshot,
  isActiveUserOnline,
  isTyping,
}: ConversationPresenceOptions): string {
  if (activeUserId && isTyping) {
    return 'Typing...';
  }

  if (!activeUserId) {
    return connectionState === 'connected'
      ? 'Select a conversation'
      : connectionState === 'connecting'
        ? 'Connecting...'
        : 'Status unavailable';
  }

  if (isActiveUserOnline) {
    return 'Online';
  }

  if (activeLastSeenAt) {
    return `Last online ${dayjs(activeLastSeenAt).fromNow()}`;
  }

  if (connectionState === 'connected' && hasPresenceSnapshot) {
    return 'Offline';
  }

  if (connectionState === 'connecting' || connectionState === 'connected') {
    return 'Checking status...';
  }

  return 'Status unavailable';
}
