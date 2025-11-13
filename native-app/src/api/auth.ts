import { apiClient, getCsrfToken, setCsrfToken } from './client';
import { fetchCsrfTokenFromServer } from './session';
import { fetchProfileDetails } from './profile';
import { UserProfile } from '@/types/user';

interface LoginApiResponse {
  ok: boolean;
  error?: string;
}

export interface AuthResponse {
  csrfToken: string;
  user: UserProfile;
}

export async function login(email: string, password: string): Promise<AuthResponse> {
  const csrfToken = await fetchCsrfTokenFromServer();
  setCsrfToken(csrfToken);

  const result = await apiClient.post<LoginApiResponse>('/login.php', {
    email,
    password,
    csrf: csrfToken,
  });

  if (!result.ok) {
    throw new Error(result.error ?? 'Login failed');
  }

  const profile = await fetchProfileDetails();
  const user: UserProfile = {
    id: profile.id,
    email: profile.email,
    name: profile.name,
    displayName: profile.displayName ?? null,
  };
  return { csrfToken, user };
}

export async function logout() {
  const csrf = getCsrfToken();
  if (!csrf) {
    return;
  }
  try {
    await apiClient.post('/logout.php', { csrf });
  } finally {
    setCsrfToken(null);
  }
}
