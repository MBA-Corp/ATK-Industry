import { useMemo, useState } from "react";
import {
  ArrowDownLeft,
  ArrowDownToLine,
  ArrowUpDown,
  ArrowUpRight,
  Boxes,
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  CircleAlert,
  Package,
  Search,
  SlidersHorizontal,
  Warehouse,
} from "lucide-react";
import { EmptyState } from "~/components/empty-state";
import { ErrorState } from "~/components/error-state";
import { SelectMenu } from "~/components/select-menu";
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
  lowStockThreshold,
  stockStatus,
} from "~/features/products/product-stock";
import type { Product } from "~/features/products/product.types";
import "~/features/inventory/inventory.css";

type StockFilter = "all" | "low" | "out" | "healthy";
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
      content: "Pantau jumlah stok dan riwayat pergerakan persediaan.",
    },
  ];
}

export default function Inventory() {
  const [period, setPeriod] = useState<InventoryPeriod>("week");
  const [filter, setFilter] = useState<StockFilter>("all");
  const [direction, setDirection] = useState<"all" | InventoryDirection>("all");
  const [category, setCategory] = useState("all");
  const [sort, setSort] = useState("stock-asc");
  const [productSearch, setProductSearch] = useState("");
  const [movementSearch, setMovementSearch] = useState("");
  const [productPage, setProductPage] = useState(1);
  const [movementPage, setMovementPage] = useState(1);
  const query = useProductsQuery();
  const products = query.data ?? noProducts;
  const activeProducts = products.filter((item) => item.is_active !== false);
  const lowCount = activeProducts.filter(
    (item) => stockStatus(item.stock) === "low",
  ).length;
  const outCount = activeProducts.filter(
    (item) => stockStatus(item.stock) === "out",
  ).length;
  const sortedMovements = useMemo(
    () =>
      [...inventoryStockMovements].sort((a, b) =>
        `${b.date}T${b.time}`.localeCompare(`${a.date}T${a.time}`),
      ),
    [],
  );
  const movements = useMemo(
    () => sortedMovements.filter((item) => isMovementInPeriod(item, period)),
    [sortedMovements, period],
  );
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
        const matchesFilter =
          filter === "all" ||
          (item.is_active !== false && stockStatus(item.stock) === filter);
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
  }, [products, productSearch, filter, category, sort]);
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
  const periodLabel = inventoryPeriodLabels[period];

  return (
    <div className="inventory-page">
      <section
        className="inventory-print-list plain-print-list"
        aria-label="Daftar persediaan untuk cetak"
      >
        <header>
          <p>ATK INDUSTRY</p>
          <h1>Daftar Persediaan</h1>
          <p>Data contoh • {visibleStock.length} produk sesuai filter</p>
        </header>
        <p className="inventory-print-meta">
          Kategori: {category === "all" ? "Semua kategori" : category} ·
          Kondisi:{" "}
          {
            { all: "Semua", low: "Menipis", out: "Habis", healthy: "Tersedia" }[
              filter
            ]
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
                <td colSpan={7}>Tidak ada produk sesuai filter.</td>
              </tr>
            )}
          </tbody>
        </table>
        <p className="inventory-print-meta">
          Batas stok menipis: maksimal {lowStockThreshold} unit. Daftar mencakup
          seluruh hasil filter, bukan hanya halaman yang terlihat.
        </p>
        <h2>Riwayat pergerakan stok</h2>
        <p className="inventory-print-meta">
          {periodLabel} · {visibleMovements.length} aktivitas · Arah:{" "}
          {direction === "all"
            ? "Semua"
            : direction === "in"
              ? "Masuk"
              : "Keluar"}{" "}
          · Pencarian: {movementSearch || "—"}
        </p>
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
            {!visibleMovements.length && (
              <tr>
                <td colSpan={6}>Tidak ada aktivitas sesuai filter.</td>
              </tr>
            )}
          </tbody>
        </table>
      </section>
      <section className="inventory-heading">
        <div>
          <p className="inventory-eyebrow">KONTROL PERSEDIAAN</p>
          <h1>
            Persediaan<span>.</span>
          </h1>
          <p>Pantau ketersediaan barang dan perubahan stok toko Anda.</p>
        </div>
        <div className="inventory-actions">
          <span className="inventory-demo-badge">
            <i />
            Data contoh
          </span>
          <SelectMenu
            className="inventory-period"
            ariaLabel="Periode riwayat stok"
            icon={<CalendarDays size={16} />}
            value={period}
            options={[
              { value: "week", label: inventoryPeriodLabels.week },
              { value: "month", label: inventoryPeriodLabels.month },
            ]}
            onChange={(value) => {
              setPeriod(value);
              setMovementPage(1);
            }}
          />
          <button
            className="inventory-print"
            type="button"
            disabled={query.isPending || query.isError}
            onClick={() => window.print()}
          >
            <ArrowDownToLine size={16} />
            Cetak daftar
          </button>
        </div>
      </section>
      <section className="inventory-summary" aria-label="Ringkasan persediaan">
        <article>
          <span className="inventory-summary-icon blue">
            <Package size={19} />
          </span>
          <div>
            <span>Produk terdaftar</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : products.length}
            </strong>
            <small>SKU dalam katalog</small>
          </div>
        </article>
        <article>
          <span className="inventory-summary-icon orange">
            <CircleAlert size={19} />
          </span>
          <div>
            <span>Stok menipis</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : lowCount}
            </strong>
            <small>produk aktif, maksimal {lowStockThreshold} unit</small>
          </div>
        </article>
        <article>
          <span className="inventory-summary-icon rose">
            <Boxes size={19} />
          </span>
          <div>
            <span>Stok habis</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : outCount}
            </strong>
            <small>produk aktif tanpa stok</small>
          </div>
        </article>
        <article>
          <span className="inventory-summary-icon green">
            <ArrowUpDown size={19} />
          </span>
          <div>
            <span>Pergerakan stok</span>
            <strong>{movements.length}</strong>
            <small>aktivitas contoh · {periodLabel}</small>
          </div>
        </article>
      </section>
      <section
        className="inventory-panel dashboard-panel"
        id="inventory-low-stock"
      >
        <div className="inventory-panel-heading">
          <div>
            <h2>Kondisi stok</h2>
            <p>Pantau jumlah stok untuk setiap produk di katalog.</p>
          </div>
          <span className="inventory-count">{visibleStock.length} produk</span>
        </div>
        <div className="inventory-toolbar">
          <label className="inventory-search workspace-search">
            <Search size={16} />
            <span className="sr-only">Cari produk</span>
            <input
              value={productSearch}
              onChange={(event) => {
                setProductSearch(event.target.value);
                setProductPage(1);
              }}
              placeholder="Cari nama, SKU, atau barcode..."
            />
          </label>
          <SelectMenu
            className="inventory-select"
            ariaLabel="Kategori"
            icon={<SlidersHorizontal size={15} />}
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
            icon={<ArrowUpDown size={15} />}
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
              { value: "all", label: "Semua stok" },
              { value: "low", label: "Menipis" },
              { value: "out", label: "Habis" },
              { value: "healthy", label: "Tersedia" },
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
            </button>
          ))}
        </div>
        {query.isPending && (
          <p className="inventory-loading">Memuat data persediaan...</p>
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
            <div className="inventory-table-wrap">
              <table className="inventory-table">
                <thead>
                  <tr>
                    <th>PRODUK</th>
                    <th>KATEGORI</th>
                    <th>STOK SAAT INI</th>
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
                            <span
                              className={`inventory-product-icon tone-${product.id % 5}`}
                            >
                              <Package size={18} />
                            </span>
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
                          <span className={`inventory-stock-badge ${status}`}>
                            <i />
                            {status === "out"
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
                                  <ArrowDownLeft size={14} />
                                ) : (
                                  <ArrowUpRight size={14} />
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
                  <ChevronLeft size={17} />
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
                  <ChevronRight size={17} />
                </button>
              </div>
            </div>
          </>
        )}
        <p className="inventory-threshold-note">
          Stok menipis: produk aktif dengan stok 1 sampai {lowStockThreshold}{" "}
          unit. Filter kondisi stok hanya menampilkan produk aktif; semua stok
          juga mencakup produk nonaktif.
        </p>
      </section>
      <section className="inventory-panel dashboard-panel inventory-history">
        <div className="inventory-panel-heading">
          <div>
            <h2>
              <Warehouse size={18} />
              Riwayat pergerakan stok
            </h2>
            <p>
              Pembelian, penjualan, opname, adjustment, retur, barang rusak, dan
              barang hilang.
            </p>
          </div>
          <span className="inventory-count">
            {visibleMovements.length} aktivitas
          </span>
        </div>
        <div className="inventory-history-toolbar">
          <label className="inventory-search workspace-search">
            <Search size={16} />
            <span className="sr-only">Cari riwayat pergerakan</span>
            <input
              value={movementSearch}
              onChange={(event) => {
                setMovementSearch(event.target.value);
                setMovementPage(1);
              }}
              placeholder="Cari produk, sumber, atau referensi..."
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
            <div className="inventory-table-wrap">
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
                        <span className={`movement-quantity ${item.direction}`}>
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
                  <ChevronLeft size={17} />
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
                  <ChevronRight size={17} />
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
        Data persediaan belum tersimpan atau diperbarui di database.
      </p>
    </div>
  );
}
