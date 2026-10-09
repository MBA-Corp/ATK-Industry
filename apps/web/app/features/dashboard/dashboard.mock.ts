export const dashboardPeriods = {
  week: { label: "7 hari terakhir", revenue: 4875000, transactions: 104, sold: 386, labels: ["Jum", "Sab", "Min", "Sen", "Sel", "Rab", "Kam"], sales: [525000, 875000, 425000, 650000, 775000, 725000, 900000], cash: 3120000, qris: 1755000 },
  month: { label: "September 2026", revenue: 18750000, transactions: 452, sold: 1684, labels: ["01 Sep", "05 Sep", "10 Sep", "15 Sep", "20 Sep", "25 Sep", "30 Sep"], sales: [2100000, 2850000, 1950000, 3300000, 2450000, 2700000, 3400000], cash: 12000000, qris: 6750000 },
} as const;
export type DashboardPeriod = keyof typeof dashboardPeriods;
export const recentTransactions = [
  { id: "TRX-261001-015", date: "2026-10-01", time: "14:32", cashier: "Dina", items: 4, payment: "QRIS", total: 48500 },
  { id: "TRX-261001-014", date: "2026-10-01", time: "14:18", cashier: "Rizky", items: 2, payment: "Tunai", total: 22000 },
  { id: "TRX-261001-013", date: "2026-10-01", time: "13:56", cashier: "Dina", items: 6, payment: "Tunai + QRIS", total: 125000 },
  { id: "TRX-260930-012", date: "2026-09-30", time: "13:40", cashier: "Rizky", items: 3, payment: "Tunai", total: 36000 },
  { id: "TRX-260930-011", date: "2026-09-30", time: "13:12", cashier: "Dina", items: 5, payment: "QRIS", total: 67500 },
  { id: "TRX-260929-010", date: "2026-09-29", time: "12:48", cashier: "Rizky", items: 2, payment: "Tunai", total: 18000 },
];
// Illustrative alerts: the PRD has not finalized the safety-stock formula.
export const lowStockProducts = [
  { name: "Kertas HVS A4 80 gsm", sku: "KRT-001", stock: 2, unit: "rim" },
  { name: "Pulpen gel hitam 0.5", sku: "PLP-012", stock: 5, unit: "pcs" },
  { name: "Buku tulis 58 lembar", sku: "BKU-008", stock: 8, unit: "pcs" },
];
export const bestSellingProducts = [
  { name: "Buku tulis 38 lembar", category: "Buku & kertas", sold: 128, share: 100, color: "blue" },
  { name: "Pulpen ballpoint hitam", category: "Alat tulis", sold: 96, share: 75, color: "purple" },
  { name: "Kertas HVS A4", category: "Buku & kertas", sold: 64, share: 50, color: "mint" },
];
export const formatRupiah = (value: number) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(value);
