<?php
/**
 * Tambah Kasir Baru - DAPUR TYAS by TECNO GARAGE
 * Phase 26: Validasi Username Unik, Password Hashing dengan password_hash()
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

$nama = trim($_POST['nama'] ?? '');
$username = strtolower(trim($_POST['username'] ?? ''));
$password = $_POST['password'] ?? '';

if (empty($nama) || empty($username) || empty($password)) {
    setFlash('error', 'Semua kolom wajib diisi.', 'danger');
    redirect('admin/kasir/index.php');
}

if (strlen($password) < 6) {
    setFlash('error', 'Kata sandi minimal harus 6 karakter.', 'danger');
    redirect('admin/kasir/index.php');
}

$pdo = db();

// Cek apakah username sudah dipakai
$stmtCheck = $pdo->prepare("SELECT COUNT(id) FROM users WHERE username = :u");
$stmtCheck->execute(['u' => $username]);
if ($stmtCheck->fetchColumn() > 0) {
    setFlash('error', "Username '{$username}' sudah digunakan. Silakan pilih username lain.", 'danger');
    redirect('admin/kasir/index.php');
}

// Hash password dengan BCRYPT
$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (nama, username, password, role, status) VALUES (:nama, :u, :p, 'kasir', 'aktif')");
    $stmt->execute([
        'nama' => $nama,
        'u'    => $username,
        'p'    => $hash
    ]);

    setFlash('success', "Akun kasir '{$nama}' ({$username}) berhasil ditambahkan dan siap digunakan.");
} catch (PDOException $e) {
    error_log("Tambah Kasir Error: " . $e->getMessage());
    setFlash('error', 'Gagal menambahkan akun kasir: ' . $e->getMessage(), 'danger');
}

redirect('admin/kasir/index.php');
