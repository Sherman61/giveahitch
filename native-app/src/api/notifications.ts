import { apiClient, getCsrfToken } from './client';

export interface PushTokenPayload {
  device_id: string;
  expo_push_token: string;
  platform?: string;
}

export interface NotificationPreview {
  id: number;
  title: string;
  body: string | null;
  created_at: string;
  is_read?: boolean;
}

interface NotificationsResponse {
  ok: boolean;
  items?: {
    id: number;
    title: string;
    body: string | null;
    created_at: string;
    is_read?: boolean;
  }[];
  error?: string;
}

export function registerPushToken(payload: PushTokenPayload) {
  return apiClient.post<{ ok: boolean }>('/mobile/notifications/register-token.php', payload);
}

export async function fetchNotificationPreviews(limit = 5) {
  const response = await apiClient.get<NotificationsResponse>(`/notifications.php?limit=${limit}`);
  if (!response.ok) {
    throw new Error(response.error ?? 'Unable to load notifications.');
  }
  return response.items ?? [];
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
  await apiClient.post('/mobile/push_subscriptions.php', {
    csrf,
    endpoint: payload.endpoint,
    keys: {
      p256dh: `${payload.deviceId ?? 'expo'}:${payload.endpoint}`,
      auth: tokenSlice,
    },
    ua: payload.userAgent ?? `expo-native/${payload.platform ?? 'unknown'}`,
  });
}

export async function sendPushTestNotification(message?: string) {
  const csrf = getCsrfToken();
  if (!csrf) {
    throw new Error('You must be logged in to trigger push tests.');
  }
  return apiClient.post<{ ok: boolean; message?: string }>('/mobile/notifications/push-test.php', {
    csrf,
    ...(message ? { message } : {}),
  });
}
