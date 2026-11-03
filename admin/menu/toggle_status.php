<?php
require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT status, nama_menu FROM menus WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $menu = $stmt->fetch();

    if ($menu) {
        $newStatus = 'tersedia';
        if ($menu['status'] === 'tersedia') {
            $newStatus = 'habis';
        } elseif ($menu['status'] === 'habis') {
            $newStatus = 'nonaktif';
        } else {
            $newStatus = 'tersedia';
        }

        $update = $pdo->prepare("UPDATE menus SET status = :st WHERE id = :id");
        $update->execute(['st' => $newStatus, 'id' => $id]);

        setFlash('success', "Status menu '{$menu['nama_menu']}' diubah menjadi: " . strtoupper($newStatus));
    }
}

redirect('admin/menu/index.php');
