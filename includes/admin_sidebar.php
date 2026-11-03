<?php
/**
 * Admin Sidebar Partial - DAPUR TYAS by TECNO GARAGE
 */
$activeMenu = $activeMenu ?? 'dashboard';
$currentUser = getAuthUser();
$restoName = getSetting('nama_restoran', 'DAPUR TYAS');
$restoSub  = getSetting('sub_brand', 'by TECNO GARAGE');
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Logo -->
    <div class="sidebar-brand" style="padding: 16px 20px 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.08); justify-content: center; text-align: center;">
        <a href="<?= BASE_URL ?>/admin/index.php" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; width: 100%;">
            <div style="width: 72px; height: 72px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.35); border: 3px solid rgba(255,255,255,0.9);">
                <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="Logo Dapur Tyas"
                     style="width: 100%; height: 100%; object-fit: cover; display: block;">
            </div>
            <div class="brand-text" style="text-align: center;">
                <h2 style="font-size: 1.05rem; font-weight: 800; color: #FFFFFF; letter-spacing: -0.3px; margin: 0; line-height: 1.2;"><?= htmlspecialchars($restoName) ?></h2>
                <span style="font-size: 0.68rem; color: var(--accent-gold); text-transform: uppercase; letter-spacing: 1px; font-weight: 700;"><?= htmlspecialchars($restoSub) ?></span>
            </div>
        </a>
    </div>

    <!-- Navigation Links -->
    <ul class="sidebar-menu">
        <li class="sidebar-heading">Menu Utama</li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/index.php" class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="sidebar-heading">Katalog & Stok</li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/menu/index.php" class="nav-link <?= $activeMenu === 'menu' ? 'active' : '' ?>">
                <i class="fa-solid fa-bowl-food"></i>
                <span>Menu Restoran</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/kategori/index.php" class="nav-link <?= $activeMenu === 'kategori' ? 'active' : '' ?>">
                <i class="fa-solid fa-tags"></i>
                <span>Kategori Menu</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/stok/index.php" class="nav-link <?= $activeMenu === 'stok' ? 'active' : '' ?>">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>Manajemen Stok</span>
            </a>
        </li>

        <li class="sidebar-heading">Operasional Meja & Order</li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/meja/index.php" class="nav-link <?= $activeMenu === 'meja' ? 'active' : '' ?>">
                <i class="fa-solid fa-chair"></i>
                <span>Manajemen Meja</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/pesanan/index.php" class="nav-link <?= $activeMenu === 'pesanan' ? 'active' : '' ?>">
                <i class="fa-solid fa-receipt"></i>
                <span>Pesanan Meja</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/transaksi/index.php" class="nav-link <?= $activeMenu === 'transaksi' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Riwayat Transaksi</span>
            </a>
        </li>

        <li class="sidebar-heading">Finansial & Laporan</li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/keuangan/index.php" class="nav-link <?= $activeMenu === 'keuangan' ? 'active' : '' ?>">
                <i class="fa-solid fa-wallet"></i>
                <span>Pengeluaran Resto</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/laporan/index.php" class="nav-link <?= $activeMenu === 'laporan' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-invoice-dollar"></i>
                <span>Laporan & Laba/Rugi</span>
            </a>
        </li>

        <li class="sidebar-heading">Manajemen Staff & Sistem</li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/kasir/index.php" class="nav-link <?= $activeMenu === 'kasir' ? 'active' : '' ?>">
                <i class="fa-solid fa-users-gear"></i>
                <span>Kelola Kasir</span>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?= BASE_URL ?>/admin/pengaturan/index.php" class="nav-link <?= $activeMenu === 'pengaturan' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear"></i>
                <span>Pengaturan Resto</span>
            </a>
        </li>

        <li class="nav-item" style="margin-top: 12px;">
            <a href="<?= BASE_URL ?>/auth/logout.php" class="nav-link" style="color: #F87171;" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
                <i class="fa-solid fa-arrow-right-from-bracket" style="color: #F87171;"></i>
                <span>Keluar (Logout)</span>
            </a>
        </li>
    </ul>

    <!-- Admin Profile Bottom Badge -->
    <div class="sidebar-footer">
        <div class="user-profile-badge">
            <div class="avatar-circle">
                <?= strtoupper(substr($currentUser['nama'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-info-text">
                <div class="user-name"><?= htmlspecialchars($currentUser['nama'] ?? 'Administrator') ?></div>
                <div class="user-role"><?= htmlspecialchars($currentUser['role'] ?? 'admin') ?></div>
            </div>
        </div>
    </div>
</aside>
