<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso(28);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $hexcol = trim($_POST['hexcol'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('colores.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_hotwheels_colores SET nombre=?, hexcol=? WHERE id=?";
        $paramsUpd = [$nombre, $hexcol, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_colores', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de color');
        redirigirConMensaje('colores.php', 'ok', 'Color actualizado.');
    } else {
        $sqlIns = "INSERT INTO tbl_hotwheels_colores (nombre, hexcol, user_ing, fecha_hora_ing) VALUES (?,?,?,NOW())";
        $paramsIns = [$nombre, $hexcol, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_hotwheels_colores', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo color');
        redirigirConMensaje('colores.php', 'ok', 'Color registrado.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_hotwheels_colores SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_colores', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del color');
    redirigirConMensaje('colores.php', 'ok', 'Estado actualizado.');
}

$colores = $pdo->query("SELECT * FROM tbl_hotwheels_colores ORDER BY nombre ASC")->fetchAll();

$tituloPagina = 'Colores';
$paginaActiva = 'colores';
include __DIR__ . '/../includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-palette"></i> Nuevo Color</h6>
            <form method="post" id="formColor">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="col_id" value="0">
                <div class="mb-2">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="col_nombre" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Color</label>
                    <input type="color" name="hexcol" id="col_hexcol" class="form-control form-control-color" value="#000000" title="Elija un color">
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Colores Registrados</h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th style="width:40px;"></th><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($colores as $c): ?>
                        <tr>
                            <td><span class="d-inline-block rounded border" style="width:22px;height:22px;background-color:<?= limpiar($c['hexcol'] ?: '#ffffff') ?>;"></span></td>
                            <td><?= limpiar($c['nombre']) ?></td>
                            <td><span class="badge <?= (int)$c['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$c['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarColor(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="colores.php?toggle=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este color?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$colores): ?><tr><td colspan="4" class="text-center text-muted">No hay colores registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarColor(c) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Color';
    document.getElementById('col_id').value = c.id;
    document.getElementById('col_nombre').value = c.nombre;
    document.getElementById('col_hexcol').value = c.hexcol || '#000000';
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formColor').reset();
    document.getElementById('col_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-palette"></i> Nuevo Color';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
