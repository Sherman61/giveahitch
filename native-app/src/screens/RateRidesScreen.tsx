import { FC } from 'react';
import { ScrollView, StyleSheet, Text, View, Linking } from 'react-native';
import { useMyMatches } from '@/hooks/useMyMatches';
import { UserProfile } from '@/types/user';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { MatchCard } from '@/components/MatchCard';
import { PrimaryButton } from '@/components/PrimaryButton';

interface Props {
  user?: UserProfile | null;
  onRequestLogin: () => void;
}

const RATEABLE_STATUSES = new Set(['completed', 'confirmed', 'in_progress']);

export const RateRidesScreen: FC<Props> = ({ user, onRequestLogin }) => {
  const { matchesList } = useMyMatches(user ?? null);

  if (!user) {
    return (
      <View style={[styles.container, styles.centered]}>
        <Text style={styles.title}>Sign in to rate rides</Text>
        <Text style={styles.subtitle}>Share feedback with drivers and passengers.</Text>
        <PrimaryButton label="Log In" onPress={onRequestLogin} />
      </View>
    );
  }

  const pendingRatings = matchesList.filter((match) => RATEABLE_STATUSES.has(match.status));

  const openRating = (matchId: number) => {
    Linking.openURL(`https://glitchahitch.com/rate_rides.php?match=${matchId}`).catch(() => {});
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Rate rides</Text>
      <Text style={styles.subtitle}>Thank your drivers or passengers after each trip.</Text>

      {pendingRatings.length === 0 && (
        <Text style={styles.empty}>No rides waiting for ratings right now.</Text>
      )}

      {pendingRatings.map((match) => (
        <View key={match.matchId} style={styles.card}>
          <MatchCard match={match} />
          <PrimaryButton label="Rate this ride" onPress={() => openRating(match.matchId)} />
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
