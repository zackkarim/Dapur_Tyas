<?php
/**
 * Toggle Status Kasir (Aktif / Nonaktif) - DAPUR TYAS by TECNO GARAGE
 * Phase 26: Toggle Akun Kasir
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('admin/kasir/index.php');
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id, nama, status FROM users WHERE id = :id AND role = 'kasir'");
$stmt->execute(['id' => $id]);
$kasir = $stmt->fetch();

if (!$kasir) {
    setFlash('error', 'Akun kasir tidak ditemukan.', 'danger');
    redirect('admin/kasir/index.php');
}

$newStatus = ($kasir['status'] === 'aktif') ? 'nonaktif' : 'aktif';

try {
    $stmtUpdate = $pdo->prepare("UPDATE users SET status = :st WHERE id = :id AND role = 'kasir'");
    $stmtUpdate->execute(['st' => $newStatus, 'id' => $id]);

    $pesanStatus = ($newStatus === 'aktif') ? 'diaktifkan kembali' : 'dinonaktifkan';
    setFlash('success', "Akun kasir '{$kasir['nama']}' berhasil {$pesanStatus}.");
} catch (PDOException $e) {
    error_log("Toggle Kasir Error: " . $e->getMessage());
    setFlash('error', 'Gagal mengubah status kasir.', 'danger');
}

redirect('admin/kasir/index.php');
