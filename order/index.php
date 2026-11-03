<?php
/**
 * Forwarding Router for Legacy /order/ links to /customer/
 * DAPUR TYAS by TECNO GARAGE
 */
require_once dirname(__DIR__) . '/config/app.php';

$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: " . BASE_URL . "/customer/index.php" . $qs);
exit;
