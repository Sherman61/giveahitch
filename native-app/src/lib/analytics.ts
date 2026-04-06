import { NativeModules, Platform } from 'react-native';

type FirebaseAnalyticsNativeModule = {
  logScreenView: (screenName: string, screenClass?: string | null) => Promise<void>;
};

const nativeModule = NativeModules.FirebaseAnalyticsModule as FirebaseAnalyticsNativeModule | undefined;

let lastTrackedScreenName: string | null = null;

export async function trackScreenView(screenName: string, screenClass?: string): Promise<void> {
  if (!screenName || lastTrackedScreenName === screenName) {
    return;
  }

  lastTrackedScreenName = screenName;

  if (Platform.OS !== 'android' || !nativeModule?.logScreenView) {
    console.log('[analytics] screen_view', { screenName, screenClass: screenClass ?? screenName });
    return;
  }

  try {
    await nativeModule.logScreenView(screenName, screenClass ?? screenName);
    console.log('[analytics] screen_view', { screenName, screenClass: screenClass ?? screenName });
  } catch (error) {
    console.warn('[analytics] screen_view_failed', {
      screenName,
      screenClass: screenClass ?? screenName,
      error: error instanceof Error ? error.message : String(error),
    });
  }
}
