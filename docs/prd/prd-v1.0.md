# Product Requirements Document (PRD) v1.0

## Retail POS & Inventory Management System

**Status:** Draft for Requirement Lock  
**Version:** 1.0  
**Current Phase:** Foundation + Requirement Validation  
**Primary Use Case:** Retail store / toko ATK sebagai implementasi awal  
**Product Direction:** Core retail/POS yang dapat dikembangkan untuk jenis toko retail lain

---

# 1. Ringkasan Produk

Sistem ini merupakan aplikasi Point of Sale (POS), pembelian, persediaan, supplier, dan pelaporan untuk operasional toko retail.

Implementasi pertama digunakan pada toko ATK. Sistem diharapkan membantu toko mengurangi kesalahan input transaksi, meningkatkan akurasi stok, mempermudah pencarian barang, memberikan informasi persediaan yang lebih baik, dan menyediakan laporan penjualan serta laba rugi.

Sistem juga harus tetap dapat mendukung proses transaksi ketika koneksi internet tidak tersedia.

---

# 2. Latar Belakang

Alur bisnis toko saat ini dimulai dari pembelian barang ke supplier sesuai kebutuhan persediaan. Jenis produk yang berbeda dapat berasal dari supplier yang berbeda.

Barang yang dibeli kemudian dimasukkan ke sistem sebagai stok dan selanjutnya dapat dijual kepada pelanggan.

Rata-rata transaksi sekitar 15 pelanggan per hari dan mayoritas pelanggan merupakan pelajar.

Penjualan saat ini hanya dilakukan melalui toko fisik.

Masalah operasional utama yang ditemukan antara lain:

- Kesalahan pemilihan barang ketika transaksi kasir.
- Kesalahan satuan ketika transaksi.
- Kesalahan transaksi dapat menyebabkan selisih stok dan nilai barang.
- Barang habis terkadang baru diketahui ketika pelanggan hendak membeli.
- Owner membutuhkan informasi barang yang paling sering dibeli ketika tidak berada di toko.
- Proses pencarian barang diharapkan semakin mudah dengan barcode.

---

# 3. Tujuan Produk

Sistem harus membantu toko untuk:

1. Memproses transaksi penjualan dengan cepat dan akurat.
2. Mengurangi kesalahan pemilihan produk dan satuan saat transaksi.
3. Menjaga informasi stok agar lebih akurat.
4. Mempermudah pencarian barang dengan barcode, kode barang, maupun nama barang.
5. Menyediakan informasi safety stock dan pergerakan barang.
6. Menyimpan histori pembelian dan harga supplier.
7. Menyediakan laporan penjualan dan laba rugi.
8. Mengidentifikasi user/karyawan yang melakukan transaksi penjualan.
9. Tetap memungkinkan transaksi ketika internet tidak tersedia.
10. Mendukung perangkat kasir yang sudah digunakan toko.

---

# 4. Ruang Lingkup Produk

Modul utama:

1. Authentication & User
2. Company Profile
3. Product Master
4. Supplier
5. Purchasing
6. Inventory
7. Point of Sale
8. Payment
9. Reporting
10. Hardware Integration
11. Offline & Recovery

---

# 5. User / Role

Role yang saat ini digunakan atau dibutuhkan:

- Kasir
- Manager
- Supervisor
- Akun utama / Owner

## 5.1 Identitas User

Setiap transaksi penjualan harus menyimpan identitas user/karyawan yang melakukan transaksi.

## 5.2 Hak Akses

Hal yang bersifat confidential seperti keuntungan toko harus memiliki pembatasan akses sesuai role.

Namun berdasarkan jawaban owner:

- Kasir boleh melihat harga modal dan laba.
- Kasir dapat mengubah harga, stok, atau membatalkan transaksi apabila mendapat arahan owner.
- Pembatalan/perubahan tidak membutuhkan approval akun utama di dalam sistem.
- Aktivitas yang wajib menyimpan nama user saat ini hanya transaksi penjualan.

> Catatan: aturan "sesuai arahan/persetujuan owner" masih membutuhkan definisi operasional yang lebih jelas. Lihat bagian Open Decisions.

---

# 6. Company Profile

Sistem harus menyediakan data perusahaan/toko minimal:

- Nama perusahaan/toko
- Alamat
- Nomor telepon
- Logo

Data ini dapat digunakan pada struk, laporan, dan identitas aplikasi.

---

# 7. Product Master

## 7.1 Data Produk

Produk minimal harus dapat memiliki:

- Kode item / SKU
- Barcode
- Nama produk
- Kategori
- Ukuran
- Varian
- Warna
- Satuan
- Harga modal
- Harga jual
- Stok
- Status aktif/nonaktif

## 7.2 Pencarian Produk

Produk harus dapat dicari menggunakan:

- Scan barcode
- Entry kode barang
- Entry nama barang

Jika barcode tidak dapat dibaca, transaksi harus tetap dapat dilanjutkan melalui entry kode barang.

## 7.3 Barcode

Owner menyatakan semua barang sudah memiliki barcode.

Namun pada bagian lain disebut bahwa tidak semua barang menggunakan barcode dan diharapkan seluruh barang menggunakan barcode.

Status requirement ini masih perlu dikonfirmasi.

## 7.4 Harga Produk

Toko hanya menggunakan harga eceran.

Tidak ada kebutuhan harga grosir atau harga khusus pelanggan pada tahap ini.

## 7.5 Multi-Satuan

Satu barang dapat dijual dalam beberapa satuan, misalnya PCS, pack, dus, atau satuan lain sesuai produk.

Harga jual ditentukan berdasarkan satuan.

Sistem harus mendukung konversi dan pengelolaan stok antar-satuan tanpa menyebabkan perbedaan stok.

Aturan konversi detail perlu disesuaikan dengan data produk.

---

# 8. Supplier

## 8.1 Data Supplier

Minimal data supplier:

- Kode supplier
- Nama supplier
- Alamat
- Kota
- Nomor telepon

## 8.2 Multi-Supplier Product

Satu barang dapat dibeli dari beberapa supplier.

## 8.3 Pemilihan Supplier

Dalam praktik toko, supplier dapat dipilih berdasarkan:

- Harga pembelian yang lebih murah.
- Supplier yang menyediakan produk dengan pergerakan penjualan yang sesuai kebutuhan toko.

Sistem perlu menyimpan relasi antara produk dan supplier.

## 8.4 Riwayat Harga Supplier

Harga supplier dapat berubah.

Sistem harus menyimpan histori harga pembelian produk dari setiap supplier.

---

# 9. Purchasing

## 9.1 Proses Pembelian

Proses pembelian saat ini dilakukan dengan mencatat barang yang akan dibeli.

Sebagian pemesanan dilakukan melalui WhatsApp agar barang disiapkan, kemudian pembayaran dilakukan di tempat supplier.

## 9.2 Penerimaan Barang

Barang yang datang dapat dicek oleh semua karyawan.

Saat barang diterima:

- Barang masuk harus menambah stok.
- Jumlah aktual yang diterima harus dicatat.
- Jika jumlah berbeda dengan pesanan/invoice, pencatatan mengikuti jumlah barang yang benar-benar diterima.

## 9.3 Retur/Revisi Pembelian

Jika terdapat kesalahan pembelian atau barang bermasalah, proses dapat dilakukan melalui retur atau revisi.

Detail workflow retur masih perlu didefinisikan.

## 9.4 Pembayaran Supplier

Pembelian ke supplier selalu menggunakan pembayaran tunai.

Tidak ada hutang/tempo supplier.

## 9.5 Biaya Operasional Pembelian

Ongkir atau biaya operasional saat pembelian dicatat terpisah dari harga modal barang.

## 9.6 Faktur Supplier

Invoice/faktur supplier perlu disimpan dalam sistem.

## 9.7 Riwayat Pembelian

Riwayat pembelian minimal harus menampilkan:

- Supplier
- Tanggal
- Produk
- Jumlah
- Harga pembelian

Informasi tambahan selain itu belum didefinisikan.

---

# 10. Inventory & Stock

## 10.1 Stok Produk

Sistem harus mencatat stok setiap produk.

## 10.2 Stock Opname

Toko melakukan stock opname sekitar satu kali dalam satu tahun secara serentak.

Jika stok fisik berbeda dengan stok sistem:

1. Selisih dicatat.
2. Stok di sistem direvisi agar sesuai dengan stok fisik.

## 10.3 Barang Rusak / Hilang

Barang rusak dan barang hilang harus dapat dicatat di dalam sistem.

Pencatatan tersebut harus mempengaruhi jumlah stok sesuai jenis transaksi stok.

## 10.4 Stock Adjustment

Jika stok sistem menunjukkan nol tetapi barang fisik masih tersedia, user dapat melakukan entry/adjustment stok untuk menambahkan jumlah stok.

## 10.5 Stock Movement

Sistem sebaiknya dapat membedakan perubahan stok berdasarkan sumbernya, minimal:

- Pembelian
- Penjualan
- Stock opname
- Barang rusak
- Barang hilang
- Adjustment
- Retur/revisi

---

# 11. Safety Stock & Inventory Intelligence

## 11.1 Safety Stock

Safety stock diharapkan menggunakan aturan tertentu.

Formula atau metode perhitungannya belum ditentukan.

## 11.2 Stock Alert

Sistem harus memberikan notifikasi ketika stok mendekati safety stock.

## 11.3 Moving Classification

Sistem perlu membantu mengidentifikasi barang:

- Slow moving
- Middle moving
- Fast moving

Tujuannya:

- Mengurangi overstock pada barang yang tidak laku.
- Mengurangi stock-out pada barang yang cepat laku.

Sistem perlu memberikan informasi/notifikasi berdasarkan klasifikasi tersebut.

Algoritma klasifikasi belum didefinisikan.

---

# 12. Point of Sale (POS)

## 12.1 Alur Transaksi

Alur utama:

1. Pelanggan datang ke toko.
2. Pelanggan memilih barang.
3. Pelanggan membawa barang ke kasir.
4. Kasir mencari/scan produk.
5. Produk dimasukkan ke transaksi.
6. Kasir menentukan jumlah/satuan.
7. Sistem menghitung total.
8. Pelanggan memilih metode pembayaran.
9. Pembayaran dilakukan.
10. Transaksi disimpan.
11. Stok dikurangi.
12. Struk dicetak.
13. Untuk pembayaran tunai, cash drawer otomatis terbuka.

## 12.2 Kesalahan Input

Sistem perlu mengurangi risiko kesalahan seperti:

- Pelanggan membeli 1 PCS tetapi kasir memasukkan 1 lusin.
- Pelanggan membeli satu produk tetapi kasir memilih produk lain.

UI POS harus membantu kasir membedakan produk, satuan, harga, dan jumlah.

## 12.3 Perubahan Harga

Owner adalah pihak yang menentukan perubahan harga.

Kasir/user dapat mengubah harga berdasarkan arahan/persetujuan owner.

Tidak ada mekanisme approval sistem yang dikonfirmasi saat ini.

## 12.4 Diskon

Owner adalah pihak yang boleh memberikan diskon atau menentukan perubahan harga.

Detail bentuk diskon belum didefinisikan.

## 12.5 Retur / Koreksi Transaksi

Jika transaksi salah:

- Barang dapat ditukar.
- Nilai transaksi disesuaikan.
- Struk kemudian dicetak kembali.

Jika pelanggan membatalkan setelah pembayaran atau kasir salah memasukkan jumlah barang, owner menginginkan proses revisi yang mudah.

Model transaksi koreksi/retur final belum ditentukan.

## 12.6 Reprint Struk

Sistem harus mendukung pencetakan kembali struk setelah transaksi dikoreksi.

---

# 13. Payment

Metode pembayaran:

- Tunai
- QRIS

## 13.1 Split Payment

Satu transaksi dapat menggunakan lebih dari satu metode pembayaran.

Contoh: sebagian tunai dan sebagian QRIS.

## 13.2 Tunai

Jika pembayaran tunai selesai:

- Transaksi disimpan.
- Struk dicetak.
- Cash drawer otomatis terbuka.

## 13.3 QRIS

Pembayaran QRIS masuk ke akun bank pemilik.

Tidak ada requirement integrasi otomatis ke provider QRIS yang dikonfirmasi dalam PRD saat ini.

## 13.4 Penjualan Tempo

Tidak ada penjualan secara utang/tempo.

---

# 14. Customer

Sistem tidak perlu menyimpan data pribadi pelanggan seperti nomor telepon dan alamat.

Tidak ada kebutuhan CRM/member yang dikonfirmasi.

---

# 15. Reporting

## 15.1 Laporan Penjualan

Sistem harus menyediakan:

- Laporan penjualan harian
- Laporan detail
- Laporan rekap

## 15.2 Laba per Transaksi

Sistem perlu menampilkan keuntungan dari masing-masing transaksi.

## 15.3 Laba Rugi

Laporan laba rugi harus mencakup:

- Laba kotor
- Laba bersih

Perhitungan harus sampai pada laba bersih usaha.

## 15.4 Periode Laporan

Laporan harus dapat ditampilkan berdasarkan periode tanggal yang dipilih.

## 15.5 Perbandingan Laporan

Owner membutuhkan perbandingan tanggal/bulan yang sama dengan tahun yang berbeda.

## 15.6 Export

Laporan harus dapat diekspor ke:

- Excel
- PDF

## 15.7 Pajak

Tidak ada PPN/pajak pada transaksi berdasarkan kebutuhan saat ini.

## 15.8 Laporan Tambahan

Owner menyatakan laporan yang sudah disebutkan dianggap cukup.

Namun kebutuhan inventory intelligence untuk slow/middle/fast moving tetap diperlukan pada modul persediaan.

---

# 16. Harga Modal & Profit

Jika harga beli supplier berubah, harga modal diharapkan menyesuaikan.

Namun metode perhitungan harga modal belum ditentukan.

Pilihan metode yang perlu diputuskan pada tahap implementasi antara lain:

- Last Purchase Price
- Weighted Average
- FIFO

PRD v1.0 tidak menetapkan metode tertentu sampai owner memberikan keputusan yang lebih spesifik.

---

# 17. Hardware Integration

## 17.1 Perangkat Kasir

Kasir menggunakan komputer Windows.

## 17.2 Barcode Scanner

Toko sudah menggunakan barcode scanner.

## 17.3 Barcode / Label Printer

Toko membutuhkan printer khusus untuk membuat barcode/label barang.

## 17.4 Receipt Printer

Printer struk tersedia/dibutuhkan untuk pencetakan transaksi.

Merek/model dan ukuran kertas belum dijawab.

Status: **TBD**

## 17.5 Cash Drawer

Cash drawer:

- Sudah tersedia.
- Terhubung langsung dengan komputer.
- Otomatis terbuka untuk transaksi tunai.
- Harus dapat dibuka secara manual.
- Hanya kasir yang boleh membuka cash drawer secara manual.

Pencatatan aktivitas manual cash drawer belum dikonfirmasi.

---

# 18. Offline Operation

## 18.1 Internet Mati

Transaksi harus tetap dapat dilakukan ketika internet mati.

Ini merupakan requirement wajib.

Sistem harus memiliki strategi agar proses POS dapat terus berjalan tanpa koneksi internet.

Detail mekanisme sinkronisasi belum menjadi bagian keputusan bisnis PRD dan perlu ditentukan dalam technical design.

## 18.2 Sinkronisasi

Setelah koneksi tersedia kembali, transaksi offline harus dapat disinkronkan tanpa kehilangan data atau membuat transaksi ganda.

Requirement ini merupakan konsekuensi teknis dari kebutuhan transaksi offline dan perlu divalidasi dalam technical architecture.

---

# 19. Failure Handling & Recovery

## 19.1 Listrik Mati

Jika listrik mati ketika transaksi berlangsung, perlu terdapat catatan khusus.

Detail bentuk catatan belum ditentukan.

## 19.2 Printer Tidak Berfungsi

Kerusakan printer tidak boleh menghalangi transaksi.

Transaksi tetap harus dapat diselesaikan.

Struk dapat dicetak kembali ketika printer tersedia.

## 19.3 Kerusakan Komputer

Jika komputer kasir mengalami kerusakan, data transaksi sebelumnya harus dapat dipulihkan.

Sistem membutuhkan mekanisme backup/recovery yang memadai.

---

# 20. Business Rules

## BR-001 — Harga Transaksi

Jika harga pada rak berbeda dengan harga di sistem, harga yang digunakan adalah harga di sistem.

## BR-002 — Payment

Metode pembayaran hanya tunai dan QRIS.

Split payment tunai + QRIS diperbolehkan.

## BR-003 — Customer Credit

Penjualan tempo/piutang pelanggan tidak digunakan.

## BR-004 — Supplier Credit

Pembelian tempo/hutang supplier tidak digunakan.

## BR-005 — Tax

Tidak ada PPN dalam transaksi.

## BR-006 — Product Unit

Harga jual produk ditentukan berdasarkan satuan.

## BR-007 — Supplier

Satu produk dapat memiliki beberapa supplier.

## BR-008 — Stock Receiving

Jumlah stok masuk mengikuti barang yang benar-benar diterima.

## BR-009 — Cash Drawer

Cash drawer otomatis terbuka pada pembayaran tunai.

Manual open hanya boleh dilakukan kasir.

## BR-010 — Offline Transaction

Transaksi harus dapat berjalan ketika internet mati.

---

# 21. Functional Requirements

## Authentication

### FR-AUTH-001
Sistem harus memungkinkan user login menggunakan akun masing-masing.

### FR-AUTH-002
Sistem harus mengetahui identitas user yang sedang aktif.

### FR-AUTH-003
Setiap transaksi penjualan harus menyimpan user yang melakukan transaksi.

## Product

### FR-PROD-001
Sistem harus memungkinkan pencarian produk berdasarkan barcode.

### FR-PROD-002
Sistem harus memungkinkan pencarian produk berdasarkan kode barang.

### FR-PROD-003
Sistem harus memungkinkan pencarian produk berdasarkan nama.

### FR-PROD-004
Sistem harus mendukung produk dengan kategori, ukuran, varian, dan warna.

### FR-PROD-005
Sistem harus mendukung lebih dari satu satuan untuk satu produk.

### FR-PROD-006
Setiap satuan dapat memiliki harga jual yang berbeda.

## Supplier

### FR-SUP-001
Sistem harus dapat menyimpan data supplier.

### FR-SUP-002
Satu produk harus dapat terhubung dengan beberapa supplier.

### FR-SUP-003
Sistem harus menyimpan riwayat harga produk dari supplier.

## Purchasing

### FR-PUR-001
Sistem harus dapat mencatat transaksi pembelian.

### FR-PUR-002
Pembelian harus mencatat supplier, tanggal, produk, quantity, dan harga.

### FR-PUR-003
Penerimaan barang harus menambah stok.

### FR-PUR-004
Jumlah penerimaan harus mengikuti barang yang benar-benar diterima.

### FR-PUR-005
Invoice/faktur supplier harus dapat disimpan.

## Inventory

### FR-INV-001
Sistem harus mencatat stok produk.

### FR-INV-002
Sistem harus mendukung stock adjustment.

### FR-INV-003
Sistem harus mendukung stock opname.

### FR-INV-004
Barang rusak harus dapat dicatat.

### FR-INV-005
Barang hilang harus dapat dicatat.

### FR-INV-006
Sistem harus memberikan notifikasi ketika stok mendekati safety stock.

### FR-INV-007
Sistem harus dapat mengidentifikasi produk slow, middle, dan fast moving.

## POS

### FR-POS-001
Kasir harus dapat menambahkan produk ke transaksi melalui scan barcode.

### FR-POS-002
Kasir harus dapat menambahkan produk melalui pencarian kode/nama.

### FR-POS-003
Kasir harus dapat memilih satuan produk.

### FR-POS-004
Sistem harus menghitung subtotal dan total secara otomatis.

### FR-POS-005
Sistem harus menyimpan transaksi beserta identitas kasir.

### FR-POS-006
Transaksi berhasil harus mengurangi stok.

### FR-POS-007
Transaksi harus dapat diselesaikan meskipun printer tidak berfungsi.

### FR-POS-008
Transaksi harus dapat dilakukan ketika internet mati.

## Payment

### FR-PAY-001
Sistem harus mendukung pembayaran tunai.

### FR-PAY-002
Sistem harus mendukung pembayaran QRIS.

### FR-PAY-003
Sistem harus mendukung split payment tunai + QRIS.

### FR-PAY-004
Pembayaran tunai berhasil harus memicu cash drawer terbuka otomatis.

## Reporting

### FR-REP-001
Sistem harus menyediakan laporan penjualan harian.

### FR-REP-002
Sistem harus menyediakan laporan detail transaksi.

### FR-REP-003
Sistem harus menyediakan laporan rekap.

### FR-REP-004
Sistem harus menghitung laba per transaksi.

### FR-REP-005
Sistem harus menyediakan laba kotor.

### FR-REP-006
Sistem harus menyediakan laba bersih.

### FR-REP-007
Laporan harus dapat difilter berdasarkan periode tanggal.

### FR-REP-008
Laporan harus dapat dibandingkan dengan periode sama pada tahun berbeda.

### FR-REP-009
Laporan harus dapat diekspor ke Excel.

### FR-REP-010
Laporan harus dapat diekspor ke PDF.

---

# 22. Non-Functional Requirements

## NFR-001 — Offline Availability

POS harus dapat tetap menjalankan transaksi ketika internet tidak tersedia.

## NFR-002 — Data Recovery

Data transaksi harus dapat dipulihkan jika perangkat kasir mengalami kerusakan.

## NFR-003 — Hardware Compatibility

Sistem harus mendukung perangkat kasir Windows dan peripheral toko yang digunakan.

## NFR-004 — Usability

POS harus membantu mengurangi kesalahan pemilihan produk, satuan, dan quantity.

## NFR-005 — Data Integrity

Transaksi penjualan dan perubahan stok harus menjaga konsistensi stok.

## NFR-006 — Auditability

Transaksi harus memiliki identitas user yang melakukan transaksi.

---

# 23. Acceptance Criteria Utama

## AC-001 — Product Search

Given kasir berada pada halaman POS  
When kasir scan barcode atau memasukkan kode/nama produk  
Then sistem menampilkan produk yang sesuai dan dapat ditambahkan ke transaksi.

## AC-002 — Multi Unit

Given produk memiliki beberapa satuan  
When kasir memilih satuan tertentu  
Then harga transaksi menggunakan harga dari satuan tersebut.

## AC-003 — Cash Payment

Given transaksi memiliki total pembayaran  
When kasir memilih pembayaran tunai dan menyelesaikan pembayaran  
Then transaksi tersimpan, stok berkurang, struk dapat dicetak, dan cash drawer terbuka.

## AC-004 — Split Payment

Given pelanggan membayar menggunakan tunai dan QRIS  
When total kedua pembayaran sama dengan total transaksi  
Then transaksi dapat diselesaikan dan masing-masing metode pembayaran tercatat.

## AC-005 — Offline

Given koneksi internet terputus  
When kasir melakukan transaksi  
Then transaksi tetap dapat diproses tanpa kehilangan data.

## AC-006 — Stock Adjustment

Given stok sistem berbeda dengan stok fisik  
When user melakukan adjustment  
Then stok sistem berubah sesuai quantity baru dan perubahan stok tercatat.

## AC-007 — Stock Alert

Given stok produk mendekati safety stock  
When kondisi safety stock terpenuhi  
Then sistem memberikan informasi/notifikasi kepada user terkait.

## AC-008 — Report Export

Given user membuka laporan  
When user memilih periode dan export Excel/PDF  
Then sistem menghasilkan file laporan sesuai periode tersebut.

---

# 24. Out of Scope / Tidak Dibutuhkan Saat Ini

Berdasarkan kebutuhan owner saat ini:

- Penjualan kredit/tempo pelanggan.
- Hutang/tempo supplier.
- Penyimpanan alamat dan nomor telepon pelanggan.
- PPN pada transaksi.
- Harga grosir.
- Marketplace integration.
- Penjualan melalui WhatsApp sebagai channel transaksi sistem.
- CRM/member system.

Jika kebutuhan berubah, item dapat masuk revisi PRD berikutnya.

---

# 25. Open Decisions / Requirement Gaps

Item berikut belum cukup jelas untuk dianggap final.

## OD-001 — Barcode

Terdapat jawaban yang berbeda:

- Disebut belum semua barang menggunakan barcode.
- Disebut semua barang sudah memiliki barcode.

Perlu konfirmasi kondisi sebenarnya.

## OD-002 — Harga Modal / HPP

"Modal menyesuaikan harga beli" belum menentukan metode.

Perlu memilih:

- Last Purchase Price
- Weighted Average
- FIFO
- Metode lain

## OD-003 — Safety Stock

Owner menginginkan safety stock berdasarkan aturan tertentu.

Belum diketahui formula, lead time, sales period, dan apakah perhitungan manual atau otomatis.

## OD-004 — Slow / Middle / Fast Moving

Klasifikasi dibutuhkan, tetapi periode evaluasi, threshold, dan formula belum ditentukan.

## OD-005 — Approval Perubahan Harga

Kasir dapat mengubah harga berdasarkan arahan/persetujuan owner, tetapi approval sistem tidak diperlukan.

Perlu ditentukan apakah persetujuan hanya verbal/offline, memakai PIN owner, permission khusus, atau tidak ada mekanisme teknis.

## OD-006 — Retur / Koreksi Transaksi

Owner menginginkan revisi transaksi yang mudah.

Belum ditentukan apakah transaksi lama diedit, dibuat return transaction, dibuat void + transaksi baru, atau dibuat adjustment.

## OD-007 — Printer Struk

Belum tersedia jawaban untuk:

- Merek/model printer.
- Ukuran kertas 58 mm / 80 mm.

## OD-008 — Manual Cash Drawer Audit

Hanya kasir boleh membuka cash drawer secara manual.

Belum diketahui apakah setiap manual open harus dicatat.

## OD-009 — Listrik Mati

Dibutuhkan "catatan khusus" jika listrik mati ketika transaksi berlangsung.

Bentuk catatan dan alurnya belum ditentukan.

## OD-010 — Informasi Tambahan Riwayat Pembelian

Owner belum memberikan jawaban untuk informasi tambahan yang ingin dilihat selain supplier, tanggal, barang, quantity, dan harga.

---

# 26. Technical Constraints / Project Decisions

Bagian ini berasal dari keputusan teknis project, bukan requirement operasional owner.

- Architecture: Decoupled Architecture
- Repository: Monorepo
- Backend: Laravel
- Frontend: React + TypeScript
- Realtime Backend: Laravel Reverb
- Realtime Frontend: Laravel Echo
- Authentication: Laravel Sanctum
- Queue/Cache: Redis
- Backend direction: Modular Monolith
- Frontend direction: SPA

Detail technical design offline, local storage/database, sync strategy, printer communication, dan cash drawer integration harus dibuat pada Technical Design Document terpisah.

---

# 27. Definition of Requirement Status

Setiap requirement berikutnya harus menggunakan salah satu status:

**Confirmed**  
Sudah dikonfirmasi oleh owner/client.

**Open Decision**  
Kebutuhan sudah ada tetapi cara/aturannya belum final.

**Technical Decision**  
Tidak memerlukan keputusan bisnis tetapi membutuhkan keputusan engineering.

**Out of Scope**  
Tidak dibutuhkan pada versi kebutuhan saat ini.

---

# 28. Next Step

Sebelum requirement dinyatakan locked untuk Sprint implementasi penuh:

1. Selesaikan Open Decisions OD-001 sampai OD-010.
2. Buat ERD berdasarkan requirement confirmed.
3. Buat API contract awal.
4. Buat technical design untuk offline transaction & sync.
5. Lakukan hardware spike untuk printer thermal, barcode scanner, barcode printer, dan cash drawer.
6. Breakdown requirement menjadi GitHub Issues frontend/backend.
7. Setelah keputusan kritis diselesaikan, ubah status dokumen menjadi **Approved / Requirement Locked**.

---

# 29. Changelog

## v1.0

- Menyusun hasil discovery menjadi formal PRD.
- Memisahkan confirmed requirements dan open decisions.
- Menambahkan functional requirements.
- Menambahkan business rules.
- Menambahkan non-functional requirements.
- Menambahkan acceptance criteria utama.
- Menandai area yang belum cukup jelas agar tidak diimplementasikan berdasarkan asumsi.
