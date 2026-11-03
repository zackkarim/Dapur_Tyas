<?php
/**
 * Cetak Laporan Keuangan & Penjualan - DAPUR TYAS by TECNO GARAGE
 * Phase 25: Print & PDF-Ready Layout with Official Header, Financial Summary, Payment Breakdown, Top Menus & Signatures
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$pdo = db();

$periode = trim($_GET['periode'] ?? 'bulanan');
$selectedDate = trim($_GET['tanggal'] ?? date('Y-m-d'));
$selectedMonth = trim($_GET['bulan'] ?? date('Y-m'));
$selectedYear = trim($_GET['tahun'] ?? date('Y'));
$startDateCustom = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDateCustom = trim($_GET['end_date'] ?? date('Y-m-d'));

switch ($periode) {
    case 'harian':
        $startDate = $selectedDate;
        $endDate = $selectedDate;
        $periodeLabel = "Harian: " . formatTanggalIndo($selectedDate, false);
        break;
    case 'tahunan':
        $startDate = $selectedYear . '-01-01';
        $endDate = $selectedYear . '-12-31';
        $periodeLabel = "Tahunan: Tahun " . htmlspecialchars($selectedYear);
        break;
    case 'kustom':
        $startDate = $startDateCustom;
        $endDate = $endDateCustom;
        $periodeLabel = "Periode: " . formatTanggalIndo($startDate, false) . " s/d " . formatTanggalIndo($endDate, false);
        break;
    case 'bulanan':
    default:
        $startDate = $selectedMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        $namaBulanIndo = formatTanggalIndo($startDate, false);
        $parts = explode(' ', $namaBulanIndo);
        $periodeLabel = "Bulanan: " . ($parts[1] ?? '') . " " . ($parts[2] ?? date('Y'));
        break;
}

// 1. Total Penjualan
$stmtSales = $pdo->prepare("SELECT 
                                COALESCE(SUM(p.nominal_bayar), 0) AS total_omset,
                                COUNT(p.id) AS total_transaksi
                            FROM payments p
                            WHERE DATE(p.paid_at) >= :s AND DATE(p.paid_at) <= :e AND p.status = 'lunas'");
$stmtSales->execute(['s' => $startDate, 'e' => $endDate]);
$salesRow = $stmtSales->fetch();
$totalPenjualan = (float) ($salesRow['total_omset'] ?? 0);
$totalTransaksi = (int) ($salesRow['total_transaksi'] ?? 0);

// 2. Total Modal (HPP)
$stmtHpp = $pdo->prepare("SELECT COALESCE(SUM(m.harga_modal * od.quantity), 0) AS total_modal
                          FROM order_details od
                          JOIN orders o ON od.order_id = o.id
                          JOIN menus m ON od.menu_id = m.id
                          WHERE DATE(o.created_at) >= :s AND DATE(o.created_at) <= :e AND o.status_pembayaran = 'lunas'");
$stmtHpp->execute(['s' => $startDate, 'e' => $endDate]);
$totalModal = (float) $stmtHpp->fetchColumn();

// 3. Total Pengeluaran
$stmtExp = $pdo->prepare("SELECT COALESCE(SUM(nominal), 0) AS total_pengeluaran 
                          FROM expenses 
                          WHERE tanggal >= :s AND tanggal <= :e");
$stmtExp->execute(['s' => $startDate, 'e' => $endDate]);
$totalPengeluaran = (float) $stmtExp->fetchColumn();

// 4. Laba Kotor & Bersih
$labaKotor = $totalPenjualan - $totalModal;
$labaBersih = $labaKotor - $totalPengeluaran;
$isUntung = ($labaBersih >= 0);

// 5. Metode Pembayaran
$stmtPayMethod = $pdo->prepare("SELECT 
                                    metode_pembayaran,
                                    COUNT(id) AS jml_transaksi,
                                    COALESCE(SUM(nominal_bayar), 0) AS total_nominal
                                FROM payments
                                WHERE DATE(paid_at) >= :s AND DATE(paid_at) <= :e AND status = 'lunas'
                                GROUP BY metode_pembayaran");
$stmtPayMethod->execute(['s' => $startDate, 'e' => $endDate]);
$payMethods = $stmtPayMethod->fetchAll();

// 6. Produk Terlaris
$stmtTopMenus = $pdo->prepare("SELECT 
                                  m.nama_menu, m.kode_menu, c.nama_kategori,
                                  COALESCE(SUM(od.quantity), 0) AS total_qty,
                                  COALESCE(SUM(od.subtotal), 0) AS total_omset,
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

$namaResto = getSetting('nama_restoran', 'DAPUR TYAS');
$subBrand = getSetting('sub_brand', 'by TECNO GARAGE');
$alamat = getSetting('alamat', 'Jl. Kuliner Nusantara No. 88, Medan');
$telepon = getSetting('telepon', '081234567890');
$currentUser = getAuthUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Keuangan — <?= htmlspecialchars($namaResto) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            background-color: #F8F9FA;
            color: #1F2937;
            padding: 24px;
            font-size: 12px;
            line-height: 1.4;
        }

        .control-bar {
            max-width: 800px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FFFFFF;
            padding: 14px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .btn-action {
            background-color: #C85A32;
            color: #FFFFFF;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-back {
            background-color: #E5E7EB;
            color: #374151;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
        }

        .report-page {
            max-width: 800px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 36px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #E5E7EB;
        }

        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #C85A32;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .header-logo {
            max-height: 65px;
            max-width: 180px;
            object-fit: contain;
        }

        .resto-info h1 {
            font-size: 1.4rem;
            font-weight: 800;
            color: #1F2937;
            margin-bottom: 2px;
        }

        .resto-info .sub-tag {
            font-size: 0.75rem;
            color: #C85A32;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .resto-info .address {
            font-size: 0.8rem;
            color: #4B5563;
        }

        .report-title-box {
            text-align: center;
            margin-bottom: 20px;
        }

        .report-title-box h2 {
            font-size: 1.2rem;
            font-weight: 800;
            color: #1F2937;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .report-title-box .badge-periode {
            display: inline-block;
            background: #FDF0EB;
            color: #C85A32;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.82rem;
        }

        .section-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #1F2937;
            border-left: 3px solid #C85A32;
            padding-left: 8px;
            margin: 18px 0 10px 0;
            text-transform: uppercase;
        }

        .table-report {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .table-report th {
            background-color: #F8F9FA;
            color: #374151;
            font-weight: 700;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #E5E7EB;
            font-size: 0.8rem;
        }

        .table-report td {
            padding: 7px 10px;
            border: 1px solid #E5E7EB;
            font-size: 0.8rem;
        }

        .summary-box-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 18px;
        }

        .summary-box {
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 10px 14px;
            background: #FAFAFA;
        }

        .summary-box .label {
            font-size: 0.72rem;
            color: #6B7280;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .summary-box .value {
            font-size: 1.05rem;
            font-weight: 800;
            color: #1F2937;
        }

        .signatures {
            margin-top: 36px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .sig-box {
            text-align: center;
            width: 200px;
        }

        .sig-space {
            height: 60px;
        }

        .sig-name {
            font-weight: 700;
            border-top: 1px solid #374151;
            padding-top: 4px;
        }

        @media print {
            body {
                background: #FFFFFF;
                padding: 0;
            }

            .control-bar {
                display: none !important;
            }

            .report-page {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="control-bar">
    <div>
        <a href="<?= BASE_URL ?>/admin/laporan/index.php" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard Laporan
        </a>
    </div>
    <div>
        <button type="button" class="btn-action" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
        </button>
    </div>
</div>

<div class="report-page">
    <!-- Header Restoran -->
    <div class="report-header">
        <div class="resto-info">
            <h1><?= htmlspecialchars($namaResto) ?></h1>
            <div class="sub-tag"><?= htmlspecialchars($subBrand) ?></div>
            <div class="address"><?= htmlspecialchars($alamat) ?> &bull; Telp: <?= htmlspecialchars($telepon) ?></div>
        </div>
        <div>
            <div style="width: 64px; height: 64px; border-radius: 50%; overflow: hidden; border: 2px solid #C85A32;">
                <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="Logo Dapur Tyas" style="width: 100%; height: 100%; object-fit: cover; display: block;">
            </div>
        </div>
    </div>

    <!-- Title & Periode -->
    <div class="report-title-box">
        <h2>LAPORAN KEUANGAN & PENJUALAN RESTORAN</h2>
        <div class="badge-periode"><?= $periodeLabel ?></div>
    </div>

    <!-- 6 Financial Summaries -->
    <div class="section-title">Ringkasan Finansial Restoran</div>
    <div class="summary-box-grid">
        <div class="summary-box">
            <div class="label">Total Transaksi</div>
            <div class="value"><?= number_format($totalTransaksi, 0, ',', '.') ?> Trx</div>
        </div>
        <div class="summary-box">
            <div class="label">Total Penjualan (Omset)</div>
            <div class="value" style="color: #C85A32;"><?= formatRupiah($totalPenjualan) ?></div>
        </div>
        <div class="summary-box">
            <div class="label">Harga Modal (HPP)</div>
            <div class="value" style="color: #D97706;"><?= formatRupiah($totalModal) ?></div>
        </div>
        <div class="summary-box">
            <div class="label">Laba Kotor (Gross Profit)</div>
            <div class="value" style="color: #65A30D;"><?= formatRupiah($labaKotor) ?></div>
        </div>
        <div class="summary-box">
            <div class="label">Biaya Pengeluaran Operasional</div>
            <div class="value" style="color: #DC2626;"><?= formatRupiah($totalPengeluaran) ?></div>
        </div>
        <div class="summary-box" style="background: <?= $isUntung ? '#ECFDF5' : '#FEF2F2' ?>; border-color: <?= $isUntung ? '#10B981' : '#EF4444' ?>;">
            <div class="label" style="color: <?= $isUntung ? '#047857' : '#B91C1C' ?>;"><?= $isUntung ? 'Laba Bersih (Net Profit)' : 'Kerugian Bersih' ?></div>
            <div class="value" style="color: <?= $isUntung ? '#047857' : '#B91C1C' ?>;"><?= formatRupiah(abs($labaBersih)) ?></div>
        </div>
    </div>

    <!-- Breakdown Metode Pembayaran -->
    <div class="section-title">Rincian Berdasarkan Metode Pembayaran</div>
    <table class="table-report">
        <thead>
            <tr>
                <th>Metode Pembayaran</th>
                <th style="text-align: center;">Jumlah Transaksi</th>
                <th style="text-align: right;">Total Nominal (Rp)</th>
                <th style="text-align: right;">Porsi Persentase</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payMethods)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #6B7280;">Tidak ada transaksi pembayaran pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($payMethods as $pm): 
                    $porsi = $totalPenjualan > 0 ? round(($pm['total_nominal'] / $totalPenjualan) * 100, 1) : 0;
                ?>
                    <tr>
                        <td><strong><?= strtoupper(htmlspecialchars($pm['metode_pembayaran'])) ?></strong></td>
                        <td style="text-align: center;"><?= $pm['jml_transaksi'] ?></td>
                        <td style="text-align: right; font-weight: 700;"><?= formatRupiah($pm['total_nominal']) ?></td>
                        <td style="text-align: right;"><?= $porsi ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Produk Terlaris -->
    <div class="section-title">10 Menu Makanan, Minuman, & Snack Terlaris</div>
    <table class="table-report">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Nama Menu</th>
                <th>Kategori</th>
                <th style="text-align: center;">Terjual</th>
                <th style="text-align: right;">Total Omset</th>
                <th style="text-align: right;">Keuntungan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($topMenus)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #6B7280;">Tidak ada menu yang terjual pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php $r = 1; foreach ($topMenus as $tm): ?>
                    <tr>
                        <td><?= $r++ ?></td>
                        <td><strong><?= htmlspecialchars($tm['nama_menu']) ?></strong> (<?= htmlspecialchars($tm['kode_menu']) ?>)</td>
                        <td><?= htmlspecialchars($tm['nama_kategori'] ?? '-') ?></td>
                        <td style="text-align: center; font-weight: 700;"><?= $tm['total_qty'] ?></td>
                        <td style="text-align: right;"><?= formatRupiah($tm['total_omset']) ?></td>
                        <td style="text-align: right; font-weight: 700; color: #047857;"><?= formatRupiah($tm['total_profit_menu']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Footer & Signatures -->
    <div class="signatures">
        <div class="sig-box">
            <div>Dicetak Oleh,</div>
            <div class="sig-space"></div>
            <div class="sig-name"><?= htmlspecialchars($currentUser['nama'] ?? 'Administrator') ?></div>
            <div style="font-size: 0.72rem; color: #6B7280;"><?= ucfirst(htmlspecialchars($currentUser['role'] ?? 'Admin')) ?></div>
        </div>

        <div class="sig-box">
            <div>Owner / Manager Resto,</div>
            <div class="sig-space"></div>
            <div class="sig-name">Owner Dapur Tyas</div>
            <div style="font-size: 0.72rem; color: #6B7280;">Tanggal: <?= date('d/m/Y') ?></div>
        </div>
    </div>
</div>

<script>
// Auto print if ?autoprint=1 is supplied
if (window.location.search.includes('autoprint=1')) {
    window.onload = function() { window.print(); }
}
</script>

</body>
</html>
