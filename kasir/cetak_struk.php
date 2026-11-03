<?php
/**
 * Cetak Struk Transaksi POS - DAPUR TYAS by TECNO GARAGE
 * Thermal Receipt Layout (58mm/80mm) with Restaurant Header & Itemized Details
 */

require_once dirname(__DIR__) . '/config/app.php';
requireKasir();

$orderId = (int) ($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    die("ID Transaksi tidak ditemukan.");
}

$pdo = db();

// Ambil data Order & Table
$stmt = $pdo->prepare("SELECT o.*, t.nomor_meja, u.nama AS nama_kasir 
                       FROM orders o
                       LEFT JOIN tables t ON o.table_id = t.id
                       LEFT JOIN users u ON o.user_id = u.id
                       WHERE o.id = :id");
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("Pesanan tidak ditemukan.");
}

// Ambil Item Order Details
$stmtItems = $pdo->prepare("SELECT od.*, m.nama_menu, m.kode_menu 
                            FROM order_details od 
                            JOIN menus m ON od.menu_id = m.id 
                            WHERE od.order_id = :id 
                            ORDER BY od.id ASC");
$stmtItems->execute(['id' => $orderId]);
$items = $stmtItems->fetchAll();

// Ambil Data Pembayaran (Payment)
$stmtPay = $pdo->prepare("SELECT * FROM payments WHERE order_id = :id ORDER BY id DESC LIMIT 1");
$stmtPay->execute(['id' => $orderId]);
$payment = $stmtPay->fetch();

$namaResto   = getSetting('nama_restoran', 'DAPUR TYAS');
$tagline     = getSetting('tagline', 'MASAKAN RUMAHAN YANG LEZAT');
$subBrand    = getSetting('sub_brand', 'by TECNO GARAGE');
$alamat      = getSetting('alamat', 'Jl. Kuliner Nusantara No. 88, Medan');
$telepon     = getSetting('telepon', '081234567890');
$footerStruk = getSetting('footer_struk', "Terima kasih atas kunjungan Anda di Dapur Tyas!\nFollow Instagram kami: @dapurtyas.id");

$kasirNama = !empty($payment['user_id']) ? ($pdo->query("SELECT nama FROM users WHERE id = {$payment['user_id']}")->fetchColumn() ?: 'Staff Kasir') : ($order['nama_kasir'] ?: 'Staff Kasir');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran #<?= htmlspecialchars($order['nomor_order']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&family=Plus+Jakarta+Sans:wght@500;700&display=swap');

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Courier Prime', Courier, monospace;
            background-color: #F3F4F6;
            color: #000000;
            padding: 20px;
        }

        .receipt-control-bar {
            max-width: 360px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .btn-action {
            background-color: #C85A32;
            color: #FFFFFF;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-back-btn {
            background-color: #E5E7EB;
            color: #374151;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
        }

        .receipt-paper {
            max-width: 340px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 24px 18px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #E5E7EB;
            font-size: 12px;
            line-height: 1.4;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: 700; }

        .receipt-brand-title {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .receipt-brand-sub {
            font-size: 10.5px;
            margin-bottom: 4px;
        }

        .divider-dashed {
            border-top: 1px dashed #000000;
            margin: 8px 0;
        }

        .divider-double {
            border-top: 2px dashed #000000;
            margin: 8px 0;
        }

        .meta-table {
            width: 100%;
            margin-bottom: 6px;
        }

        .meta-table td {
            padding: 1px 0;
            vertical-align: top;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .footer-note {
            font-size: 10.5px;
            line-height: 1.35;
            white-space: pre-line;
            margin-top: 8px;
        }

        @media print {
            body {
                background: #FFFFFF;
                padding: 0;
            }

            .receipt-control-bar {
                display: none !important;
            }

            .receipt-paper {
                box-shadow: none;
                border: none;
                max-width: 100%;
                width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="receipt-control-bar">
    <a href="<?= BASE_URL ?>/kasir/index.php" class="btn-back-btn">
        <i class="fa-solid fa-arrow-left"></i> Kasir
    </a>
    <button onclick="window.print()" class="btn-action">
        <i class="fa-solid fa-print"></i> CETAK STRUK
    </button>
</div>

<div class="receipt-paper">
    <!-- Header -->
    <div class="text-center">
        <div style="width: 58px; height: 58px; border-radius: 50%; overflow: hidden; margin: 0 auto 6px auto; border: 1.5px solid #000;">
            <img src="<?= BASE_URL ?>/promt/tambah_logo.png" alt="Logo"
                 style="width: 100%; height: 100%; object-fit: cover; display: block; filter: grayscale(100%) contrast(150%);">
        </div>
        <div class="receipt-brand-title"><?= htmlspecialchars($namaResto) ?></div>
        <div class="receipt-brand-sub"><?= htmlspecialchars($tagline) ?> &bull; <?= htmlspecialchars($subBrand) ?></div>
        <div><?= htmlspecialchars($alamat) ?></div>
        <div>Telp: <?= htmlspecialchars($telepon) ?></div>
    </div>

    <div class="divider-double"></div>

    <!-- Metadata Transaksi -->
    <table class="meta-table">
        <tr>
            <td style="width: 75px;">No. Order</td>
            <td>: <strong><?= htmlspecialchars($order['nomor_order']) ?></strong></td>
        </tr>
        <?php if ($payment): ?>
            <tr>
                <td>No. Trx</td>
                <td>: <?= htmlspecialchars($payment['nomor_transaksi']) ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td>Meja</td>
            <td>: <strong><?= htmlspecialchars($order['nomor_meja'] ?? 'Transaksi Langsung') ?></strong></td>
        </tr>
        <tr>
            <td>Kasir</td>
            <td>: <?= htmlspecialchars($kasirNama) ?></td>
        </tr>
        <tr>
            <td>Waktu</td>
            <td>: <?= date('d/m/Y H:i', strtotime($payment['paid_at'] ?? $order['created_at'])) ?> WIB</td>
        </tr>
        <tr>
            <td>Pelanggan</td>
            <td>: <?= htmlspecialchars($order['customer_name'] ?? 'Pelanggan') ?></td>
        </tr>
    </table>

    <div class="divider-dashed"></div>

    <!-- Items List -->
    <table class="items-table">
        <?php foreach ($items as $it): ?>
            <tr>
                <td colspan="2" class="font-bold"><?= htmlspecialchars($it['nama_menu']) ?></td>
            </tr>
            <tr>
                <td style="padding-left: 8px;">
                    <?= $it['quantity'] ?> x <?= number_format($it['harga_satuan'], 0, ',', '.') ?>
                    <?php if (!empty($it['catatan'])): ?>
                        <div style="font-size: 10px; font-style: italic;">* <?= htmlspecialchars($it['catatan']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-right"><?= number_format($it['subtotal'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="divider-dashed"></div>

    <!-- Summary Totals -->
    <table class="meta-table">
        <tr>
            <td>Subtotal</td>
            <td class="text-right"><?= number_format($order['total_amount'], 0, ',', '.') ?></td>
        </tr>
        <tr class="font-bold" style="font-size: 13px;">
            <td>TOTAL</td>
            <td class="text-right">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></td>
        </tr>
    </table>

    <div class="divider-dashed"></div>

    <!-- Payment Breakdown -->
    <table class="meta-table">
        <tr>
            <td>Metode Bayar</td>
            <td class="text-right font-bold"><?= strtoupper($payment['metode_pembayaran'] ?? 'TUNAI') ?></td>
        </tr>
        <tr>
            <td>Jumlah Bayar</td>
            <td class="text-right"><?= number_format($payment['nominal_bayar'] ?? $order['total_amount'], 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td>Kembalian</td>
            <td class="text-right font-bold"><?= number_format($payment['kembalian'] ?? 0, 0, ',', '.') ?></td>
        </tr>
    </table>

    <div class="divider-double"></div>

    <!-- Footer Notes -->
    <div class="text-center footer-note">
        <?= nl2br(htmlspecialchars($footerStruk)) ?>
    </div>
</div>

<script>
    <?php if (isset($_GET['autoprint']) && $_GET['autoprint'] == '1'): ?>
        window.addEventListener('load', function() {
            window.print();
        });
    <?php endif; ?>
</script>

</body>
</html>
