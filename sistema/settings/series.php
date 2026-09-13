<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso(32);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('series.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_hotwheels_series SET nombre=? WHERE id=?";
        $paramsUpd = [$nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_series', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de serie');
        redirigirConMensaje('series.php', 'ok', 'Serie actualizada.');
    } else {
        $sqlIns = "INSERT INTO tbl_hotwheels_series (nombre, user_ing, fecha_hora_ing) VALUES (?,?,NOW())";
        $paramsIns = [$nombre, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_hotwheels_series', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nueva serie');
        redirigirConMensaje('series.php', 'ok', 'Serie registrada.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_hotwheels_series SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_series', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) de la serie');
    redirigirConMensaje('series.php', 'ok', 'Estado actualizado.');
}

$series = $pdo->query("SELECT * FROM tbl_hotwheels_series ORDER BY nombre ASC")->fetchAll();

$tituloPagina = 'Series';
$paginaActiva = 'series';
include __DIR__ . '/../includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-collection"></i> Nueva Serie</h6>
            <form method="post" id="formSerie">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="ser_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="ser_nombre" class="form-control" required>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Series Registradas</h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($series as $s): ?>
                        <tr>
                            <td><?= limpiar($s['nombre']) ?></td>
                            <td><span class="badge <?= (int)$s['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$s['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarSerie(<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="series.php?toggle=<?= $s['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de esta serie?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$series): ?><tr><td colspan="3" class="text-center text-muted">No hay series registradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarSerie(s) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Serie';
    document.getElementById('ser_id').value = s.id;
    document.getElementById('ser_nombre').value = s.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formSerie').reset();
    document.getElementById('ser_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-collection"></i> Nueva Serie';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
