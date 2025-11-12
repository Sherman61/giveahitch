import { useCallback, useEffect, useState } from 'react';
import { RideSummary } from '@/types/rides';
import { fetchUpcomingRides } from '@/api/rides';

export function useRides(pollEveryMs = 20000) {
  const [rides, setRides] = useState<RideSummary[]>([]);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const loadAsync = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchUpcomingRides();
      setRides(data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load rides');
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
    rides,
    loading,
    error,
    refresh: loadAsync,
  };
}
