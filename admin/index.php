<?php
/**
 * Admin Dashboard - DAPUR TYAS by TECNO GARAGE
 * Phase 06: Real-time Statistics, Recent Orders, Top Menus, Table Monitoring, Charts
 */

$pageTitle = 'Dashboard Ringkasan';
$activeMenu = 'dashboard';
require_once dirname(__DIR__) . '/includes/admin_header.php';

$pdo = db();
$today = date('Y-m-d');

// 1. Total Penjualan Hari Ini (dari payments yang lunas)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(nominal_bayar), 0) AS total_sales, COUNT(id) AS total_trx 
                       FROM payments 
                       WHERE DATE(paid_at) = :today AND status = 'lunas'");
$stmt->execute(['today' => $today]);
$salesData = $stmt->fetch();
$penjualanHariIni = (float) ($salesData['total_sales'] ?? 0);
$jumlahTransaksiHariIni = (int) ($salesData['total_trx'] ?? 0);

// 2. Keuntungan / Gross Profit Hari Ini (Harga Jual - Harga Modal dari order_details yang lunas)
$stmt = $pdo->prepare("SELECT COALESCE(SUM((od.harga_satuan - m.harga_modal) * od.quantity), 0) AS gross_profit
                       FROM order_details od
                       JOIN orders o ON od.order_id = o.id
                       JOIN menus m ON od.menu_id = m.id
                       WHERE DATE(o.created_at) = :today AND o.status_pembayaran = 'lunas'");
$stmt->execute(['today' => $today]);
$profitData = $stmt->fetch();
$keuntunganHariIni = (float) ($profitData['gross_profit'] ?? 0);

// 3. Jumlah Pesanan Hari Ini
$stmt = $pdo->prepare("SELECT COUNT(id) AS total_orders FROM orders WHERE DATE(created_at) = :today");
$stmt->execute(['today' => $today]);
$jumlahPesananHariIni = (int) ($stmt->fetch()['total_orders'] ?? 0);

// 4. Jumlah Total Menu
$stmt = $pdo->query("SELECT COUNT(id) AS total_menu FROM menus WHERE status != 'nonaktif'");
$jumlahMenu = (int) ($stmt->fetch()['total_menu'] ?? 0);

// 5. Menu dengan Stok Menipis (stok <= min_stok)
$stmt = $pdo->query("SELECT id, nama_menu, stok, min_stok, foto FROM menus WHERE stok <= min_stok AND status != 'nonaktif' ORDER BY stok ASC LIMIT 6");
$stokMenipisList = $stmt->fetchAll();
$totalStokMenipis = count($stokMenipisList);

// 6. Pesanan Terbaru (5 Terakhir)
$stmt = $pdo->query("SELECT o.*, t.nomor_meja 
                     FROM orders o 
                     LEFT JOIN tables t ON o.table_id = t.id 
                     ORDER BY o.created_at DESC LIMIT 5");
$pesananTerbaru = $stmt->fetchAll();

// 7. Produk Terlaris (Top 5 Menu Paling Banyak Dipesan)
$stmt = $pdo->query("SELECT m.nama_menu, m.foto, c.nama_kategori, COALESCE(SUM(od.quantity), 0) AS total_terjual, COALESCE(SUM(od.subtotal), 0) AS total_omset
                     FROM menus m
                     LEFT JOIN order_details od ON m.id = od.menu_id
                     LEFT JOIN categories c ON m.category_id = c.id
                     GROUP BY m.id
                     ORDER BY total_terjual DESC, m.nama_menu ASC
                     LIMIT 5");
$produkTerlaris = $stmt->fetchAll();

// 8. Monitoring Status Meja Restoran
$stmt = $pdo->query("SELECT t.*, 
                     (SELECT o.status_pesanan FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS live_status_order,
                     (SELECT o.nomor_order FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS live_order_num
                     FROM tables t 
                     ORDER BY t.nomor_meja ASC");
$mejaList = $stmt->fetchAll();

// 9. Data Grafik Penjualan 7 Hari Terakhir
$chartLabels = [];
$chartSales = [];
$chartTrx = [];

for ($i = 6; $i >= 0; $i--) {
    $dateLoop = date('Y-m-d', strtotime("-$i days"));
    $labelDate = date('d M', strtotime($dateLoop));
    $chartLabels[] = $labelDate;

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(nominal_bayar), 0) AS sales, COUNT(id) AS trx 
                           FROM payments 
                           WHERE DATE(paid_at) = :dt AND status = 'lunas'");
    $stmt->execute(['dt' => $dateLoop]);
    $res = $stmt->fetch();

    $chartSales[] = (float) ($res['sales'] ?? 0);
    $chartTrx[] = (int) ($res['trx'] ?? 0);
}
?>

<!-- Section: Stats Widgets Grid -->
<div class="grid-stats">
    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-terracotta">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Penjualan Hari Ini</div>
            <div class="stat-value"><?= formatRupiah($penjualanHariIni) ?></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-olive">
            <i class="fa-solid fa-arrow-trend-up"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Keuntungan Hari Ini</div>
            <div class="stat-value"><?= formatRupiah($keuntunganHariIni) ?></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-gold">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Transaksi Selesai</div>
            <div class="stat-value"><?= $jumlahTransaksiHariIni ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">transaksi</span></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-blue">
            <i class="fa-solid fa-bell-concierge"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Total Pesanan Hari Ini</div>
            <div class="stat-value"><?= $jumlahPesananHariIni ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">pesanan</span></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-terracotta">
            <i class="fa-solid fa-utensils"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Total Menu Aktif</div>
            <div class="stat-value"><?= $jumlahMenu ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">menu</span></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle" style="background-color: <?= $totalStokMenipis > 0 ? 'var(--status-danger-bg)' : 'var(--status-success-bg)' ?>; color: <?= $totalStokMenipis > 0 ? 'var(--status-danger)' : 'var(--status-success)' ?>;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Stok Menipis</div>
            <div class="stat-value" style="color: <?= $totalStokMenipis > 0 ? 'var(--status-danger)' : 'var(--status-success)' ?>;">
                <?= $totalStokMenipis ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">item</span>
            </div>
        </div>
    </div>
</div>

<!-- Section: Charts Row -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Grafik Penjualan 7 Hari -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-chart-line" style="color: var(--primary); margin-right: 8px;"></i>Tren Penjualan 7 Hari Terakhir</h3>
        </div>
        <div style="height: 280px;">
            <canvas id="chartSales"></canvas>
        </div>
    </div>

    <!-- Monitoring Meja Restoran Mini -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-chair" style="color: var(--accent-olive); margin-right: 8px;"></i>Monitoring Meja</h3>
            <a href="<?= BASE_URL ?>/admin/meja/index.php" style="font-size: 0.8rem; font-weight: 600;">Lihat Semua &rarr;</a>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; max-height: 280px; overflow-y: auto;">
            <?php foreach ($mejaList as $m): ?>
                <?php
                $bgMeja = '#F1ECE1';
                $borderMeja = '#E6E0D5';
                $statusText = 'Tersedia';
                $badgeClass = 'badge-olive';

                if ($m['status'] === 'terisi' || !empty($m['live_status_order'])) {
                    $bgMeja = 'var(--primary-light)';
                    $borderMeja = 'var(--primary)';
                    $badgeClass = 'badge-terracotta';
                    if ($m['live_status_order'] === 'menunggu') {
                        $statusText = 'Menunggu';
                    } elseif ($m['live_status_order'] === 'dimasak') {
                        $statusText = 'Dimasak';
                    } elseif ($m['live_status_order'] === 'diantar') {
                        $statusText = 'Diantar';
                    } else {
                        $statusText = 'Digunakan';
                    }
                } elseif ($m['status'] === 'reservasi') {
                    $bgMeja = 'var(--accent-gold-light)';
                    $borderMeja = 'var(--accent-gold)';
                    $badgeClass = 'badge-gold';
                    $statusText = 'Reservasi';
                }
                ?>
                <div style="background: <?= $bgMeja ?>; border: 1.5px solid <?= $borderMeja ?>; border-radius: var(--radius-md); padding: 12px 8px; text-align: center;">
                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary);"><?= htmlspecialchars($m['nomor_meja']) ?></div>
                    <div style="font-size: 0.72rem; color: var(--text-secondary); margin: 2px 0 6px 0;">Kapasitas: <?= $m['kapasitas'] ?> Org</div>
                    <span class="badge <?= $badgeClass ?>" style="font-size: 0.65rem; padding: 2px 6px;"><?= $statusText ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Section: Pesanan Terbaru & Produk Terlaris -->
<div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px;">
    <!-- Pesanan Terbaru Table -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color: var(--primary); margin-right: 8px;"></i>Pesanan Terbaru</h3>
            <a href="<?= BASE_URL ?>/admin/pesanan/index.php" style="font-size: 0.8rem; font-weight: 600;">Lihat Pesanan &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Meja / Pelanggan</th>
                        <th>Status</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pesananTerbaru)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-secondary); padding: 28px;">
                                Belum ada pesanan yang tercatat hari ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pesananTerbaru as $order): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($order['nomor_order']) ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary);"><?= date('H:i', strtotime($order['created_at'])) ?> WIB</div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($order['nomor_meja'] ?? 'Takeaway') ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary);"><?= htmlspecialchars($order['customer_name'] ?? 'Pelanggan') ?></div>
                                </td>
                                <td>
                                    <?php
                                    $stBadge = 'badge-olive';
                                    $stLabel = ucfirst($order['status_pesanan']);
                                    if ($order['status_pesanan'] === 'menunggu') {
                                        $stBadge = 'badge-gold';
                                        $stLabel = 'Menunggu';
                                    } elseif ($order['status_pesanan'] === 'dimasak') {
                                        $stBadge = 'badge-terracotta';
                                        $stLabel = 'Sedang Dibuat';
                                    } elseif ($order['status_pesanan'] === 'diantar') {
                                        $stBadge = 'badge-terracotta';
                                        $stLabel = 'Sedang Diantar';
                                    } elseif ($order['status_pesanan'] === 'selesai') {
                                        $stBadge = 'badge-olive';
                                        $stLabel = 'Selesai';
                                    }
                                    ?>
                                    <span class="badge <?= $stBadge ?>"><?= $stLabel ?></span>
                                </td>
                                <td>
                                    <strong><?= formatRupiah($order['total_amount']) ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Produk Terlaris Card -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-fire" style="color: var(--primary); margin-right: 8px;"></i>Produk Terlaris</h3>
            <a href="<?= BASE_URL ?>/admin/menu/index.php" style="font-size: 0.8rem; font-weight: 600;">Lihat Menu &rarr;</a>
        </div>
        <div>
            <?php if (empty($produkTerlaris)): ?>
                <div style="text-align: center; color: var(--text-secondary); padding: 28px;">
                    Belum ada data penjualan produk.
                </div>
            <?php else: ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($produkTerlaris as $idx => $menu): ?>
                        <li style="display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-subtle);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: <?= $idx === 0 ? 'var(--primary)' : 'var(--bg-surface-soft)' ?>; color: <?= $idx === 0 ? '#FFFFFF' : 'var(--text-primary)' ?>; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem;">
                                    <?= $idx + 1 ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-primary);"><?= htmlspecialchars($menu['nama_menu']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary);"><?= htmlspecialchars($menu['nama_kategori'] ?? 'Menu') ?></div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 700; font-size: 0.85rem; color: var(--primary);"><?= (int)$menu['total_terjual'] ?> Porsi</div>
                                <div style="font-size: 0.72rem; color: var(--text-secondary);"><?= formatRupiah($menu['total_omset']) ?></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('chartSales').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Penjualan (Rp)',
                data: <?= json_encode($chartSales) ?>,
                borderColor: '#C85A32',
                backgroundColor: 'rgba(200, 90, 50, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 2.5,
                pointBackgroundColor: '#C85A32',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        },
                        font: { size: 10 }
                    },
                    grid: { color: '#ECE7DE' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 } }
                }
            }
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/admin_footer.php'; ?>
