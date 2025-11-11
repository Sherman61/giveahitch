import { FC, useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { NotificationBadge } from '@/components/NotificationBadge';
import { useNotifications } from '@/hooks/useNotifications';

export const HomeScreen: FC = () => {
  const { expoPushToken, lastNotification, registerAsync, scheduleLocalTest } = useNotifications();
  const [permissionRequested, setPermissionRequested] = useState(false);

  const onRegister = async () => {
    await registerAsync();
    setPermissionRequested(true);
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>GlitchaHitch Mobile</Text>
      <Text style={styles.subtitle}>Manage rides, matches, and alerts on the go.</Text>

      <TouchableOpacity style={styles.button} onPress={onRegister}>
        <Text style={styles.buttonText}>Enable Push Notifications</Text>
      </TouchableOpacity>

      <TouchableOpacity style={[styles.button, styles.secondary]} onPress={scheduleLocalTest}>
        <Text style={styles.buttonText}>Send Test Notification</Text>
        <NotificationBadge count={lastNotification ? 1 : 0} />
      </TouchableOpacity>

      {permissionRequested && !expoPushToken && (
        <Text style={styles.warning}>Grant notification permissions in device settings.</Text>
      )}
      {expoPushToken && (
        <View style={styles.tokenBox}>
          <Text style={styles.tokenLabel}>Expo Push Token</Text>
          <Text selectable style={styles.tokenText}>{expoPushToken}</Text>
        </View>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
    padding: 24,
    justifyContent: 'center',
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 16,
    marginBottom: 24,
    color: '#6c757d',
  },
  button: {
    backgroundColor: '#0069d9',
    paddingVertical: 14,
    paddingHorizontal: 18,
    borderRadius: 8,
    marginBottom: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  secondary: {
    backgroundColor: '#0d6efd',
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
  },
  warning: {
    color: '#dc3545',
    marginTop: 8,
  },
  tokenBox: {
    marginTop: 24,
    padding: 16,
    backgroundColor: '#fff',
    borderRadius: 8,
    shadowColor: '#000',
    shadowOpacity: 0.05,
    shadowOffset: { width: 0, height: 4 },
    shadowRadius: 6,
    elevation: 2,
  },
  tokenLabel: {
    fontSize: 12,
    color: '#6c757d',
    marginBottom: 4,
    textTransform: 'uppercase',
  },
  tokenText: {
    fontSize: 12,
    color: '#212529',
  },
});
