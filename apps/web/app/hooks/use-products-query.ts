import { useQuery } from "@tanstack/react-query";

import { getProducts } from "~/services/product.service";

export const productQueryKeys = {
  all: ["products"] as const,
};

export function useProductsQuery() {
  return useQuery({
    queryKey: productQueryKeys.all,
    queryFn: getProducts,
  });
}
