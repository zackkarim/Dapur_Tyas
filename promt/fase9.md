Gunakan MASTER RULE PROJECT DAPUR TYAS.

# PHASE 09 — STOCK MANAGEMENT

Sekarang buat sistem manajemen stok.

Admin dapat:

* melihat stok
* menambah stok
* mengurangi stok
* melihat stok minimum
* melihat stok habis
* melihat histori perubahan stok

Status otomatis:

🟢 AMAN
🟡 MENIPIS
🔴 HABIS

Buat halaman:

/admin/stock.php

Tampilkan:

Nama Menu
Stok
Minimum
Status
Aksi

Setiap perubahan stok harus dicatat ke:

stock_movements

Simpan:

* menu
* tipe perubahan
* jumlah
* stok sebelum
* stok sesudah
* alasan
* user
* waktu

Jangan mengizinkan stok menjadi negatif.

Nantinya ketika order pelanggan dibayar/ditetapkan sesuai alur transaksi, stok harus dapat dikurangi secara aman.

Untuk fase ini fokus pada manajemen stok admin terlebih dahulu.

Tunggu Phase 10.
