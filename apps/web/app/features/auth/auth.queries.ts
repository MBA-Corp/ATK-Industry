import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { getCurrentUser, login, logout } from "./auth.service";

import type { CurrentUser, LoginInput } from "./auth.types";

export const authKeys = {
  all: ["auth"] as const,
  currentUser: () => [...authKeys.all, "me"] as const,
};

export function useCurrentUserQuery() {
  return useQuery({
    queryKey: authKeys.currentUser(),
    queryFn: getCurrentUser,
    retry: false,
    staleTime: 60_000,
  });
}

export function useLoginMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: LoginInput) => login(input),

    onSuccess: (user) => {
      queryClient.setQueryData<CurrentUser>(authKeys.currentUser(), user);
    },
  });
}

export function useLogoutMutation() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: logout,

    onSuccess: () => {
      queryClient.setQueryData(authKeys.currentUser(), null);
    },
  });
}
