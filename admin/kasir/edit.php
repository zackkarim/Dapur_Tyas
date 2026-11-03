<?php
/**
 * Edit Kasir - DAPUR TYAS by TECNO GARAGE
 * Phase 26: Update Nama, Username, dan Status Kasir
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/kasir/index.php');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf($csrfToken)) {
    setFlash('error', 'Token keamanan tidak valid.', 'danger');
    redirect('admin/kasir/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
$nama = trim($_POST['nama'] ?? '');
$username = strtolower(trim($_POST['username'] ?? ''));
$status = in_array($_POST['status'] ?? '', ['aktif', 'nonaktif'], true) ? $_POST['status'] : 'aktif';

if ($id <= 0 || empty($nama) || empty($username)) {
    setFlash('error', 'Data kasir tidak lengkap atau tidak valid.', 'danger');
    redirect('admin/kasir/index.php');
}

$pdo = db();

// Cek apakah username sudah dipakai oleh akun lain
$stmtCheck = $pdo->prepare("SELECT COUNT(id) FROM users WHERE username = :u AND id != :id");
$stmtCheck->execute(['u' => $username, 'id' => $id]);
if ($stmtCheck->fetchColumn() > 0) {
    setFlash('error', "Username '{$username}' sudah digunakan oleh akun lain.", 'danger');
    redirect('admin/kasir/index.php');
}

try {
    $stmt = $pdo->prepare("UPDATE users SET nama = :nama, username = :u, status = :st WHERE id = :id AND role = 'kasir'");
    $stmt->execute([
        'nama' => $nama,
        'u'    => $username,
        'st'   => $status,
        'id'   => $id
    ]);

    setFlash('success', "Data kasir '{$nama}' berhasil diperbarui.");
} catch (PDOException $e) {
    error_log("Edit Kasir Error: " . $e->getMessage());
    setFlash('error', 'Gagal memperbarui data kasir: ' . $e->getMessage(), 'danger');
}

redirect('admin/kasir/index.php');
