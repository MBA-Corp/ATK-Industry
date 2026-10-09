export type ReportLine = {
  name: string;
  quantity: number;
  price: number;
  cost: number;
};
export type ReportTransaction = {
  id: string;
  date: string;
  time: string;
  cashier: string;
  payment: "Tunai" | "QRIS" | "Transfer";
  status: "Selesai" | "Dibatalkan";
  lines: ReportLine[];
};

export const reportPeriods = [
  {
    value: "week",
    label: "25 Sep – 1 Okt 2026",
    start: "2026-09-25",
    end: "2026-10-01",
  },
  {
    value: "september",
    label: "September 2026",
    start: "2026-09-01",
    end: "2026-09-30",
  },
  {
    value: "october",
    label: "1–2 Oktober 2026",
    start: "2026-10-01",
    end: "2026-10-02",
  },
] as const;

const products = [
  { name: "Buku tulis 38 lembar", price: 3000, cost: 1800 },
  { name: "Pulpen ballpoint hitam", price: 2500, cost: 1500 },
  { name: "Kertas HVS A4 80 gsm", price: 58000, cost: 52000 },
  { name: "Pulpen gel hitam 0.5", price: 4000, cost: 2500 },
  { name: "Buku tulis 58 lembar", price: 4500, cost: 2800 },
];

export const reportTransactions: ReportTransaction[] = Array.from(
  { length: 96 },
  (_, index) => {
    const day = Math.floor(index / 3) + 1;
    return {
      id: `TRX-2026-${String(1001 + index).padStart(4, "0")}`,
      date:
        day <= 30
          ? `2026-09-${String(day).padStart(2, "0")}`
          : `2026-10-${String(day - 30).padStart(2, "0")}`,
      time: `${String(9 + (index % 9)).padStart(2, "0")}:${String((index * 7) % 60).padStart(2, "0")}`,
      cashier: ["Dewi", "Andi", "Rina"][index % 3],
      payment: (["Tunai", "QRIS", "Tunai", "Transfer", "QRIS"] as const)[
        index % 5
      ],
      status: (index % 13 === 0
        ? "Dibatalkan"
        : "Selesai") as ReportTransaction["status"],
      lines: [0, 1].map((offset) => ({
        ...products[(index + offset) % products.length],
        quantity: 2 + ((index * 3 + offset) % 12),
      })),
    };
  },
).sort((a, b) => `${b.date} ${b.time}`.localeCompare(`${a.date} ${a.time}`));

export const transactionTotal = (item: ReportTransaction) =>
  item.lines.reduce((sum, line) => sum + line.price * line.quantity, 0);
export const transactionCost = (item: ReportTransaction) =>
  item.status === "Selesai"
    ? item.lines.reduce((sum, line) => sum + line.cost * line.quantity, 0)
    : 0;
export const transactionProfit = (item: ReportTransaction) =>
  item.status === "Selesai"
    ? transactionTotal(item) - transactionCost(item)
    : 0;
export function summarizeReport(items: ReportTransaction[]) {
  const completed = items.filter((item) => item.status === "Selesai");
  const revenue = completed.reduce(
    (sum, item) => sum + transactionTotal(item),
    0,
  );
  const cost = completed.reduce((sum, item) => sum + transactionCost(item), 0);
  const sold = completed.reduce(
    (sum, item) =>
      sum + item.lines.reduce((total, line) => total + line.quantity, 0),
    0,
  );
  const products = new Map<
    string,
    { name: string; quantity: number; revenue: number }
  >();
  completed.forEach((item) =>
    item.lines.forEach((line) => {
      const previous = products.get(line.name) ?? {
        name: line.name,
        quantity: 0,
        revenue: 0,
      };
      products.set(line.name, {
        name: line.name,
        quantity: previous.quantity + line.quantity,
        revenue: previous.revenue + line.quantity * line.price,
      });
    }),
  );
  return {
    completed,
    revenue,
    cost,
    profit: revenue - cost,
    sold,
    average: completed.length ? revenue / completed.length : 0,
    products: [...products.values()].sort((a, b) => b.quantity - a.quantity),
  };
}

export function reportExcelData(items: ReportTransaction[]) {
  const rows = items.map((item, index) => {
    const row = index + 4;
    const revenue = item.status === "Selesai" ? transactionTotal(item) : 0;
    const profit = transactionProfit(item);
    return [
      item.id,
      new Date(`${item.date}T00:00:00Z`),
      item.time,
      item.cashier,
      item.payment,
      item.status,
      transactionTotal(item),
      revenue,
      transactionCost(item),
      { formula: `H${row}-I${row}`, result: profit },
      {
        formula: `IF(H${row}=0,0,J${row}/H${row})`,
        result: revenue ? profit / revenue : 0,
      },
    ];
  });
  const summary = summarizeReport(items);
  const last = items.length + 3;
  const totalRow = last + 1;
  const sum = (column: string, result: number) => ({
    formula: items.length ? `SUM(${column}4:${column}${last})` : "0",
    result,
  });
  return {
    rows,
    totals: [
      "TOTAL",
      "",
      "",
      "",
      "",
      "",
      sum(
        "G",
        items.reduce((total, item) => total + transactionTotal(item), 0),
      ),
      sum("H", summary.revenue),
      sum("I", summary.cost),
      sum("J", summary.profit),
      {
        formula: `IF(H${totalRow}=0,0,J${totalRow}/H${totalRow})`,
        result: summary.revenue ? summary.profit / summary.revenue : 0,
      },
    ],
  };
}

export function filterReport(
  items: ReportTransaction[],
  start: string,
  end: string,
  payment: string,
) {
  return items.filter(
    (item) =>
      item.date >= start &&
      item.date <= end &&
      (payment === "all" || item.payment === payment),
  );
}

export function reportCsv(items: ReportTransaction[]) {
  const cell = (value: string | number) => {
    const text = String(value);
    return `"${(/^[=+@\-\t\r]/.test(text) ? `'${text}` : text).replaceAll('"', '""')}"`;
  };
  return (
    "\uFEFF" +
    [
      [
        "Transaksi",
        "Tanggal",
        "Waktu",
        "Kasir",
        "Pembayaran",
        "Status",
        "Nilai transaksi",
        "Omzet diakui",
      ],
      ...items.map((item) => [
        item.id,
        item.date,
        item.time,
        item.cashier,
        item.payment,
        item.status,
        transactionTotal(item),
        item.status === "Selesai" ? transactionTotal(item) : 0,
      ]),
    ]
      .map((row) => row.map(cell).join(","))
      .join("\r\n")
  );
}
