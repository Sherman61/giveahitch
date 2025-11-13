import { apiClient, getCsrfToken } from './client';

export interface PushTokenPayload {
  device_id: string;
  expo_push_token: string;
  platform?: string;
}

export interface NotificationPreview {
  id: string;
  title: string;
  body: string;
  created_at: string;
}

export function registerPushToken(payload: PushTokenPayload) {
  return apiClient.post<{ ok: boolean }>('/notifications/register-token.php', payload);
}

export function fetchNotificationPreviews() {
  return apiClient.get<NotificationPreview[]>('/notifications/recent.php');
}

export async function savePushSubscription(payload: {
  endpoint: string;
  deviceId?: string | null;
  platform?: string;
  userAgent?: string;
}) {
  const csrf = getCsrfToken();
  if (!csrf) {
    return;
  }
  const tokenSlice = payload.endpoint.slice(0, 32) || payload.endpoint;
  await apiClient.post('/push_subscriptions.php', {
    csrf,
    endpoint: payload.endpoint,
    keys: {
      p256dh: `${payload.deviceId ?? 'expo'}:${payload.endpoint}`,
      auth: tokenSlice,
    },
    ua: payload.userAgent ?? `expo-native/${payload.platform ?? 'unknown'}`,
  });
}
