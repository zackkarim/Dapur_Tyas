<?php
/**
 * Monitoring Meja & Pesanan Live - DAPUR TYAS by TECNO GARAGE
 * Phase 26: Visual Table Monitoring Dashboard (Tersedia, Sedang Digunakan, Menunggu Pesanan, Sedang Dibuat, Sedang Diantar)
 */

$pageTitle = 'Monitoring Meja & Pesanan';
$activeMenu = 'pesanan';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';

$pdo = db();

// Ambil Seluruh Meja Aktif Beserta Status Pesanan Terbarunya
$stmtTables = $pdo->query("SELECT t.*, 
                                  (SELECT o.id FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS active_order_id,
                                  (SELECT o.nomor_order FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS active_order_num,
                                  (SELECT o.customer_name FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS active_customer,
                                  (SELECT o.status_pesanan FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS active_order_status,
                                  (SELECT o.status_pembayaran FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS active_payment_status,
                                  (SELECT o.total_amount FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS active_order_total,
                                  (SELECT o.created_at FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS active_order_time
                           FROM tables t
                           WHERE t.status != 'nonaktif'
                           ORDER BY t.nomor_meja ASC");
$tableList = $stmtTables->fetchAll();

// Hitung Statistik Status Meja
$totalMeja = count($tableList);
$mejaKosong = 0;
$mejaMenunggu = 0;
$mejaDimasak = 0;
$mejaDiantar = 0;
$mejaTerisi = 0;

foreach ($tableList as $tb) {
    if (empty($tb['active_order_status'])) {
        if ($tb['status'] === 'terisi') {
            $mejaTerisi++;
        } else {
            $mejaKosong++;
        }
    } else {
        if ($tb['active_order_status'] === 'menunggu') {
            $mejaMenunggu++;
        } elseif ($tb['active_order_status'] === 'diterima' || $tb['active_order_status'] === 'dimasak') {
            $mejaDimasak++;
        } elseif ($tb['active_order_status'] === 'diantar') {
            $mejaDiantar++;
        } else {
            $mejaTerisi++;
        }
    }
}

// Ambil Pesanan Aktif Tanpa Meja (Take Away)
$stmtTakeaway = $pdo->query("SELECT o.*, 
                                    (SELECT COALESCE(SUM(od.quantity), 0) FROM order_details od WHERE od.order_id = o.id) AS total_items
                             FROM orders o
                             WHERE o.table_id IS NULL AND o.status_pesanan NOT IN ('selesai', 'dibatalkan')
                             ORDER BY o.created_at DESC");
$takeawayOrders = $stmtTakeaway->fetchAll();
?>

<!-- Header & Stats -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin: 0 0 4px 0;">
                <i class="fa-solid fa-utensils" style="color: var(--primary); margin-right: 8px;"></i>
                Monitoring Status Meja & Pesanan Meja
            </h2>
            <div style="font-size: 0.88rem; color: var(--text-secondary);">
                Pantau ketersediaan meja makan dan alur pesanan pelanggan secara visual dan langsung.
            </div>
        </div>

        <div style="display: flex; gap: 8px; align-items: center;">
            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 700; color: var(--status-success); background: var(--status-success-bg); padding: 6px 14px; border-radius: 999px;">
                <span class="live-pulse" style="width: 8px; height: 8px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                Live Monitor Aktif
            </span>
            <button type="button" class="btn btn-secondary" onclick="window.location.reload()" style="padding: 6px 12px; font-size: 0.85rem;">
                <i class="fa-solid fa-arrows-rotate"></i> Refresh
            </button>
        </div>
    </div>
</div>

<!-- Table Status Legend & Summary Cards -->
<div class="grid-stats" style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); margin-bottom: 24px;">
    <!-- Tersedia -->
    <div class="stat-widget" style="border-left: 4px solid var(--status-success);">
        <div class="stat-icon-circle" style="background: var(--status-success-bg); color: var(--status-success);">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Tersedia</div>
            <div class="stat-value" style="color: var(--status-success);"><?= $mejaKosong ?> <span style="font-size: 0.8rem; font-weight: 500;">meja</span></div>
        </div>
    </div>

    <!-- Menunggu Pesanan -->
    <div class="stat-widget" style="border-left: 4px solid var(--status-warning);">
        <div class="stat-icon-circle" style="background: var(--status-warning-bg); color: var(--status-warning);">
            <i class="fa-solid fa-bell"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Menunggu Order</div>
            <div class="stat-value" style="color: var(--status-warning);"><?= $mejaMenunggu ?> <span style="font-size: 0.8rem; font-weight: 500;">meja</span></div>
        </div>
    </div>

    <!-- Sedang Dimasak -->
    <div class="stat-widget" style="border-left: 4px solid #3B82F6;">
        <div class="stat-icon-circle" style="background: #EFF6FF; color: #3B82F6;">
            <i class="fa-solid fa-fire-burner"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Sedang Dimasak</div>
            <div class="stat-value" style="color: #3B82F6;"><?= $mejaDimasak ?> <span style="font-size: 0.8rem; font-weight: 500;">meja</span></div>
        </div>
    </div>

    <!-- Sedang Diantar -->
    <div class="stat-widget" style="border-left: 4px solid #8B5CF6;">
        <div class="stat-icon-circle" style="background: #F5F3FF; color: #8B5CF6;">
            <i class="fa-solid fa-person-walking-luggage"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Sedang Diantar</div>
            <div class="stat-value" style="color: #8B5CF6;"><?= $mejaDiantar ?> <span style="font-size: 0.8rem; font-weight: 500;">meja</span></div>
        </div>
    </div>

    <!-- Total Meja -->
    <div class="stat-widget">
        <div class="stat-icon-circle" style="background: var(--bg-surface-soft); color: var(--text-primary);">
            <i class="fa-solid fa-chair"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Total Meja Resto</div>
            <div class="stat-value"><?= $totalMeja ?> <span style="font-size: 0.8rem; font-weight: 500;">meja</span></div>
        </div>
    </div>
</div>

<!-- Visual Tables Grid -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-border-all" style="color: var(--primary); margin-right: 8px;"></i>Peta Visual Meja Restoran</h3>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; padding: 20px;">
        <?php foreach ($tableList as $t): 
            $statusMeja = 'tersedia';
            $statusLabel = 'Tersedia';
            $statusBadgeClass = 'badge-success';
            $borderCardColor = '#10B981';
            $cardBg = '#FFFFFF';

            if (!empty($t['active_order_status'])) {
                switch ($t['active_order_status']) {
                    case 'menunggu':
                        $statusMeja = 'menunggu';
                        $statusLabel = 'Menunggu Pesanan';
                        $statusBadgeClass = 'badge-warning';
                        $borderCardColor = '#F59E0B';
                        $cardBg = '#FFFBEB';
                        break;
                    case 'diterima':
                    case 'dimasak':
                        $statusMeja = 'dimasak';
                        $statusLabel = 'Sedang Dibuat';
                        $statusBadgeClass = 'badge-info';
                        $borderCardColor = '#3B82F6';
                        $cardBg = '#EFF6FF';
                        break;
                    case 'diantar':
                        $statusMeja = 'diantar';
                        $statusLabel = 'Sedang Diantar';
                        $statusBadgeClass = 'badge-purple';
                        $borderCardColor = '#8B5CF6';
                        $cardBg = '#F5F3FF';
                        break;
                    default:
                        $statusMeja = 'terisi';
                        $statusLabel = 'Sedang Digunakan';
                        $statusBadgeClass = 'badge-danger';
                        $borderCardColor = '#EC4899';
                        $cardBg = '#FDF2F8';
                        break;
                }
            } elseif ($t['status'] === 'terisi') {
                $statusMeja = 'terisi';
                $statusLabel = 'Sedang Digunakan';
                $statusBadgeClass = 'badge-danger';
                $borderCardColor = '#EF4444';
                $cardBg = '#FEF2F2';
            } elseif ($t['status'] === 'reservasi') {
                $statusMeja = 'reservasi';
                $statusLabel = 'Reservasi';
                $statusBadgeClass = 'badge-warning';
                $borderCardColor = '#D97706';
                $cardBg = '#FFFBEB';
            }
        ?>
            <div style="background: <?= $cardBg ?>; border: 2px solid <?= $borderCardColor ?>; border-radius: var(--radius-lg); padding: 18px; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                
                <!-- Table Top Row -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <div style="font-weight: 800; font-size: 1.15rem; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-chair" style="color: <?= $borderCardColor ?>;"></i>
                        <?= htmlspecialchars($t['nomor_meja']) ?>
                    </div>
                    <span class="badge" style="background: rgba(0,0,0,0.06); font-size: 0.72rem; color: var(--text-secondary);">
                        <i class="fa-solid fa-users" style="margin-right: 3px;"></i> <?= $t['kapasitas'] ?> Kursi
                    </span>
                </div>

                <!-- Table Status Badge -->
                <div style="margin-bottom: 14px;">
                    <span class="badge <?= $statusBadgeClass ?>" style="font-size: 0.82rem; padding: 6px 12px;">
                        <?= $statusLabel ?>
                    </span>
                </div>

                <!-- Active Order Info (if any) -->
                <?php if (!empty($t['active_order_id'])): ?>
                    <div style="background: #FFFFFF; border-radius: 8px; padding: 10px; border: 1px solid var(--border-color); font-size: 0.8rem; margin-bottom: 12px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="color: var(--text-secondary);">Order:</span>
                            <strong style="color: var(--primary);">#<?= htmlspecialchars($t['active_order_num']) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="color: var(--text-secondary);">Pelanggan:</span>
                            <strong><?= htmlspecialchars($t['active_customer'] ?: 'Tamu') ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span style="color: var(--text-secondary);">Tagihan:</span>
                            <strong style="color: var(--text-primary);"><?= formatRupiah($t['active_order_total']) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--text-secondary);">Bayar:</span>
                            <span class="badge badge-<?= $t['active_payment_status'] === 'lunas' ? 'success' : 'danger' ?>" style="font-size: 0.7rem; padding: 2px 6px;">
                                <?= $t['active_payment_status'] === 'lunas' ? 'Lunas' : 'Belum Bayar' ?>
                            </span>
                        </div>
                    </div>
                    
                    <a href="<?= BASE_URL ?>/admin/transaksi/detail.php?id=<?= $t['active_order_id'] ?>" class="btn btn-outline" style="font-size: 0.78rem; padding: 6px; text-align: center; display: block; border-color: <?= $borderCardColor ?>; color: <?= $borderCardColor ?>;">
                        <i class="fa-solid fa-eye"></i> Detail Pesanan
                    </a>
                <?php else: ?>
                    <div style="padding: 18px 0; text-align: center; color: var(--text-secondary); font-size: 0.82rem;">
                        <i class="fa-solid fa-circle-check" style="color: #10B981; font-size: 1.3rem; display: block; margin-bottom: 6px;"></i>
                        <span style="font-weight: 700; color: #10B981;">Meja Kosong</span>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Siap menerima pesanan</div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Section: Pesanan Langsung / Take Away Aktif -->
<?php if (!empty($takeawayOrders)): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-bag-shopping" style="color: var(--primary); margin-right: 8px;"></i>Pesanan Take Away / Pesanan Langsung yang Sedang Berjalan</h3>
            <span class="badge badge-info"><?= count($takeawayOrders) ?> Pesanan</span>
        </div>
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>No. Order</th>
                        <th>Waktu Pesan</th>
                        <th>Nama Pelanggan</th>
                        <th style="text-align: center;">Jumlah Menu</th>
                        <th style="text-align: right;">Total Tagihan</th>
                        <th>Status Pesanan</th>
                        <th>Status Bayar</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($takeawayOrders as $to): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($to['nomor_order']) ?></strong></td>
                            <td><?= date('H:i', strtotime($to['created_at'])) ?> WIB</td>
                            <td><?= htmlspecialchars($to['customer_name'] ?: 'Pelanggan') ?></td>
                            <td style="text-align: center;"><?= $to['total_items'] ?> item</td>
                            <td style="text-align: right; font-weight: 700;"><?= formatRupiah($to['total_amount']) ?></td>
                            <td>
                                <span class="badge badge-warning"><?= ucfirst(htmlspecialchars($to['status_pesanan'])) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $to['status_pembayaran'] === 'lunas' ? 'success' : 'danger' ?>"><?= ucfirst(htmlspecialchars($to['status_pembayaran'])) ?></span>
                            </td>
                            <td style="text-align: center;">
                                <a href="<?= BASE_URL ?>/admin/transaksi/detail.php?id=<?= $to['id'] ?>" class="btn btn-outline" style="padding: 4px 10px; font-size: 0.8rem;">
                                    <i class="fa-solid fa-eye"></i> Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
// Auto-refresh monitoring setiap 15 detik
setInterval(function() {
    // Refresh otomatis halaman monitoring meja
    // window.location.reload();
}, 15000);
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
