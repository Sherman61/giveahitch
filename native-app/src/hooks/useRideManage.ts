import { useCallback, useEffect, useState } from 'react';
import { fetchRideManageDetails, RideManageDetails } from '@/api/rides';

export function useRideManage(rideId: number | null, enabled: boolean) {
  const [details, setDetails] = useState<RideManageDetails | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadAsync = useCallback(async () => {
    if (!rideId || !enabled) {
      return;
    }
    setLoading(true);
    try {
      const next = await fetchRideManageDetails(rideId);
      setDetails(next);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load responses');
    } finally {
      setLoading(false);
    }
  }, [rideId, enabled]);

  useEffect(() => {
    if (enabled && rideId) {
      loadAsync();
    } else if (!enabled) {
      setDetails(null);
      setError(null);
    }
  }, [rideId, enabled, loadAsync]);

  return {
    details,
    loading,
    error,
    refresh: loadAsync,
  };
}
