<?php
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    $role = getAuthRole();
    if ($role === 'admin') {
        redirect('admin/index.php');
    } else {
        redirect('kasir/index.php');
    }
} else {
    redirect('auth/login.php');
}
