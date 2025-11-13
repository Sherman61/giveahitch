import { FC } from 'react';
import { ScrollView, StyleSheet, Text, View, TouchableOpacity, Linking } from 'react-native';
import { ProfileDetails } from '@/api/profile';
import { useProfileDetails } from '@/hooks/useProfileDetails';
import { UserProfile } from '@/types/user';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { PrimaryButton } from '@/components/PrimaryButton';

interface Props {
  user: UserProfile;
  onLogout: () => void;
}

export const ProfileScreen: FC<Props> = ({ user, onLogout }) => {
  const { profile, loading, error } = useProfileDetails(Boolean(user));
  const details: ProfileDetails =
    profile ?? {
      id: user.id,
      email: user.email,
      name: user.name,
      displayName: user.displayName,
      username: null,
      score: undefined,
      createdAt: undefined,
      contact: { phone: null, whatsapp: null },
      stats: {
        ridesOffered: 0,
        ridesRequested: 0,
        ridesGiven: 0,
        ridesReceived: 0,
      },
      ratings: {},
    };

  const openProfile = () => {
    Linking.openURL('https://glitchahitch.com/profile.php').catch(() => {});
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <Text style={styles.title}>Profile</Text>
      <Text style={styles.subtitle}>Manage your contact, stats, and reputation.</Text>

      <View style={styles.card}>
        <Text style={styles.cardTitle}>{details.displayName ?? details.name}</Text>
        <Text style={styles.cardSubtitle}>{details.email}</Text>
        {details.username && <Text style={styles.metaText}>@{details.username}</Text>}
        <TouchableOpacity onPress={openProfile}>
          <Text style={styles.link}>Edit on web</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Contact</Text>
        <Text style={styles.bodyText}>Phone: {details.contact.phone ?? 'Not provided'}</Text>
        <Text style={styles.bodyText}>WhatsApp: {details.contact.whatsapp ?? 'Not provided'}</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Stats</Text>
        <View style={styles.statsRow}>
          <Stat label="Offered" value={details.stats.ridesOffered} />
          <Stat label="Requests" value={details.stats.ridesRequested} />
        </View>
        <View style={styles.statsRow}>
          <Stat label="Given" value={details.stats.ridesGiven} />
          <Stat label="Received" value={details.stats.ridesReceived} />
        </View>
      </View>

      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Ratings</Text>
        <Text style={styles.bodyText}>
          Driver rating:{' '}
          {details.ratings.driver
            ? `${details.ratings.driver.average ?? '—'} (${details.ratings.driver.count})`
            : 'No ratings yet'}
        </Text>
        <Text style={styles.bodyText}>
          Passenger rating:{' '}
          {details.ratings.passenger
            ? `${details.ratings.passenger.average ?? '—'} (${details.ratings.passenger.count})`
            : 'No ratings yet'}
        </Text>
      </View>

      {error && <Text style={styles.error}>{error}</Text>}
      {loading && <Text style={styles.metaText}>Refreshing profile…</Text>}

      <PrimaryButton label="Log out" onPress={onLogout} />
    </ScrollView>
  );
};

const Stat: FC<{ label: string; value: number }> = ({ label, value }) => (
  <View style={styles.stat}>
    <Text style={styles.statValue}>{value}</Text>
    <Text style={styles.statLabel}>{label}</Text>
  </View>
);

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
  title: {
    fontSize: 28,
    fontWeight: '700',
  },
  subtitle: {
    color: palette.muted,
    marginBottom: spacing.sm,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.lg,
    gap: spacing.xs,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  cardTitle: {
    fontSize: 20,
    fontWeight: '700',
  },
  cardSubtitle: {
    color: palette.muted,
  },
  metaText: {
    color: palette.muted,
    fontSize: 12,
  },
  link: {
    color: palette.primary,
    fontWeight: '600',
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    marginBottom: spacing.xs,
  },
  bodyText: {
    color: palette.text,
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: spacing.sm,
  },
  stat: {
    flex: 1,
    alignItems: 'center',
  },
  statValue: {
    fontSize: 18,
    fontWeight: '700',
  },
  statLabel: {
    color: palette.muted,
    fontSize: 12,
  },
  error: {
    color: palette.danger,
  },
});
