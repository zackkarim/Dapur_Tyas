<?php
/**
 * Laporan Komprehensif Restoran - DAPUR TYAS by TECNO GARAGE
 * Phase 25: Laporan Harian, Bulanan, Tahunan, Finansial (Penjualan, HPP/Modal, Pengeluaran, Laba/Rugi),
 * Metode Pembayaran, Produk Terlaris, Transaksi per Meja, Visualisasi Grafik, dan Mode Cetak.
 */

$pageTitle = 'Laporan & Laba/Rugi';
$activeMenu = 'laporan';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';

$pdo = db();

// Mode Periode Laporan: harian | bulanan | tahunan | kustom
$periode = trim($_GET['periode'] ?? 'bulanan');
if (!in_array($periode, ['harian', 'bulanan', 'tahunan', 'kustom'], true)) {
    $periode = 'bulanan';
}

$today = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');

$selectedDate = trim($_GET['tanggal'] ?? $today);
$selectedMonth = trim($_GET['bulan'] ?? $currentMonth);
$selectedYear = trim($_GET['tahun'] ?? $currentYear);
$startDateCustom = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDateCustom = trim($_GET['end_date'] ?? $today);

// Tentukan range tanggal ($startDate dan $endDate) berdasarkan filter
switch ($periode) {
    case 'harian':
        $startDate = $selectedDate;
        $endDate = $selectedDate;
        $periodeLabel = "Harian: " . formatTanggalIndo($selectedDate, false);
        break;
    case 'tahunan':
        $startDate = $selectedYear . '-01-01';
        $endDate = $selectedYear . '-12-31';
        $periodeLabel = "Tahun: " . htmlspecialchars($selectedYear);
        break;
    case 'kustom':
        $startDate = $startDateCustom;
        $endDate = $endDateCustom;
        $periodeLabel = "Rentang: " . formatTanggalIndo($startDate, false) . " s/d " . formatTanggalIndo($endDate, false);
        break;
    case 'bulanan':
    default:
        $startDate = $selectedMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $namaBulanIndo = formatTanggalIndo($startDate, false);
        $parts = explode(' ', $namaBulanIndo);
        $periodeLabel = "Bulan: " . ($parts[1] ?? '') . " " . ($parts[2] ?? date('Y'));
        break;
}

// 1. Total Penjualan & Transaksi dari Payments Lunas
$stmtSales = $pdo->prepare("SELECT 
                                COALESCE(SUM(p.nominal_bayar), 0) AS total_omset,
                                COALESCE(SUM(p.total_tagihan), 0) AS total_tagihan,
                                COUNT(p.id) AS total_transaksi
                            FROM payments p
                            WHERE DATE(p.paid_at) >= :s AND DATE(p.paid_at) <= :e AND p.status = 'lunas'");
$stmtSales->execute(['s' => $startDate, 'e' => $endDate]);
$salesRow = $stmtSales->fetch();

$totalPenjualan = (float) ($salesRow['total_omset'] ?? 0);
$totalTransaksi = (int) ($salesRow['total_transaksi'] ?? 0);

// 2. Total Modal / HPP Produk Terjual (dari pesanan yang lunas)
$stmtHpp = $pdo->prepare("SELECT COALESCE(SUM(m.harga_modal * od.quantity), 0) AS total_modal
                          FROM order_details od
                          JOIN orders o ON od.order_id = o.id
                          JOIN menus m ON od.menu_id = m.id
                          WHERE DATE(o.created_at) >= :s AND DATE(o.created_at) <= :e AND o.status_pembayaran = 'lunas'");
$stmtHpp->execute(['s' => $startDate, 'e' => $endDate]);
$totalModal = (float) $stmtHpp->fetchColumn();

// 3. Total Pengeluaran Operasional
$stmtExp = $pdo->prepare("SELECT COALESCE(SUM(nominal), 0) AS total_pengeluaran 
                          FROM expenses 
                          WHERE tanggal >= :s AND tanggal <= :e");
$stmtExp->execute(['s' => $startDate, 'e' => $endDate]);
$totalPengeluaran = (float) $stmtExp->fetchColumn();

// 4. Perhitungan Laba Kotor & Laba Bersih
$labaKotor = $totalPenjualan - $totalModal;
$labaBersih = $labaKotor - $totalPengeluaran;
$isUntung = ($labaBersih >= 0);

// 5. Breakdown Metode Pembayaran
$stmtPayMethod = $pdo->prepare("SELECT 
                                    metode_pembayaran,
                                    COUNT(id) AS jml_transaksi,
                                    COALESCE(SUM(nominal_bayar), 0) AS total_nominal
                                FROM payments
                                WHERE DATE(paid_at) >= :s AND DATE(paid_at) <= :e AND status = 'lunas'
                                GROUP BY metode_pembayaran");
$stmtPayMethod->execute(['s' => $startDate, 'e' => $endDate]);
$payMethods = $stmtPayMethod->fetchAll();

// 6. Produk Terlaris (Top 10 Menu)
$stmtTopMenus = $pdo->prepare("SELECT 
                                  m.id, m.nama_menu, m.kode_menu, m.foto, c.nama_kategori,
                                  COALESCE(SUM(od.quantity), 0) AS total_qty,
                                  COALESCE(SUM(od.subtotal), 0) AS total_omset,
                                  COALESCE(SUM(m.harga_modal * od.quantity), 0) AS total_modal_menu,
                                  COALESCE(SUM((od.harga_satuan - m.harga_modal) * od.quantity), 0) AS total_profit_menu
                              FROM order_details od
                              JOIN orders o ON od.order_id = o.id
                              JOIN menus m ON od.menu_id = m.id
                              LEFT JOIN categories c ON m.category_id = c.id
                              WHERE DATE(o.created_at) >= :s AND DATE(o.created_at) <= :e AND o.status_pembayaran = 'lunas'
                              GROUP BY m.id
                              ORDER BY total_qty DESC, total_omset DESC
                              LIMIT 10");
$stmtTopMenus->execute(['s' => $startDate, 'e' => $endDate]);
$topMenus = $stmtTopMenus->fetchAll();

// 7. Transaksi per Meja & Tipe Pesanan
$stmtTableStats = $pdo->prepare("SELECT 
                                    CASE 
                                        WHEN o.table_id IS NULL THEN 'Take Away / Langsung'
                                        ELSE t.nomor_meja
                                    END AS nama_lokasi,
                                    COUNT(DISTINCT o.id) AS total_order,
                                    COALESCE(SUM(p.nominal_bayar), 0) AS total_omset
                                FROM orders o
                                LEFT JOIN tables t ON o.table_id = t.id
                                JOIN payments p ON o.id = p.order_id AND p.status = 'lunas'
                                WHERE DATE(o.created_at) >= :s AND DATE(o.created_at) <= :e
                                GROUP BY nama_lokasi
                                ORDER BY total_omset DESC");
$stmtTableStats->execute(['s' => $startDate, 'e' => $endDate]);
$tableStats = $stmtTableStats->fetchAll();

// 8. Rincian Daftar Transaksi Pembayaran dalam Periode
$stmtTrxList = $pdo->prepare("SELECT 
                                 p.*, o.nomor_order, o.customer_name, o.order_type,
                                 t.nomor_meja, u.nama AS nama_kasir,
                                 (SELECT COALESCE(SUM(od.quantity * m.harga_modal), 0) 
                                  FROM order_details od 
                                  JOIN menus m ON od.menu_id = m.id 
                                  WHERE od.order_id = o.id) AS hpp_order
                             FROM payments p
                             JOIN orders o ON p.order_id = o.id
                             LEFT JOIN tables t ON o.table_id = t.id
                             LEFT JOIN users u ON p.user_id = u.id
                             WHERE DATE(p.paid_at) >= :s AND DATE(p.paid_at) <= :e AND p.status = 'lunas'
                             ORDER BY p.paid_at DESC");
$stmtTrxList->execute(['s' => $startDate, 'e' => $endDate]);
$trxList = $stmtTrxList->fetchAll();

// 9. Data Trend Grafik
$trendLabels = [];
$trendSales = [];
$trendProfits = [];

if ($periode === 'tahunan') {
    // 12 Bulan dalam 1 tahun
    for ($m = 1; $m <= 12; $m++) {
        $mStr = str_pad($m, 2, '0', STR_PAD_LEFT);
        $trendLabels[] = date('M', strtotime("{$selectedYear}-{$mStr}-01"));
        $sM = "{$selectedYear}-{$mStr}-01";
        $eM = date('Y-m-t', strtotime($sM));

        $st1 = $pdo->prepare("SELECT COALESCE(SUM(nominal_bayar), 0) FROM payments WHERE DATE(paid_at) >= :s AND DATE(paid_at) <= :e AND status = 'lunas'");
        $st1->execute(['s' => $sM, 'e' => $eM]);
        $sVal = (float) $st1->fetchColumn();

        $st2 = $pdo->prepare("SELECT COALESCE(SUM((od.harga_satuan - m.harga_modal) * od.quantity), 0) FROM order_details od JOIN orders o ON od.order_id = o.id JOIN menus m ON od.menu_id = m.id WHERE DATE(o.created_at) >= :s AND DATE(o.created_at) <= :e AND o.status_pembayaran = 'lunas'");
        $st2->execute(['s' => $sM, 'e' => $eM]);
        $pVal = (float) $st2->fetchColumn();

        $trendSales[] = $sVal;
        $trendProfits[] = $pVal;
    }
} else {
    // Tampilkan 7 s/d 14 titik tanggal dalam rentang
    $dtStart = new DateTime($startDate);
    $dtEnd = new DateTime($endDate);
    $diffDays = $dtStart->diff($dtEnd)->days;
    $step = ($diffDays > 31) ? ceil($diffDays / 15) : 1;

    $cur = clone $dtStart;
    while ($cur <= $dtEnd) {
        $dStr = $cur->format('Y-m-d');
        $trendLabels[] = $cur->format('d M');

        $st1 = $pdo->prepare("SELECT COALESCE(SUM(nominal_bayar), 0) FROM payments WHERE DATE(paid_at) = :d AND status = 'lunas'");
        $st1->execute(['d' => $dStr]);
        $sVal = (float) $st1->fetchColumn();

        $st2 = $pdo->prepare("SELECT COALESCE(SUM((od.harga_satuan - m.harga_modal) * od.quantity), 0) FROM order_details od JOIN orders o ON od.order_id = o.id JOIN menus m ON od.menu_id = m.id WHERE DATE(o.created_at) = :d AND o.status_pembayaran = 'lunas'");
        $st2->execute(['d' => $dStr]);
        $pVal = (float) $st2->fetchColumn();

        $trendSales[] = $sVal;
        $trendProfits[] = $pVal;

        $cur->modify("+{$step} day");
    }
}
?>

<!-- Filter & Action Header -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin: 0 0 4px 0;">
                <i class="fa-solid fa-file-invoice-dollar" style="color: var(--primary); margin-right: 8px;"></i>
                Laporan Keuangan & Penjualan Restoran
            </h2>
            <div style="font-size: 0.88rem; color: var(--text-secondary);">
                Periode Aktif: <strong style="color: var(--primary);"><?= $periodeLabel ?></strong>
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Tombol Cetak Laporan -->
            <a href="<?= BASE_URL ?>/admin/laporan/cetak.php?periode=<?= urlencode($periode) ?>&tanggal=<?= urlencode($selectedDate) ?>&bulan=<?= urlencode($selectedMonth) ?>&tahun=<?= urlencode($selectedYear) ?>&start_date=<?= urlencode($startDateCustom) ?>&end_date=<?= urlencode($endDateCustom) ?>" 
               target="_blank" class="btn btn-primary" style="padding: 9px 16px;">
                <i class="fa-solid fa-print"></i> Cetak / Ekspor PDF
            </a>
        </div>
    </div>

    <!-- Tab Filter Periode -->
    <div style="margin-top: 18px; border-top: 1px solid var(--border-color); padding-top: 16px;">
        <form method="GET" action="<?= BASE_URL ?>/admin/laporan/index.php" id="formFilterLaporan" style="display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;">
            
            <div class="form-group" style="margin-bottom: 0; min-width: 140px;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Tipe Periode</label>
                <select name="periode" id="selectPeriode" class="form-control" onchange="togglePeriodeInputs()">
                    <option value="harian" <?= $periode === 'harian' ? 'selected' : '' ?>>Harian</option>
                    <option value="bulanan" <?= $periode === 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
                    <option value="tahunan" <?= $periode === 'tahunan' ? 'selected' : '' ?>>Tahunan</option>
                    <option value="kustom" <?= $periode === 'kustom' ? 'selected' : '' ?>>Rentang Tanggal</option>
                </select>
            </div>

            <!-- Input Harian -->
            <div class="form-group periode-input" id="inputHarian" style="margin-bottom: 0; display: <?= $periode === 'harian' ? 'block' : 'none' ?>;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Pilih Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>">
            </div>

            <!-- Input Bulanan -->
            <div class="form-group periode-input" id="inputBulanan" style="margin-bottom: 0; display: <?= $periode === 'bulanan' ? 'block' : 'none' ?>;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Pilih Bulan</label>
                <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($selectedMonth) ?>">
            </div>

            <!-- Input Tahunan -->
            <div class="form-group periode-input" id="inputTahunan" style="margin-bottom: 0; display: <?= $periode === 'tahunan' ? 'block' : 'none' ?>;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Pilih Tahun</label>
                <select name="tahun" class="form-control">
                    <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- Input Kustom (Rentang) -->
            <div class="form-group periode-input" id="inputKustomStart" style="margin-bottom: 0; display: <?= $periode === 'kustom' ? 'block' : 'none' ?>;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDateCustom) ?>">
            </div>

            <div class="form-group periode-input" id="inputKustomEnd" style="margin-bottom: 0; display: <?= $periode === 'kustom' ? 'block' : 'none' ?>;">
                <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDateCustom) ?>">
            </div>

            <button type="submit" class="btn btn-secondary" style="height: 42px; padding: 0 18px;">
                <i class="fa-solid fa-filter"></i> Terapkan Filter
            </button>
        </form>
    </div>
</div>

<!-- Financial Summary Widgets (6 Key Metrics) -->
<div class="grid-stats" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
    <!-- 1. Total Transaksi -->
    <div class="stat-widget">
        <div class="stat-icon-circle" style="background: var(--bg-surface-soft); color: var(--text-primary);">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Total Transaksi</div>
            <div class="stat-value"><?= number_format($totalTransaksi, 0, ',', '.') ?> <span style="font-size: 0.85rem; font-weight: 500;">trx</span></div>
        </div>
    </div>

    <!-- 2. Total Penjualan (Omset) -->
    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-terracotta">
            <i class="fa-solid fa-sack-dollar"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Total Penjualan</div>
            <div class="stat-value" style="color: var(--primary);"><?= formatRupiah($totalPenjualan) ?></div>
        </div>
    </div>

    <!-- 3. Total Harga Modal (HPP) -->
    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-gold">
            <i class="fa-solid fa-boxes-packing"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Total Modal (HPP)</div>
            <div class="stat-value" style="color: var(--status-warning);"><?= formatRupiah($totalModal) ?></div>
        </div>
    </div>

    <!-- 4. Laba Kotor (Gross Profit) -->
    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-olive">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Laba Kotor (Gross)</div>
            <div class="stat-value" style="color: var(--status-success);"><?= formatRupiah($labaKotor) ?></div>
        </div>
    </div>

    <!-- 5. Pengeluaran Operasional -->
    <div class="stat-widget">
        <div class="stat-icon-circle" style="background: var(--status-danger-bg); color: var(--status-danger);">
            <i class="fa-solid fa-wallet"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Biaya Operasional</div>
            <div class="stat-value" style="color: var(--status-danger);"><?= formatRupiah($totalPengeluaran) ?></div>
        </div>
    </div>

    <!-- 6. Laba Bersih / Kerugian (Net Profit) -->
    <div class="stat-widget" style="border: 2px solid <?= $isUntung ? 'var(--status-success)' : 'var(--status-danger)' ?>; background: <?= $isUntung ? 'var(--status-success-bg)' : 'var(--status-danger-bg)' ?>;">
        <div class="stat-icon-circle" style="background: #FFFFFF; color: <?= $isUntung ? 'var(--status-success)' : 'var(--status-danger)' ?>;">
            <i class="fa-solid <?= $isUntung ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label" style="font-weight: 800; color: <?= $isUntung ? 'var(--status-success)' : 'var(--status-danger)' ?>;">
                <?= $isUntung ? 'KEUNTUNGAN BERSIH' : 'KERUGIAN BERSIH' ?>
            </div>
            <div class="stat-value" style="color: <?= $isUntung ? 'var(--status-success)' : 'var(--status-danger)' ?>;">
                <?= formatRupiah(abs($labaBersih)) ?>
            </div>
        </div>
    </div>
</div>

<!-- Section: Grafik Tren & Komposisi Pembayaran -->
<div class="dashboard-grid-charts" style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Chart 1: Tren Penjualan & Laba -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-chart-area" style="color: var(--primary); margin-right: 8px;"></i>Tren Penjualan & Laba Kotor</h3>
        </div>
        <div style="padding: 16px; height: 280px; position: relative;">
            <canvas id="chartTrendSales"></canvas>
        </div>
    </div>

    <!-- Chart 2: Komposisi Metode Pembayaran -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-pie-chart" style="color: var(--primary); margin-right: 8px;"></i>Metode Pembayaran</h3>
        </div>
        <div style="padding: 16px; height: 280px; position: relative; display: flex; align-items: center; justify-content: center;">
            <canvas id="chartPaymentMethod"></canvas>
        </div>
    </div>
</div>

<!-- Section: Produk Terlaris & Transaksi per Meja -->
<div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Top Selling Menus Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-fire" style="color: var(--primary); margin-right: 8px;"></i>10 Produk Menu Terlaris</h3>
            <span style="font-size: 0.8rem; color: var(--text-secondary);">Berdasarkan Jumlah Terjual</span>
        </div>
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Menu</th>
                        <th>Kategori</th>
                        <th style="text-align: center;">Terjual</th>
                        <th style="text-align: right;">Total Omset</th>
                        <th style="text-align: right;">Keuntungan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topMenus)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 28px;">
                                Belum ada penjualan menu pada periode ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rank = 1; foreach ($topMenus as $tm): ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background: <?= $rank <= 3 ? 'var(--primary-light)' : 'var(--bg-surface-soft)' ?>; color: <?= $rank <= 3 ? 'var(--primary)' : 'var(--text-secondary)' ?>; font-weight: 800;">
                                        #<?= $rank++ ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($tm['nama_menu']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-light);"><?= htmlspecialchars($tm['kode_menu']) ?></div>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--bg-surface-soft); color: var(--text-secondary);">
                                        <?= htmlspecialchars($tm['nama_kategori'] ?? '-') ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <strong style="font-size: 1rem; color: var(--text-primary);"><?= $tm['total_qty'] ?></strong>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--text-primary);">
                                    <?= formatRupiah($tm['total_omset']) ?>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--status-success);">
                                    <?= formatRupiah($tm['total_profit_menu']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Transaksi per Meja & Tipe Pesanan Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-chair" style="color: var(--primary); margin-right: 8px;"></i>Transaksi per Meja / Lokasi</h3>
        </div>
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Meja / Lokasi</th>
                        <th style="text-align: center;">Pesanan</th>
                        <th style="text-align: right;">Total Omset</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tableStats)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--text-secondary); padding: 28px;">
                                Belum ada transaksi meja pada periode ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tableStats as $ts): ?>
                            <tr>
                                <td>
                                    <strong><i class="fa-solid <?= $ts['nama_lokasi'] === 'Take Away / Langsung' ? 'fa-bag-shopping' : 'fa-chair' ?>" style="color: var(--primary); margin-right: 6px;"></i><?= htmlspecialchars($ts['nama_lokasi']) ?></strong>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge" style="background: var(--bg-surface-soft); color: var(--text-primary); font-weight: 700;">
                                        <?= $ts['total_order'] ?> pesanan
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight: 700; color: var(--text-primary);">
                                    <?= formatRupiah($ts['total_omset']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Section: Tabel Rincian Semua Transaksi -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-list-check" style="color: var(--primary); margin-right: 8px;"></i>Rincian Riwayat Pembayaran & Transaksi</h3>
        <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= count($trxList) ?> Transaksi Selesai</span>
    </div>
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>No. Order</th>
                    <th>Waktu Bayar</th>
                    <th>Meja / Tipe</th>
                    <th>Kasir</th>
                    <th>Metode</th>
                    <th style="text-align: right;">Total Tagihan</th>
                    <th style="text-align: right;">Modal (HPP)</th>
                    <th style="text-align: right;">Keuntungan</th>
                    <th style="width: 70px; text-align: center;">Struk</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trxList)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; color: var(--text-secondary); padding: 36px;">
                            Tidak ditemukan riwayat pembayaran pada rentang periode terpilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trxList as $tx): 
                        $labaTx = (float)$tx['nominal_bayar'] - (float)$tx['hpp_order'];
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($tx['nomor_transaksi']) ?></strong>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/transaksi/detail.php?id=<?= $tx['order_id'] ?>" style="color: var(--primary); font-weight: 600; text-decoration: none;">
                                    <?= htmlspecialchars($tx['nomor_order']) ?>
                                </a>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem;"><?= date('d/m/Y H:i', strtotime($tx['paid_at'])) ?></span>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--bg-surface-soft); color: var(--text-primary);">
                                    <?= htmlspecialchars($tx['nomor_meja'] ?? 'Takeaway') ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= htmlspecialchars($tx['nama_kasir'] ?: 'Staff Kasir') ?></span>
                            </td>
                            <td>
                                <span class="badge" style="text-transform: uppercase; background: <?= $tx['metode_pembayaran'] === 'qris' ? '#E0E7FF; color: #4338CA;' : ($tx['metode_pembayaran'] === 'transfer' ? '#FEE2E2; color: #991B1B;' : '#D1FAE5; color: #065F46;') ?>">
                                    <?= htmlspecialchars($tx['metode_pembayaran']) ?>
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--text-primary);">
                                <?= formatRupiah($tx['nominal_bayar']) ?>
                            </td>
                            <td style="text-align: right; color: var(--text-secondary);">
                                <?= formatRupiah($tx['hpp_order']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: <?= $labaTx >= 0 ? 'var(--status-success)' : 'var(--status-danger)' ?>;">
                                <?= formatRupiah($labaTx) ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="<?= BASE_URL ?>/kasir/cetak_struk.php?order_id=<?= $tx['order_id'] ?>" target="_blank" class="btn btn-outline" style="padding: 4px 8px; font-size: 0.78rem;" title="Lihat Struk">
                                    <i class="fa-solid fa-receipt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function togglePeriodeInputs() {
    const p = document.getElementById('selectPeriode').value;
    document.getElementById('inputHarian').style.display = (p === 'harian') ? 'block' : 'none';
    document.getElementById('inputBulanan').style.display = (p === 'bulanan') ? 'block' : 'none';
    document.getElementById('inputTahunan').style.display = (p === 'tahunan') ? 'block' : 'none';
    document.getElementById('inputKustomStart').style.display = (p === 'kustom') ? 'block' : 'none';
    document.getElementById('inputKustomEnd').style.display = (p === 'kustom') ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. Chart Trend Penjualan & Laba
    const ctxTrend = document.getElementById('chartTrendSales').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [
                {
                    label: 'Penjualan (Rp)',
                    data: <?= json_encode($trendSales) ?>,
                    borderColor: '#C85A32',
                    backgroundColor: 'rgba(200, 90, 50, 0.12)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#C85A32'
                },
                {
                    label: 'Laba Kotor (Rp)',
                    data: <?= json_encode($trendProfits) ?>,
                    borderColor: '#65A30D',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#65A30D'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { font: { family: 'Plus Jakarta Sans', size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: function(c) {
                            return c.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(c.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v) {
                            if (v >= 1000000) return (v / 1000000) + ' Jt';
                            if (v >= 1000) return (v / 1000) + ' Rb';
                            return v;
                        }
                    }
                }
            }
        }
    });

    // 2. Chart Komposisi Metode Pembayaran
    const payData = <?= json_encode($payMethods) ?>;
    const payLabels = payData.map(p => p.metode_pembayaran.toUpperCase());
    const payCounts = payData.map(p => parseInt(p.jml_transaksi));

    const ctxPay = document.getElementById('chartPaymentMethod').getContext('2d');
    new Chart(ctxPay, {
        type: 'doughnut',
        data: {
            labels: payLabels.length > 0 ? payLabels : ['Belum Ada'],
            datasets: [{
                data: payCounts.length > 0 ? payCounts : [1],
                backgroundColor: ['#10B981', '#6366F1', '#F59E0B', '#9CA3AF'],
                borderWidth: 2,
                borderColor: '#FFFFFF'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: 'Plus Jakarta Sans', size: 12 } } }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
