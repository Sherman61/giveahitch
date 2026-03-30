import { FC, useState } from 'react';
import { ScrollView, StyleSheet, Text, View, TouchableOpacity, Linking } from 'react-native';
import { PrimaryButton } from '@/components/PrimaryButton';
import { useNotifications } from '@/hooks/useNotifications';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';
import { EditProfileScreen } from './EditProfileScreen';
import { PageHeader } from '@/components/PageHeader';

interface Props {
  onBack: () => void;
}

export const SettingsScreen: FC<Props> = ({ onBack }) => {
  const { expoPushToken, registerAsync, scheduleLocalTest } = useNotifications();
  const [notificationError, setNotificationError] = useState<string | null>(null);

  const openLink = (path: string) => {
    Linking.openURL(`https://glitchahitch.com${path}`).catch(() => {});
  };

  const handleRegister = async () => {
    try {
      setNotificationError(null);
      await registerAsync();
    } catch (err) {
      setNotificationError(err instanceof Error ? err.message : 'Unable to enable notifications.');
    }
  };

  const handleLocalTest = async () => {
    try {
      setNotificationError(null);
      await scheduleLocalTest();
    } catch (err) {
      setNotificationError(err instanceof Error ? err.message : 'Unable to schedule the test notification.');
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <PageHeader title="Settings" subtitle="Control alerts, profile details, and support resources." onBack={onBack} />

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Alerts and notifications</Text>
        <PrimaryButton label="Enable push notifications" onPress={handleRegister} />
        <TouchableOpacity style={styles.secondaryButton} onPress={handleLocalTest}>
          <Text style={styles.secondaryButtonText}>Send test notification</Text>
        </TouchableOpacity>
        {notificationError && <Text style={styles.errorText}>{notificationError}</Text>}
        {expoPushToken && (
          <View style={styles.tokenBox}>
            <Text style={styles.tokenLabel}>Expo push token</Text>
            <Text selectable style={styles.tokenValue}>
              {expoPushToken}
            </Text>
          </View>
        )}
      </View>

      <EditProfileScreen />

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Quick links</Text>
        <TouchableOpacity style={styles.linkRow} onPress={() => openLink('/rides.php')}>
          <Text style={styles.linkText}>View rides on the web</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.linkRow} onPress={() => openLink('/my_rides.php')}>
          <Text style={styles.linkText}>Manage my rides</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.linkRow} onPress={() => openLink('/notifications.php')}>
          <Text style={styles.linkText}>Notification history</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Support</Text>
        <Text style={styles.bodyText}>
          Need help? WhatsApp <Text style={styles.linkText}>8452441202</Text> or open the Help Center from the web dashboard.
        </Text>
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    backgroundColor: palette.background,
  },
  content: {
    padding: spacing.lg,
    gap: spacing.lg,
    paddingBottom: spacing.xl,
  },
  section: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.lg,
    gap: spacing.sm,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '700',
  },
  secondaryButton: {
    paddingVertical: spacing.sm,
    alignItems: 'center',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#ced4da',
  },
  secondaryButtonText: {
    color: palette.primary,
    fontWeight: '600',
  },
  tokenBox: {
    borderWidth: 1,
    borderColor: '#e0e0e0',
    borderRadius: 8,
    padding: spacing.sm,
  },
  tokenLabel: {
    fontSize: 12,
    color: palette.muted,
    marginBottom: 4,
  },
  tokenValue: {
    fontSize: 12,
    color: palette.text,
  },
  linkRow: {
    paddingVertical: spacing.sm,
  },
  linkText: {
    color: palette.primary,
    fontWeight: '600',
  },
  bodyText: {
    color: palette.text,
  },
  errorText: {
    color: palette.danger,
  },
});
