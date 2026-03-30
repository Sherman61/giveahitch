import { FC } from 'react';
import { ScrollView, StyleSheet, Text, RefreshControl, View, TouchableOpacity } from 'react-native';
import { RideList } from '@/components/RideList';
import { PrimaryButton } from '@/components/PrimaryButton';
import { useRides } from '@/hooks/useRides';
import { useMyMatches } from '@/hooks/useMyMatches';
import { UserProfile } from '@/types/user';
import { AlertsBadgeButton } from '@/components/AlertsBadgeButton';
import { useAlerts } from '@/hooks/useAlerts';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { PageHeader } from '@/components/PageHeader';

interface Props {
  user?: UserProfile | null;
  onRequestLogin: () => void;
  onOpenAlerts: () => void;
  onOpenAccount: () => void;
  onOpenMyRides: () => void;
  onOpenPost: () => void;
}

export const RidesScreen: FC<Props> = ({
  user,
  onRequestLogin,
  onOpenAlerts,
  onOpenAccount,
  onOpenMyRides,
  onOpenPost,
}) => {
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
      <PageHeader
        title="Browse rides"
        subtitle={
          user
            ? `Welcome back, ${user.name}. Find the next ride quickly.`
            : 'Browse first, then sign in when you are ready to book, post, or message.'
        }
        rightAccessory={
          <View style={styles.headerActions}>
            <AlertsBadgeButton count={unreadCount} onPress={onOpenAlerts} />
            <TouchableOpacity onPress={onOpenAccount} style={styles.accountButton} activeOpacity={0.82}>
              <Text style={styles.accountButtonText}>{user ? 'Account' : 'Log In'}</Text>
            </TouchableOpacity>
          </View>
        }
      />

      {!user && (
        <View style={styles.banner}>
          <Text style={styles.bannerText}>
            Sign in to keep your ride history, alerts, and accepted matches in sync across devices.
          </Text>
          <PrimaryButton label="Log In" variant="secondary" onPress={onRequestLogin} />
        </View>
      )}

      <View style={styles.ctaRow}>
        <PrimaryButton label="Post a ride" onPress={onOpenPost} style={styles.ctaButton} />
        <PrimaryButton label="My rides" onPress={onOpenMyRides} variant="secondary" style={styles.ctaButton} />
      </View>

      {error && <Text style={styles.error}>{error}</Text>}

      {!loading && !error && (
        <RideList
          rides={rides}
          currentUser={user ?? null}
          matchesByRideId={matchesByRideId}
          onRequireLogin={onRequestLogin}
          onRideAccepted={handleRideAccepted}
          emptyMessage="No rides found right now. Pull to refresh or post a new ride."
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
    fontWeight: '700',
    fontSize: 13,
  },
  banner: {
    backgroundColor: '#eef5fb',
    padding: spacing.lg,
    borderRadius: 12,
    marginBottom: spacing.lg,
    gap: spacing.sm,
  },
  bannerText: {
    color: palette.text,
    lineHeight: 20,
  },
  ctaRow: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginBottom: spacing.lg,
  },
  ctaButton: {
    flex: 1,
  },
  error: {
    color: palette.danger,
    marginBottom: spacing.md,
  },
});
