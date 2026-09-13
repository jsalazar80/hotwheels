<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(11);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reordenar') {
    header('Content-Type: application/json');
    $ids = $_POST['ids'] ?? [];
    if (is_array($ids) && $ids) {
        $orden = 1;
        foreach ($ids as $idItem) {
            $stmt = $pdo->prepare("UPDATE tbl_soporte_categoria SET orden = ? WHERE id = ?");
            $stmt->execute([$orden, (int)$idItem]);
            $orden++;
        }
        registrarAuditoria($pdo, 'UPD', 'tbl_soporte_categoria', 0, '', 'Reordenamiento por arrastre: ' . implode(',', $ids));
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('cnf_soporte_categoria.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_soporte_categoria SET nombre=? WHERE id=?";
        $paramsUpd = [$nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_soporte_categoria', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de categoría de soporte');
        redirigirConMensaje('cnf_soporte_categoria.php', 'ok', 'Categoría actualizada.');
    } else {
        $siguienteOrden = (int)($pdo->query("SELECT COALESCE(MAX(orden),0)+1 t FROM tbl_soporte_categoria")->fetch()['t']);
        $sqlIns = "INSERT INTO tbl_soporte_categoria (nombre, orden, user_ing) VALUES (?,?,?)";
        $paramsIns = [$nombre, $siguienteOrden, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_soporte_categoria', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nueva categoría de soporte');
        redirigirConMensaje('cnf_soporte_categoria.php', 'ok', 'Categoría registrada.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_soporte_categoria SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_soporte_categoria', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) de la categoría de soporte');
    redirigirConMensaje('cnf_soporte_categoria.php', 'ok', 'Estado actualizado.');
}

$categorias = $pdo->query("SELECT * FROM tbl_soporte_categoria ORDER BY orden ASC, nombre ASC")->fetchAll();

$tituloPagina = 'Categorías de Soporte';
$paginaActiva = 'cnf_soporte_categoria';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-tags"></i> Nueva Categoría</h6>
            <form method="post" id="formCategoria">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="cat_id" value="0">
                <div class="mb-2">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="cat_nombre" class="form-control" required>
                </div>
                <input type="hidden" name="orden" id="cat_orden" value="0">
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Categorías Registradas <small class="text-muted fw-normal">(arrastra las filas para cambiar el orden)</small></h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th style="width:30px;"></th><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody id="listaOrdenable">
                    <?php foreach ($categorias as $c): ?>
                        <tr draggable="true" data-id="<?= $c['id'] ?>" class="fila-arrastrable">
                            <td class="cursor-pointer text-muted"><i class="bi bi-grip-vertical"></i></td>
                            <td><?= limpiar($c['nombre']) ?></td>
                            <td><span class="badge <?= (int)$c['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$c['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarCategoria(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="cnf_soporte_categoria.php?toggle=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de esta categoría?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$categorias): ?><tr><td colspan="4" class="text-center text-muted">No hay categorías registradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarCategoria(c) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Categoría';
    document.getElementById('cat_id').value = c.id;
    document.getElementById('cat_nombre').value = c.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formCategoria').reset();
    document.getElementById('cat_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-tags"></i> Nueva Categoría';
    document.getElementById('btnCancelar').classList.add('d-none');
}

document.addEventListener('DOMContentLoaded', function () {
    inicializarListaOrdenable('listaOrdenable', 'reordenar');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
