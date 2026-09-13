<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(16);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('cnf_bancos.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_bancos SET nombre=? WHERE id=?";
        $paramsUpd = [$nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_bancos', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de banco');
        redirigirConMensaje('cnf_bancos.php', 'ok', 'Banco actualizado.');
    } else {
        $sqlIns = "INSERT INTO tbl_bancos (nombre, user_ing) VALUES (?,?)";
        $paramsIns = [$nombre, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_bancos', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo banco');
        redirigirConMensaje('cnf_bancos.php', 'ok', 'Banco registrado.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id === 1) {
        redirigirConMensaje('cnf_bancos.php', 'error', 'No se puede desactivar el registro "No Aplica".');
    }
    $sqlToggle = "UPDATE tbl_bancos SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_bancos', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del banco');
    redirigirConMensaje('cnf_bancos.php', 'ok', 'Estado actualizado.');
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM tbl_bancos WHERE 1=1";
$params = [];
if ($busqueda !== '') { $sql .= " AND nombre LIKE ?"; $params[] = "%$busqueda%"; }
$sql .= " ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bancos = $stmt->fetchAll();

$tituloPagina = 'Configurar Bancos';
$paginaActiva = 'cnf_bancos';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-bank"></i> Nuevo Banco</h6>
            <form method="post" id="formBanco">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="b_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="b_nombre" class="form-control" placeholder="Ej: Banco Pichincha" required>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Bancos Registrados</h6>
            <form method="get" class="mb-3">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Buscar banco..." value="<?= limpiar($busqueda) ?>">
                    <button class="btn btn-outline-tsp" type="submit"><i class="bi bi-search"></i></button>
                    <a href="cnf_bancos.php" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
            <div class="table-responsive" style="max-height:520px; overflow-y:auto;">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($bancos as $b): ?>
                        <tr>
                            <td><?= limpiar($b['nombre']) ?></td>
                            <td><span class="badge <?= (int)$b['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$b['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarBanco(<?= json_encode($b, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <?php if ((int)$b['id'] !== 1): ?>
                                <a href="cnf_bancos.php?toggle=<?= $b['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este banco?')"><i class="bi bi-toggle2-on"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$bancos): ?><tr><td colspan="3" class="text-center text-muted">No se encontraron bancos.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarBanco(b) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Banco';
    document.getElementById('b_id').value = b.id;
    document.getElementById('b_nombre').value = b.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formBanco').reset();
    document.getElementById('b_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-bank"></i> Nuevo Banco';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
