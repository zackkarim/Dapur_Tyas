<?php
/**
 * Proses Pembayaran Kasir - DAPUR TYAS by TECNO GARAGE
 * Phase 20: Cash, QRIS, & Transfer Processing, Change Calculator, and Invoice Generation
 */

require_once dirname(__DIR__) . '/config/app.php';
requireKasir();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metode HTTP tidak diizinkan.'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$orderId = (int) ($data['order_id'] ?? 0);
$metode = trim($data['metode_pembayaran'] ?? 'tunai'); // tunai, qris, transfer
$nominalBayar = (float) ($data['nominal_bayar'] ?? 0);

if ($orderId <= 0 || !in_array($metode, ['tunai', 'qris', 'transfer'], true)) {
    jsonResponse(['success' => false, 'message' => 'Data pembayaran tidak lengkap atau metode tidak valid.'], 400);
}

$pdo = db();
$currentUser = getAuthUser();
$kasirId = $currentUser['id'];

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan.');
    }

    if ($order['status_pembayaran'] === 'lunas') {
        throw new Exception('Pesanan ini sudah dibayar lunas sebelumnya.');
    }

    $totalTagihan = (float) $order['total_amount'];

    // Validasi nominal bayar untuk tunai
    if ($metode === 'tunai') {
        if ($nominalBayar < $totalTagihan) {
            throw new Exception('Nominal uang yang dibayarkan kurang dari total tagihan (' . formatRupiah($totalTagihan) . ').');
        }
        $kembalian = $nominalBayar - $totalTagihan;
    } else {
        // QRIS / Transfer (simulasi lunas tepat)
        $nominalBayar = $totalTagihan;
        $kembalian = 0.00;
    }

    $nomorTransaksi = generateNomorTransaksi();

    // 1. Catat ke tabel payments
    $stmtPay = $pdo->prepare("INSERT INTO payments 
        (order_id, nomor_transaksi, metode_pembayaran, total_tagihan, nominal_bayar, kembalian, status, user_id) 
        VALUES (:oid, :notrx, :metode, :total, :bayar, :kembali, 'lunas', :uid)");
    
    $stmtPay->execute([
        'oid'     => $orderId,
        'notrx'   => $nomorTransaksi,
        'metode'  => $metode,
        'total'   => $totalTagihan,
        'bayar'   => $nominalBayar,
        'kembali' => $kembalian,
        'uid'     => $kasirId
    ]);

    // 2. Update status pembayaran di tabel orders
    $pdo->prepare("UPDATE orders SET status_pembayaran = 'lunas', user_id = :uid WHERE id = :oid")
        ->execute(['uid' => $kasirId, 'oid' => $orderId]);

    // 3. Tandai pesanan selesai & kosongkan meja agar langsung masuk riwayat
    if ($order['status_pesanan'] !== 'dibatalkan') {
        if ($order['status_pesanan'] !== 'selesai') {
            $pdo->prepare("UPDATE orders SET status_pesanan = 'selesai', user_id = :uid WHERE id = :oid")
                ->execute(['uid' => $kasirId, 'oid' => $orderId]);

            $pdo->prepare("INSERT INTO order_status_history (order_id, status_sebelum, status_sesudah, user_id, catatan)
                           VALUES (:oid, :before, 'selesai', :uid, 'Pembayaran lunas, pesanan otomatis diselesaikan')")
                ->execute([
                    'oid'    => $orderId,
                    'before' => $order['status_pesanan'],
                    'uid'    => $kasirId
                ]);
        }

        if ($order['table_id'] > 0) {
            $pdo->prepare("UPDATE tables SET status = 'kosong' WHERE id = :tid")
                ->execute(['tid' => $order['table_id']]);
        }
    }

    $pdo->commit();

    jsonResponse([
        'success'         => true,
        'message'         => 'Pembayaran berhasil diproses!',
        'nomor_transaksi' => $nomorTransaksi,
        'total_tagihan'   => $totalTagihan,
        'nominal_bayar'   => $nominalBayar,
        'kembalian'       => $kembalian,
        'kembalian_fmt'   => formatRupiah($kembalian),
        'order_id'        => $orderId,
        'cetak_url'       => BASE_URL . '/kasir/cetak_struk.php?order_id=' . $orderId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Payment Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
