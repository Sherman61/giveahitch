import { useCallback, useEffect, useState } from 'react';
import { RideSummary } from '@/types/rides';
import { fetchUpcomingRides } from '@/api/rides';

interface UseRidesOptions {
  pollEveryMs?: number;
  mine?: boolean;
  all?: boolean;
}

export function useRides(options: UseRidesOptions = {}) {
  const { pollEveryMs = 20000, mine = false, all = false } = options;
  const [rides, setRides] = useState<RideSummary[]>([]);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const loadAsync = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchUpcomingRides({ mine, all });
      setRides(data);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load rides');
    } finally {
      setLoading(false);
    }
  }, [mine, all]);

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
