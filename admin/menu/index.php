<?php
/**
 * Manajemen Menu Restoran - DAPUR TYAS by TECNO GARAGE
 * Phase 08: CRUD Menu, Upload Foto Aman, Filter Kategori, Margin Keuntungan, & Safe Delete
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$pdo = db();
$search = trim($_GET['search'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

// Ambil semua kategori aktif untuk filter dropdown & modal form
$kategoriStmt = $pdo->query("SELECT * FROM categories WHERE status = 'aktif' ORDER BY urutan ASC");
$categories = $kategoriStmt->fetchAll();

// Handle Form Tambah Menu via POST (atau modal submit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrfToken)) {
        setFlash('error', 'Token keamanan tidak valid.', 'danger');
        redirect('admin/menu/index.php');
    }

    $action = $_POST['action'];
    $catId = (int) ($_POST['category_id'] ?? 0);
    $namaMenu = trim($_POST['nama_menu'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $hargaModal = (float) ($_POST['harga_modal'] ?? 0);
    $hargaJual = (float) ($_POST['harga_jual'] ?? 0);
    $stok = (int) ($_POST['stok'] ?? 0);
    $minStok = (int) ($_POST['min_stok'] ?? 5);
    $status = in_array($_POST['status'] ?? '', ['tersedia', 'habis', 'nonaktif']) ? $_POST['status'] : 'tersedia';

    if (empty($namaMenu) || $catId <= 0 || $hargaJual <= 0) {
        setFlash('error', 'Nama menu, kategori, dan harga jual wajib diisi secara valid.', 'danger');
        redirect('admin/menu/index.php');
    }

    // Auto-generate Kode Menu jika kosong (e.g. MKN-XXXX)
    $kodePrefix = 'MNU';
    $catRow = $pdo->query("SELECT slug FROM categories WHERE id = $catId")->fetch();
    if ($catRow) {
        if ($catRow['slug'] === 'makanan') $kodePrefix = 'MKN';
        elseif ($catRow['slug'] === 'minuman') $kodePrefix = 'MNM';
        elseif ($catRow['slug'] === 'snack') $kodePrefix = 'SNK';
    }

    // Handle Upload Foto Aman
    $fotoName = 'default_menu.png';
    $uploadDir = dirname(__DIR__, 2) . '/assets/images/menus/';

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['foto']['tmp_name'];
        $fileSize = $_FILES['foto']['size'];
        $fileInfo = pathinfo($_FILES['foto']['name']);
        $ext = strtolower($fileInfo['extension'] ?? '');

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fileTmp);
        finfo_close($finfo);

        if (!in_array($ext, $allowedExts) || !in_array($mime, $allowedMimes)) {
            setFlash('error', 'Format foto tidak valid. Hanya format JPG, JPEG, PNG, dan WEBP yang diperbolehkan.', 'danger');
            redirect('admin/menu/index.php');
        } elseif ($fileSize > 2 * 1024 * 1024) { // Max 2MB
            setFlash('error', 'Ukuran foto terlalu besar. Maksimum 2 MB.', 'danger');
            redirect('admin/menu/index.php');
        } else {
            $newFileName = 'menu_' . uniqid() . '_' . time() . '.' . $ext;
            if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                $fotoName = $newFileName;
            }
        }
    }

    if ($action === 'tambah') {
        $kodeMenu = $kodePrefix . '-' . rand(100, 999);
        $checkKode = $pdo->prepare("SELECT COUNT(id) FROM menus WHERE kode_menu = :k");
        $checkKode->execute(['k' => $kodeMenu]);
        if ($checkKode->fetchColumn() > 0) {
            $kodeMenu = $kodePrefix . '-' . rand(1000, 9999);
        }

        $stmt = $pdo->prepare("INSERT INTO menus (category_id, kode_menu, nama_menu, deskripsi, foto, harga_modal, harga_jual, stok, min_stok, status) 
                               VALUES (:cat, :kode, :nama, :deskripsi, :foto, :modal, :jual, :stok, :min_stok, :status)");
        $stmt->execute([
            'cat'       => $catId,
            'kode'      => $kodeMenu,
            'nama'      => $namaMenu,
            'deskripsi' => $deskripsi,
            'foto'      => $fotoName,
            'modal'     => $hargaModal,
            'jual'      => $hargaJual,
            'stok'      => $stok,
            'min_stok'  => $minStok,
            'status'    => $status
        ]);

        $newMenuId = (int) $pdo->lastInsertId();
        // Log Stock Initial Movement
        if ($stok > 0) {
            $stmtStock = $pdo->prepare("INSERT INTO stock_movements (menu_id, tipe, jumlah, stok_sebelum, stok_sesudah, keterangan, user_id) 
                                       VALUES (:mid, 'masuk', :qty, 0, :qty, 'Stok Awal Pembuatan Menu', :uid)");
            $stmtStock->execute([
                'mid' => $newMenuId,
                'qty' => $stok,
                'uid' => getAuthUser()['id'] ?? null
            ]);
        }

        setFlash('success', "Menu '{$namaMenu}' berhasil ditambahkan ke katalog.");
        redirect('admin/menu/index.php');

    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $currentMenu = $pdo->prepare("SELECT * FROM menus WHERE id = :id");
        $currentMenu->execute(['id' => $id]);
        $oldData = $currentMenu->fetch();

        if ($oldData) {
            $finalFoto = $oldData['foto'];
            if ($fotoName !== 'default_menu.png') {
                $finalFoto = $fotoName;
            }

            $stmt = $pdo->prepare("UPDATE menus SET category_id = :cat, nama_menu = :nama, deskripsi = :deskripsi, foto = :foto, 
                                   harga_modal = :modal, harga_jual = :jual, stok = :stok, min_stok = :min_stok, status = :status 
                                   WHERE id = :id");
            $stmt->execute([
                'cat'       => $catId,
                'nama'      => $namaMenu,
                'deskripsi' => $deskripsi,
                'foto'      => $finalFoto,
                'modal'     => $hargaModal,
                'jual'      => $hargaJual,
                'stok'      => $stok,
                'min_stok'  => $minStok,
                'status'    => $status,
                'id'        => $id
            ]);

            // Jika ada perubahan stok, catat penyesuaian stok
            if ($oldData['stok'] != $stok) {
                $diff = $stok - (int)$oldData['stok'];
                $tipe = $diff > 0 ? 'masuk' : 'keluar';
                $stmtStock = $pdo->prepare("INSERT INTO stock_movements (menu_id, tipe, jumlah, stok_sebelum, stok_sesudah, keterangan, user_id) 
                                           VALUES (:mid, :tipe, :qty, :before, :after, 'Penyesuaian Manual di Form Edit Menu', :uid)");
                $stmtStock->execute([
                    'mid'    => $id,
                    'tipe'   => $tipe,
                    'qty'    => abs($diff),
                    'before' => $oldData['stok'],
                    'after'  => $stok,
                    'uid'    => getAuthUser()['id'] ?? null
                ]);
            }

            setFlash('success', "Menu '{$namaMenu}' berhasil diperbarui.");
        }
        redirect('admin/menu/index.php');
    }
}

// Query Menu dengan Filter & Search
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(m.nama_menu LIKE :search OR m.kode_menu LIKE :search OR m.deskripsi LIKE :search)";
    $params['search'] = "%$search%";
}

if ($categoryId > 0) {
    $where[] = "m.category_id = :cat_id";
    $params['cat_id'] = $categoryId;
}

if (!empty($statusFilter)) {
    $where[] = "m.status = :status_f";
    $params['status_f'] = $statusFilter;
}

$whereClause = implode(" AND ", $where);
$query = "SELECT m.*, c.nama_kategori 
          FROM menus m 
          LEFT JOIN categories c ON m.category_id = c.id 
          WHERE $whereClause 
          ORDER BY m.category_id ASC, m.nama_menu ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$menuList = $stmt->fetchAll();

$pageTitle = 'Daftar Menu Restoran';
$activeMenu = 'menu';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';
?>

<!-- Filter & Actions Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <form method="GET" action="<?= BASE_URL ?>/admin/menu/index.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; flex: 1;">
        <!-- Search Input -->
        <div style="min-width: 220px; flex: 1;">
            <input type="text" name="search" class="form-control" placeholder="Cari nama atau kode menu..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <!-- Category Dropdown -->
        <select name="category_id" class="form-control" style="width: 170px;" onchange="this.form.submit()">
            <option value="0">Semua Kategori</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nama_kategori']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Status Filter -->
        <select name="status" class="form-control" style="width: 150px;" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="tersedia" <?= $statusFilter === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
            <option value="habis" <?= $statusFilter === 'habis' ? 'selected' : '' ?>>Habis (0)</option>
            <option value="nonaktif" <?= $statusFilter === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
        </select>

        <button type="submit" class="btn btn-secondary">
            <i class="fa-solid fa-magnifying-glass"></i> Filter
        </button>

        <?php if (!empty($search) || $categoryId > 0 || !empty($statusFilter)): ?>
            <a href="<?= BASE_URL ?>/admin/menu/index.php" class="btn btn-outline" title="Reset Filter">
                <i class="fa-solid fa-rotate-left"></i> Reset
            </a>
        <?php endif; ?>
    </form>

    <button type="button" class="btn btn-primary" onclick="openAddModal()">
        <i class="fa-solid fa-plus"></i> Tambah Menu Baru
    </button>
</div>

<!-- Menu Catalog Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-bowl-food" style="color: var(--primary); margin-right: 8px;"></i>Katalog Menu Restoran</h3>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">Total: <?= count($menuList) ?> Menu</span>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 70px;">Foto</th>
                    <th>Nama Menu & Kode</th>
                    <th>Kategori</th>
                    <th>Harga Modal</th>
                    <th>Harga Jual</th>
                    <th>Estimasi Margin</th>
                    <th>Stok / Min</th>
                    <th>Status</th>
                    <th style="width: 140px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($menuList)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-secondary); padding: 36px;">
                            Tidak ditemukan menu yang sesuai dengan filter pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($menuList as $m): ?>
                        <?php
                        $margin = (float)$m['harga_jual'] - (float)$m['harga_modal'];
                        $isLowStock = (int)$m['stok'] <= (int)$m['min_stok'];
                        ?>
                        <tr>
                            <td>
                                <div style="width: 48px; height: 48px; border-radius: var(--radius-md); overflow: hidden; background-color: var(--bg-surface-soft); display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color);">
                                    <?php if (!empty($m['foto']) && file_exists(dirname(__DIR__, 2) . '/assets/images/menus/' . $m['foto'])): ?>
                                        <img src="<?= BASE_URL ?>/assets/images/menus/<?= htmlspecialchars($m['foto']) ?>" alt="<?= htmlspecialchars($m['nama_menu']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fa-solid fa-utensils" style="color: var(--primary); font-size: 1.2rem;"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong style="font-size: 0.95rem; color: var(--text-primary);"><?= htmlspecialchars($m['nama_menu']) ?></strong>
                                <div style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <code><?= htmlspecialchars($m['kode_menu']) ?></code>
                                </div>
                                <?php if (!empty($m['deskripsi'])): ?>
                                    <div style="font-size: 0.75rem; color: var(--text-light); max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($m['deskripsi']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--bg-surface-soft); color: var(--text-primary); font-size: 0.8rem;">
                                    <?= htmlspecialchars($m['nama_kategori'] ?? 'Tanpa Kategori') ?>
                                </span>
                            </td>
                            <td style="color: var(--text-secondary); font-size: 0.9rem;">
                                <?= formatRupiah($m['harga_modal']) ?>
                            </td>
                            <td>
                                <strong style="color: var(--text-primary); font-size: 0.95rem;"><?= formatRupiah($m['harga_jual']) ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $margin >= 0 ? 'badge-olive' : 'badge-terracotta' ?>" style="font-size: 0.8rem;">
                                    +<?= formatRupiah($margin) ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: <?= $isLowStock ? 'var(--status-danger)' : 'var(--text-primary)' ?>;">
                                    <?= (int)$m['stok'] ?> <span style="font-weight: normal; font-size: 0.75rem; color: var(--text-secondary);">/ min <?= $m['min_stok'] ?></span>
                                </div>
                                <?php if ($isLowStock): ?>
                                    <span style="font-size: 0.7rem; color: var(--status-danger); font-weight: 600;"><i class="fa-solid fa-circle-exclamation"></i> Menipis</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/menu/toggle_status.php?id=<?= $m['id'] ?>" title="Klik untuk ubah status" style="text-decoration: none;">
                                    <?php if ($m['status'] === 'tersedia'): ?>
                                        <span class="badge badge-olive" style="cursor: pointer;">Tersedia</span>
                                    <?php elseif ($m['status'] === 'habis'): ?>
                                        <span class="badge badge-gold" style="cursor: pointer;">Habis</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--status-danger-bg); color: var(--status-danger); cursor: pointer;">Nonaktif</span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 0.85rem;" 
                                            onclick='openEditModal(<?= json_encode($m) ?>)' title="Edit Menu">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="<?= BASE_URL ?>/admin/menu/hapus.php?id=<?= $m['id'] ?>" 
                                       class="btn btn-outline" style="padding: 6px 10px; font-size: 0.85rem; color: var(--status-danger); border-color: #FECACA;" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus menu ini? Jika sudah pernah ada riwayat pesanan, sistem akan menonaktifkannya untuk keamanan data.');"
                                       title="Hapus Menu">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Tambah / Edit Menu -->
<div id="modalMenu" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px; overflow-y: auto;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 600px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalTitle" style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">Tambah Menu Baru</h3>
            <button type="button" onclick="closeModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="formMenu" method="POST" action="<?= BASE_URL ?>/admin/menu/index.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="menuId" value="">

            <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="nama_menu">Nama Menu</label>
                    <input type="text" id="nama_menu" name="nama_menu" class="form-control" placeholder="Contoh: Ayam Bakar Spesial" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="category_id">Kategori</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="deskripsi">Deskripsi Singkat Menu</label>
                <textarea id="deskripsi" name="deskripsi" class="form-control" rows="2" placeholder="Komposisi bumbu, rasa, lalapan, atau info menu..."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="harga_modal">Harga Modal (HPP - Rp)</label>
                    <input type="number" id="harga_modal" name="harga_modal" class="form-control" value="0" min="0" step="500" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="harga_jual">Harga Jual (Rp)</label>
                    <input type="number" id="harga_jual" name="harga_jual" class="form-control" value="0" min="0" step="500" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="stok">Stok Saat Ini</label>
                    <input type="number" id="stok" name="stok" class="form-control" value="20" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="min_stok">Batas Minimum</label>
                    <input type="number" id="min_stok" name="min_stok" class="form-control" value="5" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="tersedia">Tersedia</option>
                        <option value="habis">Habis</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="foto">Foto Menu (JPG, PNG, WEBP &bull; Max 2MB)</label>
                <input type="file" id="foto" name="foto" class="form-control" accept="image/png, image/jpeg, image/webp">
                <div id="previewFotoContainer" style="margin-top: 10px; display: none;">
                    <img id="previewFoto" src="" style="width: 70px; height: 70px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Simpan Menu
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Menu Baru';
    document.getElementById('formAction').value = 'tambah';
    document.getElementById('menuId').value = '';
    document.getElementById('nama_menu').value = '';
    document.getElementById('category_id').value = '';
    document.getElementById('deskripsi').value = '';
    document.getElementById('harga_modal').value = '0';
    document.getElementById('harga_jual').value = '0';
    document.getElementById('stok').value = '20';
    document.getElementById('min_stok').value = '5';
    document.getElementById('status').value = 'tersedia';
    document.getElementById('previewFotoContainer').style.display = 'none';
    document.getElementById('modalMenu').style.display = 'flex';
}

function openEditModal(m) {
    document.getElementById('modalTitle').textContent = 'Edit Menu: ' + m.nama_menu;
    document.getElementById('formAction').value = 'edit';
    document.getElementById('menuId').value = m.id;
    document.getElementById('nama_menu').value = m.nama_menu;
    document.getElementById('category_id').value = m.category_id;
    document.getElementById('deskripsi').value = m.deskripsi || '';
    document.getElementById('harga_modal').value = parseFloat(m.harga_modal);
    document.getElementById('harga_jual').value = parseFloat(m.harga_jual);
    document.getElementById('stok').value = m.stok;
    document.getElementById('min_stok').value = m.min_stok;
    document.getElementById('status').value = m.status;
    
    if (m.foto) {
        document.getElementById('previewFoto').src = '<?= BASE_URL ?>/assets/images/menus/' + m.foto;
        document.getElementById('previewFotoContainer').style.display = 'block';
    } else {
        document.getElementById('previewFotoContainer').style.display = 'none';
    }

    document.getElementById('modalMenu').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modalMenu').style.display = 'none';
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
