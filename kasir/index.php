<?php
/**
 * POS Kasir Utama - DAPUR TYAS by TECNO GARAGE
 * Redesigned Cashier Workstation: Table Orders Queue, Walk-in POS Catalog with Photos, Status Workflow & Receipts
 */

require_once dirname(__DIR__) . '/config/app.php';
requireKasir();

$pdo = db();
$currentUser = getAuthUser();
$activeTab = trim($_GET['tab'] ?? 'meja');

// Ambil Kategori & Menu untuk Transaksi Langsung
$categories = $pdo->query("SELECT * FROM categories ORDER BY urutan ASC")->fetchAll();
$menus = $pdo->query("SELECT m.*, c.slug AS cat_slug, c.nama_kategori 
                      FROM menus m 
                      JOIN categories c ON m.category_id = c.id 
                      WHERE m.status != 'nonaktif' 
                      ORDER BY c.urutan ASC, m.nama_menu ASC")->fetchAll();
$tables = $pdo->query("SELECT * FROM tables WHERE status != 'nonaktif' ORDER BY nomor_meja ASC")->fetchAll();

$restoName = getSetting('nama_restoran', 'DAPUR TYAS');
$restoTag  = getSetting('tagline', 'MASAKAN RUMAHAN YANG LEZAT');
$restoSub  = getSetting('sub_brand', 'by TECNO GARAGE');
$rekeningBank  = getSetting('rekening_bank', 'BCA');
$rekeningNomor = getSetting('rekening_nomor', '');
$rekeningAn    = getSetting('rekening_an', 'DAPUR TYAS');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir POS — <?= htmlspecialchars($restoName) ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/kasir.css?v=<?= time() ?>">
    <style>
        .pos-menu-thumb {
            width: 100%;
            height: 105px;
            border-radius: var(--radius-sm);
            overflow: hidden;
            background: var(--bg-surface-soft);
            margin-bottom: 8px;
            position: relative;
        }

        .pos-menu-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .pos-menu-card:hover .pos-menu-thumb img {
            transform: scale(1.06);
        }

        .pos-cat-pill {
            padding: 6px 14px;
            background: var(--bg-surface-soft);
            color: var(--text-primary);
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .pos-cat-pill.active {
            background: var(--primary);
            color: #FFFFFF;
            border-color: var(--primary);
        }

        .brand-logo-frame-kasir {
            width: 46px;
            height: 46px;
            background: #FFFFFF;
            border-radius: 50%;
            border: 2px solid #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
            flex-shrink: 0;
        }

        .brand-logo-frame-kasir img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
    </style>
</head>
<body>

<div class="kasir-layout">
    <!-- Topbar Navigation -->
    <header class="kasir-topbar">
        <div class="kasir-brand-wrap">
            <div class="brand-logo-frame-kasir">
                <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="Logo">
            </div>
            <div>
                <div style="font-weight: 800; font-size: 1.15rem; color: #FFFFFF; line-height: 1.2;"><?= htmlspecialchars($restoName) ?></div>
                <div style="font-size: 0.72rem; color: var(--accent-gold); font-weight: 700; letter-spacing: 0.5px;"><?= htmlspecialchars($restoSub) ?> &bull; KASIR POS</div>
            </div>
        </div>

        <!-- Main Workspace Tabs -->
        <div class="kasir-nav-tabs">
            <button type="button" class="kasir-tab-btn <?= $activeTab === 'meja' ? 'active' : '' ?>" id="tabBtnMeja" onclick="switchTab('meja')">
                <i class="fa-solid fa-bell-concierge"></i>
                <span>PESANAN MEJA</span>
                <span class="badge-pill" id="liveBadgeIncoming">0</span>
            </button>
            <button type="button" class="kasir-tab-btn <?= $activeTab === 'langsung' ? 'active' : '' ?>" id="tabBtnLangsung" onclick="switchTab('langsung')">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>TRANSAKSI LANGSUNG</span>
            </button>
        </div>

        <!-- Cashier User Meta & Actions -->
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="text-align: right; font-size: 0.85rem;">
                <div style="font-weight: 700; color: #FFFFFF;"><?= htmlspecialchars($currentUser['nama']) ?></div>
                <div style="font-size: 0.72rem; color: var(--text-light);" id="posClock"><?= date('H:i:s') ?> WIB</div>
            </div>

            <a href="<?= BASE_URL ?>/kasir/riwayat.php" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem; color: #FFFFFF; border-color: rgba(255,255,255,0.25);" title="Riwayat Transaksi Kasir">
                <i class="fa-solid fa-clock-rotate-left"></i> Riwayat
            </a>

            <?php if (getAuthRole() === 'admin'): ?>
                <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;" title="Kembali ke Panel Admin">
                    <i class="fa-solid fa-gauge"></i> Admin
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-outline" style="padding: 6px 10px; font-size: 0.8rem; color: #F87171; border-color: rgba(248,113,113,0.3);" onclick="return confirm('Keluar dari sesi kasir?');" title="Logout">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <!-- SECTION 1: PESANAN MEJA (INCOMING QR ORDERS) -->
    <div id="sectionMeja" class="section-meja-bg" style="display: <?= $activeTab === 'meja' ? 'block' : 'none' ?>; flex: 1;">
        <div style="padding: 18px 24px 0 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; gap: 8px; align-items: center;">
                <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin: 0;">Antrean Pesanan Meja</h2>
                <span class="badge badge-olive" style="font-size: 0.75rem;"><i class="fa-solid fa-circle" style="font-size: 0.5rem;"></i> Live Auto-Sync</span>
            </div>
            
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="fetchIncomingOrders(true)">
                    <i class="fa-solid fa-rotate"></i> Refresh Antrean
                </button>
            </div>
        </div>

        <!-- Orders Grid Container -->
        <div class="orders-grid-incoming" id="incomingOrdersContainer">
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 12px; display: block;"></i>
                Memuat antrean pesanan meja...
            </div>
        </div>
    </div>

    <!-- SECTION 2: TRANSAKSI LANGSUNG (WALK-IN POS) -->
    <div id="sectionLangsung" style="display: <?= $activeTab === 'langsung' ? 'block' : 'none' ?>; flex: 1;">
        <div class="pos-workstation">
            <!-- Left Panel: Menu Catalog -->
            <div class="pos-catalog-panel">
                <div class="pos-catalog-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 12px;">
                        <div style="position: relative; flex: 1;">
                            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-light);"></i>
                            <input type="text" id="posSearchMenu" class="form-control" style="padding-left: 36px; border-radius: var(--radius-full);" placeholder="Cari nama atau deskripsi menu..." oninput="filterPosCatalog()">
                        </div>
                    </div>

                    <!-- Filter Pill Tabs -->
                    <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px;">
                        <button type="button" class="pos-cat-pill active" data-cat="semua" onclick="setPosCategory('semua')">
                            <i class="fa-solid fa-border-all"></i> Semua
                        </button>
                        <?php foreach ($categories as $cat): ?>
                            <button type="button" class="pos-cat-pill" data-cat="<?= htmlspecialchars($cat['slug']) ?>" onclick="setPosCategory('<?= htmlspecialchars($cat['slug']) ?>')">
                                <?= htmlspecialchars($cat['nama_kategori']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pos-items-grid" id="posMenuGrid">
                    <?php foreach ($menus as $m): ?>
                        <?php
                        $isSoldOut = ($m['status'] === 'habis' || (int)$m['stok'] <= 0);
                        $fotoName = $m['foto'] ?? 'default_menu.png';
                        $fotoUrl = BASE_URL . '/assets/images/' . $fotoName;
                        if (!file_exists(ROOT_PATH . '/assets/images/' . $fotoName) && file_exists(ROOT_PATH . '/assets/images/menus/' . $fotoName)) {
                            $fotoUrl = BASE_URL . '/assets/images/menus/' . $fotoName;
                        }
                        ?>
                        <div class="pos-menu-card <?= $isSoldOut ? 'soldout' : '' ?>" 
                             data-id="<?= $m['id'] ?>"
                             data-name="<?= htmlspecialchars($m['nama_menu'], ENT_QUOTES) ?>"
                             data-price="<?= (float)$m['harga_jual'] ?>"
                             data-category="<?= htmlspecialchars($m['cat_slug']) ?>"
                             onclick="addDirectItem(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nama_menu'], ENT_QUOTES) ?>', <?= (float)$m['harga_jual'] ?>)">
                            
                            <div class="pos-menu-thumb">
                                <img src="<?= $fotoUrl ?>" alt="<?= htmlspecialchars($m['nama_menu']) ?>" loading="lazy" onerror="this.src='<?= BASE_URL ?>/assets/images/default_menu.png'">
                                <?php if ($isSoldOut): ?>
                                    <div class="menu-soldout-overlay" style="font-size: 0.75rem;">HABIS</div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <div style="font-weight: 800; font-size: 0.9rem; color: var(--text-primary); margin-bottom: 2px; line-height: 1.25;"><?= htmlspecialchars($m['nama_menu']) ?></div>
                                <div style="font-size: 0.72rem; color: var(--text-secondary);"><?= htmlspecialchars($m['nama_kategori']) ?></div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 6px; border-top: 1px dashed var(--border-subtle);">
                                <strong style="color: var(--primary); font-size: 0.95rem;"><?= formatRupiah($m['harga_jual']) ?></strong>
                                <?php if (!$isSoldOut): ?>
                                    <span style="font-size: 0.72rem; color: var(--accent-olive); font-weight: 700;">Stok: <?= $m['stok'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Panel: POS Cart & Checkout -->
            <div class="pos-cart-panel">
                <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); margin: 0;">
                        <i class="fa-solid fa-cash-register" style="color: var(--primary); margin-right: 6px;"></i> Pesanan Kasir
                    </h3>
                    <button type="button" class="btn btn-outline btn-sm" style="color: var(--status-danger);" onclick="clearDirectCart()">
                        <i class="fa-solid fa-trash-can"></i> Kosongkan
                    </button>
                </div>

                <!-- Customer Details & Dine-In Select -->
                <div style="padding: 12px 18px; background: var(--bg-surface-soft); border-bottom: 1px solid var(--border-subtle); display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary);">Tipe Pesanan</label>
                        <select id="directOrderType" class="form-control" style="padding: 6px 10px; font-size: 0.85rem;" onchange="toggleTableSelect(this.value)">
                            <option value="take_away">Bungkus (Takeaway)</option>
                            <option value="dine_in">Makan di Tempat (Dine In)</option>
                        </select>
                    </div>

                    <div id="directTableWrap" style="display: none;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary);">Pilih Meja</label>
                        <select id="directTableId" class="form-control" style="padding: 6px 10px; font-size: 0.85rem;">
                            <option value="">Tanpa Nomor Meja</option>
                            <?php foreach ($tables as $tb): ?>
                                <option value="<?= $tb['id'] ?>"><?= htmlspecialchars($tb['nomor_meja']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="grid-column: 1 / -1;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary);">Nama Pelanggan (Opsional)</label>
                        <input type="text" id="directCustomerName" class="form-control" style="padding: 6px 10px; font-size: 0.85rem;" placeholder="Pelanggan Langsung">
                    </div>
                </div>

                <!-- Cart Items List -->
                <div class="pos-cart-items" id="directCartList">
                    <div style="text-align: center; padding: 40px 10px; color: var(--text-secondary);">
                        <i class="fa-solid fa-cart-plus" style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 10px; display: block;"></i>
                        Klik menu di samping untuk menambahkan item
                    </div>
                </div>

                <!-- Footer Summary & Checkout -->
                <div class="pos-cart-footer">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 6px;">
                        <span>Subtotal (<span id="directTotalItems">0</span> item)</span>
                        <strong id="directSubtotalFmt" style="color: var(--text-primary);">Rp 0</strong>
                    </div>

                    <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 14px; border-top: 1px dashed var(--border-color); padding-top: 8px;">
                        <span>TOTAL</span>
                        <span id="directTotalFmt" style="color: var(--primary);">Rp 0</span>
                    </div>

                    <button type="button" class="btn btn-primary btn-block" id="btnDirectCheckout" style="padding: 12px;" onclick="openPaymentModalDirect()" disabled>
                        <i class="fa-solid fa-cash-register"></i> PROSES PEMBAYARAN
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PEMBAYARAN KASIR -->
<div id="modalPayment" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 500px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;" id="payModalTitle">Pembayaran Kasir</h3>
            <button type="button" onclick="closePaymentModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div style="background: var(--bg-surface-soft); padding: 14px 18px; border-radius: var(--radius-md); margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-secondary);">
                <span id="payModalMeta">Order #DT-000 &bull; Meja 00</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                <span style="font-weight: 700; font-size: 1rem; color: var(--text-primary);">Total Tagihan:</span>
                <span style="font-weight: 800; font-size: 1.35rem; color: var(--primary);" id="payModalAmount">Rp 0</span>
            </div>
        </div>

        <!-- Payment Method Selector -->
        <div class="form-group">
            <label class="form-label">Metode Pembayaran</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                <label style="cursor: pointer; border: 2px solid var(--primary); padding: 10px; border-radius: var(--radius-md); text-align: center; background: var(--primary-light);" id="labelMetodeTunai">
                    <input type="radio" name="pay_metode" value="tunai" checked onchange="handleMetodeChange('tunai')" style="display: none;">
                    <i class="fa-solid fa-money-bill-wave" style="color: var(--primary); font-size: 1.2rem; display: block; margin-bottom: 4px;"></i>
                    <strong style="font-size: 0.85rem; color: var(--text-primary);">Tunai (Cash)</strong>
                </label>

                <label style="cursor: pointer; border: 2px solid var(--border-color); padding: 10px; border-radius: var(--radius-md); text-align: center;" id="labelMetodeQris">
                    <input type="radio" name="pay_metode" value="qris" onchange="handleMetodeChange('qris')" style="display: none;">
                    <i class="fa-solid fa-qrcode" style="color: var(--accent-gold); font-size: 1.2rem; display: block; margin-bottom: 4px;"></i>
                    <strong style="font-size: 0.85rem; color: var(--text-primary);">QRIS</strong>
                </label>

                <label style="cursor: pointer; border: 2px solid var(--border-color); padding: 10px; border-radius: var(--radius-md); text-align: center;" id="labelMetodeTransfer">
                    <input type="radio" name="pay_metode" value="transfer" onchange="handleMetodeChange('transfer')" style="display: none;">
                    <i class="fa-solid fa-building-columns" style="color: var(--status-info); font-size: 1.2rem; display: block; margin-bottom: 4px;"></i>
                    <strong style="font-size: 0.85rem; color: var(--text-primary);">Transfer</strong>
                </label>
            </div>
        </div>

        <!-- Cash Input Section -->
        <div id="cashInputSection">
            <div class="form-group">
                <label class="form-label" for="nominalBayarInput">Uang Diterima dari Pelanggan (Rp)</label>
                <input type="number" id="nominalBayarInput" class="form-control" style="font-size: 1.15rem; font-weight: 700;" placeholder="0" min="0" oninput="calcChange()">
            </div>

            <!-- Quick Cash Buttons -->
            <div style="display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap;" id="quickCashContainer"></div>

            <!-- Kembalian Display -->
            <div style="padding: 12px 16px; background: #FEF3C7; border: 1.5px solid #F59E0B; border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <span style="font-weight: 700; color: #92400E; font-size: 0.95rem;">Kembalian:</span>
                <span style="font-weight: 800; font-size: 1.25rem; color: #92400E;" id="displayKembalian">Rp 0</span>
            </div>
        </div>

        <!-- QRIS QR Code Display -->
        <div id="qrisQrSection" style="display: none; background: #FFFFFF; border: 2px dashed var(--border-color); border-radius: var(--radius-md); padding: 18px 16px; margin-bottom: 18px; text-align: center;">
            <div style="font-weight: 800; color: var(--text-primary); margin-bottom: 10px; font-size: 0.95rem;">
                <i class="fa-solid fa-qrcode" style="color: var(--accent-gold); margin-right: 6px;"></i> SCAN QRIS UNTUK MEMBAYAR
            </div>
            <div id="qrisQrBox" style="display: inline-block; background: #FFFFFF; padding: 8px; border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-subtle);"></div>
            <div style="margin-top: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--accent-gold-light); border: 1px solid rgba(201, 149, 50, 0.3); border-radius: var(--radius-sm); padding: 8px 12px; flex-wrap: wrap;">
                <i class="fa-solid fa-circle-check" style="color: var(--status-success);"></i>
                <span style="font-size: 0.85rem; color: var(--text-primary);">Total Tagihan QRIS: <strong id="qrisQrAmount">Rp 0</strong></span>
            </div>
            <div style="margin-top: 8px; font-size: 0.72rem; color: var(--text-secondary); font-family: monospace; font-weight: 600;">
                ID NAK Pembayaran: <span id="qrisQrRef">-</span>
            </div>
            <div style="margin-top: 8px; font-size: 0.75rem; color: var(--status-warning);">
                <i class="fa-solid fa-circle-info"></i> Minta pelanggan memindai (scan) QRIS ini dari aplikasi dompet digital / m-banking, lalu klik <strong>"Selesaikan & Cetak Struk"</strong> setelah pembayaran berhasil.
            </div>
        </div>

        <!-- Transfer Bank Display -->
        <div id="transferSection" style="display: none; background: #FFFFFF; border: 2px dashed var(--border-color); border-radius: var(--radius-md); padding: 18px 16px; margin-bottom: 18px; text-align: center;">
            <div style="font-weight: 800; color: var(--text-primary); margin-bottom: 10px; font-size: 0.95rem;">
                <i class="fa-solid fa-building-columns" style="color: var(--status-info); margin-right: 6px;"></i> TRANSFER MANUAL
            </div>

            <div style="margin-top: 4px; display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--accent-gold-light); border: 1px solid rgba(201, 149, 50, 0.3); border-radius: var(--radius-sm); padding: 8px 12px;">
                <i class="fa-solid fa-circle-check" style="color: var(--status-success);"></i>
                <span style="font-size: 0.85rem; color: var(--text-primary);">Total Tagihan Transfer: <strong id="transferAmount">Rp 0</strong></span>
            </div>

            <div style="background: var(--bg-surface-soft); border: 1.5px solid var(--border-color); border-radius: var(--radius-md); padding: 14px; margin-top: 12px;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 8px;">
                    <span style="font-size: 1.6rem; color: var(--status-info);"><i class="fa-solid fa-building-columns"></i></span>
                    <div style="text-align: left;">
                        <div style="font-weight: 800; font-size: 1rem; color: var(--text-primary);" id="transferBankName">-</div>
                        <div style="font-size: 0.72rem; color: var(--text-secondary); font-weight: 700;">PEMBAYARAN VIA TRANSFER</div>
                    </div>
                </div>
                <hr style="border: 0; border-top: 1px dashed var(--border-color); margin: 10px 0;">
                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 4px;">Nomor Rekening</div>
                <div style="font-family: monospace; font-size: 1.25rem; font-weight: 800; color: var(--text-primary); letter-spacing: 1px;" id="transferRekening">-</div>
                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 10px; margin-bottom: 4px;">Atas Nama</div>
                <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-primary);" id="transferAn">-</div>
            </div>

            <div style="margin-top: 8px; font-size: 0.72rem; color: var(--text-secondary); font-family: monospace; font-weight: 600;">
                ID Referensi Transfer: <span id="transferRef">-</span>
            </div>

            <div style="margin-top: 8px; font-size: 0.75rem; color: var(--status-warning);">
                <i class="fa-solid fa-circle-info"></i> Minta pelanggan mentransfer ke rekening di atas (mode simulasi), lalu klik <strong>"Selesaikan & Cetak Struk"</strong> setelah pembayaran berhasil.
            </div>
        </div>

        <!-- Non-Cash Notice (Transfer) -->
        <div id="nonCashSection" style="display: none; padding: 14px 18px; background: var(--bg-surface-soft); border-radius: var(--radius-md); margin-bottom: 20px; font-size: 0.85rem; text-align: center;">
            <i class="fa-solid fa-circle-check" style="color: var(--accent-olive); font-size: 1.5rem; margin-bottom: 6px; display: block;"></i>
            Payment Gateway: <strong>Mode Simulasi POS Kasir</strong>.<br>
            Klik konfirmasi setelah pelanggan menyelesaikan pembayaran.
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Batal</button>
            <button type="button" class="btn btn-primary" id="btnConfirmPayment" onclick="executePayment()">
                <i class="fa-solid fa-receipt"></i> Selesaikan & Cetak Struk
            </button>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/vendor/qrcodejs/qrcode.min.js"></script>
<script>
    let activeTabName = "<?= $activeTab ?>";
    let directCart = {};
    let activePaymentContext = null;
    let currentPosCat = 'semua';
    const transferConfig = {
        bank: '<?= htmlspecialchars($rekeningBank, ENT_QUOTES) ?>',
        rekening: '<?= htmlspecialchars($rekeningNomor, ENT_QUOTES) ?>',
        an: '<?= htmlspecialchars($rekeningAn, ENT_QUOTES) ?>'
    };

    // Live Clock
    setInterval(() => {
        const d = new Date();
        document.getElementById('posClock').textContent = d.toLocaleTimeString('id-ID') + ' WIB';
    }, 1000);

    function switchTab(tab) {
        activeTabName = tab;
        document.getElementById('sectionMeja').style.display = tab === 'meja' ? 'block' : 'none';
        document.getElementById('sectionLangsung').style.display = tab === 'langsung' ? 'block' : 'none';
        document.getElementById('tabBtnMeja').classList.toggle('active', tab === 'meja');
        document.getElementById('tabBtnLangsung').classList.toggle('active', tab === 'langsung');
    }

    // Web Audio Soft Chime for Incoming Orders
    let lastIncomingCount = 0;
    function playSoftBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime);
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc.start();
            osc.stop(ctx.currentTime + 0.4);
        } catch (e) {}
    }

    // Polling Incoming Orders
    function fetchIncomingOrders(manual = false) {
        fetch('<?= BASE_URL ?>/api/get_live_orders.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('liveBadgeIncoming').textContent = data.total_menunggu;

                    if (data.total_menunggu > lastIncomingCount && lastIncomingCount !== 0) {
                        playSoftBeep();
                    }
                    lastIncomingCount = data.total_menunggu;

                    renderIncomingOrders(data.orders);
                }
            })
            .catch(err => console.log('Polling kasir error:', err));
    }

    function renderIncomingOrders(orders) {
        const container = document.getElementById('incomingOrdersContainer');
        if (orders.length === 0) {
            container.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                    <i class="fa-solid fa-mug-hot" style="font-size: 3rem; color: var(--border-color); margin-bottom: 12px; display: block;"></i>
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">Tidak Ada Antrean Pesanan Meja</h3>
                    <p style="font-size: 0.85rem;">Pesanan baru dari scan QR pelanggan akan muncul di sini secara otomatis.</p>
                </div>
            `;
            return;
        }

        let html = '';
        orders.forEach(o => {
            const isMenunggu = (o.status_pesanan === 'menunggu');
            const isLunas = (o.status_pembayaran === 'lunas');

            let statusBadge = '';
            if (o.status_pesanan === 'menunggu') statusBadge = '<span class="badge badge-gold"><i class="fa-solid fa-clock"></i> Pesanan Baru</span>';
            else if (o.status_pesanan === 'diterima') statusBadge = '<span class="badge badge-olive"><i class="fa-solid fa-check"></i> Diterima</span>';
            else if (o.status_pesanan === 'dimasak') statusBadge = '<span class="badge badge-terracotta"><i class="fa-solid fa-fire"></i> Sedang Dibuat</span>';
            else if (o.status_pesanan === 'diantar') statusBadge = '<span class="badge badge-terracotta"><i class="fa-solid fa-bell-concierge"></i> Siap Diantar</span>';
            else if (o.status_pesanan === 'selesai') statusBadge = '<span class="badge badge-olive"><i class="fa-solid fa-circle-check"></i> Selesai</span>';
            else if (o.status_pesanan === 'dibatalkan') statusBadge = '<span class="badge" style="background: var(--status-danger-bg); color: var(--status-danger);">Dibatalkan</span>';

            let itemsHtml = '';
            o.items.forEach(it => {
                itemsHtml += `
                    <li>
                        <span><strong>${it.quantity}x</strong> ${escapeHtml(it.nama_menu)}</span>
                        <span>${formatRupiahJs(it.subtotal)}</span>
                    </li>
                    ${it.catatan ? `<div class="order-item-note">&bull; "${escapeHtml(it.catatan)}"</div>` : ''}
                `;
            });

            html += `
                <div class="incoming-order-card ${isMenunggu ? 'status-menunggu' : ''}">
                    <div class="order-card-head">
                        <div>
                            <div class="order-table-num">
                                <i class="fa-solid fa-chair" style="color: var(--primary);"></i> ${escapeHtml(o.nomor_meja)}
                            </div>
                            <div class="order-code-badge">${o.nomor_order} &bull; ${o.time_formatted}</div>
                        </div>
                        <div>${statusBadge}</div>
                    </div>

                    <div class="order-card-body">
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 8px;">
                            Pemesan: <strong>${escapeHtml(o.customer_name)}</strong>
                        </div>

                        <ul class="order-items-list">${itemsHtml}</ul>

                        ${o.catatan ? `<div class="order-notes-box"><strong>Catatan Meja:</strong> ${escapeHtml(o.catatan)}</div>` : ''}
                    </div>

                    <div class="order-card-footer">
                        <div class="order-total-row">
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">Total Tagihan:</span>
                            <span class="order-total-val">${o.total_formatted}</span>
                        </div>

                        <div class="order-actions-wrap">
                            ${isMenunggu ? `
                                <button type="button" class="btn btn-primary" onclick="updateOrderStatus(${o.id}, 'diterima')">
                                    <i class="fa-solid fa-check"></i> Terima Pesanan
                                </button>
                                <button type="button" class="btn btn-outline" style="color: var(--status-danger);" onclick="updateOrderStatus(${o.id}, 'dibatalkan')">
                                    <i class="fa-solid fa-ban"></i> Tolak
                                </button>
                            ` : ''}

                            ${o.status_pesanan === 'diterima' ? `
                                <button type="button" class="btn btn-secondary" onclick="updateOrderStatus(${o.id}, 'dimasak')">
                                    <i class="fa-solid fa-fire"></i> Sedang Dibuat
                                </button>
                            ` : ''}

                            ${o.status_pesanan === 'dimasak' ? `
                                <button type="button" class="btn btn-secondary" onclick="updateOrderStatus(${o.id}, 'diantar')">
                                    <i class="fa-solid fa-bell-concierge"></i> Siap Diantar
                                </button>
                            ` : ''}

                            ${o.status_pesanan === 'diantar' ? `
                                <button type="button" class="btn btn-secondary" onclick="updateOrderStatus(${o.id}, 'selesai')">
                                    <i class="fa-solid fa-circle-check"></i> Selesaikan
                                </button>
                            ` : ''}

                            ${!isLunas && o.status_pesanan !== 'dibatalkan' ? `
                                <button type="button" class="btn btn-primary" onclick="openPaymentModalTable(${o.id}, '${o.nomor_order}', '${o.nomor_meja}', ${o.total_amount})">
                                    <i class="fa-solid fa-cash-register"></i> BAYAR
                                </button>
                            ` : ''}

                            ${isLunas ? `
                                <a href="<?= BASE_URL ?>/kasir/cetak_struk.php?order_id=${o.id}" target="_blank" class="btn btn-outline">
                                    <i class="fa-solid fa-print"></i> Cetak Struk
                                </a>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function updateOrderStatus(orderId, nextStatus) {
        if (nextStatus === 'dibatalkan' && !confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')) {
            return;
        }

        fetch('<?= BASE_URL ?>/api/update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, status: nextStatus })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchIncomingOrders();
            } else {
                alert(data.message || 'Gagal mengubah status pesanan.');
            }
        });
    }

    // Direct Walk-in POS Logic
    function setPosCategory(cat) {
        currentPosCat = cat;
        document.querySelectorAll('.pos-cat-pill').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.cat === cat);
        });
        filterPosCatalog();
    }

    function toggleTableSelect(val) {
        document.getElementById('directTableWrap').style.display = (val === 'dine_in') ? 'block' : 'none';
    }

    function addDirectItem(id, name, price) {
        id = String(id);
        if (!directCart[id]) {
            directCart[id] = { id, name, price: parseFloat(price), qty: 1, note: '' };
        } else {
            directCart[id].qty += 1;
        }
        renderDirectCart();
    }

    function changeDirectQty(id, delta) {
        id = String(id);
        if (directCart[id]) {
            directCart[id].qty += delta;
            if (directCart[id].qty <= 0) {
                delete directCart[id];
            }
            renderDirectCart();
        }
    }

    function clearDirectCart() {
        directCart = {};
        renderDirectCart();
    }

    function renderDirectCart() {
        const container = document.getElementById('directCartList');
        const items = Object.values(directCart);
        const totalItems = items.reduce((sum, it) => sum + it.qty, 0);
        const totalAmount = items.reduce((sum, it) => sum + (it.price * it.qty), 0);

        document.getElementById('directTotalItems').textContent = totalItems;
        document.getElementById('directSubtotalFmt').textContent = formatRupiahJs(totalAmount);
        document.getElementById('directTotalFmt').textContent = formatRupiahJs(totalAmount);
        document.getElementById('btnDirectCheckout').disabled = (items.length === 0);

        if (items.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 40px 10px; color: var(--text-secondary);">
                    <i class="fa-solid fa-cart-plus" style="font-size: 2.5rem; color: var(--border-color); margin-bottom: 10px; display: block;"></i>
                    Klik menu di samping untuk menambahkan item
                </div>
            `;
            return;
        }

        let html = '';
        items.forEach(it => {
            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed var(--border-subtle);">
                    <div>
                        <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-primary);">${escapeHtml(it.name)}</div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">${formatRupiahJs(it.price)}</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div class="qty-stepper">
                            <button type="button" class="btn-qty-mod" onclick="changeDirectQty(${it.id}, -1)"><i class="fa-solid fa-minus"></i></button>
                            <span class="qty-number-display">${it.qty}</span>
                            <button type="button" class="btn-qty-mod" onclick="changeDirectQty(${it.id}, 1)"><i class="fa-solid fa-plus"></i></button>
                        </div>
                        <div style="font-weight: 800; font-size: 0.95rem; min-width: 75px; text-align: right; color: var(--text-primary);">
                            ${formatRupiahJs(it.price * it.qty)}
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function filterPosCatalog() {
        const search = document.getElementById('posSearchMenu').value.toLowerCase();
        const cat = currentPosCat;

        document.querySelectorAll('.pos-menu-card').forEach(card => {
            const name = card.dataset.name.toLowerCase();
            const category = card.dataset.category;
            const matchSearch = name.includes(search);
            const matchCat = (cat === 'semua' || category === cat);

            card.style.display = (matchSearch && matchCat) ? 'flex' : 'none';
        });
    }

    // Payment Modal Logic
    function openPaymentModalTable(orderId, orderNo, tableName, totalAmount) {
        activePaymentContext = {
            mode: 'table',
            orderId: orderId,
            totalAmount: totalAmount,
            metaText: `Pesanan ${orderNo} &bull; ${tableName}`
        };
        showPaymentDialog();
    }

    function openPaymentModalDirect() {
        const items = Object.values(directCart);
        const totalAmount = items.reduce((sum, it) => sum + (it.price * it.qty), 0);
        activePaymentContext = {
            mode: 'direct',
            totalAmount: totalAmount,
            metaText: 'Transaksi Kasir Langsung'
        };
        showPaymentDialog();
    }

    function showPaymentDialog() {
        document.getElementById('payModalMeta').innerHTML = activePaymentContext.metaText;
        document.getElementById('payModalAmount').textContent = formatRupiahJs(activePaymentContext.totalAmount);
        document.getElementById('nominalBayarInput').value = activePaymentContext.totalAmount;
        activePaymentContext.payRef = generatePayRef();
        
        const total = activePaymentContext.totalAmount;
        const quickContainer = document.getElementById('quickCashContainer');
        quickContainer.innerHTML = `
            <button type="button" class="btn btn-outline btn-sm" onclick="setCashAmount(${total})">Uang Pas</button>
            ${total <= 20000 ? `<button type="button" class="btn btn-outline btn-sm" onclick="setCashAmount(20000)">Rp 20.000</button>` : ''}
            ${total <= 50000 ? `<button type="button" class="btn btn-outline btn-sm" onclick="setCashAmount(50000)">Rp 50.000</button>` : ''}
            ${total <= 100000 ? `<button type="button" class="btn btn-outline btn-sm" onclick="setCashAmount(100000)">Rp 100.000</button>` : ''}
            <button type="button" class="btn btn-outline btn-sm" onclick="setCashAmount(${Math.ceil(total / 50000) * 50000})">Bulat 50rb</button>
        `;

        handleMetodeChange('tunai');
        calcChange();
        document.getElementById('modalPayment').style.display = 'flex';
    }

    function closePaymentModal() {
        document.getElementById('modalPayment').style.display = 'none';
    }

    function setCashAmount(val) {
        document.getElementById('nominalBayarInput').value = val;
        calcChange();
    }

    function handleMetodeChange(metode) {
        document.querySelectorAll('input[name="pay_metode"]').forEach(r => {
            r.checked = (r.value === metode);
            const parentLabel = document.getElementById('labelMetode' + ucfirst(r.value));
            if (parentLabel) {
                if (r.value === metode) {
                    parentLabel.style.borderColor = 'var(--primary)';
                    parentLabel.style.background = 'var(--primary-light)';
                } else {
                    parentLabel.style.borderColor = 'var(--border-color)';
                    parentLabel.style.background = 'transparent';
                }
            }
        });

        const isCash = (metode === 'tunai');
        document.getElementById('cashInputSection').style.display = isCash ? 'block' : 'none';
        document.getElementById('nonCashSection').style.display = isCash ? 'none' : 'block';
        document.getElementById('qrisQrSection').style.display = (metode === 'qris') ? 'block' : 'none';
        document.getElementById('transferSection').style.display = (metode === 'transfer') ? 'block' : 'none';

        if (metode === 'qris') {
            renderQrisQr();
        }
        if (metode === 'transfer') {
            renderTransferInfo();
        }
    }

    function renderTransferInfo() {
        if (!activePaymentContext) return;
        const ref = activePaymentContext.payRef || generatePayRef();
        document.getElementById('transferAmount').textContent = formatRupiahJs(activePaymentContext.totalAmount);
        document.getElementById('transferRef').textContent = ref;
        document.getElementById('transferBankName').textContent = transferConfig.bank || 'Bank';
        document.getElementById('transferRekening').textContent = transferConfig.rekening || '-';
        document.getElementById('transferAn').textContent = transferConfig.an || '-';
    }

    // QRIS Simulation: Build EMVCo QRIS payload & render QR code
    function tagName(id, value) {
        return id + String(value).length.toString().padStart(2, '0') + value;
    }

    function crc16(str) {
        let crc = 0xFFFF;
        for (let i = 0; i < str.length; i++) {
            crc ^= str.charCodeAt(i) << 8;
            for (let j = 0; j < 8; j++) {
                crc = ((crc & 0x8000) ? ((crc << 1) ^ 0x1021) : (crc << 1)) & 0xFFFF;
            }
        }
        return crc.toString(16).toUpperCase().padStart(4, '0');
    }

    function buildQrisPayload(amount, ref) {
        const merchantAccount = tagName('26', tagName('00', 'ID2024DAPURTYAS001'));
        let payload = '000201'
            + tagName('01', '12')
            + merchantAccount
            + tagName('52', '5812')
            + tagName('53', '360')
            + (amount > 0 ? tagName('54', amount.toFixed(2)) : '')
            + tagName('58', 'ID')
            + tagName('59', 'DAPUR TYAS')
            + tagName('60', 'MEDAN')
            + tagName('62', tagName('05', ref));
        payload += '6304';
        return payload + crc16(payload);
    }

    function generatePayRef() {
        const d = new Date();
        const yy = String(d.getFullYear()).slice(2);
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        const rand = Math.floor(1000 + Math.random() * 9000);
        return 'NAK-DT' + yy + mm + dd + '-' + rand;
    }

    function renderQrisQr() {
        if (!activePaymentContext) return;
        const container = document.getElementById('qrisQrBox');
        container.innerHTML = '';
        const ref = activePaymentContext.payRef || generatePayRef();
        const payload = buildQrisPayload(activePaymentContext.totalAmount, ref);

        new QRCode(container, {
            text: payload,
            width: 190,
            height: 190,
            colorDark: "#1F242D",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        document.getElementById('qrisQrAmount').textContent = formatRupiahJs(activePaymentContext.totalAmount);
        document.getElementById('qrisQrRef').textContent = ref;
    }

    function calcChange() {
        if (!activePaymentContext) return;
        const total = activePaymentContext.totalAmount;
        const paid = parseFloat(document.getElementById('nominalBayarInput').value) || 0;
        const change = Math.max(0, paid - total);
        document.getElementById('displayKembalian').textContent = formatRupiahJs(change);
    }

    function executePayment() {
        const selectedMetode = document.querySelector('input[name="pay_metode"]:checked').value;
        const paid = parseFloat(document.getElementById('nominalBayarInput').value) || 0;
        const total = activePaymentContext.totalAmount;

        if (selectedMetode === 'tunai' && paid < total) {
            alert('Nominal uang diterima kurang dari total tagihan.');
            return;
        }

        const btn = document.getElementById('btnConfirmPayment');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

        if (activePaymentContext.mode === 'table') {
            fetch('<?= BASE_URL ?>/kasir/bayar_proses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: activePaymentContext.orderId,
                    metode_pembayaran: selectedMetode,
                    nominal_bayar: paid
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closePaymentModal();
                    fetchIncomingOrders();
                    window.open(data.cetak_url, '_blank');
                } else {
                    alert(data.message || 'Pembayaran gagal.');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-receipt"></i> Selesaikan & Cetak Struk';
            });
        } else if (activePaymentContext.mode === 'direct') {
            const tableId = document.getElementById('directTableId').value;
            const orderType = document.getElementById('directOrderType').value;
            const customerName = document.getElementById('directCustomerName').value;

            fetch('<?= BASE_URL ?>/kasir/direct_order_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    table_id: tableId ? parseInt(tableId) : null,
                    order_type: orderType,
                    customer_name: customerName,
                    metode_pembayaran: selectedMetode,
                    nominal_bayar: paid,
                    items: Object.values(directCart)
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    clearDirectCart();
                    closePaymentModal();
                    window.open(data.cetak_url, '_blank');
                } else {
                    alert(data.message || 'Gagal membuat transaksi langsung.');
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-receipt"></i> Selesaikan & Cetak Struk';
            });
        }
    }

    function formatRupiahJs(num) {
        return 'Rp ' + Number(num).toLocaleString('id-ID');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Auto Poll every 3 seconds
    fetchIncomingOrders();
    setInterval(fetchIncomingOrders, 3000);
</script>

</body>
</html>
