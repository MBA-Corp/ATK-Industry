import { useRef, useState } from "react";
import {
  ArrowDownToLine,
  Banknote,
  CalendarDays,
  ChartNoAxesCombined,
  ChevronLeft,
  ChevronRight,
  CreditCard,
  Eye,
  Package,
  ReceiptText,
  Search,
  X,
} from "lucide-react";
import { SelectMenu } from "~/components/select-menu";
import { useToast } from "~/components/toast-provider";
import { downloadExcel } from "~/lib/excel-export";
import { SalesChart } from "~/features/dashboard/sales-chart";
import { formatRupiah } from "~/features/dashboard/dashboard.mock";
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
export function meta() {
  return [
    { title: "Laporan | ATK Industry" },
    {
      name: "description",
      content: "Laporan penjualan, laba kotor, dan performa produk toko.",
    },
  ];
}

export default function Reports() {
  const [period, setPeriod] = useState<string>("week");
  const [payment, setPayment] = useState("all");
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<ReportTransaction | null>(null);
  const setNotice = useToast();
  const dialog = useRef<HTMLDialogElement>(null);
  const range =
    reportPeriods.find((item) => item.value === period) ?? reportPeriods[0];
  const items = filterReport(
    reportTransactions,
    range.start,
    range.end,
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
  const groups: { label: string; value: number }[] = [];
  const start = Date.parse(`${range.start}T00:00:00Z`);
  const dayCount =
    Math.round((Date.parse(`${range.end}T00:00:00Z`) - start) / 86400000) + 1;
  const step = dayCount > 7 ? 5 : 1;
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
          : `${Number(from.slice(8))}–${Number(to.slice(8))} Sep`,
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
        title: `Laporan Penjualan — ${range.label}`,
        sheetName: "Penjualan",
        filename: `laporan-penjualan-${range.start}-${range.end}`,
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
        `${filtered.length} transaksi sesuai filter tabel diekspor ke Excel.`,
      );
    } catch {
      setNotice("Ekspor Excel gagal. Silakan coba lagi.", "error");
    } finally {
      setExporting(false);
    }
  }

  return (
    <div className="reports-page">
      <section className="reports-heading">
        <div>
          <p className="reports-eyebrow">INSIGHT & KINERJA TOKO</p>
          <h1>
            Laporan<span>.</span>
          </h1>
          <p>Kenali performa penjualan dan temukan peluang untuk tumbuh.</p>
        </div>
        <button
          className="report-button primary"
          disabled={!filtered.length || exporting}
          onClick={exportExcel}
        >
          <ArrowDownToLine size={16} />
          {exporting ? "Menyiapkan Excel…" : "Ekspor Excel"}
        </button>
      </section>
      <section className="report-filters" aria-label="Filter laporan">
        <div>
          <CalendarDays size={17} />
          <span>Periode laporan</span>
          <SelectMenu
            className="report-select"
            ariaLabel="Periode laporan"
            value={period}
            options={reportPeriods}
            onChange={(value) => {
              setPeriod(value);
              setPage(1);
            }}
          />
        </div>
        <SelectMenu
          className="report-select"
          ariaLabel="Metode pembayaran laporan"
          icon={<CreditCard size={15} />}
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
          }}
        />
      </section>
      <p className="report-demo">
        <span>
          <i />
          Data contoh
        </span>
        {range.label} · Ringkasan hanya menghitung transaksi selesai.
      </p>
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
              <Icon size={19} />
            </span>
            <div>
              <span>{label}</span>
              <strong>{value}</strong>
              <small>{caption}</small>
            </div>
          </article>
        ))}
      </section>
      <section className="report-chart-grid">
        <article className="report-panel">
          <div className="report-panel-heading">
            <div>
              <h2>Tren penjualan</h2>
              <p>
                {dayCount > 7 ? "Omzet per 5 hari" : "Omzet harian"} pada
                periode terpilih
              </p>
            </div>
            <span className="report-badge">{range.label}</span>
          </div>
          <div className="report-chart-total">
            <strong>{formatRupiah(summary.revenue)}</strong>
            <span>
              Rata-rata transaksi <b>{formatRupiah(summary.average)}</b>
            </span>
          </div>
          <SalesChart
            key={`${period}-${payment}`}
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
            <CreditCard size={18} />
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
            <Package size={18} />
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
              <p>Perhitungan berdasarkan harga modal dummy</p>
            </div>
            <ChartNoAxesCombined size={18} />
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
          </p>
        </article>
      </section>
      <section
        className="report-panel report-transactions"
        id="report-transactions"
      >
        <div className="report-panel-heading">
          <div>
            <h2>Rincian transaksi</h2>
            <p>
              Pencarian dan status di bawah hanya memfilter tabel dan ekspor
              Excel.
            </p>
          </div>
          <span className="report-badge">{filtered.length} transaksi</span>
        </div>
        <div className="report-toolbar">
          <label className="report-search workspace-search">
            <Search size={16} />
            <input
              type="search"
              aria-label="Cari transaksi laporan"
              placeholder="Cari nomor transaksi atau kasir..."
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
        <div className="report-table-wrap">
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
                    <strong className="report-transaction-id">{item.id}</strong>
                  </td>
                  <td>
                    {dateLabel(item.date)} 2026<small>{item.time}</small>
                  </td>
                  <td>{item.cashier}</td>
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
                      className="report-icon-button"
                      aria-label={`Detail ${item.id}`}
                      onClick={() => {
                        setSelected(item);
                        dialog.current?.showModal();
                      }}
                    >
                      <Eye size={15} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {!rows.length && (
          <div className="report-empty">
            <Search size={26} />
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
            Menampilkan {filtered.length ? (currentPage - 1) * 6 + 1 : 0}–
            {Math.min(currentPage * 6, filtered.length)} dari {filtered.length}{" "}
            transaksi
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
              Halaman {currentPage} dari {pages}
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
                <p>DETAIL TRANSAKSI</p>
                <h2 id="report-detail-title">{selected.id}</h2>
              </div>
              <button
                aria-label="Tutup detail transaksi"
                onClick={() => dialog.current?.close()}
              >
                <X size={20} />
              </button>
            </header>
            <p>
              {dateLabel(selected.date)} 2026 · {selected.time} · Kasir{" "}
              {selected.cashier}
            </p>
            <p>
              {selected.payment} · {selected.status}
            </p>
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
                className="report-button secondary"
                onClick={() => dialog.current?.close()}
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
