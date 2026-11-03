<?php
require_once dirname(__DIR__) . '/config/app.php';
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: " . BASE_URL . "/customer/status.php" . $qs);
exit;
