# GlitchaHitch Native

React Native (Expo) client for the GlitchaHitch platform. The goal is to mirror the core ride + messaging flows and deliver push notifications for new matches.

## Getting Started
1. Install dependencies:
   ```bash
   cd native-app
   npm install
   ```
2. Create a `.env` file (see `.env.example`) with the API origin and Expo project ID.
3. Run locally:
   ```bash
   npm run start      # opens Expo dev server
   npm run android    # builds for Android (needs emulator/device)
   npm run ios        # builds for iOS (macOS + Xcode)
   ```

## Notifications
- Uses `expo-notifications` for cross-platform push handling.
- `HomeScreen` exposes buttons to request permission and trigger a local notification for quick manual testing.
- Capture the Expo push token displayed in the UI and register it with the backend notification service.

## Development Build & APKs
1. Regenerate native Android project (only when native deps/config change):
   ```bash
   npx expo prebuild --platform android --clean
   ```
2. Install the development build onto a device/emulator:
   ```bash
    npx expo run:android
   ```
   Then start Metro with `npx expo start --dev-client` and press `a` to reload JS into the dev client.
3. Generate APK artifacts directly from Gradle:
   ```bash
   cd android
   ./gradlew assembleDebug       # outputs app/build/outputs/apk/debug/app-debug.apk
   ./gradlew assembleRelease     # outputs app/build/outputs/apk/release/app-release-unsigned.apk
   ```
   Sign + align the release APK before distributing, or prefer an Android App Bundle (AAB) for Play Store uploads.
4. Production-ready builds: configure `eas.json`, keep `google-services.json` in the repo root, then run `eas build -p android --profile production` to obtain a signed AAB for Play Console.

## Next Steps
- Replace the placeholder `scheduleLocalTest` logic with API calls to `/api/notifications.php` or dedicated push relay endpoints.
- Add authenticated screens that mirror the PHP web app (login, ride list, match detail).
- Wire push payloads to in-app navigation by reading `response.notification.request.content.data`.
