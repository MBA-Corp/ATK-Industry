# Product Requirements Document (PRD) v1.4

**Retail POS & Inventory Management System**

**Status:** Draft for Inventory, Financial & Hardware Integration Review (Revisi 1–3)  
**Version:** 1.4  
**Current Phase:** Foundation + Requirement Validation  
**Primary Use Case:** Retail Toko ATK INSAN, meliputi ATK dan kebutuhan sekolah lain (seragam, pramuka, sepatu, kaos kaki, tas) sebagai implementasi awal  
**Product Direction:** Core retail/POS yang dapat dikembangkan untuk jenis toko retail lain

## Navigasi cepat

- [9. Purchasing](#9-purchasing)
- [11. Safety Stock & ROP](#11-safety-stock--inventory-intelligence)
- [15. Reporting](#15-reporting)
- [16. Harga Modal & HPP](#16-harga-modal-valuasi-persediaan--hpp)
- [17. Hardware Integration](#17-hardware-integration-revisi-3-dan-4)
- [20. Business Rules](#20-business-rules)
- [23. Acceptance Criteria](#23-acceptance-criteria-utama)
- [25. Open Decisions](#25-open-decisions--requirement-gaps)

## 1. Ringkasan Produk

Sistem ini merupakan aplikasi Point of Sale (POS), pembelian, persediaan, supplier, dan pelaporan untuk operasional toko retail.

Implementasi pertama digunakan pada toko ATK. Sistem diharapkan membantu toko mengurangi kesalahan input transaksi, meningkatkan akurasi stok, mempermudah pencarian barang, memberikan informasi persediaan yang lebih baik, dan menyediakan laporan penjualan serta laba rugi.

Sistem juga harus tetap dapat mendukung proses transaksi ketika koneksi internet tidak tersedia.

## 2. Latar Belakang

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

## 3. Tujuan Produk

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

## 4. Ruang Lingkup Produk

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

## 5. User / Role

Role yang saat ini digunakan atau dibutuhkan:

- Kasir
- Manager
- Supervisor
- Akun utama / Owner

### 5.1 Identitas User

Setiap transaksi penjualan harus menyimpan identitas user/karyawan yang melakukan transaksi.

### 5.2 Hak Akses

Hal yang bersifat confidential seperti keuntungan toko harus memiliki pembatasan akses sesuai role.

Namun berdasarkan jawaban owner:

- Kasir boleh melihat harga modal dan laba.
- Kasir dapat mengubah harga, stok, atau membatalkan transaksi apabila mendapat arahan owner.
- Pembatalan/perubahan tidak membutuhkan approval akun utama di dalam sistem.
- Aktivitas yang wajib menyimpan nama user saat ini hanya transaksi penjualan.

> Catatan: aturan "sesuai arahan/persetujuan owner" masih membutuhkan definisi operasional yang lebih jelas. Lihat bagian Open Decisions.

## 6. Company Profile

Sistem harus menyediakan data perusahaan/toko minimal:

- Nama perusahaan/toko
- Alamat
- Nomor telepon
- Logo

Data ini dapat digunakan pada struk, laporan, dan identitas aplikasi.

## 7. Product Master

### 7.1 Data Produk

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

### 7.2 Pencarian Produk

Produk harus dapat dicari menggunakan:

- Scan barcode
- Entry kode barang
- Entry nama barang

Jika barcode tidak dapat dibaca, transaksi harus tetap dapat dilanjutkan melalui entry kode barang.

### 7.3 Barcode

**RESOLVED berdasarkan jawaban owner dan master data `uli.xlsx`.** Sebagian produk memakai barcode pabrik; sebagian tidak memiliki barcode pabrik dan dapat dibuatkan barcode internal oleh sistem. Kode item internal tetap tersedia untuk pencarian manual. Barcode pabrik tidak memuat harga jual; harga ditetapkan pada master satuan produk oleh user yang berwenang.

Scan barcode yang sudah dikenali harus menemukan produk dan satuan terkait. Scan barcode baru yang belum dikenal harus membuka alur *registrasi produk baru* (draft), tidak otomatis menciptakan produk atau menambahkan stok sebelum divalidasi.

### 7.4 Harga Produk

Toko hanya menggunakan harga eceran.

Tidak ada kebutuhan harga grosir atau harga khusus pelanggan pada tahap ini.

### 7.5 Multi-Satuan

Satu barang dapat dijual dalam beberapa satuan, misalnya PCS, pack, dus, atau satuan lain sesuai produk.

Harga jual ditentukan berdasarkan satuan.

Sistem harus mendukung konversi dan pengelolaan stok antar-satuan tanpa menyebabkan perbedaan stok.

Aturan konversi detail perlu disesuaikan dengan data produk.

### 7.6 Identitas Produk, Barcode Pabrik, dan Barcode Sistem (Revisi 4)

- `KODEITEM` historis dari owner menjadi `products.sku` dan **tidak boleh diubah saat import**. Semua 2.447 kode tampak menggunakan pola `6324-<angka>`; pola itu contoh data legacy, bukan asumsi nomor global untuk semua toko.
- Produk baru dapat memperoleh SKU berurutan secara **atomik di server**, dengan format/prefix dikonfigurasi. Import lama dan pembuatan baru harus menggunakan satu namespace SKU unik. Tidak boleh memakai `MAX(sku)+1` tanpa penguncian/sequence.
- `product_barcodes` mendukung **banyak barcode per produk**, terikat ke satu `product_unit` jika satuannya diketahui: `FACTORY`, `SYSTEM`, atau `LEGACY_UNVERIFIED` (sumber historis belum dipastikan).
- Untuk barang tanpa barcode pabrik, barcode internal disarankan menggunakan **Code 128 dari SKU internal**, bukan menciptakan EAN-13/GTIN pabrik yang tidak dimiliki toko. Pastikan scanner PANDA dapat membaca Code 128 lewat uji fisik.
- Kode/barcode disimpan sebagai **string**, mempertahankan nol di depan, huruf, dan tanda hubung. Barcode yang sama tidak boleh menunjuk ke dua produk atau dua satuan berbeda.
- Satu SKU dapat memiliki beberapa satuan dengan barcode masing-masing. Scan harus mengembalikan satuan yang benar; jika mapping ambigu/unknown, kasir harus memilih atau mengonfirmasi.

### 7.7 Pendaftaran Produk Baru lewat Scan Barcode Pabrik

1. User membuka modul produk/penerimaan barang dan memindai barcode.
2. Jika barcode sudah terdaftar, sistem membuka detail produk serta harga **per satuan** untuk ditinjau; tidak membuat SKU duplikat.
3. Jika barcode belum dikenal, sistem membuka **draft pendaftaran produk** dengan barcode terisi; user melengkapi nama, kategori, merek, satuan dasar, konversi, harga jual, dan informasi pembelian.
4. Sistem menyimpan produk dan mapping barcode/satuan secara atomik; pemilihan harga dilakukan manual berdasarkan otorisasi, bukan dibaca dari barcode.
5. Persediaan bertambah **hanya melalui purchase receive atau opening balance/adjustment** yang diaudit; menyimpan master produk tidak mengubah stok.
6. Jika barcode belum dikenali saat **offline**, transaksi tidak menciptakan produk baru otomatis; pendaftaran produk memerlukan koneksi server pada MVP.

### 7.8 Harga Jual dan Kondisi Belum Ditetapkan

- `HARGAJUAL1..4` dipetakan ke `product_units.selling_price` sesuai `SATUAN1..4`, bukan ke `products` langsung.
- Harga modal pembelian (`HARGAPOKOK1..4`) dan harga jual merupakan domain berbeda. Scan barcode pabrik **tidak** mengubah harga modal atau harga jual secara otomatis.
- Harga jual bernilai **0** pada data impor dianggap `UNPRICED / PERLU PENETAPAN HARGA`, bukan diskon 100% atau produk gratis. Item dengan satuan harga belum valid **tidak boleh dijual** sampai harga ditetapkan; penyimpanan draft/master untuk migrasi diperbolehkan.
- Penetapan/perubahan harga jual membutuhkan permission `product.change_price`, audit nilai sebelum/sesudah, dan alasan opsional.
- Informasi harga dari spreadsheet bersifat nilai migrasi awal yang perlu divalidasi owner, bukan jaminan nilai retail terkini.

### 7.9 Import Master Data Awal `uli.xlsx`

| Kolom sumber | Mapping target / aturan |
| --- | --- |
| `KODEITEM`, `NAMAITEM` | `products.sku`, `products.name` |
| `JENIS`, `MEREK` | Kategori dan label merek; normalisasi teks sebelum deduplikasi |
| `SATUAN1..4`, `KONVERSI1..4` | `product_units`; satuan pertama menjadi basis jika konversinya 1 dan sudah divalidasi |
| `BARCODE1..4` | `product_barcodes` untuk slot satuan terkait; **verifikasi mapping** saat import |
| `HARGAJUAL1..4` | Harga jual per satuan; angka 0 = perlu penetapan harga |
| `HARGAPOKOK1..4` | Harga pokok historis calon nilai opening inventory; precision internal jangan dipotong diam-diam |
| `STOK` | Opening balance melalui inventory ledger **setelah validasi unit dasar** |
| `STOKMIN` | Threshold legacy, **bukan** Safety Stock/ROP statistik v1.2 |
| `SUPPLIER` | Mapping jika kode supplier tersedia; jangan otomatis menciptakan relasi palsu |
| `RAK`, `DEPT`, `TIPE`, `SERIAL`, `KONSINYASI`, `POIN*`, `KOMISISALES*`, `KETERANGAN` | Simpan pada arsip/staging asal; pindahkan ke domain final hanya jika semantik dikonfirmasi |

Import memakai **preview/dry run → laporan error/warning → persetujuan → commit atomik per batch**, dengan penyimpanan original file, identitas batch dan status baris. Nilai stok tidak boleh diubah melalui edit master produk. Lakukan snapshot sebelum migrasi dan verifikasi total stok/value dengan laporan pembuka.

### 7.10 Data Quality Gate sebelum Go-Live

Audit terhadap master data kiriman owner menunjukkan: **2.447 SKU unik; 512 produk berbarcode (638 barcode); 1.935 produk tanpa barcode; 1.093 produk multi-satuan; 1.431 produk tidak mempunyai harga jual positif pada semua satuan; 1.237 di antaranya memiliki stok positif; 2.370 produk dengan `STOKMIN=0`; 5 pasangan harga pokok-konversi perlu pemeriksaan; 256 produk memiliki harga pokok unit dasar pecahan.**

Ini **bukan** berarti master data tidak dapat diimpor. Data bermasalah masuk antrian validasi; transaksi tidak boleh menjual satuan dengan harga yang belum sah. Jangan menghasilkan barcode internal massal dan menganggapnya sudah ditempel di barang fisik: catat status pencetakan/pemasangan label terpisah dari pencatatan barcode.

### 7.11 Pembuatan dan Pencetakan Barcode Internal

Sistem menyediakan aksi *Generate Barcode* untuk produk tanpa barcode pabrik dan *Print Label* untuk label produk. File label dapat dibuat sebagai PDF siap cetak; dukungan printer label aktual **belum diverifikasi**. Printer struk KASSEN 58 mm tidak otomatis dianggap kompatibel dengan stiker label. Pencetakan ulang harus menghasilkan barcode yang sama dan tidak membuat identitas baru.

## 8. Supplier

### 8.1 Data Supplier

Minimal data supplier:

- Kode supplier
- Nama supplier
- Alamat
- Kota
- Nomor telepon

### 8.2 Multi-Supplier Product

Satu barang dapat dibeli dari beberapa supplier.

### 8.3 Pemilihan Supplier

Dalam praktik toko, supplier dapat dipilih berdasarkan:

- Harga pembelian yang lebih murah.
- Supplier yang menyediakan produk dengan pergerakan penjualan yang sesuai kebutuhan toko.

Sistem perlu menyimpan relasi antara produk dan supplier.

### 8.4 Riwayat Harga Supplier

Harga supplier dapat berubah.

Sistem harus menyimpan histori harga pembelian produk dari setiap supplier.

## 9. Purchasing

### 9.1 Proses Pembelian

Proses pembelian saat ini dilakukan dengan mencatat barang yang akan dibeli.

Sebagian pemesanan dilakukan melalui WhatsApp agar barang disiapkan, kemudian pembayaran dilakukan di tempat supplier.

### 9.2 Penerimaan Barang

Barang yang datang dapat dicek oleh semua karyawan.

Saat barang diterima:

- Barang masuk harus menambah stok.
- Jumlah aktual yang diterima harus dicatat.
- Jika jumlah berbeda dengan pesanan/invoice, pencatatan mengikuti jumlah barang yang benar-benar diterima.

### 9.3 Retur/Revisi Pembelian

Jika terdapat kesalahan pembelian atau barang bermasalah, proses dapat dilakukan melalui retur atau revisi.

Detail workflow retur masih perlu didefinisikan.

### 9.4 Pembayaran Supplier

Pembelian ke supplier selalu menggunakan pembayaran tunai.

Tidak ada hutang/tempo supplier.

### 9.5 Biaya Angkut Pembelian dan Potongan Pembelian

Berdasarkan dokumen *CONTOH KASUS SERTA RUMUS HPP & LAP LABA RUGI*, biaya angkut **untuk memperoleh barang dari pemasok ke toko** adalah bagian dari **pembelian bersih / biaya perolehan persediaan**, bukan beban operasional periode berjalan.

- Sistem mencatat `freight_in_amount` (biaya angkut pembelian) pada pembelian yang diterima.
- Sistem mencatat `purchase_discount_amount` (potongan pembelian) yang menurunkan nilai perolehan barang.
- Retur pembelian mengurangi pembelian bersih dan nilai persediaan sesuai periode efektif retur.
- Biaya transportasi **non-pengadaan**, gaji, listrik, dan internet tetap menjadi beban operasional bila relevan.
- Sistem harus mencegah **double counting**: biaya angkut pengadaan tidak boleh sekaligus dijumlahkan di `expenses`.
- Untuk penilaian persediaan per produk, biaya angkut/potongan tingkat invoice dialokasikan ke item pembelian secara deterministik (usulan: proporsional berdasarkan nilai barang sebelum diskon; pembulatan Rupiah dan sisa dialokasikan agar total tepat).

Rumus pembelian bersih periode:

`Pembelian Bersih = Total Pembelian + Biaya Angkut Pembelian − Retur Pembelian − Potongan Pembelian`

Nilai pembelian di laporan menggunakan **barang yang benar-benar diterima** pada periode laporan, bukan draft purchase atau sekadar tanggal invoice. Mekanisme final alokasi dan retur yang terjadi setelah periode awal perlu dikonfirmasi saat accounting sign-off.

### 9.6 Faktur Supplier

Invoice/faktur supplier perlu disimpan dalam sistem.

### 9.7 Riwayat Pembelian

Riwayat pembelian minimal harus menampilkan:

- Supplier
- Tanggal
- Produk
- Jumlah
- Harga pembelian

Informasi tambahan selain itu belum didefinisikan.

## 10. Inventory & Stock

### 10.1 Stok Produk

Sistem harus mencatat stok setiap produk.

### 10.2 Stock Opname

Toko melakukan stock opname sekitar satu kali dalam satu tahun secara serentak.

Jika stok fisik berbeda dengan stok sistem:

1. Selisih dicatat.
2. Stok di sistem direvisi agar sesuai dengan stok fisik.

### 10.3 Barang Rusak / Hilang

Barang rusak dan barang hilang harus dapat dicatat di dalam sistem.

Pencatatan tersebut harus mempengaruhi jumlah stok sesuai jenis transaksi stok.

### 10.4 Stock Adjustment

Jika stok sistem menunjukkan nol tetapi barang fisik masih tersedia, user dapat melakukan entry/adjustment stok untuk menambahkan jumlah stok.

### 10.5 Stock Movement

Sistem sebaiknya dapat membedakan perubahan stok berdasarkan sumbernya, minimal:

- Pembelian
- Penjualan
- Stock opname
- Barang rusak
- Barang hilang
- Adjustment
- Retur/revisi

## 11. Safety Stock & Inventory Intelligence

### 11.1 Metode Safety Stock (Revisi 2)

Sistem menghitung **Safety Stock (SS)** untuk mengantisipasi variasi permintaan harian selama waktu tunggu pengadaan (lead time) yang dianggap tetap pada model awal.

```text
Safety Stock = Z × σd × √L
```

Keterangan:

| Simbol | Definisi | Sumber |
| --- | --- | --- |
| `Z` | Z-score berdasarkan target *cycle service level* | Parameter kebijakan, contoh 95% → 1,645 |
| `σd` | Simpangan baku permintaan harian dalam base unit/hari | Histori transaksi penjualan per hari |
| `L` | Lead time pengadaan dalam hari | Kebijakan produk/supplier |
| `SS` | Stok pengaman dalam base unit | Hasil perhitungan backend |

**Koreksi acuan:** rumus yang tercetak pada bagian awal dokumen sumber tidak memuat `√L`, tetapi contoh hitung dan uraian detailnya memuat faktor itu. Versi ini mengikuti **rumus lengkap pada contoh**; keputusan matematis ini harus dinyatakan eksplisit agar tidak ada dua implementasi berbeda.

**Asumsi model:** lead time dianggap tetap dan fluktuasi permintaan antarhari diperlakukan independen untuk estimasi simpangan baku. Jika lead time bervariasi nyata atau permintaan sangat musiman, model perlu ditinjau sebelum dipakai sebagai jaminan tingkat layanan.

### 11.2 Reorder Point (ROP)

```text
Reorder Point = (d × L) + Safety Stock
```

`d` adalah rata-rata permintaan harian dalam base unit/hari, dan `L` adalah waktu tunggu pengadaan dalam hari. Nilai yang digunakan untuk pemicu pemesanan adalah hasil akhir yang sudah dibulatkan sesuai kelipatan minimum stok base unit produk.

Untuk barang diskret seperti buku tulis, gunakan pembulatan **ke atas ke bilangan bulat**. Untuk barang yang dapat dijual pecahan, pembulatan mengikuti presisi/kelipatan base unit produk; jangan membulatkan semua produk secara global menjadi 1 unit.

### 11.3 Data dan Perhitungan Otomatis

1. Ambil produk, jumlah stok terkini, dan kebijakan lead time/service level.
2. Susun deret kuantitas penjualan bersih harian per produk dari histori sale yang completed. Normalisasikan semua satuan ke `base_quantity` dan masukkan **hari tanpa penjualan sebagai nol**.
3. Hitung `d` sebagai rata-rata aritmetika dan `σd` sebagai simpangan baku populasi dari seluruh hari kalender pada periode observasi yang terverifikasi. Cara memasukkan retur, koreksi sale, dan sale offline yang baru tersinkron ke demand historis **belum diatur dokumen acuan** sehingga harus ditetapkan sebelum kalkulasi production.
4. Backend menghitung SS, ROP, waktu kalkulasi, dan status kelengkapan data; frontend hanya menampilkan hasil.
5. Parameter yang dapat dikonfigurasi: `lead_time_days`, `target_service_level` (serta pemetaan ke Z), `demand_window_days`, `minimum_history_days`, `manual_safety_stock_base`, dan `manual_reorder_point`.
6. Jika histori tidak mencukupi, jangan menyajikan angka otomatis seolah-olah reliabel. Gunakan fallback manual jika diatur, atau `insufficient_data` jika belum tersedia.

**Acuan ilustratif:** service level 95% dan Z = 1,645, sebagaimana contoh sumber, **bukan angka wajib seluruh produk**. Periode histori minimum, tingkat layanan setiap kategori, dan lead time aktual perlu validasi owner.

### 11.4 Aturan Status Persediaan

Untuk produk dengan SS/ROP efektif valid, berlaku urutan prioritas berikut:

| Kondisi stok terkini dalam base unit | Status | Tindakan |
| --- | --- | --- |
| `stock = 0` | **Habis** (`out_of_stock`) | Tindak lanjut pengadaan |
| `0 < stock ≤ SS` | **Kritis** (`critical`) | Prioritaskan pengadaan |
| `SS < stock ≤ ROP` | **Perlu pemesanan** (`reorder_needed`) | Buat pemesanan ulang |
| `stock > ROP` | **Stok mencukupi** (`sufficient`) | Pantau persediaan |
| SS/ROP belum tersedia dan tidak ada fallback | **Data belum cukup** (`insufficient_data`) | Lengkapi histori/kebijakan |

Batas **sama dengan SS** termasuk Kritis, dan batas **sama dengan ROP** termasuk Perlu pemesanan. Kategori `overstock` **tidak** dapat disimpulkan dari SS/ROP; perlu kebijakan stok maksimum terpisah.

Untuk MVP, status dibandingkan dengan **stok tercatat saat ini** (`product_inventory.quantity_base`) sesuai contoh. Kebijakan *inventory position* yang memperhitungkan barang dalam perjalanan atau reserved stock merupakan pengembangan terpisah.

### 11.5 Dataset Acceptance Test — ATK INSAN

| Variabel | Nilai |
| --- | ---: |
| Rata-rata permintaan (`d`) | 10 buku/hari |
| Simpangan baku harian (`σd`) | 4 buku/hari |
| Lead time (`L`) | 4 hari |
| Service level / Z | 95% / 1,645 |
| Stok terkini | 50 buku |

```text
SS mentah = 1,645 × 4 × √4 = 13,16
SS efektif = ceil(13,16) = 14 buku
ROP = (10 × 4) + 14 = 54 buku
50 > 14 dan 50 ≤ 54 → Perlu pemesanan
```

Data tersebut hanya **simulasi** untuk pengujian aplikasi, bukan data aktual toko.

### 11.6 Moving Classification

Sistem perlu mengidentifikasi barang **Slow, Middle, Fast Moving**, untuk mengurangi overstock/stock-out. Aturan rolling period dan percentile tetap mengacu ke CD-005; klasifikasi movement tidak mengganti Safety Stock/ROP.

## 12. Point of Sale (POS)

### 12.1 Alur Transaksi

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
13. Setelah penjualan berhasil difinalisasi dan tersimpan durable, cash drawer otomatis terbuka sekali untuk tunai, QRIS, atau split payment.

### 12.2 Kesalahan Input

Sistem perlu mengurangi risiko kesalahan seperti:

- Pelanggan membeli 1 PCS tetapi kasir memasukkan 1 lusin.
- Pelanggan membeli satu produk tetapi kasir memilih produk lain.

UI POS harus membantu kasir membedakan produk, satuan, harga, dan jumlah.

### 12.3 Perubahan Harga

Owner adalah pihak yang menentukan perubahan harga.

Kasir/user dapat mengubah harga berdasarkan arahan/persetujuan owner.

Tidak ada mekanisme approval sistem yang dikonfirmasi saat ini.

### 12.4 Diskon

Owner adalah pihak yang boleh memberikan diskon atau menentukan perubahan harga.

Detail bentuk diskon belum didefinisikan.

### 12.5 Retur / Koreksi Transaksi

Jika transaksi salah:

- Barang dapat ditukar.
- Nilai transaksi disesuaikan.
- Struk kemudian dicetak kembali.

Jika pelanggan membatalkan setelah pembayaran atau kasir salah memasukkan jumlah barang, owner menginginkan proses revisi yang mudah.

Model transaksi koreksi/retur final belum ditentukan.

### 12.6 Reprint Struk

Sistem harus mendukung pencetakan kembali struk setelah transaksi dikoreksi.

## 13. Payment

Metode pembayaran:

- Tunai
- QRIS

### 13.1 Split Payment

Satu transaksi dapat menggunakan lebih dari satu metode pembayaran.

Contoh: sebagian tunai dan sebagian QRIS.

### 13.2 Tunai

Jika pembayaran tunai selesai:

- Transaksi disimpan.
- Struk dicetak.
- Cash drawer otomatis terbuka.

### 13.3 QRIS

Pembayaran QRIS masuk ke akun bank pemilik.

Tidak ada requirement integrasi otomatis ke provider QRIS yang dikonfirmasi dalam PRD saat ini. Karena itu, pembayaran QRIS harus terlebih dahulu **dikonfirmasi berhasil** melalui verifikasi yang tersedia atau konfirmasi manual kasir sesuai SOP; tampilan QRIS atau status menunggu pembayaran **belum** berarti sale selesai.

Setelah QRIS dikonfirmasi, transaksi difinalisasi dan disimpan durable, **cash drawer otomatis terbuka satu kali** seperti pada transaksi tunai. Pembukaan laci bukan bukti bahwa transfer QRIS sudah masuk.

### 13.4 Penjualan Tempo

Tidak ada penjualan secara utang/tempo.

## 14. Customer

Sistem tidak perlu menyimpan data pribadi pelanggan seperti nomor telepon dan alamat.

Tidak ada kebutuhan CRM/member yang dikonfirmasi.

## 15. Reporting

### 15.1 Laporan Penjualan

Sistem harus menyediakan:

- Laporan penjualan harian
- Laporan detail
- Laporan rekap

### 15.2 Laba per Transaksi

Laba kotor per transaksi dapat ditampilkan sebagai **indikator operasional** dari `sale_items.net_total - sale_items.cost_total` (setelah penyesuaian retur dan cost reversal). Biaya modal pada sale item menggunakan snapshot saat finalisasi; histori tidak diubah destruktif.

**Jangan menjumlahkan HPP transaksi sekali lagi ke HPP periodik.** Laporan laba rugi resmi harus mengikuti rekonsiliasi persediaan dan transaksi periode pada bagian 15.3.

### 15.3 Laporan Laba Rugi Periodik (Revisi 1)

Sistem wajib menghasilkan laporan untuk satu periode tanggal dengan urutan dan rumus berikut, sesuai dokumen contoh ATK INSAN:

```text
Pembelian Bersih  = Total Pembelian + Biaya Angkut Pembelian
                    - Retur Pembelian - Potongan Pembelian
HPP               = Persediaan Awal + Pembelian Bersih - Persediaan Akhir
Penjualan Bersih  = Total Penjualan - Retur Penjualan - Potongan Penjualan
Laba Kotor        = Penjualan Bersih - HPP
Laba Bersih       = Laba Kotor - Total Beban Operasional
```

**Nilai, bukan jumlah unit:** persediaan awal/akhir adalah nilai seluruh stok pada cutoff periode (`quantity_base × nilai biaya per base unit`, dijumlahkan per produk). Persediaan awal periode N berasal dari persediaan akhir periode N-1. Sistem tidak boleh menggunakan `current average_cost` untuk menghitung ulang saldo periode lampau; nilai historis harus dapat direkonstruksi dari ledger biaya/valuasi atau snapshot historis yang terverifikasi.

**Waktu pengakuan:** penjualan dari sale `completed`, retur penjualan dari retur `completed`, pembelian dari `received_at` (bukan draft), retur pembelian pada tanggal efektif retur, dan beban operasional pada `expense_date` dalam rentang tanggal termasuk batasnya. Semua periode memakai timezone bisnis yang konsisten.

**Kebijakan persediaan rusak/hilang/stock adjustment:** rumus referensi di atas berlaku langsung bila tidak ada pergerakan nilai persediaan non-pembelian/non-penjualan. Untuk periode dengan kehilangan/kerusakan, adjustment positif, atau koreksi nilai, perlu rekonsiliasi terpisah agar nilai tersebut tidak keliru disebut HPP barang terjual atau dihitung dua kali sebagai beban. Laporan menampilkan status `requires_review` sampai klasifikasi finansial adjustment disepakati dan rekonsiliasi selesai.

**Contoh validasi Januari 2026 (ATK INSAN):**

| Komponen               | Nilai (Rp)     |
| ---------------------- | -------------: |
| Persediaan awal        | 8.000.000      |
| Total pembelian        | 5.000.000      |
| Biaya angkut pembelian | 200.000        |
| Retur pembelian        | (100.000)      |
| Potongan pembelian     | (50.000)       |
| **Pembelian bersih**   | **5.050.000**  |
| Persediaan akhir       | (3.000.000)    |
| **HPP**                | **10.050.000** |
| Total penjualan        | 16.000.000     |
| Retur penjualan        | (200.000)      |
| Potongan penjualan     | (100.000)      |
| **Penjualan bersih**   | **15.700.000** |
| **Laba kotor**         | **5.650.000**  |
| Beban operasional      | (1.700.000)    |
| **Laba bersih**        | **3.950.000**  |

**Batas akuntansi:** rumus laba bersih di atas mengasumsikan tidak ada pos bunga, pajak penghasilan, maupun pendapatan/beban non-operasional tambahan. Jika kelak ada, nama dan cakupan metrik laba harus ditinjau lagi. Contoh menyebut pajak sebagai kemungkinan beban, tetapi PRD belum menetapkan integrasi pajak transaksi.

### 15.4 Periode Laporan

Laporan harus dapat ditampilkan berdasarkan periode tanggal yang dipilih.

### 15.5 Perbandingan Laporan

Owner membutuhkan perbandingan tanggal/bulan yang sama dengan tahun yang berbeda.

### 15.6 Export

Laporan harus dapat diekspor ke:

- Excel
- PDF

### 15.7 Pajak

Tidak ada PPN/pajak pada transaksi berdasarkan kebutuhan saat ini.

### 15.8 Laporan Tambahan

Owner menyatakan laporan yang sudah disebutkan dianggap cukup.

Namun kebutuhan inventory intelligence untuk slow/middle/fast moving tetap diperlukan pada modul persediaan.

## 16. Harga Modal, Valuasi Persediaan & HPP

Pisahkan dua konsep berbeda:

1. **Rumus HPP periodik laporan** sudah ditentukan oleh dokumen contoh: `Persediaan Awal + Pembelian Bersih - Persediaan Akhir` (dengan pengecualian rekonsiliasi pergerakan non-penjualan yang dijelaskan di 15.3).
2. **Metode valuasi unit persediaan**, misalnya *Moving Weighted Average*, menentukan nilai moneter persediaan dan snapshot biaya barang yang terjual. Dokumen contoh tidak memilih salah satu metode tersebut; *Moving Weighted Average* tetap **proposed default**, bukan persetujuan final owner/accounting.

Jika *Moving Weighted Average* disetujui, perolehan mencakup biaya pembelian bersih ditambah alokasi biaya angkut dan dikurangi potongan; nilai penilaian produk dihitung dari nilai persediaan historis + biaya perolehan yang diterima, kemudian dibagi kuantitas. Backend menggunakan presisi decimal internal untuk cost per base unit, sedangkan jumlah uang yang disajikan dan dikirim API berupa integer Rupiah dengan aturan pembulatan yang konsisten.

Untuk HPP historis, sistem menyimpan jejak perubahan **kuantitas dan nilai inventory** sehingga persediaan per tanggal dapat diaudit. Pembelian/retur/sale yang sudah finalized tidak boleh diedit destruktif.

**Open financial decision:** metode valuasi final, cara klasifikasi barang rusak/hilang, retur antar-periode, pembulatan alokasi biaya, dan angka pembuka saat migrasi stok awal harus disetujui sebelum laporan laba rugi dipakai resmi.

## 17. Hardware Integration (Revisi 3)

### 17.1 Inventaris Perangkat Aktual

Inventaris berikut berasal dari foto dan video operasional toko, bukan asumsi model perangkat lain. Implementasi harus memprioritaskan kompatibilitas dengan perangkat yang sudah ada.

| Perangkat | Bukti / spesifikasi terlihat | Status verifikasi |
| --- | --- | --- |
| Komputer kasir | Windows desktop, monitor LG dengan kabel VGA | Terlihat di foto/video; spesifikasi PC belum tersedia |
| Printer struk | Bagian depan berlogo KASSEN, label belakang **BT-P299**, thermal paper **58 mm**, 90 mm/s, **USB/Bluetooth**, **ESC/POS**, DC12V 2A | Spesifikasi terbaca pada label; uji cetak dari SIPINTAR belum dilakukan |
| Cash drawer | Laci uang berkunci; model **CD-339** terlihat pada stiker, foto referensi menyebut RJ11 | Owner mengonfirmasi **terbuka otomatis setelah pembayaran tunai pada POS lama**; jalur/kabel aktuator belum diverifikasi |
| Barcode scanner | PANDA, scanner genggam berkabel USB dan dudukan | Model tepat, mode USB-HID dan barcode symbology perlu diuji |
| Kertas struk | Thermal roll berlabel Printech | **Lebar printer 58 mm** telah terverifikasi, panjang/diameter roll perlu dicek fisik |
| Printer barcode/label | Belum ada bukti model yang pasti dalam foto | Tetap perlu konfirmasi perangkat tersendiri atau kebutuhan pengadaan |

### 17.2 Kebutuhan Printer Struk

- Target utama **58 mm**, dengan template struk responsif; dukungan 80 mm tetap opsional untuk ekspansi.
- Driver perintah yang diusulkan: ESC/POS melalui **USB** pada Windows. Dukungan Bluetooth terlihat pada label, namun belum diuji untuk produksi.
- Pencetakan struk hanya boleh menggunakan data transaksi yang sudah durable/finalized; `GET /api/v1/sales/{saleId}/receipt` merupakan kontrak data, **bukan** perintah mencetak secara fisik.
- Kasir dapat **reprint** berdasarkan ID sale yang sama tanpa membuat penjualan atau pengurangan stok baru; cetak ulang dapat diberi label `SALINAN`/`REPRINT`.
- Gagal cetak harus ditandai pada UI dan dapat dicoba ulang, **tanpa membatalkan transaksi yang sudah durable**.

### 17.3 Cash Drawer dan Pembayaran

- **Terkonfirmasi secara operasional:** POS lama membuka cash drawer otomatis setelah pembayaran tunai selesai.
- **Keputusan owner (pembaruan v1.3):** SIPINTAR **wajib mengirim satu perintah `open_auto` setelah sale berhasil difinalisasi dan disimpan durable**, **tanpa membedakan metode pembayaran**: CASH-only, QRIS-only, maupun split CASH + QRIS.
- QRIS yang masih `pending`, belum terkonfirmasi, atau transaksi yang belum finalized **tidak boleh** membuka laci. Karena integrasi payment gateway belum dipastikan, keberhasilan QRIS dapat membutuhkan verifikasi manual sesuai SOP toko.
- Untuk satu `client_uuid`/sale, trigger otomatis dilakukan **sekali per sale**, bukan sekali per komponen pembayaran. Perintah dicatat secara lokal agar re-render, reconnect, restart, dan sync retry tidak memicu pembukaan berulang.
- Hubungan hardware **printer ESC/POS → port drawer/kabel RJ11** adalah **hipotesis integrasi**, bukan kepastian sampai jalur fisik dan pulsa drawer diuji.
- Kegagalan perangkat setelah sale selesai **tidak boleh rollback sale, duplikasi pembayaran, stok, atau nomor transaksi**.
- Manual open **via aplikasi** hanya untuk user berizin `cash_drawer.open_manual`, dengan audit event. Penggunaan kunci **fisik** tidak otomatis terdeteksi sistem jika tidak ada sensor.
- Agen perangkat harus membedakan status **perintah terkirim**, **gagal**, dan **tidak diketahui**; tanpa sensor, sistem tidak boleh mengklaim laci benar-benar terbuka.

### 17.4 Barcode Scanner

Barcode scanner PANDA USB digunakan sebagai input pencarian SKU/barcode. Jika uji menunjukkan USB-HID keyboard wedge, input dapat diterima oleh field POS tanpa hardware agent; penggunaan mode lain membutuhkan adapter tersendiri. Scan tidak boleh menyebabkan checkout otomatis; setelah barcode dikenali tetap lakukan validasi produk, satuan, quantity, dan harga.

### 17.5 Arsitektur Integrasi Hardware

```text
React POS/PWA ──> local hardware abstraction ──> Windows local agent (opsi utama)
                                         ├── ESC/POS 58 mm printer (USB)
                                         └── Cash drawer (uji koneksi printer / RJ11)
PANDA USB scanner ──> keyboard HID ke input React (jika mode ini terbukti)

Sale finalize (durable) ──> enqueue local print / drawer commands
                           ├── command success/failure/unknown
                           └── retry hanya perintah perangkat, BUKAN POST sale lagi
```

Local hardware agent adalah **arah desain yang direkomendasikan**, bukan instalasi yang sudah terbukti. Batasi akses ke loopback, terapkan autentikasi antarkomponen, validasi origin/command, prinsip least privilege, dan jangan membuka agent ke jaringan publik. Konfigurasi port/perangkat dan kredensial tidak boleh dikirim melalui API bisnis biasa.

### 17.6 Keandalan dan Pengujian

- Perintah lokal memiliki `command_uuid` unik dan referensi `client_uuid`/`sale_id` agar retry dapat dilacak; retry otomatis **tidak boleh membuka laci dua kali** saat hasil perintah pertama tidak diketahui tanpa kebijakan eksplisit/operator.
- Bila offline, sale disimpan dahulu ke penyimpanan lokal durable (`pending_sync`), lalu cetak dan open drawer dapat dilakukan dari data lokal. Saat online kembali, sync harus tetap idempotent.
- Event lokal dapat diproses ulang untuk audit saat koneksi kembali; satu `event_uuid` tidak boleh menghasilkan beberapa catatan identik.
- Hardware smoke test wajib: print struk 58 mm, reprint, **open otomatis untuk tunai, QRIS-only, split payment**, **tidak membuka saat QRIS pending/gagal**, scanner, cabut USB, printer mati, agent mati, crash, listrik padam, offline, dan rekonsiliasi perintah berstatus tidak diketahui.

## 18. Offline Operation

### 18.1 Internet Mati

Transaksi harus tetap dapat dilakukan ketika internet mati.

Ini merupakan requirement wajib.

Sistem harus memiliki strategi agar proses POS dapat terus berjalan tanpa koneksi internet.

Detail mekanisme sinkronisasi belum menjadi bagian keputusan bisnis PRD dan perlu ditentukan dalam technical design.

### 18.2 Sinkronisasi

Setelah koneksi tersedia kembali, transaksi offline harus dapat disinkronkan tanpa kehilangan data atau membuat transaksi ganda.

Requirement ini merupakan konsekuensi teknis dari kebutuhan transaksi offline dan perlu divalidasi dalam technical architecture.

## 19. Failure Handling & Recovery

### 19.1 Listrik Mati

Jika listrik mati ketika transaksi berlangsung, perlu terdapat catatan khusus.

Detail bentuk catatan belum ditentukan.

### 19.2 Printer Tidak Berfungsi

Kerusakan printer tidak boleh menghalangi transaksi.

Transaksi tetap harus dapat diselesaikan.

Struk dapat dicetak kembali ketika printer tersedia.

### 19.3 Kerusakan Komputer

Jika komputer kasir mengalami kerusakan, data transaksi sebelumnya harus dapat dipulihkan.

Sistem membutuhkan mekanisme backup/recovery yang memadai.

## 20. Business Rules

### BR-001 — Harga Transaksi

Jika harga pada rak berbeda dengan harga di sistem, harga yang digunakan adalah harga di sistem.

### BR-002 — Payment

Metode pembayaran hanya tunai dan QRIS.

Split payment tunai + QRIS diperbolehkan.

### BR-003 — Customer Credit

Penjualan tempo/piutang pelanggan tidak digunakan.

### BR-004 — Supplier Credit

Pembelian tempo/hutang supplier tidak digunakan.

### BR-005 — Tax

Tidak ada PPN dalam transaksi.

### BR-006 — Product Unit

Harga jual produk ditentukan berdasarkan satuan.

### BR-007 — Supplier

Satu produk dapat memiliki beberapa supplier.

### BR-008 — Stock Receiving

Jumlah stok masuk mengikuti barang yang benar-benar diterima.

### BR-009 — Cash Drawer

Cash drawer otomatis dibuka **sekali setelah penjualan finalized dan durable**, untuk CASH-only, QRIS-only, maupun split CASH+QRIS. QRIS pending/gagal atau keranjang belum finalized tidak memicu perintah.

Manual open hanya boleh dilakukan kasir.

### BR-010 — Offline Transaction

Transaksi harus dapat berjalan ketika internet mati.

### BR-011 — Pembelian Bersih dan Biaya Angkut

Biaya angkut untuk memperoleh stok merupakan bagian pembelian bersih/HPP melalui valuasi persediaan; jangan dicatat lagi sebagai operating expense.

### BR-012 — HPP Periodik

HPP dasar = nilai persediaan awal + pembelian bersih - nilai persediaan akhir; persediaan historis tidak dihitung dari harga modal terkini.

### BR-013 — Laba Rugi

Penjualan bersih = penjualan bruto - retur penjualan - potongan penjualan; laba kotor = penjualan bersih - HPP; laba bersih = laba kotor - beban operasional.

### BR-014 — Rekonsiliasi Stok Non-Penjualan

Periode dengan barang rusak/hilang/opname/stock adjustment harus memiliki penanganan valuasi dan klasifikasi finansial yang eksplisit, serta diberi status `requires_review` bila belum direkonsiliasi.

### BR-015 — Safety Stock & Reorder Point (Revisi 2)

Backend menghitung `SS = Z × σd × √L` dan `ROP = (d × L) + SS` menggunakan base unit. Stok diskret dibulatkan ke atas, sedangkan stok pecahan mengikuti kelipatan satuannya.

### BR-016 — Reorder Alert Priority (Revisi 2)

Urutan kondisi: `stock=0` → Habis; `0<stock≤SS` → Kritis; `SS<stock≤ROP` → Perlu pemesanan; `stock>ROP` → Stok mencukupi. Jika perhitungan/fallback belum tersedia, tampilkan Data belum cukup, bukan status aman palsu.

### BR-017 — Parameter & Data Quality (Revisi 2)

Z-score, service level, lead time, observation window, serta manual fallback harus dapat diaudit dan tidak mengubah histori transaksi. Backend menjadi authority untuk statistik, pembulatan dan hasil status.

## 21. Functional Requirements

### Authentication

#### FR-AUTH-001

Sistem harus memungkinkan user login menggunakan akun masing-masing.

#### FR-AUTH-002

Sistem harus mengetahui identitas user yang sedang aktif.

#### FR-AUTH-003

Setiap transaksi penjualan harus menyimpan user yang melakukan transaksi.

### Product

#### FR-PROD-001

Sistem harus memungkinkan pencarian produk berdasarkan barcode.

#### FR-PROD-002

Sistem harus memungkinkan pencarian produk berdasarkan kode barang.

#### FR-PROD-003

Sistem harus memungkinkan pencarian produk berdasarkan nama.

#### FR-PROD-004

Sistem harus mendukung produk dengan kategori, ukuran, varian, dan warna.

#### FR-PROD-005

Sistem harus mendukung lebih dari satu satuan untuk satu produk.

#### FR-PROD-006

Setiap satuan dapat memiliki harga jual yang berbeda.

### Supplier

#### FR-SUP-001

Sistem harus dapat menyimpan data supplier.

#### FR-SUP-002

Satu produk harus dapat terhubung dengan beberapa supplier.

#### FR-SUP-003

Sistem harus menyimpan riwayat harga produk dari supplier.

### Purchasing

#### FR-PUR-001

Sistem harus dapat mencatat transaksi pembelian.

#### FR-PUR-002

Pembelian harus mencatat supplier, tanggal, produk, quantity, dan harga.

#### FR-PUR-003

Penerimaan barang harus menambah stok.

#### FR-PUR-004

Jumlah penerimaan harus mengikuti barang yang benar-benar diterima.

#### FR-PUR-005

Invoice/faktur supplier harus dapat disimpan.

### Inventory

#### FR-INV-001

Sistem harus mencatat stok produk.

#### FR-INV-002

Sistem harus mendukung stock adjustment.

#### FR-INV-003

Sistem harus mendukung stock opname.

#### FR-INV-004

Barang rusak harus dapat dicatat.

#### FR-INV-005

Barang hilang harus dapat dicatat.

#### FR-INV-006

Sistem harus menghitung SS/ROP atau fallback manual yang dapat diaudit, mengklasifikasikan status, dan memberi peringatan ketika `stock <= ROP`.

#### FR-INV-007

Sistem harus dapat mengidentifikasi produk slow, middle, dan fast moving.

#### FR-INV-008 — Safety Stock Calculation

Sistem harus menghitung `SS = Z × σd × √L` dan `ROP = d × L + SS` menggunakan deret permintaan harian, lead time, dan service level yang dapat dikonfigurasi.

#### FR-INV-009 — Inventory Alert Status

Sistem harus mengembalikan status `out_of_stock`, `critical`, `reorder_needed`, `sufficient`, atau `insufficient_data` berdasarkan aturan prioritas yang tidak tumpang tindih.

#### FR-INV-010 — Inventory Policy & History Sufficiency

User berizin dapat mengatur parameter per produk. Backend wajib menandai kondisi histori tidak mencukupi dan menerapkan fallback manual tanpa menghasilkan prediksi otomatis palsu.

#### FR-INV-011 — Daily Recalculation

Metrik SS/ROP harus diperbarui setelah perubahan relevan pada transaksi dan secara terjadwal; API mengembalikan waktu perhitungan serta status kalkulasi.

### POS

#### FR-POS-001

Kasir harus dapat menambahkan produk ke transaksi melalui scan barcode.

#### FR-POS-002

Kasir harus dapat menambahkan produk melalui pencarian kode/nama.

#### FR-POS-003

Kasir harus dapat memilih satuan produk.

#### FR-POS-004

Sistem harus menghitung subtotal dan total secara otomatis.

#### FR-POS-005

Sistem harus menyimpan transaksi beserta identitas kasir.

#### FR-POS-006

Transaksi berhasil harus mengurangi stok.

#### FR-POS-007

Transaksi harus dapat diselesaikan meskipun printer tidak berfungsi.

#### FR-POS-008

Transaksi harus dapat dilakukan ketika internet mati.

### Payment

#### FR-PAY-001

Sistem harus mendukung pembayaran tunai.

#### FR-PAY-002

Sistem harus mendukung pembayaran QRIS.

#### FR-PAY-003

Sistem harus mendukung split payment tunai + QRIS.

#### FR-PAY-004

Setiap sale yang selesai dan durable harus memicu satu perintah pembukaan cash drawer otomatis, termasuk CASH-only, QRIS-only, dan split CASH+QRIS. Perintah tidak dikirim saat QRIS masih pending/gagal.

### Reporting

#### FR-REP-001

Sistem harus menyediakan laporan penjualan harian.

#### FR-REP-002

Sistem harus menyediakan laporan detail transaksi.

#### FR-REP-003

Sistem harus menyediakan laporan rekap.

#### FR-REP-004

Sistem harus menghitung laba per transaksi.

#### FR-REP-005

Sistem harus menyediakan laba kotor.

#### FR-REP-006

Sistem harus menyediakan laba bersih.

#### FR-REP-007

Laporan harus dapat difilter berdasarkan periode tanggal.

#### FR-REP-008

Laporan harus dapat dibandingkan dengan periode sama pada tahun berbeda.

#### FR-REP-009

Laporan harus dapat diekspor ke Excel.

#### FR-REP-010

Laporan harus dapat diekspor ke PDF.

#### FR-REP-011

Sistem menghitung dan menampilkan total pembelian, biaya angkut pembelian, retur/potongan pembelian, dan pembelian bersih.

#### FR-REP-012

Sistem menyajikan nilai persediaan awal dan akhir sesuai tanggal laporan dari data valuasi historis.

#### FR-REP-013

Sistem menghitung HPP periodik, penjualan bersih, laba kotor, dan laba bersih dengan rumus bagian 15.3.

#### FR-REP-014

Sistem menampilkan rincian beban operasional berdasarkan kategori dan mencegah pencatatan ganda biaya angkut pengadaan.

#### FR-REP-015

Sistem melakukan kontrol rekonsiliasi stock adjustment non-penjualan sebelum angka laba rugi dianggap final.

#### FR-REP-016

Sistem menyediakan contoh uji otomatis Januari 2026: pembelian bersih Rp5.050.000, HPP Rp10.050.000, penjualan bersih Rp15.700.000, laba kotor Rp5.650.000, laba bersih Rp3.950.000.

## 22. Non-Functional Requirements

### NFR-001 — Offline Availability

POS harus dapat tetap menjalankan transaksi ketika internet tidak tersedia.

### NFR-002 — Data Recovery

Data transaksi harus dapat dipulihkan jika perangkat kasir mengalami kerusakan.

### NFR-003 — Hardware Compatibility

Sistem harus mendukung perangkat kasir Windows dan peripheral toko yang digunakan.

### NFR-004 — Usability

POS harus membantu mengurangi kesalahan pemilihan produk, satuan, dan quantity.

### NFR-005 — Data Integrity

Transaksi penjualan dan perubahan stok harus menjaga konsistensi stok.

### NFR-006 — Auditability

Transaksi harus memiliki identitas user yang melakukan transaksi.

## 23. Acceptance Criteria Utama

### AC-001 — Product Search

Given kasir berada pada halaman POS  
When kasir scan barcode atau memasukkan kode/nama produk  
Then sistem menampilkan produk yang sesuai dan dapat ditambahkan ke transaksi.

### AC-002 — Multi Unit

Given produk memiliki beberapa satuan  
When kasir memilih satuan tertentu  
Then harga transaksi menggunakan harga dari satuan tersebut.

### AC-003 — Cash Payment

Given transaksi memiliki total pembayaran tunai dan finalization berhasil disimpan durable  
When layanan perangkat terhubung dan perintah open berhasil diterima perangkat  
Then struk dapat dicetak dan sistem mengirim perintah cash drawer open **sekali**, tanpa mengklaim kondisi fisik laci bila tidak ada sensor.

### AC-004 — Split Payment

Given pelanggan membayar menggunakan tunai dan QRIS  
When total kedua pembayaran sama dengan total transaksi  
Then transaksi dapat diselesaikan dan masing-masing metode pembayaran tercatat.

### AC-005 — Offline

Given koneksi internet terputus  
When kasir melakukan transaksi  
Then transaksi tetap dapat diproses tanpa kehilangan data.

### AC-006 — Stock Adjustment

Given stok sistem berbeda dengan stok fisik  
When user melakukan adjustment  
Then stok sistem berubah sesuai quantity baru dan perubahan stok tercatat.

### AC-007 — Stock Alert

Given data SS/ROP valid dan stock produk diketahui  
When kuantitas stok berubah  
Then backend mengembalikan klasifikasi Habis, Kritis, Perlu pemesanan, atau Stok mencukupi dengan batas `≤` yang konsisten.

### AC-008 — Report Export

Given user membuka laporan  
When user memilih periode dan export Excel/PDF  
Then sistem menghasilkan file laporan sesuai periode tersebut.

### AC-009 — Financial Formula Regression

Given dataset referensi ATK INSAN untuk Januari 2026  
When laporan laba rugi 1–31 Januari 2026 dihitung  
Then hasil persis: pembelian bersih Rp5.050.000, HPP Rp10.050.000, penjualan bersih Rp15.700.000, laba kotor Rp5.650.000, dan laba bersih Rp3.950.000.

### AC-010 — Historical Inventory Cutoff

Given persediaan akhir periode sebelumnya  
When laporan periode baru dihitung  
Then nilai persediaan awal = nilai persediaan akhir periode sebelumnya dan tidak berubah akibat update harga modal saat ini.

### AC-011 — No Double Count Freight-in

Given biaya angkut pengadaan sudah menjadi bagian biaya perolehan persediaan  
When HPP dan beban operasional dihitung  
Then biaya tersebut tidak muncul kembali sebagai expense operasional.

### AC-012 — Safety Stock Golden Case

Given `d=10`, `σd=4`, `L=4`, dan `Z=1,645` untuk buku yang dijual utuh  
When backend menghitung persediaan pengaman  
Then `raw_ss=13,16`, `safety_stock_base=14`, dan `reorder_point_base=54`.

### AC-013 — ROP Decision

Given Safety Stock 14, ROP 54, dan stok saat ini 50  
When status stok dihitung  
Then status `reorder_needed` dan sistem merekomendasikan pemesanan.

### AC-014 — Boundary Conditions

Given SS 14 dan ROP 54  
When stok = 0, 14, 15, 54, dan 55  
Then status berturut-turut `out_of_stock`, `critical`, `reorder_needed`, `reorder_needed`, dan `sufficient`.

### AC-015 — Data Sufficiency

Given histori tidak lengkap atau tidak mencukupi dan tidak ada nilai manual  
When SS/ROP diminta  
Then status `insufficient_data` ditampilkan dan backend tidak mengklaim ada ROP otomatis valid.

### AC-016 — Unit & Time Consistency

Given penjualan PCS dan PACK untuk satu produk  
When permintaan harian dihitung  
Then seluruh jumlah dinormalisasikan ke base unit, hari tanpa penjualan menjadi nol, dan statistik harian konsisten pada business timezone.

### AC-017 — Printer Failure Does Not Reverse Sale

Given sale berhasil disimpan, tetapi printer tidak merespons  
When kasir memilih retry print  
Then nomor sale, stok, pembayaran, dan `client_uuid` **tidak berubah**; hanya perintah cetak yang dicoba kembali.

### AC-018 — Drawer Trigger by Payment Composition

Given tiga transaksi **completed dan durable**: CASH-only, CASH + QRIS, dan QRIS-only  
When perintah peripheral disiapkan  
Then **masing-masing transaksi memicu tepat satu perintah** `open_auto`; jumlah metode pembayaran dalam satu sale tidak menambah jumlah perintah.

### AC-018A — QRIS Pending Must Not Trigger Drawer

Given QRIS baru ditampilkan atau pembayaran belum dikonfirmasi  
When sale belum disimpan sebagai completed  
Then cash drawer tidak menerima perintah `open_auto`; setelah pembayaran dikonfirmasi dan sale durable, perintah dikirim satu kali.

### AC-019 — No Accidental Duplicate Drawer Open

Given perintah drawer sebelumnya berstatus `unknown`/timeout  
When aplikasi restart atau sinkronisasi diulang  
Then agen tidak melakukan auto-retry pembukaan laci tanpa kebijakan aman atau interaksi operator.

### AC-020 — Offline Peripheral Commands

Given koneksi server mati dan local sale telah durable sebagai `pending_sync`  
When printer/drawer lokal tersedia  
Then struk dapat dicetak dan cash drawer menerima satu perintah `open_auto` untuk **setiap sale yang telah completed secara lokal**, baik CASH-only, QRIS-only (jika sudah dikonfirmasi sesuai SOP), maupun split; sync selanjutnya tidak memicu pembukaan kembali.

### AC-021 — Barcode Scan

Given scanner PANDA aktif pada PC kasir  
When barcode dipindai ke POS  
Then sistem menemukan SKU/barcode yang cocok tanpa menyelesaikan sale secara otomatis; mode USB-HID adalah hasil uji, bukan asumsi.

### AC-022 — Scan Barcode Baru

Given barcode pabrik belum ada dalam katalog  
When user memindainya pada alur penerimaan/registrasi  
Then sistem membuka draft produk baru, barcode terisi, user harus melengkapi data serta harga, dan tidak menambah stok otomatis.

### AC-023 — Barcode Internal

Given produk tidak memiliki barcode pabrik dan SKU tersimpan  
When user berwenang memilih Generate Barcode  
Then sistem membuat/reuse barcode Code 128 yang unik dan terikat ke produk/satuan, tanpa mengganti SKU.

### AC-024 — Harga Jual Kosong

Given `HARGAJUAL* = 0` atau harga jual satuan belum disahkan  
When kasir mencoba menjual satuan tersebut  
Then sistem menolak checkout item dengan pesan penetapan harga; stok dan histori transaksi tidak berubah.

### AC-025 — Import dan Stock Ledger

Given file legacy berhasil dipreview  
When batch import master disetujui  
Then SKU lama tetap dipertahankan, barcodes tidak duplikat, stok awal dicatat sebagai pergerakan inventori dan semua warning impor dapat dilacak.

## 24. Out of Scope / Tidak Dibutuhkan Saat Ini

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

## 25. Open Decisions / Requirement Gaps

Item berikut belum cukup jelas untuk dianggap final.

### OD-001 — Barcode (Resolved pada Revisi 4)

Owner sudah mengonfirmasi bahwa sebagian produk menggunakan barcode pabrik dan produk tanpa barcode pabrik harus dapat memperoleh barcode dari sistem. Pencarian manual dengan kode internal tetap diperlukan. Jenis dan mekanisme printer **label barcode** masih menunggu uji perangkat fisik.

### OD-002 — Metode Valuasi Persediaan (Revisi 1)

**Rumus HPP periodik telah ditetapkan oleh dokumen referensi terbaru**: persediaan awal + pembelian bersih − persediaan akhir. Namun metode penetapan **nilai** persediaan per unit (moving weighted average, FIFO, dll.) **belum dipastikan owner/accounting**.

Usulan teknis saat ini: Moving Weighted Average dengan biaya perolehan termasuk biaya angkut pengadaan dan potongan. Perlu keputusan tertulis atas metode final, stock adjustment non-penjualan, pembulatan, dan retur lintas-periode. Rumus periodik bukan bukti bahwa metode average cost telah disetujui.

### OD-003 — Safety Stock (Revisi 2)

**Rumus dan dataset contoh telah diadopsi:** `SS = Z × σd × √L`, `ROP = d × L + SS`, status berbasis batas SS/ROP.

**Masih memerlukan konfirmasi operasional:** target cycle service level per produk/kategori, lead time aktual, jendela observasi dan minimum histori, penanganan musim sekolah, serta penggunaan manual fallback. Contoh 95% bukan target global yang otomatis disetujui.

### OD-004 — Slow / Middle / Fast Moving

Klasifikasi dibutuhkan, tetapi periode evaluasi, threshold, dan formula belum ditentukan.

### OD-005 — Approval Perubahan Harga

Kasir dapat mengubah harga berdasarkan arahan/persetujuan owner, tetapi approval sistem tidak diperlukan.

Perlu ditentukan apakah persetujuan hanya verbal/offline, memakai PIN owner, permission khusus, atau tidak ada mekanisme teknis.

### OD-006 — Retur / Koreksi Transaksi

Owner menginginkan revisi transaksi yang mudah.

Belum ditentukan apakah transaksi lama diedit, dibuat return transaction, dibuat void + transaksi baru, atau dibuat adjustment.

### OD-007 — Printer Struk dan Jalur Cash Drawer

**Sebagian sudah dikonfirmasi (Revisi 3):** printer KASSEN berlabel BT-P299, lebar 58 mm, ESC/POS, USB/Bluetooth; automatic drawer open pada aplikasi POS lama sudah dikonfirmasi owner.

**Masih terbuka:** hasil uji ESC/POS pada SIPINTAR, driver Windows, port aktual, sambungan/kabel cash drawer, pulse command yang kompatibel, dan model scanner yang tepat.

### OD-008 — Manual Cash Drawer Audit

**Resolved sebagai keputusan teknis di CD-008:** manual open melalui aplikasi hanya untuk user dengan permission dan wajib dicatat. Penggunaan kunci fisik tidak dapat diaudit otomatis tanpa sensor/perangkat tambahan.

### OD-009 — Listrik Mati

Dibutuhkan "catatan khusus" jika listrik mati ketika transaksi berlangsung.

Bentuk catatan dan alurnya belum ditentukan.

### OD-010 — Informasi Tambahan Riwayat Pembelian

Owner belum memberikan jawaban untuk informasi tambahan yang ingin dilihat selain supplier, tanggal, barang, quantity, dan harga.

## 26. Technical Constraints / Project Decisions

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

## 27. Definition of Requirement Status

Setiap requirement berikutnya harus menggunakan salah satu status:

**Confirmed**  
Sudah dikonfirmasi oleh owner/client.

**Open Decision**  
Kebutuhan sudah ada tetapi cara/aturannya belum final.

**Technical Decision**  
Tidak memerlukan keputusan bisnis tetapi membutuhkan keputusan engineering.

**Out of Scope**  
Tidak dibutuhkan pada versi kebutuhan saat ini.

## 28. Next Step

Sebelum requirement dinyatakan locked untuk Sprint implementasi penuh:

1. Validasi ulang Open Decisions, khususnya valuasi HPP dan klasifikasi penyesuaian persediaan menurut revisi 1.
2. Buat ERD berdasarkan requirement confirmed.
3. Buat API contract awal.
4. Buat technical design untuk offline transaction & sync.
5. Lakukan hardware spike untuk printer thermal, barcode scanner, barcode printer, dan cash drawer.
6. Breakdown requirement menjadi GitHub Issues frontend/backend.
7. Setelah keputusan kritis diselesaikan, ubah status dokumen menjadi **Approved / Requirement Locked**.

## 29. Changelog

### v1.4 — Revisi 4: Master Data, Barcode, dan Harga Jual

- Menyelaraskan aturan barcode dengan jawaban terbaru owner dan audit `uli.xlsx`.
- Menambahkan scan-to-register, alokasi SKU atomik, barcode Code 128 internal, pemetaan barcode per satuan, dan pengamanan harga 0.
- Memisahkan import produk dari opening stock berbasis inventory ledger; menambah acceptance criteria.

### v1.3 — Pembaruan: Cash Drawer untuk Semua Metode Pembayaran

- Owner meminta QRIS-only **juga membuka cash drawer secara otomatis** setelah pembayaran berhasil dan sale finalized/durable.
- Menyamakan trigger `open_auto` untuk CASH-only, QRIS-only, dan split CASH+QRIS; **sekali per sale**, bukan per payment.
- Menegaskan QRIS pending/gagal tidak membuka laci; keberhasilan QRIS wajib dikonfirmasi, dengan SOP manual selama gateway otomatis belum terintegrasi.
- Mempertahankan aturan offline, perintah idempotent, keamanan agent, pemulihan perangkat, serta rumus HPP dan Safety Stock sebelumnya.

### v1.3 — Revisi 3: Hardware Integration

- Mengunci bukti hardware aktual dari foto/video: printer KASSEN/BT-P299 58 mm, scanner PANDA USB, cash drawer CD-339 dan Windows desktop.
- Mencatat konfirmasi owner bahwa cash drawer POS lama otomatis terbuka setelah pembayaran tunai.
- Menetapkan aturan command perangkat dipisah dari commit sale, cash drawer otomatis sesuai kebijakan terbaru, crash/offline handling, audit, dan acceptance tests.
- Memisahkan bukti terverifikasi dari port/kabel/driver yang masih membutuhkan hardware spike.
- Mempertahankan seluruh revisi HPP/laba rugi (v1.1) dan Safety Stock/ROP (v1.2).

### v1.2 — Revisi 2: Safety Stock & Reorder Point

- Mengganti rumus awal lead-time-plus-safety-days dengan `SS = Z × σd × √L` dan `ROP = d × L + SS`.
- Menambahkan kebutuhan riwayat penjualan harian, konfigurasi layanan, fallback manual, klasifikasi status, dan contoh uji 14/54/50.
- Menetapkan logika batas inklusif untuk Kritis dan Perlu pemesanan serta menandai aturan operasional yang masih membutuhkan konfirmasi.
- Mempertahankan seluruh perubahan HPP/laba rugi revisi 1.

### v1.1 — Revisi 1: HPP & Laba Rugi

- Menetapkan pembelian bersih, HPP periodik, penjualan bersih, laba kotor, dan laba bersih berdasarkan contoh ATK INSAN.
- Mengubah perlakuan biaya angkut pengadaan menjadi bagian biaya perolehan stok; membedakan dari beban transport non-pengadaan.
- Menambahkan kebutuhan valuasi historis, rekonsiliasi adjustment, acceptance criteria dan dataset uji Rp3.950.000 laba bersih.
- Memisahkan keputusan rumus HPP dari metode valuasi per unit yang masih membutuhkan sign-off.

### v1.0

- Menyusun hasil discovery menjadi formal PRD.
- Memisahkan confirmed requirements dan open decisions.
- Menambahkan functional requirements.
- Menambahkan business rules.
- Menambahkan non-functional requirements.
- Menambahkan acceptance criteria utama.
- Menandai area yang belum cukup jelas agar tidak diimplementasikan berdasarkan asumsi.
