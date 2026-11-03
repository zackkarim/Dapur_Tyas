<?php
/**
 * Manajemen Kategori Menu - DAPUR TYAS by TECNO GARAGE
 * Phase 07: CRUD Kategori, Safe Delete, Search, Count Menu per Kategori
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$pdo = db();
$search = trim($_GET['search'] ?? '');

// Handle Tambah / Edit Kategori via POST di halaman yang sama jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrfToken)) {
        setFlash('error', 'Token keamanan tidak valid.', 'danger');
        redirect('admin/kategori/index.php');
    }

    $action = $_POST['action'] ?? '';
    $namaKategori = trim($_POST['nama_kategori'] ?? '');
    $urutan = (int) ($_POST['urutan'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif']) ? $_POST['status'] : 'aktif';
    $icon = trim($_POST['icon'] ?? 'fa-solid fa-utensils');

    if (empty($namaKategori)) {
        setFlash('error', 'Nama kategori wajib diisi.', 'danger');
        redirect('admin/kategori/index.php');
    }

    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $namaKategori), '-'));

    if ($action === 'tambah') {
        // Cek duplikasi slug
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM categories WHERE slug = :slug");
        $stmt->execute(['slug' => $slug]);
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . rand(10, 99);
        }

        $stmt = $pdo->prepare("INSERT INTO categories (nama_kategori, slug, icon, urutan, status) VALUES (:nama, :slug, :icon, :urutan, :status)");
        $stmt->execute([
            'nama'   => $namaKategori,
            'slug'   => $slug,
            'icon'   => $icon,
            'urutan' => $urutan,
            'status' => $status
        ]);
        setFlash('success', "Kategori '{$namaKategori}' berhasil ditambahkan.");
        redirect('admin/kategori/index.php');

    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        
        // Cek duplikasi slug untuk kategori lain
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM categories WHERE slug = :slug AND id != :id");
        $stmt->execute(['slug' => $slug, 'id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . rand(10, 99);
        }

        $stmt = $pdo->prepare("UPDATE categories SET nama_kategori = :nama, slug = :slug, icon = :icon, urutan = :urutan, status = :status WHERE id = :id");
        $stmt->execute([
            'nama'   => $namaKategori,
            'slug'   => $slug,
            'icon'   => $icon,
            'urutan' => $urutan,
            'status' => $status,
            'id'     => $id
        ]);
        setFlash('success', "Kategori '{$namaKategori}' berhasil diperbarui.");
        redirect('admin/kategori/index.php');
    }
}

// Query Kategori dengan Jumlah Menu per Kategori
$query = "SELECT c.*, COUNT(m.id) AS total_menu 
          FROM categories c 
          LEFT JOIN menus m ON c.id = m.category_id AND m.status != 'nonaktif' ";
$params = [];

if (!empty($search)) {
    $query .= " WHERE c.nama_kategori LIKE :search OR c.slug LIKE :search ";
    $params['search'] = "%$search%";
}

$query .= " GROUP BY c.id ORDER BY c.urutan ASC, c.id ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$kategoriList = $stmt->fetchAll();

$pageTitle = 'Kategori Menu';
$activeMenu = 'kategori';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';
?>

<!-- Header Actions -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div style="display: flex; gap: 12px; align-items: center; flex: 1; max-width: 400px;">
        <form method="GET" action="<?= BASE_URL ?>/admin/kategori/index.php" style="display: flex; gap: 8px; width: 100%;">
            <input type="text" name="search" class="form-control" placeholder="Cari nama kategori..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <?php if (!empty($search)): ?>
                <a href="<?= BASE_URL ?>/admin/kategori/index.php" class="btn btn-outline" title="Reset Pencarian">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <button type="button" class="btn btn-primary" onclick="openAddModal()">
        <i class="fa-solid fa-plus"></i> Tambah Kategori
    </button>
</div>

<!-- Category Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-tags" style="color: var(--primary); margin-right: 8px;"></i>Daftar Kategori Menu</h3>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">Total: <?= count($kategoriList) ?> Kategori</span>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 70px;">Urutan</th>
                    <th>Icon</th>
                    <th>Nama Kategori</th>
                    <th>Slug URL</th>
                    <th>Jumlah Menu</th>
                    <th>Status</th>
                    <th style="width: 140px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kategoriList)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 32px;">
                            <?= !empty($search) ? 'Tidak ada kategori yang cocok dengan kata kunci "' . htmlspecialchars($search) . '".' : 'Belum ada kategori.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kategoriList as $cat): ?>
                        <tr>
                            <td>
                                <span class="badge" style="background: var(--bg-surface-soft); color: var(--text-primary); font-size: 0.85rem;">
                                    #<?= $cat['urutan'] ?>
                                </span>
                            </td>
                            <td>
                                <div style="width: 36px; height: 36px; background: var(--primary-light); color: var(--primary); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                                    <i class="<?= htmlspecialchars($cat['icon'] ?: 'fa-solid fa-utensils') ?>"></i>
                                </div>
                            </td>
                            <td>
                                <strong style="font-size: 0.95rem; color: var(--text-primary);"><?= htmlspecialchars($cat['nama_kategori']) ?></strong>
                            </td>
                            <td>
                                <code style="color: var(--text-secondary); font-size: 0.85rem;"><?= htmlspecialchars($cat['slug']) ?></code>
                            </td>
                            <td>
                                <span class="badge badge-olive" style="font-size: 0.8rem;">
                                    <?= (int)$cat['total_menu'] ?> Menu
                                </span>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/kategori/toggle_status.php?id=<?= $cat['id'] ?>" 
                                   title="Klik untuk ubah status" style="text-decoration: none;">
                                    <?php if ($cat['status'] === 'aktif'): ?>
                                        <span class="badge badge-olive" style="cursor: pointer;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: var(--status-danger-bg); color: var(--status-danger); cursor: pointer;"><i class="fa-solid fa-ban" style="margin-right: 4px;"></i> Nonaktif</span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <button type="button" class="btn btn-outline" style="padding: 6px 10px; font-size: 0.85rem;" 
                                            onclick='openEditModal(<?= json_encode($cat) ?>)' title="Edit Kategori">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="<?= BASE_URL ?>/admin/kategori/hapus.php?id=<?= $cat['id'] ?>" 
                                       class="btn btn-outline" style="padding: 6px 10px; font-size: 0.85rem; color: var(--status-danger); border-color: #FECACA;" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini? Jika sudah digunakan menu, sistem akan menjaga integritas data.');"
                                       title="Hapus Kategori">
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

<!-- Modal Form Tambah / Edit Kategori -->
<div id="modalKategori" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 480px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalTitle" style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">Tambah Kategori</h3>
            <button type="button" onclick="closeModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="formKategori" method="POST" action="<?= BASE_URL ?>/admin/kategori/index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="kategoriId" value="">

            <div class="form-group">
                <label class="form-label" for="nama_kategori">Nama Kategori</label>
                <input type="text" id="nama_kategori" name="nama_kategori" class="form-control" placeholder="Contoh: Makanan Utama, Dessert" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="icon">Icon FontAwesome</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="icon" name="icon" class="form-control" value="fa-solid fa-utensils" placeholder="Contoh: fa-solid fa-bowl-food" required>
                    <select class="form-control" style="width: 140px;" onchange="document.getElementById('icon').value = this.value;">
                        <option value="">Pilih Preset</option>
                        <option value="fa-solid fa-bowl-food">🍛 Makanan</option>
                        <option value="fa-solid fa-mug-hot">🥤 Minuman</option>
                        <option value="fa-solid fa-cookie-bite">🍿 Snack</option>
                        <option value="fa-solid fa-cake-candles">🍰 Dessert</option>
                        <option value="fa-solid fa-fire">🔥 Spesial</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="urutan">Nomor Urut</label>
                    <input type="number" id="urutan" name="urutan" class="form-control" value="1" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitModal">
                    <i class="fa-solid fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Kategori Baru';
    document.getElementById('formAction').value = 'tambah';
    document.getElementById('kategoriId').value = '';
    document.getElementById('nama_kategori').value = '';
    document.getElementById('icon').value = 'fa-solid fa-utensils';
    document.getElementById('urutan').value = '1';
    document.getElementById('status').value = 'aktif';
    document.getElementById('modalKategori').style.display = 'flex';
}

function openEditModal(cat) {
    document.getElementById('modalTitle').textContent = 'Edit Kategori: ' + cat.nama_kategori;
    document.getElementById('formAction').value = 'edit';
    document.getElementById('kategoriId').value = cat.id;
    document.getElementById('nama_kategori').value = cat.nama_kategori;
    document.getElementById('icon').value = cat.icon || 'fa-solid fa-utensils';
    document.getElementById('urutan').value = cat.urutan;
    document.getElementById('status').value = cat.status;
    document.getElementById('modalKategori').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modalKategori').style.display = 'none';
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
