export const inventoryStockMovements = [
  {
    id: "MOV-001",
    date: "2026-10-01",
    time: "14:40",
    sku: "BKU-038",
    product: "Buku tulis 38 lembar",
    source: "Pembelian diterima",
    reference: "PB-261001-003",
    direction: "in",
    quantity: 120,
    unit: "pcs",
    user: "Dina",
  },
  {
    id: "MOV-002",
    date: "2026-10-01",
    time: "14:32",
    sku: "PLP-G05",
    product: "Pulpen gel hitam 0.5",
    source: "Penjualan",
    reference: "TRX-261001-015",
    direction: "out",
    quantity: 4,
    unit: "pcs",
    user: "Rizky",
  },
  {
    id: "MOV-003",
    date: "2026-09-30",
    time: "15:10",
    sku: "KRT-A480",
    product: "Kertas HVS A4 80 gsm",
    source: "Pembelian diterima",
    reference: "PB-260930-002",
    direction: "in",
    quantity: 10,
    unit: "rim",
    user: "Dina",
  },
  {
    id: "MOV-004",
    date: "2026-09-30",
    time: "13:40",
    sku: "BKU-058",
    product: "Buku tulis 58 lembar",
    source: "Penjualan",
    reference: "TRX-260930-012",
    direction: "out",
    quantity: 3,
    unit: "pcs",
    user: "Rizky",
  },
  {
    id: "MOV-005",
    date: "2026-09-29",
    time: "10:20",
    sku: "PLP-005",
    product: "Pulpen ballpoint hitam",
    source: "Barang rusak",
    reference: "STK-260929-001",
    direction: "out",
    quantity: 2,
    unit: "pcs",
    user: "Dina",
  },
  {
    id: "MOV-006",
    date: "2026-09-28",
    time: "16:05",
    sku: "PNS-002",
    product: "Pensil 2B",
    source: "Adjustment stok",
    reference: "STK-260928-004",
    direction: "in",
    quantity: 12,
    unit: "pcs",
    user: "Rizky",
  },
  {
    id: "MOV-007",
    date: "2026-09-27",
    time: "11:32",
    sku: "MAP-PLS",
    product: "Map plastik kancing",
    source: "Barang hilang",
    reference: "STK-260927-002",
    direction: "out",
    quantity: 1,
    unit: "pcs",
    user: "Dina",
  },
  {
    id: "MOV-008",
    date: "2026-09-26",
    time: "09:15",
    sku: "PLP-G05",
    product: "Pulpen gel hitam 0.5",
    source: "Pembelian diterima",
    reference: "PB-260926-001",
    direction: "in",
    quantity: 48,
    unit: "pcs",
    user: "Rizky",
  },
  {
    id: "MOV-009",
    date: "2026-09-22",
    time: "10:45",
    sku: "KRT-A480",
    product: "Kertas HVS A4 80 gsm",
    source: "Stock opname",
    reference: "OPN-260922-001",
    direction: "out",
    quantity: 1,
    unit: "rim",
    user: "Dina",
  },
  {
    id: "MOV-010",
    date: "2026-09-19",
    time: "12:18",
    sku: "BKU-038",
    product: "Buku tulis 38 lembar",
    source: "Penjualan",
    reference: "TRX-260919-028",
    direction: "out",
    quantity: 6,
    unit: "pcs",
    user: "Rizky",
  },
  {
    id: "MOV-011",
    date: "2026-09-16",
    time: "08:52",
    sku: "PST-001",
    product: "Pensil warna 12 warna",
    source: "Retur penjualan",
    reference: "RTR-260916-001",
    direction: "in",
    quantity: 1,
    unit: "set",
    user: "Dina",
  },
  {
    id: "MOV-012",
    date: "2026-09-12",
    time: "14:08",
    sku: "SPL-002",
    product: "Spidol permanen hitam",
    source: "Penjualan",
    reference: "TRX-260912-019",
    direction: "out",
    quantity: 2,
    unit: "pcs",
    user: "Rizky",
  },
] as const;

export type InventoryMovement = (typeof inventoryStockMovements)[number];
export type InventoryDirection = "in" | "out";
export type InventoryPeriod = "week" | "month" | "custom";

export type ReceiptProofLine = {
  name: string;
  qty: number;
  unitPrice: number;
};

export type CustomerRefund = {
  id: string;
  receiptNo: string;
  receiptDate: string;
  receiptTime: string;
  refundDate: string;
  refundTime: string;
  customerName: string;
  cashier: string;
  paymentMethod: "Tunai" | "QRIS";
  sku: string;
  productName: string;
  quantity: number;
  unit: string;
  refundAmount: number;
  reason: string;
  stockHandling: "Kembali ke stok aktif" | "Karantina barang rusak";
  receiptCode: string;
  receiptLines: ReceiptProofLine[];
};

export const initialCustomerRefunds: CustomerRefund[] = [
  {
    id: "RFD-261001-01",
    receiptNo: "TRX-261001-015",
    receiptDate: "2026-10-01",
    receiptTime: "14:32",
    refundDate: "2026-10-01",
    refundTime: "16:05",
    customerName: "Bpk. Hendra Wijaya",
    cashier: "Dina",
    paymentMethod: "QRIS",
    sku: "PLP-G05",
    productName: "Pulpen gel hitam 0.5",
    quantity: 2,
    unit: "PCS",
    refundAmount: 8000,
    reason: "Tinta macet saat dicoba setelah transaksi (struk asli terlampir)",
    stockHandling: "Karantina barang rusak",
    receiptCode: "STRUK-POS-015-994A",
    receiptLines: [
      { name: "Pulpen gel hitam 0.5", qty: 4, unitPrice: 4000 },
      { name: "Buku tulis 58 lembar", qty: 5, unitPrice: 4500 },
      { name: "Buku tulis 38 lembar", qty: 3, unitPrice: 3000 },
    ],
  },
  {
    id: "RFD-260930-02",
    receiptNo: "TRX-260930-011",
    receiptDate: "2026-09-30",
    receiptTime: "13:12",
    refundDate: "2026-09-30",
    refundTime: "15:40",
    customerName: "Ibu Siska Pratama",
    cashier: "Dina",
    paymentMethod: "QRIS",
    sku: "PST-001",
    productName: "Pensil warna 12 warna",
    quantity: 1,
    unit: "SET",
    refundAmount: 25000,
    reason: "Salah beli varian, kemasan masih tersegel utuh",
    stockHandling: "Kembali ke stok aktif",
    receiptCode: "STRUK-POS-011-782B",
    receiptLines: [
      { name: "Pensil warna 12 warna", qty: 1, unitPrice: 25000 },
      { name: "Buku tulis 38 lembar", qty: 10, unitPrice: 3000 },
      { name: "Pulpen ballpoint hitam", qty: 5, unitPrice: 2500 },
    ],
  },
  {
    id: "RFD-260928-01",
    receiptNo: "TRX-260928-009",
    receiptDate: "2026-09-28",
    receiptTime: "10:15",
    refundDate: "2026-09-28",
    refundTime: "13:20",
    customerName: "Sdri. Nadia Putri",
    cashier: "Rizky",
    paymentMethod: "Tunai",
    sku: "SPL-002",
    productName: "Spidol permanen hitam",
    quantity: 1,
    unit: "PCS",
    refundAmount: 7000,
    reason: "Ujung spidol kering dari pabrik",
    stockHandling: "Karantina barang rusak",
    receiptCode: "STRUK-POS-009-441C",
    receiptLines: [
      { name: "Spidol permanen hitam", qty: 2, unitPrice: 7000 },
      { name: "Lem kertas putih 40 g", qty: 2, unitPrice: 5500 },
    ],
  },
];

export function isMovementInPeriod(
  movement: { date: string },
  period: InventoryPeriod,
  customStart = "2026-09-01",
  customEnd = "2026-10-01",
): boolean {
  if (period === "custom") {
    const start = customStart <= customEnd ? customStart : customEnd;
    const end = customStart <= customEnd ? customEnd : customStart;
    return movement.date >= start && movement.date <= end;
  }
  return period === "week"
    ? movement.date >= "2026-09-25" && movement.date <= "2026-10-01"
    : movement.date >= "2026-09-01" && movement.date <= "2026-09-30";
}

export const inventoryPeriodLabels = {
  week: "25 Sep - 1 Okt 2026",
  month: "September 2026",
  custom: "Rentang khusus",
} as const;

const movementDateFormatter = new Intl.DateTimeFormat("id-ID", {
  day: "numeric",
  month: "short",
  year: "numeric",
  timeZone: "UTC",
});

export function formatMovementDate(date: string): string {
  return movementDateFormatter.format(new Date(`${date}T00:00:00Z`));
}
