import { useState } from "react";
import { Link } from "react-router";
import { ArrowDownLeft, ArrowRight, ArrowUpRight, Banknote, CalendarDays, CircleAlert, CreditCard, Package, QrCode, ReceiptText, Search, ShoppingBag } from "lucide-react";
import { SelectMenu } from "~/components/select-menu";
import { bestSellingProducts, dashboardPeriods, formatRupiah, lowStockProducts, recentTransactions, type DashboardPeriod } from "~/features/dashboard/dashboard.mock";
import { StockMovements } from "./stock-movements";
import { SalesChart } from "./sales-chart";


export function DashboardOverview() {
  const [period, setPeriod] = useState<DashboardPeriod>("week");
  const [search, setSearch] = useState("");
  const [payment, setPayment] = useState("all");
  const summary = dashboardPeriods[period];
  const transactions = recentTransactions.filter((transaction) =>
    (period === "week" || transaction.date.startsWith("2026-09")) &&
    (payment === "all" || transaction.payment === payment) &&
    `${transaction.id} ${transaction.cashier}`.toLowerCase().includes(search.toLowerCase().trim()),
  );
  const stats = [
    { label: "Total penjualan", value: formatRupiah(summary.revenue), icon: Banknote, note: "Omzet pada periode terpilih", link: "/reports", tone: "blue" },
    { label: "Transaksi penjualan", value: summary.transactions.toLocaleString("id-ID"), icon: ReceiptText, note: "Transaksi berhasil diselesaikan", link: "/reports", tone: "purple" },
    { label: "Produk terjual", value: summary.sold.toLocaleString("id-ID"), icon: ShoppingBag, note: "Dalam satuan dasar produk", link: "/products", tone: "mint" },
    { label: "Stok perlu perhatian", value: "03", icon: Package, note: "Produk dengan stok menipis", link: "/inventory", tone: "orange" },
  ];

  return <div className="dashboard-page">
    <section className="dashboard-heading"><div><p className="dashboard-eyebrow">DASHBOARD OWNER</p><h1>Setiap hari, selangkah lebih baik<span>.</span></h1><p>Pantau penjualan dan persediaan toko Anda dalam satu tampilan.</p></div><div className="dashboard-heading-actions"><SelectMenu className="dashboard-period" ariaLabel="Periode ringkasan" icon={<CalendarDays size={16} />} value={period} options={[{ value: "week", label: "7 hari terakhir" }, { value: "month", label: "September 2026" }]} onChange={setPeriod} /></div></section>
    <div className="dashboard-period-note"><span className="live-dot" />Pratinjau data toko<span className="note-separator">/</span>{period === "week" ? "25 Sep – 1 Okt 2026" : "1 – 30 Sep 2026"}</div>
    <section className="dashboard-stats" aria-label="Ringkasan operasional">{stats.map(({ label, value, icon: Icon, note, link, tone }) => <article key={label} className={`dashboard-stat ${tone}`}><div className="stat-top"><span className="stat-icon"><Icon size={20} /></span><span className="stat-label">{label}</span></div><div className="stat-value">{value}</div><p>{note}</p><Link to={link}>Lihat detail<ArrowUpRight size={17} /></Link></article>)}</section>
    <section className="dashboard-chart-grid">
      <article className="dashboard-panel sales-panel">
        <div className="panel-heading">
          <div><h2>Tren penjualan</h2><p>Pergerakan omzet pada periode terpilih</p></div>
          <span className="panel-period">{summary.label}</span>
        </div>
        <div className="sales-summary">
          <strong>{formatRupiah(summary.revenue)}</strong>
          <span><i />Total penjualan</span>
        </div>
        <SalesChart key={period} values={summary.sales} labels={summary.labels} />
      </article>
      <article className="dashboard-panel payment-panel"><div className="panel-heading"><div><h2>Metode pembayaran</h2><p>Distribusi penerimaan penjualan</p></div><CreditCard size={18} /></div><div className="payment-donut" role="img" aria-label="Penerimaan tunai 64 persen, QRIS 36 persen"><svg viewBox="0 0 180 180"><circle cx="90" cy="90" r="68" fill="none" stroke="#f0f2f8" strokeWidth="23" /><circle cx="90" cy="90" r="68" fill="none" stroke="#4272f4" strokeWidth="23" strokeDasharray="267 427" strokeLinecap="round" transform="rotate(-90 90 90)" /><circle cx="90" cy="90" r="68" fill="none" stroke="#9a82f6" strokeWidth="23" strokeDasharray="143 427" strokeDashoffset="-278" strokeLinecap="round" transform="rotate(-90 90 90)" /></svg><div><strong>{summary.transactions}</strong><span>transaksi selesai</span></div></div><div className="payment-legend"><div><span><i className="blue-dot" />Tunai <small>64%</small></span><strong>{formatRupiah(summary.cash)}</strong></div><div><span><i className="purple-dot" />QRIS <small>36%</small></span><strong>{formatRupiah(summary.qris)}</strong></div></div><p className="payment-footnote">Pembayaran gabungan dihitung per metode.</p></article>
    </section>
    <section className="dashboard-movements-section" aria-label="Pergerakan persediaan">
      <StockMovements period={period} />
    </section>
    <section className="dashboard-detail-grid">
      <article className="dashboard-panel bestseller-panel"><div className="panel-heading"><div><h2>Produk terlaris</h2><p>Favorit pelanggan · September 2026</p></div><Link to="/reports" className="dashboard-text-link">Lihat laporan<ArrowUpRight size={14} /></Link></div>{bestSellingProducts.map((product, index) => <div className="bestseller-row" key={product.name}><span className={`product-rank ${product.color}`}>{String(index + 1).padStart(2, "0")}</span><div className="bestseller-info"><strong>{product.name}</strong><span>{product.category}</span><div className="product-bar"><i className={product.color} style={{ width: `${product.share}%` }} /></div></div><div className="bestseller-quantity"><strong>{product.sold}</strong><small>terjual</small></div></div>)}</article>
      <article className="dashboard-panel stock-panel" id="stock-alerts"><div className="panel-heading"><div><h2><CircleAlert size={17} />Stok menipis</h2><p>Prioritaskan pengecekan persediaan</p></div><span className="stock-count">3 produk</span></div>{lowStockProducts.map((product) => <div className="stock-row" key={product.sku}><span className="stock-product-icon"><Package size={19} /></span><div><strong>{product.name}</strong><small>{product.sku}</small></div><span className="stock-quantity">{product.stock} <small>{product.unit}</small></span></div>)}<Link className="stock-action" to="/inventory">Periksa persediaan<ArrowRight size={16} /></Link></article>
    </section>
    <section className="dashboard-panel transactions-panel"><div className="panel-heading transaction-heading"><div><h2>Transaksi terbaru</h2><p>Aktivitas penjualan dan kasir pada periode terpilih</p></div><div className="transaction-controls"><label className="transaction-search workspace-search"><Search size={16} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari transaksi atau kasir..." aria-label="Cari transaksi atau kasir" /></label><SelectMenu className="payment-filter" ariaLabel="Filter metode pembayaran" value={payment} options={[{ value: "all", label: "Semua metode" }, { value: "Tunai", label: "Tunai" }, { value: "QRIS", label: "QRIS" }, { value: "Tunai + QRIS", label: "Tunai + QRIS" }]} onChange={setPayment} /></div></div><div className="transaction-table-scroll"><table className="dashboard-table"><thead><tr><th>No. transaksi</th><th>Tanggal & waktu</th><th>Kasir</th><th>Item</th><th>Pembayaran</th><th className="align-right">Total</th><th>Status</th></tr></thead><tbody>{transactions.slice(0, 5).map((transaction) => <tr key={transaction.id}><td><span className="transaction-id"><span><ArrowDownLeft size={15} /></span>{transaction.id}</span></td><td>{transaction.date.slice(8)} {transaction.date.slice(5, 7) === "10" ? "Okt" : "Sep"}<span className="table-time">{transaction.time}</span></td><td><span className="cashier-initial">{transaction.cashier[0]}</span>{transaction.cashier}</td><td>{transaction.items} item</td><td><span className="payment-method">{transaction.payment === "QRIS" ? <QrCode size={14} /> : <Banknote size={14} />}{transaction.payment}</span></td><td className="align-right table-total">{formatRupiah(transaction.total)}</td><td><span className="transaction-status"><i />Selesai</span></td></tr>)}{transactions.length === 0 && <tr><td colSpan={7} className="table-empty">Tidak ada transaksi yang cocok. Coba kata kunci atau metode lain.</td></tr>}</tbody></table></div><div className="transaction-footer"><span>Menampilkan {Math.min(5, transactions.length)} dari {transactions.length} transaksi contoh</span><Link to="/reports">Semua transaksi<ArrowRight size={14} /></Link></div></section>
    <p className="dashboard-data-note">Data ilustrasi untuk pratinjau dashboard. Ringkasan ini belum terhubung ke transaksi toko.</p>
  </div>;
}
