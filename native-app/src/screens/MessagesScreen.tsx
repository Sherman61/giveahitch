import { FC, useEffect } from 'react';
import { ScrollView, StyleSheet, Text, View, Linking } from 'react-native';
import { useMyMatches } from '@/hooks/useMyMatches';
import { UserProfile } from '@/types/user';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { MatchCard } from '@/components/MatchCard';
import { PrimaryButton } from '@/components/PrimaryButton';
import { useNotifications } from '@/hooks/useNotifications';

interface Props {
  user?: UserProfile | null;
  onRequestLogin: () => void;
}

export const MessagesScreen: FC<Props> = ({ user, onRequestLogin }) => {
  const { matchesList, refreshMatches } = useMyMatches(user ?? null);
  const { lastNotification } = useNotifications();

  useEffect(() => {
    if (lastNotification) {
      refreshMatches();
    }
  }, [lastNotification, refreshMatches]);

  if (!user) {
    return (
      <View style={[styles.container, styles.centered]}>
        <Text style={styles.title}>Sign in to view messages</Text>
        <Text style={styles.subtitle}>Chat with drivers or passengers after you accept a ride.</Text>
        <PrimaryButton label="Log In" onPress={onRequestLogin} />
      </View>
    );
  }

  const openMessages = (matchId: number) => {
    Linking.openURL(`https://glitchahitch.com/messages.php?match=${matchId}`).catch(() => {});
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Messages</Text>
      <Text style={styles.subtitle}>Conversations with your ride matches.</Text>

      {matchesList.length === 0 && (
        <Text style={styles.empty}>You don't have any ride matches yet.</Text>
      )}

      {matchesList.map((match) => (
        <View key={match.matchId} style={styles.card}>
          <MatchCard match={match} />
          <PrimaryButton label="Open chat" onPress={() => openMessages(match.matchId)} />
        </View>
      ))}
    </ScrollView>
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
});
