<?php
/**
 * Manajemen Meja Restoran & QR Code - DAPUR TYAS by TECNO GARAGE
 * Live Table Layout, Interactive QR Generator with LOCAL_BASE_URL, Print & Download QR
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$pdo = db();
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

// Handle Tambah / Edit Meja via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrfToken)) {
        setFlash('error', 'Token keamanan tidak valid.', 'danger');
        redirect('admin/meja/index.php');
    }

    $action = $_POST['action'] ?? '';
    $nomorMeja = trim($_POST['nomor_meja'] ?? '');
    $kapasitas = (int) ($_POST['kapasitas'] ?? 4);
    $status = in_array($_POST['status'] ?? '', ['kosong', 'terisi', 'reservasi', 'nonaktif']) ? $_POST['status'] : 'kosong';

    if (empty($nomorMeja) || $kapasitas <= 0) {
        setFlash('error', 'Nomor meja dan kapasitas wajib diisi secara valid.', 'danger');
        redirect('admin/meja/index.php');
    }

    if ($action === 'tambah') {
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM tables WHERE nomor_meja = :no");
        $stmt->execute(['no' => $nomorMeja]);
        if ($stmt->fetchColumn() > 0) {
            setFlash('error', "Nomor/Nama meja '{$nomorMeja}' sudah ada. Gunakan nama yang berbeda.", 'danger');
            redirect('admin/meja/index.php');
        }

        $qrToken = 'QR-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $nomorMeja)) . '-' . bin2hex(random_bytes(3));

        $stmt = $pdo->prepare("INSERT INTO tables (nomor_meja, kapasitas, status, qr_identifier) VALUES (:no, :kap, :st, :qr)");
        $stmt->execute([
            'no'  => $nomorMeja,
            'kap' => $kapasitas,
            'st'  => $status,
            'qr'  => $qrToken
        ]);

        setFlash('success', "Meja '{$nomorMeja}' berhasil ditambahkan.");
        redirect('admin/meja/index.php');

    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(id) FROM tables WHERE nomor_meja = :no AND id != :id");
        $stmt->execute(['no' => $nomorMeja, 'id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            setFlash('error', "Nomor meja '{$nomorMeja}' sudah digunakan oleh meja lain.", 'danger');
            redirect('admin/meja/index.php');
        }

        $stmt = $pdo->prepare("UPDATE tables SET nomor_meja = :no, kapasitas = :kap, status = :st WHERE id = :id");
        $stmt->execute([
            'no'  => $nomorMeja,
            'kap' => $kapasitas,
            'st'  => $status,
            'id'  => $id
        ]);

        setFlash('success', "Informasi meja '{$nomorMeja}' berhasil diperbarui.");
        redirect('admin/meja/index.php');
    }
}

// Query Meja dengan Live Status Pesanan
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "t.nomor_meja LIKE :search";
    $params['search'] = "%$search%";
}

if (!empty($statusFilter)) {
    $where[] = "t.status = :status_f";
    $params['status_f'] = $statusFilter;
}

$whereClause = implode(" AND ", $where);
$query = "SELECT t.*, 
          (SELECT o.status_pesanan FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS live_order_status,
          (SELECT o.nomor_order FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS live_order_no,
          (SELECT o.customer_name FROM orders o WHERE o.table_id = t.id AND o.status_pesanan NOT IN ('selesai', 'dibatalkan') ORDER BY o.id DESC LIMIT 1) AS live_customer_name
          FROM tables t 
          WHERE $whereClause 
          ORDER BY t.nomor_meja ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tablesList = $stmt->fetchAll();

// Summary Stats
$totalMeja = $pdo->query("SELECT COUNT(id) FROM tables WHERE status != 'nonaktif'")->fetchColumn();
$mejaKosong = $pdo->query("SELECT COUNT(id) FROM tables WHERE status = 'kosong'")->fetchColumn();
$mejaTerisi = $pdo->query("SELECT COUNT(id) FROM tables WHERE status = 'terisi'")->fetchColumn();
$mejaReservasi = $pdo->query("SELECT COUNT(id) FROM tables WHERE status = 'reservasi'")->fetchColumn();

$currentLocalBaseUrl = getLocalBaseUrl();

$pageTitle = 'Manajemen Meja';
$activeMenu = 'meja';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';
?>

<!-- QR Code JS Library -->
<script src="<?= BASE_URL ?>/assets/vendor/qrcodejs/qrcode.min.js"></script>

<!-- Server Local URL Notice Banner -->
<!-- Server / Domain URL Notice Banner -->
<?php
$isLiveDomain = !empty($_SERVER['HTTP_HOST']) && !preg_match('/^(localhost|127\.0\.0\.1|192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/i', $_SERVER['HTTP_HOST']);
?>
<div class="card" style="margin-bottom: 20px; background: #FAF7F2; border-left: 4px solid var(--primary); padding: 16px 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 2px;">
                <i class="fa-solid <?= $isLiveDomain ? 'fa-globe' : 'fa-wifi' ?>" style="color: var(--primary); margin-right: 6px;"></i> 
                <?= $isLiveDomain ? 'Domain Hosting & Akses Pelanggan Online Aktif' : 'Server & Akses QR Pemesanan Aktif' ?>
            </div>
            <div style="font-size: 0.82rem; color: var(--text-secondary);">
                Basis URL QR Pemesanan: <strong style="color: var(--primary); font-family: monospace; font-size: 0.9rem;"><?= htmlspecialchars(getCustomerGeneralUrl()) ?></strong>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/admin/pengaturan/index.php" class="btn btn-outline btn-sm" style="font-size: 0.8rem;">
            <i class="fa-solid fa-gear"></i> Ubah URL QR
        </a>
    </div>
</div>

<!-- Table Stats Widgets -->
<div class="grid-stats" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 20px;">
    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-terracotta">
            <i class="fa-solid fa-chair"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Total Meja Aktif</div>
            <div class="stat-value"><?= $totalMeja ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">meja</span></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-olive">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Tersedia (Kosong)</div>
            <div class="stat-value" style="color: var(--status-success);"><?= $mejaKosong ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">meja</span></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle" style="background-color: var(--primary-light); color: var(--primary);">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Digunakan (Terisi)</div>
            <div class="stat-value" style="color: var(--primary);"><?= $mejaTerisi ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">meja</span></div>
        </div>
    </div>

    <div class="stat-widget">
        <div class="stat-icon-circle stat-icon-gold">
            <i class="fa-solid fa-bookmark"></i>
        </div>
        <div class="stat-data">
            <div class="stat-label">Reservasi</div>
            <div class="stat-value" style="color: var(--accent-gold);"><?= $mejaReservasi ?> <span style="font-size: 0.8rem; font-weight: normal; color: var(--text-secondary);">meja</span></div>
        </div>
    </div>
</div>

<!-- Header Controls -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <form method="GET" action="<?= BASE_URL ?>/admin/meja/index.php" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; flex: 1;">
        <div style="min-width: 220px; flex: 1;">
            <input type="text" name="search" class="form-control" placeholder="Cari nama/nomor meja..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <select name="status" class="form-control" style="width: 170px;" onchange="this.form.submit()">
            <option value="">Semua Status Meja</option>
            <option value="kosong" <?= $statusFilter === 'kosong' ? 'selected' : '' ?>>🟢 Tersedia (Kosong)</option>
            <option value="terisi" <?= $statusFilter === 'terisi' ? 'selected' : '' ?>>🔴 Digunakan (Terisi)</option>
            <option value="reservasi" <?= $statusFilter === 'reservasi' ? 'selected' : '' ?>>🟡 Reservasi</option>
            <option value="nonaktif" <?= $statusFilter === 'nonaktif' ? 'selected' : '' ?>>⚪ Nonaktif</option>
        </select>

        <button type="submit" class="btn btn-secondary">
            <i class="fa-solid fa-magnifying-glass"></i> Filter
        </button>

        <?php if (!empty($search) || !empty($statusFilter)): ?>
            <a href="<?= BASE_URL ?>/admin/meja/index.php" class="btn btn-outline" title="Reset Filter">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        <?php endif; ?>
    </form>

    <div style="display: flex; gap: 10px;">
        <a href="<?= BASE_URL ?>/admin/meja/print_qr.php?mode=single" target="_blank" class="btn btn-secondary">
            <i class="fa-solid fa-print"></i> Cetak Standee 1 QR Meja
        </a>
        <button type="button" class="btn btn-primary" onclick="openAddModal()">
            <i class="fa-solid fa-plus"></i> Tambah Meja
        </button>
    </div>
</div>

<!-- ============ 1 QR CODE UTAMA PEMESANAN (UNTUK SEMUA MEJA) ============ -->
<div class="card" style="margin-bottom: 24px; border: 2px solid var(--primary); background: linear-gradient(135deg, #FFFFFF 0%, #FFFDF9 100%); position: relative; overflow: hidden; box-shadow: var(--shadow-md);">
    <div style="position: absolute; right: -25px; top: -25px; font-size: 8rem; color: rgba(200, 90, 50, 0.04); pointer-events: none;">
        <i class="fa-solid fa-qrcode"></i>
    </div>
    
    <div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap; position: relative; z-index: 1;">
        <!-- QR Code Preview Box -->
        <div style="text-align: center; background: #FFFFFF; padding: 14px; border-radius: var(--radius-lg); border: 2px solid #E6DFD3; box-shadow: var(--shadow-sm); flex-shrink: 0; margin: 0 auto;">
            <div id="mainUniversalQrCanvas" style="display: inline-block;"></div>
            <div style="margin-top: 8px; font-size: 0.76rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="fa-solid fa-qrcode"></i> 1 QR Untuk Semua Meja
            </div>
        </div>

        <!-- Info & Actions -->
        <div style="flex: 1; min-width: 280px;">
            <div style="display: inline-flex; align-items: center; gap: 6px; background: #FDF0EB; color: var(--primary); font-size: 0.78rem; font-weight: 800; padding: 4px 12px; border-radius: 999px; margin-bottom: 8px;">
                <i class="fa-solid fa-star"></i> FITUR 1 QR CODE TUNGGAL RESTORAN
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">
                1 QR Code Pemesanan Mandiri (Pelanggan Pilih Meja di HP)
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 12px;">
                Cukup cetak & letakkan <strong>1 QR Code ini</strong> di meja akrilik, kasir, atau dinding restoran. Ketika pelanggan melakukan scan, halaman pelanggan akan <strong>mewajibkan pelanggan memilih nomor meja</strong> tempat duduk mereka terlebih dahulu sebelum dapat memesan makanan & minuman.
            </p>

            <div style="background: var(--bg-surface-soft); padding: 8px 14px; border-radius: var(--radius-md); border: 1px solid var(--border-color); font-family: monospace; font-size: 0.82rem; color: var(--primary); word-break: break-all; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <span><i class="fa-solid fa-link"></i> <?= htmlspecialchars(getCustomerGeneralUrl()) ?></span>
                <button type="button" class="btn btn-outline btn-sm" onclick="copyUniversalUrl()" style="padding: 4px 10px; font-size: 0.75rem; white-space: nowrap;">
                    <i class="fa-solid fa-copy"></i> Salin Link
                </button>
            </div>

            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?= BASE_URL ?>/admin/meja/print_qr.php?mode=single" target="_blank" class="btn btn-primary" style="box-shadow: 0 4px 12px rgba(200, 90, 50, 0.25);">
                    <i class="fa-solid fa-print"></i> Cetak Standee 1 QR Meja
                </a>
                <button type="button" class="btn btn-secondary" onclick="openUniversalQrModal()">
                    <i class="fa-solid fa-expand"></i> Pratinjau & Unduh Gambar
                </button>
                <a href="<?= BASE_URL ?>/customer/" target="_blank" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Tes Buka Halaman Pelanggan
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Visual Interactive Table Cards Grid -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-chair" style="color: var(--primary); margin-right: 8px;"></i>Daftar &amp; Status Meja Restoran</h3>
        <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= count($tablesList) ?> Meja Terdaftar</span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px;">
        <?php foreach ($tablesList as $tb): ?>
            <?php
            $cardBg = 'var(--bg-surface)';
            $cardBorder = 'var(--border-color)';
            $statusLabel = 'Tersedia';
            $badgeStyle = 'badge-olive';

            if ($tb['status'] === 'terisi' || !empty($tb['live_order_status'])) {
                $cardBg = 'var(--primary-light)';
                $cardBorder = 'var(--primary)';
                $badgeStyle = 'badge-terracotta';
                if ($tb['live_order_status'] === 'menunggu') {
                    $statusLabel = 'Menunggu Pesanan';
                } elseif ($tb['live_order_status'] === 'dimasak') {
                    $statusLabel = 'Sedang Dibuat';
                } elseif ($tb['live_order_status'] === 'diantar') {
                    $statusLabel = 'Sedang Diantar';
                } else {
                    $statusLabel = 'Digunakan';
                }
            } elseif ($tb['status'] === 'reservasi') {
                $cardBg = 'var(--accent-gold-light)';
                $cardBorder = 'var(--accent-gold)';
                $badgeStyle = 'badge-gold';
                $statusLabel = 'Reservasi';
            } elseif ($tb['status'] === 'nonaktif') {
                $cardBg = '#F3F4F6';
                $cardBorder = '#D1D5DB';
                $badgeStyle = 'badge';
                $statusLabel = 'Nonaktif';
            }
            ?>

            <div style="background: <?= $cardBg ?>; border: 2px solid <?= $cardBorder ?>; border-radius: var(--radius-lg); padding: 20px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--shadow-sm);">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h4 style="font-size: 1.2rem; font-weight: 800; color: var(--text-primary); margin: 0;"><?= htmlspecialchars($tb['nomor_meja']) ?></h4>
                        <span class="badge <?= $badgeStyle ?>"><?= $statusLabel ?></span>
                    </div>

                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 10px;">
                        <i class="fa-solid fa-user-group" style="margin-right: 6px;"></i> Kapasitas: <strong><?= $tb['kapasitas'] ?> Orang</strong>
                    </div>

                    <?php if (!empty($tb['live_order_no'])): ?>
                        <div style="margin-bottom: 12px; padding: 10px 12px; background: rgba(255, 255, 255, 0.9); border-radius: var(--radius-sm); font-size: 0.8rem; border-left: 3px solid var(--primary);">
                            <div style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($tb['live_order_no']) ?></div>
                            <div style="color: var(--text-secondary);"><?= htmlspecialchars($tb['live_customer_name'] ?? 'Pelanggan') ?></div>
                        </div>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>/admin/meja/toggle_status.php?id=<?= $tb['id'] ?>" class="btn btn-secondary btn-block" style="padding: 8px 12px; font-size: 0.82rem; margin-top: 6px;">
                        <i class="fa-solid fa-arrows-rotate"></i> Ubah Status Meja
                    </a>
                </div>

                <div style="display: flex; gap: 6px; justify-content: flex-end; margin-top: 16px; border-top: 1px dashed rgba(0,0,0,0.1); padding-top: 12px;">
                    <button type="button" class="btn btn-outline" style="padding: 4px 10px; font-size: 0.78rem;" onclick='openEditModal(<?= json_encode($tb) ?>)' title="Edit Meja">
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                    </button>
                    <a href="<?= BASE_URL ?>/admin/meja/hapus.php?id=<?= $tb['id'] ?>" class="btn btn-outline" style="padding: 4px 10px; font-size: 0.78rem; color: var(--status-danger);" onclick="return confirm('Hapus meja ini?');" title="Hapus Meja">
                        <i class="fa-solid fa-trash-can"></i> Hapus
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal QR Code Preview, Copy URL, Download & Print -->
<div id="modalQrPreview" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1060; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 440px; padding: 28px; box-shadow: var(--shadow-lg); text-align: center; border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;" id="qrModalTitle">Barcode Utama Pemesanan</h3>
            <button type="button" onclick="closeQrModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div style="padding: 20px; background: #FAF7F2; border-radius: 16px; border: 2px solid var(--primary); margin-bottom: 16px;">
            <div style="font-weight: 800; font-size: 1.3rem; color: var(--text-primary); margin-bottom: 2px;">DAPUR TYAS</div>
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--primary); letter-spacing: 1px; margin-bottom: 14px;">MASAKAN RUMAHAN YANG LEZAT &bull; by TECNO GARAGE</div>
            
            <div id="modalQrCanvas" style="display: inline-block; background: #FFFFFF; padding: 12px; border-radius: 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); margin-bottom: 12px;"></div>

            <div id="qrModalSub" style="font-weight: 800; font-size: 1.2rem; color: var(--text-primary);"></div>
            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 4px;">Arahkan kamera HP untuk scan menu & order</div>
        </div>

        <!-- URL Display Box with Copy Button -->
        <div style="background: var(--bg-surface-soft); border-radius: var(--radius-md); padding: 10px 14px; margin-bottom: 18px; text-align: left; border: 1px solid var(--border-color);">
            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 2px;">Target URL Local Server:</div>
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                <span id="qrModalUrlText" style="font-family: monospace; font-size: 0.82rem; color: var(--primary); word-break: break-all; font-weight: 700;"></span>
                <button type="button" class="btn btn-outline btn-sm" onclick="copyQrUrl()" style="padding: 4px 8px; font-size: 0.75rem; white-space: nowrap;">
                    <i class="fa-solid fa-copy"></i> Salin
                </button>
            </div>
        </div>

        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary" onclick="downloadQrImage()">
                <i class="fa-solid fa-download"></i> Unduh PNG
            </button>
            <a id="btnCetakSingle" href="#" target="_blank" class="btn btn-primary">
                <i class="fa-solid fa-print"></i> Cetak Stiker
            </a>
        </div>
    </div>
</div>

<!-- Modal Form Tambah / Edit Meja -->
<div id="modalMeja" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-surface); border-radius: var(--radius-lg); width: 100%; max-width: 450px; padding: 28px; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalTitle" style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0;">Tambah Meja Baru</h3>
            <button type="button" onclick="closeModal()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-secondary); cursor: pointer;">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="formMeja" method="POST" action="<?= BASE_URL ?>/admin/meja/index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="formAction" value="tambah">
            <input type="hidden" name="id" id="mejaId" value="">

            <div class="form-group">
                <label class="form-label" for="nomor_meja">Nomor / Nama Meja</label>
                <input type="text" id="nomor_meja" name="nomor_meja" class="form-control" placeholder="Contoh: Meja 01, VIP 01" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="kapasitas">Kapasitas Tempat Duduk (Orang)</label>
                <input type="number" id="kapasitas" name="kapasitas" class="form-control" value="4" min="1" max="50" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Status Awal Meja</label>
                <select id="status" name="status" class="form-control">
                    <option value="kosong">🟢 Tersedia (Kosong)</option>
                    <option value="terisi">🔴 Digunakan (Terisi)</option>
                    <option value="reservasi">🟡 Reservasi</option>
                    <option value="nonaktif">⚪ Nonaktif</option>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Simpan Meja
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentActiveTable = null;
let currentActiveUrl = '';
const universalUrl = "<?= getCustomerGeneralUrl() ?>";

document.addEventListener('DOMContentLoaded', function() {
    const mainCanvas = document.getElementById('mainUniversalQrCanvas');
    if (mainCanvas) {
        new QRCode(mainCanvas, {
            text: universalUrl,
            width: 130,
            height: 130,
            colorDark: "#1F242D",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    }
});

function openUniversalQrModal() {
    currentActiveTable = { nomor_meja: 'Pelanggan (Semua Meja)', id: 0 };
    currentActiveUrl = universalUrl;

    document.getElementById('qrModalTitle').textContent = 'QR Code Tunggal: Pemesanan Meja';
    document.getElementById('qrModalSub').textContent = '1 QR Untuk Semua Meja (Pilih Meja di HP)';
    document.getElementById('qrModalUrlText').textContent = universalUrl;
    document.getElementById('btnCetakSingle').href = '<?= BASE_URL ?>/admin/meja/print_qr.php?mode=single';

    const qrContainer = document.getElementById('modalQrCanvas');
    qrContainer.innerHTML = '';
    new QRCode(qrContainer, {
        text: universalUrl,
        width: 180,
        height: 180,
        colorDark: "#1F242D",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    document.getElementById('modalQrPreview').style.display = 'flex';
}

function copyUniversalUrl() {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(universalUrl).then(() => {
            alert('Tautan 1 QR Pemesanan Mandiri berhasil disalin: ' + universalUrl);
        });
    } else {
        alert('Target URL: ' + universalUrl);
    }
}

const copyQrUrl = copyUniversalUrl;

function closeQrModal() {
    document.getElementById('modalQrPreview').style.display = 'none';
}

function downloadQrImage() {
    const img = document.querySelector('#modalQrCanvas img');
    if (img) {
        const link = document.createElement('a');
        link.download = 'QR_Pemesanan_DapurTyas.png';
        link.href = img.src;
        link.click();
    }
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Meja Baru';
    document.getElementById('formAction').value = 'tambah';
    document.getElementById('mejaId').value = '';
    document.getElementById('nomor_meja').value = '';
    document.getElementById('kapasitas').value = '4';
    document.getElementById('status').value = 'kosong';
    document.getElementById('modalMeja').style.display = 'flex';
}

function openEditModal(tb) {
    document.getElementById('modalTitle').textContent = 'Edit Meja: ' + tb.nomor_meja;
    document.getElementById('formAction').value = 'edit';
    document.getElementById('mejaId').value = tb.id;
    document.getElementById('nomor_meja').value = tb.nomor_meja;
    document.getElementById('kapasitas').value = tb.kapasitas;
    document.getElementById('status').value = tb.status;
    document.getElementById('modalMeja').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modalMeja').style.display = 'none';
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
