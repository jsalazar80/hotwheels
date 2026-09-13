<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(24);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('cnf_tipos_pago.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_tipos_pago SET nombre=? WHERE id=?";
        $paramsUpd = [$nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_tipos_pago', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de tipo de pago');
        redirigirConMensaje('cnf_tipos_pago.php', 'ok', 'Tipo de pago actualizado.');
    } else {
        $sqlIns = "INSERT INTO tbl_tipos_pago (nombre, user_ing) VALUES (?,?)";
        $paramsIns = [$nombre, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_tipos_pago', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo tipo de pago');
        redirigirConMensaje('cnf_tipos_pago.php', 'ok', 'Tipo de pago registrado.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_tipos_pago SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_tipos_pago', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del tipo de pago');
    redirigirConMensaje('cnf_tipos_pago.php', 'ok', 'Estado actualizado.');
}

$tipos = $pdo->query("SELECT * FROM tbl_tipos_pago ORDER BY id ASC")->fetchAll();

$tituloPagina = 'Tipos de Pago';
$paginaActiva = 'cnf_tipos_pago';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-credit-card"></i> Nuevo Tipo de Pago</h6>
            <form method="post" id="formTipo">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="tp_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="tp_nombre" class="form-control" required>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Tipos de Pago Registrados</h6>
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
                                <a href="cnf_tipos_pago.php?toggle=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este tipo de pago?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tipos): ?><tr><td colspan="3" class="text-center text-muted">No hay tipos de pago registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarTipo(t) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Tipo de Pago';
    document.getElementById('tp_id').value = t.id;
    document.getElementById('tp_nombre').value = t.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formTipo').reset();
    document.getElementById('tp_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-credit-card"></i> Nuevo Tipo de Pago';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
