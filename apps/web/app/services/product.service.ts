import { productMockData } from "~/features/products/product.mock";
import type { Product } from "~/features/products/product.types";

export async function getProducts(): Promise<Product[]> {
  return [...productMockData];
}
