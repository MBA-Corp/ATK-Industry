import type { Product } from "~/features/products/product.types";

export const productMockData: Product[] = [
  {
    id: 1,
    sku: "6324-01",
    name: "Pulpen",
    category: "Alat Tulis",
    stock: 24,
    unit: "PCS",
    cost_price: 3000,
    selling_price: 5000,
  },
  {
    id: 2,
    sku: "6324-100",
    name: "Buku Tulis",
    category: "Buku",
    stock: 18,
    unit: "PCS",
    cost_price: 4500,
    selling_price: 7000,
  },
  {
    id: 3,
    sku: "6324-1000",
    name: "Kertas A4",
    category: null,
    stock: 10,
    unit: "RIM",
    cost_price: 52000,
    selling_price: 60000,
  },
];
