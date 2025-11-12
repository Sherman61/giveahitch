import { FC } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { RideCard, RideSummary } from './RideCard';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  rides: RideSummary[];
  emptyMessage?: string;
}

export const RideList: FC<Props> = ({ rides, emptyMessage }) => {
  if (!rides.length) {
    return <Text style={styles.empty}>{emptyMessage ?? 'No rides available.'}</Text>;
  }

  return (
    <View style={styles.list}>
      {rides.map((ride) => (
        <RideCard key={ride.id} ride={ride} />
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
