import { useCallback, useEffect, useState } from 'react';
import { apiClient, getCsrfToken } from '@/api/client';

export interface AlertItem {
  id: number;
  title: string;
  body: string;
  type: string;
  created_at: string;
  read_at?: string | null;
}

interface AlertsResponse {
  ok: boolean;
  items?: AlertItem[];
  unread_count?: number;
  error?: string;
}

export function useAlerts(enabled: boolean) {
  const [items, setItems] = useState<AlertItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [unreadCount, setUnreadCount] = useState(0);

  const loadAsync = useCallback(async () => {
    if (!enabled) {
      setItems([]);
      return;
    }
    setLoading(true);
    try {
      const response = await apiClient.get<AlertsResponse>('/notifications.php');
      if (!response.ok) {
        throw new Error(response.error ?? 'Unable to load alerts');
      }
      setItems(response.items ?? []);
      setUnreadCount(response.unread_count ?? 0);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load alerts');
    } finally {
      setLoading(false);
    }
  }, [enabled]);

  useEffect(() => {
    loadAsync();
  }, [loadAsync]);

  const markAllRead = useCallback(async () => {
    const csrf = getCsrfToken();
    if (!csrf) {
      throw new Error('Please log in to update alerts.');
    }
    await apiClient.post('/notifications.php', {
      action: 'mark_read',
      all: true,
      csrf,
    });
    setUnreadCount(0);
    loadAsync();
  }, [loadAsync]);

  return { items, unreadCount, loading, error, refresh: loadAsync, markAllRead };
}
