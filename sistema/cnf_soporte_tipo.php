<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(12);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reordenar') {
    header('Content-Type: application/json');
    $ids = $_POST['ids'] ?? [];
    if (is_array($ids) && $ids) {
        $orden = 1;
        foreach ($ids as $idItem) {
            $stmt = $pdo->prepare("UPDATE tbl_soporte_tipo SET orden = ? WHERE id = ?");
            $stmt->execute([$orden, (int)$idItem]);
            $orden++;
        }
        registrarAuditoria($pdo, 'UPD', 'tbl_soporte_tipo', 0, '', 'Reordenamiento por arrastre: ' . implode(',', $ids));
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('cnf_soporte_tipo.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_soporte_tipo SET nombre=? WHERE id=?";
        $paramsUpd = [$nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_soporte_tipo', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de tipo de atención de soporte');
        redirigirConMensaje('cnf_soporte_tipo.php', 'ok', 'Tipo actualizado.');
    } else {
        $siguienteOrden = (int)($pdo->query("SELECT COALESCE(MAX(orden),0)+1 t FROM tbl_soporte_tipo")->fetch()['t']);
        $sqlIns = "INSERT INTO tbl_soporte_tipo (nombre, orden, user_ing) VALUES (?,?,?)";
        $paramsIns = [$nombre, $siguienteOrden, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_soporte_tipo', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo tipo de atención de soporte');
        redirigirConMensaje('cnf_soporte_tipo.php', 'ok', 'Tipo registrado.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_soporte_tipo SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_soporte_tipo', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del tipo de atención de soporte');
    redirigirConMensaje('cnf_soporte_tipo.php', 'ok', 'Estado actualizado.');
}

$tipos = $pdo->query("SELECT * FROM tbl_soporte_tipo ORDER BY orden ASC, nombre ASC")->fetchAll();

$tituloPagina = 'Tipos de Soporte';
$paginaActiva = 'cnf_soporte_tipo';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-diagram-3"></i> Nuevo Tipo</h6>
            <form method="post" id="formTipo">
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
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Tipos Registrados <small class="text-muted fw-normal">(arrastra las filas para cambiar el orden)</small></h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th style="width:30px;"></th><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="listaOrdenable">
                    <?php foreach ($tipos as $c): ?>
                        <tr draggable="true" data-id="<?= $c['id'] ?>" class="fila-arrastrable">
                            <td class="cursor-pointer text-muted"><i class="bi bi-grip-vertical"></i></td>
                            <td><?= limpiar($c['nombre']) ?></td>
                            <td><span class="badge <?= (int)$c['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$c['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarTipo(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="cnf_soporte_tipo.php?toggle=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este tipo?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tipos): ?><tr><td colspan="4" class="text-center text-muted">No hay tipos registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarTipo(c) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Tipo';
    document.getElementById('tip_id').value = c.id;
    document.getElementById('tip_nombre').value = c.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formTipo').reset();
    document.getElementById('tip_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-diagram-3"></i> Nuevo Tipo';
    document.getElementById('btnCancelar').classList.add('d-none');
}
document.addEventListener('DOMContentLoaded', function () {
    inicializarListaOrdenable('listaOrdenable', 'reordenar');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
