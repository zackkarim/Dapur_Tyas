<?php
require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT status, nomor_meja FROM tables WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $tb = $stmt->fetch();

    if ($tb) {
        $newStatus = 'kosong';
        if ($tb['status'] === 'kosong') {
            $newStatus = 'terisi';
        } elseif ($tb['status'] === 'terisi') {
            $newStatus = 'reservasi';
        } elseif ($tb['status'] === 'reservasi') {
            $newStatus = 'nonaktif';
        } else {
            $newStatus = 'kosong';
        }

        $update = $pdo->prepare("UPDATE tables SET status = :st WHERE id = :id");
        $update->execute(['st' => $newStatus, 'id' => $id]);

        setFlash('success', "Status '{$tb['nomor_meja']}' diubah menjadi: " . strtoupper($newStatus));
    }
}

redirect('admin/meja/index.php');
