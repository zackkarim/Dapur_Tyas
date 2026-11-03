<?php
/**
 * Cetak QR Code Meja Restoran - DAPUR TYAS by TECNO GARAGE
 * Stand Acrylic & Table Sticker Generator: 1 QR Code Utama Restoran (Pilih Meja di HP)
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$restoName    = getSetting('nama_restoran', 'DAPUR TYAS');
$restoTagline = getSetting('tagline', 'MASAKAN RUMAHAN YANG LEZAT');
$restoSub     = getSetting('sub_brand', 'by TECNO GARAGE');
$localBaseUrl = getLocalBaseUrl();
$universalUrl = getCustomerGeneralUrl();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak 1 QR Standee Meja — <?= htmlspecialchars($restoName) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/vendor/fontawesome/css/all.min.css">
    <script src="<?= BASE_URL ?>/assets/vendor/qrcodejs/qrcode.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F3F4F6;
            color: #1F242D;
            padding: 24px;
        }

        .no-print-bar {
            max-width: 640px;
            margin: 0 auto 24px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FFFFFF;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn-print {
            background-color: #C85A32;
            color: #FFFFFF;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }

        .btn-print:hover {
            background-color: #A94723;
        }

        .btn-back {
            background-color: #E5E7EB;
            color: #374151;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        /* Container 1 QR Standee Utama */
        .single-standee-container {
            max-width: 440px;
            margin: 0 auto;
        }

        .qr-card-stand {
            background: #FFFFFF;
            border: 3px solid #C85A32;
            border-radius: 24px;
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            position: relative;
            page-break-inside: avoid;
        }

        .qr-brand-frame {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #FAF7F2;
            border: 2px solid #E6DFD3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }

        .qr-brand-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .qr-brand-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: #1F242D;
            letter-spacing: -0.5px;
            margin-bottom: 2px;
        }

        .qr-brand-sub {
            font-size: 0.76rem;
            font-weight: 700;
            color: #C85A32;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }

        .qr-table-badge {
            display: inline-block;
            background: #FDF0EB;
            color: #C85A32;
            font-weight: 800;
            font-size: 1.15rem;
            padding: 6px 20px;
            border-radius: 999px;
            border: 1.5px solid #C85A32;
            margin-bottom: 16px;
        }

        .qr-canvas-box {
            background: #FFFFFF;
            padding: 14px;
            border-radius: 16px;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 2px solid #E5E7EB;
            margin-bottom: 16px;
        }

        .qr-scan-cta {
            font-size: 1.1rem;
            font-weight: 800;
            color: #1F242D;
            margin-bottom: 8px;
        }

        .steps-box {
            background: #FAF7F2;
            border: 1px solid #E6DFD3;
            border-radius: 12px;
            padding: 12px 16px;
            margin: 0 auto 16px auto;
            text-align: left;
            font-size: 0.82rem;
            color: #374151;
            line-height: 1.5;
        }

        .steps-box ol {
            padding-left: 20px;
            margin: 0;
        }

        .steps-box li {
            margin-bottom: 4px;
        }

        .steps-box li:last-child {
            margin-bottom: 0;
        }

        .qr-url-footer {
            font-family: monospace;
            font-size: 0.72rem;
            color: #9CA3AF;
            border-top: 1px dashed #E5E7EB;
            padding-top: 10px;
            word-break: break-all;
        }

        @media print {
            body {
                background: #FFFFFF;
                padding: 0;
            }

            .no-print-bar {
                display: none !important;
            }

            .single-standee-container {
                max-width: 100%;
                margin: 0 auto;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 90vh;
            }

            .qr-card-stand {
                border: 3px solid #000000;
                box-shadow: none;
                break-inside: avoid;
                width: 360px;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div>
        <h2 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 2px;">Cetak 1 QR Standee Meja</h2>
        <p style="font-size: 0.8rem; color: #6B7280;">Basis URL QR: <code style="color: #C85A32;"><?= htmlspecialchars($universalUrl) ?></code></p>
    </div>

    <div style="display: flex; gap: 10px;">
        <a href="<?= BASE_URL ?>/admin/meja/index.php" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
        <button onclick="window.print()" class="btn-print">
            <i class="fa-solid fa-print"></i> Cetak Dokumen
        </button>
    </div>
</div>

<!-- ============ 1 QR CODE STANDEE UTAMA RESTORAN ============ -->
<div class="single-standee-container">
    <div class="qr-card-stand">
        <div class="qr-brand-frame">
            <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="Logo">
        </div>

        <div class="qr-brand-title"><?= htmlspecialchars($restoName) ?></div>
        <div class="qr-brand-sub"><?= htmlspecialchars($restoTagline) ?> &bull; <?= htmlspecialchars($restoSub) ?></div>

        <div class="qr-table-badge">
            <i class="fa-solid fa-chair"></i> SCAN &amp; PILIH MEJA
        </div>

        <div class="qr-canvas-box" id="qrcode_single"></div>

        <div class="qr-scan-cta">
            <i class="fa-solid fa-qrcode" style="color: #C85A32; margin-right: 4px;"></i> Scan untuk Memesan Menu
        </div>

        <div class="steps-box">
            <ol>
                <li>Buka kamera atau aplikasi scanner QR di HP Anda</li>
                <li>Scan QR Code ini (bisa menggunakan kuota internet atau WiFi)</li>
                <li><strong>Pilih nomor meja</strong> tempat duduk Anda &amp; pesan menu!</li>
            </ol>
        </div>

        <div class="qr-url-footer">
            <?= htmlspecialchars($universalUrl) ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        new QRCode(document.getElementById("qrcode_single"), {
            text: "<?= $universalUrl ?>",
            width: 200,
            height: 200,
            colorDark: "#1F242D",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    });
</script>

</body>
</html>
