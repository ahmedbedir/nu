/**
 * Admin API client.
 *
 * Every call carries the staff bearer token. A missing permission surfaces as 403 and
 * the UI shows it rather than silently hiding the failure.
 */
const BASE = (import.meta.env.VITE_API_BASE as string) || 'http://127.0.0.1:8000/api/v1';
const TOKEN_KEY = 'hc_admin_token';

export function getToken(): string | null {
  try {
    return localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

export function setToken(token: string | null): void {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token);
    else localStorage.removeItem(TOKEN_KEY);
  } catch {
    /* ignore */
  }
}

export interface Result<T> {
  ok: boolean;
  status: number;
  data?: T;
  message?: string;
}

async function call<T>(path: string, init: RequestInit = {}, auth = true): Promise<Result<T>> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (init.body) headers.set('Content-Type', 'application/json');
  if (auth) {
    const t = getToken();
    if (t) headers.set('Authorization', `Bearer ${t}`);
  }

  try {
    const res = await fetch(`${BASE}${path}`, { ...init, headers });
    const text = await res.text();
    const data = text ? JSON.parse(text) : undefined;
    return { ok: res.ok, status: res.status, data, message: data?.message };
  } catch (e) {
    return { ok: false, status: 0, message: (e as Error).message };
  }
}

export const api = {
  health: () => call<{ status: string; checks: unknown }>('/health', {}, false),
  me: () => call<{ user: { name: string; public_id: string } }>('/auth/me'),
  requestOtp: (phone: string) =>
    call<{ dev_code: string | null }>('/auth/otp/request', { method: 'POST', body: JSON.stringify({ phone }) }, false),
  verifyOtp: (phone: string, code: string) =>
    call<{ token: string; user: { name: string } }>(
      '/auth/otp/verify',
      { method: 'POST', body: JSON.stringify({ phone, code }) },
      false,
    ),
};