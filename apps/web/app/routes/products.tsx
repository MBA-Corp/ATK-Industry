import { useMemo, useState } from "react";

import { EmptyState } from "~/components/empty-state";
import { ErrorState } from "~/components/error-state";
import { PaginationControls } from "~/components/pagination-controls";
import { SearchInput } from "~/components/search-input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "~/components/ui/select";
import { ProductTable } from "~/features/products/product-table";
import { useProductsQuery } from "~/hooks/use-products-query";

type SortOption = "name-asc" | "name-desc" | "sku-asc" | "sku-desc";

const PAGE_SIZE = 2;

export function meta() {
  return [
    { title: "Products | ATK Industry" },
    {
      name: "description",
      content: "Product management",
    },
  ];
}

export default function Products() {
  const [searchTerm, setSearchTerm] = useState("");
  const [sortOption, setSortOption] = useState<SortOption>("name-asc");
  const [currentPage, setCurrentPage] = useState(1);

  const productsQuery = useProductsQuery();

  const visibleProducts = useMemo(() => {
    if (!productsQuery.data) {
      return [];
    }

    const normalizedSearch = searchTerm.trim().toLowerCase();

    const filteredProducts = normalizedSearch
      ? productsQuery.data.filter((product) => {
          const sku = product.sku.toLowerCase();
          const name = product.name.toLowerCase();

          return (
            sku.includes(normalizedSearch) || name.includes(normalizedSearch)
          );
        })
      : productsQuery.data;

    return [...filteredProducts].sort((first, second) => {
      switch (sortOption) {
        case "name-asc":
          return first.name.localeCompare(second.name, "id-ID");

        case "name-desc":
          return second.name.localeCompare(first.name, "id-ID");

        case "sku-asc":
          return first.sku.localeCompare(second.sku);

        case "sku-desc":
          return second.sku.localeCompare(first.sku);
      }
    });
  }, [productsQuery.data, searchTerm, sortOption]);

  const totalPages = Math.max(1, Math.ceil(visibleProducts.length / PAGE_SIZE));

  const safeCurrentPage = Math.min(currentPage, totalPages);

  const paginatedProducts = useMemo(() => {
    const startIndex = (safeCurrentPage - 1) * PAGE_SIZE;
    const endIndex = startIndex + PAGE_SIZE;

    return visibleProducts.slice(startIndex, endIndex);
  }, [safeCurrentPage, visibleProducts]);

  return (
    <section className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Products</h1>
        <p className="mt-2 text-muted-foreground">
          Product management foundation.
        </p>
      </div>

      <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
        <div className="w-full max-w-md space-y-2">
          <label
            htmlFor="product-search"
            className="text-sm font-medium text-foreground"
          >
            Search products
          </label>

          <SearchInput
            id="product-search"
            placeholder="Search by code or product name..."
            value={searchTerm}
            onChange={(event) => {
              setSearchTerm(event.target.value);
              setCurrentPage(1);
            }}
          />
        </div>

        <div className="space-y-2">
          <label
            htmlFor="product-sort"
            className="text-sm font-medium text-foreground"
          >
            Sort by
          </label>

          <Select
            value={sortOption}
            onValueChange={(value) => {
              if (value) {
                setSortOption(value as SortOption);
                setCurrentPage(1);
              }
            }}
          >
            <SelectTrigger id="product-sort" className="w-44">
              <SelectValue />
            </SelectTrigger>

            <SelectContent>
              <SelectItem value="name-asc">Nama A-Z</SelectItem>
              <SelectItem value="name-desc">Nama Z-A</SelectItem>
              <SelectItem value="sku-asc">Kode A-Z</SelectItem>
              <SelectItem value="sku-desc">Kode Z-A</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {productsQuery.isPending && (
        <p className="text-sm text-muted-foreground">Loading products...</p>
      )}

      {productsQuery.isError && (
        <ErrorState
          title="Unable to load products"
          description="Product data could not be loaded. Please try again."
          onRetry={() => void productsQuery.refetch()}
          isRetrying={productsQuery.isFetching}
        />
      )}

      {productsQuery.isSuccess && productsQuery.data.length === 0 && (
        <EmptyState
          title="No products"
          description="There are no products to display yet."
        />
      )}

      {productsQuery.isSuccess &&
        productsQuery.data.length > 0 &&
        visibleProducts.length === 0 && (
          <EmptyState
            title="No matching products"
            description="Try using a different product code or name."
          />
        )}

      {productsQuery.isSuccess && visibleProducts.length > 0 && (
        <div className="space-y-4">
          <ProductTable products={paginatedProducts} />

          <PaginationControls
            currentPage={safeCurrentPage}
            totalPages={totalPages}
            onPageChange={setCurrentPage}
          />
        </div>
      )}
    </section>
  );
}
