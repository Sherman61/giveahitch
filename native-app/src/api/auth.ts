import { apiClient } from './client';

export interface UserProfile {
  id: number;
  name: string;
  email: string;
}

export interface AuthResponse {
  token: string;
  user: UserProfile;
}

export async function login(email: string, password: string) {
  return apiClient.post<AuthResponse>('/mobile/login.php', { email, password });
}
