Gunakan MASTER RULE PROJECT DAPUR TYAS.

# PHASE 29 — INTEGRASI BRANDING & GAMBAR DAPUR TYAS

Saya memberikan sebuah gambar resmi/identitas visual untuk restoran Dapur Tyas.

Gunakan gambar yang saya berikan sebagai **aset branding utama Dapur Tyas**.

Gambar tersebut berisi logo/ilustrasi:

**DAPUR TYAS**
**MASAKAN RUMAHAN YANG LEZAT**

Jangan membuat ulang logo tersebut menggunakan HTML/CSS.
Gunakan gambar asli yang saya berikan.

## PENEMPATAN GAMBAR

Gunakan gambar secara proporsional dan profesional pada bagian yang sesuai.

### 1. HALAMAN LOGIN

Tampilkan gambar sebagai bagian dari branding halaman login.

Buat komposisi:

Gambar Dapur Tyas
+
Form Login

Jangan membuat gambar terlalu besar sehingga form login terganggu.

---

### 2. ADMIN

Gunakan identitas Dapur Tyas pada:

* sidebar
* header jika diperlukan
* dashboard
* halaman utama admin

Untuk sidebar gunakan versi yang lebih kecil agar tidak memenuhi layar.

Contoh:

[ LOGO DAPUR TYAS ]

Dashboard
Menu
Kategori
Stok
Meja
Pesanan
Transaksi
Keuangan
Laporan
Kasir
Pengaturan

---

### 3. KASIR

Tampilkan branding Dapur Tyas pada bagian header/sidebar kasir.

Prioritaskan ruang untuk transaksi agar kasir tetap cepat bekerja.

---

### 4. HALAMAN PELANGGAN

Ini adalah bagian yang paling penting.

Gunakan gambar sebagai branding pada halaman yang dibuka pelanggan setelah scan QR meja.

Contoh:

[ GAMBAR DAPUR TYAS ]

DAPUR TYAS
Masakan Rumahan yang Lezat

Meja 05

[ Makanan ] [ Minuman ] [ Snack ]

Menu...

Gambar tidak boleh mengganggu proses pemesanan.

Gunakan ukuran responsive dan cocok untuk layar HP.

---

### 5. HALAMAN TRACKING PESANAN

Tampilkan branding Dapur Tyas secara lebih sederhana.

Contoh:

Logo
DAPUR TYAS

Order #DT-20260922-001

✓ Pesanan Diterima
✓ Sedang Dibuat
● Sedang Diantar
○ Selesai

---

### 6. STRUK

Gunakan gambar/logo sebagai bagian dari header struk jika ukurannya memungkinkan.

Contoh:

[ LOGO DAPUR TYAS ]

DAPUR TYAS
Masakan Rumahan yang Lezat

Order #DT-001
Meja 05

...

Pastikan gambar tidak membuat struk terlalu besar.

Untuk printer thermal, gunakan ukuran gambar yang optimal.

---

## PENYIMPANAN FILE

Simpan gambar sebagai aset project, misalnya:

/assets/images/dapur-tyas-logo.png

Jangan membuat banyak salinan gambar yang sama.

Gunakan satu sumber gambar dan panggil melalui path yang konsisten.

Jika perlu versi kecil untuk mobile, gunakan CSS/responsive image atau buat thumbnail hanya jika memang diperlukan.

---

## ATURAN DESAIN

Gambar merupakan identitas utama Dapur Tyas.

Jangan:

* mengubah logo secara sembarangan
* memberi filter aneh
* menambahkan neon
* memberi glow berlebihan
* memotong bagian penting logo
* meregangkan gambar
* mengubah warna asli
* menambahkan efek yang menghilangkan karakter gambar

Pertahankan nuansa:

**hangat**
**tradisional**
**rumahan**
**elegan**
**modern**

UI aplikasi harus mengikuti nuansa gambar tersebut.

Gunakan warna dari identitas visual gambar sebagai inspirasi desain:

* cokelat kayu
* krem
* terracotta
* hijau
* charcoal
* aksen hangat

Tetapi jangan membuat seluruh website berwarna cokelat tua.

Pastikan teks tetap mudah dibaca.

---

## RESPONSIVE

Pastikan gambar bekerja dengan baik pada:

* Desktop
* Laptop
* Tablet
* Smartphone

Khusus halaman pelanggan, prioritaskan smartphone.

Gunakan:

```css
width: 100%;
height: auto;
object-fit: contain;
```

atau teknik responsive yang sesuai.

Jangan memaksa gambar memenuhi layar.

---

## PENGGUNAAN LOGO DI QR MEJA

Pada halaman cetak QR meja, gunakan branding Dapur Tyas.

Contoh:

┌─────────────────────┐
│                     │
│    LOGO DAPUR TYAS  │
│                     │
│       [ QR ]        │
│                     │
│       MEJA 05       │
│                     │
│ Scan untuk Memesan  │
│                     │
└─────────────────────┘

Desain harus cocok untuk dicetak dan ditempel di meja restoran.

---

## HASIL AKHIR

Setelah implementasi, pastikan identitas Dapur Tyas terlihat konsisten di:

1. Login
2. Admin
3. Kasir
4. Halaman pelanggan
5. Halaman menu
6. Halaman tracking
7. QR meja
8. Struk

Jangan membuat halaman terlihat seperti template yang berbeda-beda.

Semua harus terasa sebagai **satu aplikasi Dapur Tyas**.

Setelah selesai tampilkan:

1. File gambar yang digunakan.
2. Lokasi penyimpanan gambar.
3. Halaman yang menggunakan gambar.
4. File yang diubah.
5. Screenshot/preview hasil jika environment mendukung.
6. Cara mengganti gambar/logo di kemudian hari.

Jangan melanjutkan ke final audit.



