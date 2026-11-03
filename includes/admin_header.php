<?php
/**
 * Admin Layout Header - DAPUR TYAS by TECNO GARAGE
 */
require_once dirname(__DIR__) . '/config/app.php';
requireAdmin();

$currentUser = getAuthUser();
$pageTitle = $pageTitle ?? 'Dashboard Admin';
$activeMenu = $activeMenu ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    
    <!-- Google Fonts & Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
    
    <!-- Chart.js CDN for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="admin-layout">
    <!-- Mobile Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/admin_sidebar.php'; ?>

    <!-- Main Content Container -->
    <main class="admin-main">
        <!-- Top Navigation Bar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button type="button" class="btn-sidebar-toggle" id="btnSidebarToggle" title="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="page-title-box">
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                    <div class="page-breadcrumb">
                        <span>Dapur Tyas</span>
                        <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                        <span><?= htmlspecialchars($pageTitle) ?></span>
                    </div>
                </div>
            </div>

            <div class="topbar-right">
                <div class="system-clock">
                    <i class="fa-regular fa-clock"></i>
                    <span id="liveClock"><?= date('H:i:s') ?> WIB</span>
                </div>
            </div>
        </header>

        <!-- Flash Alert Notification Messages -->
        <div class="admin-content">
            <?php 
            $flashSuccess = getFlash('success');
            $flashError = getFlash('error');
            $flashWarning = getFlash('warning');
            $flashInfo = getFlash('info');
            ?>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div><?= htmlspecialchars($flashSuccess['message']) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><?= htmlspecialchars($flashError['message']) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashWarning): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div><?= htmlspecialchars($flashWarning['message']) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($flashInfo): ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-circle-info"></i>
                    <div><?= htmlspecialchars($flashInfo['message']) ?></div>
                </div>
            <?php endif; ?>
