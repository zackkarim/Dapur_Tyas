Gunakan MASTER RULE PROJECT DAPUR TYAS.

# PHASE 02 — DATABASE MYSQL

Berdasarkan rancangan Phase 01, sekarang buat database MySQL.

Database minimal harus memiliki:

### users

Untuk admin dan kasir.

### categories

Untuk:

* Makanan
* Minuman
* Snack

### menus

Menyimpan:

* nama
* kategori
* foto
* deskripsi
* harga modal
* harga jual
* stok
* minimum stok
* status

### tables

Menyimpan:

* nomor meja
* kapasitas
* status
* QR identifier

### orders

Menyimpan:

* nomor order
* meja
* customer session/token
* status pesanan
* status pembayaran
* total
* catatan
* waktu

### order_details

Menyimpan:

* order
* menu
* harga saat order
* quantity
* subtotal
* catatan

### payments

Menyimpan:

* order
* metode pembayaran
* nominal
* status
* waktu pembayaran

### expenses

Menyimpan:

* kategori
* nominal
* tanggal
* deskripsi

### stock_movements

Menyimpan histori perubahan stok.

### settings

Untuk pengaturan restoran.

### order_status_history

Menyimpan histori perubahan status pesanan.

Gunakan:

* primary key
* foreign key
* index
* timestamp
* decimal untuk uang
* enum/status yang masuk akal

Pastikan database dapat mendukung:

QR meja
→ order pelanggan
→ kasir
→ pembayaran
→ tracking
→ laporan.

Buat file SQL siap import ke phpMyAdmin.

JANGAN membuat UI.

Tunggu Phase 03.


