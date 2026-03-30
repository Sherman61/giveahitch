import { useEffect, useState, useCallback } from 'react';
import { fetchNotificationPreviews, NotificationPreview } from '@/api/notifications';

export function useNotificationFeed(pollEveryMs = 15000) {
  const [notifications, setNotifications] = useState<NotificationPreview[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadAsync = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchNotificationPreviews();
      setNotifications(data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load notifications');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadAsync();
    const interval = setInterval(loadAsync, pollEveryMs);
    return () => clearInterval(interval);
  }, [loadAsync, pollEveryMs]);

  return {
    notifications,
    loading,
    error,
    refresh: loadAsync,
  };
}
