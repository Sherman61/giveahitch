import { FC, useCallback, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from 'react-native';
import { PrimaryButton } from '@/components/PrimaryButton';
import { useNotifications } from '@/hooks/useNotifications';
import { sendPushTestNotification } from '@/api/notifications';
import { palette } from '@/constants/colors';
import { spacing } from '@/constants/layout';

interface Props {
  isAuthenticated: boolean;
  onRequireLogin: () => void;
}

type StatusKind = 'idle' | 'success' | 'error';

export const PushNotificationTestScreen: FC<Props> = ({
  isAuthenticated,
  onRequireLogin,
}) => {
  const { expoPushToken, registerAsync } = useNotifications();
  const [registering, setRegistering] = useState(false);
  const [sending, setSending] = useState(false);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const [statusKind, setStatusKind] = useState<StatusKind>('idle');

  const handleRegister = useCallback(async () => {
    if (registering) {
      return;
    }
    setRegistering(true);
    setStatusMessage(null);
    try {
      await registerAsync();
      setStatusKind('success');
      setStatusMessage('Device subscription refreshed with Expo.');
    } catch (error) {
      setStatusKind('error');
      setStatusMessage(
        error instanceof Error
          ? error.message
          : 'Unable to register this device right now.'
      );
    } finally {
      setRegistering(false);
    }
  }, [registerAsync, registering]);

  const handleSendTest = useCallback(async () => {
    if (sending) {
      return;
    }
    if (!expoPushToken) {
      setStatusKind('error');
      setStatusMessage('No push subscription detected. Register first.');
      return;
    }
    setSending(true);
    setStatusMessage(null);
    try {
      await sendPushTestNotification();
      setStatusKind('success');
      setStatusMessage('Test notification queued successfully.');
    } catch (error) {
      setStatusKind('error');
      setStatusMessage(
        error instanceof Error ? error.message : 'Push test failed.'
      );
    } finally {
      setSending(false);
    }
  }, [expoPushToken, sending]);

  if (!isAuthenticated) {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Admin tools</Text>
        <Text style={styles.description}>
          Sign in with an admin account to manage push notifications for users.
        </Text>
        <PrimaryButton label="Go to login" onPress={onRequireLogin} />
      </View>
    );
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <Text style={styles.title}>Push Notifications Test</Text>
      <Text style={styles.description}>
        Inspect this device's subscription status, refresh its Expo token, and trigger a remote push
        test.
      </Text>

      <View style={styles.card}>
        <Text style={styles.cardTitle}>Subscription status</Text>
        <Text style={styles.statusValue}>
          {expoPushToken ? 'Active subscription found' : 'No subscription yet'}
        </Text>
        {expoPushToken && (
          <View style={styles.tokenBox}>
            <Text style={styles.tokenLabel}>Expo push token</Text>
            <Text selectable style={styles.tokenValue}>
              {expoPushToken}
            </Text>
          </View>
        )}
        <PrimaryButton
          label={expoPushToken ? 'Refresh subscription' : 'Register subscription'}
          onPress={handleRegister}
          style={styles.actionButton}
        />
        {registering && (
          <View style={styles.inlineStatus}>
            <ActivityIndicator size="small" color={palette.primary} />
            <Text style={styles.inlineStatusText}>
              Requesting Expo push token...
            </Text>
          </View>
        )}
      </View>

      <View style={styles.card}>
        <Text style={styles.cardTitle}>Remote test</Text>
        <Text style={styles.body}>
          Once a subscription exists, send a push notification to confirm FCM /
          APNs delivery.
        </Text>
        <PrimaryButton
          label="Send push test"
          onPress={handleSendTest}
          style={styles.actionButton}
        />
        {sending && (
          <View style={styles.inlineStatus}>
            <ActivityIndicator size="small" color={palette.primary} />
            <Text style={styles.inlineStatusText}>
              Contacting server...
            </Text>
          </View>
        )}
      </View>

      {statusMessage && (
        <View
          style={[
            styles.feedback,
            statusKind === 'success' ? styles.feedbackSuccess : styles.feedbackError,
          ]}
        >
          <Text style={styles.feedbackText}>{statusMessage}</Text>
        </View>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    padding: spacing.lg,
    gap: spacing.lg,
  },
  centered: {
    flex: 1,
    padding: spacing.lg,
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.md,
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
  },
  description: {
    color: palette.muted,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: spacing.lg,
    gap: spacing.sm,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '700',
  },
  statusValue: {
    fontSize: 14,
    color: palette.text,
  },
  tokenBox: {
    borderWidth: 1,
    borderColor: '#e1e0e0',
    borderRadius: 8,
    padding: spacing.sm,
    backgroundColor: '#fafafa',
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
  actionButton: {
    marginTop: spacing.sm,
  },
  inlineStatus: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  inlineStatusText: {
    color: palette.muted,
  },
  body: {
    color: palette.text,
  },
  feedback: {
    borderRadius: 8,
    padding: spacing.md,
  },
  feedbackSuccess: {
    backgroundColor: '#d1f7c4',
  },
  feedbackError: {
    backgroundColor: '#ffe3e3',
  },
  feedbackText: {
    color: palette.text,
  },
});
