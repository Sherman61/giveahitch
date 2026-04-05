import { FC } from 'react';
import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import type { RideMatch } from '@/api/matches';
import type { MessageThread } from '@/api/messages';
import { MatchCard } from '@/components/MatchCard';
import { RecentConversationsPanel } from '@/components/messages/RecentConversationsPanel';
import { PageHeader } from '@/components/PageHeader';
import { PrimaryButton } from '@/components/PrimaryButton';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import type { UserProfile } from '@/types/user';

interface Props {
  user?: UserProfile | null;
  matchesList: RideMatch[];
  messagingError: string | null;
  loadingThreads: boolean;
  onOpenAccount: () => void;
  onOpenConversation: (otherUserId: number) => void;
  onRefreshMatches: () => void;
  onRefreshThreads: () => void;
  onRequestLogin: () => void;
  threads: MessageThread[];
}

export const ChatsScreen: FC<Props> = ({
  user,
  matchesList,
  messagingError,
  loadingThreads,
  onOpenAccount,
  onOpenConversation,
  onRefreshMatches,
  onRefreshThreads,
  onRequestLogin,
  threads,
}) => {
  if (!user) {
    return (
      <View style={[styles.container, styles.centered]}>
        <PageHeader
          title="Chats"
          subtitle="Your inbox stays focused on accepted ride conversations."
          rightAccessory={
            <TouchableOpacity onPress={onOpenAccount} style={styles.accountButton} activeOpacity={0.82}>
              <Text style={styles.accountButtonText}>Log In</Text>
            </TouchableOpacity>
          }
        />
        <Text style={styles.subtitle}>Chat with drivers or passengers after you accept a ride.</Text>
        <PrimaryButton label="Log In" onPress={onRequestLogin} />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
      >
        <PageHeader
          title="Chats"
          subtitle="Open a recent thread or start from an accepted ride match."
          rightAccessory={
            <TouchableOpacity onPress={onOpenAccount} style={styles.accountButton} activeOpacity={0.82}>
              <Text style={styles.accountButtonText}>Account</Text>
            </TouchableOpacity>
          }
        />

        {messagingError ? <Text style={styles.error}>{messagingError}</Text> : null}

        <RecentConversationsPanel
          emptyText="No conversations yet. Start by messaging a ride match."
          loading={loadingThreads}
          onOpenConversation={onOpenConversation}
          onRefresh={onRefreshThreads}
          threads={threads}
        />

        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Ride matches</Text>
            <TouchableOpacity onPress={onRefreshMatches} activeOpacity={0.82}>
              <Text style={styles.link}>Refresh</Text>
            </TouchableOpacity>
          </View>

          {matchesList.length === 0 ? <Text style={styles.empty}>You do not have any ride matches yet.</Text> : null}

          {matchesList.map((match) => (
            <View key={match.matchId} style={styles.card}>
              <MatchCard match={match} />
              <PrimaryButton
                label={match.otherUserName ? `Message ${match.otherUserName}` : 'Open chat'}
                onPress={() => {
                  if (match.otherUserId) {
                    onOpenConversation(match.otherUserId);
                  }
                }}
              />
            </View>
          ))}
        </View>
      </ScrollView>
    </View>
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
    padding: spacing.lg,
    justifyContent: 'center',
  },
  subtitle: {
    color: palette.muted,
  },
  error: {
    color: palette.danger,
  },
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
    color: palette.text,
  },
  link: {
    color: palette.primary,
    fontWeight: '600',
  },
  empty: {
    color: palette.muted,
  },
  card: {
    gap: spacing.sm,
  },
  accountButton: {
    backgroundColor: '#edf3f9',
    borderRadius: 999,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
  },
  accountButtonText: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '700',
  },
});
