import {
  useEffect,
  useState,
  useCallback,
  createContext,
  useContext,
  ReactNode,
} from "react";
import { Platform } from "react-native";
import * as Notifications from "expo-notifications";
import * as Device from "expo-device";
import * as Application from "expo-application";
import { registerPushToken, savePushSubscription } from "@/api/notifications";

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: false,
    shouldSetBadge: false,
  }),
});

interface NotificationsContextValue {
  expoPushToken: string | null;
  lastNotification: Notifications.Notification | null;
  registerAsync: () => Promise<string | null>;
  scheduleLocalTest: () => Promise<void>;
}

const NotificationsContext = createContext<NotificationsContextValue | null>(
  null
);

function useNotificationsInternal(): NotificationsContextValue {
  const [expoPushToken, setExpoPushToken] = useState<string | null>(null);
  const [lastNotification, setLastNotification] =
    useState<Notifications.Notification | null>(null);

  useEffect(() => {
    const sub =
      Notifications.addNotificationReceivedListener(setLastNotification);
    const respSub = Notifications.addNotificationResponseReceivedListener(
      (response) => {
        console.log("Notification tapped:", response.actionIdentifier);
      }
    );
    return () => {
      sub.remove();
      respSub.remove();
    };
  }, []);

  const registerAsync = useCallback(async () => {
    if (!Device.isDevice) {
      console.warn("Push notifications only work on physical devices.");
      return null;
    }

    const { status: existingStatus } =
      await Notifications.getPermissionsAsync();
    let finalStatus = existingStatus;
    if (existingStatus !== "granted") {
      const { status } = await Notifications.requestPermissionsAsync();
      finalStatus = status;
    }
    if (finalStatus !== "granted") {
      console.warn("Push notification permission denied");
      return null;
    }

    const tokenData = await Notifications.getExpoPushTokenAsync();
    const token = tokenData.data;
    setExpoPushToken(token);

    const iosVendorId =
      Platform.OS === "ios" && Application.getIosIdForVendorAsync
        ? await Application.getIosIdForVendorAsync()
        : null;
    const androidId =
      Platform.OS === "android" &&
      typeof (Application as Record<string, unknown>).getAndroidIdAsync ===
        "function"
        ? await (
            Application as { getAndroidIdAsync: () => Promise<string | null> }
          ).getAndroidIdAsync()
        : null;
    const deviceId =
      iosVendorId ??
      androidId ??
      Device.osInternalBuildId ??
      Device.osBuildId ??
      Device.modelName ??
      "unknown-device";

    let lastError: unknown = null;
    try {
      await registerPushToken({
        device_id: deviceId,
        expo_push_token: token,
        platform: Platform.OS,
      });
    } catch (error) {
      lastError = error;
      console.warn("Failed to register Expo token on server", error);
    }

    try {
      await savePushSubscription({
        endpoint: token,
        deviceId,
        platform: Platform.OS,
        userAgent: Device.modelName ?? undefined,
      });
    } catch (error) {
      lastError = error;
      console.warn("Failed to store push subscription", error);
    }

    if (lastError) {
      throw lastError;
    }

    return token;
  }, []);

  const scheduleLocalTest = useCallback(async () => {
    await Notifications.scheduleNotificationAsync({
      content: {
        title: "Glitch A Hitch",
        body: "Local notification test successful!",
        data: { route: "Rides" },
      },
      trigger: { seconds: 2 },
    });
  }, []);

  return { expoPushToken, lastNotification, registerAsync, scheduleLocalTest };
}

export function NotificationsProvider({ children }: { children: ReactNode }) {
  const value = useNotificationsInternal();
  return (
    <NotificationsContext.Provider value={value}>
      {children}
    </NotificationsContext.Provider>
  );
}

export function useNotifications(): NotificationsContextValue {
  const context = useContext(NotificationsContext);
  if (!context) {
    throw new Error(
      "useNotifications must be used within NotificationsProvider"
    );
  }
  return context;
}
