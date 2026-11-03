<?php
require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT nomor_meja FROM tables WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $tb = $stmt->fetch();

    if ($tb) {
        $check = $pdo->prepare("SELECT COUNT(id) FROM orders WHERE table_id = :id");
        $check->execute(['id' => $id]);
        $orderCount = (int) $check->fetchColumn();

        if ($orderCount > 0) {
            $deact = $pdo->prepare("UPDATE tables SET status = 'nonaktif' WHERE id = :id");
            $deact->execute(['id' => $id]);
            setFlash('info', "Meja '{$tb['nomor_meja']}' memiliki {$orderCount} riwayat pesanan. Meja telah dinonaktifkan daripada dihapus permanen demi keamanan data transaksi.");
        } else {
            $del = $pdo->prepare("DELETE FROM tables WHERE id = :id");
            $del->execute(['id' => $id]);
            setFlash('success', "Meja '{$tb['nomor_meja']}' berhasil dihapus secara permanen.");
        }
    }
}

redirect('admin/meja/index.php');
