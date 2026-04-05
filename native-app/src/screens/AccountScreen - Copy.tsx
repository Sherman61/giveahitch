import { FC } from 'react';
import { Linking, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { PageHeader } from '@/components/PageHeader';
import { PrimaryButton } from '@/components/PrimaryButton';
import { palette } from '@/constants/colors';
import { radius, shadow, spacing } from '@/constants/layout';
import { UserProfile } from '@/types/user';

interface Props {
  user: UserProfile;
  onBack: () => void;
  onOpenProfile: () => void;
  onOpenSettings: () => void;
  onOpenRatings: () => void;
  onOpenAdminPush: () => void;
  onLogout: () => void;
}

export const AccountScreen: FC<Props> = ({
  user,
  onBack,
  onOpenProfile,
  onOpenSettings,
  onOpenRatings,
  onOpenAdminPush,
  onLogout,
}) => {
  const openProfileOnWeb = () => {
    Linking.openURL('https://glitchahitch.com/profile.php').catch(() => {});
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <PageHeader title="Account" subtitle="Manage your profile, notifications, and ride reputation." onBack={onBack} />

      <View style={styles.heroCard}>
        <Text style={styles.heroTitle}>{user.displayName ?? user.name}</Text>
        <Text style={styles.heroSubtitle}>{user.email}</Text>
        <View style={styles.heroActions}>
          <PrimaryButton label="Profile" onPress={onOpenProfile} style={styles.heroButton} />
          <PrimaryButton label="Settings" onPress={onOpenSettings} variant="secondary" style={styles.heroButton} />
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Tools</Text>
        <MenuRow label="Rate rides" description="Leave feedback for recent trips." onPress={onOpenRatings} />
        <MenuRow label="Edit on web" description="Open the full profile editor in your browser." onPress={openProfileOnWeb} />
        <MenuRow label="Push test" description="Admin notification tools." onPress={onOpenAdminPush} />
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Session</Text>
        <Text style={styles.supportText}>Need help? WhatsApp 8452441202 or use the web dashboard for support.</Text>
        <PrimaryButton label="Log out" onPress={onLogout} variant="secondary" />
      </View>
    </ScrollView>
  );
};

const MenuRow: FC<{ label: string; description: string; onPress: () => void }> = ({ label, description, onPress }) => (
  <TouchableOpacity style={styles.menuRow} onPress={onPress} activeOpacity={0.82}>
    <View style={styles.menuText}>
      <Text style={styles.menuLabel}>{label}</Text>
      <Text style={styles.menuDescription}>{description}</Text>
    </View>
    <Text style={styles.menuChevron}>{'>'}</Text>
  </TouchableOpacity>
);

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
  heroCard: {
    backgroundColor: '#ffffff',
    borderRadius: radius.md,
    padding: spacing.lg,
    gap: spacing.sm,
    ...shadow,
  },
  heroTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: palette.text,
  },
  heroSubtitle: {
    color: palette.muted,
  },
  heroActions: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginTop: spacing.sm,
  },
  heroButton: {
    flex: 1,
  },
  section: {
    backgroundColor: '#ffffff',
    borderRadius: radius.md,
    padding: spacing.lg,
    gap: spacing.sm,
    ...shadow,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: palette.text,
    marginBottom: spacing.xs,
  },
  menuRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: spacing.sm,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: '#dde3ea',
  },
  menuText: {
    flex: 1,
    paddingRight: spacing.sm,
  },
  menuLabel: {
    fontSize: 15,
    fontWeight: '600',
    color: palette.text,
  },
  menuDescription: {
    color: palette.muted,
    marginTop: 2,
  },
  menuChevron: {
    fontSize: 24,
    color: palette.muted,
  },
  supportText: {
    color: palette.text,
    lineHeight: 20,
  },
});
