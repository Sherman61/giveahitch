import { useEffect, useState, useCallback } from 'react';
import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import * as Application from 'expo-application';
import { registerPushToken } from '@/api/notifications';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: false,
    shouldSetBadge: false,
  }),
});

export function useNotifications() {
  const [expoPushToken, setExpoPushToken] = useState<string | null>(null);
  const [lastNotification, setLastNotification] = useState<Notifications.Notification | null>(null);

  useEffect(() => {
    const sub = Notifications.addNotificationReceivedListener(setLastNotification);
    const respSub = Notifications.addNotificationResponseReceivedListener((response) => {
      console.log('Notification tapped:', response.actionIdentifier);
    });
    return () => {
      sub.remove();
      respSub.remove();
    };
  }, []);

  const registerAsync = useCallback(async () => {
    if (!Device.isDevice) {
      console.warn('Push notifications only work on physical devices.');
      return null;
    }

    const { status: existingStatus } = await Notifications.getPermissionsAsync();
    let finalStatus = existingStatus;
    if (existingStatus !== 'granted') {
      const { status } = await Notifications.requestPermissionsAsync();
      finalStatus = status;
    }
    if (finalStatus !== 'granted') {
      console.warn('Push notification permission denied');
      return null;
    }

    const tokenData = await Notifications.getExpoPushTokenAsync();
    const token = tokenData.data;
    setExpoPushToken(token);

    try {
      const iosVendorId =
        Platform.OS === 'ios' && Application.getIosIdForVendorAsync
          ? await Application.getIosIdForVendorAsync()
          : null;
      const androidId =
        Platform.OS === 'android' && typeof (Application as Record<string, unknown>).getAndroidIdAsync === 'function'
          ? await (Application as { getAndroidIdAsync: () => Promise<string | null> }).getAndroidIdAsync()
          : null;
      await registerPushToken({
        device_id:
          iosVendorId ??
          androidId ??
          Device.osInternalBuildId ??
          Device.osBuildId ??
          Device.modelName ??
          'unknown-device',
        expo_push_token: token,
      });
    } catch (error) {
      console.warn('Failed to register push token', error);
    }

    return token;
  }, []);

  const scheduleLocalTest = useCallback(async () => {
    await Notifications.scheduleNotificationAsync({
      content: {
        title: 'GlitchaHitch',
        body: 'Your ride is ready. Tap to review the match.',
        data: { route: 'Rides' },
      },
      trigger: { seconds: 2 },
    });
  }, []);

  return { expoPushToken, lastNotification, registerAsync, scheduleLocalTest };
}
