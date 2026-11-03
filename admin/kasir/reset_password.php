<?php
/**
 * Reset Password Kasir - DAPUR TYAS by TECNO GARAGE
 * Phase 26: Reset Password Kasir dengan password_hash()
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
$newPassword = $_POST['new_password'] ?? '';

if ($id <= 0 || empty($newPassword)) {
    setFlash('error', 'Data tidak lengkap.', 'danger');
    redirect('admin/kasir/index.php');
}

if (strlen($newPassword) < 6) {
    setFlash('error', 'Kata sandi baru minimal 6 karakter.', 'danger');
    redirect('admin/kasir/index.php');
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id, nama FROM users WHERE id = :id AND role = 'kasir'");
$stmt->execute(['id' => $id]);
$kasir = $stmt->fetch();

if (!$kasir) {
    setFlash('error', 'Akun kasir tidak ditemukan.', 'danger');
    redirect('admin/kasir/index.php');
}

$hash = password_hash($newPassword, PASSWORD_BCRYPT);

try {
    $stmtUpdate = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id AND role = 'kasir'");
    $stmtUpdate->execute([
        'p'  => $hash,
        'id' => $id
    ]);

    setFlash('success', "Kata sandi untuk kasir '{$kasir['nama']}' berhasil direset.");
} catch (PDOException $e) {
    error_log("Reset Password Kasir Error: " . $e->getMessage());
    setFlash('error', 'Gagal mereset kata sandi kasir.', 'danger');
}

redirect('admin/kasir/index.php');
