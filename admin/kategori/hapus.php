<?php
require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $pdo = db();
    
    // Cek apakah kategori ada
    $stmt = $pdo->prepare("SELECT nama_kategori FROM categories WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $cat = $stmt->fetch();

    if ($cat) {
        // Cek apakah kategori sudah digunakan oleh menu
        $check = $pdo->prepare("SELECT COUNT(id) FROM menus WHERE category_id = :id");
        $check->execute(['id' => $id]);
        $usedCount = (int) $check->fetchColumn();

        if ($usedCount > 0) {
            // Jangan hapus jika sudah ada menu terikat untuk menjaga integritas data relasional
            setFlash('error', "Kategori '{$cat['nama_kategori']}' tidak dapat dihapus karena masih digunakan oleh {$usedCount} menu. Silakan nonaktifkan kategori ini atau pindahkan menu terkait terlebih dahulu.", 'danger');
        } else {
            $del = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $del->execute(['id' => $id]);
            setFlash('success', "Kategori '{$cat['nama_kategori']}' berhasil dihapus secara permanen.");
        }
    }
}

redirect('admin/kategori/index.php');
