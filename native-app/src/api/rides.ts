import { apiClient, getCsrfToken } from './client';
import { RideSummary } from '@/types/rides';

type RideListResponse = {
  ok: boolean;
  items: ServerRide[];
};

type ServerRide = {
  id: number;
  user_id?: number | null;
  type: 'offer' | 'request';
  from_text: string;
  to_text: string;
  ride_datetime: string | null;
  ride_end_datetime: string | null;
  seats: number;
  package_only: 0 | 1;
  note: string | null;
  phone: string | null;
  whatsapp: string | null;
  status: string;
  created_at: string;
  owner_display: string | null;
  contact_visibility?: {
    visible?: boolean;
    reason?: string;
  };
  contact_notice?: string | null;
  match_counts?: Record<string, number>;
  confirmed?: {
    match_id: number;
    status: string;
  } | null;
};

function mapRide(serverRide: ServerRide): RideSummary {
  return {
    id: serverRide.id,
    ownerId: serverRide.user_id ?? null,
    type: serverRide.type,
    origin: serverRide.from_text,
    destination: serverRide.to_text,
    departureTime: serverRide.ride_datetime,
    endTime: serverRide.ride_end_datetime,
    seats: serverRide.seats,
    packageOnly: Boolean(serverRide.package_only),
    note: serverRide.note ?? undefined,
    phone: serverRide.phone ?? undefined,
    whatsapp: serverRide.whatsapp ?? undefined,
    status: serverRide.status,
    createdAt: serverRide.created_at,
    ownerName: serverRide.owner_display ?? 'Community member',
    contactVisibility: serverRide.contact_visibility,
    contactNotice: serverRide.contact_notice ?? undefined,
    matchCounts: serverRide.match_counts ?? {},
    confirmed: serverRide.confirmed ?? undefined,
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

export async function acceptRide(rideId: number) {
  const csrf = getCsrfToken();
  if (!csrf) {
    throw new Error('Please log in to accept rides.');
  }
  const response = await apiClient.post<{ ok: boolean; error?: string }>('/match_create.php', {
    ride_id: rideId,
    csrf,
  });
  if (!response.ok) {
    throw new Error(response.error ?? 'Unable to accept ride');
  }
  return response;
}
