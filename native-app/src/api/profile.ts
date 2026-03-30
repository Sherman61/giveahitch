import { apiClient, getCsrfToken } from './client';
import { UserProfile } from '@/types/user';

interface ProfileApiResponse {
  ok: boolean;
  user?: {
    id: number;
    email: string;
    display_name?: string | null;
    username?: string | null;
    name?: string | null;
    score?: number;
    created_at?: string;
    contact?: {
      phone?: string | null;
      whatsapp?: string | null;
    };
    contact_privacy?: number;
    message_privacy?: number;
    stats?: {
      rides_offered_count: number;
      rides_requested_count: number;
      rides_given_count: number;
      rides_received_count: number;
    };
    ratings?: {
      driver?: {
        average: number | null;
        count: number;
      };
      passenger?: {
        average: number | null;
        count: number;
      };
    };
  };
  error?: string;
}

export interface ProfileDetails extends UserProfile {
  username?: string | null;
  score?: number;
  createdAt?: string;
  contact: {
    phone?: string | null;
    whatsapp?: string | null;
  };
  contactPrivacy?: number;
  messagePrivacy?: number;
  stats: {
    ridesOffered: number;
    ridesRequested: number;
    ridesGiven: number;
    ridesReceived: number;
  };
  ratings: {
    driver?: {
      average: number | null;
      count: number;
    };
    passenger?: {
      average: number | null;
      count: number;
    };
  };
}

export async function fetchProfileDetails(): Promise<ProfileDetails> {
  const response = await apiClient.get<ProfileApiResponse>('/profile.php');
  if (!response.ok || !response.user) {
    throw new Error(response.error ?? 'Unable to load profile');
  }
  const name = response.user.display_name ?? response.user.username ?? response.user.name ?? response.user.email;
  return {
    id: response.user.id,
    email: response.user.email,
    name,
    displayName: response.user.display_name ?? null,
    username: response.user.username ?? null,
    score: response.user.score ?? undefined,
    createdAt: response.user.created_at,
    contact: {
      phone: response.user.contact?.phone ?? null,
      whatsapp: response.user.contact?.whatsapp ?? null,
    },
    contactPrivacy: response.user.contact_privacy ?? 1,
    messagePrivacy: response.user.message_privacy ?? 1,
    stats: {
      ridesOffered: response.user.stats?.rides_offered_count ?? 0,
      ridesRequested: response.user.stats?.rides_requested_count ?? 0,
      ridesGiven: response.user.stats?.rides_given_count ?? 0,
      ridesReceived: response.user.stats?.rides_received_count ?? 0,
    },
    ratings: {
      driver: response.user.ratings?.driver
        ? {
            average: response.user.ratings.driver.average ?? null,
            count: response.user.ratings.driver.count ?? 0,
          }
        : undefined,
      passenger: response.user.ratings?.passenger
        ? {
            average: response.user.ratings.passenger.average ?? null,
            count: response.user.ratings.passenger.count ?? 0,
          }
        : undefined,
    },
  };
}

export async function updateProfileContact(payload: {
  display_name?: string;
  phone?: string;
  whatsapp?: string;
  contact_privacy?: number;
  message_privacy?: number;
}) {
  const csrf = getCsrfToken();
  if (!csrf) {
    throw new Error('Please log in to update your profile.');
  }
  const response = await apiClient.post<ProfileApiResponse>('/profile.php', {
    ...payload,
    csrf,
  });
  if (!response.ok) {
    throw new Error(response.error ?? 'Unable to update profile');
  }
  return response.user;
}
