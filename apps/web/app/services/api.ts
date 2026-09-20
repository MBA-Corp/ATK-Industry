const apiBaseUrl = import.meta.env.VITE_API_URL;

if (!apiBaseUrl) {
  throw new Error("VITE_API_URL is not configured.");
}

function createApiUrl(path: string) {
  const baseUrl = apiBaseUrl.endsWith("/")
    ? apiBaseUrl
    : `${apiBaseUrl}/`;

  const normalizedPath = path.startsWith("/")
    ? path.slice(1)
    : path;

  return new URL(normalizedPath, baseUrl).toString();
}

export async function apiFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  return fetch(createApiUrl(path), {
    ...init,
    credentials: "include",
    headers: {
      Accept: "application/json",
      ...init.headers,
    },
  });
}
