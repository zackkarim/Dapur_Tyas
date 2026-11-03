<?php
/**
 * Manajemen Keuangan & Pengeluaran Restoran - DAPUR TYAS by TECNO GARAGE
 * Phase 24: Real-time Sales, COGS (HPP), Expenses, Gross Profit, and Net Profit Calculation
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$pdo = db();
$bulan = trim($_GET['bulan'] ?? date('Y-m'));
$startDate = $bulan . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Handle Tambah Pengeluaran via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrfToken)) {
        setFlash('error', 'Token keamanan tidak valid.', 'danger');
        redirect('admin/keuangan/index.php?bulan=' . $bulan);
    }

    $namaPengeluaran = trim($_POST['nama_pengeluaran'] ?? '');
    $kategori = trim($_POST['kategori'] ?? 'Operasional');
    $nominal = (float) ($_POST['nominal'] ?? 0);
    $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if (empty($namaPengeluaran) || $nominal <= 0 || empty($tanggal)) {
        setFlash('error', 'Nama pengeluaran, nominal, dan tanggal wajib diisi.', 'danger');
        redirect('admin/keuangan/index.php?bulan=' . $bulan);
    }

    $stmtExp = $pdo->prepare("INSERT INTO expenses (nama_pengeluaran, kategori_pengeluaran, nominal, tanggal, keterangan, user_id) 
                              VALUES (:nama, :kat, :nom, :tgl, :ket, :uid)");
    $stmtExp->execute([
        'nama' => $namaPengeluaran,
        'kat'  => $kategori,
        'nom'  => $nominal,
        'tgl'  => $tanggal,
        'ket'  => $deskripsi,
        'uid'  => getAuthUser()['id'] ?? 1
    ]);

    setFlash('success', "Pengeluaran '{$namaPengeluaran}' sebesar " . formatRupiah($nominal) . " berhasil dicatat.");
    redirect('admin/keuangan/index.php?bulan=' . $bulan);
}

// 1. Total Penjualan (Revenue) Bulan Ini
$stmtRev = $pdo->prepare("SELECT COALESCE(SUM(nominal_bayar), 0) AS total_penjualan 
                          FROM payments 
                          WHERE DATE(paid_at) >= :s AND DATE(paid_at) <= :e AND status = 'lunas'");
$stmtRev->execute(['s' => $startDate, 'e' => $endDate]);
$totalPenjualan = (float) $stmtRev->fetchColumn();

// 2. Total Harga Modal Produk Terjual (HPP / COGS) Bulan Ini
$stmtHpp = $pdo->prepare("SELECT COALESCE(SUM(m.harga_modal * od.quantity), 0) AS total_hpp
                          FROM order_details od
                          JOIN orders o ON od.order_id = o.id
                          JOIN menus m ON od.menu_id = m.id
                          WHERE DATE(o.created_at) >= :s AND DATE(o.created_at) <= :e AND o.status_pembayaran = 'lunas'");
$stmtHpp->execute(['s' => $startDate, 'e' => $endDate]);
$totalHpp = (float) $stmtHpp->fetchColumn();

// 3. Gross Profit (Laba Kotor = Penjualan - HPP)
$grossProfit = $totalPenjualan - $totalHpp;

// 4. Total Pengeluaran Operasional Bulan Ini
$stmtExpTotal = $pdo->prepare("SELECT COALESCE(SUM(nominal), 0) AS total_pengeluaran 
                               FROM expenses 
                               WHERE tanggal >= :s AND tanggal <= :e");
$stmtExpTotal->execute(['s' => $startDate, 'e' => $endDate]);
$totalPengeluaran = (float) $stmtExpTotal->fetchColumn();

// 5. Net Profit (Laba Bersih = Gross Profit - Pengeluaran)
$netProfit = $grossProfit - $totalPengeluaran;
$isProfit = ($netProfit >= 0);

// Ambil Daftar Pengeluaran Bulan Ini
$stmtExpList = $pdo->prepare("SELECT e.*, u.nama AS nama_petugas 
                              FROM expenses e 
                              LEFT JOIN users u ON e.user_id = u.id 
                              WHERE e.tanggal >= :s AND e.tanggal <= :e 
                              ORDER BY e.tanggal DESC, e.id DESC");
$stmtExpList->execute(['s' => $startDate, 'e' => $endDate]);
$expensesList = $stmtExpList->fetchAll();

$pageTitle = 'Keuangan & Laba/Rugi';
$activeMenu = 'keuangan';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';
?>

<!-- Month Selector Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <form method="GET" action="<?= BASE_URL ?>/admin/keuangan/index.php" style="display: flex; gap: 10px; align-items: center;">
        <label style="font-weight: 700; font-size: 0.9rem; color: var(--text-primary);">Pilih Periode Bulan:</label>
        <input type="month" name="bulan" class="form-control" style="width: 180px;" value="<?= htmlspecialchars($bulan) ?>" onchange="this.form.submit()">
        <button type="submit" class="btn btn-secondary">Lihat</button>
    </form>

    <button type="button" class="btn btn-primary" onclick="openExpenseModal()">
        <i class="fa-solid fa-plus"></i> Catat Pengeluaran Baru
    </button>
</div>

<!-- Financial Summary Widgets Grid -->
<div class="grid-stats" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));">
    <!-- Total Penjualan -->
    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-terracotta">
            <i class="fa-solid fa-coins"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Total Penjualan</div>
            <div class="stat-value"><?= formatRupiah($totalPenjualan) ?></div>
        </div>
    </div>

    <!-- HPP / Modal Bahan -->
    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-gold">
            <i class="fa-solid fa-boxes-packing"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Harga Modal (HPP)</div>
            <div class="stat-value" style="color: var(--status-warning);"><?= formatRupiah($totalHpp) ?></div>
        </div>
    </div>

    <!-- Gross Profit -->
    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-olive">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Laba Kotor (Gross)</div>
            <div class="stat-value" style="color: var(--status-success);"><?= formatRupiah($grossProfit) ?></div>
        </div>
    </div>

    <!-- Pengeluaran Operasional -->
    <div class="stat-widget">
        <div class="stat-icon-circle" style="background-color: var(--status-danger-bg); color: var(--status-danger);">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Biaya Operasional</div>
            <div class="stat-value" style="color: var(--status-danger);"><?= formatRupiah($totalPengeluaran) ?></div>
        </div>
    </div>

    <!-- Net Profit / Kerugian -->
    <div class="stat-widget" style="border: 2px solid <?= $isProfit ? 'var(--status-success)' : 'var(--status-danger)' ?>; background: <?= $isProfit ? 'var(--status-success-bg)' : 'var(--status-danger-bg)' ?>;">
        <div class="stat-icon-circle" style="background: #FFFFFF; color: <?= $isProfit ? 'var(--status-success)' : 'var(--status-danger)' ?>;">
            <i class="fa-solid <?= $isProfit ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label" style="color: <?= $isProfit ? 'var(--status-success)' : 'var(--status-danger)' ?>; font-weight: 800;">
                <?= $isProfit ? 'KEUNTUNGAN BERSIH (NET)' : 'KERUGIAN BERSIH' ?>
            </div>
            <div class="stat-value" style="color: <?= $isProfit ? 'var(--status-success)' : 'var(--status-danger)' ?>;">
                <?= formatRupiah(abs($netProfit)) ?>
            </div>
        </div>
    </div>
</div>

<!-- Formula Breakdown Banner -->
<div class="card" style="padding: 18px 24px; background: var(--bg-surface-soft); border-left: 4px solid var(--primary); margin-bottom: 24px;">
    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 6px;">
        <i class="fa-solid fa-calculator" style="color: var(--primary); margin-right: 6px;"></i> Struktur Perhitungan Keuangan Restoran
    </div>
    <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.6;">
        &bull; <strong>Laba Kotor (Gross Profit)</strong> = Penjualan (<?= formatRupiah($totalPenjualan) ?>) &minus; Harga Modal Produk (<?= formatRupiah($totalHpp) ?>) = <strong><?= formatRupiah($grossProfit) ?></strong><br>
        &bull; <strong>Laba Bersih (Net Profit)</strong> = Laba Kotor (<?= formatRupiah($grossProfit) ?>) &minus; Pengeluaran Operasional (<?= formatRupiah($totalPengeluaran) ?>) = <strong style="color: <?= $isProfit ? 'var(--status-success)' : 'var(--status-danger)' ?>;"><?= formatRupiah($netProfit) ?></strong>
    </div>
</div>

<!-- Expenses Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-wallet" style="color: var(--primary); margin-right: 8px;"></i>Pencatatan Biaya & Pengeluaran Operasional</h3>
        <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= count($expensesList) ?> Catatan</span>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nama Pengeluaran</th>
                    <th>Kategori</th>
                    <th>Keterangan</th>
                    <th>Dicatat Oleh</th>
                    <th>Nominal Biaya</th>
                    <th style="width: 90px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expensesList)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 36px;">
                            Belum ada catatan pengeluaran pada bulan <?= date('F Y', strtotime($startDate)) ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expensesList as $exp): ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($exp['tanggal'])) ?></strong>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($exp['nama_pengeluaran']) ?></strong>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--bg-surface-soft); color: var(--text-primary);">
                                    <?= htmlspecialchars($exp['kategori_pengeluaran'] ?? 'Operasional') ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($exp['keterangan'] ?: '-') ?>
                            </td>
                            <td>
                                <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= htmlspecialchars($exp['nama_petugas'] ?: 'Admin') ?></span>
                            </td>
                            <td>
                                <strong style="color: var(--status-danger);"><?= formatRupiah($exp['nominal']) ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <a href="<?= BASE_URL ?>/admin/keuangan/hapus.php?id=<?= $exp['id'] ?>&bulan=<?= urlencode($bulan) ?>" 
                                   class="btn btn-outline" style="padding: 6px 10px; font-size: 0.8rem; color: var(--status-danger); border-color: #FECACA;" 
                                   onclick="return confirm('Hapus catatan pengeluaran ini?');" title="Hapus">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Tambah Pengeluaran -->
<div id="modalExpense" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 480px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">Catat Pengeluaran Baru</h3>
            <button type="button" onclick="closeExpenseModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/admin/keuangan/index.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="nama_pengeluaran">Nama / Keperluan Pengeluaran</label>
                <input type="text" id="nama_pengeluaran" name="nama_pengeluaran" class="form-control" placeholder="Contoh: Beli Tabung Gas, Token Listrik, Plastik Cup" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="kategori">Kategori</label>
                    <select id="kategori" name="kategori" class="form-control">
                        <option value="Bahan Baku / Dapur">Bahan Baku / Dapur</option>
                        <option value="Operasional Resto" selected>Operasional Resto</option>
                        <option value="Listrik & Air">Listrik & Air</option>
                        <option value="Gaji & Uang Makan">Gaji & Uang Makan</option>
                        <option value="Peralatan & Maintenance">Peralatan & Maintenance</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="tanggal">Tanggal Transaksi</label>
                    <input type="date" id="tanggal" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="nominal">Nominal Biaya (Rp)</label>
                <input type="number" id="nominal" name="nominal" class="form-control" style="font-size: 1.1rem; font-weight: 700;" placeholder="0" min="1000" step="1000" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="deskripsi">Keterangan Tambahan</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="2" placeholder="Catatan toko, no nota, atau info lainnya..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeExpenseModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Simpan Pengeluaran
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openExpenseModal() {
    document.getElementById('modalExpense').style.display = 'flex';
}

function closeExpenseModal() {
    document.getElementById('modalExpense').style.display = 'none';
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
