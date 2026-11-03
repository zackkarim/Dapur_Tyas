<?php
/**
 * Detail Transaksi - DAPUR TYAS by TECNO GARAGE
 * Phase 23: Complete Order Breakdown, History Audit Trail, and Payment Metadata
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('admin/transaksi/index.php');
}

$pdo = db();

$stmt = $pdo->prepare("SELECT o.*, t.nomor_meja, u.nama AS nama_kasir, p.nomor_transaksi, p.metode_pembayaran, p.nominal_bayar, p.kembalian, p.paid_at 
                       FROM orders o
                       LEFT JOIN tables t ON o.table_id = t.id
                       LEFT JOIN users u ON o.user_id = u.id
                       LEFT JOIN payments p ON o.id = p.order_id
                       WHERE o.id = :id");
$stmt->execute(['id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Transaksi tidak ditemukan.', 'danger');
    redirect('admin/transaksi/index.php');
}

// Ambil Items
$stmtItems = $pdo->prepare("SELECT od.*, m.nama_menu, m.kode_menu, m.harga_modal 
                            FROM order_details od
                            JOIN menus m ON od.menu_id = m.id
                            WHERE od.order_id = :id");
$stmtItems->execute(['id' => $id]);
$items = $stmtItems->fetchAll();

// Ambil Histori Status
$stmtHistory = $pdo->prepare("SELECT osh.*, u.nama AS perubah_nama 
                             FROM order_status_history osh 
                             LEFT JOIN users u ON osh.user_id = u.id 
                             WHERE osh.order_id = :id 
                             ORDER BY osh.created_at ASC");
$stmtHistory->execute(['id' => $id]);
$history = $stmtHistory->fetchAll();

$pageTitle = 'Detail Transaksi';
$activeMenu = 'transaksi';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';
?>

<!-- Back Button & Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <a href="<?= BASE_URL ?>/admin/transaksi/index.php" class="btn btn-outline">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Riwayat Transaksi
    </a>

    <?php if ($order['status_pembayaran'] === 'lunas'): ?>
        <a href="<?= BASE_URL ?>/kasir/cetak_struk.php?order_id=<?= $order['id'] ?>" target="_blank" class="btn btn-primary">
            <i class="fa-solid fa-print"></i> Cetak Struk Transaksi
        </a>
    <?php endif; ?>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Items & Breakdown -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-utensils" style="color: var(--primary); margin-right: 8px;"></i>Item Pesanan</h3>
                <span class="badge badge-olive"><?= count($items) ?> Menu</span>
            </div>

            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Harga Modal (HPP)</th>
                            <th>Harga Jual</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                            <th>Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalModal = 0.00;
                        foreach ($items as $it): 
                            $hpp = (float)$it['harga_modal'] * (int)$it['quantity'];
                            $totalModal += $hpp;
                            $profit = (float)$it['subtotal'] - $hpp;
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($it['nama_menu']) ?></strong>
                                    <?php if (!empty($it['catatan'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary); font-style: italic;">* <?= htmlspecialchars($it['catatan']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-secondary);"><?= formatRupiah($it['harga_modal']) ?></td>
                                <td><?= formatRupiah($it['harga_satuan']) ?></td>
                                <td><strong><?= $it['quantity'] ?></strong></td>
                                <td><strong><?= formatRupiah($it['subtotal']) ?></strong></td>
                                <td><span class="badge badge-olive">+<?= formatRupiah($profit) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 20px; padding: 16px; background: var(--bg-surface-soft); border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">Total Modal (HPP): <?= formatRupiah($totalModal) ?></div>
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--accent-olive);">Estimasi Gross Profit: +<?= formatRupiah((float)$order['total_amount'] - $totalModal) ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.8rem; color: var(--text-secondary);">Total Tagihan:</div>
                    <div style="font-size: 1.35rem; font-weight: 800; color: var(--primary);"><?= formatRupiah($order['total_amount']) ?></div>
                </div>
            </div>
        </div>

        <!-- Audit Trail / Order Status History -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-timeline" style="color: var(--primary); margin-right: 8px;"></i>Audit Trail Perjalanan Status</h3>
            </div>

            <ul style="list-style: none; padding: 0;">
                <?php foreach ($history as $h): ?>
                    <li style="display: flex; gap: 14px; padding: 12px 0; border-bottom: 1px dashed var(--border-subtle);">
                        <div style="font-size: 1.2rem; color: var(--primary);">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between;">
                                <strong style="color: var(--text-primary); text-transform: uppercase; font-size: 0.85rem;"><?= htmlspecialchars($h['status_sesudah']) ?></strong>
                                <span style="font-size: 0.75rem; color: var(--text-secondary);"><?= date('d/m/Y H:i:s', strtotime($h['created_at'])) ?> WIB</span>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">
                                <?= htmlspecialchars($h['catatan'] ?: '-') ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Metadata Sidebar -->
    <div>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informasi Pesanan</h3>
            </div>

            <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.9rem;">
                <div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Nomor Order:</div>
                    <strong><?= htmlspecialchars($order['nomor_order']) ?></strong>
                </div>

                <?php if (!empty($order['nomor_transaksi'])): ?>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">Nomor Transaksi Pembayaran:</div>
                        <code><?= htmlspecialchars($order['nomor_transaksi']) ?></code>
                    </div>
                <?php endif; ?>

                <div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Meja Restoran:</div>
                    <strong><?= htmlspecialchars($order['nomor_meja'] ?? 'Takeaway') ?></strong>
                </div>

                <div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Nama Pelanggan:</div>
                    <strong><?= htmlspecialchars($order['customer_name'] ?? 'Pelanggan') ?></strong>
                </div>

                <div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Petugas / Kasir:</div>
                    <strong><?= htmlspecialchars($order['nama_kasir'] ?: 'Self Order Pelanggan') ?></strong>
                </div>

                <div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Waktu Pembuatan:</div>
                    <div><?= formatTanggalIndo($order['created_at']) ?></div>
                </div>

                <div style="border-top: 1px solid var(--border-subtle); padding-top: 12px;">
                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Status Pembayaran:</div>
                    <div style="margin-top: 4px;">
                        <?php if ($order['status_pembayaran'] === 'lunas'): ?>
                            <span class="badge badge-olive">Lunas (<?= strtoupper($order['metode_pembayaran'] ?? 'TUNAI') ?>)</span>
                        <?php else: ?>
                            <span class="badge badge-gold">Belum Bayar</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($order['status_pembayaran'] === 'lunas'): ?>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">Nominal Bayar:</div>
                        <strong><?= formatRupiah($order['nominal_bayar']) ?></strong>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">Kembalian:</div>
                        <strong><?= formatRupiah($order['kembalian']) ?></strong>
                    </div>
                <?php endif; ?>

                <?php if (!empty($order['catatan'])): ?>
                    <div style="border-top: 1px solid var(--border-subtle); padding-top: 12px;">
                        <div style="font-size: 0.75rem; color: var(--text-secondary);">Catatan Khusus:</div>
                        <div style="font-size: 0.85rem; color: var(--text-primary); font-style: italic;">
                            "<?= htmlspecialchars($order['catatan']) ?>"
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
