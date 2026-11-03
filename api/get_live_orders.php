<?php
/**
 * API Endpoint: Live Orders Feed for Cashier - DAPUR TYAS by TECNO GARAGE
 * Phase 18 & 19: Returns active table orders and direct orders for cashier live monitor
 */

require_once dirname(__DIR__) . '/config/app.php';
requireKasir();

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

// Ambil pesanan yang aktif (belum selesai atau pesanan hari ini)
$statusFilter = trim($_GET['status'] ?? '');
$where = ["(NOT (o.status_pesanan = 'selesai' AND o.status_pembayaran = 'lunas')
            AND (o.status_pesanan != 'dibatalkan' OR DATE(o.created_at) = CURDATE()))"];
$params = [];

if (!empty($statusFilter)) {
    $where[] = "o.status_pesanan = :st";
    $params['st'] = $statusFilter;
}

$whereClause = implode(" AND ", $where);
$query = "SELECT o.*, t.nomor_meja, t.id AS table_real_id
          FROM orders o
          LEFT JOIN tables t ON o.table_id = t.id
          WHERE $whereClause
          ORDER BY (o.status_pesanan = 'menunggu') DESC, o.created_at DESC
          LIMIT 50";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Ambil details untuk setiap order
$stmtDetails = $pdo->prepare("SELECT od.*, m.nama_menu, m.kode_menu 
                             FROM order_details od 
                             JOIN menus m ON od.menu_id = m.id 
                             WHERE od.order_id = :oid");

$result = [];
$countMenunggu = 0;

foreach ($orders as $ord) {
    if ($ord['status_pesanan'] === 'menunggu') {
        $countMenunggu++;
    }

    $stmtDetails->execute(['oid' => $ord['id']]);
    $items = $stmtDetails->fetchAll();

    $result[] = [
        'id'                => (int) $ord['id'],
        'nomor_order'       => $ord['nomor_order'],
        'table_id'          => $ord['table_id'],
        'nomor_meja'        => $ord['nomor_meja'] ?? 'Transaksi Langsung',
        'customer_name'     => $ord['customer_name'] ?? 'Pelanggan',
        'customer_token'    => $ord['customer_token'],
        'status_pesanan'    => $ord['status_pesanan'],
        'status_pembayaran' => $ord['status_pembayaran'],
        'total_amount'      => (float) $ord['total_amount'],
        'total_formatted'   => formatRupiah($ord['total_amount']),
        'catatan'           => $ord['catatan'],
        'created_at'        => $ord['created_at'],
        'time_formatted'    => date('H:i', strtotime($ord['created_at'])) . ' WIB',
        'items'             => $items
    ];
}

jsonResponse([
    'success'         => true,
    'total_active'    => count($result),
    'total_menunggu'  => $countMenunggu,
    'orders'          => $result
]);
