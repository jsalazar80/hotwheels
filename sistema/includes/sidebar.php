<?php
$archivoActual = basename($_SERVER['SCRIPT_NAME']);
$permisos = $_SESSION['tsp_permisos'] ?? [];

$opcionesMenu = [];
if ($permisos) {
    $placeholders = implode(',', array_fill(0, count($permisos), '?'));
    $stmtMenu = $pdo->prepare("SELECT * FROM tbl_menu_admin WHERE state = 1 AND is_submenu = 0 AND id IN ($placeholders) ORDER BY orden ASC");
    $stmtMenu->execute($permisos);
    $opcionesMenu = $stmtMenu->fetchAll();
}

// Ids que tienen submenús activos (para saber si el link debe ir a menu_opciones.php)
$idsConHijos = [];
if ($opcionesMenu) {
    $stmtHijos = $pdo->query("SELECT DISTINCT is_submenu FROM tbl_menu_admin WHERE is_submenu != 0 AND state = 1");
    $idsConHijos = $stmtHijos->fetchAll(PDO::FETCH_COLUMN);
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="assets/img/logo.svg" alt="Logo">
        <div class="brand-text">Hotwheels</div>
    </div>
    <ul class="sidebar-nav">
        <?php foreach ($opcionesMenu as $opcion): ?>
            <?php
                $tieneHijos = in_array((string)$opcion['id'], array_map('strval', $idsConHijos));
                $enlace = $tieneHijos ? 'menu_opciones.php?padre=' . $opcion['id'] : $opcion['url'];
                $activo = $tieneHijos
                    ? ($archivoActual === 'menu_opciones.php' && (int)($_GET['padre'] ?? 0) === (int)$opcion['id'])
                    : ($archivoActual === $opcion['url']);
            ?>
            <li>
                <a href="<?= limpiar($enlace) ?>" class="<?= $activo ? 'active' : '' ?>">
                    <i class="bi <?= limpiar($opcion['icono'] ?: 'bi-dot') ?>"></i> <?= limpiar($opcion['nombre']) ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
    </ul>
</div>
