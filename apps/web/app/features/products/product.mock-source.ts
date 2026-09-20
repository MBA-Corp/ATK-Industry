import type { ProductDataSource } from "~/features/products/product.data-source";
import { productMockData } from "~/features/products/product.mock";

export const productMockDataSource: ProductDataSource = {
  async getProducts() {
    return [...productMockData];
  },
};
