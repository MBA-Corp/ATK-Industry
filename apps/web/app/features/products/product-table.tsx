import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "~/components/ui/table";
import type { Product } from "~/features/products/product.types";

type ProductTableProps = {
  products: Product[];
};

export function ProductTable({ products }: ProductTableProps) {
  return (
    <div className="rounded-lg border bg-card text-card-foreground">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Kode Item</TableHead>
            <TableHead>Nama Item</TableHead>
            <TableHead>Jenis</TableHead>
            <TableHead className="text-right">Stok</TableHead>
            <TableHead>Satuan</TableHead>
            <TableHead className="text-right">Harga Pokok</TableHead>
            <TableHead className="text-right">Harga Jual</TableHead>
          </TableRow>
        </TableHeader>

        <TableBody>
          {products.map((product) => (
            <TableRow key={product.id}>
              <TableCell className="font-medium">
                {product.sku}
              </TableCell>

              <TableCell>{product.name}</TableCell>

              <TableCell>
                {product.category ?? "—"}
              </TableCell>

              <TableCell className="text-right">
                {product.stock}
              </TableCell>

              <TableCell>
                {product.unit ?? "—"}
              </TableCell>

              <TableCell className="text-right tabular-nums">
                {product.cost_price.toLocaleString("id-ID")}
              </TableCell>

              <TableCell className="text-right tabular-nums">
                {product.selling_price.toLocaleString("id-ID")}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}
