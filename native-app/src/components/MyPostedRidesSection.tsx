import { FC } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { RideSummary } from '@/types/rides';
import { UserProfile } from '@/types/user';
import { MatchesByRideId } from '@/hooks/useMyMatches';
import { RideList } from './RideList';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  rides: RideSummary[];
  currentUser: UserProfile;
  matchesByRideId: MatchesByRideId;
  onRequireLogin: () => void;
  onManageRide: (ride: RideSummary) => void;
}

export const MyPostedRidesSection: FC<Props> = ({
  rides,
  currentUser,
  matchesByRideId,
  onRequireLogin,
  onManageRide,
}) => {
  return (
    <View style={styles.card}>
      <Text style={styles.heading}>Rides I posted</Text>
      <Text style={styles.subheading}>Requests and offers created from this account.</Text>
      <RideList
        rides={rides}
        currentUser={currentUser}
        matchesByRideId={matchesByRideId}
        onRequireLogin={onRequireLogin}
        onRideAccepted={() => {}}
        onManageRide={onManageRide}
        emptyMessage="You haven't posted any rides yet."
      />
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
});
