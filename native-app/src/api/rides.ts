import { apiClient, getCsrfToken } from './client';
import { RideSummary } from '@/components/RideCard';

type RideListResponse = {
  ok: boolean;
  items: ServerRide[];
};

type ServerRide = {
  id: number;
  from_text: string;
  to_text: string;
  ride_datetime: string | null;
  ride_end_datetime: string | null;
  created_at: string;
  owner_display: string;
  status: string;
};

function toStatus(status: string): RideSummary['status'] {
  switch (status) {
    case 'open':
      return 'awaiting';
    case 'cancelled':
      return 'cancelled';
    case 'completed':
    case 'closed':
      return 'completed';
    default:
      return 'scheduled';
  }
}

function mapRide(serverRide: ServerRide): RideSummary {
  return {
    id: String(serverRide.id),
    origin: serverRide.from_text,
    destination: serverRide.to_text,
    departureTime: serverRide.ride_datetime ?? serverRide.created_at,
    driverName: serverRide.owner_display,
    status: toStatus(serverRide.status),
  };
}

export interface CreateRidePayload {
  type: 'offer' | 'request';
  from_text: string;
  to_text: string;
  ride_datetime?: string | null;
  ride_end_datetime?: string | null;
  seats: number;
  phone?: string;
  whatsapp?: string;
  note?: string;
}

export async function fetchUpcomingRides() {
  const response = await apiClient.get<RideListResponse>('/ride_list.php');
  if (!response.ok) {
    throw new Error('Unable to load rides');
  }
  return response.items.map(mapRide);
}

export async function createRide(payload: CreateRidePayload) {
  const csrf = getCsrfToken();
  if (!csrf) {
    throw new Error('You must be logged in to post a ride.');
  }

  const response = await apiClient.post<{ ok: boolean; id?: number; error?: string }>('/ride_create.php', {
    ...payload,
    csrf,
  });

  if (!response.ok) {
    throw new Error(response.error ?? 'Unable to create ride');
  }
  return response;
}
