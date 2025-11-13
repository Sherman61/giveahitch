import { FC, useEffect } from 'react';
import { ScrollView, StyleSheet, Text, View, RefreshControl } from 'react-native';
import { useAlerts } from '@/hooks/useAlerts';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { PrimaryButton } from '@/components/PrimaryButton';
import { useNotifications } from '@/hooks/useNotifications';

export const AlertsScreen: FC = () => {
  const { items, unreadCount, loading, error, refresh, markAllRead } = useAlerts(true);
  const { lastNotification } = useNotifications();

  useEffect(() => {
    if (lastNotification) {
      refresh();
    }
  }, [lastNotification, refresh]);

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={loading} onRefresh={refresh} />}
    >
      <View style={styles.headerRow}>
        <View>
          <Text style={styles.title}>Alerts</Text>
          <Text style={styles.subtitle}>{unreadCount} unread notifications</Text>
        </View>
        <PrimaryButton label="Mark all read" onPress={markAllRead} variant="secondary" />
      </View>

      {error && <Text style={styles.error}>{error}</Text>}

      {items.map((alert) => (
        <View key={alert.id} style={[styles.card, !alert.read_at && styles.unread]}>
          <Text style={styles.cardTitle}>{alert.title || 'Ride update'}</Text>
          <Text style={styles.cardBody}>{alert.body}</Text>
          <Text style={styles.cardMeta}>{new Date(alert.created_at).toLocaleString()}</Text>
        </View>
      ))}

      {!items.length && !loading && <Text style={styles.empty}>No alerts yet.</Text>}
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
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  title: {
    fontSize: 26,
    fontWeight: '700',
  },
  subtitle: {
    color: palette.muted,
  },
  error: {
    color: palette.danger,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.md,
    gap: spacing.xs,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  unread: {
    borderLeftWidth: 4,
    borderLeftColor: palette.primary,
  },
  cardTitle: {
    fontWeight: '700',
  },
  cardBody: {
    color: palette.text,
  },
  cardMeta: {
    color: palette.muted,
    fontSize: 12,
  },
  empty: {
    textAlign: 'center',
    color: palette.muted,
  },
});
