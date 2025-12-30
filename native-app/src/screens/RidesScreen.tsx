import { FC } from 'react';
import { ScrollView, StyleSheet, Text, RefreshControl, View } from 'react-native';
import { RideList } from '@/components/RideList';
import { PrimaryButton } from '@/components/PrimaryButton';
import { QuickNavStrip } from '@/components/QuickNavStrip';
import { useRides } from '@/hooks/useRides';
import { useMyMatches } from '@/hooks/useMyMatches';
import { UserProfile } from '@/types/user';
import { AlertsBadgeButton } from '@/components/AlertsBadgeButton';
import { useAlerts } from '@/hooks/useAlerts';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  user?: UserProfile | null;
  onRequestLogin: () => void;
  onNavigate: (key: string) => void;
}

export const RidesScreen: FC<Props> = ({ user, onRequestLogin, onNavigate }) => {
  const { rides, loading, error, refresh } = useRides();
  const { matchesByRideId, refreshMatches, markRideAccepted } = useMyMatches(user ?? null);
  const { unreadCount } = useAlerts(Boolean(user?.id));

  const handleRideAccepted = (rideId: number, status: string) => {
    markRideAccepted(rideId, status);
    refresh();
    refreshMatches();
  };

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={loading} onRefresh={refresh} />}
    >
      <View style={styles.headerRow}>
        <View>
          <Text style={styles.title}>Rides</Text>
          <Text style={styles.subtitle}>
            {user ? `Welcome back, ${user.name}.` : 'Sign in to sync rides across devices.'}
          </Text>
        </View>
        <AlertsBadgeButton count={unreadCount} onPress={() => onNavigate('alerts')} />
      </View>

      {!user && (
        <View style={styles.banner}>
          <Text style={styles.bannerText}>Log in to see your assigned rides, matches, and history.</Text>
          <PrimaryButton label="Log In" variant="secondary" onPress={onRequestLogin} />
        </View>
      )}

      <QuickNavStrip
        items={[
          { key: 'myRides', title: 'My Rides', subtitle: 'View your posts' },
          { key: 'postRide', title: 'Post Ride', subtitle: 'request or offer' },
          
        ]}
        onSelect={onNavigate}
      />

      {error && <Text style={styles.error}>{error}</Text>}

      {!loading && !error && (
        <RideList
          rides={rides}
          currentUser={user ?? null}
          matchesByRideId={matchesByRideId}
          onRequireLogin={onRequestLogin}
          onRideAccepted={handleRideAccepted}
          emptyMessage="No rides found. Pull to refresh or schedule a new ride on the dashboard."
        />
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
    marginBottom: spacing.xs,
  },
  subtitle: {
    fontSize: 16,
    color: palette.muted,
    marginBottom: spacing.lg,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.lg,
  },
  banner: {
    backgroundColor: '#e9f2ff',
    padding: spacing.lg,
    borderRadius: 12,
    marginBottom: spacing.lg,
    gap: spacing.sm,
  },
  bannerText: {
    color: palette.text,
  },
  error: {
    color: palette.danger,
    marginBottom: spacing.md,
  },
});
