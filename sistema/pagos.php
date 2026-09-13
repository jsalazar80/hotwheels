<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(18);

$carpetaPagos = __DIR__ . '/files/pagos';

// ---- Guardar (crear o editar) un pago ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_pago') {
    $pago_id = (int)($_POST['pago_id'] ?? 0);
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $fecha = trim($_POST['fecha'] ?? '') ?: date('Y-m-d H:i:s');
    $id_tbl_tipos_pago = (int)($_POST['id_tbl_tipos_pago'] ?? 0);
    $monto = (float)($_POST['monto'] ?? 0);
    $id_tbl_bancos_post = (int)($_POST['id_tbl_bancos'] ?? 0);
    $no_cuenta_post = trim($_POST['no_cuenta'] ?? '');
    $id_tbl_bancos_cuentas_post = (int)($_POST['id_tbl_bancos_cuentas'] ?? 0);
    $ref_no_post = trim($_POST['ref_no'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    try {
        if ($cliente_id <= 0) throw new Exception('Debe seleccionar un cliente.');
        if ($monto <= 0) throw new Exception('El monto debe ser mayor a cero.');
        if ($id_tbl_tipos_pago <= 0) throw new Exception('Seleccione una forma de pago.');

        $stmtSaldo = $pdo->prepare("SELECT COALESCE(total_soportes,0) - COALESCE(total_pagos,0) saldo FROM tbl_saldos WHERE id_tbl_clientes = ? AND state = 1");
        $stmtSaldo->execute([$cliente_id]);
        $saldoPendiente = (float)($stmtSaldo->fetch()['saldo'] ?? 0);

        if ($pago_id > 0) {
            $stmtPagoAnterior = $pdo->prepare("SELECT monto FROM tbl_pagos WHERE id = ? AND id_tbl_clientes = ?");
            $stmtPagoAnterior->execute([$pago_id, $cliente_id]);
            $montoAnterior = $stmtPagoAnterior->fetch();
            if ($montoAnterior) $saldoPendiente += (float)$montoAnterior['monto'];
        }

        if ($monto > $saldoPendiente + 0.001) {
            throw new Exception('El monto no puede ser mayor al saldo pendiente del cliente ($' . number_format($saldoPendiente, 2) . ').');
        }

        $stmtTipo = $pdo->prepare("SELECT nombre FROM tbl_tipos_pago WHERE id = ?");
        $stmtTipo->execute([$id_tbl_tipos_pago]);
        $nombreTipoPago = $stmtTipo->fetch()['nombre'] ?? '';
        $requiereBanco = in_array($nombreTipoPago, ['Transferencia', 'Cheque'], true);

        if ($requiereBanco) {
            if ($id_tbl_bancos_cuentas_post <= 0 || $id_tbl_bancos_cuentas_post === 1) {
                throw new Exception('Para pagos por Transferencia o Cheque debe seleccionar una cuenta destino válida.');
            }
            if ($ref_no_post === '') {
                throw new Exception('Para pagos por Transferencia o Cheque debe ingresar el número de referencia.');
            }
            $id_tbl_bancos = $id_tbl_bancos_post ?: 1;
            $no_cuenta = $no_cuenta_post ?: null;
            $id_tbl_bancos_cuentas = $id_tbl_bancos_cuentas_post;
            $ref_no = $ref_no_post;
        } else {
            $id_tbl_bancos = 1;
            $no_cuenta = null;
            $id_tbl_bancos_cuentas = 1;
            $ref_no = null;
        }

        $pdo->beginTransaction();

        if ($pago_id > 0) {
            $sqlUpd = "UPDATE tbl_pagos SET id_tbl_clientes=?, fecha=?, id_tbl_tipos_pago=?, monto=?, id_tbl_bancos=?, no_cuenta=?, ref_no=?, id_tbl_bancos_cuentas=?, observaciones=? WHERE id=?";
            $paramsUpd = [$cliente_id, $fecha, $id_tbl_tipos_pago, $monto, $id_tbl_bancos, $no_cuenta, $ref_no, $id_tbl_bancos_cuentas, $observaciones, $pago_id];
            $stmt = $pdo->prepare($sqlUpd);
            $stmt->execute($paramsUpd);
            registrarAuditoria($pdo, 'UPD', 'tbl_pagos', $pago_id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de pago');
            $mensajeOk = 'Pago actualizado correctamente.';
        } else {
            $sqlIns = "INSERT INTO tbl_pagos (id_tbl_clientes, fecha, id_tbl_tipos_pago, monto, id_tbl_bancos, no_cuenta, ref_no, id_tbl_bancos_cuentas, observaciones, user_ing) VALUES (?,?,?,?,?,?,?,?,?,?)";
            $paramsIns = [$cliente_id, $fecha, $id_tbl_tipos_pago, $monto, $id_tbl_bancos, $no_cuenta, $ref_no, $id_tbl_bancos_cuentas, $observaciones, $_SESSION['tsp_usuario_id']];
            $stmt = $pdo->prepare($sqlIns);
            $stmt->execute($paramsIns);
            $pago_id = (int)$pdo->lastInsertId();
            registrarAuditoria($pdo, 'INS', 'tbl_pagos', $pago_id, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo pago');
            $mensajeOk = 'Pago registrado correctamente.';
        }

        // ---- Subida de comprobantes (imágenes jpg/jpeg/png, convertidas a jpg) ----
        if (!empty($_FILES['comprobantes']['name'][0])) {
            $carpetaPagoActual = $carpetaPagos . '/folder_' . $pago_id;
            $carpetaMiniaturas = $carpetaPagoActual . '/thumbnail';
            if (!is_dir($carpetaMiniaturas)) mkdir($carpetaMiniaturas, 0755, true);

            $extensionesPermitidas = ['png', 'jpg', 'jpeg'];
            $siguienteSecuencial = count(glob($carpetaPagoActual . '/ft*.jpg')) + 1;

            foreach ($_FILES['comprobantes']['name'] as $i => $nombreOriginal) {
                if ($_FILES['comprobantes']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensionesPermitidas, true)) continue;

                $nombreGuardado = 'ft' . $siguienteSecuencial . '_' . $pago_id . '.jpg';
                $nombreMiniatura = 's_' . $nombreGuardado;
                $rutaDestino = $carpetaPagoActual . '/' . $nombreGuardado;
                $rutaMiniatura = $carpetaMiniaturas . '/' . $nombreMiniatura;

                if (guardarImagenJpgConMiniatura($_FILES['comprobantes']['tmp_name'][$i], $ext, $rutaDestino, $rutaMiniatura)) {
                    $sqlArch = "INSERT INTO tbl_pagos_archivos (id_tbl_pagos, archivo, nombre_original, user_ing) VALUES (?,?,?,?)";
                    $paramsArch = [$pago_id, $nombreGuardado, $nombreOriginal, $_SESSION['tsp_usuario_id']];
                    $insArch = $pdo->prepare($sqlArch);
                    $insArch->execute($paramsArch);
                    registrarAuditoria($pdo, 'INS', 'tbl_pagos_archivos', (int)$pdo->lastInsertId(), interpolarSql($pdo, $sqlArch, $paramsArch), 'Adjunto de comprobante de pago');
                    $siguienteSecuencial++;
                }
            }
        }

        recalcularSaldoCliente($pdo, $cliente_id, 'Recálculo de totales tras editar pago');

        $pdo->commit();
        redirigirConMensaje('pagos.php', 'ok', $mensajeOk);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirigirConMensaje('pagos.php', 'error', $e->getMessage());
    }
}

// ---- Eliminar (anular) un pago ----
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    $stmtPago = $pdo->prepare("SELECT * FROM tbl_pagos WHERE id = ?");
    $stmtPago->execute([$id]);
    $pago = $stmtPago->fetch();
    if ($pago) {
        $sqlDel = "UPDATE tbl_pagos SET state=0 WHERE id=?";
        $paramsDel = [$id];
        $pdo->prepare($sqlDel)->execute($paramsDel);
        registrarAuditoria($pdo, 'UPD', 'tbl_pagos', $id, interpolarSql($pdo, $sqlDel, $paramsDel), 'Eliminación (anulación) de pago');
        recalcularSaldoCliente($pdo, (int)$pago['id_tbl_clientes'], 'Recálculo de totales tras eliminar pago');
    }
    redirigirConMensaje('pagos.php', 'ok', 'Pago eliminado y saldo actualizado.');
}

// ---- Eliminar un comprobante adjunto de un pago ----
if (isset($_GET['eliminar_archivo'])) {
    $idArchivo = (int)$_GET['eliminar_archivo'];
    $stmtArch = $pdo->prepare("SELECT * FROM tbl_pagos_archivos WHERE id = ?");
    $stmtArch->execute([$idArchivo]);
    $archivo = $stmtArch->fetch();
    $urlVolver = 'pagos.php';
    if ($archivo) {
        $sqlDel = "UPDATE tbl_pagos_archivos SET state=0 WHERE id=?";
        $paramsDel = [$idArchivo];
        $pdo->prepare($sqlDel)->execute($paramsDel);
        registrarAuditoria($pdo, 'UPD', 'tbl_pagos_archivos', $idArchivo, interpolarSql($pdo, $sqlDel, $paramsDel), 'Eliminación de comprobante adjunto de pago');
        $urlVolver = 'pagos.php?editar=' . (int)$archivo['id_tbl_pagos'];
    }
    redirigirConMensaje($urlVolver, 'ok', 'Comprobante eliminado.');
}

$clientes = $pdo->query("SELECT cl.*, COALESCE(sd.total_soportes,0) - COALESCE(sd.total_pagos,0) saldo_pendiente
    FROM tbl_clientes cl
    LEFT JOIN tbl_saldos sd ON sd.id_tbl_clientes = cl.id AND sd.state = 1
    WHERE cl.state=1 ORDER BY cl.nombre_comercial ASC")->fetchAll();
$tiposPago = $pdo->query("SELECT * FROM tbl_tipos_pago WHERE state=1 ORDER BY id ASC")->fetchAll();
$bancos = $pdo->query("SELECT * FROM tbl_bancos WHERE state=1 ORDER BY (id=1) DESC, nombre ASC")->fetchAll();
$cuentasBancarias = $pdo->query("SELECT bc.*, b.nombre banco_nombre FROM tbl_bancos_cuentas bc
    JOIN tbl_bancos b ON b.id = bc.id_tbl_bancos
    WHERE bc.state=1 ORDER BY (bc.id=1) DESC, b.nombre ASC")->fetchAll();

// ---- Filtros del listado ----
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$filtroCliente = (int)($_GET['cliente'] ?? 0);

$sql = "SELECT p.*, cl.nombre_comercial cliente, tp.nombre tipo_pago_nombre, b.nombre banco_nombre, bc.nombre cuenta_nombre,
        (SELECT COUNT(*) FROM tbl_pagos_archivos pa WHERE pa.id_tbl_pagos = p.id AND pa.state = 1) comprobantes_count
        FROM tbl_pagos p
        JOIN tbl_clientes cl ON cl.id = p.id_tbl_clientes
        JOIN tbl_tipos_pago tp ON tp.id = p.id_tbl_tipos_pago
        LEFT JOIN tbl_bancos b ON b.id = p.id_tbl_bancos
        LEFT JOIN tbl_bancos_cuentas bc ON bc.id = p.id_tbl_bancos_cuentas
        WHERE DATE(p.fecha) BETWEEN ? AND ? AND p.state = 1";
$params = [$desde, $hasta];
if ($filtroCliente > 0) { $sql .= " AND p.id_tbl_clientes = ?"; $params[] = $filtroCliente; }
$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pagos = $stmt->fetchAll();

$totalPagosRango = array_sum(array_column($pagos, 'monto'));

// ---- Comprobantes adjuntos de los pagos listados (para el modal de vista) ----
$archivosPorPago = [];
$idsPagosListados = array_column($pagos, 'id');
if ($idsPagosListados) {
    $placeholders = implode(',', array_fill(0, count($idsPagosListados), '?'));
    $stmtArch = $pdo->prepare("SELECT * FROM tbl_pagos_archivos WHERE id_tbl_pagos IN ($placeholders) AND state = 1 ORDER BY id ASC");
    $stmtArch->execute($idsPagosListados);
    foreach ($stmtArch->fetchAll() as $a) {
        $archivosPorPago[$a['id_tbl_pagos']][] = $a;
    }
}

// ---- Reabrir un pago en modo edición (p.ej. tras eliminar uno de sus comprobantes) ----
$pagoParaReabrir = null;
$idEditar = (int)($_GET['editar'] ?? 0);
if ($idEditar > 0) {
    $stmtReabrir = $pdo->prepare("SELECT p.*, cl.nombre_comercial cliente, tp.nombre tipo_pago_nombre, b.nombre banco_nombre, bc.nombre cuenta_nombre
        FROM tbl_pagos p
        JOIN tbl_clientes cl ON cl.id = p.id_tbl_clientes
        JOIN tbl_tipos_pago tp ON tp.id = p.id_tbl_tipos_pago
        LEFT JOIN tbl_bancos b ON b.id = p.id_tbl_bancos
        LEFT JOIN tbl_bancos_cuentas bc ON bc.id = p.id_tbl_bancos_cuentas
        WHERE p.id = ? AND p.state = 1");
    $stmtReabrir->execute([$idEditar]);
    $pagoParaReabrir = $stmtReabrir->fetch();
    if ($pagoParaReabrir) {
        if (isset($archivosPorPago[$idEditar])) {
            $pagoParaReabrir['archivos'] = $archivosPorPago[$idEditar];
        } else {
            $stmtArchReabrir = $pdo->prepare("SELECT * FROM tbl_pagos_archivos WHERE id_tbl_pagos = ? AND state = 1 ORDER BY id ASC");
            $stmtArchReabrir->execute([$idEditar]);
            $pagoParaReabrir['archivos'] = $stmtArchReabrir->fetchAll();
        }
    }
}

$tituloPagina = 'Pagos';
$paginaActiva = 'pagos';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-cash-coin"></i> Registrar Pago</h6>
            <form method="post" id="formPago" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="guardar_pago">
                <input type="hidden" name="pago_id" id="pago_id" value="0">
                <div class="mb-2">
                    <label class="form-label">Cliente</label>
                    <select class="form-select" name="cliente_id" id="pago_cliente" onchange="mostrarSaldoCliente()" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $cl['id'] ?>" data-saldo="<?= number_format((float)$cl['saldo_pendiente'], 2, '.', '') ?>">
                                <?= limpiar($cl['nombre_comercial']) ?><?= $cl['razon_social'] ? ' - ' . limpiar($cl['razon_social']) : '' ?> (<?= limpiar($cl['rucci']) ?>) &mdash; Saldo pendiente: $<?= number_format((float)$cl['saldo_pendiente'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text d-none" id="grupoSaldoCliente">
                        Saldo pendiente: <strong id="textoSaldoCliente">$0.00</strong>
                        <button type="button" class="btn btn-sm btn-outline-tsp py-0 ms-1" id="btnPagarTodo" onclick="pagarTodo()">Pagar todo</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Fecha</label>
                        <input type="datetime-local" class="form-control" name="fecha" id="pago_fecha" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Monto ($)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="monto" id="pago_monto" required>
                        <input type="hidden" id="pago_monto_original" value="0">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Forma de pago</label>
                    <select class="form-select" name="id_tbl_tipos_pago" id="pago_tipo" onchange="mostrarCamposBanco()">
                        <?php foreach ($tiposPago as $tp): ?>
                            <option value="<?= $tp['id'] ?>"><?= limpiar($tp['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-none" id="grupoBancoNumero">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label">Banco origen</label>
                            <select class="form-select" name="id_tbl_bancos" id="pago_banco" >  /*onchange="filtrarCuentasPorBanco()"*/
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($bancos as $b): ?>
                                    <?php if ((int)$b['id'] === 1) continue; ?>
                                    <option value="<?= $b['id'] ?>"><?= limpiar($b['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">Cuenta origen</label>
                            <input type="text" class="form-control" name="no_cuenta" id="pago_no_cuenta" placeholder="N° de cuenta de origen">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label">N° de referencia</label>
                            <input type="text" class="form-control" name="ref_no" id="pago_ref_no">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label">Cuenta destino</label>
                            <select class="form-select" name="id_tbl_bancos_cuentas" id="pago_cuenta">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($cuentasBancarias as $cb): ?>
                                    <?php if ((int)$cb['id'] === 1) continue; ?>
                                    <option value="<?= $cb['id'] ?>" data-banco="<?= $cb['id_tbl_bancos'] ?>"><?= limpiar($cb['banco_nombre']) ?> - <?= limpiar($cb['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Observaciones <small class="text-muted">(opcional)</small></label>
                    <input type="text" class="form-control" name="observaciones" id="pago_observaciones">
                </div>
                <div class="mb-3">
                    <label class="form-label">Archivos adjuntos <small class="text-muted">(opcional)</small></label>
                    <div class="dropzone" id="dropzoneComprobantes">
                        <i class="bi bi-cloud-arrow-up"></i>
                        Arrastra imágenes aquí o haz clic para seleccionar
                        <div class="small mt-1">Formatos permitidos: PNG, JPG, JPEG</div>
                    </div>
                    <input type="file" class="d-none" id="inputComprobantes" name="comprobantes[]" multiple accept=".png,.jpg,.jpeg">
                    <div id="listaComprobantesSeleccionados" class="mt-2"></div>
                    <div class="row g-2 mt-1" id="galeriaComprobantes"></div>
                </div>
                <button class="btn btn-tsp w-100" type="submit" id="btnGuardarPago"><i class="bi bi-save"></i> Registrar Pago</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelarPago" onclick="cancelarEdicionPago()">Cancelar edición</button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-funnel"></i> Filtros</h6>
            <form method="get" class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0">Del</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="<?= limpiar($desde) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0">Al</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="<?= limpiar($hasta) ?>">
                </div>
                <div class="col-8 col-md-4">
                    <label class="form-label small mb-0">Cliente</label>
                    <select name="cliente" class="form-select form-select-sm">
                        <option value="0">Todos</option>
                        <?php foreach ($clientes as $cl): ?>
                            <option value="<?= $cl['id'] ?>" <?= $filtroCliente==$cl['id']?'selected':'' ?>><?= limpiar($cl['nombre_comercial']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <button class="btn btn-tsp btn-sm w-100" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>

        <div class="card-panel">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h6 class="panel-title mb-0 border-0 pb-0"><i class="bi bi-list-ul"></i> Pagos (<?= count($pagos) ?>)</h6>
                <div class="fw-bold text-tsp">Total: $<?= number_format($totalPagosRango, 2) ?></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Fecha</th><th>Cliente</th><th>Monto</th><th>Tipo</th><th>Banco/Cuenta origen</th><th>Cuenta destino</th><th>Ref.</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($pagos as $p): ?>
                        <tr>
                            <td><?= formatoFecha($p['fecha']) ?></td>
                            <td><?= limpiar($p['cliente']) ?></td>
                            <td>$<?= number_format($p['monto'],2) ?></td>
                            <td><?= limpiar($p['tipo_pago_nombre']) ?></td>
                            <td><?= limpiar($p['banco_nombre']) ?><?= $p['no_cuenta'] ? ' - ' . limpiar($p['no_cuenta']) : '' ?></td>
                            <td><?= limpiar($p['cuenta_nombre']) ?></td>
                            <td><?= limpiar($p['ref_no']) ?></td>
                            <td class="text-nowrap">
                                <?php $pParaEditar = $p; $pParaEditar['archivos'] = $archivosPorPago[$p['id']] ?? []; ?>
                                <button type="button" class="btn btn-sm btn-outline-tsp" onclick='editarPago(<?= json_encode($pParaEditar, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <?php $numComprobantes = (int)$p['comprobantes_count']; ?>
                                <?php if ($numComprobantes > 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success" title="Ver comprobantes adjuntos" onclick='verComprobantes(<?= $p['id'] ?>, <?= json_encode($archivosPorPago[$p['id']] ?? [], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-paperclip"></i> <?= $numComprobantes ?></button>
                                <?php endif; ?>
                                <?php if (esAdmin()): ?>
                                <a href="pagos.php?eliminar=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirmarAccion('¿Eliminar este pago? El saldo del cliente se recalculará.')"><i class="bi bi-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$pagos): ?><tr><td colspan="8" class="text-center text-muted">No se encontraron pagos en el rango seleccionado.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalComprobantes" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-paperclip"></i> Comprobantes adjuntos</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2" id="listaComprobantesModal"></div>
            </div>
        </div>
    </div>
</div>

<script>
function verComprobantes(pagoId, archivos) {
    const cont = document.getElementById('listaComprobantesModal');
    if (!archivos || !archivos.length) {
        cont.innerHTML = '<p class="text-muted small mb-0">No hay comprobantes adjuntos.</p>';
    } else {
        const base = 'files/pagos/folder_' + pagoId + '/';
        cont.innerHTML = archivos.map(a => `
            <div class="col-6 col-md-4">
                <a href="${base}${a.archivo}" target="_blank">
                    <img src="${base}thumbnail/s_${a.archivo}" class="img-fluid rounded border" style="width:100%; height:100px; object-fit:cover;">
                </a>
            </div>
        `).join('');
    }
    new bootstrap.Modal(document.getElementById('modalComprobantes')).show();
}

function renderizarGaleriaComprobantes(pagoId, archivos) {
    const cont = document.getElementById('galeriaComprobantes');
    if (!archivos || !archivos.length) {
        cont.innerHTML = '';
        return;
    }
    const base = 'files/pagos/folder_' + pagoId + '/';
    cont.innerHTML = archivos.map(a => `
        <div class="col-4">
            <div class="border rounded p-1 text-center">
                <a href="${base}${a.archivo}" target="_blank">
                    <img src="${base}thumbnail/s_${a.archivo}" class="img-fluid rounded" style="width:100%; height:70px; object-fit:cover;">
                </a>
                <a href="pagos.php?eliminar_archivo=${a.id}" class="btn btn-sm btn-outline-danger mt-1 w-100 py-0" onclick="return confirmarAccion('¿Eliminar este comprobante?')"><i class="bi bi-trash"></i></a>
            </div>
        </div>
    `).join('');
}

function saldoClienteSeleccionado() {
    const sel = document.getElementById('pago_cliente');
    const opt = sel.options[sel.selectedIndex];
    return opt && opt.value ? parseFloat(opt.dataset.saldo) || 0 : 0;
}

function mostrarSaldoCliente() {
    const saldo = saldoClienteSeleccionado();
    const grupo = document.getElementById('grupoSaldoCliente');
    const texto = document.getElementById('textoSaldoCliente');
    const btnPagarTodo = document.getElementById('btnPagarTodo');
    const hayCliente = document.getElementById('pago_cliente').value !== '';

    grupo.classList.toggle('d-none', !hayCliente);
    texto.textContent = '$' + saldo.toFixed(2);
    btnPagarTodo.classList.toggle('d-none', saldo <= 0);
}

function pagarTodo() {
    const saldo = saldoClienteSeleccionado();
    const montoOriginal = parseFloat(document.getElementById('pago_monto_original').value) || 0;
    document.getElementById('pago_monto').value = (saldo + montoOriginal).toFixed(2);
}

function mostrarCamposBanco() {
    const sel = document.getElementById('pago_tipo');
    const texto = sel.options[sel.selectedIndex].text;
    const grupo = document.getElementById('grupoBancoNumero');
    const campoCuenta = document.getElementById('pago_cuenta');
    const campoBanco = document.getElementById('pago_banco');
    if (texto === 'Transferencia' || texto === 'Cheque') {
        grupo.classList.remove('d-none');
        campoCuenta.required = true;
        campoBanco.required = true;
    } else {
        grupo.classList.add('d-none');
        campoCuenta.required = false;
        campoBanco.required = false;
        campoCuenta.value = '';
        campoBanco.value = '';
        document.getElementById('pago_no_cuenta').value = '';
        document.getElementById('pago_ref_no').value = '';
    }
}
document.addEventListener('DOMContentLoaded', mostrarCamposBanco);

function filtrarCuentasPorBanco() {
    const bancoId = document.getElementById('pago_banco').value;
    const selectCuenta = document.getElementById('pago_cuenta');
    Array.from(selectCuenta.options).forEach(opt => {
        if (!opt.value) { opt.hidden = false; return; }
        opt.hidden = bancoId && opt.dataset.banco !== bancoId;
    });
    selectCuenta.value = '';
}

document.addEventListener('DOMContentLoaded', function () {
    inicializarDropzone('dropzoneComprobantes', 'inputComprobantes', 'listaComprobantesSeleccionados', ['png', 'jpg', 'jpeg']);
    <?php if ($pagoParaReabrir): ?>
    editarPago(<?= json_encode($pagoParaReabrir, JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    <?php endif; ?>
});

document.getElementById('formPago').addEventListener('submit', function (e) {
    const sel = document.getElementById('pago_tipo');
    const texto = sel.options[sel.selectedIndex].text;
    if (texto === 'Transferencia' || texto === 'Cheque') {
        const cuenta = document.getElementById('pago_cuenta').value;
        const refNo = document.getElementById('pago_ref_no').value.trim();
        if (!cuenta) {
            e.preventDefault();
            mostrarAviso('Seleccione una cuenta bancaria para pagos por Transferencia o Cheque.');
            return;
        }
        if (!refNo) {
            e.preventDefault();
            mostrarAviso('Ingrese el número de referencia.');
            return;
        }
    }

    const monto = parseFloat(document.getElementById('pago_monto').value) || 0;
    const montoOriginal = parseFloat(document.getElementById('pago_monto_original').value) || 0;
    const saldoDisponible = saldoClienteSeleccionado() + montoOriginal;
    if (monto > saldoDisponible + 0.001) {
        e.preventDefault();
        mostrarAviso('El monto no puede ser mayor al saldo pendiente del cliente ($' + saldoDisponible.toFixed(2) + ').');
    }
});

function editarPago(p) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Pago';
    document.getElementById('pago_id').value = p.id;
    document.getElementById('pago_cliente').value = p.id_tbl_clientes;
    document.getElementById('pago_monto_original').value = parseFloat(p.monto);
    mostrarSaldoCliente();
    document.getElementById('pago_fecha').value = p.fecha.replace(' ', 'T').substring(0, 16);
    document.getElementById('pago_monto').value = parseFloat(p.monto).toFixed(2);
    document.getElementById('pago_tipo').value = p.id_tbl_tipos_pago;
    document.getElementById('pago_observaciones').value = p.observaciones || '';
    mostrarCamposBanco();
    if (p.id_tbl_bancos && parseInt(p.id_tbl_bancos) !== 1) {
        document.getElementById('pago_banco').value = p.id_tbl_bancos;
        filtrarCuentasPorBanco();
    }
    if (p.id_tbl_bancos_cuentas && parseInt(p.id_tbl_bancos_cuentas) !== 1) {
        document.getElementById('pago_cuenta').value = p.id_tbl_bancos_cuentas;
    }
    document.getElementById('pago_no_cuenta').value = p.no_cuenta || '';
    document.getElementById('pago_ref_no').value = p.ref_no || '';
    renderizarGaleriaComprobantes(p.id, p.archivos || []);
    document.getElementById('btnGuardarPago').innerHTML = '<i class="bi bi-save"></i> Guardar Cambios';
    document.getElementById('btnCancelarPago').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function cancelarEdicionPago() {
    document.getElementById('formPago').reset();
    document.getElementById('pago_id').value = 0;
    document.getElementById('pago_monto_original').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-cash-coin"></i> Registrar Pago';
    document.getElementById('btnGuardarPago').innerHTML = '<i class="bi bi-save"></i> Registrar Pago';
    document.getElementById('btnCancelarPago').classList.add('d-none');
    renderizarGaleriaComprobantes(0, []);
    mostrarCamposBanco();
    mostrarSaldoCliente();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
