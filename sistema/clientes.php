<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(3);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $id_tbl_tipo_persona = (int)($_POST['id_tbl_tipo_persona'] ?? 0);
    $rucci = trim($_POST['rucci'] ?? '');
    $nombre_comercial = trim($_POST['nombre_comercial'] ?? '');
    $razon_social = trim($_POST['razon_social'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $valor_por_hora_ref = (float)($_POST['valor_por_hora_ref'] ?? 0);

    if ($id_tbl_tipo_persona === 2) {
        // Persona jurídica: la razón social es requerida
        if ($razon_social === '') {
            redirigirConMensaje('clientes.php', 'error', 'La razón social es obligatoria para personas jurídicas.');
        }
    } else {
        // Persona natural: no aplica razón social
        $razon_social = '';
    }

    if ($id_tbl_tipo_persona <= 0 || $nombre_comercial === '') {
        redirigirConMensaje('clientes.php', 'error', 'Tipo de persona y nombre son obligatorios.');
    }

    $digitosEsperados = ($id_tbl_tipo_persona === 2) ? 13 : 10;
    if (!preg_match('/^\d{' . $digitosEsperados . '}$/', $rucci)) {
        $etiqueta = ($id_tbl_tipo_persona === 2) ? 'RUC (13 dígitos)' : 'Cédula (10 dígitos)';
        redirigirConMensaje('clientes.php', 'error', "El campo $etiqueta no tiene el formato correcto.");
    }
    if (rucciExisteEnClientes($pdo, $rucci, $id)) {
        redirigirConMensaje('clientes.php', 'error', 'Ya existe otro cliente registrado con ese RUC/Cédula.');
    }

    if ($correo === '') {
        redirigirConMensaje('clientes.php', 'error', 'El correo electrónico es obligatorio.');
    }
    if (!validarFormatoCorreo($correo)) {
        redirigirConMensaje('clientes.php', 'error', 'El correo electrónico ingresado no tiene un formato válido.');
    }
    if (correoExisteEnClientes($pdo, $correo, $id)) {
        redirigirConMensaje('clientes.php', 'error', 'Ya existe otro cliente registrado con ese correo electrónico.');
    }

    if ($valor_por_hora_ref < 0) {
        redirigirConMensaje('clientes.php', 'error', 'El valor por hora referencial no puede ser menor a cero.');
    }

    try {
        if ($id > 0) {
            $sqlUpd = "UPDATE tbl_clientes SET id_tbl_tipo_persona=?, rucci=?, nombre_comercial=?, razon_social=?, direccion=?, telefono=?, celular=?, correo=?, valor_por_hora_ref=? WHERE id=?";
            $paramsUpd = [$id_tbl_tipo_persona, $rucci, $nombre_comercial, $razon_social ?: null, $direccion, $telefono, $celular, $correo, $valor_por_hora_ref, $id];
            $stmt = $pdo->prepare($sqlUpd);
            $stmt->execute($paramsUpd);
            registrarAuditoria($pdo, 'UPD', 'tbl_clientes', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de datos del cliente');
            redirigirConMensaje('clientes.php', 'ok', 'Cliente actualizado correctamente.');
        } else {
            $sqlIns = "INSERT INTO tbl_clientes (id_tbl_tipo_persona, rucci, nombre_comercial, razon_social, direccion, telefono, celular, correo, valor_por_hora_ref, user_ing) VALUES (?,?,?,?,?,?,?,?,?,?)";
            $paramsIns = [$id_tbl_tipo_persona, $rucci, $nombre_comercial, $razon_social ?: null, $direccion, $telefono, $celular, $correo, $valor_por_hora_ref, $_SESSION['tsp_usuario_id']];
            $stmt = $pdo->prepare($sqlIns);
            $stmt->execute($paramsIns);
            $nuevoId = (int)$pdo->lastInsertId();
            registrarAuditoria($pdo, 'INS', 'tbl_clientes', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo cliente');
            redirigirConMensaje('clientes.php', 'ok', 'Cliente registrado correctamente.');
        }
    } catch (PDOException $e) {
        $texto = str_contains($e->getMessage(), 'uk_rucci') ? 'Ya existe un cliente con ese RUC/Cédula.'
            : (str_contains($e->getMessage(), 'uk_correo') ? 'Ya existe un cliente con ese correo electrónico.' : 'Error al guardar: ' . $e->getMessage());
        redirigirConMensaje('clientes.php', 'error', $texto);
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_clientes SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_clientes', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del cliente');
    redirigirConMensaje('clientes.php', 'ok', 'Estado actualizado.');
}

$busqueda = trim($_GET['q'] ?? '');
$sql = "SELECT cl.*, tp.nombre tipo_persona_nombre,
        (SELECT COUNT(*) FROM tbl_soportes s WHERE s.id_tbl_clientes = cl.id) total_tickets
        FROM tbl_clientes cl
        JOIN tbl_tipo_persona tp ON tp.id = cl.id_tbl_tipo_persona
        WHERE 1=1";
$params = [];
if ($busqueda !== '') {
    $sql .= " AND (cl.nombre_comercial LIKE ? OR cl.razon_social LIKE ? OR cl.rucci LIKE ? OR cl.correo LIKE ?)";
    $params[] = "%$busqueda%"; $params[] = "%$busqueda%"; $params[] = "%$busqueda%"; $params[] = "%$busqueda%";
}
$sql .= " ORDER BY cl.nombre_comercial ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$tiposPersona = $pdo->query("SELECT * FROM tbl_tipo_persona WHERE state=1 ORDER BY id ASC")->fetchAll();

$tituloPagina = 'Clientes';
$paginaActiva = 'clientes';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-person-plus"></i> Nuevo Cliente</h6>
            <form method="post" id="formCliente">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="c_id" value="0">
                <div class="mb-2">
                    <label class="form-label">Tipo de persona</label>
                    <select class="form-select" name="id_tbl_tipo_persona" id="c_tipo_persona" onchange="cambiarTipoPersona()" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($tiposPersona as $tp): ?>
                            <option value="<?= $tp['id'] ?>"><?= limpiar($tp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label" id="lbl_rucci">Cédula/RUC</label>
                    <input type="text" name="rucci" id="c_rucci" class="form-control" maxlength="13" placeholder="10 o 13 dígitos" required>
                </div>
                <div class="mb-2">
                    <label class="form-label" id="lbl_nombre_comercial">Nombre comercial</label>
                    <input type="text" name="nombre_comercial" id="c_nombre_comercial" class="form-control" required>
                </div>
                <div class="mb-2 d-none" id="grupo_razon_social">
                    <label class="form-label">Razón social</label>
                    <input type="text" name="razon_social" id="c_razon_social" class="form-control">
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" id="c_telefono" class="form-control">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Celular</label>
                        <input type="text" name="celular" id="c_celular" class="form-control">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Correo</label>
                    <input type="email" name="correo" id="c_correo" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Valor por hora referencial ($)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="valor_por_hora_ref" id="c_valor_por_hora_ref" value="0.00">
                </div>
                <div class="mb-3">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="direccion" id="c_direccion" class="form-control">
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-people"></i> Listado de Clientes</h6>
            <form method="get" class="mb-3">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Buscar por nombre, RUC/cédula o correo..." value="<?= limpiar($busqueda) ?>">
                    <button class="btn btn-outline-tsp" type="submit"><i class="bi bi-search"></i></button>
                    <a href="clientes.php" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Nombre Comercial</th><th>Tipo</th><th>RUC/Cédula</th><th>Teléfono/Celular</th><th>Tickets</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($clientes as $cl): ?>
                        <tr>
                            <td>
                                <?= limpiar($cl['nombre_comercial']) ?>
                                <?php if ($cl['razon_social']): ?><div class="small text-muted"><?= limpiar($cl['razon_social']) ?></div><?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= limpiar($cl['tipo_persona_nombre']) ?></span></td>
                            <td><?= limpiar($cl['rucci']) ?></td>
                            <td><?= limpiar($cl['telefono']) ?><?= $cl['celular'] ? ' / ' . limpiar($cl['celular']) : '' ?></td>
                            <td><span class="badge bg-secondary"><?= (int)$cl['total_tickets'] ?></span></td>
                            <td><span class="badge <?= (int)$cl['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$cl['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarCliente(<?= json_encode($cl, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="clientes.php?toggle=<?= $cl['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este cliente?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$clientes): ?><tr><td colspan="7" class="text-center text-muted">No se encontraron clientes.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function cambiarTipoPersona() {
    const tipo = document.getElementById('c_tipo_persona').value;
    const grupoRazon = document.getElementById('grupo_razon_social');
    const lblRucci = document.getElementById('lbl_rucci');
    const lblNombreComercial = document.getElementById('lbl_nombre_comercial');
    const campoRucci = document.getElementById('c_rucci');
    if (tipo === '2') {
        grupoRazon.classList.remove('d-none');
        lblRucci.textContent = 'RUC';
        lblNombreComercial.textContent = 'Nombre comercial';
        campoRucci.maxLength = 13;
        campoRucci.dataset.digitos = '13';
    } else {
        grupoRazon.classList.add('d-none');
        document.getElementById('c_razon_social').value = '';
        lblRucci.textContent = 'Cédula';
        lblNombreComercial.textContent = 'Nombre';
        campoRucci.maxLength = 10;
        campoRucci.dataset.digitos = '10';
    }
}

function editarCliente(cl) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Cliente';
    document.getElementById('c_id').value = cl.id;
    document.getElementById('c_tipo_persona').value = cl.id_tbl_tipo_persona;
    document.getElementById('c_rucci').value = cl.rucci;
    document.getElementById('c_nombre_comercial').value = cl.nombre_comercial;
    document.getElementById('c_razon_social').value = cl.razon_social || '';
    document.getElementById('c_telefono').value = cl.telefono || '';
    document.getElementById('c_celular').value = cl.celular || '';
    document.getElementById('c_correo').value = cl.correo || '';
    document.getElementById('c_valor_por_hora_ref').value = parseFloat(cl.valor_por_hora_ref || 0).toFixed(2);
    document.getElementById('c_direccion').value = cl.direccion || '';
    cambiarTipoPersona();
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formCliente').reset();
    document.getElementById('c_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-person-plus"></i> Nuevo Cliente';
    document.getElementById('btnCancelar').classList.add('d-none');
    cambiarTipoPersona();
}
document.getElementById('formCliente').addEventListener('submit', function (e) {
    const tipo = document.getElementById('c_tipo_persona').value;
    const rucci = document.getElementById('c_rucci').value.trim();
    const digitosEsperados = tipo === '2' ? 13 : 10;
    const regex = new RegExp('^\\d{' + digitosEsperados + '}$');
    if (!regex.test(rucci)) {
        e.preventDefault();
        const etiqueta = tipo === '2' ? 'RUC (13 dígitos)' : 'Cédula (10 dígitos)';
        mostrarAviso('El campo ' + etiqueta + ' no tiene el formato correcto.');
        return;
    }
    const correo = document.getElementById('c_correo').value.trim();
    if (!correo) {
        e.preventDefault();
        mostrarAviso('El correo electrónico es obligatorio.');
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        e.preventDefault();
        mostrarAviso('El correo electrónico ingresado no tiene un formato válido.');
        return;
    }
    const valorPorHoraRef = parseFloat(document.getElementById('c_valor_por_hora_ref').value);
    if (isNaN(valorPorHoraRef) || valorPorHoraRef < 0) {
        e.preventDefault();
        mostrarAviso('El valor por hora referencial no puede ser menor a cero.');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
