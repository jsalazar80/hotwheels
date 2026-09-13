<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<base href="/">
<title><?= isset($tituloPagina) ? limpiar($tituloPagina) . ' - Hotwheels' : 'Hotwheels' ?></title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="assets/img/icon-192.png">
<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#17417a">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
<div class="topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn-toggle" id="btnToggleSidebar"><i class="bi bi-list"></i></button>
        <h5 class="mb-0 text-tsp fw-bold"><?= limpiar($tituloPagina ?? '') ?></h5>
    </div>
    <div class="dropdown">
        <button class="btn btn-outline-tsp btn-sm dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= limpiar($_SESSION['tsp_nombre'] ?? '') ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= limpiar($_SESSION['tsp_perfil_nombre'] ?? '') ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
        </ul>
    </div>
</div>
<div class="content-area">
<?php mostrarAlertas(); ?>
