# API Contract v1.0 — Implementation Notes

**Canonical contract:** `api-contract-v1.0.yaml`  
**Format:** OpenAPI 3.1  
**Architecture:** React SPA ↔ Laravel API + Laravel Reverb/Echo

---

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

---

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

---

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

---

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
12500.00
```

atau string formatted:

```json
"Rp 12.500"
```

Formatting Rupiah adalah tanggung jawab presentation layer frontend.

---

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

---

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

---

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

---

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

---

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

---

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

---

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

---

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

---

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

---

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

---

## 15. Price Authority

Frontend mengirim `unit_price` sebagai harga yang terlihat/digunakan terminal.

Backend tetap memvalidasi terhadap current allowed price dan permission user.

Jika terdapat perbedaan harga yang tidak diizinkan:

```text
409 PRICE_CONFLICT
```

Untuk offline sale, conflict dapat masuk proses offline sync review.

---

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

---

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

---

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

---

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

---

## 20. Repository Location

Recommended:

```text
docs/architecture/api-contract-v1.0.yaml
docs/architecture/api-contract-v1.0.md
```

Canonical machine-readable source:

```text
api-contract-v1.0.yaml
```

Companion implementation guide:

```text
api-contract-v1.0.md
```
