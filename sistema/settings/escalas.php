<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso(29);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('settings/escalas.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_hotwheels_escalas SET nombre=? WHERE id=?";
        $paramsUpd = [$nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_escalas', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de escala');
        redirigirConMensaje('settings/escalas.php', 'ok', 'Escala actualizada.');
    } else {
        $sqlIns = "INSERT INTO tbl_hotwheels_escalas (nombre, user_ing, fecha_hora_ing) VALUES (?,?,NOW())";
        $paramsIns = [$nombre, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_hotwheels_escalas', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nueva escala');
        redirigirConMensaje('settings/escalas.php', 'ok', 'Escala registrada.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_hotwheels_escalas SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_escalas', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) de la escala');
    redirigirConMensaje('settings/escalas.php', 'ok', 'Estado actualizado.');
}

$escalas = $pdo->query("SELECT * FROM tbl_hotwheels_escalas ORDER BY nombre ASC")->fetchAll();

$tituloPagina = 'Escalas';
$paginaActiva = 'escalas';
include __DIR__ . '/../includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-rulers"></i> Nueva Escala</h6>
            <form method="post" id="formEscala" action="settings/escalas.php">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="esc_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="esc_nombre" class="form-control" placeholder="Ej: 1:64" required>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Escalas Registradas</h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($escalas as $e): ?>
                        <tr>
                            <td><?= limpiar($e['nombre']) ?></td>
                            <td><span class="badge <?= (int)$e['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$e['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarEscala(<?= json_encode($e, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="settings/escalas.php?toggle=<?= $e['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de esta escala?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$escalas): ?><tr><td colspan="3" class="text-center text-muted">No hay escalas registradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarEscala(e) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Escala';
    document.getElementById('esc_id').value = e.id;
    document.getElementById('esc_nombre').value = e.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formEscala').reset();
    document.getElementById('esc_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-rulers"></i> Nueva Escala';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
