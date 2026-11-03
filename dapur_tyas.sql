-- ==========================================================
-- DATABASE SYSTEM DAPUR TYAS by TECNO GARAGE
-- Phase 02: MySQL Relational Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+ / phpMyAdmin
-- ==========================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- 1. Table: users (Admin & Kasir)
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_details`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `tables`;
DROP TABLE IF EXISTS `menus`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir',
    `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Users:
-- admin / admin123
-- kasir / kasir123
INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `status`) VALUES
(1, 'Administrator Dapur Tyas', 'admin', '$2y$10$PiF9mqOuXODvPGQWgT6tUuGXFgpb854fOwNFjV3nVlVNK.wV.XtlO', 'admin', 'aktif'),
(2, 'Kasir Utama', 'kasir', '$2y$10$9fJUtqVCnsC.ZXO1bqYlH.ft98qQC0R/4x3C5UjPFHxrEpkHNgaJK', 'kasir', 'aktif');

-- ----------------------------------------------------------
-- 2. Table: categories (Kategori Menu)
-- ----------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama_kategori` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(60) NOT NULL UNIQUE,
    `urutan` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_categories_urutan` (`urutan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `nama_kategori`, `slug`, `urutan`) VALUES
(1, 'Makanan', 'makanan', 1),
(2, 'Minuman', 'minuman', 2),
(3, 'Snack', 'snack', 3);

-- ----------------------------------------------------------
-- 3. Table: menus (Daftar Menu Makanan/Minuman/Snack)
-- ----------------------------------------------------------
CREATE TABLE `menus` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `kode_menu` VARCHAR(20) NOT NULL UNIQUE,
    `nama_menu` VARCHAR(100) NOT NULL,
    `deskripsi` TEXT NULL,
    `foto` VARCHAR(255) DEFAULT 'default_menu.png',
    `harga_modal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `harga_jual` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `stok` INT NOT NULL DEFAULT 0,
    `min_stok` INT NOT NULL DEFAULT 5,
    `status` ENUM('tersedia', 'habis', 'nonaktif') NOT NULL DEFAULT 'tersedia',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_menus_category` (`category_id`),
    INDEX `idx_menus_status` (`status`),
    INDEX `idx_menus_stok` (`stok`),
    CONSTRAINT `fk_menus_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Initial Data Menu
INSERT INTO `menus` (`id`, `category_id`, `kode_menu`, `nama_menu`, `deskripsi`, `foto`, `harga_modal`, `harga_jual`, `stok`, `min_stok`, `status`) VALUES
(1, 1, 'MKN-001', 'Nasi Ayam Bakar Spesial Tyas', 'Ayam bakar dengan bumbu rempah khas Dapur Tyas disajikan dengan sambal dan lalapan segar.', 'ayam_bakar.png', 15000.00, 25000.00, 50, 5, 'tersedia'),
(2, 1, 'MKN-002', 'Nasi Goreng Kampung', 'Nasi goreng autentik dengan telur, suwiran ayam, acar, dan kerupuk.', 'nasgor_kampung.png', 10000.00, 18000.00, 40, 5, 'tersedia'),
(3, 2, 'MNM-001', 'Es Teh Manis Tyas', 'Es teh racikan wangi dan menyegarkan.', 'es_teh.png', 2000.00, 5000.00, 100, 10, 'tersedia'),
(4, 2, 'MNM-002', 'Kopi Susu Gula Aren', 'Kopi robusta berpadu susu segar dan gula aren murni.', 'kopi_aren.png', 7000.00, 15000.00, 60, 10, 'tersedia'),
(5, 3, 'SNK-001', 'Pisang Goreng Crispy', 'Pisang renyah topping keju dan cokelat leleh.', 'pisang_crispy.png', 6000.00, 12000.00, 30, 5, 'tersedia'),
(6, 3, 'SNK-002', 'Tahu Walik Pedas', 'Tahu walik isi daging gurih dengan cocolan sambal kecap pedas.', 'tahu_walik.png', 7000.00, 14000.00, 35, 5, 'tersedia'),
(7, 1, 'MKN-003', 'Nasi Ayam Geprek Sambal Bawang', 'Ayam geprek ditepung renyah dengan sambal bawang pedas, disajikan dengan nasi dan lalapan.', 'ayam_geprek.jpg', 12000.00, 22000.00, 45, 5, 'tersedia'),
(8, 1, 'MKN-004', 'Nasi Rendang Sapi', 'Rendang sapi empuk dimasak lama dengan santan dan bumbu rempah khas.', 'rendang.jpg', 18000.00, 30000.00, 30, 5, 'tersedia'),
(9, 1, 'MKN-005', 'Soto Ayam Lamongan', 'Kuah bening gurih dengan suwiran ayam, telur, dan koya renyah.', 'soto_ayam.jpg', 11000.00, 20000.00, 40, 5, 'tersedia'),
(10, 1, 'MKN-006', 'Rawon Daging Sapi', 'Sup rawon hitam pekat dengan daging sapi empuk, tauge, dan sambal.', 'rawon.jpg', 17000.00, 28000.00, 25, 5, 'tersedia'),
(11, 1, 'MKN-007', 'Mie Goreng Jawa Special', 'Mie goreng jawa dengan sayur, telur, dan taburan bakwan renyah.', 'mie_goreng.jpg', 9000.00, 17000.00, 45, 5, 'tersedia'),
(12, 1, 'MKN-008', 'Ayam Goreng Kremes', 'Ayam goreng kremes renyah dengan sambal dan lalapan segar.', 'ayam_kremes.jpg', 14000.00, 24000.00, 35, 5, 'tersedia'),
(13, 1, 'MKN-009', 'Ikan Gurame Bakar', 'Ikan gurame dibakar dengan bumbu kecap manis dan sambal dabu-dabu.', 'gurame_bakar.jpg', 22000.00, 38000.00, 20, 5, 'tersedia'),
(14, 1, 'MKN-010', 'Nasi Kuning Komplit', 'Nasi kuning dengan telur balado, ayam suwir, mie, dan perkedel.', 'nasi_kuning.jpg', 13000.00, 23000.00, 30, 5, 'tersedia'),
(15, 2, 'MNM-003', 'Es Jeruk Peras', 'Jeruk peras segar dengan es batu dan gula pasir.', 'es_jeruk.jpg', 3500.00, 8000.00, 60, 10, 'tersedia'),
(16, 2, 'MNM-004', 'Es Campur', 'Es campur santan, cincau, selasih, dan buah-buahan segar.', 'es_campur.jpg', 6000.00, 13000.00, 40, 10, 'tersedia'),
(17, 2, 'MNM-005', 'Es Dawet Cendol', 'Cendol hijau dengan santan gula merah dan es serut.', 'cendol.jpg', 4000.00, 10000.00, 45, 10, 'tersedia'),
(18, 2, 'MNM-006', 'Jus Alpukat', 'Jus alpukat lembut dengan susu kental manis dan serutan cokelat.', 'jus_alpukat.jpg', 7000.00, 15000.00, 35, 10, 'tersedia'),
(19, 2, 'MNM-007', 'Jus Mangga', 'Jus mangga manis dari mangga matang pilihan.', 'jus_mangga.jpg', 6000.00, 14000.00, 30, 10, 'tersedia'),
(20, 2, 'MNM-008', 'Es Kelapa Muda', 'Air kelapa muda segar dengan daging kelapa dan es batu.', 'es_kelapa_muda.jpg', 7000.00, 15000.00, 40, 10, 'tersedia'),
(21, 3, 'SNK-003', 'Tempe Mendoan', 'Tempe mendoan tipis dengan tepung bumbu dan sambal kecap.', 'tempe_mendoan.jpg', 3000.00, 8000.00, 50, 5, 'tersedia'),
(22, 3, 'SNK-004', 'Cireng Isi Ayam', 'Cireng kenyal dengan isian ayam suwir bumbu pedas.', 'cireng.jpg', 5000.00, 11000.00, 40, 5, 'tersedia'),
(23, 3, 'SNK-005', 'Kentang Goreng', 'Kentang goreng renyah dengan saus tomat dan mayonnaise.', 'kentang_goreng.jpg', 5000.00, 12000.00, 45, 5, 'tersedia'),
(24, 3, 'SNK-006', 'Singkong Keju', 'Singkong goreng empuk dengan taburan keju parut.', 'singkong_keju.jpg', 4500.00, 10000.00, 40, 5, 'tersedia'),
(25, 3, 'SNK-007', 'Roti Bakar Coklat Keju', 'Roti bakar dengan isian coklat leleh dan keju parut.', 'roti_bakar.jpg', 6000.00, 14000.00, 30, 5, 'tersedia'),
(26, 3, 'SNK-008', 'Tahu Crispy Sambal Rujak', 'Tahu crispy gurih dengan sambal rujak pedas manis.', 'tahu_crispy.jpg', 4500.00, 10000.00, 45, 5, 'tersedia');

-- ----------------------------------------------------------
-- 4. Table: tables (Meja & QR Code Identifier)
-- ----------------------------------------------------------
CREATE TABLE `tables` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nomor_meja` VARCHAR(20) NOT NULL UNIQUE,
    `kapasitas` INT NOT NULL DEFAULT 4,
    `status` ENUM('kosong', 'terisi', 'reservasi') NOT NULL DEFAULT 'kosong',
    `qr_identifier` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tables_status` (`status`),
    INDEX `idx_tables_qr` (`qr_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tables` (`id`, `nomor_meja`, `kapasitas`, `status`, `qr_identifier`) VALUES
(1, 'Meja 01', 4, 'kosong', 'QR-MEJA-01-DT'),
(2, 'Meja 02', 4, 'kosong', 'QR-MEJA-02-DT'),
(3, 'Meja 03', 2, 'kosong', 'QR-MEJA-03-DT'),
(4, 'Meja 04', 6, 'kosong', 'QR-MEJA-04-DT'),
(5, 'Meja 05', 4, 'kosong', 'QR-MEJA-05-DT');

-- ----------------------------------------------------------
-- 5. Table: orders (Header Pesanan Restoran)
-- ----------------------------------------------------------
CREATE TABLE `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nomor_order` VARCHAR(50) NOT NULL UNIQUE,
    `table_id` INT UNSIGNED NULL,
    `customer_token` VARCHAR(100) NOT NULL,
    `customer_name` VARCHAR(100) DEFAULT 'Pelanggan',
    `order_type` ENUM('dine_in', 'take_away') NOT NULL DEFAULT 'dine_in',
    `status_pesanan` ENUM('menunggu', 'diterima', 'dimasak', 'diantar', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'menunggu',
    `status_pembayaran` ENUM('belum_bayar', 'lunas') NOT NULL DEFAULT 'belum_bayar',
    `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `catatan` TEXT NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'Kasir yang menangani pesanan',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_orders_status_pesanan` (`status_pesanan`),
    INDEX `idx_orders_status_pembayaran` (`status_pembayaran`),
    INDEX `idx_orders_token` (`customer_token`),
    INDEX `idx_orders_created` (`created_at`),
    CONSTRAINT `fk_orders_table` FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 6. Table: order_details (Rincian Item Pesanan)
-- ----------------------------------------------------------
CREATE TABLE `order_details` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `menu_id` INT UNSIGNED NOT NULL,
    `harga_satuan` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `quantity` INT NOT NULL DEFAULT 1,
    `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `catatan` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_details_order` (`order_id`),
    INDEX `idx_details_menu` (`menu_id`),
    CONSTRAINT `fk_details_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_details_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 7. Table: payments (Transaksi Pembayaran Kasir)
-- ----------------------------------------------------------
CREATE TABLE `payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `nomor_transaksi` VARCHAR(50) NOT NULL UNIQUE,
    `metode_pembayaran` ENUM('tunai', 'qris', 'transfer') NOT NULL,
    `total_tagihan` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `nominal_bayar` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `kembalian` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('lunas', 'menunggu', 'gagal') NOT NULL DEFAULT 'lunas',
    `user_id` INT UNSIGNED NOT NULL COMMENT 'Kasir penerima pembayaran',
    `paid_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_payments_order` (`order_id`),
    INDEX `idx_payments_method` (`metode_pembayaran`),
    INDEX `idx_payments_paid_at` (`paid_at`),
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 8. Table: expenses (Pencatatan Pengeluaran Operasional)
-- ----------------------------------------------------------
CREATE TABLE `expenses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nama_pengeluaran` VARCHAR(150) NOT NULL,
    `kategori_pengeluaran` VARCHAR(100) NOT NULL DEFAULT 'Operasional Resto',
    `nominal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tanggal` DATE NOT NULL,
    `keterangan` TEXT NULL,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'Admin/staff pencatat pengeluaran',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_expenses_tanggal` (`tanggal`),
    INDEX `idx_expenses_kategori` (`kategori_pengeluaran`),
    CONSTRAINT `fk_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 9. Table: stock_movements (Histori Mutasi Stok Menu/Bahan)
-- ----------------------------------------------------------
CREATE TABLE `stock_movements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `menu_id` INT UNSIGNED NOT NULL,
    `tipe` ENUM('masuk', 'keluar', 'penyesuaian') NOT NULL,
    `jumlah` INT NOT NULL,
    `stok_sebelum` INT NOT NULL,
    `stok_sesudah` INT NOT NULL,
    `keterangan` VARCHAR(255) NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'Staff yang melakukan mutasi stok',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_stock_menu` (`menu_id`),
    INDEX `idx_stock_created` (`created_at`),
    CONSTRAINT `fk_stock_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_stock_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 10. Table: settings (Pengaturan Aplikasi & Restoran)
-- ----------------------------------------------------------
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(50) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `keterangan` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `keterangan`) VALUES
('nama_restoran', 'DAPUR TYAS', 'Nama identitas utama restoran'),
('sub_brand', 'by TECNO GARAGE', 'Sub-brand / tagline sistem'),
('alamat', 'Jl. Kuliner Nusantara No. 88, Medan', 'Alamat lengkap restoran'),
('telepon', '081234567890', 'Nomor kontak telepon / WhatsApp'),
('footer_struk', 'Terima kasih atas kunjungan Anda di Dapur Tyas!\nFollow Instagram kami: @dapurtyas.id', 'Pesan footer di struk belanja'),
('pajak_persen', '0', 'Tarif PPN dalam persentase (0 jika sudah termasuk)'),
('mode_pembayaran_qris', 'simulasi', 'Mode pembayaran QRIS (simulasi/live)'),
('logo_restoran', 'logo.png', 'File logo resmi restoran');

-- ----------------------------------------------------------
-- 11. Table: order_status_history (Histori Tracking Pesanan)
-- ----------------------------------------------------------
CREATE TABLE `order_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `status_sebelum` VARCHAR(50) NULL,
    `status_sesudah` VARCHAR(50) NOT NULL,
    `user_id` INT UNSIGNED NULL COMMENT 'Kasir/admin perubah status, NULL jika sistem/pelanggan',
    `catatan` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_history_order` (`order_id`),
    INDEX `idx_history_created` (`created_at`),
    CONSTRAINT `fk_history_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

