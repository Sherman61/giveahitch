import { FC, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, RefreshControl } from 'react-native';
import { NotificationBadge } from '@/components/NotificationBadge';
import { PrimaryButton } from '@/components/PrimaryButton';
import { Card } from '@/components/Card';
import { NotificationCard } from '@/components/NotificationCard';
import { useNotifications } from '@/hooks/useNotifications';
import { useNotificationFeed } from '@/hooks/useNotificationFeed';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

export const HomeScreen: FC = () => {
  const { expoPushToken, lastNotification, registerAsync, scheduleLocalTest } = useNotifications();
  const { notifications, loading, refresh } = useNotificationFeed();
  const [permissionRequested, setPermissionRequested] = useState(false);

  const onRegister = async () => {
    await registerAsync();
    setPermissionRequested(true);
  };

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={loading} onRefresh={refresh} />}
    >
      <Text style={styles.title}>GlitchaHitch Mobile</Text>
      <Text style={styles.subtitle}>Manage rides, matches, and alerts on the go.</Text>

      <Card>
        <PrimaryButton label="Enable Push Notifications" onPress={onRegister} style={styles.cardButton} />
        <PrimaryButton
          label="Send Test Notification"
          onPress={scheduleLocalTest}
          variant="secondary"
          accessory={<NotificationBadge count={lastNotification ? 1 : 0} />}
        />
        {permissionRequested && !expoPushToken && (
          <Text style={styles.warning}>Grant notification permissions in device settings.</Text>
        )}
        {expoPushToken && (
          <View style={styles.tokenBox}>
            <Text style={styles.tokenLabel}>Expo Push Token</Text>
            <Text selectable style={styles.tokenText}>
              {expoPushToken}
            </Text>
          </View>
        )}
      </Card>

      <Text style={styles.sectionTitle}>Recent Notifications</Text>
      {notifications.map((item) => (
        <NotificationCard key={item.id} notification={item} />
      ))}
      {!notifications.length && !loading && (
        <Text style={styles.emptyState}>No notifications yet. Trigger one from the dashboard.</Text>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: palette.background,
  },
  content: {
    padding: spacing.lg,
    paddingBottom: spacing.xl,
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    marginBottom: spacing.lg,
    color: palette.muted,
  },
  warning: {
    color: palette.danger,
    marginTop: spacing.sm,
  },
  tokenBox: {
    marginTop: spacing.md,
    borderWidth: 1,
    borderColor: '#e9ecef',
    borderRadius: 8,
    padding: spacing.md,
  },
  tokenLabel: {
    fontSize: 12,
    color: palette.muted,
    marginBottom: 4,
    textTransform: 'uppercase',
  },
  tokenText: {
    fontSize: 12,
    color: palette.text,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: palette.text,
    marginBottom: spacing.md,
  },
  emptyState: {
    color: palette.muted,
    fontStyle: 'italic',
  },
  cardButton: {
    marginBottom: spacing.md,
  },
});
