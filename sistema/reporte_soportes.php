<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(26);

// Columnas del reporte (compartidas por pantalla, Excel y PDF)
const REPORTE_SOPORTES_COLUMNAS = [
    'N° Ticket', 'Fecha', 'Cliente', 'Asunto', 'Categoría', 'Tipo',
    'Prioridad', 'Técnico', 'Estado', 'Tiempo (min)', 'Valor/Hora', 'Monto Total',
];

const REPORTE_SOPORTES_SELECT = "SELECT s.id, s.numero, s.fecha, cl.nombre_comercial cliente, s.asunto,
        cat.nombre categoria, tp.nombre tipo, pr.nombre prioridad, u.nombre tecnico, e.nombre estado,
        s.total_minutos, s.valor_por_hora, s.monto_total
    FROM tbl_soportes s
    JOIN tbl_clientes cl ON cl.id = s.id_tbl_clientes
    LEFT JOIN tbl_soporte_categoria cat ON cat.id = s.id_tbl_soporte_categoria
    LEFT JOIN tbl_soporte_tipo tp ON tp.id = s.id_tbl_soporte_tipo
    LEFT JOIN tbl_soporte_prioridad pr ON pr.id = s.id_tbl_soporte_prioridad
    LEFT JOIN tbl_admin_user u ON u.id = s.id_tbl_admin_user_asignado
    JOIN tbl_soporte_estado e ON e.id = s.state";

/** Normaliza una fila de la consulta a valores crudos (números como números, fechas ya formateadas). */
function reporteSoportesFila($r) {
    return [
        'numero'        => $r['numero'],
        'fecha'         => formatoFechaHora($r['fecha']),
        'cliente'       => $r['cliente'],
        'asunto'        => $r['asunto'],
        'categoria'     => $r['categoria'] ?? '',
        'tipo'          => $r['tipo'] ?? '',
        'prioridad'     => $r['prioridad'] ?? '',
        'tecnico'       => $r['tecnico'] ?? '',
        'estado'        => $r['estado'],
        'total_minutos' => (int)$r['total_minutos'],
        'valor_por_hora'=> (float)$r['valor_por_hora'],
        'monto_total'   => (float)$r['monto_total'],
    ];
}

/**
 * Lee los filtros del reporte desde $_GET. Las fechas usan como valor por defecto el inicio
 * del mes actual y la fecha de hoy; se distingue "primera carga" (aplica default) de
 * "el usuario borró la fecha" (parámetro presente pero vacío) mediante isset(). El estado se
 * conserva como string porque ANULADO = 0 es un valor válido.
 */
function reporteSoportesFiltros() {
    return [
        'fecha_desde' => isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : date('Y-m-01'),
        'fecha_hasta' => isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : date('Y-m-d'),
        'estado'      => isset($_GET['estado']) && $_GET['estado'] !== '' ? (string)$_GET['estado'] : '',
        'cliente'     => (int)($_GET['cliente'] ?? 0),
        'categoria'   => (int)($_GET['categoria'] ?? 0),
        'tipo'        => (int)($_GET['tipo'] ?? 0),
        'prioridad'   => (int)($_GET['prioridad'] ?? 0),
    ];
}

/** Construye la cláusula WHERE (con marcadores) y los parámetros a partir de los filtros. */
function reporteSoportesWhere($f) {
    $cond = [];
    $params = [];
    if ($f['fecha_desde'] !== '') { $cond[] = "DATE(s.fecha) >= ?"; $params[] = $f['fecha_desde']; }
    if ($f['fecha_hasta'] !== '') { $cond[] = "DATE(s.fecha) <= ?"; $params[] = $f['fecha_hasta']; }
    if ($f['estado'] !== '')      { $cond[] = "s.state = ?"; $params[] = (int)$f['estado']; }
    if ($f['cliente'] > 0)        { $cond[] = "s.id_tbl_clientes = ?"; $params[] = $f['cliente']; }
    if ($f['categoria'] > 0)      { $cond[] = "s.id_tbl_soporte_categoria = ?"; $params[] = $f['categoria']; }
    if ($f['tipo'] > 0)           { $cond[] = "s.id_tbl_soporte_tipo = ?"; $params[] = $f['tipo']; }
    if ($f['prioridad'] > 0)      { $cond[] = "s.id_tbl_soporte_prioridad = ?"; $params[] = $f['prioridad']; }
    return [$cond ? ' WHERE ' . implode(' AND ', $cond) : '', $params];
}

$filtros = reporteSoportesFiltros();
[$where, $whereParams] = reporteSoportesWhere($filtros);

// ---- Exportaciones (todos los registros que cumplen el filtro, sin paginar) ----
$export = $_GET['export'] ?? '';
if ($export === 'excel' || $export === 'pdf') {
    $stmt = $pdo->prepare(REPORTE_SOPORTES_SELECT . $where . " ORDER BY s.id DESC");
    $stmt->execute($whereParams);
    $datos = $stmt->fetchAll();
    registrarAuditoria($pdo, 'SEL', 'tbl_soportes', null, interpolarSql($pdo, REPORTE_SOPORTES_SELECT . $where, $whereParams), 'Exportación de Reporte de Soportes a ' . strtoupper($export) . ' (' . count($datos) . ' registros)');
    if ($export === 'excel') {
        reporteSoportesExportarExcel($datos);
    } else {
        reporteSoportesExportarPdf($pdo, $datos);
    }
    exit;
}

function reporteSoportesExportarExcel($datos) {
    require_once __DIR__ . '/vendor/autoload.php';
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $hoja = $spreadsheet->getActiveSheet();
    $hoja->setTitle('Soportes');
    $hoja->fromArray(REPORTE_SOPORTES_COLUMNAS, null, 'A1');

    $fila = 2;
    foreach ($datos as $r) {
        $hoja->fromArray(array_values(reporteSoportesFila($r)), null, 'A' . $fila);
        $fila++;
    }

    $ultima = 'L';
    $hoja->getStyle("A1:{$ultima}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $hoja->getStyle("A1:{$ultima}1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('17417A');
    foreach (range('A', $ultima) as $col) {
        $hoja->getColumnDimension($col)->setAutoSize(true);
    }

    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="reporte_soportes.xlsx"');
    header('Cache-Control: max-age=0');
    \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');
    exit;
}

function reporteSoportesExportarPdf($pdo, $datos) {
    require_once __DIR__ . '/vendor/autoload.php';
    $config = obtenerConfiguracion($pdo);

    $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetCreator('TechSupport');
    $pdf->SetTitle('Reporte de Soportes');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(8, 10, 8);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage();

    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(23, 65, 122);
    $pdf->Cell(0, 8, $config['nombre_empresa'] ?? 'TechSupport', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(90, 90, 90);
    $pdf->Cell(0, 6, 'Reporte de Soportes  -  Generado el ' . date('Y/m/d H:i:s') . '  -  Total: ' . count($datos) . ' registro(s)', 0, 1, 'L');
    $pdf->Ln(2);

    $html = '<table border="1" cellpadding="3" style="font-size:7px;">';
    $html .= '<thead><tr style="background-color:#17417A;color:#FFFFFF;font-weight:bold;">';
    foreach (REPORTE_SOPORTES_COLUMNAS as $col) {
        $html .= '<th>' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($datos as $r) {
        $c = reporteSoportesFila($r);
        $html .= '<tr>'
            . '<td>' . htmlspecialchars($c['numero'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($c['fecha'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($c['cliente'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($c['asunto'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($c['categoria'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($c['tipo'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($c['prioridad'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($c['tecnico'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($c['estado'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td align="right">' . $c['total_minutos'] . '</td>'
            . '<td align="right">' . number_format($c['valor_por_hora'], 2) . '</td>'
            . '<td align="right">' . number_format($c['monto_total'], 2) . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, false, false, '');

    while (ob_get_level() > 0) ob_end_clean();
    $pdf->Output('reporte_soportes.pdf', 'I');
    exit;
}

// ---- Catálogos para los combos de filtro ----
$estados = $pdo->query("SELECT id, nombre FROM tbl_soporte_estado WHERE state=1 ORDER BY id ASC")->fetchAll();
$clientes = $pdo->query("SELECT id, nombre_comercial FROM tbl_clientes WHERE state=1 ORDER BY nombre_comercial ASC")->fetchAll();
$categorias = $pdo->query("SELECT id, nombre FROM tbl_soporte_categoria WHERE state=1 ORDER BY orden ASC")->fetchAll();
$tipos = $pdo->query("SELECT id, nombre FROM tbl_soporte_tipo WHERE state=1 ORDER BY orden ASC")->fetchAll();
$prioridades = $pdo->query("SELECT id, nombre FROM tbl_soporte_prioridad WHERE state=1 ORDER BY orden ASC")->fetchAll();

// ---- Vista en pantalla (paginada) ----
$padre = (int)($_GET['padre'] ?? 0);

$opcionesPorPagina = [25, 50, 100];
$porPagina = (int)($_GET['por_pagina'] ?? 25);
if (!in_array($porPagina, $opcionesPorPagina, true)) $porPagina = 25;

$stmtCount = $pdo->prepare("SELECT COUNT(*) t FROM tbl_soportes s" . $where);
$stmtCount->execute($whereParams);
$total = (int)$stmtCount->fetch()['t'];

$totalPaginas = max(1, (int)ceil($total / $porPagina));
$pagina = max(1, min($totalPaginas, (int)($_GET['pagina'] ?? 1)));
$offset = ($pagina - 1) * $porPagina;

$stmt = $pdo->prepare(REPORTE_SOPORTES_SELECT . $where . " ORDER BY s.id DESC LIMIT $porPagina OFFSET $offset");
$stmt->execute($whereParams);
$datos = $stmt->fetchAll();

$desde = $total ? $offset + 1 : 0;
$hasta = min($offset + $porPagina, $total);

// Parámetros a preservar en paginación / selector / exportación (padre + filtros activos).
// Las fechas se incluyen siempre (aunque estén vacías) para conservar el estado "sin filtro
// de fecha" al navegar; de lo contrario se re-aplicaría el default de mes actual.
$paramsBase = [];
if ($padre > 0) $paramsBase['padre'] = $padre;
$paramsBase['fecha_desde'] = $filtros['fecha_desde'];
$paramsBase['fecha_hasta'] = $filtros['fecha_hasta'];
if ($filtros['estado'] !== '')      $paramsBase['estado'] = $filtros['estado'];
if ($filtros['cliente'] > 0)        $paramsBase['cliente'] = $filtros['cliente'];
if ($filtros['categoria'] > 0)      $paramsBase['categoria'] = $filtros['categoria'];
if ($filtros['tipo'] > 0)           $paramsBase['tipo'] = $filtros['tipo'];
if ($filtros['prioridad'] > 0)      $paramsBase['prioridad'] = $filtros['prioridad'];

$tituloPagina = 'Reporte de Soportes';
$paginaActiva = 'reporte_soportes';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-funnel"></i> Filtros</h6>
    <form method="get" class="row g-2 align-items-end">
        <?php if ($padre > 0): ?><input type="hidden" name="padre" value="<?= $padre ?>"><?php endif; ?>
        <input type="hidden" name="por_pagina" value="<?= $porPagina ?>">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Fecha desde</label>
            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= limpiar($filtros['fecha_desde']) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Fecha hasta</label>
            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= limpiar($filtros['fecha_hasta']) ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Estado</label>
            <select name="estado" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= (string)$e['id'] === $filtros['estado'] ? 'selected' : '' ?>><?= limpiar($e['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Cliente</label>
            <select name="cliente" class="form-select form-select-sm">
                <option value="0">Todos</option>
                <?php foreach ($clientes as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= $cl['id'] == $filtros['cliente'] ? 'selected' : '' ?>><?= limpiar($cl['nombre_comercial']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Categoría</label>
            <select name="categoria" class="form-select form-select-sm">
                <option value="0">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $filtros['categoria'] ? 'selected' : '' ?>><?= limpiar($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Tipo</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="0">Todos</option>
                <?php foreach ($tipos as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $t['id'] == $filtros['tipo'] ? 'selected' : '' ?>><?= limpiar($t['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Prioridad</label>
            <select name="prioridad" class="form-select form-select-sm">
                <option value="0">Todas</option>
                <?php foreach ($prioridades as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $p['id'] == $filtros['prioridad'] ? 'selected' : '' ?>><?= limpiar($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-tsp btn-sm"><i class="bi bi-search"></i> Filtrar</button>
            <a href="reporte_soportes.php<?= $padre > 0 ? '?padre=' . $padre : '' ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a>
        </div>
    </form>
</div>

<div class="card-panel">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h6 class="panel-title mb-0 border-0 pb-0"><i class="bi bi-life-preserver"></i> Reporte de Soportes</h6>
        <div class="d-flex gap-2">
            <a href="reporte_soportes.php?<?= http_build_query(array_merge($paramsBase, ['export' => 'excel'])) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a href="reporte_soportes.php?<?= http_build_query(array_merge($paramsBase, ['export' => 'pdf'])) ?>" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted">Mostrar</span>
            <select class="form-select form-select-sm" style="width:auto"
                    onchange="location.href='reporte_soportes.php?<?= http_build_query(array_merge($paramsBase, ['pagina' => 1])) ?>&por_pagina=' + this.value">
                <?php foreach ($opcionesPorPagina as $op): ?>
                    <option value="<?= $op ?>" <?= $op === $porPagina ? 'selected' : '' ?>><?= $op ?></option>
                <?php endforeach; ?>
            </select>
            <span class="small text-muted">registros</span>
        </div>
        <div class="small text-muted">Mostrando <?= $desde ?>&ndash;<?= $hasta ?> de <?= $total ?></div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-tsp align-middle">
            <thead>
                <tr>
                    <?php foreach (REPORTE_SOPORTES_COLUMNAS as $col): ?>
                        <th class="text-nowrap"><?= limpiar($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datos as $r): ?>
                    <?php $c = reporteSoportesFila($r); ?>
                    <tr>
                        <td class="text-nowrap"><?= limpiar($c['numero']) ?></td>
                        <td class="text-nowrap"><?= limpiar($c['fecha']) ?></td>
                        <td><?= limpiar($c['cliente']) ?></td>
                        <td><?= limpiar($c['asunto']) ?></td>
                        <td><?= limpiar($c['categoria']) ?></td>
                        <td><?= limpiar($c['tipo']) ?></td>
                        <td><?= limpiar($c['prioridad']) ?></td>
                        <td><?= limpiar($c['tecnico']) ?></td>
                        <td><?= limpiar($c['estado']) ?></td>
                        <td class="text-end"><?= $c['total_minutos'] ?></td>
                        <td class="text-end">$<?= number_format($c['valor_por_hora'], 2) ?></td>
                        <td class="text-end">$<?= number_format($c['monto_total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$datos): ?>
                    <tr><td colspan="<?= count(REPORTE_SOPORTES_COLUMNAS) ?>" class="text-center text-muted">No hay soportes que coincidan con los filtros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPaginas > 1): ?>
    <nav>
        <ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">
            <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="reporte_soportes.php?<?= http_build_query(array_merge($paramsBase, ['por_pagina' => $porPagina, 'pagina' => $pagina - 1])) ?>">&laquo;</a>
            </li>
            <?php for ($n = max(1, $pagina - 2); $n <= min($totalPaginas, $pagina + 2); $n++): ?>
                <li class="page-item <?= $n === $pagina ? 'active' : '' ?>">
                    <a class="page-link" href="reporte_soportes.php?<?= http_build_query(array_merge($paramsBase, ['por_pagina' => $porPagina, 'pagina' => $n])) ?>"><?= $n ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                <a class="page-link" href="reporte_soportes.php?<?= http_build_query(array_merge($paramsBase, ['por_pagina' => $porPagina, 'pagina' => $pagina + 1])) ?>">&raquo;</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
