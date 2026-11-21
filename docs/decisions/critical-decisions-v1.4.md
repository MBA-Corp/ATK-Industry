# Critical Decisions Resolution v1.3

**Project:** Retail POS & Inventory Management System  
**Status:** Draft Requirements Reconciliation (Revisi 1–3)  
**Purpose:** Menutup keputusan kritis setelah PRD v1.0 agar frontend/backend dapat mulai implementasi tanpa menebak business rule.

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

## Navigasi cepat

- [CD-004: Safety Stock & ROP](#cd-004--safety-stock--reorder-point-revisi-2)
- [CD-002: HPP & valuasi](#cd-002--hpp-periodik-vs-metode-valuasi-persediaan)
- [CD-017: Laba rugi](#cd-017--struktur-laporan-laba-rugi-periodik-revisi-1)
- [CD-018: Freight-in](#cd-018--klasifikasi-freight-in-vs-operating-expense-revisi-1)
- [CD-019: Rekonsiliasi](#cd-019--inventory-adjustment-dan-rekonsiliasi-keuangan)
- [Status & konfirmasi](#ringkasan-status)

## CD-001 — Barcode, Kode Produk, dan Penetapan Harga (Revisi 4)

### Bukti Owner dan Master Data

Owner menyatakan kode produk toko mengikuti nomor urut internal; beberapa produk memiliki barcode pabrik, lainnya perlu barcode buatan sistem. Barang baru dengan barcode pabrik dapat langsung dipindai untuk registrasi lalu **harga ditetapkan oleh toko**, bukan dibaca dari pola barcode.

Audit `uli.xlsx`: 2.447 produk, 638 kode barcode untuk 512 produk, 1.935 produk tanpa barcode, tanpa barcode ganda yang terdeteksi pada file. Ini menutup pertentangan requirement lama terkait ketersediaan barcode.

### Keputusan

**RESOLVED — berikut menjadi aturan bisnis wajib:**

1. `products.sku` wajib, unik, tetap mempertahankan kode historis seperti `6324-579`; pembuatan kode baru boleh dilakukan backend menggunakan sequence atomik berformat configurable.
2. `product_barcodes` adalah daftar terpisah (0..N barcode per produk), dapat dikaitkan dengan satuan penjualan tertentu. Simpan sebagai string dan enforce unique secara global.
3. Pencarian POS tetap mendukung barcode → SKU → nama. Barcode yang belum dikenal di modul onboarding membuka draft registrasi, **bukan** otomatis menciptakan sale, master record final, harga, atau stok.
4. Barcode pabrik dicatat sebagai `FACTORY`; barcode lokal `SYSTEM`; rekaman legacy yang sumbernya tidak pasti dapat memakai `LEGACY_UNVERIFIED`.
5. Untuk label internal gunakan **Code 128** berbasis SKU unik sebagai default. Jangan menerbitkan EAN/GTIN yang tampak sebagai kode pabrik tanpa hak/prefix yang berlaku. Perangkat PANDA dan media label belum diverifikasi melalui uji fisik.
6. Harga jual melekat pada `product_unit` dan harus ditetapkan manual oleh user dengan `product.change_price`; **barcode tidak berisi harga** dan tidak memutakhirkan harga secara otomatis.
7. Harga jual nol pada import adalah *unpriced*, bukan gratis. Satuan tersebut diblokir pada POS sampai pricing selesai.
8. Pendaftaran produk baru dan alokasi SKU internal **online-only untuk MVP** agar tidak terjadi bentrok sequence antarterminal offline.

### Keputusan Teknis Pendukung

- Alokasi SKU dan pencatatan barcode dilakukan dalam database transaction dengan unique constraints; pendaftaran melalui retry/idempotent tidak boleh membuat duplikasi.
- Barcode pabrik yang sudah terpetakan ke produk/satuan lain menghasilkan conflict `BARCODE_ALREADY_ASSIGNED`; tidak boleh menimpa mapping diam-diam.
- Barcode generated yang sama dapat dicetak ulang tanpa membuat entitas lain. Hasil pencetakan label membutuhkan pengujian media/printer aktual.
- Harga modal awal boleh bernilai pecahan presisi internal, sekalipun nilai uang API penjualan ditampilkan sebagai integer IDR.

## CD-002 — HPP Periodik vs Metode Valuasi Persediaan

### Masalah

Dokumen sebelumnya menggunakan *Moving Weighted Average* sebagai usulan metode HPP dan menyatakan ongkir pembelian di luar modal. Dokumen referensi terbaru ATK INSAN secara eksplisit menghitung **HPP periodik dari persediaan awal, pembelian bersih, dan persediaan akhir**, serta memasukkan **biaya angkut pembelian** ke pembelian bersih.

### Keputusan revisi 1

**RUMUS PERIODIK DIADOPSI DARI REFERENSI; METODE VALUASI MASIH PROPOSED DEFAULT.**

```text
Pembelian Bersih = Total Pembelian + Biaya Angkut Pembelian
                   - Retur Pembelian - Potongan Pembelian
HPP Periodik     = Persediaan Awal + Pembelian Bersih - Persediaan Akhir
```

Sistem membedakan **HPP laporan periode** dan **metode valuasi per unit**. *Moving Weighted Average* tetap direkomendasikan untuk menentukan nilai moneter persediaan dan cost snapshot per transaksi, tetapi **belum dianggap owner-approved** hanya karena formula HPP periodik sudah ada.

Jika moving average dipilih, alokasi biaya angkut pembelian/potongan menjadi bagian dari landed cost saat receive. Semua biaya pengadaan yang sudah masuk persediaan **tidak boleh dihitung lagi** sebagai operating expense.

### Ketentuan integritas

- Persediaan awal pada suatu periode = nilai persediaan akhir periode sebelumnya; gunakan valuasi *as of* historis, bukan kuantitas historis dikali biaya rata-rata saat ini.
- Harga satuan modal boleh memerlukan presisi desimal internal; total amount API tetap integer Rupiah.
- Retur pembelian dan penjualan mengubah nilai pada periode efektifnya, tanpa mengubah transaksi original secara destruktif.
- Stock adjustment, barang hilang/rusak, selisih opname, dan perolehan/penurunan stok lain perlu rekonsiliasi terpisah sehingga tidak salah diklasifikasikan sebagai HPP penjualan.

### Status validasi

**PENDING ACCOUNTING SIGN-OFF:** metode valuasi unit final, alokasi landed cost, pembulatan, retrospektif retur, dan pelaporan penyesuaian stok non-penjualan. Core reporting dapat diuji dengan dataset contoh tetapi belum diklaim laporan keuangan final.

## CD-003 — Negative Stock

### Masalah

Belum ada jawaban eksplisit apakah stok boleh negatif.

Namun owner menyatakan jika stok sistem nol tetapi fisik tersedia, stok perlu di-entry terlebih dahulu.

### Keputusan

**RESOLVED**

Penjualan **tidak boleh membuat stok menjadi negatif secara default**.

Jika stok sistem tidak mencukupi:
1. Kasir tidak dapat menyelesaikan item tersebut.
2. User harus melakukan stock adjustment terlebih dahulu.
3. Setelah stok diperbaiki, transaksi dapat dilanjutkan.

### Alasan

Tujuan utama sistem adalah mengurangi selisih stok.

Negative stock justru menyembunyikan masalah inventory.

## CD-004 — Safety Stock & Reorder Point (Revisi 2)

### Masalah

Metode sebelumnya memakai `average_daily_sales × (lead_time_days + safety_days)`, yang merupakan pendekatan threshold sederhana tetapi tidak menggunakan simpangan baku permintaan atau target tingkat layanan. Dokumen terbaru mengharuskan metode berbasis variasi permintaan.

### Keputusan Rumus

**ADOPTED FOR DRAFT IMPLEMENTATION — FORMULA RESOLVED, OPERATING PARAMETERS OPEN**

```text
SS_raw = Z × sigma_daily_demand × sqrt(lead_time_days)
SS_effective = ceil_to_base_unit_step(SS_raw)
ROP_raw = average_daily_demand × lead_time_days + SS_effective
ROP_effective = ceil_to_base_unit_step(ROP_raw)
```

- Rumus lengkap menggunakan `√L`. Baris rumus singkat pada bagian awal dokumen sumber tidak menampilkan faktor itu, tetapi perhitungan terperinci di halaman berikutnya memakainya.
- Contoh simulasi: `d=10`, `σd=4`, `L=4`, `Z=1,645` → SS 13,16 → **14**; ROP **54**; stok **50** → **Perlu pemesanan**.
- Target service level 95% dan nilai Z 1,645 adalah **contoh**, bukan konfigurasi wajib seluruh produk.
- Model standar ini mengasumsikan lead time deterministik dan variasi permintaan harian cukup independen. Model berbeda diperlukan jika lead time variabel atau permintaan sangat musiman.

### Data dan Konfigurasi

- `lead_time_days`, `target_service_level`, `demand_window_days`, `minimum_history_days` diatur lewat policy terotorisasi; backend menerjemahkan service level menjadi Z-score.
- Hitung rata-rata dan simpangan baku populasi atas hari kalender dalam jendela data, termasuk hari nol penjualan. Transaksi multi-unit dinormalisasi ke base unit.
- Dokumen contoh belum menentukan dampak retur, koreksi transaksi, dan sale offline yang terlambat sinkron pada *daily demand*. Atur perlakuan tersebut melalui keputusan tersendiri sebelum production; jangan otomatis mengurangi demand pada tanggal retur.
- `manual_safety_stock_base` dan `manual_reorder_point` boleh dipakai sebagai fallback jika histori belum mencukupi; gunakan catatan alasan/perubahan user pada audit log.
- `safety_days` lama **bukan** variabel metode baru. Hapus dari kontrak v1.2, sambil menyiapkan migrasi data policy jika versi sebelumnya sudah digunakan di lingkungan nyata.
- Jangan otomatis menetapkan angka demand nol ketika histori hilang. Balikan `insufficient_data` jika tidak ada fallback yang sah.

### Urutan Status Dashboard

| Kondisi | Status |
| --- | --- |
| `stock = 0` | Habis (`out_of_stock`) |
| `0 < stock ≤ SS` | Kritis (`critical`) |
| `SS < stock ≤ ROP` | Perlu pemesanan (`reorder_needed`) |
| `stock > ROP` | Stok mencukupi (`sufficient`) |
| Metode/fallback tidak tersedia | Data belum cukup (`insufficient_data`) |

Ketidakkonsistenan batas pada dokumen sumber (bagian tabel `< SS` vs ilustrasi `1–14` dengan SS 14) diselesaikan dengan aturan **`stock ≤ SS` = Kritis**. Untuk status pemesanan gunakan **`stock ≤ ROP`** selama stok belum habis.

### Batasan MVP dan Keputusan Lanjutan

- Model contoh membandingkan `product_inventory.quantity_base` (on-hand tercatat) dengan ROP. Belum memakai inventory position atau pesanan barang dalam perjalanan.
- Angka overstock tidak dapat diturunkan hanya dari SS atau ROP.
- Tetap perlu persetujuan target service level per kategori, lead time aktual, minimum histori, definisi data return, serta strategi demand musiman.

## CD-005 — Slow / Middle / Fast Moving

### Masalah

Owner membutuhkan klasifikasi slow, middle, dan fast moving tetapi threshold belum ditentukan.

### Keputusan

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

### Important

Threshold harus configurable di system settings.

Frontend/backend tidak boleh hardcode 20/30/50 di business logic permanen.

### Future

Rule dapat dikembangkan dengan:
- sales frequency,
- revenue,
- stock turnover,
- category-specific threshold.

## CD-006 — Perubahan Harga & Persetujuan Owner

### Masalah

Owner menyatakan:
- user dapat mengubah harga atas arahan/persetujuan owner;
- tidak membutuhkan approval akun utama di sistem.

### Keputusan

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

### Makna "persetujuan owner"

Persetujuan merupakan proses operasional di luar sistem.

Sistem hanya menegakkan permission yang sudah diberikan owner.

## CD-007 — Retur / Koreksi Transaksi

### Masalah

Owner menginginkan revisi transaksi yang mudah, tetapi mekanismenya belum ditentukan.

### Keputusan

**RESOLVED**

Transaksi yang sudah finalized **tidak boleh diedit atau dihapus secara destruktif**.

Gunakan model:

#### Sebelum pembayaran selesai

Cart masih dapat diedit bebas.

#### Setelah transaksi finalized

Gunakan:
- Return / correction transaction yang mereferensikan transaksi asli.
- Stock movement koreksi.
- Payment/refund adjustment jika diperlukan.
- Jika pelanggan mengganti barang, sistem mencatat retur lalu transaksi pengganti.

Original sale tetap tersimpan.

### Void

Void digunakan untuk transaksi yang dibatalkan sebelum settlement/finalization atau sesuai kondisi yang secara teknis aman.

### Reprint

Struk transaksi maupun dokumen koreksi dapat dicetak ulang.

### Alasan

- Audit trail terjaga.
- Laporan tidak berubah tanpa jejak.
- Mengurangi manipulasi data histori.

## CD-008 — Manual Cash Drawer

### Masalah

Owner menyatakan cash drawer dapat dibuka manual dan hanya kasir yang boleh membukanya.

Belum ditentukan apakah aktivitas perlu dicatat.

### Keputusan

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

## CD-009 — Receipt Printer (Revisi 3)

### Fakta dari Foto / Video

- Printer bagian depan berlogo **KASSEN**; label belakang menyatakan model **BT-P299**.
- Ukuran kertas **58 mm**, print speed **90 mm/s**, interface **USB/Bluetooth**, command **ESC/POS**, input **DC12V 2A**.
- Pada video, printer dapat mengeluarkan struk menggunakan POS lama; **belum** ada hasil uji driver/perintah dari SIPINTAR.

### Keputusan

**PARTIALLY RESOLVED — hardware identified; integration spike remains.**

Gunakan konfigurasi receipt **58 mm** sebagai prioritas utama pada instalasi toko saat ini. Support 80 mm dapat dipertahankan sebagai perluasan. **USB** menjadi rekomendasi jalur Windows kasir yang perlu diuji; Bluetooth bukan baseline production sebelum dibuktikan stabil.

`GET /api/v1/sales/{saleId}/receipt` menyediakan data struk, bukan perintah print. Gagal cetak tidak boleh membuat sale gagal setelah sale finalized; reprint dilakukan pada sale yang sama tanpa mengubah inventory/financial history.

### Pengujian Wajib

Uji font/karakter rupiah, QRIS/split payment, panjang nama barang, potong kertas jika tersedia, port USB/driver, event kehilangan daya, ESC/POS dari agent, dan reprint setelah restart.

## CD-010 — Offline Transaction

### Masalah

Owner mewajibkan transaksi tetap berjalan ketika internet mati.

### Keputusan

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

### Offline Data Minimum

Terminal harus memiliki cache lokal minimal:

- products
- SKU/barcode
- product units
- selling prices
- latest usable stock snapshot
- user/session authorization yang dibutuhkan untuk offline operation
- unsynced transactions

### Transaction Identifier

Setiap transaksi dibuat dengan client-generated UUID.

UUID yang sama digunakan saat sync untuk memastikan idempotency.

Backend harus menolak duplicate sync berdasarkan transaction UUID.

### Sync

Saat koneksi kembali:

1. Client mengirim queued transactions.
2. Backend memvalidasi UUID.
3. Backend memproses transaksi.
4. Client menandai transaksi sebagai synced.
5. Error/conflict masuk sync review state.

### Realtime

Laravel Reverb/Echo hanya digunakan ketika online.

Reverb **bukan** mekanisme offline sync.

## CD-011 — Offline Stock Conflict

### Masalah

Dua terminal dapat menjual stok yang sama ketika offline.

### Keputusan

**RESOLVED FOR MVP**

Saat offline:
- terminal menggunakan cached stock;
- tidak boleh menjual melebihi cached available stock lokal;
- transaksi disimpan sebagai pending sync.

Saat sync:
- backend tetap melakukan validasi stok;
- jika terjadi konflik, transaksi ditandai `sync_conflict` untuk review, bukan diam-diam dibuang.

### Catatan

Perfect stock consistency tidak mungkin dijamin antara beberapa terminal yang sama-sama offline tanpa local shared server.

MVP memilih traceability dan conflict handling dibanding menyembunyikan konflik.

## CD-012 — Listrik Mati / Crash Recovery

### Masalah

Owner meminta "catatan khusus" jika listrik mati ketika transaksi berlangsung.

### Keputusan

**RESOLVED**

POS harus memiliki draft persistence lokal.

Cart/draft transaction disimpan secara berkala ke local durable storage.

Jika aplikasi/browser/komputer restart:

- unfinished cart dapat dideteksi;
- user dapat memilih Resume atau Discard;
- sistem mencatat bahwa draft dipulihkan setelah interruption.

Transaksi belum dianggap sale sampai finalization berhasil.

Tidak perlu membuat transaksi penjualan palsu hanya untuk mencatat listrik mati.

## CD-013 — Hardware Bridge dan Perintah Cash Drawer (Revisi 3)

### Konfirmasi Owner

**Perilaku POS lama yang terkonfirmasi:** cash drawer otomatis terbuka setelah pembayaran tunai berhasil. **Keputusan owner terbaru (pembaruan v1.3):** pada SIPINTAR, cash drawer **juga harus otomatis terbuka untuk QRIS-only** serta split CASH+QRIS sesudah sale selesai. Ini persyaratan baru SIPINTAR; video POS lama belum membuktikan mekanisme QRIS-only. Foto menunjukkan laci CD-339 berkunci; foto referensi menampilkan RJ11, namun **jalur aktuator nyata belum diverifikasi**.

### Arah Engineering

**RESOLVED AS DESIGN DIRECTION; PHYSICAL INTEGRATION NOT YET TESTED.**

Gunakan lapisan abstraksi perangkat (`ReceiptPrinterService`, `CashDrawerService`, `BarcodeScannerAdapter`, dan adapter barcode-label jika dibutuhkan) agar React tidak bergantung pada driver/vendor.

- Local Windows agent/service di loopback adalah opsi utama yang diuji, bukan pilihan final sebelum spike berhasil.
- Koneksi ke agent wajib dibatasi (loopback, autentikasi, validasi origin/perintah, least privilege); jangan mengekspos perintah buka laci ke API publik atau kredensial mentah ke frontend.
- Jika scanner PANDA beroperasi sebagai **USB-HID keyboard wedge**, gunakan input React langsung; sifat ini masih **harus diuji**.
- Perangkat fisik tidak memerlukan backend realtime agar bisa bekerja saat internet mati.

### Sale and Hardware Command Separation

```text
(Online) backend sale commit / (Offline) local durable pending_sync sale
                       ↓
           enqueue local device command UUID
                       ↓
            print receipt (independent)
                       ↓
            drawer-open once for every finalized/durable sale
                       ↓
            local status: sent / failed / unknown
                       ↓
          audit event sync when available
```

- Perintah `open_auto` wajib berlaku **CASH-only, QRIS-only, dan split CASH+QRIS** setelah sale completed/durable; **maksimal satu trigger otomatis per sale**, bukan per baris pembayaran.
- QRIS yang baru ditampilkan, masih `pending`, gagal, atau belum diverifikasi **tidak boleh memicu open**. Tanpa integrasi payment gateway, kasir perlu memverifikasi keberhasilan QRIS sesuai SOP sebelum menyelesaikan sale.
- Retur/refund, reprint, dan membuka ulang histori transaksi **tidak otomatis memicu open** kecuali ada keputusan bisnis tambahan; manual open tetap berizin dan diaudit.
- Retry sync sale dengan `client_uuid` sama **tidak** boleh mengulangi print atau membuka cash drawer.
- Retry print adalah operasi terpisah (reprint). Drawer dengan hasil `unknown` tidak boleh otomatis dicoba ulang karena berisiko membuka dua kali.
- Jika hardware gagal setelah sale durable, sale dan stock movement **tetap valid**; kasir menerima pesan peringatan dan opsi penanganan perangkat.
- Log `command_sent` hanya membuktikan perintah disampaikan, **bukan** bahwa laci fisik terbuka. Sensor status fisik belum terkonfirmasi.
- Manual open **melalui aplikasi** perlu permission `cash_drawer.open_manual` dan audit; pembukaan dengan kunci fisik tanpa sensor tidak bisa direkam secara otomatis.

### Uji Lapangan Sebelum Production

Tentukan lokasi kabel RJ11/drawer kick port, pulse command ESC/POS yang aman, dukungan USB vendor driver, hasil print 58 mm, response timeout, mode scanner, offline, crash/restart, dan failure injection. **Uji trigger untuk tunai, QRIS-only, split, QRIS pending/gagal, dan retry sync** secara terpisah. Tidak ada klaim dukungan otomatis di SIPINTAR sebelum diuji.

## CD-014 — Purchase History Extra Information

### Masalah

Owner belum memberikan informasi tambahan yang ingin dilihat di histori pembelian.

### Keputusan

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

## CD-015 — Supplier Selection

### Masalah

Owner memilih supplier berdasarkan harga termurah dan pertimbangan pergerakan barang.

### Keputusan

**RESOLVED**

Sistem tidak memilih supplier otomatis pada MVP.

Ketika melakukan pembelian, sistem menampilkan informasi pendukung:

- supplier terkait produk;
- latest purchase price;
- historical prices;
- latest purchase date.

User tetap menentukan supplier.

Future recommendation engine dapat ditambahkan kemudian.

## CD-016 — Final Sale State

### Keputusan

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

## CD-017 — Struktur Laporan Laba Rugi Periodik (Revisi 1)

**ADOPTED FROM NEW REFERENCE — ACCOUNTING REVIEW REQUIRED FOR EDGE CASES.**

Urutan laporan wajib:

```text
Total Penjualan
- Retur Penjualan
- Potongan Penjualan
= Penjualan Bersih

Persediaan Awal
+ Total Pembelian
+ Biaya Angkut Pembelian
- Retur Pembelian
- Potongan Pembelian
= Barang Tersedia untuk Dijual
- Persediaan Akhir
= HPP Periodik

Penjualan Bersih - HPP Periodik = Laba Kotor
Laba Kotor - Beban Operasional = Laba Bersih
```

**Golden test, Januari 2026:**

| Indikator        | Target (Rp) |
| ---------------- | ----------: |
| Pembelian bersih | 5.050.000   |
| HPP              | 10.050.000  |
| Penjualan bersih | 15.700.000  |
| Laba kotor       | 5.650.000   |
| Laba bersih      | 3.950.000   |

Revenue dan profit di tingkat item/transaksi hanya metrik operasional; HPP transaksi tidak boleh dikurangkan **kedua kali** setelah HPP periodik dihitung.

## CD-018 — Klasifikasi Freight-in vs Operating Expense (Revisi 1)

**ADOPTED FROM NEW REFERENCE.**

Biaya angkut **untuk membawa persediaan dari supplier** menjadi biaya perolehan barang / bagian pembelian bersih; biaya seperti gaji, listrik, internet, atau transportasi non-pengadaan termasuk beban operasional yang sesuai. Sistem harus mencegah pencatatan ganda. Data purchase harus memuat freight-in dan potongan pembelian; data cost valuation per produk harus mendukung alokasi.

## CD-019 — Inventory Adjustment dan Rekonsiliasi Keuangan

**OPEN FINANCIAL DECISION.**

Rumus HPP dari dokumen contoh mengasumsikan persediaan bergerak melalui pembelian dan penjualan/retur. Jika terdapat stok rusak/hilang, koreksi opname, stock adjustment, atau koreksi nilai, persediaan berkurang/bertambah bukan karena penjualan. Nilai non-penjualan ini wajib dipisahkan dan direkonsiliasi sebelum HPP dilaporkan sebagai nilai barang terjual. Selama kebijakan klasifikasi belum disetujui, API laporan memberikan `reconciliation_status=requires_review` dan informasi peringatan. Jangan menyembunyikan variance dengan otomatis membebankannya dua kali.


## CD-020 — Master Data Legacy `uli.xlsx` dan Migrasi Awal (Revisi 4)

**RESOLVED untuk rancangan migrasi; persetujuan nilai transaksi/opening inventory masih diperlukan.**

- File memiliki 2.447 SKU unik dan 41 kolom pada satu sheet `Satuan`; jangan menganggapnya sudah siap langsung untuk penjualan production.
- `KODEITEM` adalah unique internal SKU, bukan otomatis GTIN/barcode pabrik. Barcode per satuan memakai `BARCODE1..4` terkait slot `SATUAN1..4` yang harus diverifikasi pada contoh nyata.
- 1.431 item belum memiliki harga jual positif; 1.237 di antaranya memiliki stok positif. Harga nol merupakan *review queue*; tidak boleh dijual sebagai Rp0.
- 2.370 nilai `STOKMIN=0` tidak digunakan sebagai hasil SS/ROP; parameter v1.2 tetap dihitung dari riwayat permintaan + lead time + service level.
- 256 `HARGAPOKOK1` mengandung pecahan; 5 pasangan HPP-konversi berbeda dari perkalian numerik sederhana. Nilai modal historis tidak boleh dibulatkan tanpa kebijakan akuntansi.
- `STOK` diperlakukan kandidat opening balance base-unit dengan purchase/opening valuation **yang harus dikonfirmasi**. Import katalog saja tidak memodifikasi stok.
- Lakukan dry run, dedup SKU/barcode, normalisasi kategori/satuan, approval perubahan, audit batch, dan rekonsiliasi nilai persediaan.
- Nilai `SUPPLIER`, `DEPT`, `TIPE`, `SERIAL`, dan `KONSINYASI` disimpan sebagai data sumber sampai arti/relasinya dipastikan.

## CD-021 — Harga Jual per Unit dan Otorisasi (Revisi 4)

**RESOLVED.**

- Barcode pabrik mengidentifikasi produk/satuan; **harga jual tidak diturunkan dari barcode**.
- Setelah scan barang baru, user berwenang mengisi harga per satuan sebelum satuan dijual.
- Perubahan harga jual mendapat audit (`old_price`, `new_price`, `user`, `time`, optional reason).
- Tidak ada approval elektronik owner pada MVP (mengikuti CD-006), tetapi backend wajib memeriksa permission `product.change_price`.
- Harga modal untuk penilaian HPP (v1.1) dan harga jual per satuan tidak boleh disamakan.

## Ringkasan Status

| ID     | Decision                           | Status                                            |
| ------ | ---------------------------------- | ------------------------------------------------- |
| CD-001 | Barcode fallback                   | Resolved                                          |
| CD-002 | HPP periodik + metode valuasi      | Rumus adopted; valuasi belum disetujui accounting |
| CD-003 | Negative stock                     | Resolved                                          |
| CD-004 | Safety Stock/ROP statistik (rev 2) | Rumus adopted; parameter operasional perlu review |
| CD-005 | Moving classification              | Resolved/configurable                             |
| CD-006 | Price approval                     | Resolved                                          |
| CD-007 | Return/correction                  | Resolved                                          |
| CD-008 | Manual cash drawer audit           | Resolved                                          |
| CD-009 | Receipt printer                    | Hardware identified; SIPINTAR integration requires spike |
| CD-010 | Offline architecture               | Resolved                                          |
| CD-011 | Offline stock conflict             | Resolved for MVP                                  |
| CD-012 | Crash/power recovery               | Resolved                                          |
| CD-013 | Hardware abstraction               | Design direction accepted; physical integration untested |
| CD-014 | Purchase history                   | Resolved for MVP                                  |
| CD-015 | Supplier selection                 | Resolved                                          |
| CD-016 | Sale state model                   | Resolved                                          |
| CD-017 | Laporan laba rugi periodik         | Adopted from new reference                        |
| CD-018 | Freight-in sebagai biaya perolehan | Adopted from new reference                        |
| CD-019 | Koreksi nilai stok non-penjualan   | Open financial decision                           |

## Client Confirmation Masih Diperlukan

Setelah keputusan di atas, hanya dua hal yang sebaiknya dianggap blocker sebelum production/financial go-live:

### 1. HPP / Accounting

Rumus **HPP periodik, penjualan bersih, laba kotor, dan laba bersih** sudah mengikuti dokumen contoh baru. Konfirmasi yang **masih diperlukan**:

- metode valuasi persediaan per unit (usulan *Moving Weighted Average*);
- cara pembagian freight-in dan potongan pembelian ke setiap barang;
- penanganan selisih stok/barang rusak/hilang sebagai komponen keuangan;
- prosedur pencatatan modal awal, penyesuaian periode terdahulu, dan retur lintas-periode.

Catatan: **tidak** cukup hanya meminta persetujuan moving average tanpa memeriksa kebijakan tersebut.

### 2. Actual Printer Hardware

**Foto sudah memenuhi identifikasi dasar:** KASSEN/BT-P299, 58 mm, USB/Bluetooth, ESC/POS; scanner PANDA USB dan drawer CD-339 juga terlihat. Owner mengonfirmasi cash drawer POS lama **terbuka otomatis** untuk transaksi tunai.

**Masih membutuhkan uji lapangan:** driver/port USB dan jalur drawer sebenarnya, jenis kabel, command ESC/POS, scanner HID, serta ketahanan offline/crash. Tidak lagi perlu meminta merek/ukuran printer dari owner.

Kedua hal ini tidak perlu menghentikan frontend/backend foundation development.

## Requirement Lock Recommendation

Setelah owner menyetujui metode valuasi dan kebijakan rekonsiliasi persediaan:

**Business requirement dapat dianggap locked untuk MVP**, sementara detail printer masuk sebagai technical integration task.

Tim dapat melanjutkan ke:

1. ERD / Domain Model
2. API Contract
3. Offline Technical Design
4. Hardware Spike
5. GitHub Epic & Issue Breakdown
6. Sprint 1 implementation

## Changelog v1.4 — Master Data dan Barcode

- CD-001 diperbarui menggunakan konfirmasi owner dan data aktual.
- CD-020 dan CD-021 mendefinisikan staging migrasi, barcode, identitas produk dan otorisasi harga jual.
- Keputusan HPP v1.1, Safety Stock v1.2, dan cash drawer semua metode pembayaran v1.3 tetap berlaku.

## Pembaruan v1.3 — QRIS Membuka Cash Drawer

- Menetapkan keputusan owner bahwa sale QRIS-only yang sudah berhasil/finalized **juga wajib membuka cash drawer otomatis**.
- Memperbarui CD-013: trigger per completed sale untuk CASH, QRIS, dan split; QRIS pending/gagal tidak membuka.
- Mempertahankan aturan satu trigger per sale, anti-duplikasi, manual permission, agent keamanan lokal, dan failure recovery.
- Rumus HPP (v1.1) dan Safety Stock/ROP (v1.2) tidak berubah.

## Changelog v1.3

- Konfirmasi perangkat aktual dari foto/video, termasuk label printer BT-P299 58 mm ESC/POS dan scanner PANDA USB.
- Owner mengonfirmasi drawer otomatis pada pembayaran tunai POS lama.
- Pemisahan durable sale dari hardware command, peraturan CASH/split/QRIS, retry/reprint, audit, keamanan agent, dan status hardware tanpa sensor.
- Uji lapangan tetap wajib sebelum menyatakan kompatibilitas SIPINTAR.

## Changelog v1.2

- Merevisi CD-004 sesuai Safety Stock statistik `Z × σd × √L` dan ROP `(d × L) + SS`.
- Menetapkan boundary status, fallback data, kebutuhan parameter, asumsi model, dan golden test 14/54/50.
- Mencatat ketidakkonsistenan rumus/batas pada dokumen contoh dan status parameter yang belum dikonfirmasi.
- Menjaga seluruh keputusan HPP dan laba rugi revisi 1.

## Changelog v1.1

- Mengadopsi rumus laporan periodik dari contoh ATK INSAN.
- Merevisi CD-002 dan menambahkan CD-017 s.d. CD-019.
- Mengoreksi freight-in, test angka acuan, dan daftar persetujuan accounting.

