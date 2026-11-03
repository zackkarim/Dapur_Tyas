<?php
/**
 * Riwayat Seluruh Transaksi Restoran - DAPUR TYAS by TECNO GARAGE
 * Phase 23: Complete Transaction Logs, Advanced Filters, Totals, and Detail Viewer
 */

$pageTitle = 'Riwayat Transaksi';
$activeMenu = 'transaksi';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';

$pdo = db();
$search = trim($_GET['search'] ?? '');
$tglMulai = trim($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tglSelesai = trim($_GET['tgl_selesai'] ?? date('Y-m-d'));
$statusPesanan = trim($_GET['status_pesanan'] ?? '');
$statusBayar = trim($_GET['status_bayar'] ?? '');
$metodeBayar = trim($_GET['metode'] ?? '');

$where = ["DATE(o.created_at) >= :tgl_mulai AND DATE(o.created_at) <= :tgl_selesai"];
$params = [
    'tgl_mulai'   => $tglMulai,
    'tgl_selesai' => $tglSelesai
];

if (!empty($search)) {
    $where[] = "(o.nomor_order LIKE :search OR p.nomor_transaksi LIKE :search OR o.customer_name LIKE :search OR t.nomor_meja LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($statusPesanan)) {
    $where[] = "o.status_pesanan = :sp";
    $params['sp'] = $statusPesanan;
}

if (!empty($statusBayar)) {
    $where[] = "o.status_pembayaran = :sb";
    $params['sb'] = $statusBayar;
}

if (!empty($metodeBayar)) {
    $where[] = "p.metode_pembayaran = :mb";
    $params['mb'] = $metodeBayar;
}

$whereClause = implode(" AND ", $where);
$query = "SELECT o.*, t.nomor_meja, u.nama AS nama_kasir, p.nomor_transaksi, p.metode_pembayaran, p.paid_at 
          FROM orders o
          LEFT JOIN tables t ON o.table_id = t.id
          LEFT JOIN users u ON o.user_id = u.id
          LEFT JOIN payments p ON o.id = p.order_id
          WHERE $whereClause
          ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$totalOmset = 0.00;
$totalTrx = count($orders);
foreach ($orders as $ord) {
    if ($ord['status_pembayaran'] === 'lunas') {
        $totalOmset += (float)$ord['total_amount'];
    }
}
?>

<!-- Filter & Summary Bar -->
<div class="card" style="padding: 20px; margin-bottom: 24px;">
    <form method="GET" action="<?= BASE_URL ?>/admin/transaksi/index.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: flex-end;">
        <div>
            <label class="form-label" style="font-size: 0.8rem;">Cari Transaksi</label>
            <input type="text" name="search" class="form-control" placeholder="No. Order / Pelanggan..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div>
            <label class="form-label" style="font-size: 0.8rem;">Tanggal Mulai</label>
            <input type="date" name="tgl_mulai" class="form-control" value="<?= htmlspecialchars($tglMulai) ?>">
        </div>

        <div>
            <label class="form-label" style="font-size: 0.8rem;">Tanggal Selesai</label>
            <input type="date" name="tgl_selesai" class="form-control" value="<?= htmlspecialchars($tglSelesai) ?>">
        </div>

        <div>
            <label class="form-label" style="font-size: 0.8rem;">Status Pesanan</label>
            <select name="status_pesanan" class="form-control">
                <option value="">Semua Status</option>
                <option value="menunggu" <?= $statusPesanan === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                <option value="diterima" <?= $statusPesanan === 'diterima' ? 'selected' : '' ?>>Diterima</option>
                <option value="dimasak" <?= $statusPesanan === 'dimasak' ? 'selected' : '' ?>>Dimasak</option>
                <option value="diantar" <?= $statusPesanan === 'diantar' ? 'selected' : '' ?>>Diantar</option>
                <option value="selesai" <?= $statusPesanan === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                <option value="dibatalkan" <?= $statusPesanan === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
        </div>

        <div>
            <label class="form-label" style="font-size: 0.8rem;">Pembayaran</label>
            <select name="status_bayar" class="form-control">
                <option value="">Semua</option>
                <option value="lunas" <?= $statusBayar === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                <option value="belum_bayar" <?= $statusBayar === 'belum_bayar' ? 'selected' : '' ?>>Belum Bayar</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-secondary btn-block">
                <i class="fa-solid fa-filter"></i> Filter
            </button>
            <a href="<?= BASE_URL ?>/admin/transaksi/index.php" class="btn btn-outline" title="Reset">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        </div>
    </form>
</div>

<!-- Transaction Table Card -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title"><i class="fa-solid fa-receipt" style="color: var(--primary); margin-right: 8px;"></i>Log Transaksi Restoran</h3>
            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">
                Periode: <?= formatTanggalIndo($tglMulai . ' 00:00:00', false) ?> s/d <?= formatTanggalIndo($tglSelesai . ' 00:00:00', false) ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 0.8rem; color: var(--text-secondary);">Total Omset Lunas:</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary);"><?= formatRupiah($totalOmset) ?></div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Waktu & Tanggal</th>
                    <th>Nomor Order</th>
                    <th>Meja / Pelanggan</th>
                    <th>Kasir</th>
                    <th>Status Pesanan</th>
                    <th>Status Bayar</th>
                    <th>Metode</th>
                    <th>Total Tagihan</th>
                    <th style="width: 110px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-secondary); padding: 36px;">
                            Tidak ditemukan data transaksi untuk filter yang dipilih.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $ord): ?>
                        <?php
                        $badgeSt = 'badge-olive';
                        if ($ord['status_pesanan'] === 'menunggu') $badgeSt = 'badge-gold';
                        elseif ($ord['status_pesanan'] === 'dimasak' || $ord['status_pesanan'] === 'diantar') $badgeSt = 'badge-terracotta';
                        elseif ($ord['status_pesanan'] === 'dibatalkan') $badgeSt = 'badge';
                        ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($ord['created_at'])) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);"><?= date('H:i', strtotime($ord['created_at'])) ?> WIB</div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($ord['nomor_order']) ?></strong>
                                <?php if (!empty($ord['nomor_transaksi'])): ?>
                                    <div style="font-size: 0.72rem; color: var(--text-secondary);"><code><?= htmlspecialchars($ord['nomor_transaksi']) ?></code></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($ord['nomor_meja'] ?? 'Takeaway') ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);"><?= htmlspecialchars($ord['customer_name'] ?? 'Pelanggan') ?></div>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-primary);"><?= htmlspecialchars($ord['nama_kasir'] ?: 'Self Order') ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $badgeSt ?>"><?= ucfirst($ord['status_pesanan']) ?></span>
                            </td>
                            <td>
                                <?php if ($ord['status_pembayaran'] === 'lunas'): ?>
                                    <span class="badge badge-olive">Lunas</span>
                                <?php else: ?>
                                    <span class="badge badge-gold">Belum Bayar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-secondary);"><?= strtoupper($ord['metode_pembayaran'] ?: '-') ?></span>
                            </td>
                            <td>
                                <strong style="color: var(--text-primary);"><?= formatRupiah($ord['total_amount']) ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 4px; justify-content: center;">
                                    <a href="<?= BASE_URL ?>/admin/transaksi/detail.php?id=<?= $ord['id'] ?>" class="btn btn-outline" style="padding: 6px 10px; font-size: 0.8rem;" title="Detail Transaksi">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <?php if ($ord['status_pembayaran'] === 'lunas'): ?>
                                        <a href="<?= BASE_URL ?>/kasir/cetak_struk.php?order_id=<?= $ord['id'] ?>" target="_blank" class="btn btn-outline" style="padding: 6px 10px; font-size: 0.8rem;" title="Cetak Struk">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
