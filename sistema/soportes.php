<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(2);

$mensajeError = '';

// ---- Registrar nueva solicitud de soporte ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_soporte') {
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $solicitante = trim($_POST['solicitante'] ?? '');
    $fecha = trim($_POST['fecha'] ?? '') ?: date('Y-m-d H:i:s');
    $asunto = trim($_POST['asunto'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $id_tbl_soporte_categoria = (int)($_POST['id_tbl_soporte_categoria'] ?? 0);
    $id_tbl_soporte_tipo = (int)($_POST['id_tbl_soporte_tipo'] ?? 0);
    $id_tbl_soporte_prioridad = (int)($_POST['id_tbl_soporte_prioridad'] ?? 0);
    $id_tbl_admin_user_asignado = (int)($_POST['id_tbl_admin_user_asignado'] ?? 0);
    $valor_por_hora = (float)($_POST['valor_por_hora'] ?? 0);

    try {
        $pdo->beginTransaction();

        if ($cliente_id === 0) throw new Exception('Debe seleccionar un cliente.');
        if ($asunto === '') throw new Exception('El asunto de la solicitud es obligatorio.');
        if ($id_tbl_soporte_prioridad <= 0) throw new Exception('Seleccione la prioridad de la solicitud.');

        [$numero, $siguiente] = generarNumeroTicket($pdo);

        $sqlIns = "INSERT INTO tbl_soportes
            (numero, fecha, id_tbl_clientes, solicitante, asunto, descripcion, id_tbl_soporte_categoria, id_tbl_soporte_tipo, id_tbl_soporte_prioridad, id_tbl_admin_user_asignado, valor_por_hora, state, user_ing)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $paramsIns = [
            $numero, $fecha, $cliente_id, $solicitante, $asunto, $descripcion,
            $id_tbl_soporte_categoria ?: null, $id_tbl_soporte_tipo ?: null, $id_tbl_soporte_prioridad,
            $id_tbl_admin_user_asignado ?: null, $valor_por_hora, ESTADO_SOPORTE_REPORTADO, $_SESSION['tsp_usuario_id'],
        ];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $soporteId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_soportes', $soporteId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nueva solicitud de soporte');

        incrementarNumeroTicket($pdo, $siguiente);

        // ---- Subida de archivos (imágenes y video) ----
        procesarArchivosAdjuntosSoporte($pdo, $soporteId, $_SESSION['tsp_usuario_id']);

        $pdo->commit();
        header("Location: soporte_detalle.php?id=$soporteId&msg_tipo=ok&msg=" . urlencode("Solicitud N° $numero registrada correctamente."));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensajeError = $e->getMessage();
    }
}

$clientes = $pdo->query("SELECT * FROM tbl_clientes WHERE state=1 ORDER BY nombre_comercial ASC")->fetchAll();
$categorias = $pdo->query("SELECT * FROM tbl_soporte_categoria WHERE state=1 ORDER BY orden ASC")->fetchAll();
$tipos = $pdo->query("SELECT * FROM tbl_soporte_tipo WHERE state=1 ORDER BY orden ASC")->fetchAll();
$prioridades = $pdo->query("SELECT * FROM tbl_soporte_prioridad WHERE state=1 ORDER BY orden ASC")->fetchAll();
$tecnicos = $pdo->query("SELECT * FROM tbl_admin_user WHERE state=1 ORDER BY nombre ASC")->fetchAll();
[$numeroPreview, ] = generarNumeroTicket($pdo);

// ---- Filtros del listado ----
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$filtroEstado = $_GET['estado'] ?? '';
$filtroPrioridad = (int)($_GET['prioridad'] ?? 0);
$filtroCliente = (int)($_GET['cliente'] ?? 0);
$q = trim($_GET['q'] ?? '');

$sql = "SELECT s.*, cl.nombre_comercial cliente, cl.correo cliente_correo, e.nombre estado_nombre, e.color estado_color, e.icono estado_icono,
        p.nombre prioridad_nombre, p.color prioridad_color, u.nombre tecnico_nombre,
        (SELECT COUNT(*) FROM tbl_email_send es WHERE es.type_email = 1 AND es.table_id = s.id AND es.state = 1) correos_enviados
        FROM tbl_soportes s
        JOIN tbl_clientes cl ON cl.id = s.id_tbl_clientes
        JOIN tbl_soporte_estado e ON e.id = s.state
        JOIN tbl_soporte_prioridad p ON p.id = s.id_tbl_soporte_prioridad
        LEFT JOIN tbl_admin_user u ON u.id = s.id_tbl_admin_user_asignado
        WHERE DATE(s.fecha) BETWEEN ? AND ?";
$params = [$desde, $hasta];
if ($filtroEstado !== '') { $sql .= " AND s.state = ?"; $params[] = (int)$filtroEstado; }
if ($filtroPrioridad > 0) { $sql .= " AND s.id_tbl_soporte_prioridad = ?"; $params[] = $filtroPrioridad; }
if ($filtroCliente > 0) { $sql .= " AND s.id_tbl_clientes = ?"; $params[] = $filtroCliente; }
if ($q !== '') { $sql .= " AND (s.numero LIKE ? OR s.asunto LIKE ? OR cl.nombre_comercial LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql .= " ORDER BY s.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll();

$estados = $pdo->query("SELECT * FROM tbl_soporte_estado WHERE state=1 ORDER BY id ASC")->fetchAll();

$tituloPagina = 'Soportes';
$paginaActiva = 'soportes';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>

<?php if ($mensajeError): ?>
<div class="alert alert-danger"><?= limpiar($mensajeError) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="formNuevoSoporte">
<input type="hidden" name="accion" value="guardar_soporte">

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-person"></i> Cliente y Solicitante</h6>
            <div class="mb-2">
                <label class="form-label">Cliente</label>
                <select class="form-select" name="cliente_id" id="soporte_cliente_id" onchange="cargarValorPorHoraRef()" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>" data-valor-hora-ref="<?= number_format((float)$cl['valor_por_hora_ref'], 2, '.', '') ?>">
                            <?= limpiar($cl['nombre_comercial']) ?><?= $cl['razon_social'] ? ' - ' . limpiar($cl['razon_social']) : '' ?> (<?= limpiar($cl['rucci']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted"><i class="bi bi-info-circle"></i> Si el cliente no existe, regístrelo primero en el módulo <a href="clientes.php">Clientes</a>.</small>
            </div>
            <div class="mb-2">
                <label class="form-label">Solicitante <small class="text-muted">(persona que reporta)</small></label>
                <input type="text" class="form-control" name="solicitante" id="soporte_solicitante" placeholder="Nombre de quien solicita el soporte">
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-life-preserver"></i> Solicitud de Soporte</h6>
            <div class="row">
                <div class="col-6 mb-2">
                    <label class="form-label">N° Ticket</label>
                    <input type="text" class="form-control" value="<?= limpiar($numeroPreview) ?>" disabled>
                </div>
                <div class="col-6 mb-2">
                    <label class="form-label">Fecha</label>
                    <input type="datetime-local" class="form-control" name="fecha" id="soporte_fecha" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Asunto</label>
                <input type="text" class="form-control" name="asunto" id="soporte_asunto" placeholder="Ej: No enciende el equipo" required>
            </div>
            <div class="row">
                <div class="col-6 mb-2">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="id_tbl_soporte_categoria" id="soporte_categoria">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= limpiar($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 mb-2">
                    <label class="form-label">Tipo de atención</label>
                    <select class="form-select" name="id_tbl_soporte_tipo" id="soporte_tipo">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= limpiar($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-6 mb-2">
                    <label class="form-label">Prioridad</label>
                    <select class="form-select" name="id_tbl_soporte_prioridad" id="soporte_prioridad" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($prioridades as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= limpiar($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 mb-2">
                    <label class="form-label">Técnico asignado <small class="text-muted">(opcional)</small></label>
                    <select class="form-select" name="id_tbl_admin_user_asignado" id="soporte_tecnico">
                        <option value="">-- Sin asignar --</option>
                        <?php foreach ($tecnicos as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= limpiar($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label">Valor por hora ($) <small class="text-muted">(para el cálculo del monto)</small></label>
                <input type="number" step="0.01" min="0" class="form-control" name="valor_por_hora" id="soporte_valor_por_hora" value="0.00">
            </div>
        </div>
    </div>
</div>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-card-text"></i> Detalle</h6>
    <div class="mb-2">
        <label class="form-label">Descripción del problema</label>
        <textarea class="form-control" name="descripcion" id="soporte_descripcion" rows="3" placeholder="Describa el problema reportado por el cliente..."></textarea>
    </div>
    <div class="mb-2">
        <label class="form-label">Archivos adjuntos <small class="text-muted">(opcional)</small></label>
        <div class="dropzone" id="dropzoneArchivos">
            <i class="bi bi-cloud-arrow-up"></i>
            Arrastra archivos aquí o haz clic para seleccionar
            <div class="small mt-1">Formatos permitidos: PNG, JPG, MP4</div>
        </div>
        <input type="file" class="d-none" id="inputArchivos" name="archivos[]" multiple accept=".png,.jpg,.jpeg,.mp4">
        <div id="listaArchivosSeleccionados" class="mt-2"></div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <button type="submit" class="btn btn-tsp px-4"><i class="bi bi-save"></i> Registrar Solicitud</button>
</div>
</form>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-funnel"></i> Filtros</h6>
    <form method="get" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Del</label>
            <input type="date" name="desde" class="form-control form-control-sm" value="<?= limpiar($desde) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Al</label>
            <input type="date" name="hasta" class="form-control form-control-sm" value="<?= limpiar($hasta) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $filtroEstado===(string)$e['id']?'selected':'' ?>><?= limpiar($e['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Prioridad</label>
            <select name="prioridad" class="form-select form-select-sm">
                <option value="0">Todas</option>
                <?php foreach ($prioridades as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filtroPrioridad==$p['id']?'selected':'' ?>><?= limpiar($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small mb-0">Buscar N°/asunto/cliente</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?= limpiar($q) ?>">
        </div>
        <div class="col-6 col-md-1">
            <button class="btn btn-tsp btn-sm w-100" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-list-ul"></i> Solicitudes (<?= count($solicitudes) ?>)</h6>
    <div class="table-responsive">
        <table class="table table-sm table-tsp align-middle">
            <thead><tr><th>N°</th><th>Fecha</th><th>Cliente</th><th>Asunto</th><th>Prioridad</th><th>Estado</th><th>Técnico</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td><?= limpiar($s['numero']) ?></td>
                    <td><?= formatoFecha($s['fecha']) ?></td>
                    <td><?= limpiar($s['cliente']) ?></td>
                    <td><?= limpiar($s['asunto']) ?></td>
                    <td><span class="badge bg-<?= limpiar($s['prioridad_color']) ?>"><?= limpiar($s['prioridad_nombre']) ?></span></td>
                    <td><span class="badge" style="background-color:<?= limpiar($s['estado_color']) ?>;"><i class="bi <?= limpiar($s['estado_icono']) ?>"></i> <?= limpiar($s['estado_nombre']) ?></span></td>
                    <td><?= limpiar($s['tecnico_nombre'] ?: '-') ?></td>
                    <td class="text-nowrap">
                        <a href="soporte_detalle.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-tsp"><i class="bi bi-eye"></i> Ver</a>
                        <button type="button" class="btn btn-sm btn-outline-tsp" title="Clonar solicitud" onclick='clonarSoporte(<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-copy"></i></button>
                        <?php if ((int)$s['state'] === ESTADO_SOPORTE_FINALIZADO): ?>
                            <a href="soporte_pdf.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-outline-tsp" title="Exportar PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                            <?php if (!empty($s['cliente_correo'])): ?>
                                <?php $correosEnviados = (int)$s['correos_enviados']; ?>
                                <?php if ($correosEnviados > 0): ?>
                                    <a href="soporte_email.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-success" title="Ya se envió <?= $correosEnviados ?> vez(veces). Enviar de nuevo a <?= limpiar($s['cliente_correo']) ?>" onclick="return confirmarAccion('¿Enviar el reporte PDF de este soporte por correo a <?= limpiar($s['cliente_correo']) ?>?')"><i class="bi bi-envelope-check"></i> <?= $correosEnviados ?></a>
                                <?php else: ?>
                                    <a href="soporte_email.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-tsp" title="Enviar PDF por correo" onclick="return confirmarAccion('¿Enviar el reporte PDF de este soporte por correo a <?= limpiar($s['cliente_correo']) ?>?')"><i class="bi bi-envelope"></i></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary disabled" title="El cliente no tiene correo registrado"><i class="bi bi-envelope"></i></button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Solo disponible cuando la solicitud está Finalizada"><i class="bi bi-file-earmark-pdf"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Solo disponible cuando la solicitud está Finalizada"><i class="bi bi-envelope"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$solicitudes): ?><tr><td colspan="8" class="text-center text-muted">No se encontraron solicitudes con los filtros aplicados.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    inicializarDropzone('dropzoneArchivos', 'inputArchivos', 'listaArchivosSeleccionados', ['png','jpg','jpeg','mp4']);
});

function cargarValorPorHoraRef() {
    const sel = document.getElementById('soporte_cliente_id');
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('soporte_valor_por_hora').value = parseFloat(opt.dataset.valorHoraRef || 0).toFixed(2);
    }
}

function formatoDatetimeLocal(fecha) {
    const pad = n => String(n).padStart(2, '0');
    return fecha.getFullYear() + '-' + pad(fecha.getMonth() + 1) + '-' + pad(fecha.getDate()) + 'T' + pad(fecha.getHours()) + ':' + pad(fecha.getMinutes());
}

function clonarSoporte(s) {
    document.getElementById('soporte_cliente_id').value = s.id_tbl_clientes;
    cargarValorPorHoraRef();
    document.getElementById('soporte_solicitante').value = s.solicitante || '';
    document.getElementById('soporte_fecha').value = formatoDatetimeLocal(new Date());
    document.getElementById('soporte_asunto').value = s.asunto || '';
    document.getElementById('soporte_descripcion').value = s.descripcion || '';
    document.getElementById('soporte_categoria').value = s.id_tbl_soporte_categoria || '';
    document.getElementById('soporte_tipo').value = s.id_tbl_soporte_tipo || '';
    document.getElementById('soporte_prioridad').value = s.id_tbl_soporte_prioridad || '';
    document.getElementById('soporte_tecnico').value = s.id_tbl_admin_user_asignado || '';
    document.getElementById('soporte_valor_por_hora').value = parseFloat(s.valor_por_hora || 0).toFixed(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    mostrarAviso('Datos del ticket ' + s.numero + ' copiados en "Registrar Solicitud". Revise los datos y guarde para crear la nueva solicitud.', 'success');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
