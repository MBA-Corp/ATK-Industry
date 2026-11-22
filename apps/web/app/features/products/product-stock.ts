import type { Product } from "./product.types";

export const lowStockThreshold = 8;
export const defaultMaxStock = 100;

export function stockStatus(stock: number): "out" | "low" | "healthy" {
  return stock <= 0 ? "out" : stock <= lowStockThreshold ? "low" : "healthy";
}

export function productMaxStock(
  product: Pick<Product, "max_stock">,
  override?: number,
): number {
  return override ?? product.max_stock ?? defaultMaxStock;
}

export function isOverMaxStock(stock: number, maxStock: number): boolean {
  return maxStock > 0 && stock > maxStock;
}
