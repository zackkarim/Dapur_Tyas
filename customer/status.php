<?php
/**
 * Customer Live Order Tracking - DAPUR TYAS by TECNO GARAGE
 * Real-time 5-stage timeline, dynamic status banners, item summary, & payment status
 */

require_once dirname(__DIR__) . '/config/app.php';

$pdo = db();
$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    die("Akses tidak sah. Token pesanan tidak ditemukan.");
}

// Ambil Header Pesanan
$stmt = $pdo->prepare("SELECT o.*, t.nomor_meja 
                       FROM orders o 
                       LEFT JOIN tables t ON o.table_id = t.id 
                       WHERE o.customer_token = :token 
                       LIMIT 1");
$stmt->execute(['token' => $token]);
$order = $stmt->fetch();

if (!$order) {
    die("Pesanan tidak ditemukan atau tautan sudah kadaluarsa.");
}

// Ambil Detail Items
$stmtItems = $pdo->prepare("SELECT od.*, m.nama_menu, m.foto 
                            FROM order_details od 
                            JOIN menus m ON od.menu_id = m.id 
                            WHERE od.order_id = :oid 
                            ORDER BY od.id ASC");
$stmtItems->execute(['oid' => $order['id']]);
$items = $stmtItems->fetchAll();

$nomorMeja = $order['nomor_meja'] ?? 'Takeaway';
$restoName = getSetting('nama_restoran', 'DAPUR TYAS');
$restoSub  = getSetting('sub_brand', 'by TECNO GARAGE');
$restoTag  = getSetting('tagline', 'MASAKAN RUMAHAN YANG LEZAT');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Status Pesanan #<?= htmlspecialchars($order['nomor_order']) ?> &bull; <?= htmlspecialchars($restoName) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/promt/tambah_logo.png">
    
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pelanggan.css?v=<?= time() ?>">
    <style>
        .order-hero-card {
            background: linear-gradient(135deg, #1E2229 0%, #2D333F 100%);
            color: #FFFFFF;
            padding: 22px 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .hero-table-pill {
            background: rgba(200, 90, 50, 0.25);
            border: 1px solid var(--primary);
            color: #FFA07A;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 800;
        }

        .hero-order-no {
            font-size: 1.35rem;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: -0.5px;
        }

        .live-pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
            display: inline-block;
            margin-right: 6px;
            animation: pulseGlow 1.5s infinite;
        }

        /* Dynamic Status Banner */
        .status-dynamic-banner {
            padding: 16px;
            border-radius: var(--radius-md);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .status-banner-menunggu {
            background: #FEF3C7;
            border: 1.5px solid #F59E0B;
            color: #92400E;
        }

        .status-banner-diterima {
            background: #E8F5E9;
            border: 1.5px solid #4CAF50;
            color: #1B5E20;
        }

        .status-banner-dimasak {
            background: #FFF7ED;
            border: 1.5px solid #EA580C;
            color: #9A3412;
        }

        .status-banner-diantar {
            background: #FDF0EB;
            border: 2px solid var(--primary);
            color: var(--primary);
            animation: bounceSoft 2s infinite;
        }

        .status-banner-selesai {
            background: #ECFDF5;
            border: 1.5px solid #10B981;
            color: #065F46;
        }

        @keyframes bounceSoft {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
    </style>
</head>
<body>

<div class="customer-container">
    <!-- Header -->
    <header class="customer-header">
        <a href="<?= BASE_URL ?>/customer/<?= $order['table_id'] ? '?meja=' . urlencode(preg_replace('/^Meja\s*/i', '', $nomorMeja)) : '' ?>" class="customer-brand-link">
            <div class="customer-brand-logo-frame">
                <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="<?= htmlspecialchars($restoName) ?>">
            </div>
            <div class="brand-text-block">
                <div class="brand-main-title"><?= htmlspecialchars($restoName) ?></div>
                <div class="brand-tagline"><?= htmlspecialchars($restoTag) ?></div>
            </div>
        </a>

        <div style="font-size: 0.8rem; font-weight: 700; color: var(--accent-olive); display: flex; align-items: center;">
            <span class="live-pulse-dot"></span> Live Tracking
        </div>
    </header>

    <div style="padding: 16px; flex: 1;">
        <!-- Hero Card -->
        <div class="order-hero-card">
            <div class="hero-top">
                <div class="hero-table-pill">
                    <i class="fa-solid fa-chair" style="margin-right: 4px;"></i> <?= htmlspecialchars($nomorMeja) ?>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-light);">
                    <?= date('H:i', strtotime($order['created_at'])) ?> WIB
                </div>
            </div>
            <div class="hero-order-no">#<?= htmlspecialchars($order['nomor_order']) ?></div>
            <div style="font-size: 0.82rem; color: rgba(255,255,255,0.75); margin-top: 4px;">
                Pemesan: <strong><?= htmlspecialchars($order['customer_name'] ?? 'Pelanggan') ?></strong>
            </div>
        </div>

        <!-- Dynamic Live Message Banner -->
        <div id="dynamicStatusBanner" class="status-dynamic-banner status-banner-menunggu">
            <i class="fa-solid fa-clock" id="dynamicStatusIcon" style="font-size: 1.6rem;"></i>
            <div>
                <strong id="dynamicStatusHeadline" style="display: block; font-size: 0.95rem; margin-bottom: 2px;">Pesanan Baru Terkirim</strong>
                <span id="dynamicStatusSub" style="font-size: 0.82rem;">Menunggu konfirmasi oleh kasir & dapur.</span>
            </div>
        </div>

        <!-- Stepper Timeline Card -->
        <div class="card" style="padding: 20px; margin-bottom: 18px;">
            <h3 class="card-title" style="font-size: 1rem; margin-bottom: 14px;">
                <i class="fa-solid fa-route" style="color: var(--primary); margin-right: 8px;"></i>Tahapan Pesanan
            </h3>

            <div class="tracking-stepper">
                <div class="tracking-step" id="step-0">
                    <div class="tracking-dot"></div>
                    <div class="step-title">1. Pesanan Terkirim</div>
                    <div class="step-desc">Pesanan telah masuk ke sistem antrean restoran.</div>
                </div>

                <div class="tracking-step" id="step-1">
                    <div class="tracking-dot"></div>
                    <div class="step-title">2. Pesanan Diterima Dapur</div>
                    <div class="step-desc">Kasir & dapur telah menerima dan menyiapkan bahan.</div>
                </div>

                <div class="tracking-step" id="step-2">
                    <div class="tracking-dot"></div>
                    <div class="step-title">3. Sedang Dibuat / Dimasak</div>
                    <div class="step-desc">Koki Dapur Tyas sedang mengolah hidangan segar Anda.</div>
                </div>

                <div class="tracking-step" id="step-3">
                    <div class="tracking-dot"></div>
                    <div class="step-title">4. Siap & Sedang Diantar</div>
                    <div class="step-desc">Pelayan sedang membawakan hidangan menuju meja Anda.</div>
                </div>

                <div class="tracking-step" id="step-4">
                    <div class="tracking-dot"></div>
                    <div class="step-title">5. Selesai</div>
                    <div class="step-desc">Selamat bersantap & terima kasih telah mengunjungi Dapur Tyas!</div>
                </div>
            </div>
        </div>

        <!-- Detail Rincian Pesanan -->
        <div class="card" style="padding: 20px; margin-bottom: 20px;">
            <h3 class="card-title" style="font-size: 1rem; margin-bottom: 14px;">
                <i class="fa-solid fa-list-check" style="color: var(--primary); margin-right: 8px;"></i>Rincian Pesanan
            </h3>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($items as $item): ?>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 10px; border-bottom: 1px dashed var(--border-subtle);">
                        <div>
                            <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-primary);">
                                <?= htmlspecialchars($item['nama_menu']) ?> <span style="color: var(--primary); font-size: 0.85rem;">× <?= $item['quantity'] ?></span>
                            </div>
                            <?php if (!empty($item['catatan'])): ?>
                                <div style="font-size: 0.75rem; color: var(--text-secondary); font-style: italic; margin-top: 2px;">
                                    Catatan: "<?= htmlspecialchars($item['catatan']) ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-primary);">
                            <?= formatRupiah($item['subtotal']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($order['catatan'])): ?>
                <div style="margin-top: 14px; padding: 10px 12px; background: var(--bg-surface-soft); border-radius: var(--radius-sm); font-size: 0.8rem;">
                    <strong>Catatan Khusus:</strong> <?= htmlspecialchars($order['catatan']) ?>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 2px solid var(--border-color); font-size: 1.15rem; font-weight: 800;">
                <span>Total Pembayaran:</span>
                <span style="color: var(--primary);"><?= formatRupiah($order['total_amount']) ?></span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; font-size: 0.85rem;">
                <span style="color: var(--text-secondary);">Status Pembayaran:</span>
                <?php if ($order['status_pembayaran'] === 'lunas'): ?>
                    <span class="badge badge-olive"><i class="fa-solid fa-circle-check" style="margin-right: 4px;"></i> LUNAS</span>
                <?php else: ?>
                    <span class="badge badge-gold"><i class="fa-solid fa-clock" style="margin-right: 4px;"></i> BELUM BAYAR (Kasir)</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tombol Pesan Lagi -->
        <div style="display: flex; gap: 12px;">
            <a href="<?= BASE_URL ?>/customer/<?= $order['table_id'] ? '?meja=' . urlencode(preg_replace('/^Meja\s*/i', '', $nomorMeja)) : '' ?>" class="btn btn-outline btn-block" style="padding: 12px;">
                <i class="fa-solid fa-plus"></i> Pesan Menu Tambahan
            </a>
        </div>
    </div>
</div>

<script>
    const customerToken = "<?= htmlspecialchars($token, ENT_QUOTES) ?>";
    let currentStatus = "<?= $order['status_pesanan'] ?>";

    function updateLiveStatusUI(status, stageIndex) {
        for (let i = 0; i <= 4; i++) {
            const el = document.getElementById('step-' + i);
            if (!el) continue;
            el.classList.remove('active', 'completed');
            if (i < stageIndex) {
                el.classList.add('completed');
            } else if (i === stageIndex) {
                el.classList.add('active');
            }
        }

        const banner = document.getElementById('dynamicStatusBanner');
        const icon = document.getElementById('dynamicStatusIcon');
        const headline = document.getElementById('dynamicStatusHeadline');
        const sub = document.getElementById('dynamicStatusSub');

        banner.className = 'status-dynamic-banner';

        if (status === 'menunggu') {
            banner.classList.add('status-banner-menunggu');
            icon.className = 'fa-solid fa-clock';
            headline.textContent = 'Pesanan Baru Terkirim';
            sub.textContent = 'Menunggu konfirmasi oleh kasir & dapur.';
        } else if (status === 'diterima') {
            banner.classList.add('status-banner-diterima');
            icon.className = 'fa-solid fa-check-double';
            headline.textContent = 'Pesanan Diterima Dapur';
            sub.textContent = 'Antrean telah masuk dan bahan sedang disiapkan.';
        } else if (status === 'dimasak') {
            banner.classList.add('status-banner-dimasak');
            icon.className = 'fa-solid fa-fire-burner';
            headline.textContent = 'Sedang Dibuat / Dimasak';
            sub.textContent = 'Koki kami sedang mengolah hidangan lezat untuk Anda.';
        } else if (status === 'diantar') {
            banner.classList.add('status-banner-diantar');
            icon.className = 'fa-solid fa-bell-concierge';
            headline.textContent = '🚴 Pesanan Siap & Sedang Diantar!';
            sub.textContent = 'Hidangan sedang dibawakan menuju meja Anda. Selamat menikmati!';
        } else if (status === 'selesai') {
            banner.classList.add('status-banner-selesai');
            icon.className = 'fa-solid fa-circle-check';
            headline.textContent = '✓ Pesanan Selesai';
            sub.textContent = 'Terima kasih banyak telah bersantap di Dapur Tyas!';
        }
    }

    const initialMap = {
        'menunggu': 0,
        'diterima': 1,
        'dimasak': 2,
        'diantar': 3,
        'selesai': 4
    };
    updateLiveStatusUI(currentStatus, initialMap[currentStatus] !== undefined ? initialMap[currentStatus] : 0);

    // Polling Real-time setiap 3 detik
    function pollOrderStatus() {
        fetch('<?= BASE_URL ?>/api/get_order_status.php?token=' + encodeURIComponent(customerToken))
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.status_pesanan !== currentStatus) {
                        currentStatus = data.status_pesanan;
                        updateLiveStatusUI(data.status_pesanan, data.stage_index);
                    }
                }
            })
            .catch(err => console.log('Polling error:', err));
    }

    setInterval(pollOrderStatus, 3000);
</script>

</body>
</html>
