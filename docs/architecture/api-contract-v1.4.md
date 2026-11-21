# API Contract v1.4 — Implementation Notes

**Canonical contract:** `api-contract-v1.4.yaml`  
**Format:** OpenAPI 3.1  
**Architecture:** React SPA ↔ Laravel API + Laravel Reverb/Echo

## Navigasi cepat

- [4. Money Contract](#4-money-contract)
- [11. Purchase Finalization](#11-purchase-finalization)
- [12. Inventory](#12-inventory-contract)
- [20. Financial Reporting](#20-financial-reporting--inventory-valuation-contract-revisi-1)
- [21. Safety Stock & ROP](#21-safety-stock--reorder-point-contract-revisi-2)
- [22. Hardware & Peripheral Contract](#22-peripheral-integration--cash-drawer-contract-revisi-3-dan-4)
- [23. Lokasi repo](#23-repository-location)

## 1. Contract Rules

### URL Versioning

Business API menggunakan:

```text
/api/v1/*
```

Sanctum tetap menggunakan:

```text
/sanctum/csrf-cookie
/login
/logout
```

## 2. Authentication

First-party SPA menggunakan Laravel Sanctum cookie/session authentication.

Frontend flow:

```text
GET /sanctum/csrf-cookie
POST /login
GET /api/v1/me
```

Semua request authenticated harus mengirim credentials/cookies.

Frontend tidak menyimpan API bearer token di localStorage untuk normal online session.

## 3. Response Envelope

Single resource:

```json
{
  "data": {}
}
```

Collection:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 2431,
    "last_page": 122
  }
}
```

Validation error:

```json
{
  "message": "Validation failed.",
  "errors": {
    "sku": [
      "SKU already exists."
    ]
  }
}
```

Domain conflict:

```json
{
  "message": "Insufficient stock.",
  "code": "INSUFFICIENT_STOCK",
  "details": {
    "product_id": 10,
    "available": 2,
    "requested": 3
  }
}
```

## 4. Money Contract

Semua nilai uang API menggunakan **integer Rupiah**.

Benar:

```json
{
  "selling_price": 12500
}
```

Artinya:

```text
Rp12.500
```

Jangan kirim:

```json
12500.0
```

atau string formatted:

```json
"Rp 12.500"
```

Formatting Rupiah adalah tanggung jawab presentation layer frontend.

## 5. Quantity Contract

Quantity dikirim sebagai numeric decimal.

Contoh:

```json
{
  "quantity": 2,
  "conversion_to_base": 12,
  "base_quantity": 24
}
```

Backend menjadi authority untuk perhitungan final `base_quantity`.

Frontend boleh menghitung preview tetapi tidak boleh dianggap source of truth.

## 6. Date & Time

Tanggal:

```text
YYYY-MM-DD
```

Datetime menggunakan ISO 8601.

Contoh:

```text
2026-09-21T10:30:00+07:00
```

Client mengirim datetime dengan timezone offset.

Backend menyimpan waktu secara konsisten dan mengembalikan ISO 8601.

## 7. Product Contract

Search product:

```http
GET /api/v1/products?search=6324-01
```

`search` dapat mencari:

- SKU
- barcode
- product name

Product mempunyai:

```text
units[]
barcodes[]
inventory
```

Satu ProductUnit harus memiliki:

```text
is_base = true
```

## 8. Sales Contract

### Online Sale

```http
POST /api/v1/sales
```

Client wajib menghasilkan:

```text
client_uuid
```

sebelum request dilakukan.

Contoh:

```json
{
  "client_uuid": "2ce45bd9-ea82-4a82-939c-67161384bed3",
  "terminal_uuid": "de5de632-8ec2-4201-839f-e7920734fe19",
  "occurred_at": "2026-09-21T10:30:00+07:00",
  "items": [
    {
      "product_id": 10,
      "product_unit_id": 20,
      "quantity": 2,
      "unit_price": 5000,
      "discount_amount": 0
    }
  ],
  "payments": [
    {
      "method": "CASH",
      "amount": 4000
    },
    {
      "method": "QRIS",
      "amount": 6000
    }
  ]
}
```

Backend wajib memvalidasi:

```text
sum(payments) == grand_total
```

Backend tidak mempercayai subtotal/cost yang dihitung frontend.

## 9. Offline Sales

Frontend menyimpan locally completed transaction menggunakan `client_uuid`.

Sync:

```http
POST /api/v1/offline/sales-sync
```

Request dapat membawa batch hingga 100 sale.

Backend response per item:

```text
synced
already_synced
conflict
rejected
```

`already_synced` dianggap sukses oleh client dan item lokal boleh ditandai synced.

### Idempotency

```text
client_uuid
```

harus unique.

Retry request dengan UUID sama tidak boleh membuat sale kedua.

## 10. Offline Bootstrap

Saat terminal memiliki koneksi:

```http
GET /api/v1/offline/bootstrap?terminal_uuid=...
```

Frontend menyimpan minimal:

- current user;
- permissions;
- products;
- barcodes;
- product units;
- prices;
- inventory snapshot.

Incremental update:

```http
GET /api/v1/offline/changes?since=...
```

## 11. Purchase Finalization

Purchase dibuat sebagai draft:

```http
POST /api/v1/purchases
```

Draft dapat diubah:

```http
PATCH /api/v1/purchases/{id}
```

Stock **belum bertambah**.

Hanya ketika:

```http
POST /api/v1/purchases/{id}/receive
```

backend:

1. finalizes purchase;
2. creates stock movements;
3. updates current inventory;
4. updates HPP/average cost sesuai metode yang disepakati.

Received purchase tidak diedit secara destruktif.

Gunakan purchase return/correction.

## 12. Inventory Contract

Frontend tidak pernah mengirim:

```text
new_stock = 100
```

sebagai direct product edit.

Perubahan stok dilakukan melalui domain command:

```text
Purchase Receive
Sale
Sale Return
Purchase Return
Stock Adjustment
Stock Opname
```

Endpoint correction:

```http
POST /api/v1/stock-adjustments
```

Dengan begitu semua perubahan menghasilkan stock movement.

## 13. Realtime Contract

Realtime bukan source of truth.

Flow:

```text
Laravel transaction commit
       ↓
Broadcast Event
       ↓
Reverb
       ↓
Echo
       ↓
Frontend invalidate relevant TanStack Query
       ↓
HTTP API refetch
```

### Recommended Channels

```text
private-terminal.{terminalUuid}
private-inventory
private-sales
```

Nama channel final dapat disesuaikan ketika authorization channel Laravel dibuat.

### Event v1

#### `stock.updated`

Payload minimum:

```json
{
  "product_id": 10,
  "quantity_base": 120,
  "occurred_at": "2026-09-21T10:31:00+07:00"
}
```

Frontend action:

```text
invalidate product/inventory query
```

#### `sale.completed`

```json
{
  "sale_id": 100,
  "sale_no": "INV-20260921-0001",
  "terminal_uuid": "de5de632-8ec2-4201-839f-e7920734fe19",
  "grand_total": 10000,
  "completed_at": "2026-09-21T10:31:00+07:00"
}
```

Frontend action:

```text
invalidate dashboard/sales query
```

#### `product.updated`

```json
{
  "product_id": 10,
  "updated_at": "2026-09-21T10:31:00+07:00"
}
```

Frontend action:

```text
invalidate product query
refresh offline cache when appropriate
```

#### `sync.conflict.created`

Target channel:

```text
private-terminal.{terminalUuid}
```

Payload:

```json
{
  "client_uuid": "...",
  "conflict_code": "INSUFFICIENT_STOCK"
}
```

## 14. HTTP Status Rules

```text
200 OK
201 Created
204 No Content
401 Unauthenticated
403 Forbidden
404 Not Found
409 Domain Conflict
422 Validation Error
429 Too Many Requests
500 Unexpected Server Error
```

Gunakan `409` untuk conflict business state seperti:

```text
INSUFFICIENT_STOCK
PURCHASE_ALREADY_RECEIVED
SALE_ALREADY_RETURNED
SYNC_CONFLICT
```

Gunakan `422` untuk bentuk input invalid.

## 15. Price Authority

Frontend mengirim `unit_price` sebagai harga yang terlihat/digunakan terminal.

Backend tetap memvalidasi terhadap current allowed price dan permission user.

Jika terdapat perbedaan harga yang tidak diizinkan:

```text
409 PRICE_CONFLICT
```

Untuk offline sale, conflict dapat masuk proses offline sync review.

## 16. Permission Contract

`GET /api/v1/me` mengembalikan flat permission list:

```json
{
  "permissions": [
    "sale.create",
    "sale.return",
    "product.view",
    "inventory.view"
  ]
}
```

Frontend menggunakan permission untuk visibility/UX.

Backend **tetap wajib** melakukan authorization.

Menyembunyikan button pada frontend bukan security control.

## 17. Contract Ownership

Perubahan kontrak tidak boleh dilakukan sepihak.

Jika backend ingin mengubah:

```text
selling_price
```

menjadi:

```text
price
```

harus melalui contract change terlebih dahulu.

Recommended workflow:

```text
Edit OpenAPI
↓
Pull Request
↓
Frontend + Backend review
↓
Merge
↓
Implementation
```

OpenAPI menjadi source of truth lintas tim.

## 18. Versioning Rules

Breaking change tidak dilakukan diam-diam di `/api/v1`.

Breaking examples:

- rename response fields;
- menghapus required field;
- mengubah type integer menjadi string;
- mengubah semantic endpoint.

Jika v1 sudah digunakan production dan perlu breaking change:

```text
/api/v2
```

Additive change yang backward-compatible tetap dapat berada di v1.

## 19. Implementation Priority

### Phase A — Foundation

```text
GET  /api/v1/health
GET  /api/v1/me
Auth
Terminals
```

### Phase B — Catalog

```text
Products
Product Units
Barcodes
Suppliers
Inventory read
```

### Phase C — Purchasing

```text
Purchases
Purchase Receive
Purchase Attachments
Purchase Returns
```

### Phase D — POS

```text
Sales
Payments
Receipt
Sale Returns
Cash Drawer Event
```

### Phase E — Offline

```text
Offline Bootstrap
Offline Changes
Offline Sales Sync
```

### Phase F — Reporting

```text
Expenses
Sales Reports
Profit & Loss
Export PDF/XLSX
```

## 20. Financial Reporting & Inventory Valuation Contract (Revisi 1)

### Data dan prinsip

Sesuai dokumen contoh ATK INSAN, backend adalah **authority** atas pembelian bersih, persediaan awal/akhir, HPP periodik, penjualan bersih, laba kotor, dan laba bersih. Frontend hanya memformat integer Rupiah dan menampilkan detail.

```text
net_purchases       = gross_purchases + freight_in - purchase_returns - purchase_discounts
cost_of_goods       = opening_inventory + net_purchases - ending_inventory
net_sales           = gross_sales - sales_returns - sales_discounts
gross_profit        = net_sales - cost_of_goods
net_profit          = gross_profit - operating_expenses
```

`cost_of_goods` di atas adalah HPP periodik dasar sesuai referensi; penyesuaian persediaan non-penjualan yang belum diklasifikasikan memicu `reconciliation_status=requires_review`.

### Purchase fields

`POST /api/v1/purchases` / `PATCH /api/v1/purchases/{id}` dapat membawa:

```json
{
  "freight_in_amount": 200000,
  "purchase_discount_amount": 50000
}
```

`freight_in_amount` hanya untuk biaya angkut yang menjadi bagian perolehan stok; tidak sekaligus masuk `expenses`. Response pembelian mengembalikan nilai dan alokasi landed cost setiap item. Backend menjamin jumlah alokasi persis cocok dengan header invoice dan recalculation saat receive.

### Profit & Loss API

```http
GET /api/v1/reports/profit-loss?from=2026-01-01&to=2026-01-31
```

Response `data` menyediakan breakdown baru: `gross_sales`, `sales_returns`, `sales_discounts`, `net_sales`, `opening_inventory`, `gross_purchases`, `freight_in`, `purchase_returns`, `purchase_discounts`, `net_purchases`, `goods_available_for_sale`, `ending_inventory`, `cogs`, `gross_profit`, `operating_expenses`, `operating_expense_breakdown`, `net_profit`, serta `reconciliation_status`. Field lama `revenue` dipertahankan sebagai alias `net_sales` untuk kompatibilitas v1, dan **jangan dijumlahkan**.

Point-in-time inventory:

```http
GET /api/v1/reports/inventory-valuation?as_of=2026-01-31
```

Nilai persediaan historis berasal dari ledger nilai/perubahan stok, bukan `current average_cost × past quantity`. Permission khusus laporan harga modal/laba ditegakkan backend.

### Golden Test

Januari 2026 (data simulasi ATK INSAN):

```json
{
  "gross_purchases": 5000000,
  "freight_in": 200000,
  "purchase_returns": 100000,
  "purchase_discounts": 50000,
  "net_purchases": 5050000,
  "opening_inventory": 8000000,
  "ending_inventory": 3000000,
  "cogs": 10050000,
  "gross_sales": 16000000,
  "sales_returns": 200000,
  "sales_discounts": 100000,
  "net_sales": 15700000,
  "gross_profit": 5650000,
  "operating_expenses": 1700000,
  "net_profit": 3950000
}
```

Kewajiban test: reproduksi tepat semua nilai, continuity persediaan antar-periode, tidak menghitung freight-in dua kali, tidak mengurangi diskon penjualan dua kali, pengakuan receipt vs draft, dan status review saat stock variance terjadi.

### Metode valuasi yang belum locked

Moving Weighted Average untuk harga per unit tetap usulan, **bukan** konsekuensi otomatis rumus HPP periodik. Presisi decimal dipakai untuk perhitungan rate internal, sedangkan amounts API integer IDR. Field `average_cost` API yang saat ini berbentuk integer IDR merupakan nilai tampilan dibulatkan; `inventory_value` menyimpan total carrying value yang digunakan untuk pembuktian saldo, sehingga data historis tidak bergantung pada pembulatan rate tampilan.

## 21. Safety Stock & Reorder Point Contract (Revisi 2)

### Rumus dan aturan batas

Backend adalah authority bagi semua nilai berikut:

```text
safety_stock_raw          = z_score × stddev_daily_demand × sqrt(lead_time_days)
safety_stock_base         = ceil_to_base_unit_step(safety_stock_raw)
reorder_point_base        = ceil_to_base_unit_step(average_daily_demand × lead_time_days + safety_stock_base)
```

Aturan status: `stock=0` Habis; `0<stock<=SS` Kritis; `SS<stock<=ROP` Perlu pemesanan; `stock>ROP` Stok mencukupi. Jika tidak ada perhitungan/fallback valid, return `insufficient_data`. Jangan memakai `safety_days` v1.1 sebagai input rumus baru.

### Inventory Policy API

```http
GET /api/v1/products/{productId}/inventory-policy
PUT /api/v1/products/{productId}/inventory-policy
```

Contoh PUT:

```json
{
  "auto_reorder_point_enabled": true,
  "lead_time_days": 4,
  "target_service_level": 0.95,
  "demand_window_days": 90,
  "minimum_history_days": 30,
  "manual_safety_stock_base": null,
  "manual_reorder_point": null
}
```

`demand_window_days=90` dan `minimum_history_days=30` merupakan **contoh konfigurasi engineering**, bukan angka yang sudah ditetapkan owner. `target_service_level` disimpan sebagai probabilitas 0–1; backend memilih Z-score dengan cara yang konsisten. Permission untuk mengubah kebijakan dan audit log tetap wajib.

Contoh response untuk dataset simulasi ATK INSAN (mean & SD telah diperoleh dari histori):

```json
{
  "data": {
    "auto_reorder_point_enabled": true,
    "lead_time_days": 4,
    "target_service_level": 0.95,
    "demand_window_days": 90,
    "minimum_history_days": 30,
    "average_daily_demand_base": 10,
    "stddev_daily_demand_base": 4,
    "z_score": 1.645,
    "raw_safety_stock_base": 13.16,
    "safety_stock_base": 14,
    "calculated_reorder_point": 54,
    "effective_reorder_point_base": 54,
    "calculation_status": "ready",
    "last_calculated_at": "2026-11-21T10:30:00+07:00"
  }
}
```

`calculation_status` bernilai `ready`, `manual_fallback`, `insufficient_history`, atau `not_configured`. Contoh tanggal hanyalah ilustrasi response, bukan waktu kejadian toko.

### Dashboard dan Reorder Alerts

```http
GET /api/v1/inventory?stock_status=reorder_needed
GET /api/v1/inventory/reorder-alerts
```

`stock_status` mengikuti enum baru: `out_of_stock`, `critical`, `reorder_needed`, `sufficient`, `insufficient_data`. Aplikasi dapat memfilter berdasarkan status, namun **data asli tetap disuplai backend**.

`GET /api/v1/inventory/reorder-alerts` mengembalikan koleksi paginated produk yang berstatus `out_of_stock`, `critical`, atau `reorder_needed`; produk `insufficient_data` perlu ditampilkan sebagai antrian pemeriksaan data yang terpisah.

### Calculation & Caching Rules

- Jumlah penjualan dinormalisasi ke base unit; seluruh hari kalender pada jendela valid (termasuk nol) masuk statistik.
- Backend menggunakan standard deviation populasi dan business timezone toko; perlakuan retur, koreksi sale, serta sinkronisasi offline terlambat adalah keputusan bisnis/teknis terbuka yang perlu konsisten di semua komponen.
- Refresh hasil setelah perubahan policy/stock relevant dan melalui job berkala untuk dashboard. Simpan `last_calculated_at`; frontend tidak menghitung ulang secara mandiri.
- Jika histori belum memadai, gunakan nilai manual jika valid atau kirim status `insufficient_data` tanpa menampilkan ROP rekaan.
- Ketika terminal offline, dashboard menandai metrik cache sebagai berpotensi *stale*, bukan menjanjikan stok realtime.

### Golden Regression Tests

```text
Z=1.645, sigma=4, L=4, d=10
raw_ss=13.16; effective_ss=14; ROP=54
stock=50 -> reorder_needed
stock=0 -> out_of_stock
stock=14 -> critical
stock=15 -> reorder_needed
stock=54 -> reorder_needed
stock=55 -> sufficient
```

**Compatibility note:** penamaan status `low_stock/normal` dan field `safety_days` pada kontrak draft v1.1 berubah. Ini merupakan perubahan skema draft; bila klien sudah mengonsumsi v1.1, koordinasikan perubahan kontrak sebelum deploy. **URL tetap `/api/v1` hanya selama belum ada konsumen production yang terikat.**

## 22. Peripheral Integration & Cash Drawer Contract (Revisi 3)

### 22.1 Confirmed Baseline

Perangkat aktual: printer KASSEN dengan label **BT-P299**, thermal **58 mm**, **USB/Bluetooth**, **ESC/POS**, 90 mm/s, DC12V 2A; cash drawer **CD-339**; scanner **PANDA USB**. Owner mengonfirmasi **automatic cash drawer open pada pembayaran tunai POS lama** dan menetapkan bahwa **SIPINTAR harus membuka cash drawer secara otomatis juga pada QRIS-only dan split payment setelah sale selesai**. Kompatibilitas SIPINTAR, jalur drawer fisik, scanner HID dan driver masih memerlukan hardware spike.

### 22.2 Boundary of HTTP API vs Local Hardware

`GET /api/v1/sales/{saleId}/receipt` adalah **data receipt** untuk print/reprint. Endpoint ini **bukan** perintah fisik untuk membuka laci atau mencetak.

Browser POS berkomunikasi dengan Windows local agent melalui adapter perangkat. Agent tidak dijangkau dari server, tidak boleh terbuka pada interface publik, dan menggunakan autentikasi per terminal dengan origin checking serta validasi command. Rahasia/USB path perangkat tidak masuk OpenAPI bisnis.

### 22.3 Sale Durability First

- Online: finalisasi sale dan stok terjadi dalam transaksi backend. Setelah respons finalization sukses, local frontend mengantre perintah print/drawer.
- Offline: sale finalized secara **lokal durable** (`pending_sync`) lebih dulu; baru local hardware command dijalankan. Backend menerima sale secara idempotent saat sync.
- **Semua sale yang sudah completed/durable** memicu **satu** perintah `open_auto`, baik pembayaran **CASH-only, QRIS-only, maupun split CASH+QRIS**. Trigger dihitung **per sale** (`client_uuid`), bukan per payment atau saat QRIS ditampilkan.
- QRIS `pending`/gagal tidak boleh memicu perangkat. Karena provider QRIS belum terintegrasi secara otomatis, konfirmasi sukses mungkin dilakukan manual oleh kasir sesuai SOP sebelum sale difinalisasi.
- Perintah hardware lokal memiliki identitas yang disimpan durable dan dideduplikasi supaya React re-render, refresh, reconnect, maupun retry sync tidak membuka laci dua kali.
- Gagal cetak/open **tidak pernah** membuat frontend mengulang `POST /api/v1/sales` untuk memperbaiki hardware.
- Print retry memakai receipt sale yang sama; drawer `unknown`/timeout memerlukan keputusan operator, **jangan auto-retry**.
- `command_sent` bukan bukti sensor bahwa laci fisik terbuka.

### 22.4 Audit API — Additive v1.3

`POST /api/v1/terminals/{terminalUuid}/peripheral-events` menerima audit event nontransaksional, termasuk saat reprint dan pembukaan laci. **Tidak ada endpoint baru untuk QRIS drawer:** pemilihan waktu trigger adalah logika POS/agent lokal setelah sale finalized; backend API mencatat outcome. Gunakan `event_uuid` yang tetap pada retry submit agar audit idempotent. Contoh:

```json
{
  "event_uuid": "317b4c5a-5bd2-4cd8-9a32-0f32ba5bff13",
  "command_uuid": "b9123d9d-1d77-43f7-94e1-013e05db6f07",
  "client_uuid": "2ce45bd9-ea82-4a82-939c-67161384bed3",
  "peripheral_type": "cash_drawer",
  "action": "open_auto",
  "source": "pos",
  "outcome": "command_sent",
  "occurred_at": "2026-11-21T12:30:00+07:00"
}
```

HTTP `201` untuk event baru; `200` dengan event sama untuk replay `event_uuid`; `409` untuk UUID sama tetapi payload berbeda; `422` jika invalid. `event_uuid` wajib untuk endpoint baru. `sale_id` dan `client_uuid` menjadi korelasi opsional; satu di antaranya sebaiknya tersedia untuk event sale.

Endpoint lama `/cash-drawer-events` dipertahankan untuk kompatibilitas; **jangan menulis kedua endpoint untuk satu kejadian**. Pembukaan manual melalui UI tetap memerlukan authorization `cash_drawer.open_manual` dan audit. Tanpa sensor laci, mekanisme kunci fisik di luar aplikasi tidak dapat diamati.

### 22.5 Operational Acceptance Tests

| Scenario | Expected result |
| --- | --- |
| CASH-only completed | 1 drawer command setelah sale durable |
| CASH + QRIS completed | 1 drawer command setelah sale durable |
| QRIS-only completed/durable | 1 drawer command setelah sale durable |
| QRIS pending/gagal atau payment belum tervalidasi | Tidak ada drawer command |
| Printer error | Sale tetap completed; UI menawarkan reprint |
| Drawer error/unknown | Sale tetap completed; tidak auto-retry drawer |
| Duplicate sale sync | Tidak ada sale baru, stock movement baru, atau drawer open baru |
| Offline finalized sale (CASH/QRIS/split) | Local durable sale lebih dahulu; command lokal dapat diproses setelah status pembayaran diizinkan SOP |
| Barcode scan | Scanner membaca input, tidak auto-checkout |


## 23. Repository Location

Recommended:

```text
docs/architecture/api-contract-v1.4.yaml
docs/architecture/api-contract-v1.3.md
```

Canonical machine-readable source:

```text
api-contract-v1.4.yaml
```

Companion implementation guide:

```text
api-contract-v1.3.md
```

## 24. Catalog Master Data, Scan-to-Register dan Barcode (Revisi 4)

### 24.1 Perbedaan Identitas

- `products.sku` adalah kode internal toko, misalnya `6324-579`. Backend boleh mengalokasikan SKU baru secara sequence atomik ketika `sku_mode=auto`; kode legacy yang diimpor tetap dipertahankan.
- `product_barcodes[].barcode` adalah pengenal scannable produk/satuan, boleh barcode pabrik atau barcode internal. Nilai disimpan sebagai string (jangan hilangkan leading zero atau tanda hubung).
- Harga jual tetap `product_units[].selling_price` dan tidak berasal dari barcode.

### 24.2 Scan Barcode Produk Baru

```http
GET /api/v1/catalog/scan-lookup?code=8990037600910
```

Bila diketahui, response mengembalikan product + product_unit yang ter-resolve; bila tidak, `status=not_found` dan frontend membuka draft registrasi dengan `scanned_code` prefilled. Endpoint ini **tidak** membuat produk, stok, atau transaksi.

```http
POST /api/v1/products
Content-Type: application/json
```

```json
{
  "sku_mode": "auto",
  "name": "Produk Baru dari Supplier",
  "category_id": 1,
  "units": [{"client_key": "pcs", "unit_id": 1, "conversion_to_base": 1, "selling_price": 5000, "is_base": true}],
  "barcodes": [{"barcode": "8990000000000", "source": "FACTORY", "unit_client_key": "pcs", "is_primary": true}]
}
```

Angka pada contoh adalah **ilustrasi payload**, bukan barcode pabrik asli untuk diterapkan. `sku_mode=auto` meminta sequence server; alternatif `sku_mode=provided` harus menyertakan `sku` valid untuk migrasi/kode yang telah teralokasi. `units[].client_key` mengikat `barcodes[].unit_client_key` secara transaksional sebelum ID satuan tersedia.

### 24.3 Buat/Tempel Barcode Internal

```http
POST /api/v1/products/{productId}/barcodes
```

```json
{
  "mode": "system",
  "product_unit_id": 20,
  "is_primary": false
}
```

Backend menggunakan Code 128 dari SKU; meminta ulang pada produk/satuan sama mengembalikan mapping yang sudah ada. Mode `factory` mensyaratkan field `barcode` dan menolak konflik dengan `409 BARCODE_ALREADY_ASSIGNED`. Barcode sistem tidak mengubah harga jual.

```http
GET /api/v1/products/{productId}/barcodes/{barcodeId}/print-artifact
```

Mengembalikan `application/pdf` label untuk dicetak/diulang, tanpa regenerasi identitas. Dukungan printer stiker khusus belum terbukti; browser/A4 menjadi alternatif sampai uji hardware.

### 24.4 Penetapan Harga per Satuan

```http
PATCH /api/v1/products/{productId}/units/{productUnitId}/price
```

```json
{
  "selling_price": 5000,
  "reason": "Harga jual awal disetujui owner"
}
```

Backend memeriksa `product.change_price`, merekam audit, dan memvalidasi bahwa satuan adalah milik product. Sale untuk satuan yang berharga 0/belum disetujui mengembalikan `409 PRICE_NOT_SET`. Scan tidak otomatis mengubah `selling_price` atau harga pokok.

### 24.5 Import Legacy: Jalur Operasional

`uli.xlsx` disarankan masuk melalui **script/importer admin terkontrol** (preview/dry run, laporan warning, commit + audit) dan **bukan** endpoint POST produk tanpa validasi massal. Saat ini belum ditambahkan endpoint import publik karena proses dan permission bulk import belum disetujui. Opening stock hanya masuk melalui stock movement khusus; tidak boleh set saldo langsung dari `STOK`.

Minimum audit: 2.447 SKU unik, 638 barcode unik (512 produk), 1.935 tanpa barcode, 1.431 tanpa harga jual positif (1.237 masih memiliki stok), 1.093 multi-unit, 2.370 `STOKMIN=0`, 256 nilai cost unit pecahan, dan 5 mismatch pembanding cost per conversion. Pastikan `STOKMIN` tidak mengganti perhitungan statistik v1.2.

### 24.6 Error dan Idempotency

| Condition | Status | Code |
| --- | --- | --- |
| Barcode sudah dipakai produk/satuan lain | 409 | `BARCODE_ALREADY_ASSIGNED` |
| SKU telah digunakan | 409 | `SKU_ALREADY_EXISTS` |
| Barcode tidak ditemukan pada lookup | 200 | `status=not_found` (bukan domain conflict) |
| Unit belum ditetapkan harga jual | 409 | `PRICE_NOT_SET` |
| User tanpa izin mengubah harga | 403 | `FORBIDDEN` |
| Payload konversi/satuan tidak valid | 422 | `VALIDATION_ERROR` |

## Changelog v1.4 — Catalog & Barcode

- Menambahkan scan-lookup, pendaftaran produk dengan SKU auto, barcode factory/system, label artifact, update harga satuan dan kontrak konflik.
- Migrasi `uli.xlsx` direkomendasikan lewat staging importer admin; tidak mengubah stock snapshot atau flow offline sale secara langsung.
- Kontrak HPP/laba rugi, Safety Stock/ROP, printer/cash drawer semua metode v1.3 tetap dipertahankan.

## Pembaruan v1.3 — QRIS Membuka Cash Drawer

- Mengubah aturan `open_auto` agar **setiap sale completed/durable** (CASH, QRIS-only, split) memicu **satu** perintah pembukaan cash drawer.
- Menambah negative test untuk QRIS pending/gagal dan aturan konfirmasi pembayaran sebelum finalization.
- Tidak mengubah path atau schema HTTP; event peripheral sebelumnya tetap kompatibel.
- Mempertahankan idempotensi sale/command, keamanan agent, HPP/laba rugi, serta Safety Stock/ROP.

## Changelog v1.3

- Memisahkan API receipt data dari local Windows agent printer/cash drawer.
- Menambahkan `POST /api/v1/terminals/{terminalUuid}/peripheral-events` beserta event UUID idempotency dan status `command_sent`/`failed`/`unknown`.
- Mendokumentasikan trigger pembayaran CASH dan split, mencegah duplicate sale/perintah drawer ketika retry, serta uji offline.
- Menjaga semua kontrak finansial v1.1 dan inventory v1.2.

## Changelog v1.2

- Mengganti formula SS/ROP, parameter policy, dan status inventory berdasarkan dokumen contoh Safety Stock ATK INSAN.
- Menambahkan contoh PUT/GET policy, daftar reorder-alerts, fallback data, rounding, dan golden test 14/54/50.
- Menegaskan breaking changes pada enum inventory dan menghindari klaim kondisi realtime saat offline.
- Mempertahankan perubahan financial reporting v1.1.

## Changelog v1.1

- Menambahkan breakdown periodik HPP, pembelian/penjualan bersih, dan laba rugi.
- Mendefinisikan historical inventory valuation dan report reconciliation status.
- Menjaga field revenue lama sebagai alias demi kompatibilitas v1.

