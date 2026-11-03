<?php
/**
 * API Endpoint: Live Order Status Polling - DAPUR TYAS by TECNO GARAGE
 * Phase 16 & 22: Returns Live Status and Payment Status by Secure Customer Token
 */

require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

// Handle background session table setter if requested
if (isset($_GET['set_table_session'])) {
    $tid = (int)$_GET['set_table_session'];
    $stmtT = $pdo->prepare("SELECT * FROM tables WHERE id = :id AND status != 'nonaktif'");
    $stmtT->execute(['id' => $tid]);
    $tb = $stmtT->fetch();
    if ($tb) {
        $_SESSION['customer_table_id'] = (int)$tb['id'];
        $_SESSION['customer_table_no'] = $tb['nomor_meja'];
        $_SESSION['customer_table_token'] = $tb['qr_identifier'];
    }
    jsonResponse(['success' => true]);
}

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    jsonResponse(['success' => false, 'message' => 'Token pesanan tidak valid.'], 400);
}

$stmt = $pdo->prepare("SELECT o.id, o.nomor_order, o.status_pesanan, o.status_pembayaran, o.total_amount, 
                              t.nomor_meja, o.table_id, o.created_at, o.updated_at
                       FROM orders o
                       LEFT JOIN tables t ON o.table_id = t.id
                       WHERE o.customer_token = :token
                       LIMIT 1");
$stmt->execute(['token' => $token]);
$order = $stmt->fetch();

if (!$order) {
    jsonResponse(['success' => false, 'message' => 'Data pesanan tidak ditemukan.'], 404);
}

// Map stage index untuk stepper visual (0: Menunggu, 1: Diterima, 2: Dimasak/Sedang Dibuat, 3: Diantar/Siap Diantar, 4: Selesai)
$statusMap = [
    'menunggu'   => 0,
    'diterima'   => 1,
    'dimasak'    => 2,
    'diantar'    => 3,
    'selesai'    => 4,
    'dibatalkan' => -1
];

$stageIndex = $statusMap[$order['status_pesanan']] ?? 0;

jsonResponse([
    'success'           => true,
    'nomor_order'       => $order['nomor_order'],
    'status_pesanan'    => $order['status_pesanan'],
    'status_pembayaran' => $order['status_pembayaran'],
    'stage_index'       => $stageIndex,
    'nomor_meja'        => $order['nomor_meja'] ?? 'Takeaway',
    'total_amount'      => (float) $order['total_amount'],
    'total_formatted'   => formatRupiah($order['total_amount']),
    'updated_at'        => $order['updated_at']
]);
