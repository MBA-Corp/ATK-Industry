import { useRef, useState } from "react";
import {
  ArrowDownToLine,
  CalendarDays,
  CheckCheck,
  ChevronLeft,
  ChevronRight,
  Eye,
  FileCheck2,
  Package,
  Printer,
  ReceiptText,
  Search,
  ShoppingBag,
  Truck,
  X,
} from "lucide-react";
import { SelectMenu } from "~/components/select-menu";
import { useToast } from "~/components/toast-provider";
import { downloadExcel } from "~/lib/excel-export";
import { productMockData } from "~/features/products/product.mock";
import { productMaxStock } from "~/features/products/product-stock";
import {
  initialPurchases,
  purchaseTotal,
  statuses,
  suppliers,
  type Purchase,
} from "~/features/purchases/purchase.mock";
import "~/features/purchases/purchase.css";

const money = (value: number) =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  }).format(value);
const dateLabel = (value: string) =>
  new Date(`${value}T12:00:00`).toLocaleDateString("id-ID", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });

export function meta() {
  return [
    { title: "Pembelian | ATK Industry" },
    {
      name: "description",
      content:
        "Pantau riwayat pesanan pembelian dan rincian faktur penerimaan barang dari supplier.",
    },
  ];
}

export default function Purchases() {
  const [purchases] = useState(initialPurchases);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [supplier, setSupplier] = useState("all");
  const [dateMode, setDateMode] = useState("all");
  const [customStart, setCustomStart] = useState("2026-09-20");
  const [customEnd, setCustomEnd] = useState("2026-10-01");
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<Purchase | null>(null);
  const setNotice = useToast();
  const detail = useRef<HTMLDialogElement>(null);

  const startBound = customStart <= customEnd ? customStart : customEnd;
  const endBound = customStart <= customEnd ? customEnd : customStart;
  const filtered = purchases.filter((item) => {
    const inDate =
      dateMode === "all"
        ? true
        : dateMode === "week"
          ? item.date >= "2026-09-25" && item.date <= "2026-10-01"
          : dateMode === "month"
            ? item.date >= "2026-09-01" && item.date <= "2026-09-30"
            : item.date >= startBound && item.date <= endBound;
    return (
      inDate &&
      (status === "all" || item.status === status) &&
      (supplier === "all" || item.supplier === supplier) &&
      `${item.id} ${item.invoiceNo} ${item.supplier} ${item.recordedBy} ${item.lines.map((line) => line.name).join(" ")}`
        .toLocaleLowerCase("id-ID")
        .includes(search.trim().toLocaleLowerCase("id-ID"))
    );
  });

  const pageCount = Math.max(1, Math.ceil(filtered.length / 6));
  const currentPage = Math.min(page, pageCount);
  const rows = filtered.slice((currentPage - 1) * 6, currentPage * 6);
  const active = purchases.filter((item) => item.status !== "Dibatalkan");

  const [exporting, setExporting] = useState(false);
  async function exportExcel() {
    setExporting(true);
    try {
      await downloadExcel({
        title: "Daftar Pembelian & Faktur Supplier",
        sheetName: "Pembelian",
        filename: "pembelian-supplier",
        columns: [
          { title: "Nomor pesanan", width: 20 },
          { title: "No. Faktur", width: 20 },
          { title: "Tanggal", width: 18, format: "dd mmm yyyy" },
          { title: "Supplier", width: 32 },
          { title: "Dicatat oleh", width: 18 },
          { title: "Status", width: 16 },
          { title: "Total pembelian", width: 24, format: '"Rp" #,##0' },
        ],
        rows: filtered.map((item) => [
          item.id,
          item.invoiceNo,
          new Date(`${item.date}T00:00:00Z`),
          item.supplier,
          item.recordedBy,
          item.status,
          purchaseTotal(item),
        ]),
      });
      setNotice(`${filtered.length} data pembelian diekspor ke Excel.`);
    } catch {
      setNotice("Ekspor Excel gagal. Silakan coba lagi.", "error");
    } finally {
      setExporting(false);
    }
  }

  return (
    <div className="purchases-page">
      <section className="purchases-heading">
        <div>
          <p className="purchases-eyebrow">PENGAWASAN PENGADAAN · OWNER</p>
          <h1>Pembelian</h1>
          <p>
            Pantau riwayat pesanan pembelian (PO), rincian faktur supplier, dan
            penerimaan stok yang dicatat melalui terminal kasir.
          </p>
        </div>
        <div className="purchases-actions">
          <button
            className="purchase-button primary"
            onClick={exportExcel}
            disabled={exporting || !filtered.length}
          >
            <ArrowDownToLine size={15} />
            {exporting ? "Menyiapkan Excel…" : "Ekspor Excel"}
          </button>
        </div>
      </section>

      <section className="purchases-summary" aria-label="Ringkasan pembelian">
        {[
          {
            label: "Total nilai pembelian",
            value: money(
              active.reduce((sum, item) => sum + purchaseTotal(item), 0),
            ),
            caption: "Selain pesanan yang dibatalkan",
            icon: ShoppingBag,
            tone: "blue",
          },
          {
            label: "Menunggu penerimaan",
            value: purchases.filter((item) => item.status === "Dipesan").length,
            caption: "Sedang dikirim oleh supplier",
            icon: Truck,
            tone: "amber",
          },
          {
            label: "Pembelian diterima",
            value: purchases.filter((item) => item.status === "Diterima")
              .length,
            caption: "Stok telah diverifikasi kasir",
            icon: CheckCheck,
            tone: "green",
          },
          {
            label: "Draf dicatat kasir",
            value: purchases.filter((item) => item.status === "Draf").length,
            caption: "Menunggu konfirmasi pemesanan",
            icon: ReceiptText,
            tone: "violet",
          },
        ].map(({ label, value, caption, icon: Icon, tone }) => (
          <article key={label}>
            <span className={`purchase-stat-icon ${tone}`}>
              <Icon size={18} />
            </span>
            <span>
              {label}
              <strong>{value}</strong>
              {caption && <small>{caption}</small>}
            </span>
          </article>
        ))}
      </section>

      <p className="purchase-demo">
        <strong>Catatan Tupoksi:</strong> Input pembelian baru dan penerimaan
        barang dilakukan oleh <strong>Kasir / Staf Operasional</strong>. Pada
        dashboard ini, <strong>Owner</strong> dapat mengaudit rincian faktur,
        memantau harga beli, dan memeriksa kesesuaian kapasitas stok gudang.
      </p>

      <section className="purchases-panel dashboard-panel" id="purchase-orders">
        <div className="purchases-panel-heading">
          <div>
            <h2>Riwayat &amp; detail pembelian</h2>
            <p>
              Filter berdasarkan supplier, rentang tanggal custom, atau klik
              nomor PO untuk melihat rincian barang &amp; faktur.
            </p>
          </div>
          <span className="purchase-count" role="status">
            {filtered.length} pesanan
          </span>
        </div>

        <div className="purchases-toolbar">
          <label className="purchase-search workspace-search">
            <Search size={15} />
            <input
              type="search"
              aria-label="Cari pembelian"
              placeholder="Cari nomor PO, no. faktur, supplier, kasir, atau produk…"
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
              }}
            />
          </label>
          <SelectMenu
            className="purchase-supplier"
            ariaLabel="Filter supplier"
            value={supplier}
            options={[
              { value: "all", label: "Semua supplier" },
              ...suppliers.map((item) => ({ value: item, label: item })),
            ]}
            icon={<Truck size={14} />}
            onChange={(value) => {
              setSupplier(value);
              setPage(1);
            }}
          />
          <SelectMenu
            className="purchase-supplier"
            ariaLabel="Filter tanggal pembelian"
            value={dateMode}
            options={[
              { value: "all", label: "Semua tanggal" },
              { value: "week", label: "25 Sep – 1 Okt 2026" },
              { value: "month", label: "September 2026" },
              { value: "custom", label: "Pilih tanggal (Custom)" },
            ]}
            icon={<CalendarDays size={14} />}
            onChange={(value) => {
              setDateMode(value);
              setPage(1);
            }}
          />
        </div>

        {dateMode === "custom" && (
          <div
            className="purchase-custom-dates"
            role="group"
            aria-label="Rentang tanggal pembelian"
          >
            <div className="custom-date-bar">
              <label>
                Dari
                <input
                  type="date"
                  value={customStart}
                  onChange={(e) => {
                    setCustomStart(e.target.value);
                    setPage(1);
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
                  }}
                />
              </label>
            </div>
          </div>
        )}

        <div
          className="purchase-tabs"
          role="group"
          aria-label="Status pembelian"
        >
          {["all", ...statuses].map((item) => (
            <button
              key={item}
              type="button"
              aria-pressed={status === item}
              className={status === item ? "selected" : ""}
              onClick={() => {
                setStatus(item);
                setPage(1);
              }}
            >
              {item === "all" ? "Semua pembelian" : item}
              <span>
                {
                  purchases.filter(
                    (order) => item === "all" || order.status === item,
                  ).length
                }
              </span>
            </button>
          ))}
        </div>

        <div
          className="purchase-table-wrap"
          role="region"
          aria-label="Tabel pesanan pembelian"
          tabIndex={0}
        >
          <table className="purchase-table">
            <thead>
              <tr>
                <th>NO. PESANAN &amp; FAKTUR</th>
                <th>SUPPLIER &amp; TERMIN</th>
                <th>TANGGAL &amp; PENCATAT</th>
                <th>RINCIAN PRODUK</th>
                <th className="purchase-amount">TOTAL PEMBELIAN</th>
                <th>STATUS</th>
                <th>DETAIL</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((item) => (
                <tr key={item.id}>
                  <td>
                    <button
                      type="button"
                      className="purchase-order-link"
                      aria-label={`Lihat detail pesanan ${item.id}`}
                      onClick={() => {
                        setSelected(item);
                        detail.current?.showModal();
                      }}
                    >
                      {item.id}
                    </button>
                    <small>Faktur: {item.invoiceNo}</small>
                  </td>
                  <td>
                    <strong>{item.supplier}</strong>
                    <small>Termin: {item.paymentTerms}</small>
                  </td>
                  <td>
                    <strong>{dateLabel(item.date)}</strong>
                    <small>Dicatat: {item.recordedBy}</small>
                  </td>
                  <td>
                    <strong>{item.lines.length} jenis produk</strong>
                    <small>
                      {item.lines.reduce((sum, line) => sum + line.quantity, 0)}{" "}
                      unit barang
                    </small>
                  </td>
                  <td className="purchase-amount">
                    <strong>{money(purchaseTotal(item))}</strong>
                  </td>
                  <td>
                    <span
                      className={`purchase-status status-${statuses.indexOf(item.status)}`}
                    >
                      <i />
                      {item.status}
                    </span>
                  </td>
                  <td>
                    <button
                      type="button"
                      className="purchase-row-action"
                      aria-label={`Lihat detail pesanan ${item.id}`}
                      onClick={() => {
                        setSelected(item);
                        detail.current?.showModal();
                      }}
                    >
                      <Eye size={13} />
                      Lihat detail
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {rows.length === 0 && (
          <div className="purchase-empty">
            <Search size={26} />
            <h3>Pembelian tidak ditemukan</h3>
            <p>Coba kata kunci atau filter lain.</p>
            <button
              className="purchase-button secondary"
              onClick={() => {
                setSearch("");
                setStatus("all");
                setSupplier("all");
                setDateMode("all");
                setPage(1);
              }}
            >
              Hapus filter
            </button>
          </div>
        )}

        <div className="purchase-pagination">
          <span>
            Menampilkan{" "}
            <strong>
              {filtered.length ? (currentPage - 1) * 6 + 1 : 0}–
              {Math.min(currentPage * 6, filtered.length)}
            </strong>{" "}
            dari <strong>{filtered.length}</strong> pembelian
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
              Halaman <strong>{currentPage}</strong> dari {pageCount}
            </span>
            <button
              aria-label="Halaman berikutnya"
              disabled={currentPage === pageCount}
              onClick={() => setPage(currentPage + 1)}
            >
              <ChevronRight size={15} />
            </button>
          </div>
        </div>
      </section>

      {/* Dialog Detail Pembelian (Read-Only Owner Audit View) */}
      <dialog
        ref={detail}
        className="purchase-dialog"
        aria-labelledby="purchase-detail-title"
      >
        {selected && (
          <>
            <header>
              <div>
                <p>DETAIL PEMBELIAN &amp; FAKTUR SUPPLIER</p>
                <h2 id="purchase-detail-title">
                  {selected.id} · {selected.invoiceNo}
                </h2>
              </div>
              <button
                type="button"
                aria-label="Tutup detail"
                onClick={() => detail.current?.close()}
              >
                <X size={18} />
              </button>
            </header>

            <div className="purchase-detail-meta">
              <div>
                <strong>{selected.supplier}</strong>
                <small>
                  Tanggal: {dateLabel(selected.date)} · Dicatat oleh:{" "}
                  {selected.recordedBy} · Termin: {selected.paymentTerms}
                </small>
              </div>
              <span
                className={`purchase-status status-${statuses.indexOf(selected.status)}`}
              >
                <FileCheck2 size={13} />
                {selected.status}
              </span>
            </div>

            <div className="purchase-detail-list">
              {selected.lines.map((line, index) => {
                const productRef = productMockData.find(
                  (p) => p.id === line.productId,
                );
                const maxCap = productRef ? productMaxStock(productRef) : null;
                return (
                  <div className="purchase-detail-line" key={index}>
                    <Package size={17} />
                    <span>
                      <strong>{line.name}</strong>
                      <small>
                        {line.quantity} unit × {money(line.price)}
                        {productRef
                          ? ` · Stok gudang saat ini: ${productRef.stock}/${maxCap} ${productRef.unit || "PCS"}`
                          : ""}
                      </small>
                    </span>
                    <b>{money(line.quantity * line.price)}</b>
                  </div>
                );
              })}
            </div>

            {selected.note && (
              <p className="purchase-detail-note">
                <strong>Catatan Kasir / Surat Jalan:</strong> {selected.note}
              </p>
            )}

            <footer>
              <span>
                Total nilai pembelian
                <strong>{money(purchaseTotal(selected))}</strong>
              </span>
              <div className="purchases-actions">
                <button
                  type="button"
                  className="purchase-button secondary"
                  onClick={() => window.print()}
                >
                  <Printer size={14} />
                  Cetak bukti PO
                </button>
                <button
                  type="button"
                  className="purchase-button primary"
                  onClick={() => detail.current?.close()}
                >
                  Tutup detail
                </button>
              </div>
            </footer>
          </>
        )}
      </dialog>
    </div>
  );
}
