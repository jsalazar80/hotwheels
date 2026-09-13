<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(2);

$id = (int)($_GET['id'] ?? 0);
$carpetaArchivos = __DIR__ . '/files/soportes';

// ---- Actualizar datos del ticket ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar_soporte') {
    $id_tbl_soporte_categoria = (int)($_POST['id_tbl_soporte_categoria'] ?? 0);
    $id_tbl_soporte_tipo = (int)($_POST['id_tbl_soporte_tipo'] ?? 0);
    $id_tbl_soporte_prioridad = (int)($_POST['id_tbl_soporte_prioridad'] ?? 0);
    $id_tbl_admin_user_asignado = (int)($_POST['id_tbl_admin_user_asignado'] ?? 0);
    $nuevoEstado = (int)($_POST['state'] ?? 1);
    $id_tbl_clientes = (int)($_POST['id_tbl_clientes'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $solicitante = trim($_POST['solicitante'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $analisis = trim($_POST['analisis'] ?? '');
    $solucion = trim($_POST['solucion'] ?? '');
    $observacion = trim($_POST['observacion'] ?? '');
    $recomendacion = trim($_POST['recomendacion'] ?? '');
    $fecha_hora_solved_str = trim($_POST['fecha_hora_solved_str'] ?? '');
    $fecha_hora_solved_end = trim($_POST['fecha_hora_solved_end'] ?? '');
    $valor_por_hora = (float)($_POST['valor_por_hora'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmtActual = $pdo->prepare("SELECT * FROM tbl_soportes WHERE id = ? FOR UPDATE");
        $stmtActual->execute([$id]);
        $actual = $stmtActual->fetch();
        if (!$actual) throw new Exception('La solicitud no existe.');

        if ($fecha === '') throw new Exception('La fecha de la solicitud es obligatoria.');
        if ($asunto === '') throw new Exception('El asunto de la solicitud es obligatorio.');
        if ($id_tbl_clientes <= 0) throw new Exception('Debe seleccionar un cliente.');

        $tsFecha = strtotime($fecha);
        $tsInicio = $fecha_hora_solved_str !== '' ? strtotime($fecha_hora_solved_str) : null;
        $tsFin = $fecha_hora_solved_end !== '' ? strtotime($fecha_hora_solved_end) : null;

        if ($tsInicio !== null && $tsInicio < $tsFecha) {
            throw new Exception('El inicio de atención no puede ser anterior a la fecha de la solicitud.');
        }
        if ($tsFin !== null && $tsFin < $tsFecha) {
            throw new Exception('El fin de atención no puede ser anterior a la fecha de la solicitud.');
        }
        if ($tsInicio !== null && $tsFin !== null && $tsFin < $tsInicio) {
            throw new Exception('El fin de atención no puede ser anterior al inicio de atención.');
        }
        if ($nuevoEstado === ESTADO_SOPORTE_FINALIZADO && ($tsInicio === null || $tsFin === null)) {
            throw new Exception('Para marcar la solicitud como Finalizado debe registrar el inicio y el fin de atención.');
        }

        $cambioEstado = (int)$actual['state'] !== $nuevoEstado;

        $fechaCierre = $actual['fecha_cierre'];
        if (in_array($nuevoEstado, [ESTADO_SOPORTE_SOLUCIONADO, ESTADO_SOPORTE_FINALIZADO], true) && !$fechaCierre) {
            $fechaCierre = date('Y-m-d H:i:s');
        } elseif (in_array($nuevoEstado, [ESTADO_SOPORTE_REPORTADO, ESTADO_SOPORTE_EN_PROCESO], true)) {
            $fechaCierre = null; // Se reabrió el ticket
        }

        $sqlUpd = "UPDATE tbl_soportes SET
            id_tbl_clientes=?, fecha=?, asunto=?, id_tbl_soporte_categoria=?, id_tbl_soporte_tipo=?, id_tbl_soporte_prioridad=?, id_tbl_admin_user_asignado=?,
            solicitante=?, descripcion=?, analisis=?, solucion=?, observacion=?, recomendacion=?,
            fecha_hora_solved_str=?, fecha_hora_solved_end=?, valor_por_hora=?, fecha_cierre=?, state=?
            WHERE id=?";
        $paramsUpd = [
            $id_tbl_clientes, $fecha, $asunto, $id_tbl_soporte_categoria ?: null, $id_tbl_soporte_tipo ?: null, $id_tbl_soporte_prioridad, $id_tbl_admin_user_asignado ?: null,
            $solicitante, $descripcion, $analisis, $solucion, $observacion, $recomendacion,
            $fecha_hora_solved_str ?: null, $fecha_hora_solved_end ?: null, $valor_por_hora, $fechaCierre, $nuevoEstado,
            $id,
        ];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_soportes', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de la solicitud de soporte');

        recalcularTiempoSoporte($pdo, $id, 'Recálculo de tiempo y costo tras actualizar el soporte');
        recalcularSaldoCliente($pdo, (int)$actual['id_tbl_clientes'], 'Recálculo de saldo del cliente tras actualizar el soporte');
        if ((int)$actual['id_tbl_clientes'] !== $id_tbl_clientes) {
            recalcularSaldoCliente($pdo, $id_tbl_clientes, 'Recálculo de saldo del nuevo cliente tras reasignar el soporte');
        }

        if ($comentario !== '' || $cambioEstado) {
            $stmtEstadoNuevo = $pdo->prepare("SELECT nombre FROM tbl_soporte_estado WHERE id = ?");
            $stmtEstadoNuevo->execute([$nuevoEstado]);
            $nombreEstadoNuevo = $stmtEstadoNuevo->fetch()['nombre'] ?? '';
            $textoComentario = $comentario !== '' ? $comentario : 'Cambio de estado a "' . $nombreEstadoNuevo . '".';
            $sqlIns = "INSERT INTO tbl_soportes_seguimiento (id_tbl_soportes, comentario, id_tbl_soporte_estado, user_ing) VALUES (?,?,?,?)";
            $paramsIns = [$id, $textoComentario, $cambioEstado ? $nuevoEstado : null, $_SESSION['tsp_usuario_id']];
            $ins = $pdo->prepare($sqlIns);
            $ins->execute($paramsIns);
            registrarAuditoria($pdo, 'INS', 'tbl_soportes_seguimiento', (int)$pdo->lastInsertId(), interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de seguimiento/comentario de la solicitud de soporte');
        }

        // ---- Subida de nuevos archivos ----
        procesarArchivosAdjuntosSoporte($pdo, $id, $_SESSION['tsp_usuario_id']);

        $pdo->commit();
        redirigirConMensaje("soporte_detalle.php?id=$id", 'ok', 'Solicitud actualizada correctamente.');
    } catch (Exception $e) {
        $pdo->rollBack();
        redirigirConMensaje("soporte_detalle.php?id=$id", 'error', $e->getMessage());
    }
}

// ---- Eliminar un archivo adjunto ----
if (isset($_GET['eliminar_archivo'])) {
    $idArchivo = (int)$_GET['eliminar_archivo'];
    $stmtArch = $pdo->prepare("SELECT * FROM tbl_soportes_archivos WHERE id = ?");
    $stmtArch->execute([$idArchivo]);
    $archivo = $stmtArch->fetch();
    if ($archivo && (int)$archivo['id_tbl_soportes'] === $id) {
        $sqlDel = "UPDATE tbl_soportes_archivos SET state=0 WHERE id=?";
        $paramsDel = [$idArchivo];
        $pdo->prepare($sqlDel)->execute($paramsDel);
        registrarAuditoria($pdo, 'UPD', 'tbl_soportes_archivos', $idArchivo, interpolarSql($pdo, $sqlDel, $paramsDel), 'Eliminación de archivo adjunto de la solicitud de soporte');

        $carpetaSoporte = $carpetaArchivos . '/folder_' . $id;
        $rutaNueva = $carpetaSoporte . '/' . $archivo['archivo'];
        if (file_exists($rutaNueva)) {
            @unlink($rutaNueva);
            $rutaMiniatura = $carpetaSoporte . '/thumbnail/s_' . pathinfo($archivo['archivo'], PATHINFO_FILENAME) . '.jpg';
            if (file_exists($rutaMiniatura)) @unlink($rutaMiniatura);
        } else {
            $rutaPlana = $carpetaArchivos . '/' . $archivo['archivo'];
            if (file_exists($rutaPlana)) @unlink($rutaPlana);
        }
    }
    redirigirConMensaje("soporte_detalle.php?id=$id", 'ok', 'Archivo eliminado.');
}

$stmt = $pdo->prepare("SELECT s.*, cl.nombre_comercial cliente, cl.razon_social cliente_razon_social, cl.rucci cliente_rucci,
    cl.telefono cliente_telefono, cl.celular cliente_celular, cl.correo cliente_correo, cl.direccion cliente_direccion,
    e.nombre estado_nombre, e.color estado_color, e.icono estado_icono
    FROM tbl_soportes s
    JOIN tbl_clientes cl ON cl.id = s.id_tbl_clientes
    JOIN tbl_soporte_estado e ON e.id = s.state
    WHERE s.id = ?");
$stmt->execute([$id]);
$soporte = $stmt->fetch();

if (!$soporte) {
    redirigirConMensaje('soportes.php', 'error', 'La solicitud no existe.');
}

$categorias = $pdo->query("SELECT * FROM tbl_soporte_categoria WHERE state=1 ORDER BY orden ASC")->fetchAll();
$tipos = $pdo->query("SELECT * FROM tbl_soporte_tipo WHERE state=1 ORDER BY orden ASC")->fetchAll();
$prioridades = $pdo->query("SELECT * FROM tbl_soporte_prioridad WHERE state=1 ORDER BY orden ASC")->fetchAll();
$estados = $pdo->query("SELECT * FROM tbl_soporte_estado WHERE state=1 ORDER BY id ASC")->fetchAll();
$tecnicos = $pdo->query("SELECT * FROM tbl_admin_user WHERE state=1 ORDER BY nombre ASC")->fetchAll();
$clientes = $pdo->query("SELECT id, nombre_comercial, razon_social, rucci FROM tbl_clientes WHERE state=1 ORDER BY nombre_comercial ASC")->fetchAll();

$stmtArchivos = $pdo->prepare("SELECT * FROM tbl_soportes_archivos WHERE id_tbl_soportes = ? AND state=1 ORDER BY id DESC");
$stmtArchivos->execute([$id]);
$archivos = $stmtArchivos->fetchAll();

$stmtSeguimiento = $pdo->prepare("SELECT sg.*, u.nombre usuario_nombre, e.nombre estado_nombre, e.color estado_color
    FROM tbl_soportes_seguimiento sg
    LEFT JOIN tbl_admin_user u ON u.id = sg.user_ing
    LEFT JOIN tbl_soporte_estado e ON e.id = sg.id_tbl_soporte_estado
    WHERE sg.id_tbl_soportes = ? AND sg.state=1 ORDER BY sg.id DESC");
$stmtSeguimiento->execute([$id]);
$seguimientos = $stmtSeguimiento->fetchAll();

$tituloPagina = 'Solicitud ' . $soporte['numero'];
$paginaActiva = 'soportes';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="soportes.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver a Soportes</a>
    <?php if ((int)$soporte['state'] === ESTADO_SOPORTE_FINALIZADO): ?>
        <a href="soporte_pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-tsp"><i class="bi bi-file-earmark-pdf"></i> Generar PDF</a>
    <?php else: ?>
        <button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Solo disponible cuando la solicitud está Finalizada"><i class="bi bi-file-earmark-pdf"></i> Generar PDF</button>
    <?php endif; ?>
</div>

<form method="post" enctype="multipart/form-data" id="formSoporte">
<input type="hidden" name="accion" value="actualizar_soporte">
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-panel">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div class="flex-grow-1">
                    <h6 class="panel-title border-0 pb-0 mb-1"><i class="bi bi-life-preserver"></i> Ticket N° <?= limpiar($soporte['numero']) ?></h6>
                    <input type="text" class="form-control fs-5 fw-bold" name="asunto" id="soporte_asunto" value="<?= limpiar($soporte['asunto']) ?>" placeholder="Asunto de la solicitud" required>
                </div>
                <span class="badge" style="background-color:<?= limpiar($soporte['estado_color']) ?>; font-size:0.9rem;">
                    <i class="bi <?= limpiar($soporte['estado_icono']) ?>"></i> <?= limpiar($soporte['estado_nombre']) ?>
                </span>
            </div>
            <hr>
            <div class="mb-2">
                <label class="form-label">Fecha</label>
                <input type="datetime-local" class="form-control" name="fecha" id="soporte_fecha" value="<?= date('Y-m-d\TH:i', strtotime($soporte['fecha'])) ?>" required>
            </div>
            <?php if ($soporte['fecha_cierre']): ?>
                <p class="mb-2 text-muted small"><i class="bi bi-check-circle"></i> Cerrado el <?= formatoFechaHora($soporte['fecha_cierre']) ?></p>
            <?php endif; ?>

            <div class="mb-2">
                <label class="form-label">Solicitante</label>
                <input type="text" class="form-control" name="solicitante" value="<?= limpiar($soporte['solicitante']) ?>">
            </div>
            <div class="mb-2">
                <label class="form-label">Descripción</label>
                <textarea class="form-control" name="descripcion" rows="3"><?= limpiar($soporte['descripcion']) ?></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label">Análisis</label>
                <textarea class="form-control" name="analisis" rows="3" placeholder="Análisis técnico del problema..."><?= limpiar($soporte['analisis']) ?></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label">Solución</label>
                <textarea class="form-control" name="solucion" rows="3" placeholder="Solución aplicada..."><?= limpiar($soporte['solucion']) ?></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label">Observación</label>
                <textarea class="form-control" name="observacion" rows="2"><?= limpiar($soporte['observacion']) ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Recomendación</label>
                <textarea class="form-control" name="recomendacion" rows="2"><?= limpiar($soporte['recomendacion']) ?></textarea>
            </div>

            <hr>
            <h6 class="panel-title" style="border:none;"><i class="bi bi-stopwatch"></i> Tiempo y Costo de Atención</h6>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Inicio de atención</label>
                    <input type="datetime-local" class="form-control" name="fecha_hora_solved_str" id="soporte_solved_str" value="<?= $soporte['fecha_hora_solved_str'] ? date('Y-m-d\TH:i', strtotime($soporte['fecha_hora_solved_str'])) : '' ?>">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Fin de atención</label>
                    <input type="datetime-local" class="form-control" name="fecha_hora_solved_end" id="soporte_solved_end" value="<?= $soporte['fecha_hora_solved_end'] ? date('Y-m-d\TH:i', strtotime($soporte['fecha_hora_solved_end'])) : '' ?>">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Valor por hora ($)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="valor_por_hora" id="soporte_valor_hora" value="<?= number_format((float)$soporte['valor_por_hora'], 2, '.', '') ?>">
                </div>
            </div>
            <div class="row text-center mb-3">
                <div class="col-6">
                    <div class="text-muted small">Tiempo total</div>
                    <div class="fw-bold text-tsp" id="soporte_tiempo_total"><?= (int)$soporte['total_minutos'] ?> min</div>
                </div>
                <div class="col-6">
                    <div class="text-muted small">Monto total</div>
                    <div class="fw-bold text-tsp" id="soporte_monto_total">$<?= number_format((float)$soporte['monto_total'], 2) ?></div>
                </div>
            </div>

            <hr>
            <h6 class="panel-title" style="border:none;"><i class="bi bi-paperclip"></i> Archivos adjuntos</h6>
            <div class="mb-2">
                <div class="dropzone" id="dropzoneArchivos">
                    <i class="bi bi-cloud-arrow-up"></i>
                    Arrastra archivos aquí o haz clic para seleccionar
                    <div class="small mt-1">Formatos permitidos: PNG, JPG, MP4</div>
                </div>
                <input type="file" class="d-none" id="inputArchivos" name="archivos[]" multiple accept=".png,.jpg,.jpeg,.mp4">
                <div id="listaArchivosSeleccionados" class="mt-2"></div>
            </div>
        </div>

        <?php if ($archivos): ?>
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-images"></i> Archivos Adjuntos</h6>
            <div class="row g-2">
                <?php foreach ($archivos as $a): ?>
                    <?php
                        $rutaArchivo = resolverRutaArchivoSoporte($id, $a['archivo']);
                        $rutaMiniatura = resolverRutaMiniaturaSoporte($id, $a['archivo']);
                    ?>
                    <div class="col-6 col-md-4">
                        <div class="border rounded p-2 text-center position-relative">
                            <?php if ($a['tipo_archivo'] === 'video'): ?>
                                <video src="<?= limpiar($rutaArchivo) ?>" <?= $rutaMiniatura ? 'poster="' . limpiar($rutaMiniatura) . '"' : '' ?> controls style="width:100%; max-height:120px;"></video>
                            <?php elseif ($a['tipo_archivo'] === 'documento'): ?>
                                <a href="<?= limpiar($rutaArchivo) ?>" target="_blank" class="d-flex flex-column align-items-center py-3 text-decoration-none">
                                    <i class="bi bi-file-earmark-pdf text-danger" style="font-size:2.5rem;"></i>
                                    <small class="text-truncate w-100"><?= limpiar($a['nombre_original']) ?></small>
                                </a>
                            <?php else: ?>
                                <a href="<?= limpiar($rutaArchivo) ?>" target="_blank">
                                    <img src="<?= limpiar($rutaMiniatura ?: $rutaArchivo) ?>" style="width:100%; max-height:120px; object-fit:cover;">
                                </a>
                            <?php endif; ?>
                            <a href="soporte_detalle.php?id=<?= $id ?>&eliminar_archivo=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger mt-1 w-100" onclick="return confirmarAccion('¿Eliminar este archivo?')"><i class="bi bi-trash"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-clock-history"></i> Bitácora de Seguimiento</h6>
            <?php if (!$seguimientos): ?>
                <p class="text-muted text-center mb-0">Aún no hay seguimientos registrados.</p>
            <?php endif; ?>
            <?php foreach ($seguimientos as $sg): ?>
                <div class="border-bottom py-2">
                    <div class="d-flex justify-content-between">
                        <strong><?= limpiar($sg['usuario_nombre'] ?: 'Sistema') ?></strong>
                        <small class="text-muted"><?= formatoFechaHora($sg['fecha_hora_ing']) ?></small>
                    </div>
                    <?php if ($sg['estado_nombre']): ?>
                        <span class="badge mb-1" style="background-color:<?= limpiar($sg['estado_color']) ?>;">Cambio a: <?= limpiar($sg['estado_nombre']) ?></span>
                    <?php endif; ?>
                    <div><?= nl2br(limpiar($sg['comentario'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-person"></i> Cliente</h6>
            <div class="mb-2">
                <select class="form-select" name="id_tbl_clientes" id="soporte_cliente" required>
                    <?php foreach ($clientes as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= $cl['id']==$soporte['id_tbl_clientes']?'selected':'' ?>>
                            <?= limpiar($cl['nombre_comercial']) ?><?= $cl['razon_social'] ? ' - ' . limpiar($cl['razon_social']) : '' ?> (<?= limpiar($cl['rucci']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($soporte['cliente_razon_social']): ?><p class="mb-1 small text-muted"><?= limpiar($soporte['cliente_razon_social']) ?></p><?php endif; ?>
            <p class="mb-1 small">RUC/Cédula: <?= limpiar($soporte['cliente_rucci']) ?></p>
            <?php if ($soporte['cliente_telefono']): ?><p class="mb-1 small"><i class="bi bi-telephone"></i> <?= limpiar($soporte['cliente_telefono']) ?></p><?php endif; ?>
            <?php if ($soporte['cliente_celular']): ?><p class="mb-1 small"><i class="bi bi-phone"></i> <?= limpiar($soporte['cliente_celular']) ?></p><?php endif; ?>
            <?php if ($soporte['cliente_correo']): ?><p class="mb-0 small"><i class="bi bi-envelope"></i> <?= limpiar($soporte['cliente_correo']) ?></p><?php endif; ?>
        </div>

        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-gear"></i> Clasificación y Estado</h6>
            <div class="mb-2">
                <label class="form-label">Estado</label>
                <select class="form-select" name="state" id="soporte_state">
                    <?php foreach ($estados as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $e['id']==$soporte['state']?'selected':'' ?>><?= limpiar($e['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Categoría</label>
                <select class="form-select" name="id_tbl_soporte_categoria">
                    <option value="">-- Sin categoría --</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id']==$soporte['id_tbl_soporte_categoria']?'selected':'' ?>><?= limpiar($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Tipo de atención</label>
                <select class="form-select" name="id_tbl_soporte_tipo">
                    <option value="">-- Sin tipo --</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id']==$soporte['id_tbl_soporte_tipo']?'selected':'' ?>><?= limpiar($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-2">
                <label class="form-label">Prioridad</label>
                <select class="form-select" name="id_tbl_soporte_prioridad">
                    <?php foreach ($prioridades as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id']==$soporte['id_tbl_soporte_prioridad']?'selected':'' ?>><?= limpiar($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Técnico asignado</label>
                <select class="form-select" name="id_tbl_admin_user_asignado">
                    <option value="">-- Sin asignar --</option>
                    <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id']==$soporte['id_tbl_admin_user_asignado']?'selected':'' ?>><?= limpiar($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Agregar comentario de seguimiento <small class="text-muted">(opcional)</small></label>
                <textarea class="form-control" name="comentario" rows="3" placeholder="Describa el avance realizado..."></textarea>
            </div>
            <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar Cambios</button>
        </div>
    </div>
</div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    inicializarDropzone('dropzoneArchivos', 'inputArchivos', 'listaArchivosSeleccionados', ['png','jpg','jpeg','mp4']);
    inicializarCalculoTiempoSoporte();
    document.getElementById('formSoporte').addEventListener('submit', function (e) {
        if (!validarFechasSoporte()) e.preventDefault();
    });
});

function validarFechasSoporte() {
    const campoFecha = document.getElementById('soporte_fecha');
    const campoInicio = document.getElementById('soporte_solved_str');
    const campoFin = document.getElementById('soporte_solved_end');
    const campoEstado = document.getElementById('soporte_state');
    if (!campoFecha) return true;

    const fecha = campoFecha.value ? new Date(campoFecha.value) : null;
    const inicio = campoInicio.value ? new Date(campoInicio.value) : null;
    const fin = campoFin.value ? new Date(campoFin.value) : null;

    if (campoEstado && parseInt(campoEstado.value) === <?= ESTADO_SOPORTE_FINALIZADO ?> && (!campoInicio.value || !campoFin.value)) {
        mostrarAviso('Para marcar la solicitud como Finalizado debe registrar el inicio y el fin de atención.');
        return false;
    }
    if (fecha && inicio && inicio < fecha) {
        mostrarAviso('El inicio de atención no puede ser anterior a la fecha de la solicitud.');
        return false;
    }
    if (fecha && fin && fin < fecha) {
        mostrarAviso('El fin de atención no puede ser anterior a la fecha de la solicitud.');
        return false;
    }
    if (inicio && fin && fin < inicio) {
        mostrarAviso('El fin de atención no puede ser anterior al inicio de atención.');
        return false;
    }
    return true;
}

function inicializarCalculoTiempoSoporte() {
    const campoInicio = document.getElementById('soporte_solved_str');
    const campoFin = document.getElementById('soporte_solved_end');
    const campoValorHora = document.getElementById('soporte_valor_hora');
    const salidaTiempo = document.getElementById('soporte_tiempo_total');
    const salidaMonto = document.getElementById('soporte_monto_total');
    if (!campoInicio || !campoFin || !campoValorHora) return;

    function recalcular() {
        const inicio = campoInicio.value ? new Date(campoInicio.value) : null;
        const fin = campoFin.value ? new Date(campoFin.value) : null;
        const valorHora = parseFloat(campoValorHora.value) || 0;

        let totalMinutos = 0;
        if (inicio && fin && fin > inicio) {
            totalMinutos = Math.round((fin - inicio) / 60000);
        }
        const montoTotal = (totalMinutos / 60) * valorHora;

        salidaTiempo.textContent = totalMinutos + ' min';
        salidaMonto.textContent = '$' + montoTotal.toFixed(2);
    }

    [campoInicio, campoFin, campoValorHora].forEach(campo => {
        campo.addEventListener('input', recalcular);
        campo.addEventListener('change', recalcular);
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
