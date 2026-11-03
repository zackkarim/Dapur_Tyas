<?php
/**
 * Riwayat Mutasi Stok - DAPUR TYAS by TECNO GARAGE
 * Phase 09: Stock Movements Audit Log
 */

$pageTitle = 'Riwayat Mutasi Stok';
$activeMenu = 'stok';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';

$pdo = db();
$search = trim($_GET['search'] ?? '');
$tipeFilter = trim($_GET['tipe'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(m.nama_menu LIKE :search OR sm.keterangan LIKE :search OR u.nama LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($tipeFilter)) {
    $where[] = "sm.tipe = :tipe";
    $params['tipe'] = $tipeFilter;
}

$whereClause = implode(" AND ", $where);
$query = "SELECT sm.*, m.nama_menu, m.kode_menu, u.nama AS nama_petugas 
          FROM stock_movements sm
          JOIN menus m ON sm.menu_id = m.id
          LEFT JOIN users u ON sm.user_id = u.id
          WHERE $whereClause
          ORDER BY sm.created_at DESC
          LIMIT 100";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$movements = $stmt->fetchAll();
?>

<!-- Header Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <form method="GET" action="<?= BASE_URL ?>/admin/stok/riwayat.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; flex: 1;">
        <div style="min-width: 220px; flex: 1;">
            <input type="text" name="search" class="form-control" placeholder="Cari nama menu, alasan, atau petugas..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <select name="tipe" class="form-control" style="width: 170px;" onchange="this.form.submit()">
            <option value="">Semua Mutasi</option>
            <option value="masuk" <?= $tipeFilter === 'masuk' ? 'selected' : '' ?>>Stok Masuk</option>
            <option value="keluar" <?= $tipeFilter === 'keluar' ? 'selected' : '' ?>>Stok Keluar</option>
            <option value="penyesuaian" <?= $tipeFilter === 'penyesuaian' ? 'selected' : '' ?>>Penyesuaian Opname</option>
        </select>

        <button type="submit" class="btn btn-secondary">
            <i class="fa-solid fa-magnifying-glass"></i> Filter
        </button>

        <?php if (!empty($search) || !empty($tipeFilter)): ?>
            <a href="<?= BASE_URL ?>/admin/stok/riwayat.php" class="btn btn-outline" title="Reset Filter">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        <?php endif; ?>
    </form>

    <a href="<?= BASE_URL ?>/admin/stok/index.php" class="btn btn-outline">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke Stok
    </a>
</div>

<!-- Stock Movements Log Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary); margin-right: 8px;"></i>Audit Log Mutasi Stok</h3>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">100 Catatan Terakhir</span>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Waktu / Tanggal</th>
                    <th>Menu</th>
                    <th>Tipe Mutasi</th>
                    <th>Perubahan (Qty)</th>
                    <th>Sebelum &rarr; Sesudah</th>
                    <th>Alasan / Keterangan</th>
                    <th>Petugas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 36px;">
                            Belum ada riwayat mutasi stok.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <?php
                        $badgeTipe = 'badge-olive';
                        $iconTipe = 'fa-plus';
                        $qtySign = '+';
                        if ($m['tipe'] === 'keluar') {
                            $badgeTipe = 'badge-terracotta';
                            $iconTipe = 'fa-minus';
                            $qtySign = '-';
                        } elseif ($m['tipe'] === 'penyesuaian') {
                            $badgeTipe = 'badge-gold';
                            $iconTipe = 'fa-sliders';
                            $qtySign = '~';
                        }
                        ?>
                        <tr>
                            <td style="white-space: nowrap;">
                                <div style="font-weight: 600; color: var(--text-primary);"><?= date('d M Y', strtotime($m['created_at'])) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);"><?= date('H:i:s', strtotime($m['created_at'])) ?> WIB</div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($m['nama_menu']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <code><?= htmlspecialchars($m['kode_menu']) ?></code>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $badgeTipe ?>" style="font-size: 0.75rem;">
                                    <i class="fa-solid <?= $iconTipe ?>" style="margin-right: 4px;"></i> <?= ucfirst($m['tipe']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="font-size: 1rem; color: <?= $m['tipe'] === 'keluar' ? 'var(--status-danger)' : ($m['tipe'] === 'masuk' ? 'var(--status-success)' : 'var(--accent-gold)') ?>;">
                                    <?= $qtySign ?><?= (int)$m['jumlah'] ?>
                                </strong>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 0.85rem;">
                                <span><?= (int)$m['stok_sebelum'] ?></span> &rarr; <strong style="color: var(--text-primary);"><?= (int)$m['stok_sesudah'] ?></strong>
                            </td>
                            <td>
                                <?= htmlspecialchars($m['keterangan'] ?: '-') ?>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary);">
                                    <?= htmlspecialchars($m['nama_petugas'] ?: 'Sistem') ?>
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
