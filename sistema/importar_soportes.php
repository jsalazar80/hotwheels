<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(22);
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Encabezados exactos esperados en el Excel, en el orden en que se generan en la plantilla.
const IMPORTAR_SOPORTES_COLUMNAS = [
    'FECHA REPORTADO', 'HORA REPORTADO', 'CLIENTE', 'SOLICITANTE', 'DESCRIPCION',
    'ANALISIS', 'SOLUCION', 'OBSERVACION', 'RECOMENDACION', 'CATEGORIA', 'TIPO',
    'PRIORIDAD', 'TECNICO', 'ESTADO', 'FECHA DESDE', 'HORA DESDE', 'FECHA HASTA', 'HORA HASTA', 'VALOR HORA',
];

/**
 * Convierte el valor crudo de una celda (serial de Excel o texto) a un timestamp Unix.
 * Devuelve null si no es un valor numérico de Excel (fecha/hora real de celda).
 */
function importarSoportesExcelATimestamp($valor) {
    if ($valor === null || $valor === '' || !is_numeric($valor)) return null;
    try {
        return ExcelDate::excelToDateTimeObject($valor)->getTimestamp();
    } catch (\Throwable $e) {
        return null;
    }
}

/** Interpreta una celda como fecha (Y-m-d), aceptando serial de Excel o texto en varios formatos. */
function importarSoportesCeldaComoFecha($valor) {
    $ts = importarSoportesExcelATimestamp($valor);
    if ($ts !== null) return date('Y-m-d', $ts);
    $valor = trim((string)$valor);
    if ($valor === '') return null;
    foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d'] as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);
        if ($dt !== false) return $dt->format('Y-m-d');
    }
    $t = strtotime($valor);
    return $t !== false ? date('Y-m-d', $t) : null;
}

/** Interpreta una celda como hora (H:i:s), aceptando serial de Excel o texto en varios formatos. */
function importarSoportesCeldaComoHora($valor) {
    $ts = importarSoportesExcelATimestamp($valor);
    if ($ts !== null) return date('H:i:s', $ts);
    $valor = trim((string)$valor);
    if ($valor === '') return null;
    foreach (['H:i:s', 'H:i', 'h:i A', 'h:i a'] as $formato) {
        $dt = DateTime::createFromFormat($formato, $valor);
        if ($dt !== false) return $dt->format('H:i:s');
    }
    $t = strtotime($valor);
    return $t !== false ? date('H:i:s', $t) : null;
}

function importarSoportesCombinarFechaHora($celdaFecha, $celdaHora) {
    $fecha = importarSoportesCeldaComoFecha($celdaFecha);
    $hora = importarSoportesCeldaComoHora($celdaHora);
    if ($fecha === null || $hora === null) return null;
    return $fecha . ' ' . $hora;
}

/** Genera y envía la plantilla Excel para descarga (termina el script). */
function importarSoportesGenerarPlantilla() {
    $spreadsheet = new Spreadsheet();
    $hoja = $spreadsheet->getActiveSheet();
    $hoja->setTitle('Soportes');
    $hoja->fromArray(IMPORTAR_SOPORTES_COLUMNAS, null, 'A1');
    $hoja->fromArray([
        date('Y-m-d'), '09:00', 'Pescafoods', 'Juan Pérez',
        'Ejemplo: no enciende el equipo de facturación', 'Se revisó la fuente de poder', 'Se reemplazó la fuente de poder',
        'Cliente satisfecho con la atención', 'Dar mantenimiento preventivo cada 6 meses',
        'Asistencia técnica', 'Presencial', 'Media', 'Julián Salazar', 'FINALIZADO',
        date('Y-m-d'), '09:00', date('Y-m-d'), '10:00', '25.00',
    ], null, 'A2');

    $ultimaColumna = 'S';
    $hoja->getStyle("A1:{$ultimaColumna}1")->getFont()->setBold(true);
    $hoja->getStyle("A1:{$ultimaColumna}1")->getFont()->getColor()->setRGB('FFFFFF');
    $hoja->getStyle("A1:{$ultimaColumna}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('17417A');
    foreach (range('A', $ultimaColumna) as $col) {
        $hoja->getColumnDimension($col)->setAutoSize(true);
    }

    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="plantilla_importar_soportes.xlsx"');
    header('Cache-Control: max-age=0');
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');
    exit;
}

if (($_GET['accion'] ?? '') === 'plantilla') {
    importarSoportesGenerarPlantilla();
}

// ---- Procesar archivo subido (previsualizar o importar) ----
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['previsualizar', 'importar'], true)) {
    $modo = $_POST['accion'];

    if (empty($_FILES['archivo']['tmp_name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        redirigirConMensaje('importar_soportes.php', 'error', 'Debe seleccionar un archivo Excel (.xlsx) válido.');
    }
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        redirigirConMensaje('importar_soportes.php', 'error', 'El archivo debe tener extensión .xlsx o .xls.');
    }

    try {
        $hoja = IOFactory::load($_FILES['archivo']['tmp_name'])->getActiveSheet();
    } catch (\Throwable $e) {
        redirigirConMensaje('importar_soportes.php', 'error', 'No se pudo leer el archivo: ' . $e->getMessage());
    }

    $filas = $hoja->toArray(null, true, false, false);
    if (count($filas) < 1) {
        redirigirConMensaje('importar_soportes.php', 'error', 'El archivo está vacío.');
    }

    $encabezados = array_map(fn($v) => strtoupper(trim((string)$v)), $filas[0]);
    $colIndice = array_flip($encabezados);

    $faltantes = array_diff(IMPORTAR_SOPORTES_COLUMNAS, array_keys($colIndice));
    if ($faltantes) {
        redirigirConMensaje('importar_soportes.php', 'error', 'Faltan columnas en el archivo: ' . implode(', ', $faltantes));
    }

    // ---- Mapas de resolución nombre -> id ----
    $mapaClientes = [];
    foreach ($pdo->query("SELECT id, nombre_comercial FROM tbl_clientes WHERE state=1") as $r) {
        $mapaClientes[mb_strtoupper(trim($r['nombre_comercial']))] = (int)$r['id'];
    }
    $mapaCategorias = [];
    foreach ($pdo->query("SELECT id, nombre FROM tbl_soporte_categoria WHERE state=1") as $r) {
        $mapaCategorias[mb_strtoupper(trim($r['nombre']))] = (int)$r['id'];
    }
    $mapaTipos = [];
    foreach ($pdo->query("SELECT id, nombre FROM tbl_soporte_tipo WHERE state=1") as $r) {
        $mapaTipos[mb_strtoupper(trim($r['nombre']))] = (int)$r['id'];
    }
    $mapaPrioridades = [];
    foreach ($pdo->query("SELECT id, nombre FROM tbl_soporte_prioridad WHERE state=1") as $r) {
        $mapaPrioridades[mb_strtoupper(trim($r['nombre']))] = (int)$r['id'];
    }
    $mapaTecnicos = [];
    foreach ($pdo->query("SELECT id, nombre FROM tbl_admin_user WHERE state=1") as $r) {
        $mapaTecnicos[mb_strtoupper(trim($r['nombre']))] = (int)$r['id'];
    }
    $mapaEstados = [];
    foreach ($pdo->query("SELECT id, nombre FROM tbl_soporte_estado WHERE state=1") as $r) {
        $mapaEstados[mb_strtoupper(trim($r['nombre']))] = (int)$r['id'];
    }

    $config = $pdo->query("SELECT id, prefijo_ticket, siguiente_numero FROM tbl_configuracion ORDER BY id LIMIT 1")->fetch();
    $numeroActual = (int)$config['siguiente_numero'];
    $prefijo = $config['prefijo_ticket'];

    $tablaDestino = $modo === 'importar' ? 'tbl_soportes' : 'tbl_soportes_preview';
    $tipoImportId = $modo === 'importar' ? 2 : 1;

    if ($modo === 'previsualizar') {
        $pdo->exec("TRUNCATE TABLE tbl_soportes_preview");
    }

    $sqlImportIns = "INSERT INTO tbl_imports (id_import_type, table_name, fecha_hora_str, state, user_ing) VALUES (?,?,?,?,?)";
    $paramsImportIns = [$tipoImportId, $tablaDestino, date('Y-m-d H:i:s'), 1, $_SESSION['tsp_usuario_id']];
    $pdo->prepare($sqlImportIns)->execute($paramsImportIns);
    $importId = (int)$pdo->lastInsertId();
    registrarAuditoria($pdo, 'INS', 'tbl_imports', $importId, interpolarSql($pdo, $sqlImportIns, $paramsImportIns), 'Inicio de ' . ($modo === 'importar' ? 'importación' : 'previsualización') . ' de soportes desde Excel');

    $filasExitosas = [];
    $filasConError = [];
    $clientesAfectados = [];

    for ($i = 1; $i < count($filas); $i++) {
        $fila = $filas[$i];
        $numeroFilaExcel = $i + 1;

        if (count(array_filter($fila, fn($v) => trim((string)$v) !== '')) === 0) continue; // fila vacía, se ignora

        $obtener = fn($nombreCol) => trim((string)($fila[$colIndice[$nombreCol]] ?? ''));

        $clienteNombre = $obtener('CLIENTE');
        $solicitante = $obtener('SOLICITANTE');
        $descripcion = $obtener('DESCRIPCION');
        $analisis = $obtener('ANALISIS');
        $solucion = $obtener('SOLUCION');
        $observacion = $obtener('OBSERVACION');
        $recomendacion = $obtener('RECOMENDACION');
        $categoriaNombre = $obtener('CATEGORIA');
        $tipoNombre = $obtener('TIPO');
        $prioridadNombre = $obtener('PRIORIDAD');
        $tecnicoNombre = $obtener('TECNICO');
        $estadoNombre = $obtener('ESTADO');
        $valorHoraTexto = $obtener('VALOR HORA');

        $fecha = importarSoportesCombinarFechaHora($fila[$colIndice['FECHA REPORTADO']] ?? '', $fila[$colIndice['HORA REPORTADO']] ?? '');
        $fechaInicio = importarSoportesCombinarFechaHora($fila[$colIndice['FECHA DESDE']] ?? '', $fila[$colIndice['HORA DESDE']] ?? '');
        $fechaFin = importarSoportesCombinarFechaHora($fila[$colIndice['FECHA HASTA']] ?? '', $fila[$colIndice['HORA HASTA']] ?? '');

        $errores = [];
        $camposParaValidar = [
            'FECHA REPORTADO / HORA REPORTADO' => $fecha,
            'CLIENTE' => $clienteNombre,
            'SOLICITANTE' => $solicitante,
            'DESCRIPCION' => $descripcion,
            'SOLUCION' => $solucion,
            'CATEGORIA' => $categoriaNombre,
            'TIPO' => $tipoNombre,
            'PRIORIDAD' => $prioridadNombre,
            'TECNICO' => $tecnicoNombre,
            'ESTADO' => $estadoNombre,
            'FECHA DESDE / HORA DESDE' => $fechaInicio,
            'FECHA HASTA / HORA HASTA' => $fechaFin,
            'VALOR HORA' => $valorHoraTexto,
        ];
        foreach ($camposParaValidar as $campo => $valor) {
            if ($valor === null || $valor === '') $errores[] = "El campo \"$campo\" está vacío o no es válido.";
        }

        $clienteId = $categoriaId = $tipoId = $prioridadId = $tecnicoId = $estadoId = null;
        if ($clienteNombre !== '') {
            $clienteId = $mapaClientes[mb_strtoupper($clienteNombre)] ?? null;
            if (!$clienteId) $errores[] = "Cliente \"$clienteNombre\" no encontrado.";
        }
        if ($categoriaNombre !== '') {
            $categoriaId = $mapaCategorias[mb_strtoupper($categoriaNombre)] ?? null;
            if (!$categoriaId) $errores[] = "Categoría \"$categoriaNombre\" no encontrada.";
        }
        if ($tipoNombre !== '') {
            $tipoId = $mapaTipos[mb_strtoupper($tipoNombre)] ?? null;
            if (!$tipoId) $errores[] = "Tipo de atención \"$tipoNombre\" no encontrado.";
        }
        if ($prioridadNombre !== '') {
            $prioridadId = $mapaPrioridades[mb_strtoupper($prioridadNombre)] ?? null;
            if (!$prioridadId) $errores[] = "Prioridad \"$prioridadNombre\" no encontrada.";
        }
        if ($tecnicoNombre !== '') {
            $tecnicoId = $mapaTecnicos[mb_strtoupper($tecnicoNombre)] ?? null;
            if (!$tecnicoId) $errores[] = "Técnico \"$tecnicoNombre\" no encontrado.";
        }
        if ($estadoNombre !== '') {
            $estadoId = $mapaEstados[mb_strtoupper($estadoNombre)] ?? null;
            if ($estadoId === null) $errores[] = "Estado \"$estadoNombre\" no encontrado.";
        }

        $valorPorHora = is_numeric(str_replace(',', '.', $valorHoraTexto)) ? (float)str_replace(',', '.', $valorHoraTexto) : null;
        if ($valorHoraTexto !== '' && $valorPorHora === null) $errores[] = "El campo \"VALOR HORA\" no es numérico.";

        if (!$errores && $fechaInicio !== null && $fechaFin !== null) {
            $totalMinutos = (int) round((strtotime($fechaFin) - strtotime($fechaInicio)) / 60);
            if ($totalMinutos < 0) {
                $errores[] = 'La fecha/hora "HASTA" no puede ser anterior a la fecha/hora "DESDE".';
            }
        }

        if ($errores) {
            $filasConError[] = ['fila' => $numeroFilaExcel, 'mensajes' => $errores];
            continue;
        }

        $montoTotal = round(($valorPorHora / 60) * $totalMinutos, 2);
        $asunto = mb_substr($descripcion, 0, 200);
        $numero = $prefijo . str_pad((string)$numeroActual, 6, '0', STR_PAD_LEFT);

        $sqlIns = "INSERT INTO {$tablaDestino}
            (numero, fecha, id_tbl_clientes, solicitante, asunto, descripcion, analisis, solucion, observacion, recomendacion,
             id_tbl_soporte_categoria, id_tbl_soporte_tipo, id_tbl_soporte_prioridad, id_tbl_admin_user_asignado,
             fecha_hora_solved_str, fecha_hora_solved_end, total_minutos, valor_por_hora, monto_total, fecha_cierre,
             state, import_id, user_ing)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $paramsIns = [
            $numero, $fecha, $clienteId, $solicitante, $asunto, $descripcion, $analisis, $solucion, $observacion, $recomendacion,
            $categoriaId, $tipoId, $prioridadId, $tecnicoId,
            $fechaInicio, $fechaFin, $totalMinutos, $valorPorHora, $montoTotal, $fechaFin,
            $estadoId, $importId, $_SESSION['tsp_usuario_id'],
        ];

        try {
            $stmt = $pdo->prepare($sqlIns);
            $stmt->execute($paramsIns);
            $nuevoId = (int)$pdo->lastInsertId();
            if ($modo === 'importar') {
                registrarAuditoria($pdo, 'INS', 'tbl_soportes', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Importación de soporte desde Excel (import_id=' . $importId . ')');
                $clientesAfectados[$clienteId] = true;
            }
            $numeroActual++;
            $filasExitosas[] = [
                'fila' => $numeroFilaExcel, 'numero' => $numero, 'cliente' => $clienteNombre,
                'asunto' => $asunto, 'fecha' => $fecha, 'monto_total' => $montoTotal,
            ];
        } catch (\Throwable $e) {
            $filasConError[] = ['fila' => $numeroFilaExcel, 'mensajes' => ['Error al guardar: ' . $e->getMessage()]];
        }
    }

    if ($modo === 'importar' && $filasExitosas) {
        $pdo->prepare("UPDATE tbl_configuracion SET siguiente_numero = ? WHERE id = ?")->execute([$numeroActual, $config['id']]);
        foreach (array_keys($clientesAfectados) as $clienteId) {
            recalcularSaldoCliente($pdo, (int)$clienteId, 'Recálculo de saldo tras importar soportes desde Excel');
        }
    }

    $sqlImportUpd = "UPDATE tbl_imports SET fecha_hora_end = ? WHERE id = ?";
    $paramsImportUpd = [date('Y-m-d H:i:s'), $importId];
    $pdo->prepare($sqlImportUpd)->execute($paramsImportUpd);
    registrarAuditoria($pdo, 'UPD', 'tbl_imports', $importId, interpolarSql($pdo, $sqlImportUpd, $paramsImportUpd), 'Fin de ' . ($modo === 'importar' ? 'importación' : 'previsualización') . ' de soportes desde Excel');

    $resultado = [
        'modo' => $modo,
        'import_id' => $importId,
        'exitosas' => $filasExitosas,
        'errores' => $filasConError,
    ];
}

$tituloPagina = 'Importar Soportes';
$paginaActiva = 'importar_soportes';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-file-earmark-excel"></i> Importar Soportes desde Excel</h6>
            <p>
                Descargue la plantilla, complétela con sus datos (respetando los encabezados) y súbala aquí.
                Puede <strong>previsualizar</strong> primero (simula la carga y muestra errores sin afectar datos reales)
                o <strong>importar</strong> directamente (crea los soportes de verdad).
            </p>
            <a href="importar_soportes.php?accion=plantilla" class="btn btn-outline-tsp mb-3"><i class="bi bi-download"></i> Descargar plantilla Excel</a>

            <form method="post" enctype="multipart/form-data" id="formImportar">
                <div class="mb-3">
                    <label class="form-label">Archivo Excel (.xlsx)</label>
                    <input type="file" class="form-control" name="archivo" accept=".xlsx,.xls" required>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="accion" value="previsualizar" class="btn btn-outline-tsp"><i class="bi bi-eye"></i> Previsualizar</button>
                    <button type="submit" name="accion" value="importar" class="btn btn-tsp" onclick="return confirmarAccion('¿Importar los soportes de este archivo? Esta acción creará registros reales en el sistema.')"><i class="bi bi-cloud-upload"></i> Importar</button>
                </div>
            </form>

            <hr>
            <h6 class="text-muted small text-uppercase">Columnas requeridas en el Excel</h6>
            <p class="small text-muted mb-0">
                <?= implode(', ', array_map('limpiar', IMPORTAR_SOPORTES_COLUMNAS)) ?>.
                Todas las columnas son obligatorias en cada fila, excepto ANALISIS, OBSERVACION y RECOMENDACION, que pueden dejarse vacías.
                CLIENTE, CATEGORIA, TIPO, PRIORIDAD y TECNICO
                deben coincidir exactamente (sin distinguir mayúsculas/minúsculas) con un registro activo ya existente en el sistema.
            </p>
        </div>
    </div>

    <div class="col-lg-5">
        <?php if ($resultado): ?>
        <div class="card-panel">
            <h6 class="panel-title">
                <i class="bi bi-clipboard-check"></i>
                Resultado de <?= $resultado['modo'] === 'importar' ? 'la importación' : 'la previsualización' ?>
            </h6>
            <div class="alert alert-<?= $resultado['errores'] ? 'warning' : 'success' ?> py-2">
                <?= count($resultado['exitosas']) ?> fila(s) <?= $resultado['modo'] === 'importar' ? 'importadas' : 'válidas (previsualizadas)' ?> correctamente,
                <?= count($resultado['errores']) ?> fila(s) con errores.
            </div>

            <?php if ($resultado['exitosas']): ?>
            <h6 class="small text-uppercase text-muted">Filas procesadas correctamente</h6>
            <div class="table-responsive mb-3" style="max-height:260px; overflow-y:auto;">
                <table class="table table-sm table-tsp">
                    <thead><tr><th>Fila</th><th>N° Ticket</th><th>Cliente</th><th>Asunto</th><th>Monto</th></tr></thead>
                    <tbody>
                    <?php foreach ($resultado['exitosas'] as $ok): ?>
                        <tr>
                            <td><?= (int)$ok['fila'] ?></td>
                            <td><?= limpiar($ok['numero']) ?></td>
                            <td><?= limpiar($ok['cliente']) ?></td>
                            <td><?= limpiar($ok['asunto']) ?></td>
                            <td>$<?= number_format((float)$ok['monto_total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($resultado['modo'] === 'previsualizar'): ?>
                <p class="small text-muted">Estas filas se guardaron temporalmente en <code>tbl_soportes_preview</code> solo para revisión; no son soportes reales todavía.</p>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($resultado['errores']): ?>
            <h6 class="small text-uppercase text-muted">Filas con errores</h6>
            <div class="table-responsive" style="max-height:260px; overflow-y:auto;">
                <table class="table table-sm table-tsp">
                    <thead><tr><th>Fila</th><th>Errores</th></tr></thead>
                    <tbody>
                    <?php foreach ($resultado['errores'] as $err): ?>
                        <tr>
                            <td><?= (int)$err['fila'] ?></td>
                            <td class="small"><?= implode('<br>', array_map('limpiar', $err['mensajes'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
