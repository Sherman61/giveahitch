import { getSiteBaseUrl } from './client';

const CSRF_REGEX = /<meta\s+name=["']csrf-token["'][^>]*content=["']([^"']+)["']/i;

export async function fetchCsrfTokenFromServer(): Promise<string> {
  const response = await fetch(`${getSiteBaseUrl()}/login.php`, {
    method: 'GET',
    credentials: 'include',
  });
  const html = await response.text();
  const match = CSRF_REGEX.exec(html);
  if (!match) {
    throw new Error('Unable to retrieve CSRF token');
  }
  return match[1];
}

