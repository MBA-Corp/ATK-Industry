export type Product = {
  id: number;
  sku: string;
  name: string;
  category: string | null;
  stock: number;
  unit: string | null;
  cost_price: number;
  selling_price: number;
};
