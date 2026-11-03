<?php
/**
 * Manajemen Kasir & Staff - DAPUR TYAS by TECNO GARAGE
 * Phase 26: List Kasir, Tambah, Edit, Nonaktifkan, dan Reset Password
 */

$pageTitle = 'Kelola Kasir & Staff';
$activeMenu = 'kasir';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';

$pdo = db();
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$where = ["role = 'kasir'"];
$params = [];

if (!empty($search)) {
    $where[] = "(nama LIKE :search OR username LIKE :search)";
    $params['search'] = "%$search%";
}

if (!empty($statusFilter) && in_array($statusFilter, ['aktif', 'nonaktif'], true)) {
    $where[] = "status = :status";
    $params['status'] = $statusFilter;
}

$whereClause = implode(" AND ", $where);
$stmt = $pdo->prepare("SELECT u.*, 
                              (SELECT COUNT(id) FROM payments WHERE user_id = u.id) AS total_transaksi_kasir,
                              (SELECT MAX(paid_at) FROM payments WHERE user_id = u.id) AS transaksi_terakhir
                       FROM users u 
                       WHERE $whereClause 
                       ORDER BY u.created_at DESC");
$stmt->execute($params);
$kasirList = $stmt->fetchAll();
?>

<!-- Header Action Bar -->
<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin: 0 0 4px 0;">
                <i class="fa-solid fa-users-gear" style="color: var(--primary); margin-right: 8px;"></i>
                Manajemen Kasir Restoran
            </h2>
            <div style="font-size: 0.88rem; color: var(--text-secondary);">
                Kelola akun operator POS kasir, kredensial login, dan hak akses.
            </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <button type="button" class="btn btn-primary" onclick="openTambahKasirModal()">
                <i class="fa-solid fa-user-plus"></i> Tambah Kasir Baru
            </button>
        </div>
    </div>

    <!-- Search & Status Filter -->
    <div style="margin-top: 18px; border-top: 1px solid var(--border-color); padding-top: 16px;">
        <form method="GET" action="<?= BASE_URL ?>/admin/kasir/index.php" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <div style="flex: 1; min-width: 220px; position: relative;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-light); font-size: 0.9rem;"></i>
                <input type="text" name="search" class="form-control" style="padding-left: 38px;" placeholder="Cari nama atau username kasir..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div style="width: 170px;">
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="aktif" <?= $statusFilter === 'aktif' ? 'selected' : '' ?>>Hanya Aktif</option>
                    <option value="nonaktif" <?= $statusFilter === 'nonaktif' ? 'selected' : '' ?>>Hanya Nonaktif</option>
                </select>
            </div>

            <button type="submit" class="btn btn-secondary">Cari</button>
            <?php if (!empty($search) || !empty($statusFilter)): ?>
                <a href="<?= BASE_URL ?>/admin/kasir/index.php" class="btn btn-outline">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Kasir Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-id-card-clip" style="color: var(--primary); margin-right: 8px;"></i>Daftar Akun Kasir Terdaftar</h3>
        <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= count($kasirList) ?> Akun</span>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 50px;">Avatar</th>
                    <th>Nama Kasir</th>
                    <th>Username</th>
                    <th>Status Akun</th>
                    <th style="text-align: center;">Total Transaksi</th>
                    <th>Terakhir Aktif</th>
                    <th>Terdaftar Sejak</th>
                    <th style="width: 140px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kasirList)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-secondary); padding: 36px;">
                            <i class="fa-solid fa-user-slash" style="font-size: 2rem; margin-bottom: 8px; color: var(--text-light); display: block;"></i>
                            Tidak ada data kasir yang sesuai dengan pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kasirList as $kasir): ?>
                        <tr>
                            <td>
                                <div style="width: 38px; height: 38px; border-radius: 50%; background: <?= $kasir['status'] === 'aktif' ? 'var(--primary-light)' : '#F3F4F6' ?>; color: <?= $kasir['status'] === 'aktif' ? 'var(--primary)' : '#9CA3AF' ?>; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">
                                    <?= strtoupper(substr($kasir['nama'], 0, 1)) ?>
                                </div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($kasir['nama']) ?></strong>
                            </td>
                            <td>
                                <code style="background: var(--bg-surface-soft); padding: 3px 8px; border-radius: 4px; font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($kasir['username']) ?></code>
                            </td>
                            <td>
                                <span class="badge badge-<?= $kasir['status'] === 'aktif' ? 'success' : 'danger' ?>">
                                    <i class="fa-solid <?= $kasir['status'] === 'aktif' ? 'fa-check' : 'fa-ban' ?>" style="margin-right: 4px;"></i>
                                    <?= ucfirst(htmlspecialchars($kasir['status'])) ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" style="background: var(--bg-surface-soft); color: var(--text-primary); font-weight: 700;">
                                    <?= number_format($kasir['total_transaksi_kasir'], 0, ',', '.') ?> trx
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.82rem; color: var(--text-secondary);">
                                    <?= !empty($kasir['transaksi_terakhir']) ? formatTanggalIndo($kasir['transaksi_terakhir'], true) : 'Belum pernah transaksi' ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.82rem; color: var(--text-secondary);">
                                    <?= date('d/m/Y', strtotime($kasir['created_at'])) ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 6px; justify-content: center;">
                                    <!-- Edit Kasir -->
                                    <button type="button" class="btn btn-outline" style="padding: 6px 9px; font-size: 0.8rem;" 
                                            onclick="openEditKasirModal(<?= htmlspecialchars(json_encode($kasir)) ?>)" title="Edit Kasir">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <!-- Reset Password -->
                                    <button type="button" class="btn btn-outline" style="padding: 6px 9px; font-size: 0.8rem; color: var(--accent-gold); border-color: #FDE68A;" 
                                            onclick="openResetPasswordModal(<?= $kasir['id'] ?>, '<?= htmlspecialchars($kasir['nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($kasir['username'], ENT_QUOTES) ?>')" title="Reset Password">
                                        <i class="fa-solid fa-key"></i>
                                    </button>

                                    <!-- Toggle Status (Aktif/Nonaktif) -->
                                    <a href="<?= BASE_URL ?>/admin/kasir/toggle_status.php?id=<?= $kasir['id'] ?>" 
                                       class="btn btn-outline" style="padding: 6px 9px; font-size: 0.8rem; color: <?= $kasir['status'] === 'aktif' ? 'var(--status-danger)' : 'var(--status-success)' ?>; border-color: <?= $kasir['status'] === 'aktif' ? '#FECACA' : '#A7F3D0' ?>;" 
                                       onclick="return confirm('Apakah Anda yakin ingin <?= $kasir['status'] === 'aktif' ? 'MENONAKTIFKAN' : 'MENGAKTIFKAN' ?> akun kasir <?= htmlspecialchars($kasir['nama'], ENT_QUOTES) ?>?');" 
                                       title="<?= $kasir['status'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="fa-solid <?= $kasir['status'] === 'aktif' ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
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

<!-- Modal 1: Tambah Kasir Baru -->
<div id="modalTambahKasir" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 460px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">Tambah Kasir Baru</h3>
            <button type="button" onclick="closeTambahKasirModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/admin/kasir/tambah.php">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="add_nama">Nama Lengkap Kasir</label>
                <input type="text" id="add_nama" name="nama" class="form-control" placeholder="Contoh: Rina Anggraini" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="add_username">Username (Untuk Login)</label>
                <input type="text" id="add_username" name="username" class="form-control" placeholder="Contoh: kasir_rina (huruf/angka)" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="add_password">Kata Sandi Awal</label>
                <input type="password" id="add_password" name="password" class="form-control" placeholder="Minimal 6 karakter" minlength="6" required>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeTambahKasirModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Simpan Kasir
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Data Kasir -->
<div id="modalEditKasir" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 460px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">Edit Data Kasir</h3>
            <button type="button" onclick="closeEditKasirModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/admin/kasir/edit.php">
            <?= csrf_field() ?>
            <input type="hidden" id="edit_id" name="id">

            <div class="form-group">
                <label class="form-label" for="edit_nama">Nama Lengkap Kasir</label>
                <input type="text" id="edit_nama" name="nama" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="edit_username">Username</label>
                <input type="text" id="edit_username" name="username" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="edit_status">Status Akun</label>
                <select id="edit_status" name="status" class="form-control">
                    <option value="aktif">Aktif (Bisa Login)</option>
                    <option value="nonaktif">Nonaktif (Diblokir)</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditKasirModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Perbarui Data
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Reset Password Kasir -->
<div id="modalResetPassword" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 460px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">Reset Password Kasir</h3>
            <button type="button" onclick="closeResetPasswordModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/admin/kasir/reset_password.php">
            <?= csrf_field() ?>
            <input type="hidden" id="reset_id" name="id">

            <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 16px;">
                Ubah kata sandi untuk akun <strong id="reset_nama_label" style="color: var(--text-primary);"></strong> (<code id="reset_username_label"></code>):
            </p>

            <div class="form-group">
                <label class="form-label" for="reset_new_password">Password Baru</label>
                <input type="password" id="reset_new_password" name="new_password" class="form-control" placeholder="Minimal 6 karakter" minlength="6" required>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Batal</button>
                <button type="submit" class="btn btn-primary" style="background-color: var(--status-warning); border-color: var(--status-warning);">
                    <i class="fa-solid fa-key"></i> Simpan Password Baru
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openTambahKasirModal() {
    document.getElementById('modalTambahKasir').style.display = 'flex';
}
function closeTambahKasirModal() {
    document.getElementById('modalTambahKasir').style.display = 'none';
}

function openEditKasirModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_nama').value = data.nama;
    document.getElementById('edit_username').value = data.username;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('modalEditKasir').style.display = 'flex';
}
function closeEditKasirModal() {
    document.getElementById('modalEditKasir').style.display = 'none';
}

function openResetPasswordModal(id, nama, username) {
    document.getElementById('reset_id').value = id;
    document.getElementById('reset_nama_label').textContent = nama;
    document.getElementById('reset_username_label').textContent = username;
    document.getElementById('reset_new_password').value = '';
    document.getElementById('modalResetPassword').style.display = 'flex';
}
function closeResetPasswordModal() {
    document.getElementById('modalResetPassword').style.display = 'none';
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
