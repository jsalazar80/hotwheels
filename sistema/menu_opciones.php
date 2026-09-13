<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirLogin();

$padre = (int)($_GET['padre'] ?? 0);

$stmtPadre = $pdo->prepare("SELECT * FROM tbl_menu_admin WHERE id = ?");
$stmtPadre->execute([$padre]);
$menuPadre = $stmtPadre->fetch();

if (!$menuPadre) {
    redirigirConMensaje('index.php', 'error', 'Opción de menú no encontrada.');
}

if (!tienePermiso($padre)) {
    redirigirConMensaje('index.php', 'error', 'No tiene permiso para acceder a esta sección.');
}

// Solo se muestran las opciones cuyo id el usuario tiene permitido en su perfil
$permisos = $_SESSION['tsp_permisos'] ?? [];
$opciones = [];
if ($permisos) {
    $placeholders = implode(',', array_fill(0, count($permisos), '?'));
    $stmt = $pdo->prepare("SELECT * FROM tbl_menu_admin WHERE state = 1 AND is_submenu = ? AND id IN ($placeholders) ORDER BY orden ASC");
    $stmt->execute(array_merge([$padre], $permisos));
    $opciones = $stmt->fetchAll();
}

// Una opción puede a su vez agrupar sus propias opciones hijas (ej. "Config. Carros"
// dentro de "Configuración"); en ese caso enlaza a esta misma pantalla en vez de a su url.
foreach ($opciones as &$op) {
    $op['tiene_hijos'] = menuTieneHijos($pdo, $op['id']);
}
unset($op);

$tituloPagina = limpiar($menuPadre['nombre']);
$paginaActiva = '';
include __DIR__ . '/includes/header.php';
?>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi <?= limpiar($menuPadre['icono'] ?: 'bi-grid') ?>"></i> <?= limpiar($menuPadre['nombre']) ?></h6>
    <div class="row g-3">
        <?php foreach ($opciones as $op): ?>
            <?php
                if ($op['tiene_hijos']) {
                    $enlaceHijo = 'menu_opciones.php?padre=' . $op['id'];
                } else {
                    $separador = str_contains($op['url'], '?') ? '&' : '?';
                    $enlaceHijo = $op['url'] . $separador . 'padre=' . $padre;
                }
            ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="<?= limpiar($enlaceHijo) ?>" class="text-decoration-none">
                    <div class="card-panel text-center py-4 h-100 mb-0 cursor-pointer" style="transition: box-shadow .15s;"
                         onmouseover="this.style.boxShadow='0 4px 14px rgba(23,65,122,0.18)'"
                         onmouseout="this.style.boxShadow=''">
                        <i class="bi <?= limpiar($op['icono'] ?: 'bi-dot') ?>" style="font-size:2.2rem; color: var(--azul-principal);"></i>
                        <div class="mt-2 fw-semibold text-tsp"><?= limpiar($op['nombre']) ?></div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
        <?php if (!$opciones): ?>
            <div class="col-12 text-center text-muted py-4">No hay opciones disponibles para su perfil.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
