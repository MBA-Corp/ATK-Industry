import { useMemo, useState } from "react";
import {
  ArrowDownToLine,
  ArrowUpDown,
  Barcode,
  Boxes,
  ChevronLeft,
  ChevronRight,
  Package,
  Search,
  SlidersHorizontal,
  X,
} from "lucide-react";
import { EmptyState } from "~/components/empty-state";
import { ErrorState } from "~/components/error-state";
import { LoadingScreen } from "~/components/loading-screen";
import { SelectMenu } from "~/components/select-menu";
import { useProductsQuery } from "~/features/products/product.queries";
import {
  lowStockThreshold,
  stockStatus,
} from "~/features/products/product-stock";
import type { Product } from "~/features/products/product.types";
import "~/features/products/product.css";

type StatusFilter = "all" | "active" | "low" | "out" | "inactive";
type SortOption =
  "name-asc" | "name-desc" | "stock-asc" | "price-asc" | "price-desc";
const pageSize = 8;
const noProducts: Product[] = [];
const currency = new Intl.NumberFormat("id-ID", {
  style: "currency",
  currency: "IDR",
  maximumFractionDigits: 0,
});

export function meta() {
  return [
    { title: "Produk | ATK Industry" },
    {
      name: "description",
      content: "Katalog produk dan informasi persediaan toko ATK.",
    },
  ];
}

export default function Products() {
  const [search, setSearch] = useState("");
  const [category, setCategory] = useState("all");
  const [status, setStatus] = useState<StatusFilter>("all");
  const [sort, setSort] = useState<SortOption>("name-asc");
  const [page, setPage] = useState(1);
  const query = useProductsQuery();
  const products = query.data ?? noProducts;
  const categories = useMemo(
    () =>
      [
        ...new Set(
          products
            .map((product) => product.category)
            .filter((value): value is string => Boolean(value)),
        ),
      ].sort((a, b) => a.localeCompare(b, "id-ID")),
    [products],
  );
  const filtered = useMemo(() => {
    const term = search.trim().toLocaleLowerCase("id-ID");
    const found = products.filter((product) => {
      const matchesSearch =
        !term ||
        [
          product.sku,
          product.name,
          product.barcode ?? "",
          product.category ?? "",
        ].some((value) => value.toLocaleLowerCase("id-ID").includes(term));
      const matchesCategory =
        category === "all" || product.category === category;
      const active = product.is_active !== false;
      const matchesStatus =
        status === "all" ||
        (status === "active" && active) ||
        (status === "inactive" && !active) ||
        (status === "out" && active && stockStatus(product.stock) === "out") ||
        (status === "low" && active && stockStatus(product.stock) === "low");
      return matchesSearch && matchesCategory && matchesStatus;
    });
    return found.sort((a, b) => {
      switch (sort) {
        case "name-desc":
          return b.name.localeCompare(a.name, "id-ID");
        case "stock-asc":
          return a.stock - b.stock;
        case "price-asc":
          return a.selling_price - b.selling_price;
        case "price-desc":
          return b.selling_price - a.selling_price;
        default:
          return a.name.localeCompare(b.name, "id-ID");
      }
    });
  }, [products, search, category, status, sort]);
  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
  const currentPage = Math.min(page, totalPages);
  const displayedProducts = filtered.slice(
    (currentPage - 1) * pageSize,
    currentPage * pageSize,
  );
  const activeCount = products.filter(
    (product) => product.is_active !== false,
  ).length;
  const lowStockCount = products.filter(
    (product) =>
      stockStatus(product.stock) === "low" && product.is_active !== false,
  ).length;
  const empty = !query.isPending && !query.isError && filtered.length === 0;

  return (
    <div className="products-page">
      <section
        className="plain-print-list"
        aria-label="Daftar produk untuk cetak"
      >
        <header>
          <p>ATK INDUSTRY</p>
          <h1>Daftar Produk</h1>
          <p>{filtered.length} produk sesuai filter</p>
        </header>
        <p>
          Kategori: {category === "all" ? "Semua" : category} · Status:{" "}
          {
            {
              all: "Semua",
              active: "Aktif",
              inactive: "Nonaktif",
              low: "Stok menipis",
              out: "Stok habis",
            }[status]
          }{" "}
          · Pencarian: {search || "—"}
        </p>
        <table>
          <thead>
            <tr>
              <th>No.</th>
              <th>SKU / Barcode</th>
              <th>Nama produk</th>
              <th>Kategori</th>
              <th>Stok</th>
              <th>Harga jual</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((product, index) => (
              <tr key={product.id}>
                <td>{index + 1}</td>
                <td>
                  {product.sku}
                  <br />
                  {product.barcode || "—"}
                </td>
                <td>
                  {product.name}
                  {product.size ? ` · ${product.size}` : ""}
                  {product.color ? ` · ${product.color}` : ""}
                </td>
                <td>{product.category || "—"}</td>
                <td className="print-number">
                  {product.stock} {product.unit || "unit"}
                </td>
                <td className="print-number">
                  {currency.format(product.selling_price)}
                </td>
                <td>{product.is_active === false ? "Nonaktif" : "Aktif"}</td>
              </tr>
            ))}
            {!filtered.length && (
              <tr>
                <td colSpan={7}>Tidak ada produk sesuai filter.</td>
              </tr>
            )}
          </tbody>
        </table>
      </section>
      <section className="products-heading">
        <div>
          <p className="products-eyebrow">KATALOG & PERSEDIAAN</p>
          <h1>
            Produk <span>.</span>
          </h1>
          <p>Kelola katalog produk dan pantau ketersediaan barang di toko.</p>
        </div>
        <div className="products-heading-actions">
          <span className="product-data-badge">
            <span />
            Data contoh
          </span>
          <button
            type="button"
            className="products-secondary-action"
            disabled={query.isPending || query.isError || filtered.length === 0}
            onClick={() => window.print()}
          >
            <ArrowDownToLine size={16} />
            Cetak daftar
          </button>
        </div>
      </section>
      <section className="product-summary" aria-label="Ringkasan produk">
        <article>
          <span className="product-summary-icon blue">
            <Package size={19} />
          </span>
          <div>
            <span>Seluruh produk</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : products.length}
            </strong>
            <small>item di katalog</small>
          </div>
        </article>
        <article>
          <span className="product-summary-icon green">
            <Boxes size={19} />
          </span>
          <div>
            <span>Produk aktif</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : activeCount}
            </strong>
            <small>tersedia di katalog</small>
          </div>
        </article>
        <article>
          <span className="product-summary-icon orange">
            <ArrowDownToLine size={19} />
          </span>
          <div>
            <span>Stok menipis</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : lowStockCount}
            </strong>
            <small>produk aktif · ≤ {lowStockThreshold} unit</small>
          </div>
        </article>
        <article>
          <span className="product-summary-icon rose">
            <Barcode size={19} />
          </span>
          <div>
            <span>Barcode terdaftar</span>
            <strong>
              {query.isPending || query.isError
                ? "\u2014"
                : products.filter((product) => product.barcode).length}
            </strong>
            <small>siap untuk pencarian</small>
          </div>
        </article>
      </section>
      <section className="product-catalog dashboard-panel" id="product-catalog">
        <div className="catalog-heading">
          <div>
            <h2>Daftar produk</h2>
            <p>Temukan item berdasarkan nama, kode, kategori, atau barcode.</p>
          </div>
          <span className="catalog-total">{filtered.length} produk</span>
        </div>
        <div className="product-toolbar">
          <label className="product-search workspace-search">
            <Search size={17} />
            <span className="sr-only">Cari produk</span>
            <input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
              }}
              placeholder="Cari nama, SKU, atau barcode..."
            />
            <kbd>⌕</kbd>
          </label>
          <SelectMenu
            className="product-select"
            ariaLabel="Filter kategori"
            icon={<SlidersHorizontal size={15} />}
            value={category}
            options={[
              { value: "all", label: "Semua kategori" },
              ...categories.map((item) => ({ value: item, label: item })),
            ]}
            onChange={(value) => {
              setCategory(value);
              setPage(1);
            }}
          />
          <SelectMenu
            className="product-select"
            ariaLabel="Urutkan produk"
            icon={<ArrowUpDown size={15} />}
            value={sort}
            options={[
              { value: "name-asc", label: "Nama A–Z" },
              { value: "name-desc", label: "Nama Z–A" },
              { value: "stock-asc", label: "Stok terendah" },
              { value: "price-asc", label: "Harga termurah" },
              { value: "price-desc", label: "Harga termahal" },
            ]}
            onChange={(value) => {
              setSort(value as SortOption);
              setPage(1);
            }}
          />
        </div>
        <div
          className="product-filter-tabs"
          role="group"
          aria-label="Filter status produk"
        >
          {(
            [
              { value: "all", label: "Semua" },
              { value: "active", label: "Aktif" },
              { value: "low", label: "Stok menipis" },
              { value: "out", label: "Stok habis" },
              { value: "inactive", label: "Nonaktif" },
            ] as const
          ).map((item) => (
            <button
              key={item.value}
              type="button"
              aria-pressed={status === item.value}
              className={status === item.value ? "selected" : ""}
              onClick={() => {
                setStatus(item.value);
                setPage(1);
              }}
            >
              {item.label}
            </button>
          ))}
        </div>
        {query.isPending && (
          <div className="product-loading">
            <LoadingScreen />
          </div>
        )}
        {query.isError && (
          <ErrorState
            title="Produk belum dapat dimuat"
            description="Silakan periksa koneksi dan coba kembali."
            onRetry={() => void query.refetch()}
            isRetrying={query.isFetching}
          />
        )}
        {empty && (
          <EmptyState
            title="Produk tidak ditemukan"
            description="Coba kata kunci, kategori, atau status yang berbeda."
            action={
              <button
                className="product-reset"
                onClick={() => {
                  setSearch("");
                  setCategory("all");
                  setStatus("all");
                  setPage(1);
                }}
              >
                Hapus filter <X size={14} />
              </button>
            }
          />
        )}
        {!query.isPending && !query.isError && displayedProducts.length > 0 && (
          <>
            <div className="product-table-scroll">
              <table className="products-table">
                <thead>
                  <tr>
                    <th>PRODUK</th>
                    <th>KATEGORI</th>
                    <th>BARCODE</th>
                    <th>STOK</th>
                    <th>HARGA JUAL</th>
                    <th>STATUS</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((product) => {
                    const active = product.is_active !== false;
                    const out = stockStatus(product.stock) === "out";
                    const low = stockStatus(product.stock) === "low";
                    return (
                      <tr
                        key={product.id}
                        className={
                          displayedProducts.includes(product)
                            ? undefined
                            : "product-print-row"
                        }
                      >
                        <td>
                          <div className="product-name-cell">
                            <span
                              className={`product-thumb tone-${product.id % 5}`}
                            >
                              <Package size={19} />
                            </span>
                            <span>
                              <strong>{product.name}</strong>
                              <small>
                                {product.sku}
                                {product.size ? ` · ${product.size}` : ""}
                                {product.color ? ` · ${product.color}` : ""}
                              </small>
                            </span>
                          </div>
                        </td>
                        <td>
                          <span className="product-category-pill">
                            {product.category || "Tanpa kategori"}
                          </span>
                        </td>
                        <td>
                          <span className="product-barcode-cell">
                            <Barcode size={15} />
                            {product.barcode || "—"}
                          </span>
                        </td>
                        <td>
                          <span
                            className={`product-stock ${out ? "out" : low ? "low" : ""}`}
                          >
                            <strong>{product.stock}</strong>
                            <small>{product.unit || "unit"}</small>
                          </span>
                        </td>
                        <td className="product-price">
                          {currency.format(product.selling_price)}
                        </td>
                        <td>
                          <span
                            className={`product-status ${active ? "active" : "inactive"}`}
                          >
                            <i />
                            {active ? "Aktif" : "Nonaktif"}
                          </span>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            <div className="product-pagination">
              <span>
                Menampilkan{" "}
                <strong>
                  {(currentPage - 1) * pageSize + 1}–
                  {Math.min(currentPage * pageSize, filtered.length)}
                </strong>{" "}
                dari <strong>{filtered.length}</strong> produk
              </span>
              <div>
                <button
                  aria-label="Halaman sebelumnya"
                  disabled={currentPage <= 1}
                  onClick={() => setPage(currentPage - 1)}
                >
                  <ChevronLeft size={17} />
                </button>
                <span>
                  Halaman <strong>{currentPage}</strong> dari {totalPages}
                </span>
                <button
                  aria-label="Halaman berikutnya"
                  disabled={currentPage >= totalPages}
                  onClick={() => setPage(currentPage + 1)}
                >
                  <ChevronRight size={17} />
                </button>
              </div>
            </div>
          </>
        )}
        <p className="product-threshold-note">
          Stok menipis: produk aktif dengan stok 1–{lowStockThreshold} unit.
          Filter stok habis menampilkan produk aktif tanpa stok tersedia.
        </p>
      </section>
      <p className="products-data-note">
        Data produk ini bersifat ilustrasi dan belum tersimpan di database.
      </p>
    </div>
  );
}
