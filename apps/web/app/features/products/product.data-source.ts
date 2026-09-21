import type { Product } from "~/features/products/product.types";

export type ProductDataSource = {
  getProducts: () => Promise<Product[]>;
};
