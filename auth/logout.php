<?php
require_once dirname(__DIR__) . '/config/app.php';

logoutUser();
setFlash('success', 'Anda telah berhasil keluar dari sistem.', 'success');
redirect('auth/login.php');
