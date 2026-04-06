import { apiClient, getCsrfToken } from './client';

type PresenceHeartbeatResponse = {
  ok: boolean;
  last_online?: string | null;
};

export async function heartbeatPresence(): Promise<PresenceHeartbeatResponse | null> {
  const csrf = getCsrfToken();
  if (!csrf) {
    return null;
  }

  return apiClient.post<PresenceHeartbeatResponse>('/presence/heartbeat.php', {
    csrf,
  });
}
