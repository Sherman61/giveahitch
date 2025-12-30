import { useCallback, useEffect, useState } from 'react';
import { fetchMyMatches, RideMatch } from '@/api/matches';
import { UserProfile } from '@/types/user';

export type MatchesByRideId = Record<number, RideMatch>;

export function useMyMatches(user: UserProfile | null) {
  const [matchesByRideId, setMatchesByRideId] = useState<MatchesByRideId>({});
  const [matchesList, setMatchesList] = useState<RideMatch[]>([]);
  const userId = user?.id ?? null;

  const loadAsync = useCallback(async () => {
    if (!userId) {
      setMatchesByRideId({});
      setMatchesList([]);
      return;
    }
    const matches = await fetchMyMatches(userId);
    setMatchesList(matches);
    const next: MatchesByRideId = {};
    matches.forEach((match) => {
      next[match.rideId] = match;
    });
    setMatchesByRideId(next);
  }, [userId]);

  useEffect(() => {
    if (!userId) {
      setMatchesByRideId({});
      return;
    }
    loadAsync().catch(() => {});
  }, [loadAsync, userId]);

  const markRideAccepted = useCallback((rideId: number, status: string) => {
    if (!userId) {
      return;
    }
    setMatchesByRideId((prev) => ({
      ...prev,
      [rideId]: { rideId, matchId: prev[rideId]?.matchId ?? Date.now(), status, ride: prev[rideId]?.ride },
    }));
    setMatchesList((prev) => {
      const existing = prev.find((match) => match.rideId === rideId);
      if (existing) {
        return prev.map((match) => (match.rideId === rideId ? { ...match, status } : match));
      }
      return [...prev, { rideId, matchId: Date.now(), status }];
    });
  }, [userId]);

  return {
    matchesByRideId,
    matchesList,
    refreshMatches: loadAsync,
    markRideAccepted,
  };
}
