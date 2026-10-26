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

export interface StreamEvent {
  kind: 'booking' | 'sos';
  booking_id?: number;
  sos_id?: string;
  event?: string;
  from_state?: string | null;
  to_state?: string | null;
  state?: string;
  reporter_role?: string;
  created_at?: string;
  occurred_at?: string;
}

/**
 * Opens the authenticated operations stream using fetch so the bearer token can
 * be sent in an Authorization header. EventSource cannot set headers and would
 * otherwise force an insecure query-string token.
 */
export function openOperationsStream(
  onEvent: (event: StreamEvent) => void,
  onState: (state: 'connecting' | 'open' | 'error' | 'closed') => void,
): () => void {
  const token = getToken();
  if (!token) {
    onState('error');
    return () => undefined;
  }

  const controller = new AbortController();
  const base = (import.meta.env.VITE_API_BASE as string) || 'http://127.0.0.1:8000/api/v1';

  onState('connecting');
  void fetch(`${base}/admin/operations/stream`, {
    headers: { Accept: 'text/event-stream', Authorization: `Bearer ${token}` },
    signal: controller.signal,
  }).then(async (response) => {
    if (!response.ok || !response.body) throw new Error(`stream_${response.status}`);
    onState('open');
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let kind: StreamEvent['kind'] = 'booking';

    while (!controller.signal.aborted) {
      const { value, done } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });
      const frames = buffer.split('\n\n');
      buffer = frames.pop() || '';
      for (const frame of frames) {
        const eventLine = frame.split('\n').find((line) => line.startsWith('event:'));
        const dataLine = frame.split('\n').find((line) => line.startsWith('data:'));
        if (eventLine?.includes('sos')) kind = 'sos';
        else if (eventLine?.includes('booking')) kind = 'booking';
        if (dataLine) {
          try { onEvent({ kind, ...JSON.parse(dataLine.replace(/^data:\s*/, '')) }); } catch { /* ignore malformed frame */ }
        }
      }
    }
  }).catch(() => {
    if (!controller.signal.aborted) onState('error');
  });

  return () => {
    controller.abort();
    onState('closed');
  };
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