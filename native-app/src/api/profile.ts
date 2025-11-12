import { apiClient } from './client';
import { UserProfile } from '@/types/user';

interface ProfileApiResponse {
  ok: boolean;
  user?: {
    id: number;
    email: string;
    display_name?: string | null;
    name?: string | null;
  };
  error?: string;
}

export async function fetchProfile(): Promise<UserProfile> {
  const response = await apiClient.get<ProfileApiResponse>('/profile.php');
  if (!response.ok || !response.user) {
    throw new Error(response.error ?? 'Unable to load profile');
  }
  const name = response.user.display_name ?? response.user.name ?? response.user.email;
  return {
    id: response.user.id,
    email: response.user.email,
    name,
    displayName: response.user.display_name ?? null,
  };
}

