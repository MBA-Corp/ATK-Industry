import { useRef, useState } from "react";
import { Link, useLocation } from "react-router";
import {
  ArrowDownToLine,
  ArrowRight,
  Banknote,
  Boxes,
  CalendarDays,
  ChartNoAxesCombined,
  ChevronLeft,
  ChevronRight,
  CreditCard,
  Eye,
  FileCheck2,
  Package,
  Printer,
  ReceiptText,
  RotateCcw,
  Search,
  X,
} from "lucide-react";
import { SelectMenu } from "~/components/select-menu";
import { useToast } from "~/components/toast-provider";
import { downloadExcel } from "~/lib/excel-export";
import { SalesChart } from "~/features/dashboard/sales-chart";
import { formatRupiah } from "~/features/dashboard/dashboard.mock";
import {
  formatMovementDate,
  initialCustomerRefunds,
  type CustomerRefund,
} from "~/features/inventory/inventory.mock";
import {
  filterReport,
  reportPeriods,
  reportTransactions,
  summarizeReport,
  transactionTotal,
  reportExcelData,
  type ReportTransaction,
} from "~/features/reports/report.mock";
import "~/features/reports/report.css";

const dateLabel = (date: string) =>
  new Date(`${date}T12:00:00`).toLocaleDateString("id-ID", {
    day: "numeric",
    month: "short",
  });

const fullDateLabel = (date: string) =>
  new Date(`${date}T12:00:00`).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });

export function meta() {
  return [
    { title: "Laporan | ATK Industry" },
    {
      name: "description",
      content:
        "Laporan penjualan, daftar barang terjual harian, rincian transaksi, dan refund produk pembeli.",
    },
  ];
}

export default function Reports() {
  const location = useLocation();
  const viewParam = new URLSearchParams(location.search).get("view");
  const activeView: "overview" | "daily" | "transactions" | "refunds" =
    viewParam === "daily"
      ? "daily"
      : viewParam === "transactions"
        ? "transactions"
        : viewParam === "refunds"
          ? "refunds"
          : "overview";

  const [period, setPeriod] = useState<string>("week");
  const [customStart, setCustomStart] = useState("2026-09-25");
  const [customEnd, setCustomEnd] = useState("2026-10-01");
  const [payment, setPayment] = useState("all");
  const [dailyDate, setDailyDate] = useState("all");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [refundHandlingFilter, setRefundHandlingFilter] = useState("all");
  const [page, setPage] = useState(1);
  const [dailyPage, setDailyPage] = useState(1);
  const [selected, setSelected] = useState<ReportTransaction | null>(null);
  const [selectedRefund, setSelectedRefund] = useState<CustomerRefund | null>(
    null,
  );
  const setNotice = useToast();
  const dialog = useRef<HTMLDialogElement>(null);
  const refundDialog = useRef<HTMLDialogElement>(null);

  const presetRange =
    reportPeriods.find((item) => item.value === period) ?? reportPeriods[0];
  const startBound =
    period === "custom"
      ? customStart <= customEnd
        ? customStart
        : customEnd
      : presetRange.start;
  const endBound =
    period === "custom"
      ? customStart <= customEnd
        ? customEnd
        : customStart
      : presetRange.end;
  const rangeLabel =
    period === "custom"
      ? `${fullDateLabel(startBound)} – ${fullDateLabel(endBound)}`
      : presetRange.label;

  const items = filterReport(
    reportTransactions,
    startBound,
    endBound,
    payment,
  );
  const summary = summarizeReport(items);
  const filtered = items.filter(
    (item) =>
      (status === "all" || item.status === status) &&
      `${item.id} ${item.cashier}`
        .toLowerCase()
        .includes(search.trim().toLowerCase()),
  );
  const pages = Math.max(1, Math.ceil(filtered.length / 6));
  const currentPage = Math.min(page, pages);
  const rows = filtered.slice((currentPage - 1) * 6, currentPage * 6);

  // Filtered Refunds for Sub-page 4: Refund Produk Pembeli
  const filteredRefunds = initialCustomerRefunds.filter((item) => {
    const matchesHandling =
      refundHandlingFilter === "all" ||
      item.stockHandling === refundHandlingFilter;
    const term = search.trim().toLowerCase();
    const matchesSearch =
      !term ||
      `${item.id} ${item.receiptNo} ${item.customerName} ${item.productName} ${item.sku} ${item.cashier}`
        .toLowerCase()
        .includes(term);
    return matchesHandling && matchesSearch;
  });
  const totalRefundAmount = initialCustomerRefunds.reduce(
    (sum, item) => sum + item.refundAmount,
    0,
  );
  const restockedRefundUnits = initialCustomerRefunds
    .filter((item) => item.stockHandling === "Kembali ke stok aktif")
    .reduce((sum, item) => sum + item.quantity, 0);
  const quarantinedRefundUnits = initialCustomerRefunds
    .filter((item) => item.stockHandling === "Karantina barang rusak")
    .reduce((sum, item) => sum + item.quantity, 0);

  // Daily Sold Items List & Totals
  const availableDays = [
    ...new Set(summary.completed.map((item) => item.date)),
  ].sort((a, b) => b.localeCompare(a));
  const activeDailyDate =
    dailyDate !== "all" && availableDays.includes(dailyDate)
      ? dailyDate
      : "all";

  const dailySoldMap = new Map<
    string,
    {
      key: string;
      date: string;
      name: string;
      transactions: number;
      quantity: number;
      unitPrice: number;
      revenue: number;
      profit: number;
    }
  >();
  const targetCompleted =
    activeDailyDate === "all"
      ? summary.completed
      : summary.completed.filter((tx) => tx.date === activeDailyDate);

  targetCompleted.forEach((tx) => {
    tx.lines.forEach((line) => {
      const key = `${tx.date}__${line.name}`;
      const prev = dailySoldMap.get(key) ?? {
        key,
        date: tx.date,
        name: line.name,
        transactions: 0,
        quantity: 0,
        unitPrice: line.price,
        revenue: 0,
        profit: 0,
      };
      const lineRevenue = line.quantity * line.price;
      const lineCost = line.quantity * line.cost;
      dailySoldMap.set(key, {
        ...prev,
        transactions: prev.transactions + 1,
        quantity: prev.quantity + line.quantity,
        revenue: prev.revenue + lineRevenue,
        profit: prev.profit + (lineRevenue - lineCost),
      });
    });
  });

  const dailySoldRows = [...dailySoldMap.values()].sort(
    (a, b) =>
      b.date.localeCompare(a.date) ||
      b.quantity - a.quantity ||
      a.name.localeCompare(b.name, "id-ID"),
  );

  const filteredDailySoldRows = dailySoldRows.filter((row) =>
    `${row.name} ${fullDateLabel(row.date)}`
      .toLowerCase()
      .includes(search.trim().toLowerCase()),
  );
  const dailyPageSize = 6;
  const dailyPages = Math.max(
    1,
    Math.ceil(filteredDailySoldRows.length / dailyPageSize),
  );
  const currentDailyPage = Math.min(dailyPage, dailyPages);
  const paginatedDailyRows = filteredDailySoldRows.slice(
    (currentDailyPage - 1) * dailyPageSize,
    currentDailyPage * dailyPageSize,
  );

  const dailyTotals = filteredDailySoldRows.reduce(
    (acc, row) => ({
      quantity: acc.quantity + row.quantity,
      revenue: acc.revenue + row.revenue,
      profit: acc.profit + row.profit,
      transactions: acc.transactions + row.transactions,
    }),
    { quantity: 0, revenue: 0, profit: 0, transactions: 0 },
  );

  const groups: { label: string; value: number }[] = [];
  const start = Date.parse(`${startBound}T00:00:00Z`);
  const dayCount = Math.max(
    1,
    Math.round((Date.parse(`${endBound}T00:00:00Z`) - start) / 86400000) + 1,
  );
  const step = dayCount > 7 ? Math.ceil(dayCount / 6) : 1;
  for (let offset = 0; offset < dayCount; offset += step) {
    const from = new Date(start + offset * 86400000).toISOString().slice(0, 10);
    const to = new Date(
      start + Math.min(offset + step - 1, dayCount - 1) * 86400000,
    )
      .toISOString()
      .slice(0, 10);
    groups.push({
      label:
        step === 1
          ? dateLabel(from)
          : `${Number(from.slice(8))}–${Number(to.slice(8))} ${from.slice(5, 7) === "10" ? "Okt" : "Sep"}`,
      value: summary.completed
        .filter((item) => item.date >= from && item.date <= to)
        .reduce((sum, item) => sum + transactionTotal(item), 0),
    });
  }
  const methods = ["Tunai", "QRIS", "Transfer"].map((name) => {
    const transactions = summary.completed.filter(
      (item) => item.payment === name,
    );
    const total = transactions.reduce(
      (sum, item) => sum + transactionTotal(item),
      0,
    );
    return {
      name,
      total,
      count: transactions.length,
      share: summary.revenue ? (total / summary.revenue) * 100 : 0,
    };
  });

  const [exporting, setExporting] = useState(false);
  async function exportExcel() {
    setExporting(true);
    try {
      await downloadExcel({
        title: `Laporan Penjualan — ${rangeLabel}`,
        sheetName: "Penjualan",
        filename: `laporan-penjualan-${startBound}-${endBound}`,
        columns: [
          { title: "Transaksi", width: 23 },
          { title: "Tanggal", width: 18, format: "dd mmm yyyy" },
          { title: "Waktu", width: 12 },
          { title: "Kasir", width: 18 },
          { title: "Pembayaran", width: 18 },
          { title: "Status", width: 18 },
          { title: "Nilai transaksi", width: 24, format: '"Rp" #,##0' },
          { title: "Omzet diakui", width: 24, format: '"Rp" #,##0' },
          { title: "Harga pokok penjualan", width: 26, format: '"Rp" #,##0' },
          { title: "Laba kotor", width: 24, format: '"Rp" #,##0' },
          { title: "Margin laba (%)", width: 20, format: "0.00%" },
        ],
        ...reportExcelData(filtered),
      });
      setNotice(
        `${filtered.length} transaksi sesuai filter diekspor ke Excel.`,
      );
    } catch {
      setNotice("Ekspor Excel gagal. Silakan coba lagi.", "error");
    } finally {
      setExporting(false);
    }
  }

  const pageHeadingTitle =
    activeView === "daily"
      ? "Daftar barang terjual harian"
      : activeView === "transactions"
        ? "Rincian transaksi"
        : activeView === "refunds"
          ? "Refund produk pembeli"
          : "Laporan penjualan";

  const pageHeadingSubtitle =
    activeView === "daily"
      ? "Rincian kuantitas barang yang terjual setiap hari beserta akumulasi total unit, omzet, dan laba kotor."
      : activeView === "transactions"
        ? "Daftar lengkap struk transaksi penjualan kasir beserta status penyelesaian pembayaran."
        : activeView === "refunds"
          ? "Audit pengembalian produk dari pembeli yang dicatat melalui kasir POS beserta verifikasi bukti struk asli."
          : "Evaluasi omzet penjualan, perhitungan laba kotor, tren harian, dan proporsi metode pembayaran.";

  return (
    <div className="reports-page">
      <section className="reports-heading">
        <div>
          <p className="reports-eyebrow">ANALITIK &amp; KEUANGAN</p>
          <h1>{pageHeadingTitle}</h1>
          <p>{pageHeadingSubtitle}</p>
        </div>
        <div className="report-export-actions">
          <button
            className="report-button primary"
            disabled={!filtered.length || exporting}
            onClick={exportExcel}
          >
            <ArrowDownToLine size={15} />
            {exporting ? "Menyiapkan Excel…" : "Ekspor Excel"}
          </button>
        </div>
      </section>

      {activeView !== "refunds" && (
        <section className="report-filters" aria-label="Filter laporan">
          <div className="report-filter-left">
            <CalendarDays size={16} />
            <span>Periode laporan</span>
            <SelectMenu
              className="report-select"
              ariaLabel="Periode laporan"
              value={period}
              options={[
                ...reportPeriods,
                { value: "custom", label: "Pilih tanggal (Custom)" },
              ]}
              onChange={(value) => {
                setPeriod(value);
                setDailyDate("all");
                setPage(1);
                setDailyPage(1);
              }}
            />
            {period === "custom" && (
              <div
                className="custom-date-bar"
                role="group"
                aria-label="Rentang tanggal laporan"
              >
                <label>
                  Dari
                  <input
                    type="date"
                    value={customStart}
                    onChange={(e) => {
                      setCustomStart(e.target.value);
                      setPage(1);
                      setDailyPage(1);
                    }}
                  />
                </label>
                <label>
                  Sampai
                  <input
                    type="date"
                    value={customEnd}
                    onChange={(e) => {
                      setCustomEnd(e.target.value);
                      setPage(1);
                      setDailyPage(1);
                    }}
                  />
                </label>
              </div>
            )}
          </div>
          <SelectMenu
            className="report-select"
            ariaLabel="Metode pembayaran laporan"
            icon={<CreditCard size={14} />}
            value={payment}
            options={[
              { value: "all", label: "Semua pembayaran" },
              ...["Tunai", "QRIS", "Transfer"].map((value) => ({
                value,
                label: value,
              })),
            ]}
            onChange={(value) => {
              setPayment(value);
              setPage(1);
              setDailyPage(1);
            }}
          />
        </section>
      )}

      <p className="report-demo" id="report-summary-section">
        Sub-halaman Laporan:{" "}
        <Link to="/reports" className="report-inline-link">
          Laporan penjualan
        </Link>{" "}
        ·{" "}
        <Link to="/reports?view=daily" className="report-inline-link">
          Daftar barang terjual harian
        </Link>{" "}
        ·{" "}
        <Link to="/reports?view=transactions" className="report-inline-link">
          Rincian transaksi
        </Link>{" "}
        ·{" "}
        <Link to="/reports?view=refunds" className="report-inline-link">
          Refund produk pembeli
        </Link>
      </p>

      {activeView !== "refunds" ? (
        <section className="report-stats" aria-label="Ringkasan penjualan">
          {[
            {
              label: "Total penjualan",
              value: formatRupiah(summary.revenue),
              caption: "Omzet transaksi selesai",
              icon: Banknote,
              tone: "blue",
            },
            {
              label: "Laba kotor",
              value: formatRupiah(summary.profit),
              caption: "Penjualan dikurangi modal barang",
              icon: ChartNoAxesCombined,
              tone: "green",
            },
            {
              label: "Transaksi selesai",
              value: summary.completed.length,
              caption: `${items.length - summary.completed.length} transaksi dibatalkan`,
              icon: ReceiptText,
              tone: "violet",
            },
            {
              label: "Produk terjual",
              value: summary.sold,
              caption: "Total unit pada transaksi selesai",
              icon: Package,
              tone: "amber",
            },
          ].map(({ label, value, caption, icon: Icon, tone }) => (
            <article key={label}>
              <span className={`report-stat-icon ${tone}`}>
                <Icon size={18} />
              </span>
              <div>
                <span>{label}</span>
                <strong>{value}</strong>
                <small>{caption}</small>
              </div>
            </article>
          ))}
        </section>
      ) : (
        <section className="report-stats" aria-label="Ringkasan refund pembeli">
          {[
            {
              label: "Total refund tercatat",
              value: `${initialCustomerRefunds.length} struk`,
              caption: "Telah diverifikasi struk POS asli",
              icon: RotateCcw,
              tone: "blue",
            },
            {
              label: "Total dana dikembalikan",
              value: formatRupiah(totalRefundAmount),
              caption: "Tunai & QRIS kepada pembeli",
              icon: Banknote,
              tone: "green",
            },
            {
              label: "Kembali ke stok aktif",
              value: `${restockedRefundUnits} unit`,
              caption: "Kemasan utuh & layak jual",
              icon: Package,
              tone: "violet",
            },
            {
              label: "Karantina barang rusak",
              value: `${quarantinedRefundUnits} unit`,
              caption: "Cacat produksi / tidak dijual",
              icon: Boxes,
              tone: "amber",
            },
          ].map(({ label, value, caption, icon: Icon, tone }) => (
            <article key={label}>
              <span className={`report-stat-icon ${tone}`}>
                <Icon size={18} />
              </span>
              <div>
                <span>{label}</span>
                <strong>{value}</strong>
                <small>{caption}</small>
              </div>
            </article>
          ))}
        </section>
      )}

      {/* SUB-PAGE 1: LAPORAN PENJUALAN (OVERVIEW) */}
      {activeView === "overview" && (
        <>
          <section className="report-chart-grid">
            <article className="report-panel">
              <div className="report-panel-heading">
                <div>
                  <h2>Tren penjualan</h2>
                  <p>
                    {dayCount > 7 ? "Omzet per rentang hari" : "Omzet harian"}{" "}
                    pada periode terpilih
                  </p>
                </div>
                <span className="report-badge">{rangeLabel}</span>
              </div>
              <div className="report-chart-total">
                <strong>{formatRupiah(summary.revenue)}</strong>
                <span>
                  Rata-rata transaksi <b>{formatRupiah(summary.average)}</b>
                </span>
              </div>
              <SalesChart
                key={`${period}-${startBound}-${endBound}-${payment}`}
                values={groups.map((group) => group.value)}
                labels={groups.map((group) => group.label)}
              />
            </article>
            <article className="report-panel">
              <div className="report-panel-heading">
                <div>
                  <h2>Metode pembayaran</h2>
                  <p>Proporsi omzet transaksi selesai</p>
                </div>
                <CreditCard size={17} />
              </div>
              <div className="report-payment-total">
                <strong>{summary.completed.length}</strong>
                <span>transaksi selesai</span>
              </div>
              <div
                className="report-payment-stack"
                role="img"
                aria-label={methods
                  .map((item) => `${item.name} ${item.share.toFixed(1)} persen`)
                  .join(", ")}
              >
                {methods.map((item, index) => (
                  <i
                    key={item.name}
                    className={`report-tone-${index}`}
                    style={{ width: `${item.share}%` }}
                  />
                ))}
              </div>
              <div className="report-methods">
                {methods.map((item, index) => (
                  <div key={item.name}>
                    <span>
                      <i className={`report-tone-${index}`} />
                      {item.name}
                      <small>{item.count} transaksi</small>
                    </span>
                    <span>
                      <strong>{formatRupiah(item.total)}</strong>
                      <small>
                        {item.share.toLocaleString("id-ID", {
                          maximumFractionDigits: 1,
                        })}
                        % dari omzet
                      </small>
                    </span>
                  </div>
                ))}
              </div>
            </article>
          </section>

          <section className="report-detail-grid">
            <article className="report-panel">
              <div className="report-panel-heading">
                <div>
                  <h2>Produk terlaris</h2>
                  <p>Diurutkan berdasarkan jumlah unit terjual</p>
                </div>
                <Link
                  to="/reports?view=daily"
                  className="report-button secondary"
                >
                  Lihat terjual harian <ArrowRight size={13} />
                </Link>
              </div>
              <div className="report-products">
                {summary.products.slice(0, 5).map((product, index) => (
                  <div key={product.name}>
                    <span className="report-rank">
                      {String(index + 1).padStart(2, "0")}
                    </span>
                    <div>
                      <strong>{product.name}</strong>
                      <div className="report-product-bar">
                        <i
                          style={{
                            width: `${(product.quantity / summary.products[0].quantity) * 100}%`,
                          }}
                        />
                      </div>
                    </div>
                    <span>
                      <strong>{product.quantity} unit</strong>
                      <small>{formatRupiah(product.revenue)}</small>
                    </span>
                  </div>
                ))}
                {!summary.products.length && (
                  <p className="report-empty">
                    Belum ada produk terjual pada filter ini.
                  </p>
                )}
              </div>
            </article>
            <article className="report-panel">
              <div className="report-panel-heading">
                <div>
                  <h2>Ringkasan laba kotor</h2>
                  <p>Perhitungan berdasarkan harga modal produk</p>
                </div>
                <ChartNoAxesCombined size={17} />
              </div>
              <dl className="report-profit">
                <div>
                  <dt>Total penjualan</dt>
                  <dd>{formatRupiah(summary.revenue)}</dd>
                </div>
                <div>
                  <dt>Harga pokok penjualan</dt>
                  <dd>{formatRupiah(summary.cost)}</dd>
                </div>
                <div className="report-profit-total">
                  <dt>Laba kotor</dt>
                  <dd>{formatRupiah(summary.profit)}</dd>
                </div>
                <div>
                  <dt>Margin laba kotor</dt>
                  <dd>
                    {(summary.revenue
                      ? (summary.profit / summary.revenue) * 100
                      : 0
                    ).toLocaleString("id-ID", { maximumFractionDigits: 1 })}
                    %
                  </dd>
                </div>
              </dl>
              <p className="report-profit-note">
                Laba kotor belum memperhitungkan biaya operasional dan pajak.
                Lihat{" "}
                <Link
                  to="/reports?view=refunds"
                  className="report-inline-link"
                >
                  Refund produk pembeli <ArrowRight size={12} />
                </Link>
              </p>
            </article>
          </section>
        </>
      )}

      {/* SUB-PAGE 2: DAFTAR BARANG TERJUAL HARIAN & TOTALANNYA */}
      {activeView === "daily" && (
        <>
          <section
            className="report-panel report-transactions"
            id="daily-sold-items"
          >
            <div className="report-panel-heading">
              <div>
                <h2>Daftar barang yang terjual harian</h2>
                <p>
                  Rincian kuantitas barang terjual per hari beserta nilai
                  penjualan dan laba kotornya.
                </p>
              </div>
              <div className="report-export-actions">
                <span className="report-badge" role="status">
                  {filteredDailySoldRows.length} baris rekap
                </span>
                <SelectMenu
                  className="report-select"
                  ariaLabel="Filter tanggal penjualan harian"
                  icon={<CalendarDays size={14} />}
                  value={activeDailyDate}
                  options={[
                    {
                      value: "all",
                      label: `Semua hari (${availableDays.length} hari)`,
                    },
                    ...availableDays.map((d) => ({
                      value: d,
                      label: fullDateLabel(d),
                    })),
                  ]}
                  onChange={(val) => {
                    setDailyDate(val);
                    setDailyPage(1);
                  }}
                />
              </div>
            </div>
            <div className="report-toolbar">
              <label className="report-search workspace-search">
                <Search size={15} />
                <input
                  type="search"
                  aria-label="Cari barang terjual harian"
                  placeholder="Cari nama barang atau tanggal terjual…"
                  value={search}
                  onChange={(event) => {
                    setSearch(event.target.value);
                    setDailyPage(1);
                  }}
                />
              </label>
            </div>
            <div
              className="report-table-wrap"
              role="region"
              aria-label="Tabel daftar barang terjual harian"
              tabIndex={0}
            >
              <table className="report-table">
                <thead>
                  <tr>
                    <th>TANGGAL</th>
                    <th>NAMA BARANG</th>
                    <th>FREKUENSI STRUK</th>
                    <th className="report-amount">QTY TERJUAL</th>
                    <th className="report-amount">HARGA JUAL</th>
                    <th className="report-amount">TOTAL PENJUALAN</th>
                    <th className="report-amount">TOTAL LABA KOTOR</th>
                  </tr>
                </thead>
                <tbody>
                  {paginatedDailyRows.map((row) => (
                    <tr key={row.key}>
                      <td>
                        <strong>{fullDateLabel(row.date)}</strong>
                      </td>
                      <td>
                        <strong>{row.name}</strong>
                      </td>
                      <td>{row.transactions} transaksi</td>
                      <td className="report-amount">{row.quantity} unit</td>
                      <td className="report-amount">
                        {formatRupiah(row.unitPrice)}
                      </td>
                      <td className="report-amount">
                        {formatRupiah(row.revenue)}
                      </td>
                      <td className="report-amount">
                        {formatRupiah(row.profit)}
                      </td>
                    </tr>
                  ))}
                  {paginatedDailyRows.length === 0 && (
                    <tr>
                      <td colSpan={7} className="report-empty">
                        Tidak ada barang terjual yang cocok dengan pencarian.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            <footer className="report-pagination">
              <span>
                Menampilkan{" "}
                <strong>
                  {filteredDailySoldRows.length
                    ? (currentDailyPage - 1) * dailyPageSize + 1
                    : 0}
                  –
                  {Math.min(
                    currentDailyPage * dailyPageSize,
                    filteredDailySoldRows.length,
                  )}
                </strong>{" "}
                dari <strong>{filteredDailySoldRows.length}</strong> baris
                barang terjual
              </span>
              <div>
                <button
                  type="button"
                  aria-label="Halaman sebelumnya"
                  disabled={currentDailyPage === 1}
                  onClick={() => setDailyPage(currentDailyPage - 1)}
                >
                  <ChevronLeft size={15} />
                </button>
                <span>
                  Halaman <strong>{currentDailyPage}</strong> dari {dailyPages}
                </span>
                <button
                  type="button"
                  aria-label="Halaman berikutnya"
                  disabled={currentDailyPage === dailyPages}
                  onClick={() => setDailyPage(currentDailyPage + 1)}
                >
                  <ChevronRight size={15} />
                </button>
              </div>
            </footer>
          </section>

          {/* Kartu Terpisah: Total Akumulasi Penjualan Harian */}
          <section
            className="report-panel daily-accumulation-panel"
            aria-label="Total akumulasi barang terjual harian"
          >
            <div className="daily-accumulation-header">
              <div>
                <span className="reports-eyebrow">REKAPITULASI FILTER AKTIF</span>
                <h2>Total akumulasi penjualan harian</h2>
              </div>
              <span className="report-badge">
                {activeDailyDate === "all"
                  ? `${availableDays.length} hari terpilih`
                  : fullDateLabel(activeDailyDate)}
              </span>
            </div>
            <div className="daily-accumulation-grid">
              <div className="daily-accumulation-item">
                <span>Item terekap</span>
                <strong>{filteredDailySoldRows.length} baris produk</strong>
              </div>
              <div className="daily-accumulation-item">
                <span>Frekuensi transaksi</span>
                <strong>{dailyTotals.transactions} struk</strong>
              </div>
              <div className="daily-accumulation-item">
                <span>Total kuantitas terjual</span>
                <strong>
                  {dailyTotals.quantity.toLocaleString("id-ID")} unit
                </strong>
              </div>
              <div className="daily-accumulation-item highlight-lime">
                <span>Total nilai penjualan</span>
                <strong>{formatRupiah(dailyTotals.revenue)}</strong>
              </div>
              <div className="daily-accumulation-item highlight-mint">
                <span>Total laba kotor</span>
                <strong>{formatRupiah(dailyTotals.profit)}</strong>
              </div>
            </div>
          </section>
        </>
      )}

      {/* SUB-PAGE 3: RINCIAN TRANSAKSI */}
      {activeView === "transactions" && (
        <section
          className="report-panel report-transactions"
          id="report-transactions"
        >
          <div className="report-panel-heading">
            <div>
              <h2>Rincian transaksi</h2>
              <p>
                Pencarian dan status di bawah memfilter tabel serta file hasil
                ekspor Excel.
              </p>
            </div>
            <span className="report-badge" role="status">
              {filtered.length} transaksi
            </span>
          </div>
          <div className="report-toolbar">
            <label className="report-search workspace-search">
              <Search size={15} />
              <input
                type="search"
                aria-label="Cari transaksi laporan"
                placeholder="Cari nomor transaksi atau kasir…"
                value={search}
                onChange={(event) => {
                  setSearch(event.target.value);
                  setPage(1);
                }}
              />
            </label>
            <SelectMenu
              className="report-select"
              ariaLabel="Status transaksi laporan"
              value={status}
              options={[
                { value: "all", label: "Semua status" },
                { value: "Selesai", label: "Selesai" },
                { value: "Dibatalkan", label: "Dibatalkan" },
              ]}
              onChange={(value) => {
                setStatus(value);
                setPage(1);
              }}
            />
          </div>
          <div
            className="report-table-wrap"
            role="region"
            aria-label="Tabel rincian transaksi"
            tabIndex={0}
          >
            <table className="report-table">
              <thead>
                <tr>
                  {[
                    "NO. TRANSAKSI",
                    "TANGGAL",
                    "KASIR",
                    "PEMBAYARAN",
                    "NILAI TRANSAKSI",
                    "STATUS",
                    "DETAIL",
                  ].map((label) => (
                    <th scope="col" key={label}>
                      {label}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {rows.map((item) => (
                  <tr key={item.id}>
                    <td>
                      <button
                        type="button"
                        className="report-transaction-id"
                        onClick={() => {
                          setSelected(item);
                          dialog.current?.showModal();
                        }}
                      >
                        {item.id}
                      </button>
                    </td>
                    <td>
                      <strong>
                        {dateLabel(item.date)} {item.date.slice(0, 4)}
                      </strong>
                      <small>{item.time}</small>
                    </td>
                    <td>
                      <strong>{item.cashier}</strong>
                    </td>
                    <td>{item.payment}</td>
                    <td className="report-amount">
                      {formatRupiah(transactionTotal(item))}
                    </td>
                    <td>
                      <span
                        className={`report-status ${item.status === "Selesai" ? "complete" : "cancelled"}`}
                      >
                        <i />
                        {item.status}
                      </span>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="report-icon-button"
                        aria-label={`Detail ${item.id}`}
                        onClick={() => {
                          setSelected(item);
                          dialog.current?.showModal();
                        }}
                      >
                        <Eye size={14} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {!rows.length && (
            <div className="report-empty">
              <Search size={24} />
              <h3>Tidak ada transaksi yang cocok</h3>
              <p>Coba kata kunci lain atau reset filter tabel.</p>
              <button
                className="report-button secondary"
                onClick={() => {
                  setSearch("");
                  setStatus("all");
                  setPage(1);
                }}
              >
                Reset filter tabel
              </button>
            </div>
          )}
          <footer className="report-pagination">
            <span>
              Menampilkan{" "}
              <strong>
                {filtered.length ? (currentPage - 1) * 6 + 1 : 0}–
                {Math.min(currentPage * 6, filtered.length)}
              </strong>{" "}
              dari <strong>{filtered.length}</strong> transaksi
            </span>
            <div>
              <button
                aria-label="Halaman sebelumnya"
                disabled={currentPage === 1}
                onClick={() => setPage(currentPage - 1)}
              >
                <ChevronLeft size={15} />
              </button>
              <span>
                Halaman <strong>{currentPage}</strong> dari {pages}
              </span>
              <button
                aria-label="Halaman berikutnya"
                disabled={currentPage === pages}
                onClick={() => setPage(currentPage + 1)}
              >
                <ChevronRight size={15} />
              </button>
            </div>
          </footer>
        </section>
      )}

      {/* SUB-PAGE 4: REFUND PRODUK PEMBELI (BUKTI STRUK) */}
      {activeView === "refunds" && (
        <section
          className="report-panel report-transactions"
          id="report-refunds"
        >
          <div className="report-panel-heading">
            <div>
              <h2>Daftar refund produk dari pembeli (Bukti Struk)</h2>
              <p>
                Setiap pengembalian barang yang dicatat oleh kasir di terminal
                POS wajib disertai verifikasi struk pembelian asli.
              </p>
            </div>
            <span className="report-badge" role="status">
              {filteredRefunds.length} refund tercatat
            </span>
          </div>
          <div className="report-toolbar">
            <label className="report-search workspace-search">
              <Search size={15} />
              <input
                type="search"
                aria-label="Cari refund produk pembeli"
                placeholder="Cari no. refund, no. struk, nama pembeli, atau produk…"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
              />
            </label>
            <SelectMenu
              className="report-select"
              ariaLabel="Filter perlakuan stok refund"
              value={refundHandlingFilter}
              options={[
                { value: "all", label: "Semua perlakuan stok" },
                {
                  value: "Kembali ke stok aktif",
                  label: "Kembali ke stok aktif",
                },
                {
                  value: "Karantina barang rusak",
                  label: "Karantina barang rusak",
                },
              ]}
              onChange={setRefundHandlingFilter}
            />
          </div>
          <div
            className="report-table-wrap"
            role="region"
            aria-label="Tabel detail refund pembeli"
            tabIndex={0}
          >
            <table className="report-table">
              <thead>
                <tr>
                  <th>NO. REFUND &amp; WAKTU</th>
                  <th>PEMBELI &amp; KASIR</th>
                  <th>PRODUK DIREFUND</th>
                  <th>ALASAN &amp; PERLAKUAN STOK</th>
                  <th className="report-amount">NILAI REFUND</th>
                  <th>BUKTI STRUK POS</th>
                </tr>
              </thead>
              <tbody>
                {filteredRefunds.map((item) => (
                  <tr key={item.id}>
                    <td>
                      <strong>{item.id}</strong>
                      <small>
                        {formatMovementDate(item.refundDate)} ·{" "}
                        {item.refundTime}
                      </small>
                    </td>
                    <td>
                      <strong>{item.customerName}</strong>
                      <small>Kasir: {item.cashier}</small>
                    </td>
                    <td>
                      <strong>
                        {item.productName} ({item.quantity} {item.unit})
                      </strong>
                      <small>SKU: {item.sku}</small>
                    </td>
                    <td className="report-cell-wrap">
                      <strong>{item.reason}</strong>
                      <small>{item.stockHandling}</small>
                    </td>
                    <td className="report-amount">
                      <strong>{formatRupiah(item.refundAmount)}</strong>
                      <small>{item.paymentMethod}</small>
                    </td>
                    <td>
                      <button
                        type="button"
                        className="report-proof-pill"
                        onClick={() => {
                          setSelectedRefund(item);
                          refundDialog.current?.showModal();
                        }}
                      >
                        <ReceiptText size={13} />
                        Detail &amp; struk {item.receiptNo}
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {!filteredRefunds.length && (
            <div className="report-empty">
              <Search size={24} />
              <h3>Data refund tidak ditemukan</h3>
              <p>Coba kata kunci lain atau tampilkan semua perlakuan stok.</p>
              <button
                type="button"
                className="report-button secondary"
                onClick={() => {
                  setSearch("");
                  setRefundHandlingFilter("all");
                }}
              >
                Reset filter refund
              </button>
            </div>
          )}
        </section>
      )}

      <p className="report-footnote">
        Data ilustrasi untuk mencoba laporan. Belum terhubung ke transaksi toko.
      </p>

      <dialog
        className="report-dialog"
        ref={dialog}
        aria-labelledby="report-detail-title"
      >
        {selected && (
          <>
            <header>
              <div>
                <p>DETAIL STRUK TRANSAKSI</p>
                <h2 id="report-detail-title">{selected.id}</h2>
              </div>
              <button
                type="button"
                aria-label="Tutup detail transaksi"
                onClick={() => dialog.current?.close()}
              >
                <X size={18} />
              </button>
            </header>
            <div className="report-detail-meta">
              <div>
                <strong>
                  {dateLabel(selected.date)} 2026 · {selected.time}
                </strong>
                <small>
                  Kasir: {selected.cashier} · Metode: {selected.payment}
                </small>
              </div>
              <span
                className={`report-status ${selected.status === "Selesai" ? "complete" : "cancelled"}`}
              >
                <i />
                {selected.status}
              </span>
            </div>
            <div className="report-line-items">
              {selected.lines.map((line) => (
                <div key={line.name}>
                  <span>
                    <strong>{line.name}</strong>
                    <small>
                      {line.quantity} × {formatRupiah(line.price)}
                    </small>
                  </span>
                  <strong>{formatRupiah(line.quantity * line.price)}</strong>
                </div>
              ))}
            </div>
            <div className="report-dialog-total">
              <span>Nilai transaksi</span>
              <strong>{formatRupiah(transactionTotal(selected))}</strong>
            </div>
            {selected.status === "Dibatalkan" && (
              <p className="report-cancel-note">
                Transaksi dibatalkan dan tidak dihitung dalam omzet, laba kotor,
                atau produk terjual.
              </p>
            )}
            <footer>
              <button
                type="button"
                className="report-button secondary"
                onClick={() => dialog.current?.close()}
              >
                Tutup
              </button>
            </footer>
          </>
        )}
      </dialog>

      {/* Dialog Bukti Struk Refund */}
      <dialog
        className="report-dialog"
        ref={refundDialog}
        aria-labelledby="report-refund-title"
      >
        {selectedRefund && (
          <>
            <header>
              <div>
                <p>DETAIL REFUND &amp; BUKTI STRUK POS</p>
                <h2 id="report-refund-title">
                  {selectedRefund.id} · {selectedRefund.receiptNo}
                </h2>
              </div>
              <button
                type="button"
                aria-label="Tutup bukti struk"
                onClick={() => refundDialog.current?.close()}
              >
                <X size={18} />
              </button>
            </header>
            <div className="report-detail-meta">
              <div>
                <strong>
                  Pembeli: {selectedRefund.customerName} (Kasir:{" "}
                  {selectedRefund.cashier})
                </strong>
                <small>
                  Kode Verifikasi Struk: {selectedRefund.receiptCode} · Waktu
                  Struk: {formatMovementDate(selectedRefund.receiptDate)}{" "}
                  {selectedRefund.receiptTime}
                </small>
              </div>
              <span className="report-status complete">
                <FileCheck2 size={13} />
                Struk Terverifikasi
              </span>
            </div>
            <div className="report-line-items">
              {selectedRefund.receiptLines.map((line) => (
                <div key={line.name}>
                  <span>
                    <strong>{line.name}</strong>
                    <small>
                      {line.qty} × {formatRupiah(line.unitPrice)} (Item pada
                      struk asli)
                    </small>
                  </span>
                  <strong>{formatRupiah(line.qty * line.unitPrice)}</strong>
                </div>
              ))}
            </div>
            <p className="report-cancel-note">
              <strong>Item Direfund:</strong> {selectedRefund.productName} (
              {selectedRefund.quantity} {selectedRefund.unit}) ·{" "}
              <strong>Alasan:</strong> {selectedRefund.reason} ·{" "}
              <strong>Stok:</strong> {selectedRefund.stockHandling}
            </p>
            <div className="report-dialog-total">
              <span>
                Total Dana Dikembalikan ({selectedRefund.paymentMethod})
              </span>
              <strong>{formatRupiah(selectedRefund.refundAmount)}</strong>
            </div>
            <footer>
              <button
                type="button"
                className="report-button secondary"
                onClick={() => window.print()}
              >
                <Printer size={14} />
                Cetak bukti
              </button>
              <button
                type="button"
                className="report-button primary"
                onClick={() => refundDialog.current?.close()}
              >
                Tutup
              </button>
            </footer>
          </>
        )}
      </dialog>
    </div>
  );
}
