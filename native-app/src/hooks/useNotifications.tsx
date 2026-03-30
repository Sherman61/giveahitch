import {
  useEffect,
  useState,
  useCallback,
  createContext,
  useContext,
  ReactNode,
} from "react";
import { Platform } from "react-native";
import Constants from "expo-constants";
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

const uuidPattern =
  /^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/;
const ANDROID_CHANNEL_ID = "default";

function isAndroidPushConfigured(): boolean {
  const extra =
    (Constants.expoConfig?.extra as {
      googleServicesFileConfigured?: boolean;
    }) ?? {};

  return Boolean(extra.googleServicesFileConfigured);
}

function resolveExpoProjectId(): string | null {
  const extra =
    (Constants.expoConfig?.extra as {
      expoProjectId?: string;
      eas?: { projectId?: string };
    }) ?? {};

  const candidate =
    Constants.easConfig?.projectId ||
    extra.expoProjectId ||
    extra.eas?.projectId ||
    process.env.EXPO_PUBLIC_PROJECT_ID ||
    null;

  if (candidate && uuidPattern.test(candidate)) {
    return candidate;
  }

  return null;
}

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

  const ensureAndroidChannelAsync = useCallback(async () => {
    if (Platform.OS !== "android") {
      return;
    }

    await Notifications.setNotificationChannelAsync(ANDROID_CHANNEL_ID, {
      name: "Default",
      importance: Notifications.AndroidImportance.MAX,
      vibrationPattern: [0, 250, 250, 250],
      lightColor: "#2563eb",
    });
  }, []);

  const registerAsync = useCallback(async () => {
    if (!Device.isDevice) {
      console.warn("Push notifications only work on physical devices.");
      return null;
    }

    await ensureAndroidChannelAsync();

    if (Platform.OS === "android" && !isAndroidPushConfigured()) {
      throw new Error(
        "Android push is not configured yet. Add google-services.json and rebuild the app."
      );
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

    const projectId = resolveExpoProjectId();
    if (!projectId) {
      console.warn(
        "Expo project ID missing. Set EXPO_PUBLIC_PROJECT_ID to enable push notifications."
      );
      return null;
    }

    const tokenData = await Notifications.getExpoPushTokenAsync({ projectId });
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
            Application as unknown as {
              getAndroidIdAsync: () => Promise<string | null>;
            }
          ).getAndroidIdAsync()
        : null;
    const deviceId =
      iosVendorId ??
      androidId ??
      Device.osInternalBuildId ??
      Device.osBuildId ??
      Device.modelName ??
      "unknown-device";

    await registerPushToken({
      device_id: deviceId,
      expo_push_token: token,
      platform: Platform.OS,
    });

    await savePushSubscription({
      endpoint: token,
      deviceId,
      platform: Platform.OS,
      userAgent: Device.modelName ?? undefined,
    });

    return token;
  }, [ensureAndroidChannelAsync]);

  const scheduleLocalTest = useCallback(async () => {
    await ensureAndroidChannelAsync();
    await Notifications.scheduleNotificationAsync({
      content: {
        title: "Glitch A Hitch",
        body: "Local notification test successful!",
        data: { route: "Rides" },
        ...(Platform.OS === "android"
          ? { sound: "default" as const }
          : {}),
      },
      trigger: {
        type: Notifications.SchedulableTriggerInputTypes.TIME_INTERVAL,
        seconds: 2,
        ...(Platform.OS === "android"
          ? { channelId: ANDROID_CHANNEL_ID }
          : {}),
      },
    });
  }, [ensureAndroidChannelAsync]);

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
