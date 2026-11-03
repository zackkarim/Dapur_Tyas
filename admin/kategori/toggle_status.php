<?php
require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT status, nama_kategori FROM categories WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $cat = $stmt->fetch();

    if ($cat) {
        $newStatus = $cat['status'] === 'aktif' ? 'nonaktif' : 'aktif';
        $update = $pdo->prepare("UPDATE categories SET status = :st WHERE id = :id");
        $update->execute(['st' => $newStatus, 'id' => $id]);

        setFlash('success', "Status kategori '{$cat['nama_kategori']}' berhasil diubah menjadi " . strtoupper($newStatus) . ".");
    }
}

redirect('admin/kategori/index.php');
