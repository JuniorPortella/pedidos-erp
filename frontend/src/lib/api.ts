import type { ErrorResponse, ValidationFields } from '../types/api';
import { invalidateSession } from './session';

const API_URL = (
  import.meta.env.VITE_API_URL ?? 'http://localhost:18081'
).replace(/\/$/, '');

const unsafeMethods = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
const authenticationPaths = new Set([
  '/auth/login',
  '/auth/refresh',
  '/auth/logout',
]);

export class ApiError extends Error {
  public constructor(
    public readonly status: number,
    message: string,
    public readonly fields: ValidationFields = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

function cookie(name: string): string | null {
  const prefix = `${encodeURIComponent(name)}=`;

  for (const part of document.cookie.split(';')) {
    const normalized = part.trim();

    if (normalized.startsWith(prefix)) {
      return decodeURIComponent(normalized.slice(prefix.length));
    }
  }

  return null;
}

async function errorFrom(response: Response): Promise<ApiError> {
  let payload: ErrorResponse = {};

  try {
    payload = (await response.json()) as ErrorResponse;
  } catch {
    // Responses from proxies may not contain JSON.
  }

  return new ApiError(
    response.status,
    payload.error ?? 'Nao foi possivel concluir a operacao.',
    payload.fields ?? {},
  );
}

function headersFor(options: RequestInit): Headers {
  const headers = new Headers(options.headers);
  const method = (options.method ?? 'GET').toUpperCase();

  headers.set('Accept', 'application/json');

  if (options.body && !(options.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json');
  }

  if (unsafeMethods.has(method)) {
    const csrfToken = cookie('csrf_token');

    if (csrfToken) {
      headers.set('X-CSRF-Token', csrfToken);
    }
  }

  return headers;
}

async function send(path: string, options: RequestInit): Promise<Response> {
  try {
    return await fetch(`${API_URL}${path}`, {
      ...options,
      credentials: 'include',
      headers: headersFor(options),
    });
  } catch {
    throw new ApiError(
      0,
      'Nao foi possivel conectar com a API. Verifique o ambiente.',
    );
  }
}

let refreshInProgress: Promise<void> | null = null;

async function refreshSession(): Promise<void> {
  if (!refreshInProgress) {
    refreshInProgress = (async () => {
      const response = await send('/auth/refresh', { method: 'POST' });

      if (!response.ok) {
        throw await errorFrom(response);
      }
    })().finally(() => {
      refreshInProgress = null;
    });
  }

  return refreshInProgress;
}

export interface ApiRequestOptions extends RequestInit {
  retryOnUnauthorized?: boolean;
}

export async function apiRequest<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const { retryOnUnauthorized = true, ...requestOptions } = options;
  let response = await send(path, requestOptions);

  if (
    response.status === 401 &&
    retryOnUnauthorized &&
    !authenticationPaths.has(path)
  ) {
    try {
      await refreshSession();
      response = await send(path, requestOptions);
    } catch {
      invalidateSession();
      throw await errorFrom(response);
    }
  }

  if (response.status === 401 && !authenticationPaths.has(path)) {
    invalidateSession();
  }

  if (!response.ok) {
    throw await errorFrom(response);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

export function jsonBody(data: unknown): string {
  return JSON.stringify(data);
}
