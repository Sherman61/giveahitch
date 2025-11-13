import { apiClient, getCsrfToken } from './client';
import { RideSummary } from '@/types/rides';

type RideListResponse = {
  ok: boolean;
  items: ServerRide[];
};

type RideListOptions = {
  mine?: boolean;
  all?: boolean;
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

type RideManageApiResponse = {
  ok: boolean;
  error?: string;
  ride: {
    id: number;
    type: 'offer' | 'request';
    status: string;
  };
  pending: ServerRideResponder[];
  confirmed?: ServerRideConfirmed | null;
};

type ServerRideResponder = {
  match_id: number;
  status: string;
  created_at: string;
  requester_id: number;
  requester_name: string | null;
  requester_phone?: string | null;
  requester_whatsapp?: string | null;
  requester_contact_notice?: string | null;
  requester_contact_visibility?: {
    visible?: boolean;
    reason?: string;
  };
};

type ServerRideConfirmed = {
  match_id: number;
  status: string;
  created_at: string;
  other_id: number;
  other_name: string | null;
  other_phone?: string | null;
  other_whatsapp?: string | null;
  other_contact_notice?: string | null;
  other_contact_visibility?: {
    visible?: boolean;
    reason?: string;
  };
};

export interface RideResponder {
  matchId: number;
  status: string;
  requestedAt: string;
  userId: number;
  name: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  contactNotice?: string | null;
  contactVisible: boolean;
}

export interface RideManageDetails {
  rideId: number;
  rideType: 'offer' | 'request';
  rideStatus: string;
  pending: RideResponder[];
  confirmed?: {
    matchId: number;
    status: string;
    userId: number;
    name: string | null;
    phone?: string | null;
    whatsapp?: string | null;
    contactNotice?: string | null;
    contactVisible: boolean;
  } | null;
}

function buildRideListPath(options?: RideListOptions) {
  if (!options) return '/ride_list.php';
  const params: string[] = [];
  if (options.mine) params.push('mine=1');
  if (options.all) params.push('all=1');
  const qs = params.join('&');
  return `/ride_list.php${qs ? `?${qs}` : ''}`;
}

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

export interface UpdateRidePayload extends CreateRidePayload {
  id: number;
  package_only?: number | boolean;
}

export async function fetchUpcomingRides(options?: RideListOptions) {
  const response = await apiClient.get<RideListResponse>(buildRideListPath(options));
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

export function fetchMyRides() {
  return fetchUpcomingRides({ mine: true, all: true });
}

function mapResponder(server: ServerRideResponder): RideResponder {
  const visibility = server.requester_contact_visibility ?? {};
  return {
    matchId: server.match_id,
    status: server.status,
    requestedAt: server.created_at,
    userId: server.requester_id,
    name: server.requester_name,
    phone: server.requester_phone ?? undefined,
    whatsapp: server.requester_whatsapp ?? undefined,
    contactNotice: server.requester_contact_notice ?? visibility.reason ?? undefined,
    contactVisible: Boolean(visibility.visible),
  };
}

function mapConfirmed(server?: ServerRideConfirmed | null): RideManageDetails['confirmed'] {
  if (!server) {
    return null;
  }
  const visibility = server.other_contact_visibility ?? {};
  return {
    matchId: server.match_id,
    status: server.status,
    userId: server.other_id,
    name: server.other_name,
    phone: server.other_phone ?? undefined,
    whatsapp: server.other_whatsapp ?? undefined,
    contactNotice: server.other_contact_notice ?? visibility.reason ?? undefined,
    contactVisible: Boolean(visibility.visible),
  };
}

export async function fetchRideManageDetails(rideId: number): Promise<RideManageDetails> {
  const response = await apiClient.get<RideManageApiResponse>(`/ride_matches_list.php?ride_id=${rideId}`);
  if (!response.ok) {
    throw new Error(response.error ?? 'Unable to load ride responses');
  }
  return {
    rideId: response.ride.id,
    rideType: response.ride.type,
    rideStatus: response.ride.status,
    pending: response.pending.map(mapResponder),
    confirmed: mapConfirmed(response.confirmed),
  };
}

export async function updateRide(payload: UpdateRidePayload) {
  const csrf = getCsrfToken();
  if (!csrf) {
    throw new Error('You must be logged in to edit rides.');
  }
  const response = await apiClient.post<{ ok: boolean; error?: string; errors?: Record<string, string> }>(
    '/ride_update.php',
    {
      ...payload,
      package_only: typeof payload.package_only !== 'undefined' ? payload.package_only : payload.seats === 0 ? 1 : 0,
      csrf,
    },
  );
  if (!response.ok) {
    const firstError = response.errors ? Object.values(response.errors)[0] : null;
    throw new Error(response.error ?? firstError ?? 'Unable to update ride');
  }
  return response;
}

export async function deleteRide(id: number) {
  const csrf = getCsrfToken();
  if (!csrf) {
    throw new Error('You must be logged in to delete rides.');
  }
  const response = await apiClient.post<{ ok: boolean; error?: string }>('/ride_delete.php', { id, csrf });
  if (!response.ok) {
    throw new Error(response.error ?? 'Unable to delete ride');
  }
  return response;
}
