<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(17);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $id_tbl_bancos = (int)($_POST['id_tbl_bancos'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '' || $id_tbl_bancos <= 0) {
        redirigirConMensaje('cnf_cuentas_bancarias.php', 'error', 'Banco y número de cuenta son obligatorios.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_bancos_cuentas SET id_tbl_bancos=?, nombre=? WHERE id=?";
        $paramsUpd = [$id_tbl_bancos, $nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_bancos_cuentas', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de cuenta bancaria');
        redirigirConMensaje('cnf_cuentas_bancarias.php', 'ok', 'Cuenta bancaria actualizada.');
    } else {
        $sqlIns = "INSERT INTO tbl_bancos_cuentas (id_tbl_bancos, nombre, user_ing) VALUES (?,?,?)";
        $paramsIns = [$id_tbl_bancos, $nombre, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_bancos_cuentas', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nueva cuenta bancaria');
        redirigirConMensaje('cnf_cuentas_bancarias.php', 'ok', 'Cuenta bancaria registrada.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id === 1) {
        redirigirConMensaje('cnf_cuentas_bancarias.php', 'error', 'No se puede desactivar la cuenta "No Aplica".');
    }
    $sqlToggle = "UPDATE tbl_bancos_cuentas SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_bancos_cuentas', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) de la cuenta bancaria');
    redirigirConMensaje('cnf_cuentas_bancarias.php', 'ok', 'Estado actualizado.');
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT bc.*, b.nombre banco_nombre FROM tbl_bancos_cuentas bc
        JOIN tbl_bancos b ON b.id = bc.id_tbl_bancos
        WHERE 1=1";
$params = [];
if ($busqueda !== '') {
    $sql .= " AND (bc.nombre LIKE ? OR b.nombre LIKE ?)";
    $params[] = "%$busqueda%"; $params[] = "%$busqueda%";
}
$sql .= " ORDER BY b.nombre ASC, bc.nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cuentas = $stmt->fetchAll();

$bancos = $pdo->query("SELECT * FROM tbl_bancos WHERE state=1 ORDER BY (id=1) DESC, nombre ASC")->fetchAll();

$tituloPagina = 'Configurar Cuentas Bancarias';
$paginaActiva = 'cnf_cuentas_bancarias';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-wallet2"></i> Nueva Cuenta Bancaria</h6>
            <form method="post" id="formCuenta">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="cb_id" value="0">
                <div class="mb-2">
                    <label class="form-label">Banco</label>
                    <select class="form-select" name="id_tbl_bancos" id="cb_banco" required>
                        <?php foreach ($bancos as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= limpiar($b['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Número de cuenta</label>
                    <input type="text" name="nombre" id="cb_nombre" class="form-control" placeholder="Ej: 2201234567" required>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Cuentas Bancarias Registradas</h6>
            <form method="get" class="mb-3">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Buscar por banco o cuenta..." value="<?= limpiar($busqueda) ?>">
                    <button class="btn btn-outline-tsp" type="submit"><i class="bi bi-search"></i></button>
                    <a href="cnf_cuentas_bancarias.php" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
            <div class="table-responsive" style="max-height:520px; overflow-y:auto;">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Banco</th><th>N° de Cuenta</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($cuentas as $c): ?>
                        <tr>
                            <td><?= limpiar($c['banco_nombre']) ?></td>
                            <td><?= limpiar($c['nombre']) ?></td>
                            <td><span class="badge <?= (int)$c['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$c['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarCuenta(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <?php if ((int)$c['id'] !== 1): ?>
                                <a href="cnf_cuentas_bancarias.php?toggle=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de esta cuenta?')"><i class="bi bi-toggle2-on"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$cuentas): ?><tr><td colspan="4" class="text-center text-muted">No se encontraron cuentas bancarias.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarCuenta(c) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Cuenta Bancaria';
    document.getElementById('cb_id').value = c.id;
    document.getElementById('cb_banco').value = c.id_tbl_bancos;
    document.getElementById('cb_nombre').value = c.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formCuenta').reset();
    document.getElementById('cb_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-wallet2"></i> Nueva Cuenta Bancaria';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
