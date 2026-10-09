export type Product = {
  id: number;
  sku: string;
  barcode?: string;
  size?: string | null;
  color?: string | null;
  name: string;
  category: string | null;
  stock: number;
  unit: string | null;
  cost_price: number;
  selling_price: number;
  is_active?: boolean;
};
