<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso(34);

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_hotwheels_carros SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_carros', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del auto');
    redirigirConMensaje('carros.php', 'ok', 'Estado actualizado.');
}

$busqueda = trim($_GET['buscar'] ?? '');
$sql = "SELECT c.*, f.nombre fabricante_nombre, s.nombre serie_nombre, m.nombre marca_nombre,
        e.nombre escala_nombre, t.nombre tipo_nombre, co.nombre color_nombre, co.hexcol color_hex,
        (SELECT a.archivo FROM tbl_hotwheels_carros_archivos a WHERE a.id_tbl_hotwheels_carros = c.id AND a.state = 1 ORDER BY a.id ASC LIMIT 1) portada
    FROM tbl_hotwheels_carros c
    LEFT JOIN tbl_hotwheels_fabricantes f ON f.id = c.id_tbl_hotwheels_fabricantes
    LEFT JOIN tbl_hotwheels_series s ON s.id = c.id_tbl_hotwheels_series
    LEFT JOIN tbl_hotwheels_marcas m ON m.id = c.id_tbl_hotwheels_marcas
    LEFT JOIN tbl_hotwheels_escalas e ON e.id = c.id_tbl_hotwheels_escalas
    LEFT JOIN tbl_hotwheels_tipos t ON t.id = c.id_tbl_hotwheels_tipos
    LEFT JOIN tbl_hotwheels_colores co ON co.id = c.id_tbl_hotwheels_colores";
$params = [];
if ($busqueda !== '') {
    $sql .= " WHERE c.modelo LIKE ? OR c.internalcode LIKE ? OR m.nombre LIKE ?";
    $comodin = '%' . $busqueda . '%';
    $params = [$comodin, $comodin, $comodin];
}
$sql .= " ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$carros = $stmt->fetchAll();

$tituloPagina = 'Carros';
$paginaActiva = 'carros';
include __DIR__ . '/../includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="card-panel">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="panel-title mb-0 border-0 pb-0"><i class="bi bi-car-front-fill"></i> Carros (<?= count($carros) ?>)</h6>
        <div class="d-flex gap-2">
            <form method="get" class="d-flex gap-2">
                <input type="text" name="buscar" class="form-control form-control-sm" placeholder="Buscar por modelo, código o marca..." value="<?= limpiar($busqueda) ?>" style="width:260px;">
                <button class="btn btn-sm btn-outline-tsp" type="submit"><i class="bi bi-search"></i></button>
            </form>
            <a href="carro_detalle.php" class="btn btn-tsp btn-sm"><i class="bi bi-plus-circle"></i> Nuevo Auto</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-tsp align-middle">
            <thead><tr><th style="width:50px;"></th><th>Modelo</th><th>Marca</th><th>Serie</th><th>Tipo</th><th>Color</th><th>Escala</th><th>Cant.</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($carros as $c): ?>
                <?php $rutaMiniatura = $c['portada'] ? resolverRutaMiniaturaCarro($c['id'], $c['portada']) : null; ?>
                <tr>
                    <td>
                        <?php if ($rutaMiniatura): ?>
                            <img src="<?= limpiar($rutaMiniatura) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                        <?php else: ?>
                            <i class="bi bi-image text-muted" style="font-size:1.5rem;"></i>
                        <?php endif; ?>
                    </td>
                    <td><?= limpiar($c['modelo']) ?></td>
                    <td><?= limpiar($c['marca_nombre'] ?: '-') ?></td>
                    <td><?= limpiar($c['serie_nombre'] ?: '-') ?></td>
                    <td><?= limpiar($c['tipo_nombre'] ?: '-') ?></td>
                    <td><?php if ($c['color_nombre']): ?><span class="d-inline-block rounded border me-1" style="width:12px;height:12px;background-color:<?= limpiar($c['color_hex'] ?: '#fff') ?>;"></span><?php endif; ?><?= limpiar($c['color_nombre'] ?: '-') ?></td>
                    <td><?= limpiar($c['escala_nombre'] ?: '-') ?></td>
                    <td><?= (int)$c['cantidad'] ?></td>
                    <td><span class="badge <?= (int)$c['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$c['state']===1?'Activo':'Inactivo' ?></span></td>
                    <td class="text-nowrap">
                        <a href="carro_detalle.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-tsp"><i class="bi bi-pencil"></i></a>
                        <a href="carros.php?toggle=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este auto?')"><i class="bi bi-toggle2-on"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$carros): ?><tr><td colspan="10" class="text-center text-muted">No hay autos registrados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
