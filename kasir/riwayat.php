<?php
/**
 * Riwayat Transaksi Kasir - DAPUR TYAS by TECNO GARAGE
 * Phase 21 & 23: Cashier shift transaction logs and reprint receipt capability
 */

require_once dirname(__DIR__) . '/config/app.php';
requireKasir();

$pdo = db();
$currentUser = getAuthUser();
$kasirId = $currentUser['id'];

$search = trim($_GET['search'] ?? '');
$tanggal = trim($_GET['tanggal'] ?? date('Y-m-d'));

$where = ["DATE(p.paid_at) = :tgl"];
$params = ['tgl' => $tanggal];

if ($currentUser['role'] === 'kasir') {
    $where[] = "p.user_id = :uid";
    $params['uid'] = $kasirId;
}

if (!empty($search)) {
    $where[] = "(o.nomor_order LIKE :search OR p.nomor_transaksi LIKE :search OR o.customer_name LIKE :search)";
    $params['search'] = "%$search%";
}

$whereClause = implode(" AND ", $where);
$query = "SELECT p.*, o.nomor_order, o.customer_name, o.order_type, t.nomor_meja, u.nama AS nama_kasir 
          FROM payments p
          JOIN orders o ON p.order_id = o.id
          LEFT JOIN tables t ON o.table_id = t.id
          LEFT JOIN users u ON p.user_id = u.id
          WHERE $whereClause
          ORDER BY p.paid_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Total Summary
$totalOmset = array_sum(array_column($payments, 'total_tagihan'));
$totalTrx = count($payments);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi Kasir — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/kasir.css">
</head>
<body style="background: var(--bg-base); padding: 24px;">

<div style="max-width: 1100px; margin: 0 auto;">
    <!-- Top Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <a href="<?= BASE_URL ?>/kasir/index.php" class="btn btn-outline" style="padding: 8px 14px;">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Kasir
            </a>
            <div>
                <h1 style="font-size: 1.35rem; font-weight: 800; color: var(--text-primary); margin: 0;">Riwayat Transaksi Kasir</h1>
                <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= APP_NAME ?> &bull; <?= APP_SUB_NAME ?></div>
            </div>
        </div>

        <div style="display: flex; gap: 12px; align-items: center;">
            <div style="background: var(--bg-surface); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: var(--radius-md); font-size: 0.9rem;">
                Total Transaksi: <strong><?= $totalTrx ?></strong> &bull; Total Omset: <strong style="color: var(--primary);"><?= formatRupiah($totalOmset) ?></strong>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 20px;">
        <form method="GET" action="<?= BASE_URL ?>/kasir/riwayat.php" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 220px;">
                <input type="text" name="search" class="form-control" placeholder="Cari nomor order / nomor transaksi..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div style="width: 170px;">
                <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal) ?>" onchange="this.form.submit()">
            </div>

            <button type="submit" class="btn btn-secondary">
                <i class="fa-solid fa-magnifying-glass"></i> Filter
            </button>

            <?php if (!empty($search) || $tanggal !== date('Y-m-d')): ?>
                <a href="<?= BASE_URL ?>/kasir/riwayat.php" class="btn btn-outline" title="Reset">
                    <i class="fa-solid fa-rotate-left"></i> Hari Ini
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary); margin-right: 8px;"></i>Daftar Pembayaran Selesai</h3>
            <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= formatTanggalIndo($tanggal . ' 00:00:00', false) ?></span>
        </div>

        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>No. Order & Transaksi</th>
                        <th>Meja / Tipe</th>
                        <th>Pelanggan</th>
                        <th>Metode</th>
                        <th>Total Tagihan</th>
                        <th>Kembalian</th>
                        <th style="width: 120px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 36px;">
                                Belum ada transaksi yang tercatat pada tanggal ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td>
                                    <strong><?= date('H:i', strtotime($p['paid_at'])) ?> WIB</strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($p['nomor_order']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                        <code><?= htmlspecialchars($p['nomor_transaksi']) ?></code>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--bg-surface-soft); color: var(--text-primary);">
                                        <?= htmlspecialchars($p['nomor_meja'] ?? 'Takeaway') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($p['customer_name'] ?? 'Pelanggan') ?>
                                </td>
                                <td>
                                    <span class="badge badge-olive"><?= strtoupper($p['metode_pembayaran']) ?></span>
                                </td>
                                <td>
                                    <strong style="color: var(--primary);"><?= formatRupiah($p['total_tagihan']) ?></strong>
                                </td>
                                <td style="color: var(--text-secondary);">
                                    <?= formatRupiah($p['kembalian']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="<?= BASE_URL ?>/kasir/cetak_struk.php?order_id=<?= $p['order_id'] ?>" target="_blank" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;" title="Cetak Ulang Struk">
                                        <i class="fa-solid fa-print"></i> Struk
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
