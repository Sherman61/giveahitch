import { FC, useCallback, useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, RefreshControl, View } from 'react-native';
import { PrimaryButton } from '@/components/PrimaryButton';
import { QuickNavStrip } from '@/components/QuickNavStrip';
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

interface Props {
  user?: UserProfile | null;
  onNavigate: (key: string) => void;
  onRequestLogin: () => void;
}

export const MyRidesScreen: FC<Props> = ({ user, onNavigate, onRequestLogin }) => {
  const { rides, loading, error, refresh } = useRides({ mine: true, all: true, pollEveryMs: 30000 });
  const { matchesByRideId, matchesList } = useMyMatches(user ?? null);
  const [managedRide, setManagedRide] = useState<RideSummary | null>(null);
  const [editingRide, setEditingRide] = useState<RideSummary | null>(null);
  const [manageRefreshTick, setManageRefreshTick] = useState(0);
  const { lastNotification } = useNotifications();

  const handleManageRide = useCallback(
    (ride: RideSummary) => {
      setManagedRide(ride);
    },
    [],
  );

  const handleExploreRides = useCallback(() => onNavigate('rides'), [onNavigate]);
  const handleRideUpdated = useCallback(() => {
    refresh();
    setManageRefreshTick((tick) => tick + 1);
  }, [refresh]);

  const handleRideDeleted = useCallback(() => {
    setManagedRide(null);
    refresh();
  }, [refresh]);

  const closeManage = useCallback(() => setManagedRide(null), []);
  const closeEdit = useCallback(() => setEditingRide(null), []);

  useEffect(() => {
    if (lastNotification) {
      refresh();
    }
  }, [lastNotification, refresh]);

  if (!user) {
    return (
      <View style={[styles.container, styles.centered]}>
        <View style={styles.banner}>
          <Text style={styles.title}>Sign in to view your rides</Text>
          <Text style={styles.subtitle}>Access rides you've posted or joined.</Text>
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
      <Text style={styles.title}>My rides</Text>
      <Text style={styles.subtitle}>Manage your ride offers and requests.</Text>

      <QuickNavStrip
        items={[
          { key: 'rides', title: 'Explore', subtitle: 'Browse new rides' },
          { key: 'postRide', title: 'Post Ride', subtitle: 'Share availability' },
          { key: 'alerts', title: 'Alerts', subtitle: 'Ride updates' },
          { key: 'messages', title: 'Messages', subtitle: 'Conversations' },
          { key: 'rate', title: 'Rate rides', subtitle: 'Share feedback' },
          { key: 'settings', title: 'Settings', subtitle: 'Alerts & links' },
          { key: 'login', title: 'Account', subtitle: 'Profile & logout' },
          { key: 'editProfile', title: 'Edit profile', subtitle: 'Update contact' },
        ]}
        onSelect={onNavigate}
        refreshSignal={manageRefreshTick}
      />

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
        onNavigate={onNavigate}
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
});
