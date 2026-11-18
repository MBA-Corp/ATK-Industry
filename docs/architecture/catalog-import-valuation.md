# Catalog import and provisional inventory valuation

Status: implemented, verification recorded in CODEX-HANDOFF. This is not approved
financial accounting. Existing v1 catalog/checkout responses retain their shape.

## Import contract
Permission catalog.import, authenticated active user. Grant via RbacSeeder for the
owner role after reviewing access. Preview does not mutate catalog or inventory.

POST /api/v1/catalog-imports/preview (multipart):
- file: .xlsx, at most 10 MiB; sheet Satuan, maximum 3000 nonempty data rows.
- conversion_mode=to_base: explicit acknowledgement that KONVERSI values express
  units of SATUAN1 per selected unit, not a chained conversion.
- zero_price_policy=disable_sales.

Response data: hash, total, valid, failed, commit_allowed, rows. Each row contains
source row number (header + index), sku/name, errors, warnings, normalized units and
source metadata. Review every warning; source data is provisional.

Required columns: KODEITEM, NAMAITEM, SATUAN1, KONVERSI1=1, HARGAJUAL1, STOK.
Optional JENIS category; SATUAN2–4 with matching positive KONVERSI and HARGAJUAL;
BARCODE1–4; HARGAPOKOK1; STOKMIN. Other source metadata (MEREK, SUPPLIER, RAK,
KETERANGAN etc.) is retained in audited import rows, not silently turned into
supplier relations or stock planning policy. Numeric SKU/barcode cells cannot
recover Excel display-only leading zeros: store identifiers as text before upload.
Formula cells, XML entities, oversized XML/ZIP entries and duplicate headers fail.

POST /api/v1/catalog-imports/commit: same multipart fields plus idempotency_key UUID,
preview_hash and acknowledge_provisional_source=true.
Success data: id, imported, replayed. Same key/content returns original import;
same key/different content or another import of same content => 409. Revalidation
and all rows/opening movements commit atomically; any failed row imports nothing.
Database uniqueness closes concurrent duplicate SKU/barcode/key races; 409 requires
retry with the same key or a new preview, never blind replacement.
Zero base selling price disables the product; alternate zero-price units are inactive.
Zero/missing opening cost means unknown cost, not a free acquisition.
Existing products are never overwritten. Source data remains in the database only.

## Valuation ledger
Additive tables inventory_costs and inventory_valuations plus sale_items.cost_total.
ApplyStockMovementAction serializes product/balance changes and the associated
valuation in one transaction. Value is DECIMAL(30,6), quantity DECIMAL(18,6); BCMath
is required. No float money arithmetic.
Receipts use actually received quantity times the purchase price, excluding freight.
Outflows allocate current value proportionally; final depletion removes the full
residual. A sale snapshots its cost. Cancellation restores that snapshot.
Unknown existing/acquired cost propagates until inventory is depleted or an explicit
future audited opening reconciliation is implemented. No historical cost backfill.
Positive adjustments without acquisition evidence invalidate blended cost.
The ledger follows processing order. Backdated occurred_at does not retroactively
revalue transactions; reporting must distinguish transaction dates from cost history.
The policy label is moving_average_excluding_freight_provisional.
