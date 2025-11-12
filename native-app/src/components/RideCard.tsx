import { FC } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import dayjs from 'dayjs';
import { Card } from './Card';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

export interface RideSummary {
  id: string;
  origin: string;
  destination: string;
  departureTime: string;
  driverName: string;
  status: 'scheduled' | 'completed' | 'cancelled' | 'awaiting';
}

const statusCopy: Record<RideSummary['status'], string> = {
  scheduled: 'Scheduled',
  completed: 'Completed',
  cancelled: 'Cancelled',
  awaiting: 'Awaiting Driver',
};

const statusColor: Record<RideSummary['status'], string> = {
  scheduled: palette.primary,
  completed: palette.muted,
  cancelled: palette.danger,
  awaiting: palette.accent,
};

interface Props {
  ride: RideSummary;
}

export const RideCard: FC<Props> = ({ ride }) => {
  return (
    <Card>
      <View style={styles.header}>
        <Text style={styles.route}>{`${ride.origin} -> ${ride.destination}`}</Text>
        <Text style={[styles.status, { color: statusColor[ride.status] }]}>{statusCopy[ride.status]}</Text>
      </View>
      <Text style={styles.time}>{dayjs(ride.departureTime).format('MMM D, h:mm A')}</Text>
      <Text style={styles.driver}>Driver: {ride.driverName}</Text>
    </Card>
  );
};

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.xs,
  },
  route: {
    fontSize: 16,
    fontWeight: '700',
    color: palette.text,
  },
  status: {
    fontWeight: '600',
  },
  time: {
    color: palette.muted,
    marginBottom: spacing.xs,
  },
  driver: {
    color: palette.text,
    fontWeight: '500',
  },
});
