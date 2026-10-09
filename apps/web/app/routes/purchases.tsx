import { useRef, useState, type FormEvent } from "react";
import {
  ArrowDownToLine,
  ArrowUpRight,
  CheckCheck,
  ChevronLeft,
  ChevronRight,
  Clock3,
  Package,
  Plus,
  ReceiptText,
  Search,
  ShoppingBag,
  Trash2,
  Truck,
  X,
} from "lucide-react";
import { SelectMenu } from "~/components/select-menu";
import { useToast } from "~/components/toast-provider";
import { downloadExcel } from "~/lib/excel-export";
import { productMockData } from "~/features/products/product.mock";
import {
  initialPurchases,
  purchaseTotal,
  statuses,
  suppliers,
  type Purchase,
  type PurchaseLine,
  type PurchaseStatus,
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
const blankLine = (): PurchaseLine => ({
  productId: 1,
  name: productMockData[0].name,
  quantity: 1,
  price: productMockData[0].cost_price ?? 0,
});
const today = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
};

export function meta() {
  return [
    { title: "Pembelian | ATK Industry" },
    {
      name: "description",
      content: "Kelola pesanan pembelian dan penerimaan barang dari supplier.",
    },
  ];
}

export default function Purchases() {
  const [purchases, setPurchases] = useState(initialPurchases);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("all");
  const [supplier, setSupplier] = useState("all");
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<Purchase | null>(null);
  const [lines, setLines] = useState<PurchaseLine[]>([blankLine()]);
  const setNotice = useToast();
  const dialog = useRef<HTMLDialogElement>(null);
  const detail = useRef<HTMLDialogElement>(null);
  const filtered = purchases.filter(
    (item) =>
      (status === "all" || item.status === status) &&
      (supplier === "all" || item.supplier === supplier) &&
      `${item.id} ${item.supplier} ${item.lines.map((line) => line.name).join(" ")}`
        .toLocaleLowerCase("id-ID")
        .includes(search.trim().toLocaleLowerCase("id-ID")),
  );
  const pageCount = Math.max(1, Math.ceil(filtered.length / 6));
  const currentPage = Math.min(page, pageCount);
  const rows = filtered.slice((currentPage - 1) * 6, currentPage * 6);
  const active = purchases.filter((item) => item.status !== "Dibatalkan");
  function updateLine(index: number, patch: Partial<PurchaseLine>) {
    setLines((current) =>
      current.map((line, i) => (i === index ? { ...line, ...patch } : line)),
    );
  }
  function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const next: Purchase = {
      id: `PO-2026-${String(Math.max(...purchases.map((item) => Number(item.id.split("-").at(-1)))) + 1).padStart(4, "0")}`,
      supplier: String(data.get("supplier")),
      date: String(data.get("date")),
      status: "Draf",
      lines: lines.map((line) => ({ ...line })),
      note: String(data.get("note")),
    };
    setPurchases((current) => [next, ...current]);
    setSearch("");
    setStatus("all");
    setSupplier("all");
    setPage(1);
    setNotice(`${next.id} berhasil ditambahkan sebagai draf contoh.`);
    dialog.current?.close();
    event.currentTarget.reset();
  }
  function changeStatus(next: PurchaseStatus) {
    if (!selected) return;
    const updated = { ...selected, status: next };
    setPurchases((current) =>
      current.map((item) => (item.id === selected.id ? updated : item)),
    );
    setSelected(updated);
    setNotice(
      `${selected.id}: status contoh diubah menjadi ${next.toLowerCase()}.`,
    );
  }
  const [exporting, setExporting] = useState(false);
  async function exportExcel() {
    setExporting(true);
    try {
      await downloadExcel({
        title: "Daftar Pembelian",
        sheetName: "Pembelian",
        filename: "pembelian-contoh",
        columns: [
          { title: "Nomor pesanan", width: 23 },
          { title: "Tanggal", width: 18, format: "dd mmm yyyy" },
          { title: "Supplier", width: 36 },
          { title: "Status", width: 18 },
          { title: "Total pembelian", width: 24, format: '"Rp" #,##0' },
        ],
        rows: filtered.map((item) => [
          item.id,
          new Date(`${item.date}T00:00:00Z`),
          item.supplier,
          item.status,
          purchaseTotal(item),
        ]),
      });
      setNotice(`${filtered.length} pembelian diekspor ke Excel.`);
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
          <p className="purchases-eyebrow">PENGELOLAAN PEMBELIAN</p>
          <h1>
            Pembelian<span>.</span>
          </h1>
          <p>Dari pesanan hingga penerimaan, semua tercatat dengan rapi.</p>
        </div>
        <div className="purchases-actions">
          <button
            className="purchase-button secondary"
            onClick={exportExcel}
            disabled={exporting || !filtered.length}
          >
            <ArrowDownToLine size={15} />
            {exporting ? "Menyiapkan Excel…" : "Ekspor Excel"}
          </button>
          <button
            className="purchase-button primary"
            onClick={() => {
              setLines([blankLine()]);
              dialog.current?.showModal();
            }}
          >
            <Plus size={16} />
            Pembelian baru
          </button>
        </div>
      </section>
      <section className="purchases-summary" aria-label="Ringkasan pembelian">
        {[
          {
            label: "Total pembelian",
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
            caption: "Pesanan dalam proses pengiriman",
            icon: Truck,
            tone: "amber",
          },
          {
            label: "Pembelian diterima",
            value: purchases.filter((item) => item.status === "Diterima")
              .length,
            caption: "Pesanan selesai diterima",
            icon: CheckCheck,
            tone: "green",
          },
          {
            label: "Draf pembelian",
            value: purchases.filter((item) => item.status === "Draf").length,
            caption: "Periksa sebelum dipesan",
            icon: ReceiptText,
            tone: "violet",
          },
        ].map(({ label, value, caption, icon: Icon, tone }) => (
          <article key={label}>
            <span className={`purchase-stat-icon ${tone}`}>
              <Icon size={20} />
            </span>
            <span>
              {label}
              <strong>{value}</strong>
              <small>{caption}</small>
            </span>
          </article>
        ))}
      </section>
      <div className="purchase-demo">
        <span>
          <span className="purchase-demo-dot" />
          Mode contoh
        </span>
        Perubahan hanya berlaku selama halaman ini terbuka dan tidak mengubah
        stok.
      </div>
      <section className="purchases-panel dashboard-panel" id="purchase-orders">
        <div className="purchases-panel-heading">
          <div>
            <h2>Daftar pembelian</h2>
            <p>Kelola pesanan dan pantau status penerimaan dari supplier.</p>
          </div>
          <span className="purchase-count">{purchases.length} pesanan</span>
        </div>
        <div className="purchases-toolbar">
          <label className="purchase-search workspace-search">
            <Search size={17} />
            <input
              type="search"
              aria-label="Cari pembelian"
              placeholder="Cari nomor pesanan, supplier, atau produk..."
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
            icon={<Truck size={15} />}
            onChange={(value) => {
              setSupplier(value);
              setPage(1);
            }}
          />
        </div>
        <div
          className="purchase-tabs"
          role="group"
          aria-label="Status pembelian"
        >
          {["all", ...statuses].map((item) => (
            <button
              key={item}
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
        <div className="purchase-table-wrap">
          <table className="purchase-table">
            <thead>
              <tr>
                <th>NOMOR PESANAN</th>
                <th>SUPPLIER</th>
                <th>TANGGAL</th>
                <th>PRODUK</th>
                <th>TOTAL PEMBELIAN</th>
                <th>STATUS</th>
                <th>
                  <span className="sr-only">Detail</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {rows.map((item) => (
                <tr key={item.id}>
                  <td>
                    <button
                      className="purchase-order-link"
                      onClick={() => {
                        setSelected(item);
                        detail.current?.showModal();
                      }}
                    >
                      <span>
                        <ReceiptText size={17} />
                      </span>
                      {item.id}
                    </button>
                  </td>
                  <td>
                    <strong>{item.supplier}</strong>
                    <small>Supplier alat tulis</small>
                  </td>
                  <td>{dateLabel(item.date)}</td>
                  <td>
                    {item.lines.length} produk
                    <small>
                      {item.lines.reduce((sum, line) => sum + line.quantity, 0)}{" "}
                      unit
                    </small>
                  </td>
                  <td className="purchase-amount">
                    {money(purchaseTotal(item))}
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
                      className="purchase-detail-button"
                      aria-label={`Detail ${item.id}`}
                      onClick={() => {
                        setSelected(item);
                        detail.current?.showModal();
                      }}
                    >
                      <ArrowUpRight size={17} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {rows.length === 0 && (
          <div className="purchase-empty">
            <Search size={28} />
            <h3>Pembelian tidak ditemukan</h3>
            <p>Coba kata kunci atau filter lain.</p>
            <button
              className="purchase-button secondary"
              onClick={() => {
                setSearch("");
                setStatus("all");
                setSupplier("all");
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
              <ChevronLeft size={16} />
            </button>
            <span>
              Halaman {currentPage} dari {pageCount}
            </span>
            <button
              aria-label="Halaman berikutnya"
              disabled={currentPage === pageCount}
              onClick={() => setPage(currentPage + 1)}
            >
              <ChevronRight size={16} />
            </button>
          </div>
        </div>
      </section>
      <p className="purchase-footnote">
        <Clock3 size={14} />
        Penerimaan barang membantu menjaga pencatatan pembelian tetap teratur.
      </p>
      <dialog
        ref={dialog}
        className="purchase-dialog"
        aria-labelledby="new-purchase-title"
      >
        <form onSubmit={save}>
          <header>
            <div>
              <p>BUAT PESANAN</p>
              <h2 id="new-purchase-title">Pembelian baru</h2>
            </div>
            <button
              type="button"
              aria-label="Tutup form"
              onClick={() => dialog.current?.close()}
            >
              <X size={20} />
            </button>
          </header>
          <p className="purchase-dialog-note">
            Simpan sebagai draf contoh. Data belum terhubung ke server.
          </p>
          <div className="purchase-form-grid">
            <label>
              Supplier
              <select name="supplier" required>
                {suppliers.map((item) => (
                  <option key={item}>{item}</option>
                ))}
              </select>
            </label>
            <label>
              Tanggal pembelian
              <input name="date" type="date" defaultValue={today()} required />
            </label>
          </div>
          <h3>Produk pesanan</h3>
          <div className="purchase-form-lines">
            {lines.map((line, index) => (
              <div className="purchase-form-line" key={index}>
                <label>
                  Produk
                  <select
                    value={line.productId}
                    onChange={(event) => {
                      const product = productMockData.find(
                        (item) => item.id === Number(event.target.value),
                      )!;
                      updateLine(index, {
                        productId: product.id,
                        name: product.name,
                        price: product.cost_price ?? 0,
                      });
                    }}
                  >
                    {productMockData.map((item) => (
                      <option key={item.id} value={item.id}>
                        {item.name}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  Jumlah
                  <input
                    aria-label={`Jumlah produk ${index + 1}`}
                    type="number"
                    min="1"
                    max="1000000"
                    required
                    value={line.quantity}
                    onChange={(event) =>
                      updateLine(index, {
                        quantity: Number(event.target.value),
                      })
                    }
                  />
                </label>
                <label>
                  Harga beli (Rp)
                  <input
                    aria-label={`Harga produk ${index + 1}`}
                    type="number"
                    min="1"
                    max="1000000000"
                    required
                    value={line.price}
                    onChange={(event) =>
                      updateLine(index, { price: Number(event.target.value) })
                    }
                  />
                </label>
                <button
                  type="button"
                  disabled={lines.length === 1}
                  aria-label={`Hapus produk ${index + 1}`}
                  onClick={() =>
                    setLines((current) => current.filter((_, i) => i !== index))
                  }
                >
                  <Trash2 size={16} />
                </button>
              </div>
            ))}
          </div>
          <button
            type="button"
            className="purchase-add-line"
            onClick={() => setLines((current) => [...current, blankLine()])}
          >
            <Plus size={15} />
            Tambah produk
          </button>
          <label className="purchase-note-label">
            Catatan
            <textarea
              name="note"
              placeholder="Catatan untuk pesanan ini (opsional)"
              maxLength={500}
            />
          </label>
          <footer>
            <span>
              Total pembelian
              <strong>
                {money(
                  lines.reduce(
                    (sum, line) => sum + line.price * line.quantity,
                    0,
                  ),
                )}
              </strong>
            </span>
            <button className="purchase-button primary" type="submit">
              Simpan draf
            </button>
          </footer>
        </form>
      </dialog>
      <dialog
        ref={detail}
        className="purchase-dialog"
        aria-labelledby="purchase-detail-title"
      >
        {selected && (
          <>
            <header>
              <div>
                <p>DETAIL PEMBELIAN</p>
                <h2 id="purchase-detail-title">{selected.id}</h2>
              </div>
              <button
                aria-label="Tutup detail"
                onClick={() => detail.current?.close()}
              >
                <X size={20} />
              </button>
            </header>
            <div className="purchase-detail-meta">
              <span>
                <strong>{selected.supplier}</strong>
                <small>{dateLabel(selected.date)}</small>
              </span>
              <span
                className={`purchase-status status-${statuses.indexOf(selected.status)}`}
              >
                <i />
                {selected.status}
              </span>
            </div>
            {selected.lines.map((line, index) => (
              <div className="purchase-detail-line" key={index}>
                <Package size={19} />
                <span>
                  <strong>{line.name}</strong>
                  <small>
                    {line.quantity} × {money(line.price)}
                  </small>
                </span>
                <b>{money(line.quantity * line.price)}</b>
              </div>
            ))}
            {selected.note && (
              <p className="purchase-detail-note">{selected.note}</p>
            )}
            <p className="purchase-dialog-note">
              Simulasi data contoh. Penerimaan di sini tidak menambah stok
              persediaan.
            </p>
            <footer>
              <span>
                Total pembelian<strong>{money(purchaseTotal(selected))}</strong>
              </span>
              <div className="purchases-actions">
                {selected.status === "Draf" && (
                  <>
                    <button
                      className="purchase-button secondary"
                      onClick={() => changeStatus("Dibatalkan")}
                    >
                      Batalkan draf
                    </button>
                    <button
                      className="purchase-button primary"
                      onClick={() => changeStatus("Dipesan")}
                    >
                      Tandai dipesan
                    </button>
                  </>
                )}
                {selected.status === "Dipesan" && (
                  <button
                    className="purchase-button primary"
                    onClick={() => changeStatus("Diterima")}
                  >
                    <CheckCheck size={16} />
                    Tandai diterima
                  </button>
                )}
              </div>
            </footer>
          </>
        )}
      </dialog>
    </div>
  );
}
