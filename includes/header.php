<?php
/**
 * Global Header Component
 * AI Camera POS System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

$currentUser = current_user();
$shopName = get_setting('shop_name', 'AI SMART MART');
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= isset($pageTitle) ? clean($pageTitle) . ' - ' : '' ?><?= clean($shopName) ?></title>
    
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/receipt.css') ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

<!-- Main Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-2">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white" href="<?= is_admin() ? base_url('admin/index.php') : base_url('pos/index.php') ?>">
            <span class="badge bg-primary rounded-3 p-2 d-inline-flex align-items-center justify-content-center">
                <i class="bi bi-camera-fill fs-5"></i>
            </span>
            <span><?= clean($shopName) ?></span>
            <span class="badge bg-info text-dark rounded-pill fw-semibold ms-1 font-monospace" style="font-size: 0.7rem;">AI POS</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center">
                <?php if (is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentDir === 'admin' && $currentPage === 'index.php') ? 'active fw-semibold' : '' ?>" href="<?= base_url('admin/index.php') ?>">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentDir === 'pos' && $currentPage === 'index.php') ? 'active fw-semibold text-warning' : 'text-warning' ?>" href="<?= base_url('pos/index.php') ?>">
                            <i class="bi bi-shop me-1"></i> <strong>Open POS</strong>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentDir === 'admin' && ($currentPage === 'products.php' || $currentPage === 'product_form.php')) ? 'active fw-semibold' : '' ?>" href="<?= base_url('admin/products.php') ?>">
                            <i class="bi bi-box-seam me-1"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentDir === 'admin' && $currentPage === 'categories.php') ? 'active fw-semibold' : '' ?>" href="<?= base_url('admin/categories.php') ?>">
                            <i class="bi bi-tags me-1"></i> Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentDir === 'admin' && $currentPage === 'sales.php') ? 'active fw-semibold' : '' ?>" href="<?= base_url('admin/sales.php') ?>">
                            <i class="bi bi-receipt me-1"></i> Sales History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentDir === 'admin' && $currentPage === 'users.php') ? 'active fw-semibold' : '' ?>" href="<?= base_url('admin/users.php') ?>">
                            <i class="bi bi-people me-1"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentDir === 'admin' && $currentPage === 'settings.php') ? 'active fw-semibold' : '' ?>" href="<?= base_url('admin/settings.php') ?>">
                            <i class="bi bi-gear me-1"></i> AI & Settings
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentDir === 'pos' && $currentPage === 'index.php') ? 'active fw-bold' : '' ?>" href="<?= base_url('pos/index.php') ?>">
                            <i class="bi bi-shop me-1"></i> Point of Sale
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentDir === 'pos' && $currentPage === 'sales.php') ? 'active fw-semibold' : '' ?>" href="<?= base_url('pos/sales.php') ?>">
                            <i class="bi bi-receipt me-1"></i> My Sales
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                <?php if ($currentUser): ?>
                    <div class="dropdown">
                        <button class="btn btn-dark border border-secondary text-white dropdown-toggle d-flex align-items-center gap-2 py-1 px-3 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="badge <?= is_admin() ? 'bg-danger' : 'bg-success' ?> rounded-pill text-uppercase" style="font-size: 0.65rem;">
                                <?= clean($currentUser['role']) ?>
                            </span>
                            <span class="small fw-semibold"><?= clean($currentUser['full_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><h6 class="dropdown-header">Signed in as <strong>@<?= clean($currentUser['username']) ?></strong></h6></li>
                            <?php if (is_admin()): ?>
                                <li><a class="dropdown-item" href="<?= base_url('admin/settings.php') ?>"><i class="bi bi-gear me-2"></i>AI Settings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= base_url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= base_url('login.php') ?>" class="btn btn-outline-light btn-sm px-3 rounded-pill">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Main Container Start -->
<main class="flex-grow-1">
    <div class="container-fluid px-3 px-lg-4 py-3">
        <?php require_once __DIR__ . '/alerts.php'; ?>
