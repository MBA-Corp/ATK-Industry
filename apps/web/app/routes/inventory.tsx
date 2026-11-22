import { useMemo, useRef, useState, type FormEvent } from "react";
import { Link, useLocation } from "react-router";
import {
  ArrowDownLeft,
  ArrowRight,
  ArrowUpDown,
  ArrowUpRight,
  Boxes,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  CircleAlert,
  Package,
  Printer,
  Search,
  SlidersHorizontal,
  X,
} from "lucide-react";
import { EmptyState } from "~/components/empty-state";
import { ErrorState } from "~/components/error-state";
import { SelectMenu } from "~/components/select-menu";
import { useToast } from "~/components/toast-provider";
import { useProductsQuery } from "~/features/products/product.queries";
import {
  inventoryStockMovements,
  inventoryPeriodLabels,
  formatMovementDate,
  isMovementInPeriod,
  type InventoryDirection,
  type InventoryPeriod,
} from "~/features/inventory/inventory.mock";
import {
  isOverMaxStock,
  lowStockThreshold,
  productMaxStock,
  stockStatus,
} from "~/features/products/product-stock";
import type { Product } from "~/features/products/product.types";
import "~/features/inventory/inventory.css";

type StockFilter = "all" | "low" | "out" | "over" | "healthy";
const noProducts: Product[] = [];
const pageSize = 8;
const movementPageSize = 6;
const pages = (items: number, size: number) =>
  Math.max(1, Math.ceil(items / size));

export function meta() {
  return [
    { title: "Persediaan | ATK Industry" },
    {
      name: "description",
      content:
        "Pantau kondisi stok, batas maksimum, serta halaman riwayat pergerakan stok.",
    },
  ];
}

export default function Inventory() {
  const location = useLocation();
  const isMovementsPage =
    location.pathname.endsWith("/movements") ||
    location.search.includes("view=movements") ||
    location.search.includes("page=movements");

  const notify = useToast();
  const [period, setPeriod] = useState<InventoryPeriod>("week");
  const [customStart, setCustomStart] = useState("2026-09-25");
  const [customEnd, setCustomEnd] = useState("2026-10-01");
  const [filter, setFilter] = useState<StockFilter>("all");
  const [direction, setDirection] = useState<"all" | InventoryDirection>("all");
  const [category, setCategory] = useState("all");
  const [sort, setSort] = useState("stock-asc");
  const [productSearch, setProductSearch] = useState("");
  const [movementSearch, setMovementSearch] = useState("");
  const [productPage, setProductPage] = useState(1);
  const [movementPage, setMovementPage] = useState(1);

  // Max stock overrides per product ID
  const [maxStockOverrides, setMaxStockOverrides] = useState<
    Record<number, number>
  >({});
  const [editingMaxProduct, setEditingMaxProduct] = useState<Product | null>(
    null,
  );
  const maxDialogRef = useRef<HTMLDialogElement>(null);

  const query = useProductsQuery();
  const products = query.data ?? noProducts;
  const getMax = (item: Product) =>
    productMaxStock(item, maxStockOverrides[item.id]);

  const activeProducts = products.filter((item) => item.is_active !== false);
  const lowCount = activeProducts.filter(
    (item) => stockStatus(item.stock) === "low",
  ).length;
  const outCount = activeProducts.filter(
    (item) => stockStatus(item.stock) === "out",
  ).length;
  const overCount = activeProducts.filter((item) =>
    isOverMaxStock(item.stock, getMax(item)),
  ).length;
  const healthyCount = activeProducts.filter(
    (item) =>
      stockStatus(item.stock) === "healthy" &&
      !isOverMaxStock(item.stock, getMax(item)),
  ).length;

  const sortedMovements = useMemo(
    () =>
      [...inventoryStockMovements].sort((a, b) =>
        `${b.date}T${b.time}`.localeCompare(`${a.date}T${a.time}`),
      ),
    [],
  );
  const movements = useMemo(
    () =>
      sortedMovements.filter((item) =>
        isMovementInPeriod(item, period, customStart, customEnd),
      ),
    [sortedMovements, period, customStart, customEnd],
  );
  const inMovementsCount = movements.filter(
    (item) => item.direction === "in",
  ).length;
  const outMovementsCount = movements.filter(
    (item) => item.direction === "out",
  ).length;
  const distinctMovedSkus = new Set(movements.map((item) => item.sku)).size;

  const categories = useMemo(
    () =>
      [
        ...new Set(
          products
            .map((item) => item.category)
            .filter((value): value is string => Boolean(value)),
        ),
      ].sort((a, b) => a.localeCompare(b, "id-ID")),
    [products],
  );
  const visibleStock = useMemo(() => {
    const term = productSearch.trim().toLocaleLowerCase("id-ID");
    return products
      .filter((item) => {
        const searchable = [
          item.name,
          item.sku,
          item.barcode ?? "",
          item.category ?? "",
        ];
        const matchesText =
          !term ||
          searchable.some((value) =>
            value.toLocaleLowerCase("id-ID").includes(term),
          );
        const maxVal = productMaxStock(item, maxStockOverrides[item.id]);
        const isOver = isOverMaxStock(item.stock, maxVal);
        const matchesFilter =
          filter === "all" ||
          (item.is_active !== false &&
            (filter === "over"
              ? isOver
              : filter === "healthy"
                ? stockStatus(item.stock) === "healthy" && !isOver
                : stockStatus(item.stock) === filter));
        return (
          matchesText &&
          matchesFilter &&
          (category === "all" || item.category === category)
        );
      })
      .sort((a, b) =>
        sort === "name"
          ? a.name.localeCompare(b.name, "id-ID")
          : sort === "stock-desc"
            ? b.stock - a.stock
            : a.stock - b.stock || a.name.localeCompare(b.name, "id-ID"),
      );
  }, [products, productSearch, filter, category, sort, maxStockOverrides]);

  const productPageCount = pages(visibleStock.length, pageSize);
  const currentProductPage = Math.min(productPage, productPageCount);
  const stockRows = visibleStock.slice(
    (currentProductPage - 1) * pageSize,
    currentProductPage * pageSize,
  );
  const visibleMovements = useMemo(() => {
    const term = movementSearch.trim().toLocaleLowerCase("id-ID");
    return movements.filter(
      (item) =>
        (direction === "all" || item.direction === direction) &&
        (!term ||
          [item.product, item.sku, item.source, item.reference, item.user].some(
            (value) => value.toLocaleLowerCase("id-ID").includes(term),
          )),
    );
  }, [movements, direction, movementSearch]);
  const movementPageCount = pages(visibleMovements.length, movementPageSize);
  const currentMovementPage = Math.min(movementPage, movementPageCount);
  const movementRows = visibleMovements.slice(
    (currentMovementPage - 1) * movementPageSize,
    currentMovementPage * movementPageSize,
  );
  const periodLabel =
    period === "custom"
      ? `${customStart} s/d ${customEnd}`
      : inventoryPeriodLabels[period];

  function saveMaxStock(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!editingMaxProduct) return;
    const data = new FormData(event.currentTarget);
    const nextMax = Math.max(1, Number(data.get("max_stock") || 100));
    setMaxStockOverrides((prev) => ({
      ...prev,
      [editingMaxProduct.id]: nextMax,
    }));
    notify(
      `Batas maksimum stok ${editingMaxProduct.name} diperbarui menjadi ${nextMax} ${editingMaxProduct.unit || "unit"}.`,
    );
    maxDialogRef.current?.close();
  }

  // PAGE 2: RIWAYAT PERGERAKAN STOK (MURNI PERGERAKAN STOK, TANPA REFUND)
  if (isMovementsPage) {
    return (
      <div className="inventory-page">
        <section
          className="inventory-print-list plain-print-list"
          aria-label="Riwayat pergerakan stok untuk cetak"
        >
          <header>
            <p>ATK INDUSTRY</p>
            <h1>Riwayat Pergerakan Stok</h1>
            <p>
              {periodLabel} • {visibleMovements.length} aktivitas sesuai filter
            </p>
          </header>
          <table>
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Produk / SKU</th>
                <th>Sumber</th>
                <th>Referensi</th>
                <th>Petugas</th>
                <th>Perubahan</th>
              </tr>
            </thead>
            <tbody>
              {visibleMovements.map((item) => (
                <tr key={item.id}>
                  <td>
                    {formatMovementDate(item.date)}
                    <br />
                    {item.time}
                  </td>
                  <td>
                    {item.product}
                    <br />
                    {item.sku}
                  </td>
                  <td>{item.source}</td>
                  <td>{item.reference}</td>
                  <td>{item.user}</td>
                  <td className="print-number">
                    {item.direction === "in" ? "+" : "−"}
                    {item.quantity} {item.unit}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>

        <section className="inventory-heading">
          <div>
            <p className="inventory-eyebrow">PERSEDIAAN · LOG AKTIVITAS</p>
            <h1>Riwayat pergerakan stok</h1>
            <p>
              Audit keluar-masuk barang dari penerimaan pembelian, penjualan kasir, dan penyesuaian opname gudang.
            </p>
          </div>
          <div className="inventory-actions">
            <Link to="/inventory" className="inventory-print">
              Kondisi stok
              <ArrowRight size={14} />
            </Link>
            <button
              className="inventory-print"
              type="button"
              onClick={() => window.print()}
            >
              <Printer size={15} />
              Cetak riwayat
            </button>
          </div>
        </section>

        <section
          className="inventory-summary"
          aria-label="Ringkasan pergerakan stok"
        >
          <article>
            <span className="inventory-summary-icon blue">
              <ArrowUpDown size={18} />
            </span>
            <div>
              <span>Total pergerakan</span>
              <strong>{movements.length}</strong>
              <small>{periodLabel}</small>
            </div>
          </article>
          <article>
            <span className="inventory-summary-icon green">
              <ArrowDownLeft size={18} />
            </span>
            <div>
              <span>Barang masuk</span>
              <strong>{inMovementsCount}</strong>
              <small>Penerimaan PO &amp; penyesuaian</small>
            </div>
          </article>
          <article>
            <span className="inventory-summary-icon orange">
              <ArrowUpRight size={18} />
            </span>
            <div>
              <span>Barang keluar</span>
              <strong>{outMovementsCount}</strong>
              <small>Penjualan kasir &amp; opname</small>
            </div>
          </article>
          <article>
            <span className="inventory-summary-icon rose">
              <Boxes size={18} />
            </span>
            <div>
              <span>SKU bergerak</span>
              <strong>{distinctMovedSkus}</strong>
              <small>Produk aktif pada periode ini</small>
            </div>
          </article>
        </section>

        <section
          className="inventory-panel dashboard-panel inventory-history"
          id="inventory-history"
        >
          <div className="inventory-panel-heading">
            <div>
              <h2>Daftar riwayat keluar-masuk barang</h2>
              <p>
                Filter berdasarkan rentang tanggal, pencarian produk/referensi, atau arah pergerakan.
              </p>
            </div>
            <span className="inventory-count" role="status">
              {visibleMovements.length} aktivitas
            </span>
          </div>
          <div className="inventory-history-toolbar">
            <SelectMenu
              className="inventory-period"
              ariaLabel="Periode riwayat stok"
              icon={<CalendarDays size={15} />}
              value={period}
              options={[
                { value: "week", label: inventoryPeriodLabels.week },
                { value: "month", label: inventoryPeriodLabels.month },
                { value: "custom", label: "Pilih tanggal (Custom)" },
              ]}
              onChange={(value) => {
                setPeriod(value);
                setMovementPage(1);
              }}
            />
            {period === "custom" && (
              <div
                className="custom-date-bar"
                role="group"
                aria-label="Filter tanggal pergerakan"
              >
                <label>
                  Dari
                  <input
                    type="date"
                    value={customStart}
                    onChange={(e) => {
                      setCustomStart(e.target.value);
                      setMovementPage(1);
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
                      setMovementPage(1);
                    }}
                  />
                </label>
              </div>
            )}
            <label className="inventory-search workspace-search">
              <Search size={15} />
              <span className="sr-only">Cari riwayat pergerakan</span>
              <input
                value={movementSearch}
                onChange={(event) => {
                  setMovementSearch(event.target.value);
                  setMovementPage(1);
                }}
                placeholder="Cari produk, sumber, atau referensi…"
              />
            </label>
            <SelectMenu
              className="inventory-select"
              ariaLabel="Jenis pergerakan"
              value={direction}
              options={[
                { value: "all", label: "Barang masuk & keluar" },
                { value: "in", label: "Barang masuk" },
                { value: "out", label: "Barang keluar" },
              ]}
              onChange={(value) => {
                setDirection(value);
                setMovementPage(1);
              }}
            />
          </div>
          {movementRows.length === 0 && (
            <EmptyState
              title="Aktivitas tidak ditemukan"
              description="Belum ada aktivitas yang cocok untuk periode ini."
              action={
                <button
                  type="button"
                  className="inventory-print"
                  onClick={() => {
                    setMovementSearch("");
                    setDirection("all");
                    setMovementPage(1);
                  }}
                >
                  Hapus filter pencarian
                </button>
              }
            />
          )}
          {movementRows.length > 0 && (
            <>
              <div
                className="inventory-table-wrap"
                role="region"
                aria-label="Tabel riwayat pergerakan stok"
                tabIndex={0}
              >
                <table className="inventory-history-table">
                  <thead>
                    <tr>
                      <th>TANGGAL</th>
                      <th>PRODUK</th>
                      <th>SUMBER</th>
                      <th>NO. REFERENSI</th>
                      <th>PETUGAS</th>
                      <th>PERUBAHAN</th>
                    </tr>
                  </thead>
                  <tbody>
                    {visibleMovements.map((item) => (
                      <tr
                        key={item.id}
                        className={
                          movementRows.includes(item)
                            ? undefined
                            : "inventory-print-row"
                        }
                      >
                        <td>
                          <strong>{formatMovementDate(item.date)}</strong>
                          <small>{item.time}</small>
                        </td>
                        <td>
                          <span className="movement-product">
                            <strong>{item.product}</strong>
                            <small>{item.sku}</small>
                          </span>
                        </td>
                        <td>
                          <span className={`movement-source ${item.direction}`}>
                            <i />
                            {item.source}
                          </span>
                        </td>
                        <td>
                          <span className="movement-reference">
                            {item.reference}
                          </span>
                        </td>
                        <td>
                          <span className="movement-user">
                            <i>{item.user[0]}</i>
                            {item.user}
                          </span>
                        </td>
                        <td>
                          <span
                            className={`movement-quantity ${item.direction}`}
                          >
                            {item.direction === "in" ? "+" : "-"}
                            {item.quantity}
                            <small>{item.unit}</small>
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="inventory-pagination">
                <span>
                  Menampilkan{" "}
                  <strong>
                    {(currentMovementPage - 1) * movementPageSize + 1}-
                    {Math.min(
                      currentMovementPage * movementPageSize,
                      visibleMovements.length,
                    )}
                  </strong>{" "}
                  dari <strong>{visibleMovements.length}</strong> aktivitas
                </span>
                <div>
                  <button
                    aria-label="Halaman aktivitas sebelumnya"
                    disabled={currentMovementPage <= 1}
                    onClick={() => setMovementPage(currentMovementPage - 1)}
                  >
                    <ChevronLeft size={16} />
                  </button>
                  <span>
                    Halaman <strong>{currentMovementPage}</strong> dari{" "}
                    {movementPageCount}
                  </span>
                  <button
                    aria-label="Halaman aktivitas berikutnya"
                    disabled={currentMovementPage >= movementPageCount}
                    onClick={() => setMovementPage(currentMovementPage + 1)}
                  >
                    <ChevronRight size={16} />
                  </button>
                </div>
              </div>
            </>
          )}
          <p className="inventory-demo-note">
            Riwayat data contoh: {periodLabel}.
          </p>
        </section>

        <p className="inventory-data-note">
          Data riwayat pergerakan stok bersifat ilustrasi dan belum tersimpan di
          database.
        </p>
      </div>
    );
  }

  // PAGE 1: KONDISI STOK & BATAS MAKSIMUM (DEFAULT /inventory PAGE)
  return (
    <div className="inventory-page">
      <section
        className="inventory-print-list plain-print-list"
        aria-label="Daftar kondisi stok untuk cetak"
      >
        <header>
          <p>ATK INDUSTRY</p>
          <h1>Daftar Kondisi Stok</h1>
          <p>Data contoh • {visibleStock.length} produk sesuai filter</p>
        </header>
        <p className="inventory-print-meta">
          Kategori: {category === "all" ? "Semua kategori" : category} ·
          Kondisi:{" "}
          {
            {
              all: "Semua",
              low: "Menipis",
              out: "Habis",
              over: "Lewat batas maks.",
              healthy: "Tersedia",
            }[filter]
          }{" "}
          · Pencarian: {productSearch || "—"}
        </p>
        <table>
          <thead>
            <tr>
              <th>No.</th>
              <th>SKU</th>
              <th>Nama produk</th>
              <th>Kategori</th>
              <th>Stok</th>
              <th>Batas Maks.</th>
              <th>Satuan</th>
              <th>Kondisi</th>
            </tr>
          </thead>
          <tbody>
            {visibleStock.map((product, index) => (
              <tr key={product.id}>
                <td>{index + 1}</td>
                <td>{product.sku}</td>
                <td>
                  {product.name}
                  {product.is_active === false ? " (Nonaktif)" : ""}
                </td>
                <td>{product.category || "—"}</td>
                <td className="print-number">{product.stock}</td>
                <td className="print-number">{getMax(product)}</td>
                <td>{product.unit || "unit"}</td>
                <td>
                  {
                    { out: "Habis", low: "Menipis", healthy: "Tersedia" }[
                      stockStatus(product.stock)
                    ]
                  }
                </td>
              </tr>
            ))}
            {!visibleStock.length && (
              <tr>
                <td colSpan={8}>Tidak ada produk sesuai filter.</td>
              </tr>
            )}
          </tbody>
        </table>
      </section>

      <section className="inventory-heading">
        <div>
          <p className="inventory-eyebrow">PERSEDIAAN · KONTROL GUDANG</p>
          <h1>Kondisi stok</h1>
          <p>
            Pantau ketersediaan stok aktual serta atur batas maksimum penyimpanan gudang per SKU.
          </p>
        </div>
        <div className="inventory-actions">
          <Link to="/inventory?view=movements" className="inventory-print">
            Riwayat pergerakan stok
            <ArrowRight size={14} />
          </Link>
          <button
            className="inventory-print"
            type="button"
            disabled={query.isPending || query.isError}
            onClick={() => window.print()}
          >
            <Printer size={15} />
            Cetak daftar
          </button>
        </div>
      </section>

      <section className="inventory-summary" aria-label="Ringkasan kondisi stok">
        <article>
          <span className="inventory-summary-icon blue">
            <Package size={18} />
          </span>
          <div>
            <span>Produk terdaftar</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : products.length}
            </strong>
            <small>{activeProducts.length} produk aktif</small>
          </div>
        </article>
        <article>
          <span className="inventory-summary-icon orange">
            <CircleAlert size={18} />
          </span>
          <div>
            <span>Stok menipis</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : lowCount}
            </strong>
            <small>Produk aktif · 1–{lowStockThreshold} unit</small>
          </div>
        </article>
        <article>
          <span className="inventory-summary-icon rose">
            <Boxes size={18} />
          </span>
          <div>
            <span>Stok habis</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : outCount}
            </strong>
            <small>Produk aktif tanpa stok</small>
          </div>
        </article>
        <article>
          <span className="inventory-summary-icon green">
            <ArrowUpDown size={18} />
          </span>
          <div>
            <span>Lewat batas maks.</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : overCount}
            </strong>
            <small>{healthyCount} produk dalam batas ideal</small>
          </div>
        </article>
      </section>

      <section
        className="inventory-panel dashboard-panel"
        id="inventory-low-stock"
      >
        <div className="inventory-panel-heading">
          <div>
            <h2>Kondisi stok &amp; batas maksimum</h2>
            <p>
              Atur batas maksimum stok per SKU agar pengadaan barang tidak melebihi kapasitas gudang.
            </p>
          </div>
          <span className="inventory-count" role="status">
            {query.isSuccess && `${visibleStock.length} produk`}
          </span>
        </div>
        <div className="inventory-toolbar">
          <label className="inventory-search workspace-search">
            <Search size={15} />
            <span className="sr-only">Cari produk</span>
            <input
              value={productSearch}
              onChange={(event) => {
                setProductSearch(event.target.value);
                setProductPage(1);
              }}
              placeholder="Cari nama, SKU, atau barcode…"
            />
          </label>
          <SelectMenu
            className="inventory-select"
            ariaLabel="Kategori"
            icon={<SlidersHorizontal size={14} />}
            value={category}
            options={[
              { value: "all", label: "Semua kategori" },
              ...categories.map((item) => ({ value: item, label: item })),
            ]}
            onChange={(value) => {
              setCategory(value);
              setProductPage(1);
            }}
          />
          <SelectMenu
            className="inventory-select"
            ariaLabel="Urutkan produk"
            icon={<ArrowUpDown size={14} />}
            value={sort}
            options={[
              { value: "stock-asc", label: "Stok terendah" },
              { value: "stock-desc", label: "Stok tertinggi" },
              { value: "name", label: "Nama A-Z" },
            ]}
            onChange={(value) => {
              setSort(value);
              setProductPage(1);
            }}
          />
        </div>
        <div
          className="inventory-tabs"
          role="group"
          aria-label="Filter status stok"
        >
          {(
            [
              { value: "all", label: "Semua stok", count: products.length },
              { value: "low", label: "Menipis", count: lowCount },
              { value: "out", label: "Habis", count: outCount },
              {
                value: "over",
                label: "Melebihi batas maks.",
                count: overCount,
              },
              { value: "healthy", label: "Tersedia", count: healthyCount },
            ] as const
          ).map((item) => (
            <button
              key={item.value}
              type="button"
              aria-pressed={filter === item.value}
              className={filter === item.value ? "selected" : ""}
              onClick={() => {
                setFilter(item.value);
                setProductPage(1);
              }}
            >
              {item.label}
              {query.isSuccess && (
                <span className="tab-count">{item.count}</span>
              )}
            </button>
          ))}
        </div>
        {query.isPending && (
          <p className="inventory-loading" role="status">
            Memuat data persediaan...
          </p>
        )}
        {query.isError && (
          <ErrorState
            title="Persediaan belum dapat dimuat"
            description="Periksa koneksi lalu coba kembali."
            onRetry={() => void query.refetch()}
            isRetrying={query.isFetching}
          />
        )}
        {!query.isPending && !query.isError && stockRows.length === 0 && (
          <EmptyState
            title="Produk tidak ditemukan"
            description="Coba kata kunci atau filter lain."
            action={
              <button
                type="button"
                className="inventory-print"
                onClick={() => {
                  setProductSearch("");
                  setCategory("all");
                  setFilter("all");
                  setProductPage(1);
                }}
              >
                Hapus filter
              </button>
            }
          />
        )}
        {!query.isPending && !query.isError && stockRows.length > 0 && (
          <>
            <div
              className="inventory-table-wrap"
              role="region"
              aria-label="Tabel kondisi stok"
              tabIndex={0}
            >
              <table className="inventory-table">
                <thead>
                  <tr>
                    <th>PRODUK</th>
                    <th>KATEGORI</th>
                    <th>STOK SAAT INI</th>
                    <th>BATAS MAKS. STOK</th>
                    <th>KONDISI</th>
                    <th>PERGERAKAN TERAKHIR</th>
                  </tr>
                </thead>
                <tbody>
                  {visibleStock.map((product) => {
                    const last = sortedMovements.find(
                      (item) => item.sku === product.sku,
                    );
                    const status = stockStatus(product.stock);
                    const maxVal = getMax(product);
                    const over = isOverMaxStock(product.stock, maxVal);
                    const fillPct = Math.min(
                      100,
                      Math.round((product.stock / Math.max(1, maxVal)) * 100),
                    );
                    return (
                      <tr
                        key={product.id}
                        className={
                          stockRows.includes(product)
                            ? undefined
                            : "inventory-print-row"
                        }
                      >
                        <td>
                          <div className="inventory-product-cell">
                            <span>
                              <strong>{product.name}</strong>
                              {product.is_active === false && (
                                <small>Nonaktif</small>
                              )}
                              <small>
                                {product.sku}
                                {product.barcode ? ` · ${product.barcode}` : ""}
                              </small>
                            </span>
                          </div>
                        </td>
                        <td>
                          <span className="inventory-category">
                            {product.category || "Tanpa kategori"}
                          </span>
                        </td>
                        <td>
                          <span className={`inventory-quantity ${status}`}>
                            <strong>{product.stock}</strong>
                            <small>{product.unit || "unit"}</small>
                          </span>
                        </td>
                        <td>
                          <div className="inventory-max-cell">
                            <span>
                              <strong>
                                Maks. {maxVal} {product.unit || "unit"}
                              </strong>
                              <small>{fillPct}% kapasitas</small>
                            </span>
                            <button
                              type="button"
                              className="inventory-max-btn"
                              onClick={() => {
                                setEditingMaxProduct(product);
                                maxDialogRef.current?.showModal();
                              }}
                            >
                              Atur maks.
                            </button>
                          </div>
                        </td>
                        <td>
                          <span
                            className={`inventory-stock-badge ${over ? "over" : status}`}
                          >
                            <i />
                            {over
                              ? "Lewat batas maks."
                              : status === "out"
                                ? "Stok habis"
                                : status === "low"
                                  ? "Menipis"
                                  : "Tersedia"}
                          </span>
                        </td>
                        <td>
                          {last ? (
                            <span className="inventory-last">
                              <i className={last.direction}>
                                {last.direction === "in" ? (
                                  <ArrowDownLeft size={13} />
                                ) : (
                                  <ArrowUpRight size={13} />
                                )}
                              </i>
                              <span>
                                <strong>{last.source}</strong>
                                <small>
                                  {formatMovementDate(last.date)} · {last.time}
                                </small>
                              </span>
                            </span>
                          ) : (
                            <span className="inventory-no-movement">
                              Belum ada pergerakan contoh
                            </span>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            <div className="inventory-pagination">
              <span>
                Menampilkan{" "}
                <strong>
                  {(currentProductPage - 1) * pageSize + 1}-
                  {Math.min(currentProductPage * pageSize, visibleStock.length)}
                </strong>{" "}
                dari <strong>{visibleStock.length}</strong> produk
              </span>
              <div>
                <button
                  aria-label="Halaman produk sebelumnya"
                  disabled={currentProductPage <= 1}
                  onClick={() => setProductPage(currentProductPage - 1)}
                >
                  <ChevronLeft size={16} />
                </button>
                <span>
                  Halaman <strong>{currentProductPage}</strong> dari{" "}
                  {productPageCount}
                </span>
                <button
                  aria-label="Halaman produk berikutnya"
                  disabled={currentProductPage >= productPageCount}
                  onClick={() => setProductPage(currentProductPage + 1)}
                >
                  <ChevronRight size={16} />
                </button>
              </div>
            </div>
          </>
        )}
        <p className="inventory-threshold-note">
          Batas minimum (Stok menipis): 1–{lowStockThreshold} unit · Klik tombol{" "}
          <strong>Atur maks.</strong> pada baris produk untuk mengubah batas
          maksimum penyimpanan per SKU.
        </p>
      </section>

      {/* Dialog Atur Batas Maksimum Stok */}
      <dialog
        ref={maxDialogRef}
        className="inventory-dialog"
        aria-labelledby="max-stock-dialog-title"
      >
        {editingMaxProduct && (
          <form key={editingMaxProduct.id} onSubmit={saveMaxStock}>
            <header>
              <div>
                <p>KAPASITAS GUDANG</p>
                <h2 id="max-stock-dialog-title">Atur batas maksimum stok</h2>
              </div>
              <button
                type="button"
                aria-label="Tutup dialog"
                onClick={() => maxDialogRef.current?.close()}
              >
                <X size={18} />
              </button>
            </header>
            <p className="inventory-dialog-note">
              Produk: <strong>{editingMaxProduct.name}</strong> (
              {editingMaxProduct.sku}) · Stok saat ini:{" "}
              <strong>
                {editingMaxProduct.stock} {editingMaxProduct.unit || "PCS"}
              </strong>
            </p>
            <label className="inventory-dialog-field">
              Batas maksimum stok ({editingMaxProduct.unit || "PCS"})
              <input
                name="max_stock"
                type="number"
                min={lowStockThreshold + 1}
                max={100000}
                required
                defaultValue={getMax(editingMaxProduct)}
              />
            </label>
            <footer>
              <button
                type="button"
                className="inventory-print"
                onClick={() => maxDialogRef.current?.close()}
              >
                Batal
              </button>
              <button type="submit" className="inventory-primary-btn">
                Simpan batas maks.
              </button>
            </footer>
          </form>
        )}
      </dialog>

      <p className="inventory-data-note">
        Data persediaan belum tersimpan atau diperbarui di database.
      </p>
    </div>
  );
}
