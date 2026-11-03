Gunakan MASTER RULE PROJECT DAPUR TYAS.

## PHASE 16 — CUSTOMER ORDER TRACKING

Setelah pelanggan mengirim pesanan, tampilkan halaman tracking.

Contoh:

DAPUR TYAS

Meja 05

Order #DT-20260922-001

STATUS PESANAN

● Pesanan Diterima
│
● Sedang Diproses
│
○ Sedang Dibuat
│
○ Sedang Diantar
│
○ Selesai

Status harus berasal dari database.

Tambahkan detail:

Pesanan:
Ayam Geprek × 2
Es Teh × 2

Total:
Rp46.000

Pelanggan dapat membuka halaman tracking tanpa login.

Gunakan token/order ID yang aman agar pelanggan tidak bisa melihat order pelanggan lain hanya dengan mengganti ID secara sembarangan.

Tambahkan tombol:

[ Pesan Lagi ]

Jika status berubah di kasir, halaman pelanggan harus dapat memperbarui status.

Buat polling AJAX sederhana terlebih dahulu jika belum menggunakan WebSocket.

Tunggu Phase 17.
