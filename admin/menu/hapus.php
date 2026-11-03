<?php
require_once dirname(__DIR__, 2) . '/config/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT nama_menu, foto FROM menus WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $menu = $stmt->fetch();

    if ($menu) {
        // Cek apakah menu sudah pernah tercatat dalam detail transaksi/pesanan
        $check = $pdo->prepare("SELECT COUNT(id) FROM order_details WHERE menu_id = :id");
        $check->execute(['id' => $id]);
        $trxCount = (int) $check->fetchColumn();

        if ($trxCount > 0) {
            // Ubah status ke nonaktif daripada hard delete agar laporan historis tetap akurat
            $deact = $pdo->prepare("UPDATE menus SET status = 'nonaktif' WHERE id = :id");
            $deact->execute(['id' => $id]);

            setFlash('info', "Menu '{$menu['nama_menu']}' memiliki {$trxCount} riwayat transaksi. Untuk menjaga keutuhan laporan finansial, menu ini telah diubah statusnya menjadi NONAKTIF dan disembunyikan dari katalog pelanggan.");
        } else {
            // Hapus mutasi stok terkait dulu jika ada
            $pdo->prepare("DELETE FROM stock_movements WHERE menu_id = :id")->execute(['id' => $id]);
            
            // Hapus menu
            $del = $pdo->prepare("DELETE FROM menus WHERE id = :id");
            $del->execute(['id' => $id]);

            // Hapus file foto jika bukan default
            if (!empty($menu['foto']) && $menu['foto'] !== 'default_menu.png') {
                $fotoPath = dirname(__DIR__, 2) . '/assets/images/menus/' . $menu['foto'];
                if (file_exists($fotoPath)) {
                    unlink($fotoPath);
                }
            }

            setFlash('success', "Menu '{$menu['nama_menu']}' berhasil dihapus secara permanen.");
        }
    }
}

redirect('admin/menu/index.php');
