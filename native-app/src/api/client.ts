import Constants from 'expo-constants';

export const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_URL ??
  Constants.expoConfig?.extra?.apiUrl ??
  'https://glitchahitch.com/api';

const SITE_BASE_URL = API_BASE_URL.replace(/\/api\/?$/, '');

let csrfToken: string | null = null;

type HttpMethod = 'GET' | 'POST';

async function request<T>(path: string, method: HttpMethod = 'GET', body?: unknown): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };
  if (csrfToken) {
    headers['X-CSRF-Token'] = csrfToken;
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers,
    credentials: 'include',
    body: body ? JSON.stringify(body) : undefined,
  });

  if (!response.ok) {
    const message = await response.text();
    throw new Error(message || `Request failed with ${response.status}`);
  }

  // Some endpoints return no JSON (204). Guard parse.
  if (response.status === 204) {
    return {} as T;
  }

  return response.json() as Promise<T>;
}

export function setCsrfToken(token: string | null) {
  csrfToken = token;
}

export function getCsrfToken() {
  return csrfToken;
}

export function getSiteBaseUrl() {
  return SITE_BASE_URL;
}

export const apiClient = {
  get: <T>(path: string) => request<T>(path, 'GET'),
  post: <T>(path: string, payload?: unknown) => request<T>(path, 'POST', payload),
};
