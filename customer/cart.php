<?php
/**
 * Customer Shopping Cart - DAPUR TYAS by TECNO GARAGE
 * Standalone Cart Review, Item Notes, General Kitchen Notes, & Order Dispatch
 */

require_once dirname(__DIR__) . '/config/app.php';

$pdo = db();
$rawMeja = trim($_GET['meja'] ?? '');
$tableId = (int) ($_GET['table'] ?? 0);
$token = trim($_GET['token'] ?? '');

$tableInfo = null;

if (!empty($rawMeja)) {
    $cleanNum = preg_replace('/^Meja\s*/i', '', $rawMeja);
    $stmt = $pdo->prepare("SELECT * FROM tables WHERE nomor_meja = :raw OR nomor_meja = :formatted OR nomor_meja LIKE :likeNo OR id = :idNum LIMIT 1");
    $stmt->execute([
        'raw'       => $rawMeja,
        'formatted' => 'Meja ' . sprintf('%02d', (int)$cleanNum),
        'likeNo'    => '%' . $cleanNum . '%',
        'idNum'     => (int)$cleanNum
    ]);
    $tableInfo = $stmt->fetch();
} elseif ($tableId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tables WHERE id = :id AND status != 'nonaktif' LIMIT 1");
    $stmt->execute(['id' => $tableId]);
    $tableInfo = $stmt->fetch();
} elseif (!empty($_SESSION['customer_table_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM tables WHERE id = :id AND status != 'nonaktif' LIMIT 1");
    $stmt->execute(['id' => (int)$_SESSION['customer_table_id']]);
    $tableInfo = $stmt->fetch();
}

$currentTableId = $tableInfo ? (int)$tableInfo['id'] : 0;
if ($currentTableId <= 0) {
    redirect('customer/index.php');
}
$nomorMejaDisplay = $tableInfo ? $tableInfo['nomor_meja'] : 'Dapur Tyas';
$tableToken = $tableInfo ? $tableInfo['qr_identifier'] : '';

$restoName = getSetting('nama_restoran', 'DAPUR TYAS');
$restoTag  = getSetting('tagline', 'MASAKAN RUMAHAN YANG LEZAT');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Keranjang Pesanan — <?= htmlspecialchars($nomorMejaDisplay) ?> &bull; <?= htmlspecialchars($restoName) ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pelanggan.css?v=<?= time() ?>">
    <style>
        .cart-item-card {
            background: #FFFFFF;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm);
        }

        .cart-item-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .cart-item-title {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-primary);
        }

        .cart-item-price-each {
            font-size: 0.82rem;
            color: var(--text-secondary);
        }

        .cart-item-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px dashed var(--border-subtle);
            padding-top: 10px;
            margin-top: 10px;
        }

        .cart-item-subtotal {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.05rem;
        }

        .item-note-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm);
            font-size: 0.82rem;
            font-family: var(--font-main);
            background: var(--bg-surface-soft);
            margin-top: 6px;
            outline: none;
            transition: var(--transition);
        }

        .item-note-input:focus {
            border-color: var(--primary);
            background: #FFFFFF;
        }
    </style>
</head>
<body>

<div class="customer-container">
    <!-- Header -->
    <header class="customer-header">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="<?= BASE_URL ?>/customer/<?= $currentTableId ? '?meja=' . urlencode(preg_replace('/^Meja\s*/i', '', $nomorMejaDisplay)) : '' ?>" class="btn-outline" style="width: 36px; height: 36px; border-radius: 50%; padding: 0; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="brand-name-h1" style="font-size: 1.1rem;">Keranjang Pesanan</h1>
                <div class="brand-sub-tag"><?= htmlspecialchars($restoName) ?> &bull; <?= htmlspecialchars($restoTag) ?></div>
            </div>
        </div>

        <div class="table-pill-indicator">
            <i class="fa-solid fa-chair"></i>
            <span><?= htmlspecialchars($nomorMejaDisplay) ?></span>
        </div>
    </header>

    <div style="padding: 16px; flex: 1;">
        <!-- Container Items -->
        <div id="cartItemsContainer"></div>

        <!-- Empty Cart Message -->
        <div id="emptyCartMessage" style="display: none; text-align: center; padding: 48px 20px; color: var(--text-secondary);">
            <i class="fa-solid fa-basket-shopping" style="font-size: 3rem; color: #D1D5DB; margin-bottom: 14px; display: block;"></i>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin-bottom: 6px;">Keranjang Masih Kosong</h3>
            <p style="font-size: 0.85rem; margin-bottom: 20px;">Yuk pilih hidangan masakan rumahan favorit Anda!</p>
            <a href="<?= BASE_URL ?>/customer/<?= $currentTableId ? '?meja=' . urlencode(preg_replace('/^Meja\s*/i', '', $nomorMejaDisplay)) : '' ?>" class="btn btn-primary">
                <i class="fa-solid fa-utensils"></i> Lihat Daftar Menu
            </a>
        </div>

        <!-- Form Info Pemesan & Catatan Dapur -->
        <div id="checkoutFormSection" style="display: none; margin-top: 16px;">
            <div class="card" style="padding: 18px; margin-bottom: 16px;">
                <h4 style="font-size: 0.95rem; font-weight: 800; margin-bottom: 12px; color: var(--text-primary);">
                    <i class="fa-solid fa-user-pen" style="color: var(--primary); margin-right: 6px;"></i> Identitas & Catatan Dapur
                </h4>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label" for="customerName">Nama Pemesan (Opsional)</label>
                    <input type="text" id="customerName" class="form-control" placeholder="Contoh: Kak Tyas, Meja Depan" value="Pelanggan <?= htmlspecialchars($nomorMejaDisplay) ?>">
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="orderGeneralNotes">Catatan Tambahan untuk Dapur</label>
                    <textarea id="orderGeneralNotes" class="form-control" rows="2" placeholder="Contoh: Sambal dipisah, jangan terlalu manis..."></textarea>
                </div>
            </div>

            <!-- Ringkasan Tagihan -->
            <div class="card" style="padding: 18px; margin-bottom: 24px; background: var(--bg-surface-soft);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; color: var(--text-secondary);">
                    <span>Total Item Pesanan:</span>
                    <strong id="summaryTotalItems" style="color: var(--text-primary);">0 item</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 800; color: var(--text-primary); border-top: 1.5px dashed var(--border-color); padding-top: 10px;">
                    <span>Total Tagihan:</span>
                    <span id="summaryTotalAmount" style="color: var(--primary);">Rp 0</span>
                </div>
            </div>

            <!-- Tombol Checkout -->
            <button type="button" class="btn btn-primary btn-block" id="btnSubmitOrder" onclick="submitCustomerOrder()" style="padding: 14px; font-size: 1.05rem;">
                <i class="fa-solid fa-paper-plane"></i> KIRIM PESANAN SEKARANG
            </button>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/order.js?v=<?= time() ?>"></script>
<script>
    const tableId = <?= (int)$currentTableId ?>;
    const tableToken = "<?= htmlspecialchars($tableToken, ENT_QUOTES) ?>";

    document.addEventListener('DOMContentLoaded', function () {
        OrderApp.init(tableId);
    });

    function renderCartPage(cart, totalCount, totalAmount) {
        const container = document.getElementById('cartItemsContainer');
        const emptyMsg = document.getElementById('emptyCartMessage');
        const formSection = document.getElementById('checkoutFormSection');
        const summaryItems = document.getElementById('summaryTotalItems');
        const summaryAmount = document.getElementById('summaryTotalAmount');

        const items = Object.values(cart);

        if (items.length === 0) {
            container.innerHTML = '';
            emptyMsg.style.display = 'block';
            formSection.style.display = 'none';
            return;
        }

        emptyMsg.style.display = 'none';
        formSection.style.display = 'block';
        summaryItems.textContent = totalCount + ' Porsi / Item';
        summaryAmount.textContent = OrderApp.formatRupiah(totalAmount);

        let html = '';
        items.forEach(item => {
            const subtotal = item.price * item.qty;
            html += `
                <div class="cart-item-card">
                    <div class="cart-item-top">
                        <div>
                            <div class="cart-item-title">${escapeHtml(item.name)}</div>
                            <div class="cart-item-price-each">${OrderApp.formatRupiah(item.price)} &times; ${item.qty}</div>
                        </div>
                        <button type="button" onclick="OrderApp.changeQty(${item.id}, -${item.qty})" style="background: none; border: none; color: #EF4444; cursor: pointer; padding: 4px;" title="Hapus Item">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>

                    <input type="text" class="item-note-input" placeholder="Catatan item (cth: jangan pedas, es dipisah)..." 
                           value="${escapeHtml(item.note || '')}" 
                           onchange="OrderApp.updateNote(${item.id}, this.value)">

                    <div class="cart-item-bottom">
                        <div class="qty-stepper">
                            <button type="button" class="btn-qty-mod" onclick="OrderApp.changeQty(${item.id}, -1)"><i class="fa-solid fa-minus"></i></button>
                            <span class="qty-number-display">${item.qty}</span>
                            <button type="button" class="btn-qty-mod" onclick="OrderApp.changeQty(${item.id}, 1)"><i class="fa-solid fa-plus"></i></button>
                        </div>
                        <div class="cart-item-subtotal">${OrderApp.formatRupiah(subtotal)}</div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function submitCustomerOrder() {
        const cart = OrderApp.getCart();
        const items = Object.values(cart);
        if (items.length === 0) {
            alert('Keranjang Anda kosong.');
            return;
        }

        const customerName = document.getElementById('customerName').value.trim() || 'Pelanggan';
        const generalNotes = document.getElementById('orderGeneralNotes').value.trim();
        const btn = document.getElementById('btnSubmitOrder');

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim Pesanan...';

        fetch('<?= BASE_URL ?>/customer/checkout_process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                table_id: tableId,
                token: tableToken,
                customer_name: customerName,
                general_notes: generalNotes,
                items: items
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                OrderApp.clearCart();
                window.location.href = data.redirect_url;
            } else {
                alert(data.message || 'Gagal mengirim pesanan. Silakan coba lagi.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> KIRIM PESANAN SEKARANG';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Terjadi kesalahan jaringan saat mengirim pesanan.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> KIRIM PESANAN SEKARANG';
        });
    }
</script>

</body>
</html>
