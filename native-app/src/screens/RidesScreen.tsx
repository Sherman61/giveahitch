import { FC } from 'react';
import { ScrollView, StyleSheet, Text, RefreshControl, View } from 'react-native';
import { RideList } from '@/components/RideList';
import { PrimaryButton } from '@/components/PrimaryButton';
import { useRides } from '@/hooks/useRides';
import { UserProfile } from '@/types/user';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  user?: UserProfile | null;
  onRequestLogin: () => void;
}

export const RidesScreen: FC<Props> = ({ user, onRequestLogin }) => {
  const { rides, loading, error, refresh } = useRides();

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={loading} onRefresh={refresh} />}
    >
      <Text style={styles.title}>Rides</Text>
      <Text style={styles.subtitle}>
        {user ? `Welcome back, ${user.name}.` : 'Sign in to sync rides across devices.'}
      </Text>

      {!user && (
        <View style={styles.banner}>
          <Text style={styles.bannerText}>Log in to see your assigned rides, matches, and history.</Text>
          <PrimaryButton label="Log In" variant="secondary" onPress={onRequestLogin} />
        </View>
      )}

      {error && <Text style={styles.error}>{error}</Text>}

      {!loading && !error && (
        <RideList
          rides={rides}
          currentUser={user ?? null}
          onRequireLogin={onRequestLogin}
          onRideAccepted={refresh}
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
