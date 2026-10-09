import { useQuery } from "@tanstack/react-query";

import { checkHealth } from "~/features/health/health.service";

const healthQueryKey = ["health"] as const;

export function useHealthQuery() {
  return useQuery({
    queryKey: healthQueryKey,
    queryFn: async () => {
      const response = await checkHealth();

      return {
        status: response.status,
      };
    },
  });
}
