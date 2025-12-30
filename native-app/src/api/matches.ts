import { apiClient } from './client';

type MatchesResponse = {
  ok: boolean;
  items: ServerMatch[];
};

type ServerMatch = {
  match_id: number;
  ride_id: number;
  match_status: string;
  driver_user_id?: number;
  passenger_user_id?: number;
  driver_display?: string | null;
  passenger_display?: string | null;
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
  driverId?: number;
  passengerId?: number;
  otherUserId?: number;
  otherUserName?: string | null;
  ride?: {
    type: string;
    from: string;
    to: string;
    datetime: string | null;
  };
}

export async function fetchMyMatches(currentUserId?: number | null): Promise<RideMatch[]> {
  const response = await apiClient.get<MatchesResponse>('/my_matches.php');
  if (!response.ok) {
    return [];
  }
  return response.items.map((item) => ({
    matchId: item.match_id,
    rideId: item.ride_id,
    status: item.match_status,
    driverId: item.driver_user_id,
    passengerId: item.passenger_user_id,
    otherUserId:
      currentUserId && item.driver_user_id === currentUserId
        ? item.passenger_user_id
        : currentUserId && item.passenger_user_id === currentUserId
          ? item.driver_user_id
          : item.passenger_user_id ?? item.driver_user_id,
    otherUserName:
      currentUserId && item.driver_user_id === currentUserId
        ? item.passenger_display
        : currentUserId && item.passenger_user_id === currentUserId
          ? item.driver_display
          : item.passenger_display ?? item.driver_display ?? null,
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
