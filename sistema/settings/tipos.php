<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso(33);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('tipos.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_hotwheels_tipos SET nombre=? WHERE id=?";
        $paramsUpd = [$nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_tipos', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de tipo');
        redirigirConMensaje('tipos.php', 'ok', 'Tipo actualizado.');
    } else {
        $sqlIns = "INSERT INTO tbl_hotwheels_tipos (nombre, user_ing, fecha_hora_ing) VALUES (?,?,NOW())";
        $paramsIns = [$nombre, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_hotwheels_tipos', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo tipo');
        redirigirConMensaje('tipos.php', 'ok', 'Tipo registrado.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_hotwheels_tipos SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_tipos', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del tipo');
    redirigirConMensaje('tipos.php', 'ok', 'Estado actualizado.');
}

[$pagina, $porPagina, $offset] = obtenerPaginacion();
$totalTipos = (int)$pdo->query("SELECT COUNT(*) t FROM tbl_hotwheels_tipos")->fetch()['t'];
$tipos = $pdo->query("SELECT * FROM tbl_hotwheels_tipos ORDER BY nombre ASC LIMIT $porPagina OFFSET $offset")->fetchAll();

$tituloPagina = 'Tipos';
$paginaActiva = 'tipos';
include __DIR__ . '/../includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-grid-3x3-gap"></i> Nuevo Tipo</h6>
            <form method="post" id="formTipo" action="settings/tipos.php">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="tip_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="tip_nombre" class="form-control" required>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Tipos Registrados</h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($tipos as $t): ?>
                        <tr>
                            <td><?= limpiar($t['nombre']) ?></td>
                            <td><span class="badge <?= (int)$t['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$t['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarTipo(<?= json_encode($t, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="settings/tipos.php?toggle=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este tipo?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tipos): ?><tr><td colspan="3" class="text-center text-muted">No hay tipos registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php renderizarPaginador($totalTipos, $pagina, $porPagina); ?>
        </div>
    </div>
</div>

<script>
function editarTipo(t) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Tipo';
    document.getElementById('tip_id').value = t.id;
    document.getElementById('tip_nombre').value = t.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formTipo').reset();
    document.getElementById('tip_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-grid-3x3-gap"></i> Nuevo Tipo';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
