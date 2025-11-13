import { apiClient } from './client';

type MatchesResponse = {
  ok: boolean;
  items: ServerMatch[];
};

type ServerMatch = {
  match_id: number;
  ride_id: number;
  match_status: string;
  ride: {
    type: string;
    from_text: string;
    to_text: string;
    ride_datetime: string | null;
  };
};

export interface RideMatch {
  matchId: number;
  rideId: number;
  status: string;
  ride?: {
    type: string;
    from: string;
    to: string;
    datetime: string | null;
  };
}

export async function fetchMyMatches(): Promise<RideMatch[]> {
  const response = await apiClient.get<MatchesResponse>('/my_matches.php');
  if (!response.ok) {
    return [];
  }
  return response.items.map((item) => ({
    matchId: item.match_id,
    rideId: item.ride_id,
    status: item.match_status,
    ride: item.ride
      ? {
          type: item.ride.type,
          from: item.ride.from_text,
          to: item.ride.to_text,
          datetime: item.ride.ride_datetime,
        }
      : undefined,
  }));
}
