<?php
/**
 * Manajemen Stok Menu - DAPUR TYAS by TECNO GARAGE
 * Phase 09: Live Stock Monitor, Stock In/Out/Adjustment, History Log, Prevention of Negative Stock
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$pdo = db();
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status_stok'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);

// Handle Perubahan Stok via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrfToken)) {
        setFlash('error', 'Token keamanan tidak valid.', 'danger');
        redirect('admin/stok/index.php');
    }

    $menuId = (int) ($_POST['menu_id'] ?? 0);
    $tipe = $_POST['tipe'] ?? ''; // 'masuk', 'keluar', 'penyesuaian'
    $jumlah = (int) ($_POST['jumlah'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($menuId <= 0 || $jumlah <= 0 || !in_array($tipe, ['masuk', 'keluar', 'penyesuaian'])) {
        setFlash('error', 'Data perubahan stok tidak valid.', 'danger');
        redirect('admin/stok/index.php');
    }

    // Ambil data menu saat ini
    $stmt = $pdo->prepare("SELECT id, nama_menu, stok, min_stok FROM menus WHERE id = :id FOR UPDATE");
    $pdo->beginTransaction();

    try {
        $stmt->execute(['id' => $menuId]);
        $menu = $stmt->fetch();

        if (!$menu) {
            throw new Exception('Menu tidak ditemukan.');
        }

        $stokSebelum = (int) $menu['stok'];
        $stokSesudah = $stokSebelum;

        if ($tipe === 'masuk') {
            $stokSesudah = $stokSebelum + $jumlah;
            if (empty($keterangan)) $keterangan = 'Penerimaan Stok / Belanja Bahan';
        } elseif ($tipe === 'keluar') {
            if ($jumlah > $stokSebelum) {
                throw new Exception("Jumlah stok keluar ({$jumlah}) melebihi stok yang tersedia saat ini ({$stokSebelum}). Stok tidak boleh negatif.");
            }
            $stokSesudah = $stokSebelum - $jumlah;
            if (empty($keterangan)) $keterangan = 'Pengurangan Stok / Bahan Rusak / Expired';
        } elseif ($tipe === 'penyesuaian') {
            // Jumlah di sini diartikan sebagai stok aktual hasil opname
            $stokSesudah = $jumlah;
            if (empty($keterangan)) $keterangan = 'Hasil Stock Opname Fisik';
        }

        // Tentukan status menu otomatis (tersedia / habis)
        $statusBaru = $stokSesudah > 0 ? 'tersedia' : 'habis';

        // Update Stok di Tabel menus
        $updateMenu = $pdo->prepare("UPDATE menus SET stok = :stok, status = :st WHERE id = :id");
        $updateMenu->execute([
            'stok' => $stokSesudah,
            'st'   => $statusBaru,
            'id'   => $menuId
        ]);

        // Catat ke stock_movements
        $insertLog = $pdo->prepare("INSERT INTO stock_movements (menu_id, tipe, jumlah, stok_sebelum, stok_sesudah, keterangan, user_id) 
                                    VALUES (:mid, :tipe, :qty, :before, :after, :ket, :uid)");
        $insertLog->execute([
            'mid'    => $menuId,
            'tipe'   => $tipe,
            'qty'    => $tipe === 'penyesuaian' ? abs($stokSesudah - $stokSebelum) : $jumlah,
            'before' => $stokSebelum,
            'after'  => $stokSesudah,
            'ket'    => $keterangan,
            'uid'    => getAuthUser()['id'] ?? null
        ]);

        $pdo->commit();
        setFlash('success', "Stok untuk menu '{$menu['nama_menu']}' berhasil diperbarui dari {$stokSebelum} menjadi {$stokSesudah}.");
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', $e->getMessage(), 'danger');
    }

    redirect('admin/stok/index.php');
}

// Ambil Kategori untuk dropdown filter
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'aktif' ORDER BY urutan ASC")->fetchAll();

// Query Menu & Stok
$where = ["m.status != 'nonaktif'"];
$params = [];

if (!empty($search)) {
    $where[] = "(m.nama_menu LIKE :search OR m.kode_menu LIKE :search)";
    $params['search'] = "%$search%";
}

if ($categoryId > 0) {
    $where[] = "m.category_id = :cat_id";
    $params['cat_id'] = $categoryId;
}

if ($statusFilter === 'aman') {
    $where[] = "m.stok > m.min_stok";
} elseif ($statusFilter === 'menipis') {
    $where[] = "m.stok <= m.min_stok AND m.stok > 0";
} elseif ($statusFilter === 'habis') {
    $where[] = "m.stok = 0";
}

$whereClause = implode(" AND ", $where);
$query = "SELECT m.*, c.nama_kategori 
          FROM menus m 
          LEFT JOIN categories c ON m.category_id = c.id 
          WHERE $whereClause 
          ORDER BY (m.stok <= m.min_stok) DESC, m.stok ASC, m.nama_menu ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$stokList = $stmt->fetchAll();

// Summary Stats
$statAman = $pdo->query("SELECT COUNT(id) FROM menus WHERE stok > min_stok AND status != 'nonaktif'")->fetchColumn();
$statMenipis = $pdo->query("SELECT COUNT(id) FROM menus WHERE stok <= min_stok AND stok > 0 AND status != 'nonaktif'")->fetchColumn();
$statHabis = $pdo->query("SELECT COUNT(id) FROM menus WHERE stok = 0 AND status != 'nonaktif'")->fetchColumn();

$pageTitle = 'Manajemen Stok Menu';
$activeMenu = 'stok';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';
?>

<!-- Stock Summary Cards -->
<div class="grid-stats" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-olive">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Stok Aman</div>
            <div class="stat-value" style="color: var(--status-success);"><?= $statAman ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">item</span></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-gold">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Stok Menipis</div>
            <div class="stat-value" style="color: var(--status-warning);"><?= $statMenipis ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">item</span></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle" style="background-color: var(--status-danger-bg); color: var(--status-danger);">
            <i class="fa-solid fa-ban"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Stok Habis (0)</div>
            <div class="stat-value" style="color: var(--status-danger);"><?= $statHabis ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">item</span></div>
        </div>
    </div>
</div>

<!-- Filter & Actions Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <form method="GET" action="<?= BASE_URL ?>/admin/stok/index.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; flex: 1;">
        <div style="min-width: 220px; flex: 1;">
            <input type="text" name="search" class="form-control" placeholder="Cari nama atau kode menu..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <select name="category_id" class="form-control" style="width: 170px;" onchange="this.form.submit()">
            <option value="0">Semua Kategori</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nama_kategori']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status_stok" class="form-control" style="width: 160px;" onchange="this.form.submit()">
            <option value="">Semua Kondisi</option>
            <option value="aman" <?= $statusFilter === 'aman' ? 'selected' : '' ?>>🟢 Stok Aman</option>
            <option value="menipis" <?= $statusFilter === 'menipis' ? 'selected' : '' ?>>🟡 Stok Menipis</option>
            <option value="habis" <?= $statusFilter === 'habis' ? 'selected' : '' ?>>🔴 Stok Habis</option>
        </select>

        <button type="submit" class="btn btn-secondary">
            <i class="fa-solid fa-magnifying-glass"></i> Filter
        </button>

        <?php if (!empty($search) || $categoryId > 0 || !empty($statusFilter)): ?>
            <a href="<?= BASE_URL ?>/admin/stok/index.php" class="btn btn-outline" title="Reset Filter">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        <?php endif; ?>
    </form>

    <a href="<?= BASE_URL ?>/admin/stok/riwayat.php" class="btn btn-outline">
        <i class="fa-solid fa-clock-rotate-left"></i> Riwayat Mutasi Stok
    </a>
</div>

<!-- Stock Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-boxes-stacked" style="color: var(--primary); margin-right: 8px;"></i>Status Stok Real-Time</h3>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">Total: <?= count($stokList) ?> Item</span>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Menu</th>
                    <th>Kategori</th>
                    <th>Stok Saat Ini</th>
                    <th>Batas Minimum</th>
                    <th>Status Stok</th>
                    <th style="width: 220px; text-align: center;">Kelola Stok</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stokList)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 36px;">
                            Tidak ada data stok yang sesuai dengan filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stokList as $item): ?>
                        <?php
                        $stokVal = (int) $item['stok'];
                        $minVal = (int) $item['min_stok'];

                        if ($stokVal === 0) {
                            $badgeStatus = '<span class="badge" style="background: var(--status-danger-bg); color: var(--status-danger);"><i class="fa-solid fa-circle" style="font-size: 0.5rem; margin-right: 4px;"></i> HABIS</span>';
                            $rowColor = 'color: var(--status-danger);';
                        } elseif ($stokVal <= $minVal) {
                            $badgeStatus = '<span class="badge" style="background: var(--status-warning-bg); color: var(--status-warning);"><i class="fa-solid fa-circle" style="font-size: 0.5rem; margin-right: 4px;"></i> MENIPIS</span>';
                            $rowColor = 'color: var(--status-warning);';
                        } else {
                            $badgeStatus = '<span class="badge badge-olive"><i class="fa-solid fa-circle" style="font-size: 0.5rem; margin-right: 4px;"></i> AMAN</span>';
                            $rowColor = 'color: var(--text-primary);';
                        }
                        ?>
                        <tr>
                            <td>
                                <strong style="font-size: 0.95rem; color: var(--text-primary);"><?= htmlspecialchars($item['nama_menu']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <code><?= htmlspecialchars($item['kode_menu']) ?></code>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--bg-surface-soft); color: var(--text-primary);">
                                    <?= htmlspecialchars($item['nama_kategori'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <strong style="font-size: 1.15rem; <?= $rowColor ?>"><?= $stokVal ?></strong>
                                <span style="font-size: 0.8rem; color: var(--text-secondary);">porsi/unit</span>
                            </td>
                            <td style="color: var(--text-secondary);">
                                <?= $minVal ?> unit
                            </td>
                            <td>
                                <?= $badgeStatus ?>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 0.8rem; color: var(--status-success); border-color: #C8E6C9;" 
                                            onclick='openStockModal(<?= json_encode($item) ?>, "masuk")' title="Tambah Stok Masuk">
                                        <i class="fa-solid fa-plus"></i> Masuk
                                    </button>
                                    <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 0.8rem; color: var(--status-danger); border-color: #FECACA;" 
                                            onclick='openStockModal(<?= json_encode($item) ?>, "keluar")' title="Kurangi Stok / Bahan Rusak">
                                        <i class="fa-solid fa-minus"></i> Keluar
                                    </button>
                                    <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 0.8rem;" 
                                            onclick='openStockModal(<?= json_encode($item) ?>, "penyesuaian")' title="Opname / Penyesuaian">
                                        <i class="fa-solid fa-sliders"></i> Opname
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Aksi Stok -->
<div id="modalStock" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 480px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalStockTitle" style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">Kelola Stok Menu</h3>
            <button type="button" onclick="closeStockModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="formStock" method="POST" action="<?= BASE_URL ?>/admin/stok/index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="menu_id" id="stockMenuId" value="">
            <input type="hidden" name="tipe" id="stockTipe" value="masuk">

            <div style="background-color: var(--bg-surface-soft); padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 18px;">
                <div style="font-size: 0.8rem; color: var(--text-secondary);">Nama Menu:</div>
                <div id="stockMenuName" style="font-weight: 800; font-size: 1rem; color: var(--text-primary);"></div>
                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;">
                    Stok Saat Ini: <strong id="stockCurrentVal" style="color: var(--primary);"></strong> unit
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" id="stockJumlahLabel" for="jumlah">Jumlah Stok Masuk</label>
                <input type="number" id="jumlah" name="jumlah" class="form-control" value="1" min="1" required>
                <div id="stockHelpText" style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 4px;"></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="keterangan">Keterangan / Alasan Perubahan</label>
                <input type="text" id="keterangan" name="keterangan" class="form-control" placeholder="Contoh: Belanja bahan dapur, Barang rusak, dll">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeStockModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnStockSubmit">
                    <i class="fa-solid fa-check"></i> Konfirmasi Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openStockModal(item, tipe) {
    document.getElementById('stockMenuId').value = item.id;
    document.getElementById('stockTipe').value = tipe;
    document.getElementById('stockMenuName').textContent = item.nama_menu;
    document.getElementById('stockCurrentVal').textContent = item.stok;
    document.getElementById('jumlah').value = '1';
    document.getElementById('keterangan').value = '';

    const titleEl = document.getElementById('modalStockTitle');
    const labelEl = document.getElementById('stockJumlahLabel');
    const helpEl = document.getElementById('stockHelpText');
    const inputJumlah = document.getElementById('jumlah');

    if (tipe === 'masuk') {
        titleEl.textContent = 'Tambah Stok Masuk (Restock)';
        labelEl.textContent = 'Jumlah Stok Tambahan';
        helpEl.textContent = 'Jumlah ini akan DITAMBAHKAN ke saldo stok saat ini.';
        inputJumlah.min = 1;
        inputJumlah.value = '10';
    } else if (tipe === 'keluar') {
        titleEl.textContent = 'Kurangi Stok (Barang Rusak / Expired)';
        labelEl.textContent = 'Jumlah Stok Dikeluarkan';
        helpEl.textContent = 'Maksimal pengurangan adalah ' + item.stok + ' unit.';
        inputJumlah.min = 1;
        inputJumlah.max = item.stok;
        inputJumlah.value = '1';
    } else if (tipe === 'penyesuaian') {
        titleEl.textContent = 'Penyesuaian Stok (Stock Opname)';
        labelEl.textContent = 'Stok Fisik Aktual Sebenarnya';
        helpEl.textContent = 'Saldo stok menu akan langsung DIUBAH menjadi angka ini.';
        inputJumlah.min = 0;
        inputJumlah.removeAttribute('max');
        inputJumlah.value = item.stok;
    }

    document.getElementById('modalStock').style.display = 'flex';
}

function closeStockModal() {
    document.getElementById('modalStock').style.display = 'none';
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
