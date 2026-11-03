<?php
/**
 * Pengaturan Restoran & Sistem - DAPUR TYAS by TECNO GARAGE
 * Comprehensive settings manager: Branding, Hotspot LOCAL_BASE_URL, Contact, Hours, & Receipt Footer
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$pdo = db();

// Handle Simpan Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($csrfToken)) {
        setFlash('error', 'Token keamanan tidak valid.', 'danger');
        redirect('admin/pengaturan/index.php');
    }

    $namaRestoran   = trim($_POST['nama_restoran'] ?? 'DAPUR TYAS');
    $tagline        = trim($_POST['tagline'] ?? 'MASAKAN RUMAHAN YANG LEZAT');
    $subBrand       = trim($_POST['sub_brand'] ?? 'by TECNO GARAGE');
    $localBaseUrl   = trim($_POST['local_base_url'] ?? BASE_URL);
    if (empty($localBaseUrl)) {
        $localBaseUrl = BASE_URL;
    }
    $alamat         = trim($_POST['alamat'] ?? '');
    $telepon        = trim($_POST['telepon'] ?? '');
    $whatsapp       = trim($_POST['whatsapp'] ?? '');
    $instagram      = trim($_POST['instagram'] ?? '');
    $jamOperasional = trim($_POST['jam_operasional'] ?? '');
    $tentangResto   = trim($_POST['tentang_restoran'] ?? '');
    $footerStruk    = trim($_POST['footer_struk'] ?? '');
    $pajakPersen    = (float) ($_POST['pajak_persen'] ?? 0);
    $modeQris       = in_array($_POST['mode_pembayaran_qris'] ?? '', ['simulasi', 'live'], true) ? $_POST['mode_pembayaran_qris'] : 'simulasi';
    $rekeningBank   = trim($_POST['rekening_bank'] ?? 'BCA');
    $rekeningNomor  = trim($_POST['rekening_nomor'] ?? '');
    $rekeningAn     = trim($_POST['rekening_an'] ?? 'DAPUR TYAS');

    // Normalisasi local_base_url
    $localBaseUrl = rtrim($localBaseUrl, '/');

    $settingsToUpdate = [
        'nama_restoran'        => $namaRestoran,
        'tagline'              => $tagline,
        'sub_brand'            => $subBrand,
        'local_base_url'       => $localBaseUrl,
        'alamat'               => $alamat,
        'telepon'              => $telepon,
        'whatsapp'             => $whatsapp,
        'instagram'            => $instagram,
        'jam_operasional'      => $jamOperasional,
        'tentang_restoran'     => $tentangResto,
        'footer_struk'         => $footerStruk,
        'pajak_persen'         => (string) $pajakPersen,
        'mode_pembayaran_qris' => $modeQris,
        'rekening_bank'        => $rekeningBank,
        'rekening_nomor'       => $rekeningNomor,
        'rekening_an'          => $rekeningAn
    ];

    // Handle Upload Logo Baru jika diunggah
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['logo_file']['tmp_name'];
        $fileName = $_FILES['logo_file']['name'];
        $fileSize = $_FILES['logo_file']['size'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['png', 'jpg', 'jpeg', 'webp'];
        $allowedMimes = ['image/png', 'image/jpeg', 'image/webp'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $fileTmp);
        finfo_close($finfo);

        if (in_array($fileExt, $allowedExts, true) && in_array($mime, $allowedMimes, true)) {
            if ($fileSize <= 3 * 1024 * 1024) {
                $destPath = ROOT_PATH . '/assets/images/logo.png';
                if (move_uploaded_file($fileTmp, $destPath)) {
                    $settingsToUpdate['logo_restoran'] = 'logo.png';
                } else {
                    setFlash('warning', 'Gagal memindahkan file logo baru, namun pengaturan teks tetap disimpan.', 'warning');
                }
            } else {
                setFlash('error', 'Ukuran file logo maksimal 3MB.', 'danger');
            }
        } else {
            setFlash('error', 'Format logo harus berupa gambar PNG, JPG, atau WEBP.', 'danger');
        }
    }

    try {
        $stmtSave = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) 
                                   VALUES (:key, :val) 
                                   ON DUPLICATE KEY UPDATE setting_value = :val_update");
        
        foreach ($settingsToUpdate as $key => $val) {
            $stmtSave->execute([
                'key'        => $key,
                'val'        => (string)$val,
                'val_update' => (string)$val
            ]);
        }

        setFlash('success', 'Pengaturan restoran, URL Hotspot lokal, dan profil berhasil diperbarui.');
    } catch (PDOException $e) {
        error_log("Save Settings Error: " . $e->getMessage());
        setFlash('error', 'Gagal menyimpan pengaturan: ' . $e->getMessage(), 'danger');
    }

    redirect('admin/pengaturan/index.php');
}

// Ambil Current Settings
$namaRestoranCurrent   = getSetting('nama_restoran', 'DAPUR TYAS');
$taglineCurrent        = getSetting('tagline', 'MASAKAN RUMAHAN YANG LEZAT');
$subBrandCurrent       = getSetting('sub_brand', 'by TECNO GARAGE');
$localBaseUrlCurrent   = getSetting('local_base_url', '');
if (empty($localBaseUrlCurrent)) {
    $localBaseUrlCurrent = BASE_URL;
}
$alamatCurrent         = getSetting('alamat', 'Jl. Kuliner Nusantara No. 88, Medan');
$teleponCurrent        = getSetting('telepon', '081234567890');
$whatsappCurrent       = getSetting('whatsapp', '081234567890');
$instagramCurrent      = getSetting('instagram', '@dapurtyas.id');
$jamOperasionalCurrent = getSetting('jam_operasional', 'Setiap Hari: 10.00 - 22.00 WIB');
$tentangRestoCurrent   = getSetting('tentang_restoran', 'Dapur Tyas menyajikan aneka hidangan masakan rumahan autentik Indonesia dengan bumbu rempah pilihan, cita rasa lezat, dan suasana hangat yang nyaman.');
$footerStrukCurrent    = getSetting('footer_struk', "Terima kasih atas kunjungan Anda di Dapur Tyas!\nFollow Instagram kami: @dapurtyas.id");
$pajakCurrent          = getSetting('pajak_persen', '0');
$modeQrisCurrent       = getSetting('mode_pembayaran_qris', 'simulasi');
$rekeningBankCurrent   = getSetting('rekening_bank', 'BCA');
$rekeningNomorCurrent  = getSetting('rekening_nomor', '');
$rekeningAnCurrent     = getSetting('rekening_an', 'DAPUR TYAS');

$detectedIps = getDetectedLocalIps();

$pageTitle = 'Pengaturan Restoran';
$activeMenu = 'pengaturan';
require_once dirname(__DIR__, 2) . '/includes/admin_header.php';
?>

<div class="card" style="margin-bottom: 24px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin: 0 0 4px 0;">
                <i class="fa-solid fa-store" style="color: var(--primary); margin-right: 8px;"></i>
                Pengaturan Restoran & Sistem URL
            </h2>
            <div style="font-size: 0.88rem; color: var(--text-secondary);">
                Konfigurasikan informasi restoran, domain/URL akses QR pelanggan, kontak, jam buka, dan footer struk.
            </div>
        </div>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/admin/pengaturan/index.php" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div style="display: grid; grid-template-columns: 1fr 1.35fr; gap: 24px; align-items: flex-start;">
        <!-- Card 1: Domain / URL Akses & Logo -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Domain & Base URL Configuration -->
            <div class="card" style="border: 2px solid var(--primary);">
                <div class="card-header" style="background: #FAF7F2; margin: -20px -20px 18px -20px; padding: 14px 20px; border-radius: 14px 14px 0 0;">
                    <h3 class="card-title" style="color: var(--primary); font-size: 1rem;">
                        <i class="fa-solid fa-globe" style="margin-right: 8px;"></i>URL Domain & Akses QR Pelanggan
                    </h3>
                </div>

                <div class="form-group">
                    <label class="form-label" for="local_base_url">URL Basis Pelanggan / QR Code Meja</label>
                    <input type="text" id="local_base_url" name="local_base_url" class="form-control" 
                           value="<?= htmlspecialchars($localBaseUrlCurrent) ?>" required style="font-family: monospace; font-weight: 700;">
                    <div style="font-size: 0.76rem; color: var(--text-secondary); margin-top: 6px;">
                        Semua QR code meja dan tautan pelanggan dibuat menggunakan URL ini. Jika web sudah di-hosting, gunakan URL domain hosting Anda.
                    </div>
                </div>

                <!-- One-click Domain Sync & Detected IPs -->
                <div style="background: var(--bg-surface-soft); padding: 10px 12px; border-radius: var(--radius-sm); border: 1px dashed var(--border-color); font-size: 0.8rem;">
                    <div style="font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">Tombol Cepat URL:</div>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-primary btn-sm" style="padding: 4px 10px; font-size: 0.75rem;"
                                onclick="document.getElementById('local_base_url').value = '<?= htmlspecialchars(BASE_URL) ?>'">
                            <i class="fa-solid fa-rotate"></i> Pakai Domain Saat Ini (<?= htmlspecialchars(BASE_URL) ?>)
                        </button>
                        <?php foreach ($detectedIps as $ip): ?>
                            <?php $suggestedUrl = "http://{$ip}/dapur_tyas"; ?>
                            <button type="button" class="btn btn-outline btn-sm" style="padding: 3px 8px; font-size: 0.75rem; background: #FFFFFF;"
                                    onclick="document.getElementById('local_base_url').value = '<?= $suggestedUrl ?>'">
                                <?= htmlspecialchars($suggestedUrl) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Logo & Visual Identity -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-image" style="color: var(--primary); margin-right: 8px;"></i>Logo & Identitas Visual</h3>
                </div>
                
                <div style="text-align: center; padding: 10px 0 16px 0;">
                    <div style="background: var(--bg-surface-soft); border-radius: var(--radius-lg); padding: 16px; border: 1px solid var(--border-color); display: inline-block; max-width: 100%;">
                        <img src="<?= BASE_URL ?>/promt/tambah_logo.png?v=<?= time() ?>" alt="Logo Dapur Tyas"
                             style="width: 120px; height: 120px; object-fit: cover; border-radius: 50%; border: 3px solid var(--border-color); display: block; margin: 0 auto;">
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 8px;">
                        Logo resmi ditampilkan di header pelanggan, kasir, admin, struk belanja, dan QR meja.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="logo_file">Unggah Logo Baru (Opsional)</label>
                    <input type="file" id="logo_file" name="logo_file" class="form-control" accept="image/png, image/jpeg, image/webp">
                    <div style="font-size: 0.75rem; color: var(--text-light); margin-top: 4px;">Format: PNG/JPG/WEBP, Maks. 3MB.</div>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 16px 0;">

                <div class="form-group">
                    <label class="form-label" for="mode_pembayaran_qris">Mode Pembayaran QRIS</label>
                    <select id="mode_pembayaran_qris" name="mode_pembayaran_qris" class="form-control">
                        <option value="simulasi" <?= $modeQrisCurrent === 'simulasi' ? 'selected' : '' ?>>Simulasi POS (Kasir Langsung Konfirmasi)</option>
                        <option value="live" <?= $modeQrisCurrent === 'live' ? 'selected' : '' ?>>Live Gateway / Statis</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="pajak_persen">Pajak Restoran / PB1 (%)</label>
                    <input type="number" id="pajak_persen" name="pajak_persen" class="form-control" step="0.1" min="0" max="100" value="<?= htmlspecialchars($pajakCurrent) ?>">
                </div>
            </div>

            <!-- Pembayaran Transfer Bank -->
            <div class="card" style="border: 2px solid var(--status-info);">
                <div class="card-header" style="background: #F7FAFD; margin: -20px -20px 18px -20px; padding: 14px 20px; border-radius: 14px 14px 0 0;">
                    <h3 class="card-title" style="color: var(--status-info); font-size: 1rem;">
                        <i class="fa-solid fa-building-columns" style="margin-right: 8px;"></i>Pembayaran Transfer Bank
                    </h3>
                </div>

                <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 14px;">
                    Nomor rekening ini ditampilkan di modal pembayaran kasir ketika pelanggan memilih metode <strong>Transfer</strong>.
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label class="form-label" for="rekening_bank">Nama Bank</label>
                        <input type="text" id="rekening_bank" name="rekening_bank" class="form-control"
                               value="<?= htmlspecialchars($rekeningBankCurrent) ?>" placeholder="BCA">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="rekening_nomor">Nomor Rekening</label>
                        <input type="text" id="rekening_nomor" name="rekening_nomor" class="form-control" style="font-family: monospace;"
                               value="<?= htmlspecialchars($rekeningNomorCurrent) ?>" placeholder="1234567890">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="rekening_an">Atas Nama (Nama Pemilik Rekening)</label>
                    <input type="text" id="rekening_an" name="rekening_an" class="form-control"
                           value="<?= htmlspecialchars($rekeningAnCurrent) ?>" placeholder="DAPUR TYAS">
                </div>
            </div>
        </div>

        <!-- Card 2: Informasi Restoran & Profil -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-store" style="color: var(--primary); margin-right: 8px;"></i>Profil & Kontak Restoran</h3>
            </div>

            <div class="form-group">
                <label class="form-label" for="nama_restoran">Nama Identitas Utama Restoran</label>
                <input type="text" id="nama_restoran" name="nama_restoran" class="form-control" 
                       value="<?= htmlspecialchars($namaRestoranCurrent) ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label" for="tagline">Tagline Resmi</label>
                    <input type="text" id="tagline" name="tagline" class="form-control" 
                           value="<?= htmlspecialchars($taglineCurrent) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="sub_brand">Sub-Brand Pengembang</label>
                    <input type="text" id="sub_brand" name="sub_brand" class="form-control" 
                           value="<?= htmlspecialchars($subBrandCurrent) ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label" for="telepon">Nomor Telepon</label>
                    <input type="text" id="telepon" name="telepon" class="form-control" 
                           value="<?= htmlspecialchars($teleponCurrent) ?>" placeholder="081234567890">
                </div>

                <div class="form-group">
                    <label class="form-label" for="whatsapp">Nomor WhatsApp</label>
                    <input type="text" id="whatsapp" name="whatsapp" class="form-control" 
                           value="<?= htmlspecialchars($whatsappCurrent) ?>" placeholder="081234567890">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label class="form-label" for="instagram">Akun Instagram</label>
                    <input type="text" id="instagram" name="instagram" class="form-control" 
                           value="<?= htmlspecialchars($instagramCurrent) ?>" placeholder="@dapurtyas.id">
                </div>

                <div class="form-group">
                    <label class="form-label" for="jam_operasional">Jam Operasional</label>
                    <input type="text" id="jam_operasional" name="jam_operasional" class="form-control" 
                           value="<?= htmlspecialchars($jamOperasionalCurrent) ?>" placeholder="Setiap Hari: 10.00 - 22.00 WIB">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="alamat">Alamat Lengkap Restoran</label>
                <textarea id="alamat" name="alamat" class="form-control" rows="2" 
                          placeholder="Alamat outlet atau cabang..."><?= htmlspecialchars($alamatCurrent) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="tentang_restoran">Tentang Dapur Tyas (Deskripsi Ringkas)</label>
                <textarea id="tentang_restoran" name="tentang_restoran" class="form-control" rows="2" 
                          placeholder="Cerita dan keunggulan cita rasa masakan rumah..."><?= htmlspecialchars($tentangRestoCurrent) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="footer_struk">Pesan Footer Struk Belanja</label>
                <textarea id="footer_struk" name="footer_struk" class="form-control" rows="2" 
                          placeholder="Pesan terima kasih, akun sosmed, WiFi..."><?= htmlspecialchars($footerStrukCurrent) ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 0.95rem;">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Seluruh Pengaturan
                </button>
            </div>
        </div>
    </div>
</form>

<?php require_once dirname(__DIR__, 2) . '/includes/admin_footer.php'; ?>
