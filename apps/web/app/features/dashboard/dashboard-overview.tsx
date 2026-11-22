import { useRef, useState } from "react";
import { Link } from "react-router";
import {
  ArrowDownLeft,
  ArrowRight,
  ArrowUpRight,
  Banknote,
  CalendarDays,
  CircleAlert,
  CreditCard,
  FileCheck2,
  Package,
  Printer,
  QrCode,
  ReceiptText,
  RotateCcw,
  Search,
  ShoppingBag,
  X,
} from "lucide-react";
import { SelectMenu } from "~/components/select-menu";
import {
  bestSellingProducts,
  dashboardPeriods,
  formatRupiah,
  lowStockProducts,
  recentTransactions,
  type DashboardPeriod,
} from "~/features/dashboard/dashboard.mock";
import {
  formatMovementDate,
  initialCustomerRefunds,
  type CustomerRefund,
} from "~/features/inventory/inventory.mock";
import { StockMovements } from "./stock-movements";
import { SalesChart } from "./sales-chart";

export function DashboardOverview() {
  const [period, setPeriod] = useState<DashboardPeriod>("week");
  const [customStart, setCustomStart] = useState("2026-09-25");
  const [customEnd, setCustomEnd] = useState("2026-10-01");
  const [search, setSearch] = useState("");
  const [payment, setPayment] = useState("all");
  const [selectedRefund, setSelectedRefund] = useState<CustomerRefund | null>(
    null,
  );
  const refundDialogRef = useRef<HTMLDialogElement>(null);

  const summary = dashboardPeriods[period];
  const cashShare = Math.round(
    (summary.cash / Math.max(1, summary.revenue)) * 100,
  );
  const qrisShare = 100 - cashShare;
  const startBound = customStart <= customEnd ? customStart : customEnd;
  const endBound = customStart <= customEnd ? customEnd : customStart;
  const transactions = recentTransactions.filter((transaction) => {
    const inPeriod =
      period === "week"
        ? true
        : period === "month"
          ? transaction.date.startsWith("2026-09")
          : transaction.date >= startBound && transaction.date <= endBound;
    return (
      inPeriod &&
      (payment === "all" || transaction.payment === payment) &&
      `${transaction.id} ${transaction.cashier}`
        .toLowerCase()
        .includes(search.toLowerCase().trim())
    );
  });

  const periodCaption =
    period === "week"
      ? "25 September – 1 Oktober 2026 · 7 hari terakhir"
      : period === "month"
        ? "1 – 30 September 2026 · Rekapitulasi bulanan"
        : `${startBound} s/d ${endBound} · Rentang tanggal khusus`;

  const stats = [
    {
      label: "Total penjualan",
      value: formatRupiah(summary.revenue),
      icon: Banknote,
      note: "Omzet pada periode terpilih",
      link: "/reports",
      action: "Laporan penjualan",
      tone: "lime",
    },
    {
      label: "Transaksi penjualan",
      value: summary.transactions.toLocaleString("id-ID"),
      icon: ReceiptText,
      note: "Transaksi berhasil diselesaikan",
      link: "/reports?view=transactions",
      action: "Rincian transaksi",
      tone: "neutral",
    },
    {
      label: "Produk terjual",
      value: summary.sold.toLocaleString("id-ID"),
      icon: ShoppingBag,
      note: "Dalam satuan dasar produk",
      link: "/reports?view=daily",
      action: "Terjual harian & total",
      tone: "neutral",
    },
    {
      label: "Stok perlu perhatian",
      value: String(lowStockProducts.length),
      icon: Package,
      note: "Stok di bawah batas minimum (1–8 unit)",
      link: "#stock-alerts",
      action: "Lihat produk menipis",
      tone: "cream",
    },
  ];

  return (
    <div className="dashboard-page owner-overview">
      <header className="dashboard-heading">
        <div>
          <p className="dashboard-eyebrow">RINGKASAN OPERASIONAL</p>
          <h1>Ringkasan toko</h1>
          <p>{periodCaption}</p>
        </div>
        <div className="dashboard-period-controls">
          <SelectMenu
            className="dashboard-period"
            ariaLabel="Periode ringkasan"
            icon={<CalendarDays size={15} />}
            value={period}
            options={[
              { value: "week", label: "7 hari terakhir" },
              { value: "month", label: "September 2026" },
              { value: "custom", label: "Pilih tanggal (Custom)" },
            ]}
            onChange={setPeriod}
          />
          {period === "custom" && (
            <div
              className="custom-date-bar"
              role="group"
              aria-label="Rentang tanggal khusus"
            >
              <label>
                Dari
                <input
                  type="date"
                  aria-label="Dari tanggal"
                  value={customStart}
                  onChange={(e) => setCustomStart(e.target.value)}
                />
              </label>
              <label>
                Sampai
                <input
                  type="date"
                  aria-label="Sampai tanggal"
                  value={customEnd}
                  onChange={(e) => setCustomEnd(e.target.value)}
                />
              </label>
            </div>
          )}
        </div>
      </header>

      <section className="dashboard-stats" aria-label="Ringkasan operasional">
        {stats.map(({ label, value, icon: Icon, note, link, action, tone }) => (
          <article key={label} className={`dashboard-stat ${tone}`}>
            <div className="stat-top">
              <span className="stat-icon">
                <Icon size={18} />
              </span>
              <span className="stat-label">{label}</span>
            </div>
            <div className="stat-value">{value}</div>
            <p>{note}</p>
            <Link to={link}>
              {action}
              <ArrowUpRight size={15} />
            </Link>
          </article>
        ))}
      </section>

      <section className="dashboard-chart-grid">
        <article className="dashboard-panel sales-panel">
          <div className="panel-heading">
            <div>
              <h2>Tren penjualan</h2>
              <p>Pergerakan omzet harian pada periode terpilih</p>
            </div>
            <span className="panel-period">{summary.label}</span>
          </div>
          <div className="sales-summary">
            <strong>{formatRupiah(summary.revenue)}</strong>
            <span>
              <i />
              Total penjualan
            </span>
          </div>
          <SalesChart
            key={period}
            values={summary.sales}
            labels={summary.labels}
          />
        </article>

        <article className="dashboard-panel payment-panel">
          <div className="panel-heading">
            <div>
              <h2>Metode pembayaran</h2>
              <p>Distribusi penerimaan ({summary.label})</p>
            </div>
            <CreditCard size={17} />
          </div>
          <div
            className="payment-donut"
            role="img"
            aria-label={`Penerimaan tunai ${cashShare} persen, QRIS ${qrisShare} persen`}
          >
            <svg viewBox="0 0 180 180">
              <circle
                cx="90"
                cy="90"
                r="68"
                fill="none"
                stroke="#f1f1f1"
                strokeWidth="23"
              />
              <circle
                cx="90"
                cy="90"
                r="68"
                fill="none"
                stroke="#1f1d3d"
                strokeWidth="23"
                strokeDasharray="267 427"
                strokeLinecap="round"
                transform="rotate(-90 90 90)"
              />
              <circle
                cx="90"
                cy="90"
                r="68"
                fill="none"
                stroke="#c5b0f4"
                strokeWidth="23"
                strokeDasharray="143 427"
                strokeDashoffset="-278"
                strokeLinecap="round"
                transform="rotate(-90 90 90)"
              />
            </svg>
            <div>
              <strong>{summary.transactions}</strong>
              <span>transaksi selesai</span>
            </div>
          </div>
          <div className="payment-legend">
            <div>
              <span>
                <i className="blue-dot" />
                Tunai <small>{cashShare}%</small>
              </span>
              <strong>{formatRupiah(summary.cash)}</strong>
            </div>
            <div>
              <span>
                <i className="purple-dot" />
                QRIS <small>{qrisShare}%</small>
              </span>
              <strong>{formatRupiah(summary.qris)}</strong>
            </div>
          </div>
          <p className="payment-footnote">
            Pembayaran gabungan dihitung proporsional per metode.
          </p>
        </article>
      </section>

      <section
        className="dashboard-movements-section"
        aria-label="Pergerakan persediaan"
      >
        <StockMovements
          period={period}
          customStart={startBound}
          customEnd={endBound}
        />
      </section>

      <section className="dashboard-detail-grid">
        <article className="dashboard-panel bestseller-panel">
          <div className="panel-heading">
            <div>
              <h2>Produk terlaris &amp; terjual harian</h2>
              <p>Unit terjual &amp; estimasi nilai · {summary.label}</p>
            </div>
            <Link to="/reports?view=daily" className="dashboard-text-link">
              Rekap harian
              <ArrowUpRight size={13} />
            </Link>
          </div>
          {bestSellingProducts.map((product, index) => {
            const soldCount =
              period === "week" ? product.sold : product.soldMonth;
            return (
              <div className="bestseller-row" key={product.name}>
                <span className={`product-rank ${product.color}`}>
                  {String(index + 1).padStart(2, "0")}
                </span>
                <div className="bestseller-info">
                  <strong>{product.name}</strong>
                  <span>
                    {product.category} · {formatRupiah(product.unitPrice)}/unit
                  </span>
                  <div className="product-bar">
                    <i
                      className={product.color}
                      style={{ width: `${product.share}%` }}
                    />
                  </div>
                </div>
                <div className="bestseller-quantity">
                  <strong>{soldCount} unit</strong>
                  <small>{formatRupiah(soldCount * product.unitPrice)}</small>
                </div>
              </div>
            );
          })}
          <div className="bestseller-daily-summary">
            <span>
              Total barang terjual ({summary.label}):{" "}
              <strong>{summary.sold.toLocaleString("id-ID")} unit</strong>
            </span>
            <Link to="/reports?view=daily" className="dashboard-text-link">
              Daftar terjual harian <ArrowRight size={13} />
            </Link>
          </div>
        </article>

        <article className="dashboard-panel stock-panel" id="stock-alerts">
          <div className="panel-heading">
            <div>
              <h2>
                <CircleAlert size={17} />
                Stok menipis
              </h2>
              <p>Bandingkan sisa stok dengan batas maksimum kapasitas</p>
            </div>
            <span className="stock-count">{lowStockProducts.length} produk</span>
          </div>
          {lowStockProducts.map((product) => (
            <div className="stock-row" key={product.sku}>
              <span className="stock-product-icon">
                <Package size={17} />
              </span>
              <div>
                <strong>{product.name}</strong>
                <small>
                  {product.sku} · Maks. {product.maxStock} {product.unit}
                </small>
              </div>
              <span className="stock-quantity">
                {product.stock} / {product.maxStock} <small>{product.unit}</small>
              </span>
            </div>
          ))}
          <Link className="stock-action" to="/inventory">
            Periksa persediaan
            <ArrowRight size={15} />
          </Link>
        </article>
      </section>

      {/* Panel Detail Refund Produk dari Pembeli & Bukti Struk di Dashboard Owner */}
      <section
        className="dashboard-panel transactions-panel dashboard-refunds-panel"
        aria-label="Detail refund produk dari pembeli"
      >
        <div className="panel-heading transaction-heading">
          <div>
            <h2>
              <RotateCcw size={17} />
              Detail refund produk dari pembeli (Bukti Struk)
            </h2>
            <p>
              Pantau pengembalian barang yang dicatat kasir beserta verifikasi bukti struk transaksi POS asli
            </p>
          </div>
          <Link
            to="/reports?view=refunds"
            className="dashboard-text-link"
          >
            Halaman refund <ArrowUpRight size={13} />
          </Link>
        </div>
        <div className="transaction-table-scroll">
          <table className="dashboard-table">
            <thead>
              <tr>
                <th>NO. REFUND &amp; TANGGAL</th>
                <th>PEMBELI &amp; KASIR</th>
                <th>PRODUK DIREFUND</th>
                <th>ALASAN &amp; PERLAKUAN STOK</th>
                <th className="align-right">NILAI REFUND</th>
                <th>BUKTI STRUK &amp; DETAIL</th>
              </tr>
            </thead>
            <tbody>
              {initialCustomerRefunds.map((item) => (
                <tr key={item.id}>
                  <td>
                    <strong className="table-cell-title">{item.id}</strong>
                    <small className="table-cell-sub">
                      {formatMovementDate(item.refundDate)} · {item.refundTime}
                    </small>
                  </td>
                  <td>
                    <strong className="table-cell-title">{item.customerName}</strong>
                    <small className="table-cell-sub">Kasir: {item.cashier}</small>
                  </td>
                  <td>
                    <strong className="table-cell-title">{item.productName}</strong>
                    <small className="table-cell-sub">
                      {item.quantity} {item.unit} · {item.sku}
                    </small>
                  </td>
                  <td className="table-cell-wrap">
                    <span className="table-cell-title">{item.reason}</span>
                    <small className="table-cell-sub">{item.stockHandling}</small>
                  </td>
                  <td className="align-right table-total">
                    {formatRupiah(item.refundAmount)}
                    <small className="table-cell-sub">{item.paymentMethod}</small>
                  </td>
                  <td>
                    <button
                      type="button"
                      className="dashboard-refund-proof-btn"
                      onClick={() => {
                        setSelectedRefund(item);
                        refundDialogRef.current?.showModal();
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
      </section>

      <section className="dashboard-panel transactions-panel">
        <div className="panel-heading transaction-heading">
          <div>
            <h2>Transaksi terbaru</h2>
            <p>Aktivitas penjualan kasir ({summary.label})</p>
          </div>
          <div className="transaction-controls">
            <label className="transaction-search workspace-search">
              <Search size={15} />
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Cari transaksi atau kasir…"
                aria-label="Cari transaksi atau kasir"
              />
            </label>
            <SelectMenu
              className="payment-filter"
              ariaLabel="Filter metode pembayaran"
              value={payment}
              options={[
                { value: "all", label: "Semua metode" },
                { value: "Tunai", label: "Tunai" },
                { value: "QRIS", label: "QRIS" },
                { value: "Tunai + QRIS", label: "Tunai + QRIS" },
              ]}
              onChange={setPayment}
            />
          </div>
        </div>
        <div className="transaction-table-scroll">
          <table className="dashboard-table">
            <thead>
              <tr>
                <th>NO. TRANSAKSI</th>
                <th>TANGGAL &amp; WAKTU</th>
                <th>KASIR</th>
                <th>ITEM</th>
                <th>PEMBAYARAN</th>
                <th className="align-right">TOTAL</th>
                <th>STATUS</th>
              </tr>
            </thead>
            <tbody>
              {transactions.slice(0, 5).map((transaction) => (
                <tr key={transaction.id}>
                  <td>
                    <span className="transaction-id">
                      <span>
                        <ArrowDownLeft size={14} />
                      </span>
                      {transaction.id}
                    </span>
                  </td>
                  <td>
                    {transaction.date.slice(8)}{" "}
                    {transaction.date.slice(5, 7) === "10" ? "Okt" : "Sep"}
                    <span className="table-time">{transaction.time}</span>
                  </td>
                  <td>
                    <span className="cashier-initial">
                      {transaction.cashier[0]}
                    </span>
                    {transaction.cashier}
                  </td>
                  <td>{transaction.items} item</td>
                  <td>
                    <span className="payment-method">
                      {transaction.payment === "QRIS" ? (
                        <QrCode size={14} />
                      ) : (
                        <Banknote size={14} />
                      )}
                      {transaction.payment}
                    </span>
                  </td>
                  <td className="align-right table-total">
                    {formatRupiah(transaction.total)}
                  </td>
                  <td>
                    <span className="transaction-status">
                      <i />
                      Selesai
                    </span>
                  </td>
                </tr>
              ))}
              {transactions.length === 0 && (
                <tr>
                  <td colSpan={7} className="table-empty">
                    Tidak ada transaksi yang cocok. Coba kata kunci atau metode
                    lain.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <div className="transaction-footer">
          <span role="status">
            Menampilkan {Math.min(5, transactions.length)} dari{" "}
            {transactions.length} transaksi contoh
          </span>
          <Link to="/reports?view=transactions">
            Semua transaksi
            <ArrowRight size={14} />
          </Link>
        </div>
      </section>

      {/* Dialog Detail Refund & Bukti Struk di Dashboard Owner */}
      <dialog
        ref={refundDialogRef}
        className="dashboard-receipt-dialog"
        aria-labelledby="dashboard-refund-title"
      >
        {selectedRefund && (
          <div>
            <header className="dashboard-receipt-header">
              <div>
                <p>DETAIL REFUND &amp; BUKTI STRUK POS</p>
                <h2 id="dashboard-refund-title">
                  {selectedRefund.id} · {selectedRefund.receiptNo}
                </h2>
              </div>
              <button
                type="button"
                className="dashboard-icon-button"
                aria-label="Tutup detail refund"
                onClick={() => refundDialogRef.current?.close()}
              >
                <X size={18} />
              </button>
            </header>

            <div className="dashboard-receipt-paper">
              <div className="dashboard-receipt-head">
                <strong>ATK INDUSTRY — BUKTI STRUK POS ASLI</strong>
                <span>Jl. Niaga Raya No. 12, Jakarta</span>
                <small>
                  Kode Verifikasi Struk: {selectedRefund.receiptCode}
                </small>
              </div>
              <div className="dashboard-receipt-meta">
                <div>
                  <span>No. Struk Asal</span>
                  <strong>{selectedRefund.receiptNo}</strong>
                </div>
                <div>
                  <span>Waktu Transaksi Struk</span>
                  <strong>
                    {formatMovementDate(selectedRefund.receiptDate)} ·{" "}
                    {selectedRefund.receiptTime}
                  </strong>
                </div>
                <div>
                  <span>Waktu Refund</span>
                  <strong>
                    {formatMovementDate(selectedRefund.refundDate)} ·{" "}
                    {selectedRefund.refundTime}
                  </strong>
                </div>
                <div>
                  <span>Pembeli &amp; Kasir</span>
                  <strong>
                    {selectedRefund.customerName} (Kasir:{" "}
                    {selectedRefund.cashier})
                  </strong>
                </div>
              </div>
              <div className="dashboard-receipt-lines">
                <p>DAFTAR ITEM PADA STRUK ASLI</p>
                {selectedRefund.receiptLines.map((line) => (
                  <div key={line.name}>
                    <span>
                      {line.name} ({line.qty} × {formatRupiah(line.unitPrice)})
                    </span>
                    <strong>{formatRupiah(line.qty * line.unitPrice)}</strong>
                  </div>
                ))}
              </div>
              <div className="dashboard-receipt-verif">
                <div className="dashboard-receipt-stamp">
                  <FileCheck2 size={16} />
                  <span>BUKTI STRUK TERVERIFIKASI · REFUND DISETUJUI</span>
                </div>
                <p>
                  <strong>Produk Direfund:</strong> {selectedRefund.productName}{" "}
                  ({selectedRefund.quantity} {selectedRefund.unit} · SKU:{" "}
                  {selectedRefund.sku})
                </p>
                <p>
                  <strong>Alasan Pembeli:</strong> {selectedRefund.reason}
                </p>
                <p>
                  <strong>Perlakuan Stok:</strong>{" "}
                  {selectedRefund.stockHandling}
                </p>
                <div className="dashboard-receipt-total">
                  <span>
                    Total Pengembalian ({selectedRefund.paymentMethod})
                  </span>
                  <strong>{formatRupiah(selectedRefund.refundAmount)}</strong>
                </div>
              </div>
            </div>

            <footer className="dashboard-receipt-footer">
              <button
                type="button"
                className="dashboard-text-link"
                onClick={() => window.print()}
              >
                <Printer size={14} />
                Cetak bukti struk
              </button>
              <button
                type="button"
                className="dashboard-receipt-close-btn"
                onClick={() => refundDialogRef.current?.close()}
              >
                Tutup detail
              </button>
            </footer>
          </div>
        )}
      </dialog>

      <p className="dashboard-data-note">
        Data ilustrasi untuk pratinjau dashboard. Ringkasan ini belum terhubung
        ke transaksi toko.
      </p>
    </div>
  );
}
