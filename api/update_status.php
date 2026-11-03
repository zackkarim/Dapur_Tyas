<?php
/**
 * API Endpoint: Update Order Status & Stock Flow - DAPUR TYAS by TECNO GARAGE
 * Phase 19: Order Status State Machine, History Logger, and Inventory Integration
 */

require_once dirname(__DIR__) . '/config/app.php';
requireKasir();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$orderId = (int) ($data['order_id'] ?? 0);
$newStatus = trim($data['status'] ?? '');
$notes = trim($data['notes'] ?? '');

$allowedStatuses = ['menunggu', 'diterima', 'dimasak', 'diantar', 'selesai', 'dibatalkan'];

if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    jsonResponse(['success' => false, 'message' => 'Data order atau status tidak valid.'], 400);
}

$pdo = db();
$currentUser = getAuthUser();
$userId = $currentUser['id'] ?? null;

$pdo->beginTransaction();

try {
    // Ambil order saat ini
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id FOR UPDATE");
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan.');
    }

    $oldStatus = $order['status_pesanan'];

    if ($oldStatus === $newStatus) {
        jsonResponse(['success' => true, 'message' => 'Status tidak berubah.']);
    }

    // 1. Integrasi Stok: Saat pesanan pertama kali 'diterima', potong stok menu
    if ($newStatus === 'diterima' && $oldStatus === 'menunggu') {
        $stmtDetails = $pdo->prepare("SELECT od.menu_id, od.quantity, m.stok, m.nama_menu 
                                     FROM order_details od 
                                     JOIN menus m ON od.menu_id = m.id 
                                     WHERE od.order_id = :oid FOR UPDATE");
        $stmtDetails->execute(['oid' => $orderId]);
        $details = $stmtDetails->fetchAll();

        foreach ($details as $d) {
            $mid = (int) $d['menu_id'];
            $qty = (int) $d['quantity'];
            $stokLama = (int) $d['stok'];
            $stokBaru = max(0, $stokLama - $qty);
            $menuStatus = $stokBaru > 0 ? 'tersedia' : 'habis';

            // Update stok menu
            $pdo->prepare("UPDATE menus SET stok = :s, status = :st WHERE id = :mid")
                ->execute(['s' => $stokBaru, 'st' => $menuStatus, 'mid' => $mid]);

            // Catat log mutasi stok
            $pdo->prepare("INSERT INTO stock_movements (menu_id, tipe, jumlah, stok_sebelum, stok_sesudah, keterangan, user_id) 
                           VALUES (:mid, 'keluar', :qty, :before, :after, :ket, :uid)")
                ->execute([
                    'mid'    => $mid,
                    'qty'    => $qty,
                    'before' => $stokLama,
                    'after'  => $stokBaru,
                    'ket'    => "Penjualan Pesanan " . $order['nomor_order'],
                    'uid'    => $userId
                ]);
        }
    }

    // 2. Revert Stok jika pesanan dibatalkan setelah diterima
    if ($newStatus === 'dibatalkan' && in_array($oldStatus, ['diterima', 'dimasak', 'diantar'], true)) {
        $stmtDetails = $pdo->prepare("SELECT od.menu_id, od.quantity, m.stok 
                                     FROM order_details od 
                                     JOIN menus m ON od.menu_id = m.id 
                                     WHERE od.order_id = :oid FOR UPDATE");
        $stmtDetails->execute(['oid' => $orderId]);
        $details = $stmtDetails->fetchAll();

        foreach ($details as $d) {
            $mid = (int) $d['menu_id'];
            $qty = (int) $d['quantity'];
            $stokLama = (int) $d['stok'];
            $stokBaru = $stokLama + $qty;

            $pdo->prepare("UPDATE menus SET stok = :s, status = 'tersedia' WHERE id = :mid")
                ->execute(['s' => $stokBaru, 'mid' => $mid]);

            $pdo->prepare("INSERT INTO stock_movements (menu_id, tipe, jumlah, stok_sebelum, stok_sesudah, keterangan, user_id) 
                           VALUES (:mid, 'masuk', :qty, :before, :after, :ket, :uid)")
                ->execute([
                    'mid'    => $mid,
                    'qty'    => $qty,
                    'before' => $stokLama,
                    'after'  => $stokBaru,
                    'ket'    => "Pengembalian Stok (Batal Pesanan " . $order['nomor_order'] . ")",
                    'uid'    => $userId
                ]);
        }
    }

    // 3. Update Status di tabel orders
    $stmtUpdate = $pdo->prepare("UPDATE orders SET status_pesanan = :st, user_id = :uid WHERE id = :id");
    $stmtUpdate->execute([
        'st'  => $newStatus,
        'uid' => $userId,
        'id'  => $orderId
    ]);

    // 4. Catat Histori Perubahan Status
    $stmtHistory = $pdo->prepare("INSERT INTO order_status_history (order_id, status_sebelum, status_sesudah, user_id, catatan) 
                                 VALUES (:oid, :before, :after, :uid, :catatan)");
    $stmtHistory->execute([
        'oid'     => $orderId,
        'before'  => $oldStatus,
        'after'   => $newStatus,
        'uid'     => $userId,
        'catatan' => !empty($notes) ? $notes : "Status diubah menjadi {$newStatus} oleh kasir ({$currentUser['nama']})"
    ]);

    // 5. Jika Selesai & Lunas, kosongkan meja
    if ($newStatus === 'selesai' && $order['table_id'] > 0 && $order['status_pembayaran'] === 'lunas') {
        $pdo->prepare("UPDATE tables SET status = 'kosong' WHERE id = :tid")
            ->execute(['tid' => $order['table_id']]);
    }

    $pdo->commit();

    jsonResponse([
        'success'        => true,
        'message'        => "Status pesanan #{$order['nomor_order']} berhasil diubah menjadi " . strtoupper($newStatus),
        'status_pesanan' => $newStatus
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Update Status Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
