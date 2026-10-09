export const lowStockThreshold = 8;

export function stockStatus(stock: number): "out" | "low" | "healthy" {
  return stock <= 0 ? "out" : stock <= lowStockThreshold ? "low" : "healthy";
}
