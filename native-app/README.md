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

## Next Steps
- Replace the placeholder `scheduleLocalTest` logic with API calls to `/api/notifications.php` or dedicated push relay endpoints.
- Add authenticated screens that mirror the PHP web app (login, ride list, match detail).
- Wire push payloads to in-app navigation by reading `response.notification.request.content.data`.
