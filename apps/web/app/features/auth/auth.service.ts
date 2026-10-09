import { ApiError, apiJson } from "~/lib/api";

import type { CurrentUser, DataResponse, LoginInput } from "./auth.types";

export async function initializeCsrf(): Promise<void> {
  await apiJson<void>("/sanctum/csrf-cookie");
}

export async function getCurrentUser(): Promise<CurrentUser | null> {
  try {
    const response =
      await apiJson<DataResponse<{ user: CurrentUser }>>("/api/v1/me");

    return response.data.user;
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      return null;
    }

    throw error;
  }
}

export async function login(input: LoginInput): Promise<CurrentUser> {
  await initializeCsrf();

  await apiJson<void>("/login", {
    method: "POST",
    body: JSON.stringify(input),
  });

  const user = await getCurrentUser();

  if (!user) {
    throw new Error(
      "Login succeeded but the authenticated user could not be loaded.",
    );
  }

  return user;
}

export async function logout(): Promise<void> {
  await apiJson<void>("/logout", {
    method: "POST",
  });
}
