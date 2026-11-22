export const dashboardPeriods = {
  week: {
    label: "7 hari terakhir",
    revenue: 4875000,
    transactions: 104,
    sold: 386,
    labels: ["Jum", "Sab", "Min", "Sen", "Sel", "Rab", "Kam"],
    sales: [525000, 875000, 425000, 650000, 775000, 725000, 900000],
    cash: 3120000,
    qris: 1755000,
  },
  month: {
    label: "September 2026",
    revenue: 18750000,
    transactions: 452,
    sold: 1684,
    labels: ["01 Sep", "05 Sep", "10 Sep", "15 Sep", "20 Sep", "25 Sep", "30 Sep"],
    sales: [2100000, 2850000, 1950000, 3300000, 2450000, 2700000, 3400000],
    cash: 12000000,
    qris: 6750000,
  },
  custom: {
    label: "Rentang tanggal khusus",
    revenue: 6420000,
    transactions: 142,
    sold: 518,
    labels: ["H-6", "H-5", "H-4", "H-3", "H-2", "H-1", "Hari ini"],
    sales: [740000, 920000, 680000, 890000, 1050000, 990000, 1150000],
    cash: 3980000,
    qris: 2440000,
  },
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

export const lowStockProducts = [
  { name: "Kertas HVS A4 80 gsm", sku: "KRT-A480", stock: 2, maxStock: 60, unit: "RIM" },
  { name: "Pulpen gel hitam 0.5", sku: "PLP-G05", stock: 5, maxStock: 80, unit: "PCS" },
  { name: "Buku tulis 58 lembar", sku: "BKU-058", stock: 8, maxStock: 120, unit: "PCS" },
];

export const bestSellingProducts = [
  { name: "Buku tulis 38 lembar", category: "Buku & Kertas", sold: 128, soldMonth: 412, unitPrice: 3000, share: 100, color: "blue" },
  { name: "Pulpen ballpoint hitam", category: "Alat Tulis", sold: 96, soldMonth: 318, unitPrice: 2500, share: 75, color: "purple" },
  { name: "Kertas HVS A4 80 gsm", category: "Buku & Kertas", sold: 64, soldMonth: 210, unitPrice: 58000, share: 50, color: "mint" },
];

export const formatRupiah = (value: number) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
