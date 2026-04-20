import type { ApiErrorBody } from '../types';

const JSON_HEADERS = { 'Content-Type': 'application/json' };

let accessToken: string | null = null;

export function setAccessToken(token: string | null): void {
  accessToken = token;
}

export function getAccessToken(): string | null {
  return accessToken;
}

async function refreshAccessToken(): Promise<boolean> {
  const res = await fetch('/api/v1/auth/refresh', {
    method: 'POST',
    credentials: 'include',
  });
  if (!res.ok) {
    return false;
  }
  const body = (await res.json()) as { access_token: string };
  accessToken = body.access_token;
  return true;
}

export async function apiFetch(
  path: string,
  init: RequestInit = {},
  retried = false,
): Promise<Response> {
  const headers = new Headers(init.headers);
  if (accessToken && !headers.has('Authorization')) {
    headers.set('Authorization', `Bearer ${accessToken}`);
  }
  if (
    init.body &&
    typeof init.body === 'string' &&
    !headers.has('Content-Type')
  ) {
    headers.set('Content-Type', 'application/json');
  }

  const res = await fetch(path, {
    ...init,
    headers,
    credentials: 'include',
  });

  if (res.status === 401 && !retried && path !== '/api/v1/auth/refresh') {
    const ok = await refreshAccessToken();
    if (ok) {
      return apiFetch(path, init, true);
    }
  }

  return res;
}

export async function parseJsonOrThrow<T>(res: Response): Promise<T> {
  const text = await res.text();
  if (!res.ok) {
    let msg = res.statusText;
    try {
      const err = JSON.parse(text) as ApiErrorBody;
      msg = err.error?.message ?? msg;
    } catch {
      /* ignore */
    }
    throw new Error(msg);
  }
  return JSON.parse(text || 'null') as T;
}

export { JSON_HEADERS };
