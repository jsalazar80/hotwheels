<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(13);

$coloresDisponibles = ['secondary' => 'Gris', 'info' => 'Celeste', 'primary' => 'Azul', 'warning' => 'Amarillo', 'danger' => 'Rojo', 'success' => 'Verde', 'dark' => 'Negro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reordenar') {
    header('Content-Type: application/json');
    $ids = $_POST['ids'] ?? [];
    if (is_array($ids) && $ids) {
        $orden = 1;
        foreach ($ids as $idItem) {
            $stmt = $pdo->prepare("UPDATE tbl_soporte_prioridad SET orden = ? WHERE id = ?");
            $stmt->execute([$orden, (int)$idItem]);
            $orden++;
        }
        registrarAuditoria($pdo, 'UPD', 'tbl_soporte_prioridad', 0, '', 'Reordenamiento por arrastre: ' . implode(',', $ids));
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $color = trim($_POST['color'] ?? 'secondary');

    if ($nombre === '') {
        redirigirConMensaje('cnf_soporte_prioridad.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_soporte_prioridad SET nombre=?, color=? WHERE id=?";
        $paramsUpd = [$nombre, $color, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_soporte_prioridad', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de prioridad de soporte');
        redirigirConMensaje('cnf_soporte_prioridad.php', 'ok', 'Prioridad actualizada.');
    } else {
        $siguienteOrden = (int)($pdo->query("SELECT COALESCE(MAX(orden),0)+1 t FROM tbl_soporte_prioridad")->fetch()['t']);
        $sqlIns = "INSERT INTO tbl_soporte_prioridad (nombre, color, orden, user_ing) VALUES (?,?,?,?)";
        $paramsIns = [$nombre, $color, $siguienteOrden, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_soporte_prioridad', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nueva prioridad de soporte');
        redirigirConMensaje('cnf_soporte_prioridad.php', 'ok', 'Prioridad registrada.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_soporte_prioridad SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_soporte_prioridad', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) de la prioridad de soporte');
    redirigirConMensaje('cnf_soporte_prioridad.php', 'ok', 'Estado actualizado.');
}

$prioridades = $pdo->query("SELECT * FROM tbl_soporte_prioridad ORDER BY orden ASC, nombre ASC")->fetchAll();

$tituloPagina = 'Prioridades de Soporte';
$paginaActiva = 'cnf_soporte_prioridad';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-exclamation-diamond"></i> Nueva Prioridad</h6>
            <form method="post" id="formPrioridad">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="pr_id" value="0">
                <div class="mb-2">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="pr_nombre" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Color</label>
                    <select class="form-select" name="color" id="pr_color">
                        <?php foreach ($coloresDisponibles as $valor => $etiqueta): ?>
                            <option value="<?= $valor ?>"><?= $etiqueta ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Prioridades Registradas <small class="text-muted fw-normal">(arrastra las filas para cambiar el orden)</small></h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th style="width:30px;"></th><th>Nombre</th><th>Color</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="listaOrdenable">
                    <?php foreach ($prioridades as $p): ?>
                        <tr draggable="true" data-id="<?= $p['id'] ?>" class="fila-arrastrable">
                            <td class="cursor-pointer text-muted"><i class="bi bi-grip-vertical"></i></td>
                            <td><?= limpiar($p['nombre']) ?></td>
                            <td><span class="badge bg-<?= limpiar($p['color']) ?>"><?= limpiar($p['nombre']) ?></span></td>
                            <td><span class="badge <?= (int)$p['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$p['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarPrioridad(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="cnf_soporte_prioridad.php?toggle=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de esta prioridad?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$prioridades): ?><tr><td colspan="5" class="text-center text-muted">No hay prioridades registradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarPrioridad(p) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Prioridad';
    document.getElementById('pr_id').value = p.id;
    document.getElementById('pr_nombre').value = p.nombre;
    document.getElementById('pr_color').value = p.color;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formPrioridad').reset();
    document.getElementById('pr_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-exclamation-diamond"></i> Nueva Prioridad';
    document.getElementById('btnCancelar').classList.add('d-none');
}
document.addEventListener('DOMContentLoaded', function () {
    inicializarListaOrdenable('listaOrdenable', 'reordenar');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
