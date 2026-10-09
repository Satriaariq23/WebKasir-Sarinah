# Sarinah Kasir

Aplikasi kasir dan manajemen operasional toko berbasis PHP untuk kebutuhan portofolio.

## Fitur

- Penjualan kasir dan cetak transaksi
- Pengelolaan barang, stok, dan stok opname
- Pembelian, retur pembelian, dan retur penjualan
- Pengelolaan pelanggan, supplier, piutang, dan hutang
- Laporan operasional
- Manajemen pengguna dan hak akses
- Salesman, komisi, serta program poin dan reward pelanggan
- Smoke test dan test case Selenium IDE

## Teknologi

- PHP 8.2+
- MySQL atau MariaDB
- Apache (XAMPP)
- MySQLi dan Composer autoload
- Bootstrap, jQuery, DataTables, dan Selenium IDE untuk pengujian

## Instalasi lokal

1. Clone repository ke folder `htdocs` XAMPP.
2. Salin `.env.example` menjadi `.env`, lalu sesuaikan koneksi database.
3. Buat database kosong bernama `sarinah`.
4. Import schema anonim dan fixture demo yang Anda siapkan sesuai panduan di [database/README.md](database/README.md).
5. Pastikan Apache dan MySQL/MariaDB aktif di XAMPP.
6. Buka `http://localhost/sarinah-apps/`.

Konfigurasi aplikasi membaca environment variable. Jangan commit file `.env`, dump database lokal, log, atau file upload.

## Pengujian

Folder `docs/` berisi skenario Selenium IDE. Jalankan pengujian hanya pada database testing yang berisi data fixture, bukan database produksi.

## Catatan portofolio

Repository ini menggunakan data dummy atau data anonim. Data operasional asli, kredensial, log, dan hasil upload tidak termasuk dalam repository publik.

## Lisensi

Kode aplikasi ini dipublikasikan untuk keperluan portofolio. Periksa lisensi setiap dependency pihak ketiga sebelum melakukan distribusi ulang.