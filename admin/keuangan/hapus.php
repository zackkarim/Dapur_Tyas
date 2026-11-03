<?php
require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$bulan = trim($_GET['bulan'] ?? date('Y-m'));

if ($id > 0) {
    $pdo = db();
    $del = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
    $del->execute(['id' => $id]);
    setFlash('success', 'Catatan pengeluaran berhasil dihapus.');
}

redirect('admin/keuangan/index.php?bulan=' . urlencode($bulan));
