import { productMockDataSource } from "~/features/products/product.mock-source";

const productDataSource = productMockDataSource;

export async function getProducts() {
  return productDataSource.getProducts();
}
