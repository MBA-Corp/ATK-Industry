# Critical Decisions Resolution v1.0

**Project:** Retail POS & Inventory Management System  
**Status:** Proposed Requirement Lock  
**Purpose:** Menutup keputusan kritis setelah PRD v1.0 agar frontend/backend dapat mulai implementasi tanpa menebak business rule.

---

## Prinsip Keputusan

1. Requirement dari owner tetap menjadi sumber utama.
2. Jika jawaban owner ambigu, dipilih desain yang:
   - tidak merusak histori transaksi,
   - mudah diaudit,
   - fleksibel dikonfigurasi,
   - aman untuk stok dan laporan,
   - tidak membuat implementasi MVP terlalu kompleks.
3. Keputusan teknis dibuat agar business rule dapat berubah tanpa membongkar arsitektur.
4. Semua threshold/rule yang mungkin berubah dibuat configurable, bukan hardcoded.

---

# CD-001 — Barcode

## Masalah
Discovery memiliki dua informasi yang bertentangan:
- ada pernyataan bahwa belum semua barang menggunakan barcode;
- ada pernyataan bahwa semua barang sudah memiliki barcode.

## Keputusan
**RESOLVED**

Sistem tidak boleh bergantung pada barcode sebagai satu-satunya cara memilih produk.

POS wajib mendukung:
1. Scan barcode.
2. Entry kode/SKU.
3. Search nama produk.

Barcode bersifat **recommended identifier**, tetapi transaksi tetap dapat berjalan tanpa barcode.

## Implementasi
Product:
- `sku` wajib dan unique.
- `barcode` nullable tetapi unique jika terisi.

POS fallback:
`Barcode -> SKU -> Nama`

Dengan keputusan ini, kondisi data barcode client tidak menjadi blocker development.

---

# CD-002 — Harga Modal / HPP

## Masalah
Owner menyatakan harga modal harus menyesuaikan ketika harga beli berubah, tetapi metode HPP belum ditentukan.

## Keputusan
**PROPOSED DEFAULT: Moving Weighted Average / Average Cost**

Harga modal produk dihitung dengan moving weighted average pada saat penerimaan pembelian.

Formula:

`new_average_cost = ((old_stock × old_average_cost) + (received_qty × purchase_cost)) / (old_stock + received_qty)`

Harga modal disimpan dalam base unit.

## Alasan
- Cocok untuk retail dengan pembelian berulang.
- Harga supplier dapat berubah.
- Satu produk dapat berasal dari beberapa supplier.
- Lebih mudah dipahami dan diaudit dibanding FIFO.
- Tidak membutuhkan layer inventory lot yang kompleks untuk MVP.

## Catatan
Biaya ongkir/operasional pembelian **tidak masuk HPP produk**, karena owner menyatakan biaya tersebut dihitung terpisah.

## Status Validasi
Keputusan ini dapat digunakan sebagai default engineering.

**Sebelum laporan laba rugi digunakan resmi, owner/accounting perlu mengonfirmasi bahwa Average Cost dapat diterima.**

---

# CD-003 — Negative Stock

## Masalah
Belum ada jawaban eksplisit apakah stok boleh negatif.

Namun owner menyatakan jika stok sistem nol tetapi fisik tersedia, stok perlu di-entry terlebih dahulu.

## Keputusan
**RESOLVED**

Penjualan **tidak boleh membuat stok menjadi negatif secara default**.

Jika stok sistem tidak mencukupi:
1. Kasir tidak dapat menyelesaikan item tersebut.
2. User harus melakukan stock adjustment terlebih dahulu.
3. Setelah stok diperbaiki, transaksi dapat dilanjutkan.

## Alasan
Tujuan utama sistem adalah mengurangi selisih stok.

Negative stock justru menyembunyikan masalah inventory.

---

# CD-004 — Safety Stock

## Masalah
Owner menginginkan safety stock menggunakan aturan tertentu tetapi belum menentukan formula.

## Keputusan
**RESOLVED FOR MVP**

Gunakan model configurable per produk:

- `lead_time_days`
- `safety_days`
- `manual_reorder_point` (optional override)

Default automatic calculation:

`average_daily_sales × (lead_time_days + safety_days)`

Jika histori penjualan belum cukup, gunakan `manual_reorder_point`.

## MVP
User dapat:
- mengatur safety stock manual;
- mengatur lead time supplier/product;
- mendapatkan notifikasi jika stok <= reorder point.

## Future
Setelah histori cukup, sistem dapat memberikan rekomendasi otomatis.

Tidak perlu membuat algoritma forecasting kompleks pada MVP.

---

# CD-005 — Slow / Middle / Fast Moving

## Masalah
Owner membutuhkan klasifikasi slow, middle, dan fast moving tetapi threshold belum ditentukan.

## Keputusan
**RESOLVED AS CONFIGURABLE RULE**

Gunakan rolling period default:

**90 hari**

Metric awal:

**quantity base-unit terjual selama periode**

Default klasifikasi menggunakan percentile SKU aktif:

- Fast Moving: top 20%
- Middle Moving: berikutnya 30%
- Slow Moving: sisa 50%

Produk tanpa penjualan selama periode otomatis masuk Slow Moving.

## Important
Threshold harus configurable di system settings.

Frontend/backend tidak boleh hardcode 20/30/50 di business logic permanen.

## Future
Rule dapat dikembangkan dengan:
- sales frequency,
- revenue,
- stock turnover,
- category-specific threshold.

---

# CD-006 — Perubahan Harga & Persetujuan Owner

## Masalah
Owner menyatakan:
- user dapat mengubah harga atas arahan/persetujuan owner;
- tidak membutuhkan approval akun utama di sistem.

## Keputusan
**RESOLVED**

MVP tidak membutuhkan workflow approval harga.

Gunakan permission:

`product.change_price`

Owner menentukan user/role mana yang memiliki permission tersebut.

Semua perubahan harga wajib membuat audit log:

- user
- timestamp
- old price
- new price
- product
- optional reason

## Makna "persetujuan owner"
Persetujuan merupakan proses operasional di luar sistem.

Sistem hanya menegakkan permission yang sudah diberikan owner.

---

# CD-007 — Retur / Koreksi Transaksi

## Masalah
Owner menginginkan revisi transaksi yang mudah, tetapi mekanismenya belum ditentukan.

## Keputusan
**RESOLVED**

Transaksi yang sudah finalized **tidak boleh diedit atau dihapus secara destruktif**.

Gunakan model:

### Sebelum pembayaran selesai
Cart masih dapat diedit bebas.

### Setelah transaksi finalized
Gunakan:
- Return / correction transaction yang mereferensikan transaksi asli.
- Stock movement koreksi.
- Payment/refund adjustment jika diperlukan.
- Jika pelanggan mengganti barang, sistem mencatat retur lalu transaksi pengganti.

Original sale tetap tersimpan.

## Void
Void digunakan untuk transaksi yang dibatalkan sebelum settlement/finalization atau sesuai kondisi yang secara teknis aman.

## Reprint
Struk transaksi maupun dokumen koreksi dapat dicetak ulang.

## Alasan
- Audit trail terjaga.
- Laporan tidak berubah tanpa jejak.
- Mengurangi manipulasi data histori.

---

# CD-008 — Manual Cash Drawer

## Masalah
Owner menyatakan cash drawer dapat dibuka manual dan hanya kasir yang boleh membukanya.

Belum ditentukan apakah aktivitas perlu dicatat.

## Keputusan
**RESOLVED**

Setiap manual cash drawer open wajib dicatat.

Audit record minimal:

- cashier/user
- timestamp
- terminal/device
- reason (optional untuk MVP, dapat dibuat required kemudian)

Permission:

`cash_drawer.open_manual`

Automatic open dari transaksi tunai tidak dianggap manual open tetapi tetap dapat tercatat sebagai device event.

---

# CD-009 — Receipt Printer

## Masalah
Model printer dan ukuran kertas belum dikonfirmasi.

## Keputusan
**PARTIALLY RESOLVED**

Aplikasi tidak boleh terikat pada satu model printer.

Receipt renderer mendukung konfigurasi:

- 58 mm
- 80 mm

Target protokol:

**ESC/POS-compatible**

Namun sebelum production deployment tetap diperlukan hardware spike terhadap perangkat aktual client.

## Informasi yang masih dibutuhkan
- merek printer;
- model;
- koneksi USB/LAN/Bluetooth;
- ukuran kertas;
- dukungan ESC/POS.

Informasi ini tidak menghalangi development core POS.

---

# CD-010 — Offline Transaction

## Masalah
Owner mewajibkan transaksi tetap berjalan ketika internet mati.

## Keputusan
**RESOLVED ARCHITECTURALLY**

Frontend harus bersifat offline-capable.

Arah teknis:

React SPA/PWA
+
IndexedDB/local durable storage
+
Offline transaction queue
+
Sync service

Backend Laravel tetap menjadi central source of truth.

## Offline Data Minimum
Terminal harus memiliki cache lokal minimal:

- products
- SKU/barcode
- product units
- selling prices
- latest usable stock snapshot
- user/session authorization yang dibutuhkan untuk offline operation
- unsynced transactions

## Transaction Identifier
Setiap transaksi dibuat dengan client-generated UUID.

UUID yang sama digunakan saat sync untuk memastikan idempotency.

Backend harus menolak duplicate sync berdasarkan transaction UUID.

## Sync
Saat koneksi kembali:

1. Client mengirim queued transactions.
2. Backend memvalidasi UUID.
3. Backend memproses transaksi.
4. Client menandai transaksi sebagai synced.
5. Error/conflict masuk sync review state.

## Realtime
Laravel Reverb/Echo hanya digunakan ketika online.

Reverb **bukan** mekanisme offline sync.

---

# CD-011 — Offline Stock Conflict

## Masalah
Dua terminal dapat menjual stok yang sama ketika offline.

## Keputusan
**RESOLVED FOR MVP**

Saat offline:
- terminal menggunakan cached stock;
- tidak boleh menjual melebihi cached available stock lokal;
- transaksi disimpan sebagai pending sync.

Saat sync:
- backend tetap melakukan validasi stok;
- jika terjadi konflik, transaksi ditandai `sync_conflict` untuk review, bukan diam-diam dibuang.

## Catatan
Perfect stock consistency tidak mungkin dijamin antara beberapa terminal yang sama-sama offline tanpa local shared server.

MVP memilih traceability dan conflict handling dibanding menyembunyikan konflik.

---

# CD-012 — Listrik Mati / Crash Recovery

## Masalah
Owner meminta "catatan khusus" jika listrik mati ketika transaksi berlangsung.

## Keputusan
**RESOLVED**

POS harus memiliki draft persistence lokal.

Cart/draft transaction disimpan secara berkala ke local durable storage.

Jika aplikasi/browser/komputer restart:

- unfinished cart dapat dideteksi;
- user dapat memilih Resume atau Discard;
- sistem mencatat bahwa draft dipulihkan setelah interruption.

Transaksi belum dianggap sale sampai finalization berhasil.

Tidak perlu membuat transaksi penjualan palsu hanya untuk mencatat listrik mati.

---

# CD-013 — Hardware Bridge

## Masalah
Cash drawer terhubung langsung ke komputer dan printer/barcode printer perlu dikontrol.

Browser tidak selalu memiliki akses aman/langsung ke peripheral Windows.

## Keputusan
**RESOLVED AS TECHNICAL DIRECTION**

Arsitektur hardware harus menggunakan abstraction layer.

Frontend tidak boleh memanggil hardware secara langsung dari komponen bisnis.

Buat interface seperti:

- `ReceiptPrinterService`
- `CashDrawerService`
- `BarcodePrinterService`

Untuk production Windows, lakukan technical spike untuk memilih salah satu:

1. Local Windows hardware agent/service (recommended direction).
2. Browser-supported device API jika hardware terbukti kompatibel.
3. Print server/vendor driver integration.

Core frontend POS tidak boleh bergantung pada implementasi hardware tertentu.

---

# CD-014 — Purchase History Extra Information

## Masalah
Owner belum memberikan informasi tambahan yang ingin dilihat di histori pembelian.

## Keputusan
**RESOLVED FOR MVP**

Riwayat pembelian minimal berisi:

- purchase number
- supplier
- transaction date
- invoice/reference number
- items
- quantity
- unit
- purchase price
- total
- user who recorded
- invoice attachment

Tidak menambahkan field bisnis lain sampai ada kebutuhan nyata.

---

# CD-015 — Supplier Selection

## Masalah
Owner memilih supplier berdasarkan harga termurah dan pertimbangan pergerakan barang.

## Keputusan
**RESOLVED**

Sistem tidak memilih supplier otomatis pada MVP.

Ketika melakukan pembelian, sistem menampilkan informasi pendukung:

- supplier terkait produk;
- latest purchase price;
- historical prices;
- latest purchase date.

User tetap menentukan supplier.

Future recommendation engine dapat ditambahkan kemudian.

---

# CD-016 — Final Sale State

## Keputusan
**RESOLVED**

Sale menggunakan status minimal:

- `draft`
- `pending_payment`
- `completed`
- `cancelled`
- `return/corrected` melalui reference transaction
- `pending_sync`
- `sync_conflict`

Stock hanya dianggap terjual saat sale menjadi `completed`.

Untuk offline, local completed sale dapat berstatus `pending_sync` sampai backend mengonfirmasi sinkronisasi.

---

# Ringkasan Status

| ID | Decision | Status |
|---|---|---|
| CD-001 | Barcode fallback | Resolved |
| CD-002 | HPP Average Cost | Proposed default, accounting confirmation required |
| CD-003 | Negative stock | Resolved |
| CD-004 | Safety stock | Resolved for MVP |
| CD-005 | Moving classification | Resolved/configurable |
| CD-006 | Price approval | Resolved |
| CD-007 | Return/correction | Resolved |
| CD-008 | Manual cash drawer audit | Resolved |
| CD-009 | Receipt printer | Partially resolved; actual hardware info required |
| CD-010 | Offline architecture | Resolved |
| CD-011 | Offline stock conflict | Resolved for MVP |
| CD-012 | Crash/power recovery | Resolved |
| CD-013 | Hardware abstraction | Resolved technical direction |
| CD-014 | Purchase history | Resolved for MVP |
| CD-015 | Supplier selection | Resolved |
| CD-016 | Sale state model | Resolved |

---

# Client Confirmation Masih Diperlukan

Setelah keputusan di atas, hanya dua hal yang sebaiknya dianggap blocker sebelum production/financial go-live:

## 1. HPP / Accounting
Konfirmasi apakah **Moving Weighted Average / Average Cost** diterima sebagai metode harga modal/laba.

## 2. Actual Printer Hardware
Minta:
- merek,
- model,
- connection type,
- paper width.

Kedua hal ini tidak perlu menghentikan frontend/backend foundation development.

---

# Requirement Lock Recommendation

Setelah owner menyetujui metode HPP:

**Business requirement dapat dianggap locked untuk MVP**, sementara detail printer masuk sebagai technical integration task.

Tim dapat melanjutkan ke:

1. ERD / Domain Model
2. API Contract
3. Offline Technical Design
4. Hardware Spike
5. GitHub Epic & Issue Breakdown
6. Sprint 1 implementation
