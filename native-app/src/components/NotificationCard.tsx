import { FC } from 'react';
import { Text, StyleSheet } from 'react-native';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import { Card } from './Card';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { NotificationPreview } from '@/api/notifications';

dayjs.extend(relativeTime);

interface Props {
  notification: NotificationPreview;
}

export const NotificationCard: FC<Props> = ({ notification }) => {
  return (
    <Card>
      <Text style={styles.title}>{notification.title}</Text>
      <Text style={styles.body}>{notification.body}</Text>
      <Text style={styles.timestamp}>{dayjs(notification.created_at).fromNow()}</Text>
    </Card>
  );
};

const styles = StyleSheet.create({
  title: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: spacing.xs,
    color: palette.text,
  },
  body: {
    color: palette.muted,
    marginBottom: spacing.sm,
  },
  timestamp: {
    fontSize: 12,
    color: palette.muted,
  },
});
