import { FC, useCallback, useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, RefreshControl, View, TouchableOpacity } from 'react-native';
import { PrimaryButton } from '@/components/PrimaryButton';
import { useRides } from '@/hooks/useRides';
import { useMyMatches } from '@/hooks/useMyMatches';
import { UserProfile } from '@/types/user';
import { RideSummary } from '@/types/rides';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { MyPostedRidesSection } from '@/components/MyPostedRidesSection';
import { MyRespondedRidesSection } from '@/components/MyRespondedRidesSection';
import { RideManageModal } from '@/components/RideManageModal';
import { RideEditModal } from '@/components/RideEditModal';
import { useNotifications } from '@/hooks/useNotifications';
import { useAlerts } from '@/hooks/useAlerts';
import { AlertsBadgeButton } from '@/components/AlertsBadgeButton';
import { PageHeader } from '@/components/PageHeader';

interface Props {
  user?: UserProfile | null;
  onRequestLogin: () => void;
  onOpenAlerts: () => void;
  onOpenAccount: () => void;
  onOpenBrowse: () => void;
  onOpenMessages: () => void;
  onOpenPost: () => void;
}

export const MyRidesScreen: FC<Props> = ({
  user,
  onRequestLogin,
  onOpenAlerts,
  onOpenAccount,
  onOpenBrowse,
  onOpenMessages,
  onOpenPost,
}) => {
  const { rides, loading, error, refresh } = useRides({ mine: true, all: true, pollEveryMs: 30000 });
  const { matchesByRideId, matchesList } = useMyMatches(user ?? null);
  const [managedRide, setManagedRide] = useState<RideSummary | null>(null);
  const [editingRide, setEditingRide] = useState<RideSummary | null>(null);
  const [refreshSignal, setRefreshSignal] = useState(0);
  const { lastNotification } = useNotifications();
  const { unreadCount } = useAlerts(Boolean(user?.id));

  const handleManageRide = useCallback((ride: RideSummary) => {
    setManagedRide(ride);
  }, []);

  const handleExploreRides = useCallback(() => onOpenBrowse(), [onOpenBrowse]);
  const handleRideUpdated = useCallback(() => {
    refresh();
    setRefreshSignal((current) => current + 1);
  }, [refresh]);

  const handleRideDeleted = useCallback(() => {
    setManagedRide(null);
    refresh();
    setRefreshSignal((current) => current + 1);
  }, [refresh]);

  const closeManage = useCallback(() => setManagedRide(null), []);
  const closeEdit = useCallback(() => setEditingRide(null), []);

  useEffect(() => {
    if (lastNotification) {
      refresh();
      setRefreshSignal((current) => current + 1);
    }
  }, [lastNotification, refresh]);

  if (!user) {
    return (
      <View style={[styles.container, styles.centered]}>
        <View style={styles.banner}>
          <Text style={styles.title}>Sign in to view your rides</Text>
          <Text style={styles.subtitle}>Access the rides you posted and the rides you joined.</Text>
          <PrimaryButton label="Log In" onPress={onRequestLogin} />
        </View>
      </View>
    );
  }

  return (
    <>
      <ScrollView
        style={styles.container}
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={loading} onRefresh={refresh} />}
      >
        <PageHeader
          title="My rides"
          subtitle="Track what you posted and what you joined without bouncing around the app."
          rightAccessory={
            <View style={styles.headerActions}>
              <AlertsBadgeButton count={unreadCount} onPress={onOpenAlerts} />
              <TouchableOpacity onPress={onOpenAccount} style={styles.accountButton} activeOpacity={0.82}>
                <Text style={styles.accountButtonText}>Account</Text>
              </TouchableOpacity>
            </View>
          }
        />

        <View style={styles.actionRow}>
          <PrimaryButton label="Browse rides" onPress={onOpenBrowse} variant="secondary" style={styles.actionButton} />
          <PrimaryButton label="Post a ride" onPress={onOpenPost} style={styles.actionButton} />
        </View>
        <PrimaryButton label="Messages" onPress={onOpenMessages} variant="secondary" />

        {error && <Text style={styles.error}>{error}</Text>}

        {!loading && !error && (
          <>
            <MyPostedRidesSection
              rides={rides}
              currentUser={user}
              matchesByRideId={matchesByRideId}
              onRequireLogin={onRequestLogin}
              onManageRide={handleManageRide}
            />
            <MyRespondedRidesSection matches={matchesList} onExploreRides={handleExploreRides} />
          </>
        )}
      </ScrollView>
      <RideManageModal
        ride={managedRide}
        visible={Boolean(managedRide)}
        onClose={closeManage}
        onEdit={(ride) => setEditingRide(ride)}
        onRideUpdated={handleRideUpdated}
        onRideDeleted={handleRideDeleted}
        refreshSignal={refreshSignal}
        onNavigate={(key) => {
          if (key === 'messages') onOpenMessages();
          if (key === 'rides') onOpenBrowse();
          if (key === 'postRide') onOpenPost();
        }}
      />
      <RideEditModal
        ride={editingRide}
        visible={Boolean(editingRide)}
        onClose={closeEdit}
        onSaved={handleRideUpdated}
      />
    </>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: palette.background,
  },
  content: {
    padding: spacing.lg,
    paddingBottom: spacing.xl,
    gap: spacing.md,
  },
  centered: {
    justifyContent: 'center',
    alignItems: 'center',
    padding: spacing.lg,
  },
  banner: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.lg,
    width: '100%',
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
    gap: spacing.sm,
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
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  accountButton: {
    backgroundColor: '#edf3f9',
    borderRadius: 999,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
  },
  accountButtonText: {
    color: palette.text,
    fontSize: 13,
    fontWeight: '700',
  },
  actionRow: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  actionButton: {
    flex: 1,
  },
});
