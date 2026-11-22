import { useMemo, useState } from "react";
import {
  ArrowDownToLine,
  ArrowUpDown,
  Barcode,
  Boxes,
  ChevronLeft,
  ChevronRight,
  Package,
  Printer,
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
  isOverMaxStock,
  lowStockThreshold,
  productMaxStock,
  stockStatus,
} from "~/features/products/product-stock";
import type { Product } from "~/features/products/product.types";
import "~/features/products/product.css";

type StatusFilter = "all" | "active" | "low" | "out" | "over" | "inactive";
type SortOption =
  | "name-asc"
  | "name-desc"
  | "stock-asc"
  | "price-asc"
  | "price-desc";
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
      const maxStock = productMaxStock(product);
      const matchesStatus =
        status === "all" ||
        (status === "active" && active) ||
        (status === "inactive" && !active) ||
        (status === "out" && active && stockStatus(product.stock) === "out") ||
        (status === "low" && active && stockStatus(product.stock) === "low") ||
        (status === "over" && active && isOverMaxStock(product.stock, maxStock));
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
  const outStockCount = products.filter(
    (product) =>
      stockStatus(product.stock) === "out" && product.is_active !== false,
  ).length;
  const overStockCount = products.filter(
    (product) =>
      product.is_active !== false &&
      isOverMaxStock(product.stock, productMaxStock(product)),
  ).length;
  const inactiveCount = products.filter(
    (product) => product.is_active === false,
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
              over: "Melebihi batas maks.",
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
              <th>Stok / Batas Maks.</th>
              <th>Harga modal</th>
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
                  {product.stock} / {productMaxStock(product)}{" "}
                  {product.unit || "unit"}
                </td>
                <td className="print-number">
                  {currency.format(product.cost_price)}
                </td>
                <td className="print-number">
                  {currency.format(product.selling_price)}
                </td>
                <td>{product.is_active === false ? "Nonaktif" : "Aktif"}</td>
              </tr>
            ))}
            {!filtered.length && (
              <tr>
                <td colSpan={8}>Tidak ada produk sesuai filter.</td>
              </tr>
            )}
          </tbody>
        </table>
      </section>

      <section className="products-heading">
        <div>
          <p className="products-eyebrow">MANAJEMEN KATALOG</p>
          <h1>Katalog produk</h1>
          <p>
            Pantau daftar SKU, barcode, batas maksimum stok, harga modal, dan harga jual.
          </p>
        </div>
        <div className="products-heading-actions">
          <button
            type="button"
            className="products-secondary-action"
            disabled={query.isPending || query.isError || filtered.length === 0}
            onClick={() => window.print()}
          >
            <Printer size={15} aria-hidden="true" />
            Cetak daftar
          </button>
        </div>
      </section>

      <section className="product-summary" aria-label="Ringkasan produk">
        <article>
          <span className="product-summary-icon blue">
            <Package size={18} />
          </span>
          <div>
            <span>Seluruh produk</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : products.length}
            </strong>
            <small>{categories.length} kategori produk</small>
          </div>
        </article>
        <article>
          <span className="product-summary-icon green">
            <Boxes size={18} />
          </span>
          <div>
            <span>Produk aktif</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : activeCount}
            </strong>
            <small>Siap dijual di kasir</small>
          </div>
        </article>
        <article>
          <span className="product-summary-icon orange">
            <ArrowDownToLine size={18} />
          </span>
          <div>
            <span>Stok menipis</span>
            <strong>
              {query.isPending || query.isError ? "\u2014" : lowStockCount}
            </strong>
            <small>Min. 1–{lowStockThreshold} unit · {overStockCount} lewat maks.</small>
          </div>
        </article>
        <article>
          <span className="product-summary-icon rose">
            <Barcode size={18} />
          </span>
          <div>
            <span>Barcode terdaftar</span>
            <strong>
              {query.isPending || query.isError
                ? "\u2014"
                : products.filter((product) => product.barcode).length}
            </strong>
            <small>Siap pindai scanner POS</small>
          </div>
        </article>
      </section>

      <section className="product-catalog dashboard-panel" id="product-catalog">
        <div className="catalog-heading">
          <div>
            <h2>Daftar produk</h2>
            <p>
              Periksa stok aktual dibandingkan batas kapasitas maksimum per SKU.
            </p>
          </div>
          <span className="catalog-total" role="status">
            {query.isSuccess && `${filtered.length} produk`}
          </span>
        </div>
        <div className="product-toolbar">
          <label className="product-search workspace-search">
            <Search size={15} />
            <span className="sr-only">Cari produk</span>
            <input
              value={search}
              onChange={(event) => {
                setSearch(event.target.value);
                setPage(1);
              }}
              placeholder="Cari nama, SKU, atau barcode…"
            />
          </label>
          <SelectMenu
            className="product-select"
            ariaLabel="Filter kategori"
            icon={<SlidersHorizontal size={14} />}
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
            icon={<ArrowUpDown size={14} />}
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
              { value: "all", label: "Semua", count: products.length },
              { value: "active", label: "Aktif", count: activeCount },
              { value: "low", label: "Stok menipis", count: lowStockCount },
              { value: "out", label: "Stok habis", count: outStockCount },
              { value: "over", label: "Lewat batas maks.", count: overStockCount },
              { value: "inactive", label: "Nonaktif", count: inactiveCount },
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
              {query.isSuccess && (
                <span className="tab-count">{item.count}</span>
              )}
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
            <div
              className="product-table-scroll"
              role="region"
              aria-label="Tabel katalog produk"
              tabIndex={0}
            >
              <table className="products-table">
                <thead>
                  <tr>
                    <th>PRODUK</th>
                    <th>KATEGORI</th>
                    <th>BARCODE</th>
                    <th>STOK / BATAS MAKS.</th>
                    <th className="product-price">HARGA MODAL</th>
                    <th className="product-price">HARGA JUAL</th>
                    <th>STATUS JUAL</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((product) => {
                    const active = product.is_active !== false;
                    const out = stockStatus(product.stock) === "out";
                    const low = stockStatus(product.stock) === "low";
                    const maxStock = productMaxStock(product);
                    const over = isOverMaxStock(product.stock, maxStock);
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
                            <Barcode size={14} />
                            {product.barcode || "—"}
                          </span>
                        </td>
                        <td>
                          <div className="product-stock-wrap">
                            <span
                              className={`product-stock ${out ? "out" : low ? "low" : ""}`}
                            >
                              <strong>{product.stock}</strong>
                              <small>
                                / {maxStock} {product.unit || "unit"}
                              </small>
                            </span>
                            {(out || low || over) && (
                              <span
                                className={`product-stock-chip ${out ? "out" : low ? "low" : "over"}`}
                              >
                                {out
                                  ? "Habis"
                                  : low
                                    ? "Menipis"
                                    : "Melebihi maks."}
                              </span>
                            )}
                          </div>
                        </td>
                        <td className="product-price product-cost">
                          {currency.format(product.cost_price)}
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
                  <ChevronLeft size={16} />
                </button>
                <span>
                  Halaman <strong>{currentPage}</strong> dari {totalPages}
                </span>
                <button
                  aria-label="Halaman berikutnya"
                  disabled={currentPage >= totalPages}
                  onClick={() => setPage(currentPage + 1)}
                >
                  <ChevronRight size={16} />
                </button>
              </div>
            </div>
          </>
        )}
        <p className="product-threshold-note">
          Stok menipis: 1–{lowStockThreshold} unit · Batas maksimum stok mencegah
          kelebihan penumpukan barang di gudang (dapat disesuaikan di menu Persediaan).
        </p>
      </section>
      <p className="products-data-note">
        Data produk ini bersifat ilustrasi dan belum tersimpan di database.
      </p>
    </div>
  );
}
