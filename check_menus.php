<?php
require_once __DIR__ . '/config/app.php';
$stmt = db()->query("SELECT m.id, m.nama_menu, m.foto, m.harga_jual, m.status, c.nama_kategori 
                     FROM menus m 
                     LEFT JOIN categories c ON m.category_id = c.id 
                     ORDER BY m.id ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
