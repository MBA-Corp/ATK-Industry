import { apiFetch } from "~/services/api";

export async function checkHealth(): Promise<Response> {
  const response = await apiFetch("/api/v1/health");

  if (!response.ok) {
    throw new Error(
      `Health check failed with HTTP status ${response.status}.`,
    );
  }

  return response;
}
