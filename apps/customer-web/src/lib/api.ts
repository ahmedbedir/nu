/**
 * Minimal typed API client for the Laravel backend.
 *
 * The token is held in memory plus localStorage so a page refresh keeps the session;
 * every failure surfaces as a typed result rather than a thrown surprise in the UI.
 */

export type ApiResult<T> =
  | { ok: true; data: T }
  | { ok: false; status: number; message: string; code?: string; errors?: Record<string, string[]> };

// Server-side rendering needs an absolute URL; the browser uses the rewrite proxy.
const CLIENT_BASE = process.env.NEXT_PUBLIC_API_BASE || '/backend/v1';
const SERVER_BASE = `${process.env.API_URL || 'http://127.0.0.1:8000'}/api/v1`;
const BASE = typeof window === 'undefined' ? SERVER_BASE : CLIENT_BASE;
const TOKEN_KEY = 'hc_token';

export function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  try {
    return window.localStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

export function setToken(token: string | null): void {
  if (typeof window === 'undefined') return;
  try {
    if (token) window.localStorage.setItem(TOKEN_KEY, token);
    else window.localStorage.removeItem(TOKEN_KEY);
  } catch {
    /* storage may be unavailable in private mode — session simply won't persist */
  }
}

async function request<T>(
  path: string,
  init: RequestInit = {},
  withAuth = true,
): Promise<ApiResult<T>> {
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');

  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  if (withAuth) {
    const token = getToken();
    if (token) headers.set('Authorization', `Bearer ${token}`);
  }

  let response: Response;

  try {
    response = await fetch(`${BASE}${path}`, { ...init, headers, cache: 'no-store' });
  } catch {
    return { ok: false, status: 0, message: 'network_error' };
  }

  const text = await response.text();
  const payload = text ? safeJson(text) : null;

  if (!response.ok) {
    return {
      ok: false,
      status: response.status,
      message: (payload?.message as string) || 'request_failed',
      code: payload?.code as string | undefined,
      errors: payload?.errors as Record<string, string[]> | undefined,
    };
  }

  return { ok: true, data: payload as T };
}

function safeJson(text: string): any {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

// --- endpoints ---------------------------------------------------------

export interface ServiceItem {
  public_id: string;
  code: string;
  name_ar: string;
  name_en: string;
  description_ar?: string | null;
  description_en?: string | null;
  billing_mode: 'fixed' | 'time';
  default_duration_minutes: number;
  base_price: string;
  currency: string;
  requires_medical_order: boolean;
}

export interface PatientItem {
  public_id: string;
  full_name: string;
  birth_date?: string | null;
  age?: number | null;
  sex: 'male' | 'female' | 'unspecified';
  blood_type?: string | null;
  conditions_state: 'known' | 'unknown';
  medications_state: 'known' | 'unknown';
  allergies_state: 'known' | 'unknown';
  status: string;
}

export const api = {
  health: () => request<{ status: string }>('/health', {}, false),

  requestOtp: (phone: string) =>
    request<{ requested: boolean; expires_at: string; dev_code: string | null }>(
      '/auth/otp/request',
      { method: 'POST', body: JSON.stringify({ phone }) },
      false,
    ),

  verifyOtp: (phone: string, code: string) =>
    request<{ token: string; user: { public_id: string; name: string; phone: string }; first_time: boolean }>(
      '/auth/otp/verify',
      { method: 'POST', body: JSON.stringify({ phone, code }) },
      false,
    ),

  me: () => request<{ user: { public_id: string; name: string; phone: string; identity_status: string } }>('/auth/me'),

  logout: () => request<{ revoked: boolean }>('/auth/logout', { method: 'POST' }),

  services: () => request<{ data: ServiceItem[] }>('/services', {}, false),

  patients: () => request<{ data: PatientItem[] }>('/patients'),

  patient: (id: string) => request<{ patient: PatientItem & { clinical?: unknown } }>(`/patients/${id}`),

  createPatient: (payload: {
    full_name: string;
    birth_date?: string;
    sex: string;
    relationship: string;
  }) => request<{ patient: PatientItem }>('/patients', { method: 'POST', body: JSON.stringify(payload) }),
};