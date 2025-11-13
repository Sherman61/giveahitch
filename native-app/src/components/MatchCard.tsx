import { FC } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { RideMatch } from '@/api/matches';

dayjs.extend(relativeTime);

interface Props {
  match: RideMatch;
}

const statusCopy: Record<string, string> = {
  pending: 'Requested',
  accepted: 'Accepted',
  confirmed: 'Confirmed',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

export const MatchCard: FC<Props> = ({ match }) => {
  return (
    <View style={styles.card}>
      <Text style={styles.route}>
        {(match.ride?.from ?? 'Origin') + ' -> ' + (match.ride?.to ?? 'Destination')}
      </Text>
      <Text style={styles.meta}>
        {match.ride?.datetime ? dayjs(match.ride.datetime).format('MMM D, h:mm A') : 'Flexible time'}
      </Text>
      <Text style={styles.status}>{statusCopy[match.status] ?? match.status}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.md,
    marginBottom: spacing.sm,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  route: {
    fontWeight: '600',
    fontSize: 16,
  },
  meta: {
    color: palette.muted,
    marginVertical: 4,
  },
  status: {
    color: palette.primary,
    fontWeight: '600',
  },
});
