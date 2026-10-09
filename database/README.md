# Database lokal

Folder ini berisi migration SQL yang dapat dijalankan setelah schema utama tersedia.

## Menyiapkan database

1. Buat database `sarinah` menggunakan MySQL atau MariaDB.
2. Import schema dari sumber lokal yang sudah dianonimkan.
3. Jalankan migration SQL di folder ini sesuai urutan tanggal pada nama file.
4. Gunakan data fixture anonim untuk pengujian.

Dump database lokal yang berisi data pelanggan, supplier, transaksi, atau akun pengguna tidak boleh dipublikasikan. File dump lokal di root sengaja dikecualikan oleh `.gitignore`.

## Akun demo

Buat akun demo baru pada database lokal. Skenario Selenium menggunakan placeholder `CHANGE_ME`; ganti nilainya hanya pada salinan lokal sebelum menjalankan test. Jangan menaruh password akun demo atau hash password produksi di repository.