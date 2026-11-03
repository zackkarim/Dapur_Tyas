<?php
/**
 * Customer Self-Ordering Portal - DAPUR TYAS by TECNO GARAGE
 * Tonggak utama: Mobile-First Catalog, Instant Filtering, Table Auto-Detection & Bottom Sheet Checkout
 * Fully self-contained assets (offline WiFi-friendly).
 */

require_once dirname(__DIR__) . '/config/app.php';

$pdo = db();

// Ambil input meja dari berbagai parameter (meja=05, meja=5, table=5, token=...)
$rawMeja = trim($_GET['meja'] ?? '');
$tableId = (int) ($_GET['table'] ?? 0);
$token = trim($_GET['token'] ?? '');

$tableInfo = null;

// 1. Cari berdasarkan parameter meja (e.g. 05, 5, "Meja 05")
if (!empty($rawMeja)) {
    $cleanNum = preg_replace('/^Meja\s*/i', '', $rawMeja);
    $stmt = $pdo->prepare("SELECT * FROM tables WHERE (nomor_meja = :raw OR nomor_meja = :formatted OR nomor_meja LIKE :likeNo OR id = :idNum) AND status != 'nonaktif' LIMIT 1");
    $stmt->execute([
        'raw'       => $rawMeja,
        'formatted' => 'Meja ' . sprintf('%02d', (int)$cleanNum),
        'likeNo'    => '%' . $cleanNum . '%',
        'idNum'     => (int)$cleanNum
    ]);
    $tableInfo = $stmt->fetch();
} elseif ($tableId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tables WHERE id = :id AND status != 'nonaktif' LIMIT 1");
    $stmt->execute(['id' => $tableId]);
    $tableInfo = $stmt->fetch();
} elseif (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM tables WHERE qr_identifier = :tok AND status != 'nonaktif' LIMIT 1");
    $stmt->execute(['tok' => $token]);
    $tableInfo = $stmt->fetch();
}

// 2. Simpan ke Session jika ditemukan, atau jika scan 1 QR umum (tanpa parameter meja), reset session agar wajib memilih meja terlebih dahulu
if ($tableInfo) {
    $_SESSION['customer_table_id'] = (int)$tableInfo['id'];
    $_SESSION['customer_table_no'] = $tableInfo['nomor_meja'];
    $_SESSION['customer_table_token'] = $tableInfo['qr_identifier'];
} else {
    unset($_SESSION['customer_table_id'], $_SESSION['customer_table_no'], $_SESSION['customer_table_token']);
    $tableInfo = null;
}

// Daftar semua meja aktif untuk Table Picker Modal
$allTables = $pdo->query("SELECT * FROM tables WHERE status != 'nonaktif' ORDER BY nomor_meja ASC")->fetchAll();

$currentTableId = $tableInfo ? (int)$tableInfo['id'] : 0;
$currentNomorMeja = $tableInfo ? $tableInfo['nomor_meja'] : '';
$currentTableToken = $tableInfo ? $tableInfo['qr_identifier'] : '';

// Kategori & Menu dari Database
$categories = $pdo->query("SELECT * FROM categories ORDER BY urutan ASC")->fetchAll();
$menus = $pdo->query("SELECT m.*, c.nama_kategori, c.slug AS cat_slug 
                      FROM menus m 
                      JOIN categories c ON m.category_id = c.id 
                      WHERE m.status != 'nonaktif' 
                      ORDER BY c.urutan ASC, m.nama_menu ASC")->fetchAll();

// Dynamic Restaurant Settings
$restoName     = getSetting('nama_restoran', 'DAPUR TYAS');
$restoSub      = getSetting('sub_brand', 'by TECNO GARAGE');
$restoTagline  = getSetting('tagline', 'MASAKAN RUMAHAN YANG LEZAT');
$restoAlamat   = getSetting('alamat', 'Jl. Kuliner Nusantara No. 88, Medan');
$restoTelp     = getSetting('telepon', '081234567890');
$restoWa       = getSetting('whatsapp', '081234567890');
$restoIg       = getSetting('instagram', '@dapurtyas.id');
$restoJam      = getSetting('jam_operasional', 'Setiap Hari: 10.00 - 22.00 WIB');
$restoTentang  = getSetting('tentang_restoran', 'Dapur Tyas menyajikan aneka hidangan masakan rumahan autentik Indonesia dengan bumbu rempah pilihan, cita rasa lezat, dan suasana hangat yang nyaman.');

$mapsQuery = urlencode(preg_replace('/\s+/', ' ', trim($restoAlamat)));
$waNumber = preg_replace('/[^0-9]/', '', $restoWa);
$igHandle = ltrim(trim($restoIg), '@');

// Foto "Menu Andalan" untuk dekorasi hero
$heroDishes = array_slice($menus, 0, 3);
$heroThumbs = [];
foreach ($heroDishes as $m) {
    $fn = $m['foto'] ?? 'default_menu.png';
    $u = BASE_URL . '/assets/images/' . $fn;
    if (!file_exists(ROOT_PATH . '/assets/images/' . $fn) && file_exists(ROOT_PATH . '/assets/images/menus/' . $fn)) {
        $u = BASE_URL . '/assets/images/menus/' . $fn;
    }
    $heroThumbs[] = [$m['nama_menu'], $u];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($restoName) ?> — <?= htmlspecialchars($restoTagline) ?></title>
    <meta name="description" content="<?= htmlspecialchars($restoTentang) ?>">

    <!-- PWA-ish / favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/promt/tambah_logo.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/promt/tambah_logo.png">

    <!-- Local Assets (Aman Tanpa Internet) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pelanggan.css?v=<?= time() ?>">
</head>
<body>

<div class="customer-container">
    <!-- ============ 1. TOP STICKY HEADER ============ -->
    <header class="customer-header">
        <a href="<?= BASE_URL ?>/customer/<?= $currentNomorMeja ? '?meja=' . urlencode(preg_replace('/^Meja\s*/i', '', $currentNomorMeja)) : '' ?>" class="customer-brand-link">
            <div class="customer-brand-logo-frame">
                <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="<?= htmlspecialchars($restoName) ?>">
            </div>
            <div class="brand-text-block">
                <div class="brand-main-title"><?= htmlspecialchars($restoName) ?></div>
                <div class="brand-tagline"><?= htmlspecialchars($restoTagline) ?></div>
            </div>
        </a>

        <div class="customer-header-actions">
            <button type="button" class="table-pill-btn <?= $currentNomorMeja ? 'active-selected' : 'table-pill-required' ?>" onclick="openTablePicker()" title="Pilih / Ganti Meja">
                <i class="fa-solid fa-chair"></i>
                <span id="headerTableLabel"><?= $currentNomorMeja ? htmlspecialchars($currentNomorMeja) : 'Pilih Meja' ?></span>
                <?php if ($currentNomorMeja): ?>
                    <span style="font-size: 0.72rem; opacity: 0.85;">(Ganti)</span>
                <?php endif; ?>
            </button>

            <button type="button" class="btn-header-cart" onclick="handleOpenCart()" title="Buka Keranjang Pesanan">
                <i class="fa-solid fa-cart-shopping"></i>
                <span class="header-cart-text">Keranjang</span>
                <span class="cart-badge-pill" id="headerCartCount" style="display: none;">0</span>
                <span class="header-cart-total" id="headerCartTotal" style="display: none;">Rp 0</span>
            </button>
        </div>
    </header>

    <!-- ============ 2. CUSTOMER HERO ============ -->
    <section class="customer-hero">
        <div class="hero-pattern" aria-hidden="true"></div>

        <div class="hero-layout-grid">
            <div class="hero-text-col">
                <div class="hero-emblem-badge">
                    <i class="fa-solid fa-wifi"></i>
                    <span>Mode Order via WiFi Lokal &bull; Meja Resto</span>
                </div>

                <h1 class="hero-heading-h1">
                    <?= htmlspecialchars($restoName) ?>
                    <span class="hero-heading-serif">Selamat Datang!</span>
                </h1>
                <div class="hero-tagline-text"><?= htmlspecialchars($restoTagline) ?></div>
                <p class="hero-quote-desc">
                    "Nikmati berbagai makanan, minuman, dan snack favorit dengan cita rasa rumahan yang lezat,
                    bumbu rempah autentik, disajikan hangat langsung dari dapur kami."
                </p>

                <div class="hero-btn-group">
                    <a href="#menuCatalogFeed" class="hero-cta-btn">
                        <i class="fa-solid fa-bowl-food"></i> LIHAT MENU SEKARANG
                    </a>
                    <button type="button" class="hero-table-btn" onclick="openTablePicker()">
                        <i class="fa-solid fa-chair"></i>
                        <span><?= $currentNomorMeja ? htmlspecialchars($currentNomorMeja) : 'Pilih Meja Saya' ?></span>
                    </button>
                </div>

                <!-- Wi-Fi ordering helper card -->
                <div class="wifi-order-help">
                    <div class="wifi-help-icon"><i class="fa-solid fa-utensils"></i></div>
                    <div class="wifi-help-text">
                        <strong>Panduan Pesan di Meja:</strong> Sambung WiFi &rarr; Pilih Nomor Meja &rarr; Pilih Menu &rarr; Kirim Pesanan ke Kasir/Dapur.
                    </div>
                </div>
            </div>

            <div class="hero-visual-col">
                <?php if (!empty($heroDishes)): ?>
                    <?php $featuredDish = $heroDishes[0]; ?>
                    <div class="hero-showcase-card">
                        <div class="showcase-badge"><i class="fa-solid fa-star"></i> Menu Andalan</div>
                        <img src="<?= $heroThumbs[0][1] ?>" alt="<?= htmlspecialchars($featuredDish['nama_menu']) ?>" class="showcase-img" loading="lazy">
                        <div class="showcase-overlay">
                            <div class="showcase-name"><?= htmlspecialchars($featuredDish['nama_menu']) ?></div>
                            <div class="showcase-row">
                                <span class="showcase-price"><?= formatRupiah($featuredDish['harga_jual']) ?></span>
                                <button type="button" class="btn-showcase-add" onclick='handleAddItem(<?= $featuredDish['id'] ?>, "<?= htmlspecialchars($featuredDish['nama_menu'], ENT_QUOTES) ?>", <?= (float)$featuredDish['harga_jual'] ?>, "<?= htmlspecialchars($featuredDish['foto'] ?? 'default_menu.png') ?>")'>
                                    <i class="fa-solid fa-plus"></i> Tambah
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="hero-floating-pills">
                        <div class="hero-float-pill float-pill-1">
                            <i class="fa-solid fa-fire-burner" style="color: var(--primary);"></i>
                            <div>
                                <strong>Masakan Hangat</strong>
                                <span>Dimasak fresh saat dipesan</span>
                            </div>
                        </div>
                        <div class="hero-float-pill float-pill-2">
                            <i class="fa-solid fa-award" style="color: #D97706;"></i>
                            <div>
                                <strong>100% Rasa Juara</strong>
                                <span>Resep otentik keluarga</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ============ 3. FEATURE STRIP ============ -->
    <section class="feature-strip">
        <div class="feature-item">
            <div class="feature-icon"><i class="fa-solid fa-hamsa"></i></div>
            <div>
                <div class="feature-title">100% Halal</div>
                <div class="feature-sub">Dijamin halal & higienis</div>
            </div>
        </div>
        <div class="feature-item">
            <div class="feature-icon"><i class="fa-solid fa-seedling"></i></div>
            <div>
                <div class="feature-title">Bahan Segar</div>
                <div class="feature-sub">Rempah pilihan setiap hari</div>
            </div>
        </div>
        <div class="feature-item">
            <div class="feature-icon"><i class="fa-solid fa-bolt"></i></div>
            <div>
                <div class="feature-title">Pesan Cepat</div>
                <div class="feature-sub">Langsung ke dapur, anti antre</div>
            </div>
        </div>
    </section>

    <!-- ============ 4. SEARCH BAR ============ -->
    <div class="customer-search-wrap">
        <div class="customer-search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="customerSearchInput" class="customer-search-input"
                   placeholder="Cari makanan, minuman, atau snack..." 
                   oninput="handleSearchFilter()">
        </div>
    </div>

    <!-- ============ 5. PILL-SHAPED CATEGORY FILTER TABS ============ -->
    <nav class="category-nav-wrap">
        <div class="category-tabs">
            <button type="button" class="cat-tab-btn active" data-slug="semua" onclick="handleCategoryFilter('semua')">
                <i class="fa-solid fa-border-all"></i> Semua
            </button>
            <?php foreach ($categories as $cat): ?>
                <?php
                $catIcon = 'fa-solid fa-utensils';
                $slugLower = strtolower($cat['slug']);
                if (str_contains($slugLower, 'makan')) $catIcon = 'fa-solid fa-bowl-rice';
                elseif (str_contains($slugLower, 'minum')) $catIcon = 'fa-solid fa-glass-water';
                elseif (str_contains($slugLower, 'snack') || str_contains($slugLower, 'cemil')) $catIcon = 'fa-solid fa-cookie-bite';
                ?>
                <button type="button" class="cat-tab-btn" data-slug="<?= htmlspecialchars($cat['slug']) ?>" onclick="handleCategoryFilter('<?= htmlspecialchars($cat['slug']) ?>')">
                    <i class="<?= $catIcon ?>"></i> <?= htmlspecialchars($cat['nama_kategori']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- ============ 6. MENU CATALOG FEED ============ -->
    <main id="menuCatalogFeed">
        <div class="menu-feed-header">
            <h2 class="menu-feed-title" id="activeCategoryHeadline">
                <i class="fa-solid fa-bowl-food" style="color: var(--primary); margin-right: 6px;"></i>Daftar Menu Pilihan
            </h2>
            <span class="menu-feed-count" id="menuCountDisplay"><?= count($menus) ?> menu tersedia</span>
        </div>

        <div class="menu-feed-grid" id="menuGridContainer">
            <?php foreach ($menus as $m): ?>
                <?php
                $isHabis = ($m['status'] === 'habis' || (int)$m['stok'] <= 0);
                $isFavorit = in_array((int)$m['id'], [1, 4], true);

                // Cari foto makanan lokal
                $fotoName = $m['foto'] ?? 'default_menu.png';
                $fotoUrl = BASE_URL . '/assets/images/' . $fotoName;
                if (!file_exists(ROOT_PATH . '/assets/images/' . $fotoName) && file_exists(ROOT_PATH . '/assets/images/menus/' . $fotoName)) {
                    $fotoUrl = BASE_URL . '/assets/images/menus/' . $fotoName;
                }
                ?>
                <article class="menu-card-item"
                     data-menu-id="<?= $m['id'] ?>"
                     data-menu-name="<?= htmlspecialchars($m['nama_menu'], ENT_QUOTES) ?>"
                     data-menu-desc="<?= htmlspecialchars($m['deskripsi'] ?? '', ENT_QUOTES) ?>"
                     data-category-slug="<?= htmlspecialchars($m['cat_slug']) ?>">

                    <div class="menu-thumb-wrap">
                        <img src="<?= $fotoUrl ?>" alt="<?= htmlspecialchars($m['nama_menu']) ?>" class="menu-thumb-img" loading="lazy" onerror="this.src='<?= BASE_URL ?>/assets/images/default_menu.png'">

                        <div class="menu-category-chip">
                            <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($m['nama_kategori']) ?>
                        </div>
                        <?php if ($isFavorit && !$isHabis): ?>
                            <div class="menu-fav-chip"><i class="fa-solid fa-star"></i> Favorit</div>
                        <?php endif; ?>

                        <?php if ($isHabis): ?>
                            <div class="menu-soldout-overlay"><span><i class="fa-solid fa-ban"></i> HABIS</span></div>
                        <?php endif; ?>
                    </div>

                    <div class="menu-info-content">
                        <div>
                            <h3 class="menu-title-text"><?= htmlspecialchars($m['nama_menu']) ?></h3>
                            <?php if (!empty($m['deskripsi'])): ?>
                                <p class="menu-desc-text"><?= htmlspecialchars($m['deskripsi']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="menu-bottom-row">
                            <div>
                                <div class="menu-price-text"><?= formatRupiah($m['harga_jual']) ?></div>
                                <?php if (!$isHabis): ?>
                                    <div class="menu-stock-text"><i class="fa-solid fa-circle-check"></i> Stok tersedia</div>
                                <?php endif; ?>
                            </div>

                            <div class="menu-action-box" data-menu-id="<?= $m['id'] ?>">
                                <?php if ($isHabis): ?>
                                    <button type="button" class="btn-add-item" disabled><i class="fa-solid fa-ban"></i> Habis</button>
                                <?php else: ?>
                                    <button type="button" class="btn-add-item"
                                            onclick='handleAddItem(<?= $m['id'] ?>, "<?= htmlspecialchars($m['nama_menu'], ENT_QUOTES) ?>", <?= (float)$m['harga_jual'] ?>, "<?= htmlspecialchars($fotoName) ?>")'>
                                        <i class="fa-solid fa-plus"></i> Tambah
                                    </button>
                                    <div class="qty-stepper" style="display: none;">
                                        <button type="button" class="btn-qty-mod" onclick="OrderApp.changeQty(<?= $m['id'] ?>, -1)"><i class="fa-solid fa-minus"></i></button>
                                        <span class="qty-number-display">1</span>
                                        <button type="button" class="btn-qty-mod" onclick="OrderApp.changeQty(<?= $m['id'] ?>, 1)"><i class="fa-solid fa-plus"></i></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- ============ 7. KENAPA DAPUR TYAS ============ -->
    <section class="about-banner">
        <div class="about-banner-icon"><i class="fa-solid fa-heart"></i></div>
        <h3 class="about-banner-title">Dibuat dengan Cinta dari Rumah</h3>
        <p class="about-banner-desc">
            Setiap piring yang kami sajikan adalah warisan resep keluarga yang diracik dengan bahan segar,
            tanpa MSG berlebih, dan sepenuh hati. Karena lezat tidak harus mahal.
        </p>
    </section>

    <!-- ============ 8. INFORMASI RESTORAN ============ -->
    <section class="customer-info-section">
        <div class="section-headline">
            <h2><i class="fa-solid fa-circle-info"></i> Informasi &amp; Kontak</h2>
            <p>Kami siap melayani Anda</p>
        </div>

        <div class="info-cards-grid">
            <!-- Tentang Kami -->
            <div class="info-card info-card-accent">
                <h3 class="info-card-title">
                    <span class="info-card-icon"><i class="fa-solid fa-store"></i></span>
                    Tentang <?= htmlspecialchars($restoName) ?>
                </h3>
                <p class="info-card-desc"><?= nl2br(htmlspecialchars($restoTentang)) ?></p>
            </div>

            <!-- Jam Operasional -->
            <div class="info-card">
                <h3 class="info-card-title">
                    <span class="info-card-icon gold"><i class="fa-solid fa-clock"></i></span>
                    Jam Operasional
                </h3>
                <p class="info-card-desc"><strong><?= htmlspecialchars($restoJam) ?></strong></p>
                <div class="info-tags-row">
                    <span class="info-tag"><i class="fa-solid fa-utensils"></i> Makan di Tempat</span>
                    <span class="info-tag"><i class="fa-solid fa-bag-shopping"></i> Take Away</span>
                </div>
            </div>

            <!-- Hubungi Kami -->
            <div class="info-card">
                <h3 class="info-card-title">
                    <span class="info-card-icon"><i class="fa-solid fa-location-dot"></i></span>
                    Lokasi &amp; Kontak
                </h3>
                <div class="contact-quick-list">
                    <a href="https://maps.google.com/?q=<?= $mapsQuery ?>" target="_blank" rel="noopener" class="contact-quick-item">
                        <i class="fa-solid fa-map-pin"></i>
                        <span><?= htmlspecialchars($restoAlamat) ?></span>
                        <i class="fa-solid fa-arrow-up-right-from-square contact-arrow"></i>
                    </a>
                    <a href="https://wa.me/<?= $waNumber ?>" target="_blank" rel="noopener" class="contact-quick-item">
                        <i class="fa-brands fa-whatsapp"></i>
                        <span>WhatsApp: <strong><?= htmlspecialchars($restoWa) ?></strong></span>
                        <i class="fa-solid fa-arrow-up-right-from-square contact-arrow"></i>
                    </a>
                    <?php if (!empty($igHandle)): ?>
                        <a href="https://instagram.com/<?= htmlspecialchars($igHandle) ?>" target="_blank" rel="noopener" class="contact-quick-item">
                            <i class="fa-brands fa-instagram"></i>
                            <span>Instagram: <strong><?= htmlspecialchars($restoIg) ?></strong></span>
                            <i class="fa-solid fa-arrow-up-right-from-square contact-arrow"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 9. FOOTER ============ -->
    <footer class="customer-footer">
        <div class="footer-brand">
            <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="" class="footer-logo">
            <div>
                <div class="footer-brand-title"><?= htmlspecialchars($restoName) ?></div>
                <div class="footer-brand-tagline"><?= htmlspecialchars($restoTagline) ?> &bull; <?= htmlspecialchars($restoSub) ?></div>
            </div>
        </div>

        <div class="footer-links-row">
            <a href="#menuCatalogFeed"><i class="fa-solid fa-bowl-food"></i> Menu</a>
            <a href="javascript:void(0)" onclick="openTablePicker()"><i class="fa-solid fa-chair"></i> Pilih Meja</a>
            <a href="https://wa.me/<?= $waNumber ?>" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
            <a href="https://instagram.com/<?= htmlspecialchars($igHandle) ?>" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i> Instagram</a>
            <a href="<?= BASE_URL ?>/auth/login.php"><i class="fa-solid fa-user-lock"></i> Staff Login</a>
        </div>

        <div class="footer-payments">
            <span class="pay-chip"><i class="fa-solid fa-credit-card"></i> Debit</span>
            <span class="pay-chip"><i class="fa-solid fa-money-bill-wave"></i> Tunai</span>
            <span class="pay-chip"><i class="fa-solid fa-qrcode"></i> QRIS</span>
        </div>

        <div class="footer-copyright">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($restoName) ?>. All rights reserved.<br>
            Powered by <strong><?= htmlspecialchars($restoSub) ?></strong>
        </div>
    </footer>

    <!-- ============ 10. FLOATING CART BOTTOM PILL ============ -->
    <div class="cart-floating-bar" id="cartFloatingBar" style="display: none;" onclick="handleOpenCart()">
        <div class="cart-bar-left">
            <div class="cart-badge-count" id="cartTotalCount">0</div>
            <div>
                <div class="cart-bar-label">Keranjang <span id="cartBarTable"><?= htmlspecialchars($currentNomorMeja ?: 'Meja Anda') ?></span></div>
                <div class="cart-bar-price" id="cartTotalPrice">Rp 0</div>
            </div>
        </div>
        <div class="cart-bar-btn-text">
            <span>Lihat Pesanan</span>
            <i class="fa-solid fa-arrow-right"></i>
        </div>
    </div>
</div>

<!-- ============ DRAWER OVERLAY & BOTTOM SHEET CART ============ -->
<div class="drawer-overlay" id="drawerOverlay" onclick="OrderApp.closeCartDrawer()"></div>

<div class="bottom-sheet-drawer" id="bottomSheetCart">
    <div class="sheet-handle-bar"></div>
    <div class="sheet-header">
        <div>
            <div class="sheet-title"><i class="fa-solid fa-receipt" style="color: var(--primary); margin-right: 6px;"></i> Pesanan Anda</div>
            <div style="font-size: 0.78rem; color: var(--primary); font-weight: 700;">
                <i class="fa-solid fa-chair"></i> <span id="drawerTableLabel"><?= htmlspecialchars($currentNomorMeja ?: 'Belum Memilih Meja') ?></span>
            </div>
        </div>
        <button type="button" class="btn-close-sheet" onclick="OrderApp.closeCartDrawer()">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="sheet-body">
        <div id="drawerCartItems"></div>

        <div class="cart-note-box">
            <div class="form-group" style="margin-bottom: 10px;">
                <label class="form-label" for="customerNameInput">Nama Pemesan (Opsional)</label>
                <input type="text" id="customerNameInput" class="form-control" placeholder="Nama Anda (cth: Kak Tyas)">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="orderGeneralNotes">Catatan Khusus untuk Dapur</label>
                <textarea id="orderGeneralNotes" class="form-control" rows="2" placeholder="Cth: Sambal dipisah, jangan terlalu manis..."></textarea>
            </div>
        </div>
    </div>

    <div class="sheet-footer">
        <div class="drawer-totals">
            <div>
                <span>Total Item:</span>
                <strong id="drawerTotalCount">0 Item</strong>
            </div>
            <div>
                <span>Total Tagihan:</span>
                <span id="drawerTotalAmount">Rp 0</span>
            </div>
        </div>
        <button type="button" class="btn btn-primary btn-block" id="btnSubmitOrderDrawer" onclick="submitCustomerOrderFromDrawer()">
            <i class="fa-solid fa-paper-plane"></i> KIRIM PESANAN SEKARANG
        </button>
        <p class="sheet-legal">Dengan mengirim pesanan, kamu menyetujui pesanan dieksekusi oleh dapur &amp; melunasi di kasir.</p>
    </div>
</div>

<!-- ============ TABLE PICKER MODAL ============ -->
<div class="table-picker-modal" id="tablePickerModal" style="display: <?= empty($currentTableId) ? 'flex' : 'none' ?>;">
    <div class="table-picker-card">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 2px;">
            <button type="button" id="btnCloseTablePicker" class="btn-close-picker" onclick="closeTablePicker()" style="display: <?= !empty($currentTableId) ? 'inline-flex' : 'none' ?>;" title="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="picker-brand">
            <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="Logo" class="picker-logo">
        </div>

        <div class="picker-step-badge">
            <i class="fa-solid fa-chair"></i> LANGKAH 1: PILIH MEJA DAHULU
        </div>

        <h3 class="picker-title">SELAMAT DATANG DI <?= htmlspecialchars($restoName) ?></h3>
        <p class="picker-sub">Silakan pilih nomor meja tempat Anda duduk terlebih dahulu sebelum memesan hidangan:</p>

        <div class="table-picker-grid">
            <?php foreach ($allTables as $t): ?>
                <?php
                $isSelected = ($currentTableId === (int)$t['id']);
                $isTerisi = ($t['status'] === 'terisi');
                ?>
                <button type="button" class="table-select-btn <?= $isSelected ? 'active' : '' ?>"
                        data-table-id="<?= $t['id'] ?>"
                        onclick="selectTable(<?= $t['id'] ?>, '<?= htmlspecialchars($t['nomor_meja'], ENT_QUOTES) ?>', '<?= htmlspecialchars($t['qr_identifier'], ENT_QUOTES) ?>')">
                    <div class="table-btn-icon"><i class="fa-solid fa-chair"></i></div>
                    <div class="table-btn-name"><?= htmlspecialchars($t['nomor_meja']) ?></div>
                    <div class="table-btn-cap"><i class="fa-solid fa-user-group"></i> <?= $t['kapasitas'] ?> Kursi</div>
                    <?php if ($isTerisi): ?>
                        <div class="table-btn-status used"><i class="fa-solid fa-circle-dot"></i> Terisi</div>
                    <?php else: ?>
                        <div class="table-btn-status empty"><i class="fa-solid fa-circle-check"></i> Kosong</div>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="picker-hint">
            <i class="fa-solid fa-circle-info"></i>
            Setelah memilih nomor meja, Anda dapat langsung memilih menu makanan dan mengirimkan pesanan ke dapur.
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="<?= BASE_URL ?>/assets/js/order.js?v=<?= time() ?>"></script>
<script>
    var activeTableId = <?= (int)$currentTableId ?>;
    var activeTableNo = "<?= htmlspecialchars($currentNomorMeja, ENT_QUOTES) ?>";
    var activeTableToken = "<?= htmlspecialchars($currentTableToken, ENT_QUOTES) ?>";

    document.addEventListener('DOMContentLoaded', function () {
        OrderApp.init(activeTableId);
        updateCartBarTable();

        if (!activeTableId || activeTableId <= 0) {
            openTablePicker(true);
        }
    });

    function updateCartBarTable() {
        var el = document.getElementById('cartBarTable');
        if (el) el.textContent = activeTableNo || 'Meja Anda';
    }

    function openTablePicker(force) {
        var modal = document.getElementById('tablePickerModal');
        var closeBtn = document.getElementById('btnCloseTablePicker');
        if (modal) {
            modal.style.display = 'flex';
            if (force || !activeTableId || activeTableId <= 0) {
                if (closeBtn) closeBtn.style.display = 'none';
            } else {
                if (closeBtn) closeBtn.style.display = 'inline-flex';
            }
        }
    }

    function closeTablePicker() {
        if (!activeTableId || activeTableId <= 0) {
            showToast('Silakan pilih nomor meja Anda terlebih dahulu!', 'warning');
            return;
        }
        var modal = document.getElementById('tablePickerModal');
        if (modal) modal.style.display = 'none';
    }

    function handleAddItem(id, name, price, photo) {
        if (!activeTableId || activeTableId <= 0) {
            openTablePicker(true);
            showToast('Silakan pilih nomor meja terlebih dahulu sebelum memilih menu!', 'warning');
            return false;
        }
        var added = OrderApp.addItem(id, name, price, photo);
        if (added) {
            showToast(name + ' ditambahkan ke keranjang');
        }
    }

    function handleOpenCart() {
        if (!activeTableId || activeTableId <= 0) {
            openTablePicker(true);
            showToast('Silakan pilih nomor meja terlebih dahulu!', 'warning');
            return;
        }
        OrderApp.openCartDrawer();
    }

    function selectTable(id, name, token) {
        activeTableId = id;
        activeTableNo = name;
        activeTableToken = token;

        var headerBtn = document.querySelector('.table-pill-btn');
        if (headerBtn) {
            headerBtn.classList.remove('table-pill-required');
            headerBtn.classList.add('active-selected');
        }
        var lbl = document.getElementById('headerTableLabel');
        if (lbl) {
            lbl.innerHTML = name + ' <span style="font-size:0.72rem; opacity:0.85;">(Ganti)</span>';
        }

        var dLbl = document.getElementById('drawerTableLabel');
        if (dLbl) dLbl.textContent = name;

        document.querySelectorAll('.table-select-btn').forEach(function(btn) {
            btn.classList.toggle('active', btn.dataset.tableId == id);
        });

        document.getElementById('tablePickerModal').style.display = 'none';
        var closeBtn = document.getElementById('btnCloseTablePicker');
        if (closeBtn) closeBtn.style.display = 'inline-flex';

        updateCartBarTable();
        OrderApp.init(id);

        var cleanNo = name.replace(/^Meja\s*/i, '');
        var newUrl = window.location.pathname + '?meja=' + encodeURIComponent(cleanNo);
        window.history.replaceState({ path: newUrl }, '', newUrl);

        fetch('<?= BASE_URL ?>/api/get_order_status.php?set_table_session=' + id).catch(function(){});

        showToast('Meja ' + name + ' berhasil dipilih. Silakan pilih menu!');
    }

    function showToast(msg, type) {
        type = type || 'success';
        var toast = document.getElementById('customerToast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'customerToast';
            toast.className = 'customer-toast';
            document.body.appendChild(toast);
        }
        var icon = (type === 'warning') ? '<i class="fa-solid fa-triangle-exclamation"></i> ' : '<i class="fa-solid fa-circle-check"></i> ';
        toast.innerHTML = icon + msg;
        toast.className = 'customer-toast show ' + type;
        clearTimeout(window.__toastTimer);
        window.__toastTimer = setTimeout(function () {
            toast.className = 'customer-toast';
        }, 2800);
    }

    function handleCategoryFilter(slug) {
        var query = document.getElementById('customerSearchInput').value;
        OrderApp.filterMenuCatalog(slug, query);
    }

    function handleSearchFilter() {
        var query = document.getElementById('customerSearchInput').value;
        var activeTab = document.querySelector('.cat-tab-btn.active');
        var activeSlug = activeTab ? activeTab.dataset.slug : 'semua';
        OrderApp.filterMenuCatalog(activeSlug, query);
    }

    function submitCustomerOrderFromDrawer() {
        var cart = OrderApp.getCart();
        var items = Object.values(cart);
        if (items.length === 0) {
            alert('Keranjang pesanan Anda masih kosong.');
            return;
        }

        if (!activeTableId || activeTableId <= 0) {
            showToast('Silakan pilih nomor meja Anda terlebih dahulu!', 'warning');
            openTablePicker(true);
            return;
        }

        var customerName = document.getElementById('customerNameInput').value.trim() || 'Pelanggan Meja ' + activeTableNo;
        var generalNotes = document.getElementById('orderGeneralNotes').value.trim();
        var btn = document.getElementById('btnSubmitOrderDrawer');

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim Pesanan ke Dapur...';

        fetch('<?= BASE_URL ?>/customer/checkout_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                table_id: activeTableId,
                token: activeTableToken,
                customer_name: customerName,
                general_notes: generalNotes,
                items: items
            })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                OrderApp.clearCart();
                window.location.href = data.redirect_url;
            } else {
                alert(data.message || 'Gagal mengirim pesanan. Silakan coba lagi.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> KIRIM PESANAN SEKARANG';
            }
        })
        .catch(function (err) {
            console.error(err);
            alert('Terjadi gangguan jaringan saat mengirim pesanan.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> KIRIM PESANAN SEKARANG';
        });
    }
    // Laptop shortcut: Tutup modal atau drawer dengan tombol Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            OrderApp.closeCartDrawer();
            if (activeTableId && activeTableId > 0) {
                var tpModal = document.getElementById('tablePickerModal');
                if (tpModal && tpModal.style.display !== 'none') {
                    closeTablePicker();
                }
            }
        }
    });
</script>

</body>
</html>