import { FC } from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import type { MessageThread } from '@/api/messages';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

dayjs.extend(relativeTime);

interface Props {
  emptyText: string;
  loading: boolean;
  onOpenConversation: (otherUserId: number) => void;
  onRefresh: () => void;
  threads: MessageThread[];
}

export const RecentConversationsPanel: FC<Props> = ({
  emptyText,
  loading,
  onOpenConversation,
  onRefresh,
  threads,
}) => (
  <View style={styles.section}>
    <View style={styles.sectionHeader}>
      <Text style={styles.sectionTitle}>Recent conversations</Text>
      <TouchableOpacity onPress={onRefresh} disabled={loading}>
        <Text style={styles.link}>{loading ? 'Refreshing...' : 'Refresh'}</Text>
      </TouchableOpacity>
    </View>

    {threads.length === 0 && <Text style={styles.empty}>{emptyText}</Text>}

    {threads.map((thread) => {
      return (
        <TouchableOpacity
          key={thread.id}
          style={styles.threadRow}
          onPress={() => onOpenConversation(thread.otherUser.id)}
          activeOpacity={0.82}
        >
          <View style={styles.threadText}>
            <Text style={styles.threadName}>
              {thread.otherUser.displayName ?? thread.otherUser.username ?? 'Member'}
            </Text>
            <Text style={styles.threadPreview}>
              {thread.lastMessage?.body ? thread.lastMessage.body.slice(0, 80) : 'No messages yet.'}
            </Text>
          </View>
          <View style={styles.threadMeta}>
            {thread.unreadCount ? <Text style={styles.unreadBadge}>{thread.unreadCount}</Text> : null}
            {thread.lastMessageAt ? (
              <Text style={styles.threadTime}>{dayjs(thread.lastMessageAt).fromNow()}</Text>
            ) : null}
          </View>
        </TouchableOpacity>
      );
    })}
  </View>
);

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
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    gap: spacing.sm,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
  },
  link: {
    color: palette.primary,
    fontWeight: '600',
  },
  empty: {
    color: palette.muted,
  },
  threadRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: spacing.sm,
    paddingVertical: spacing.sm,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#e8ecef',
  },
  threadText: {
    flex: 1,
    gap: 4,
  },
  threadName: {
    fontWeight: '700',
    color: palette.text,
  },
  threadPreview: {
    color: palette.muted,
  },
  threadMeta: {
    alignItems: 'flex-end',
    gap: 4,
  },
  unreadBadge: {
    minWidth: 22,
    textAlign: 'center',
    backgroundColor: palette.primary,
    color: '#fff',
    borderRadius: 999,
    paddingHorizontal: 6,
    paddingVertical: 2,
    overflow: 'hidden',
    fontSize: 12,
    fontWeight: '700',
  },
  threadTime: {
    color: palette.muted,
    fontSize: 12,
  },
});
