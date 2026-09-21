const apiBaseUrl = import.meta.env.VITE_API_URL;

if (!apiBaseUrl) {
  throw new Error("VITE_API_URL is not configured.");
}

const SAFE_HTTP_METHODS = new Set(["GET", "HEAD", "OPTIONS"]);

function createApiUrl(path: string) {
  const baseUrl = apiBaseUrl.endsWith("/") ? apiBaseUrl : `${apiBaseUrl}/`;

  const normalizedPath = path.startsWith("/") ? path.slice(1) : path;

  return new URL(normalizedPath, baseUrl).toString();
}

function getCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const prefix = `${name}=`;

  const cookie = document.cookie
    .split("; ")
    .find((item) => item.startsWith(prefix));

  if (!cookie) {
    return null;
  }

  return cookie.slice(prefix.length);
}

export class ApiError extends Error {
  readonly status: number;
  readonly code?: string;
  readonly details?: unknown;
  readonly errors?: Record<string, string[]>;

  constructor(
    message: string,
    status: number,
    options?: {
      code?: string;
      details?: unknown;
      errors?: Record<string, string[]>;
    },
  ) {
    super(message);

    this.name = "ApiError";
    this.status = status;
    this.code = options?.code;
    this.details = options?.details;
    this.errors = options?.errors;
  }
}

export async function apiFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  const method = (init.method ?? "GET").toUpperCase();
  const headers = new Headers(init.headers);

  headers.set("Accept", "application/json");

  if (
    init.body &&
    !(init.body instanceof FormData) &&
    !headers.has("Content-Type")
  ) {
    headers.set("Content-Type", "application/json");
  }

  if (!SAFE_HTTP_METHODS.has(method)) {
    const xsrfToken = getCookie("XSRF-TOKEN");

    if (xsrfToken && !headers.has("X-XSRF-TOKEN")) {
      headers.set("X-XSRF-TOKEN", decodeURIComponent(xsrfToken));
    }
  }

  return fetch(createApiUrl(path), {
    ...init,
    method,
    credentials: "include",
    headers,
  });
}

export async function apiJson<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const response = await apiFetch(path, init);

  if (response.status === 204) {
    return undefined as T;
  }

  const contentType = response.headers.get("content-type") ?? "";

  const payload = contentType.includes("application/json")
    ? await response.json()
    : null;

  if (!response.ok) {
    throw new ApiError(
      payload?.message ?? `Request failed with status ${response.status}.`,
      response.status,
      {
        code: payload?.code,
        details: payload?.details,
        errors: payload?.errors,
      },
    );
  }

  return payload as T;
}
