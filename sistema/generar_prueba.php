<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(21);

const GENERAR_PRUEBA_CANTIDAD = 100;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'generar') {
    $clientes = $pdo->query("SELECT id, valor_por_hora_ref FROM tbl_clientes WHERE state=1")->fetchAll();
    $categorias = $pdo->query("SELECT id FROM tbl_soporte_categoria WHERE state=1")->fetchAll(PDO::FETCH_COLUMN);
    $tipos = $pdo->query("SELECT id FROM tbl_soporte_tipo WHERE state=1")->fetchAll(PDO::FETCH_COLUMN);
    $prioridades = $pdo->query("SELECT id FROM tbl_soporte_prioridad WHERE state=1")->fetchAll(PDO::FETCH_COLUMN);
    $tecnicos = $pdo->query("SELECT id FROM tbl_admin_user WHERE state=1")->fetchAll(PDO::FETCH_COLUMN);

    if (!$clientes || !$prioridades || !$tecnicos) {
        redirigirConMensaje('generar_prueba.php', 'error', 'Se necesita al menos un cliente, una prioridad y un usuario activos para generar datos de prueba.');
    }

    $solicitantes = [
        'Ma. Alexandra Buchelli', 'Carlos Andrade', 'Katherine Rivera', 'Jorge Paredes',
        'Mónica Salazar', 'Fernando Vera', 'Lucía Espinoza', 'Roberto Zambrano',
        'Andrea Coronel', 'Diego Mendoza', 'Paola Cevallos', 'Xavier Ortega',
        'Gabriela Suárez', 'Marco Villacís', 'Verónica Chávez', 'Iván Rodríguez',
    ];
    $asuntosPorCategoria = [
        1 => ['Cambio de batería laptop', 'Formateo de equipo de escritorio', 'Instalación de impresora en red', 'Revisión de laptop lenta', 'Configuración de correo en equipo nuevo', 'Falla de encendido en PC'],
        2 => ['Ajuste en módulo de facturación', 'Corrección de reporte mensual', 'Nueva funcionalidad en sistema interno', 'Bug en formulario de pedidos', 'Actualización de librería del sistema'],
        3 => ['Revisión de marcador biométrico', 'Configuración de huellas nuevo personal', 'Falla en lector de acceso principal', 'Sincronización de reloj biométrico'],
        4 => ['Asesoría en migración a la nube', 'Consultoría de seguridad informática', 'Recomendación de equipos nuevos', 'Diagnóstico de red interna'],
        5 => ['Implementación de nueva sucursal', 'Cableado estructurado de oficina', 'Proyecto de cámaras de seguridad', 'Migración de servidor principal'],
        6 => ['Mantenimiento preventivo de servidores', 'Respaldo mensual de base de datos', 'Limpieza de equipos de cómputo', 'Revisión de UPS y respaldo eléctrico'],
    ];
    $asuntosGenericos = ['Revisión general de equipo', 'Soporte remoto solicitado por el cliente', 'Atención de incidente reportado'];
    $descripciones = [
        'El cliente reporta el inconveniente desde hace algunos días y solicita atención prioritaria.',
        'Se detectó el problema durante una revisión de rutina y se agenda visita técnica.',
        'Solicitud generada telefónicamente por el encargado de sistemas del cliente.',
        'El equipo presenta fallas intermitentes que afectan la operación diaria.',
        'Requerimiento habitual de mantenimiento programado.',
    ];
    $analisisPool = ['Se revisó el equipo y se identificó la causa raíz del problema.', 'Diagnóstico realizado in situ, se procede con la solución.', 'Análisis remoto confirma el origen de la falla reportada.'];
    $solucionPool = ['Se reemplazó el componente afectado y se realizaron pruebas de funcionamiento.', 'Se aplicó la corrección correspondiente y el cliente validó el resultado.', 'Se reconfiguró el equipo/servicio y quedó operativo.'];
    $recomendacionPool = ['Se recomienda mantenimiento preventivo cada 6 meses.', 'Se sugiere renovar el equipo en el próximo período fiscal.', 'Se recomienda capacitar al personal en el uso correcto del sistema.'];

    $estadosDistribucion = [
        ESTADO_SOPORTE_ANULADO => 5,
        ESTADO_SOPORTE_REPORTADO => 12,
        ESTADO_SOPORTE_EN_PROCESO => 13,
        ESTADO_SOPORTE_SOLUCIONADO => 15,
        ESTADO_SOPORTE_FINALIZADO => 55,
    ];
    $bolsaEstados = [];
    foreach ($estadosDistribucion as $estado => $peso) {
        for ($k = 0; $k < $peso; $k++) $bolsaEstados[] = $estado;
    }

    try {
        $pdo->beginTransaction();

        $config = $pdo->query("SELECT id, prefijo_ticket, siguiente_numero FROM tbl_configuracion ORDER BY id LIMIT 1")->fetch();
        if (!$config) throw new Exception('No se encontró la configuración de numeración de tickets.');
        $prefijo = $config['prefijo_ticket'];
        $numeroActual = (int)$config['siguiente_numero'];

        $idsCreados = [];
        for ($i = 0; $i < GENERAR_PRUEBA_CANTIDAD; $i++) {
            $cliente = $clientes[array_rand($clientes)];
            $clienteId = (int)$cliente['id'];
            $valorHora = (float)$cliente['valor_por_hora_ref'];
            if ($valorHora <= 0) $valorHora = random_int(15, 45);

            $categoriaId = $categorias && random_int(1, 100) <= 90 ? (int)$categorias[array_rand($categorias)] : null;
            $tipoId = $tipos && random_int(1, 100) <= 90 ? (int)$tipos[array_rand($tipos)] : null;
            $prioridadId = (int)$prioridades[array_rand($prioridades)];
            $tecnicoId = random_int(1, 100) <= 80 ? (int)$tecnicos[array_rand($tecnicos)] : null;
            $estado = $bolsaEstados[array_rand($bolsaEstados)];

            $asuntosDisponibles = $categoriaId && isset($asuntosPorCategoria[$categoriaId])
                ? $asuntosPorCategoria[$categoriaId]
                : $asuntosGenericos;
            $asunto = $asuntosDisponibles[array_rand($asuntosDisponibles)];
            $descripcion = $descripciones[array_rand($descripciones)];
            $solicitante = $solicitantes[array_rand($solicitantes)];

            $fechaBase = date('Y-m-d', strtotime('-' . random_int(0, 240) . ' days'));
            $fecha = $fechaBase . ' ' . str_pad((string)random_int(8, 18), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)random_int(0, 59), 2, '0', STR_PAD_LEFT) . ':00';
            $numero = $prefijo . str_pad((string)$numeroActual, 6, '0', STR_PAD_LEFT);
            $numeroActual++;

            $cerrado = in_array($estado, [ESTADO_SOPORTE_SOLUCIONADO, ESTADO_SOPORTE_FINALIZADO], true);
            $inicio = $fin = $fechaCierre = null;
            $analisis = $solucion = $recomendacion = '';
            if ($cerrado) {
                $inicio = $fecha;
                $fin = date('Y-m-d H:i:s', strtotime($inicio . ' + ' . random_int(20, 240) . ' minutes'));
                $fechaCierre = $fin;
                $analisis = $analisisPool[array_rand($analisisPool)];
                $solucion = $solucionPool[array_rand($solucionPool)];
                $recomendacion = random_int(1, 100) <= 50 ? $recomendacionPool[array_rand($recomendacionPool)] : '';
            }

            $sqlIns = "INSERT INTO tbl_soportes
                (numero, fecha, id_tbl_clientes, solicitante, asunto, descripcion, analisis, solucion, observacion, recomendacion,
                 id_tbl_soporte_categoria, id_tbl_soporte_tipo, id_tbl_soporte_prioridad, id_tbl_admin_user_asignado,
                 fecha_hora_solved_str, fecha_hora_solved_end, valor_por_hora, fecha_cierre, state, user_ing)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $paramsIns = [
                $numero, $fecha, $clienteId, $solicitante, $asunto, $descripcion, $analisis, $solucion, '', $recomendacion,
                $categoriaId, $tipoId, $prioridadId, $tecnicoId,
                $inicio, $fin, $valorHora, $fechaCierre, $estado, $tecnicoId ?: (int)$tecnicos[array_rand($tecnicos)],
            ];
            $stmt = $pdo->prepare($sqlIns);
            $stmt->execute($paramsIns);
            $nuevoId = (int)$pdo->lastInsertId();
            registrarAuditoria($pdo, 'INS', 'tbl_soportes', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Generación de datos de prueba (soporte aleatorio)');

            if ($cerrado) {
                recalcularTiempoSoporte($pdo, $nuevoId, 'Recálculo de tiempo y costo (datos de prueba)');
            }
            $idsCreados[] = $nuevoId;
        }

        $pdo->prepare("UPDATE tbl_configuracion SET siguiente_numero = ? WHERE id = ?")->execute([$numeroActual, $config['id']]);

        foreach ($clientes as $cl) {
            recalcularSaldoCliente($pdo, (int)$cl['id'], 'Recálculo de saldo tras generar datos de prueba (soportes)');
        }

        $pdo->commit();
        redirigirConMensaje('generar_prueba.php', 'ok', count($idsCreados) . ' soportes de prueba generados correctamente.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirigirConMensaje('generar_prueba.php', 'error', 'No se pudo generar los datos: ' . $e->getMessage());
    }
}

$totalSoportesActual = (int)$pdo->query("SELECT COUNT(*) t FROM tbl_soportes")->fetch()['t'];

$tituloPagina = 'Generar Datos de Prueba';
$paginaActiva = 'generar_prueba';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-magic"></i> Generar Soportes de Prueba</h6>
            <p>
                Esta herramienta crea <strong><?= GENERAR_PRUEBA_CANTIDAD ?> solicitudes de soporte aleatorias</strong>
                (cliente, categoría, tipo, prioridad, técnico, fechas y estado al azar) usando los clientes y catálogos
                ya registrados en el sistema, útil para pruebas y demostraciones.
            </p>
            <ul class="text-muted small">
                <li>Los tickets Solucionado/Finalizado incluyen inicio/fin de atención y su monto se calcula automáticamente.</li>
                <li>El número de ticket continúa la numeración real configurada en <a href="cnf_empresa.php">Datos de la Empresa</a>.</li>
                <li>El saldo de cada cliente se recalcula al finalizar.</li>
                <li>Actualmente hay <strong><?= $totalSoportesActual ?></strong> soporte(s) registrados en el sistema.</li>
            </ul>
            <form method="post" onsubmit="return confirmarAccion('¿Generar <?= GENERAR_PRUEBA_CANTIDAD ?> soportes aleatorios? Esta acción insertará registros reales en la base de datos.')">
                <input type="hidden" name="accion" value="generar">
                <button class="btn btn-tsp" type="submit"><i class="bi bi-magic"></i> Generar <?= GENERAR_PRUEBA_CANTIDAD ?> soportes aleatorios</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
