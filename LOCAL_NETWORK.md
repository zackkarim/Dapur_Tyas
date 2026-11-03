# PANDUAN PENGUJIAN SERVER LOKAL & HOTSPOT LAPTOP
## DAPUR TYAS by TECNO GARAGE
**MASAKAN RUMAHAN YANG LEZAT**

---

Panduan ini berisi instruksi lengkap langkah demi langkah untuk menjalankan aplikasi **DAPUR TYAS** dalam lingkungan jaringan lokal (Local Offline / Hotspot Laptop) tanpa memerlukan koneksi internet eksternal.

---

## 1. Topologi Jaringan Local Server

```
                 ┌──────────────────────────────────────────────┐
                 │                LAPTOP SERVER                 │
                 │  - XAMPP (Apache Port 80, MySQL Port 3306)   │
                 │  - Mobile Hotspot Windows (WiFi Aktif)       │
                 │  - IP Laptop: 192.168.137.1                  │
                 └──────────────────────┬───────────────────────┘
                                        │
                         (Hotspot WiFi Jaringan Lokal)
                                        │
           ┌────────────────────────────┼────────────────────────────┐
           ▼                            ▼                            ▼
   ┌───────────────┐            ┌───────────────┐            ┌───────────────┐
   │ HP PELANGGAN  │            │ HP PELANGGAN  │            │  KASIR TABLET │
   │   (Meja 01)   │            │   (Meja 05)   │            │    / LAPTOP   │
   └───────────────┘            └───────────────┘            └───────────────┘
```

---

## 2. Cara Menyalakan XAMPP (Apache & MySQL)

1. Buka aplikasi **XAMPP Control Panel** di Laptop (Run as Administrator jika diperlukan).
2. Pada baris **Apache**, klik tombol **Start** (Pastikan indikator berubah menjadi hijau / Port 80, 443 aktif).
3. Pada baris **MySQL**, klik tombol **Start** (Pastikan indikator berubah menjadi hijau / Port 3306 aktif).
4. Pastikan database `dapur_tyas` sudah terpasang. Jika belum, import file `database/dapur_tyas.sql` melalui phpMyAdmin (`http://localhost/phpmyadmin`).

---

## 3. Cara Mengaktifkan Mobile Hotspot di Laptop (Windows 10 / 11)

1. Buka **Windows Settings** (Tekan tombol `Win + I`).
2. Pilih menu **Network & Internet** &rarr; **Mobile Hotspot**.
3. Klik tombol toggle menjadi **ON**.
4. Atur nama jaringan WiFi (SSID) dan kata sandi:
   - Contoh Nama WiFi: `DAPUR_TYAS_WIFI`
   - Contoh Sandi: `dapurtyas123`
5. Pilih **Share over: Wi-Fi**.

---

## 4. Cara Mengetahui IP Address Lokal Laptop

### Cara A: Melalui Command Prompt / PowerShell
1. Tekan tombol `Win + R`, ketik `cmd` atau `powershell`, lalu tekan Enter.
2. Ketik perintah:
   ```cmd
   ipconfig
   ```
3. Cari adapter bernama **Wireless LAN adapter Wi-Fi** atau **Local Area Connection* X** (Microsoft Wi-Fi Direct Virtual Adapter).
4. Lihat baris **IPv4 Address**.
   - Pada Windows Mobile Hotspot default, IP laptop biasanya: **`192.168.137.1`**
   - Pada router Wi-Fi rumah/kantor, IP laptop biasanya: **`192.168.1.xxx`** atau **`192.168.0.xxx`**

### Cara B: Melalui Panel Admin Dapur Tyas
- Buka dashboard Admin di browser: `http://localhost/dapur_tyas/admin/pengaturan/`
- Sistem akan secara otomatis mendeteksi dan menampilkan daftar IP Address lokal laptop yang aktif di layar.

---

## 5. Cara Mengatur `LOCAL_BASE_URL`

Sistem Dapur Tyas telah dirancang fleksibel. Anda hanya perlu mengubah konfigurasi di **satu tempat**:

### Opsi 1: Melalui Panel Admin (Paling Mudah)
1. Buka browser di laptop: `http://localhost/dapur_tyas/admin/pengaturan/`
2. Login sebagai Admin (Username: `admin`, Sandi: `admin123`).
3. Pada kartu **Server Lokal & Hotspot Laptop**, masukkan URL IP Laptop Anda:
   ```text
   http://192.168.137.1:8080/dapur_tyas
   ```
4. Klik **Simpan Seluruh Pengaturan**. Semua QR Code meja dan endpoint pelanggan langsung tersinkronisasi.

### Opsi 2: Melalui File `config/app.php`
1. Buka file `config/app.php` menggunakan text editor.
2. Cari baris konstanta `LOCAL_BASE_URL_DEFAULT`:
   ```php
   defined('LOCAL_BASE_URL_DEFAULT') or define('LOCAL_BASE_URL_DEFAULT', 'http://192.168.137.1:8080/dapur_tyas');
   ```
3. Simpan file.

---

## 6. Daftar URL Sistem Dapur Tyas

Setelah `LOCAL_BASE_URL` diatur (contoh IP `192.168.137.1`):

| Halaman | URL Akses Lokal | Keterangan |
|---|---|---|
| **Portal Pelanggan (Umum)** | `http://192.168.137.1:8080/dapur_tyas/customer/` | Membuka katalog dan memilih meja |
| **Meja 01** | `http://192.168.137.1:8080/dapur_tyas/customer/?meja=01` | Otomatis masuk sebagai Meja 01 |
| **Meja 02** | `http://192.168.137.1:8080/dapur_tyas/customer/?meja=02` | Otomatis masuk sebagai Meja 02 |
| **Meja 03** | `http://192.168.137.1:8080/dapur_tyas/customer/?meja=03` | Otomatis masuk sebagai Meja 03 |
| **Meja 04** | `http://192.168.137.1:8080/dapur_tyas/customer/?meja=04` | Otomatis masuk sebagai Meja 04 |
| **Meja 05** | `http://192.168.137.1:8080/dapur_tyas/customer/?meja=05` | Otomatis masuk sebagai Meja 05 |
| **Kasir POS** | `http://192.168.137.1:8080/dapur_tyas/kasir/` | Workstation kasir & live order queue |
| **Admin Panel** | `http://192.168.137.1:8080/dapur_tyas/admin/` | Pengaturan resto, menu, meja, & QR |
| **Login Staff** | `http://192.168.137.1:8080/dapur_tyas/auth/login.php` | Autentikasi kasir & admin |

---

## 7. Cara Menyambungkan HP Pelanggan & Melakukan Pemesanan

> **CATATAN (sementara):** Stiker QR / barcode meja **belum aktif**. Pelanggan cukup memilih meja secara manual di layar.

1. Di HP pelanggan, aktifkan **Wi-Fi**.
2. Sambungkan ke jaringan Hotspot Laptop (misal: `DAPUR_TYAS_WIFI`).
3. Buka alamat menu di browser HP (Chrome / Safari): `http://192.168.137.1:8080/dapur_tyas/customer/`
   (atau buka `http://192.168.137.1:8080/dapur_tyas/customer/?meja=01` untuk langsung masuk sebagai Meja 01).
4. Jendela **"SELAMAT DATANG"** muncul otomatis. Pelanggan menekan nomor meja tempat duduknya (misal: **Meja 05**).
5. Sistem Dapur Tyas akan terbuka dengan nomor meja yang terdeteksi.
6. Pelanggan memilih hidangan makanan, minuman, dan snack, menambahkan catatan porsi, lalu menekan tombol **Kirim Pesanan**.
7. Layar HP pelanggan akan berpindah ke halaman **Live Tracking Status** (`#DT-XXXX`).

> Jika nomor meja sudah dipilih sebelumnya, cukup gunakan tombol **"Pilih Meja"** di pojok kanan atas header.

---

## 8. Alur Simulasi Kasir & Real-Time Tracking

1. Buka layar **Kasir POS** di laptop: `http://localhost/dapur_tyas/kasir/`
2. Ketika pelanggan mengirim pesanan dari HP, kasir akan mendengar nada notifikasi lembut (*chime*) dan kartu pesanan meja akan muncul secara otomatis:
   - Status Awal: `[ 🔔 Pesanan Baru ]`
3. Kasir menekan **Terima Pesanan** &rarr; Dapur menerima antrean.
4. Kasir menekan **Sedang Dibuat** &rarr; Layar HP pelanggan langsung berganti status ke *Sedang Dibuat*.
5. Kasir menekan **Siap Diantar** &rarr; Layar HP pelanggan berganti status ke *Siap Diantar*.
6. Pelayan mengantarkan pesanan & kasir menekan **BAYAR** (Pilih Tunai/QRIS) &rarr; Kasir dapat mencetak struk thermal belanja.
7. Kasir menekan **Selesaikan** &rarr; Layar HP pelanggan menampilkan status *Selesai*.

---

## 9. Troubleshooting Windows Defender Firewall

Jika HP pelanggan gagal membuka alamat web (`ERR_CONNECTION_TIMED_OUT` atau tidak bisa diakses):

### Langkah Mengizinkan Port 80 Apache di Windows Firewall:
1. Tekan tombol `Win + R`, ketik `wf.msc`, lalu tekan Enter (Membuka *Windows Defender Firewall with Advanced Security*).
2. Klik **Inbound Rules** di panel sebelah kiri.
3. Klik **New Rule...** di panel sebelah kanan.
4. Pilih **Port** &rarr; klik **Next**.
5. Pilih **TCP**, pada kolom **Specific local ports**, ketik: `80, 443, 8080` &rarr; klik **Next**.
6. Pilih **Allow the connection** &rarr; klik **Next**.
7. Centang ketiga opsi: **Domain**, **Private**, dan **Public** &rarr; klik **Next**.
8. Beri nama: `Apache XAMPP Web Server` &rarr; klik **Finish**.
9. Coba refresh browser di HP pelanggan.

---

## 10. Catatan Penting

- **JANGAN menggunakan `localhost`** pada URL atau QR Code yang dibagikan ke HP pelanggan, karena `localhost` pada HP akan mengarah ke perangkat HP itu sendiri, bukan ke laptop server.
- **Koneksi Internet TIDAK DIPERLUKAN**. Sistem ini bekerja 100% offline secara lokal menggunakan jaringan hotspot laptop dan database MySQL XAMPP.
- Pastikan laptop server tidak dalam mode *Sleep* saat pengujian berlangsung.

---
**DAPUR TYAS** — Masakan Rumahan yang Lezat &bull; *by TECNO GARAGE*
