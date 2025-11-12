import { apiClient } from './client';

export interface PushTokenPayload {
  device_id: string;
  expo_push_token: string;
}

export interface NotificationPreview {
  id: string;
  title: string;
  body: string;
  created_at: string;
}

export function registerPushToken(payload: PushTokenPayload) {
  return apiClient.post<{ success: boolean }>('/notifications/register-token.php', payload);
}

export function fetchNotificationPreviews() {
  return apiClient.get<NotificationPreview[]>('/notifications/recent.php');
}
