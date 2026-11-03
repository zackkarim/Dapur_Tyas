<?php
/**
 * Checkout Process Handler - DAPUR TYAS by TECNO GARAGE
 * Handles customer order creation, calculates subtotal, updates table status & history
 */

require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metode HTTP tidak diizinkan.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items'])) {
    jsonResponse(['success' => false, 'message' => 'Data pesanan atau keranjang kosong.'], 400);
}

$tableId = (int) ($input['table_id'] ?? 0);
$customerName = trim($input['customer_name'] ?? 'Pelanggan');
$generalNotes = trim($input['general_notes'] ?? '');
$items = (array) $input['items'];

$pdo = db();

// Validasi Meja Wajib
$table = null;
if ($tableId > 0) {
    $stmtT = $pdo->prepare("SELECT * FROM tables WHERE id = :id AND status != 'nonaktif'");
    $stmtT->execute(['id' => $tableId]);
    $table = $stmtT->fetch();
}

if (!$table) {
    jsonResponse(['success' => false, 'message' => 'Silakan pilih nomor meja Anda terlebih dahulu sebelum memesan.'], 400);
}

$nomorOrder = generateNomorOrder();
$customerToken = generateToken(32);

try {
    $pdo->beginTransaction();

    // Hitung Total & Validasi Harga Aktual dari DB
    $totalAmount = 0;
    $validOrderDetails = [];

    $stmtMenu = $pdo->prepare("SELECT id, nama_menu, harga_jual, stok, status FROM menus WHERE id = :id FOR UPDATE");

    foreach ($items as $item) {
        $menuId = (int) ($item['id'] ?? 0);
        $qty = (int) ($item['qty'] ?? 1);
        $itemNote = trim($item['note'] ?? '');

        if ($menuId <= 0 || $qty <= 0) continue;

        $stmtMenu->execute(['id' => $menuId]);
        $menuDb = $stmtMenu->fetch();

        if (!$menuDb || $menuDb['status'] === 'nonaktif') {
            throw new Exception("Menu '{$item['name']}' sudah tidak tersedia.");
        }

        $hargaSatuan = (float) $menuDb['harga_jual'];
        $subtotal = $hargaSatuan * $qty;
        $totalAmount += $subtotal;

        $validOrderDetails[] = [
            'menu_id'      => $menuId,
            'harga_satuan' => $hargaSatuan,
            'quantity'     => $qty,
            'subtotal'     => $subtotal,
            'catatan'      => $itemNote
        ];
    }

    if (empty($validOrderDetails)) {
        throw new Exception("Tidak ada item yang valid untuk diproses.");
    }

    // Insert Order Header
    $stmtOrder = $pdo->prepare("INSERT INTO orders 
        (nomor_order, table_id, customer_token, customer_name, order_type, status_pesanan, status_pembayaran, total_amount, catatan) 
        VALUES (:no, :table_id, :token, :cname, :otype, 'menunggu', 'belum_bayar', :total, :notes)");

    $stmtOrder->execute([
        'no'        => $nomorOrder,
        'table_id'  => $table ? $table['id'] : null,
        'token'     => $customerToken,
        'cname'     => $customerName ?: ($table ? 'Pelanggan ' . $table['nomor_meja'] : 'Pelanggan'),
        'otype'     => $table ? 'dine_in' : 'take_away',
        'total'     => $totalAmount,
        'notes'     => $generalNotes
    ]);

    $orderId = (int) $pdo->lastInsertId();

    // Insert Order Details
    $stmtDetail = $pdo->prepare("INSERT INTO order_details 
        (order_id, menu_id, harga_satuan, quantity, subtotal, catatan) 
        VALUES (:oid, :mid, :harga, :qty, :sub, :catatan)");

    foreach ($validOrderDetails as $det) {
        $stmtDetail->execute([
            'oid'     => $orderId,
            'mid'     => $det['menu_id'],
            'harga'   => $det['harga_satuan'],
            'qty'     => $det['quantity'],
            'sub'     => $det['subtotal'],
            'catatan' => $det['catatan']
        ]);
    }

    // Insert Initial Tracking History
    $stmtHist = $pdo->prepare("INSERT INTO order_status_history 
        (order_id, status_sebelum, status_sesudah, catatan) 
        VALUES (:oid, NULL, 'menunggu', 'Pesanan berhasil dikirim oleh pelanggan.')");
    $stmtHist->execute(['oid' => $orderId]);

    // Update status meja menjadi 'terisi' jika sebelumnya kosong
    if ($table && $table['status'] === 'kosong') {
        $pdo->prepare("UPDATE tables SET status = 'terisi' WHERE id = :tid")->execute(['tid' => $table['id']]);
    }

    $pdo->commit();

    // Redirect link ke halaman live tracking customer
    $redirectUrl = BASE_URL . '/customer/status.php?token=' . urlencode($customerToken);

    jsonResponse([
        'success'      => true,
        'message'      => 'Pesanan Anda berhasil dikirim ke dapur.',
        'nomor_order'  => $nomorOrder,
        'token'        => $customerToken,
        'redirect_url' => $redirectUrl
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}
