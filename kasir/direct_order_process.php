<?php
/**
 * Proses Order Kasir Langsung (Walk-in POS) - DAPUR TYAS by TECNO GARAGE
 * Phase 17 & 20: Direct order creation & instant payment option
 */

require_once dirname(__DIR__) . '/config/app.php';
requireKasir();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metode HTTP tidak diizinkan.'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['items']) || !is_array($data['items'])) {
    jsonResponse(['success' => false, 'message' => 'Keranjang POS kosong.'], 400);
}

$pdo = db();
$currentUser = getAuthUser();
$kasirId = $currentUser['id'];

$tableId = !empty($data['table_id']) ? (int) $data['table_id'] : null;
$customerName = !empty($data['customer_name']) ? trim(sanitize($data['customer_name'])) : 'Pelanggan Langsung';
$orderType = in_array($data['order_type'] ?? '', ['dine_in', 'take_away']) ? $data['order_type'] : 'take_away';
$metodeBayar = trim($data['metode_pembayaran'] ?? 'tunai');
$nominalBayar = (float) ($data['nominal_bayar'] ?? 0);
$items = $data['items'];

$pdo->beginTransaction();

try {
    $totalAmount = 0.00;
    $validDetails = [];
    $stmtMenu = $pdo->prepare("SELECT id, nama_menu, harga_jual, stok, status FROM menus WHERE id = :id FOR UPDATE");

    foreach ($items as $it) {
        $mid = (int) ($it['id'] ?? 0);
        $qty = (int) ($it['qty'] ?? 0);
        $note = !empty($it['note']) ? trim(sanitize($it['note'])) : null;

        if ($mid <= 0 || $qty <= 0) continue;

        $stmtMenu->execute(['id' => $mid]);
        $mDb = $stmtMenu->fetch();

        if (!$mDb || $mDb['status'] === 'nonaktif') {
            throw new Exception("Menu '{$it['name']}' tidak aktif.");
        }

        $price = (float) $mDb['harga_jual'];
        $subtotal = $price * $qty;
        $totalAmount += $subtotal;

        $validDetails[] = [
            'menu_id'  => $mid,
            'price'    => $price,
            'qty'      => $qty,
            'subtotal' => $subtotal,
            'note'     => $note,
            'stok'     => (int) $mDb['stok']
        ];
    }

    if (empty($validDetails)) {
        throw new Exception("Tidak ada item pesanan yang valid.");
    }

    $nomorOrder = generateNomorOrder();
    $customerToken = generateToken(32);

    // 1. Simpan Orders
    $stmtOrder = $pdo->prepare("INSERT INTO orders 
        (nomor_order, table_id, customer_token, customer_name, order_type, status_pesanan, status_pembayaran, total_amount, user_id) 
        VALUES (:no, :tid, :token, :cname, :otype, 'diterima', 'lunas', :total, :uid)");
    
    $stmtOrder->execute([
        'no'     => $nomorOrder,
        'tid'    => $tableId,
        'token'  => $customerToken,
        'cname'  => $customerName,
        'otype'  => $orderType,
        'total'  => $totalAmount,
        'uid'    => $kasirId
    ]);

    $orderId = (int) $pdo->lastInsertId();

    // 2. Simpan Details & Kurangi Stok Langsung
    $stmtInsertDetail = $pdo->prepare("INSERT INTO order_details (order_id, menu_id, harga_satuan, quantity, subtotal, catatan) 
                                      VALUES (:oid, :mid, :price, :qty, :sub, :cat)");
    $stmtUpdateStock = $pdo->prepare("UPDATE menus SET stok = :s, status = :st WHERE id = :mid");
    $stmtStockLog = $pdo->prepare("INSERT INTO stock_movements (menu_id, tipe, jumlah, stok_sebelum, stok_sesudah, keterangan, user_id) 
                                   VALUES (:mid, 'keluar', :qty, :before, :after, :ket, :uid)");

    foreach ($validDetails as $det) {
        $stmtInsertDetail->execute([
            'oid'   => $orderId,
            'mid'   => $det['menu_id'],
            'price' => $det['price'],
            'qty'   => $det['qty'],
            'sub'   => $det['subtotal'],
            'cat'   => $det['note']
        ]);

        $stokLama = $det['stok'];
        $stokBaru = max(0, $stokLama - $det['qty']);
        $menuStatus = $stokBaru > 0 ? 'tersedia' : 'habis';

        $stmtUpdateStock->execute(['s' => $stokBaru, 'st' => $menuStatus, 'mid' => $det['menu_id']]);
        $stmtStockLog->execute([
            'mid'    => $det['menu_id'],
            'qty'    => $det['qty'],
            'before' => $stokLama,
            'after'  => $stokBaru,
            'ket'    => "Penjualan Kasir Langsung {$nomorOrder}",
            'uid'    => $kasirId
        ]);
    }

    // 3. Simpan Pembayaran
    if ($metodeBayar === 'tunai') {
        if ($nominalBayar < $totalAmount) {
            throw new Exception("Uang yang diterima (" . formatRupiah($nominalBayar) . ") kurang dari total tagihan (" . formatRupiah($totalAmount) . ").");
        }
        $kembalian = $nominalBayar - $totalAmount;
    } else {
        $nominalBayar = $totalAmount;
        $kembalian = 0.00;
    }

    $nomorTrx = generateNomorTransaksi();
    $stmtPay = $pdo->prepare("INSERT INTO payments 
        (order_id, nomor_transaksi, metode_pembayaran, total_tagihan, nominal_bayar, kembalian, status, user_id) 
        VALUES (:oid, :notrx, :metode, :total, :bayar, :kembali, 'lunas', :uid)");
    
    $stmtPay->execute([
        'oid'     => $orderId,
        'notrx'   => $nomorTrx,
        'metode'  => $metodeBayar,
        'total'   => $totalAmount,
        'bayar'   => $nominalBayar,
        'kembali' => $kembalian,
        'uid'     => $kasirId
    ]);

    // 4. Catat Histori Order
    $pdo->prepare("INSERT INTO order_status_history (order_id, status_sebelum, status_sesudah, user_id, catatan) 
                   VALUES (:oid, NULL, 'diterima', :uid, 'Transaksi langsung kasir (lunas)')")
        ->execute(['oid' => $orderId, 'uid' => $kasirId]);

    $pdo->commit();

    jsonResponse([
        'success'         => true,
        'message'         => 'Transaksi kasir langsung berhasil disimpan!',
        'order_id'        => $orderId,
        'nomor_order'     => $nomorOrder,
        'nomor_transaksi' => $nomorTrx,
        'total_amount'    => $totalAmount,
        'kembalian'       => $kembalian,
        'kembalian_fmt'   => formatRupiah($kembalian),
        'cetak_url'       => BASE_URL . '/kasir/cetak_struk.php?order_id=' . $orderId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Direct POS Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
