<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso(30);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('fabricantes.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_hotwheels_fabricantes SET nombre=? WHERE id=?";
        $paramsUpd = [$nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_fabricantes', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de fabricante');
        redirigirConMensaje('fabricantes.php', 'ok', 'Fabricante actualizado.');
    } else {
        $sqlIns = "INSERT INTO tbl_hotwheels_fabricantes (nombre, user_ing, fecha_hora_ing) VALUES (?,?,NOW())";
        $paramsIns = [$nombre, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_hotwheels_fabricantes', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo fabricante');
        redirigirConMensaje('fabricantes.php', 'ok', 'Fabricante registrado.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_hotwheels_fabricantes SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_fabricantes', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del fabricante');
    redirigirConMensaje('fabricantes.php', 'ok', 'Estado actualizado.');
}

$fabricantes = $pdo->query("SELECT * FROM tbl_hotwheels_fabricantes ORDER BY nombre ASC")->fetchAll();

$tituloPagina = 'Fabricantes';
$paginaActiva = 'fabricantes';
include __DIR__ . '/../includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-building"></i> Nuevo Fabricante</h6>
            <form method="post" id="formFabricante">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="fab_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="fab_nombre" class="form-control" required>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Fabricantes Registrados</h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($fabricantes as $f): ?>
                        <tr>
                            <td><?= limpiar($f['nombre']) ?></td>
                            <td><span class="badge <?= (int)$f['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$f['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarFabricante(<?= json_encode($f, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="fabricantes.php?toggle=<?= $f['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este fabricante?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$fabricantes): ?><tr><td colspan="3" class="text-center text-muted">No hay fabricantes registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarFabricante(f) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Fabricante';
    document.getElementById('fab_id').value = f.id;
    document.getElementById('fab_nombre').value = f.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formFabricante').reset();
    document.getElementById('fab_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-building"></i> Nuevo Fabricante';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
