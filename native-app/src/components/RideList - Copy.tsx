import { FC } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { RideCard } from './RideCard';
import { RideSummary } from '@/types/rides';
import { UserProfile } from '@/types/user';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { RideMatch } from '@/api/matches';

interface Props {
  rides: RideSummary[];
  emptyMessage?: string;
  currentUser?: UserProfile | null;
  matchesByRideId?: Record<number, RideMatch>;
  onRequireLogin: () => void;
  onRideAccepted: (rideId: number, status: string) => void;
  onManageRide?: (ride: RideSummary) => void;
}

export const RideList: FC<Props> = ({
  rides,
  emptyMessage,
  currentUser,
  matchesByRideId,
  onRequireLogin,
  onRideAccepted,
  onManageRide,
}) => {
  if (!rides.length) {
    return <Text style={styles.empty}>{emptyMessage ?? 'No rides available.'}</Text>;
  }

  const currentUserId = currentUser?.id ?? null;

  return (
    <View style={styles.list}>
      {rides.map((ride) => (
        <RideCard
          key={ride.id}
          ride={ride}
          currentUserId={currentUserId}
          matchStatus={matchesByRideId?.[ride.id]?.status}
          onRequireLogin={onRequireLogin}
          onRideAccepted={onRideAccepted}
          onManageRide={onManageRide}
        />
      ))}
    </View>
  );
};

const styles = StyleSheet.create({
  list: {
    gap: spacing.md,
  },
  empty: {
    color: palette.muted,
    fontStyle: 'italic',
  },
});
