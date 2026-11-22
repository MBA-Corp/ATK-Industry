import { productMockData } from "~/features/products/product.mock";

export type PurchaseStatus = "Draf" | "Dipesan" | "Diterima" | "Dibatalkan";
export type PurchaseLine = {
  productId: number;
  name: string;
  quantity: number;
  price: number;
};
export type Purchase = {
  id: string;
  invoiceNo: string;
  supplier: string;
  date: string;
  recordedBy: string;
  paymentTerms: string;
  status: PurchaseStatus;
  lines: PurchaseLine[];
  note: string;
};
export const purchaseTotal = (purchase: Purchase) =>
  purchase.lines.reduce((sum, line) => sum + line.quantity * line.price, 0);
export const suppliers = [
  "PT Sinar Stationery",
  "CV Kertas Nusantara",
  "UD Mitra Alat Tulis",
];
export const statuses: PurchaseStatus[] = [
  "Draf",
  "Dipesan",
  "Diterima",
  "Dibatalkan",
];
const sampleStatuses: PurchaseStatus[] = [
  "Dipesan",
  "Draf",
  "Diterima",
  "Dipesan",
  "Diterima",
  "Diterima",
  "Dibatalkan",
  "Draf",
  "Diterima",
];
const sampleCashiers = ["Kasir Rina", "Kasir Dimas", "Kasir Nisa"];
const sampleTerms = ["Tempo 14 Hari", "Tunai", "Tempo 30 Hari"];

export const initialPurchases: Purchase[] = sampleStatuses.map(
  (status, index) => ({
    id: `PO-2026-${String(48 - index).padStart(4, "0")}`,
    invoiceNo: `FAK-SUP-${String(890 - index).padStart(4, "0")}`,
    supplier: suppliers[index % suppliers.length],
    date:
      index === 0
        ? "2026-10-01"
        : `2026-09-${String(31 - index).padStart(2, "0")}`,
    recordedBy: sampleCashiers[index % sampleCashiers.length],
    paymentTerms: sampleTerms[index % sampleTerms.length],
    status,
    lines: productMockData
      .slice(index % 3, (index % 3) + 2 + (index % 2))
      .map((product, i) => ({
        productId: product.id,
        name: product.name,
        quantity: (i + 1) * 12,
        price: product.cost_price ?? 0,
      })),
    note:
      index === 0
        ? "Dicatat oleh kasir berdasarkan surat jalan pemasok. Kemasan telah diperiksa saat barang masuk."
        : "Dokumen pesanan pembelian diinput melalui terminal operasional kasir.",
  }),
);
