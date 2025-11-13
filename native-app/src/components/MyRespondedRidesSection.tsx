import { FC } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { RideMatch } from '@/api/matches';
import { spacing } from '@/constants/layout';
import { palette } from '@/constants/colors';
import { MatchCard } from './MatchCard';
import { PrimaryButton } from './PrimaryButton';

interface Props {
  matches: RideMatch[];
  onExploreRides: () => void;
}

export const MyRespondedRidesSection: FC<Props> = ({ matches, onExploreRides }) => {
  const hasMatches = matches.length > 0;

  return (
    <View style={styles.card}>
      <Text style={styles.heading}>Rides I responded to</Text>
      <Text style={styles.subheading}>Requests and offers you've joined recently.</Text>

      {hasMatches ? (
        matches.map((match) => <MatchCard key={match.matchId} match={match} />)
      ) : (
        <View style={styles.emptyState}>
          <Text style={styles.emptyText}>You haven't responded to any rides yet.</Text>
          <PrimaryButton label="Find rides" onPress={onExploreRides} variant="secondary" />
        </View>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.lg,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
    gap: spacing.md,
  },
  heading: {
    fontSize: 20,
    fontWeight: '700',
    color: palette.text,
  },
  subheading: {
    color: palette.muted,
  },
  emptyState: {
    alignItems: 'flex-start',
    gap: spacing.sm,
  },
  emptyText: {
    color: palette.muted,
  },
});
