<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirLogin();

$totAbiertos = $pdo->query("SELECT COUNT(*) t FROM tbl_soportes WHERE state IN (" . ESTADO_SOPORTE_REPORTADO . "," . ESTADO_SOPORTE_EN_PROCESO . ")")->fetch()['t'];
$totCerrados = $pdo->query("SELECT COUNT(*) t FROM tbl_soportes WHERE state IN (" . ESTADO_SOPORTE_SOLUCIONADO . "," . ESTADO_SOPORTE_FINALIZADO . ")")->fetch()['t'];
$totClientes = $pdo->query("SELECT COUNT(*) t FROM tbl_clientes WHERE state=1")->fetch()['t'];
$totHoy = $pdo->query("SELECT COUNT(*) t FROM tbl_soportes WHERE DATE(fecha) = CURDATE() AND state != " . ESTADO_SOPORTE_ANULADO)->fetch()['t'];

$totSoportesFacturado = $pdo->query("SELECT COALESCE(SUM(total_soportes),0) t FROM tbl_saldos WHERE state=1")->fetch()['t'];
$totPagosGeneral = $pdo->query("SELECT COALESCE(SUM(total_pagos),0) t FROM tbl_saldos WHERE state=1")->fetch()['t'];
$totSaldoGeneral = $totSoportesFacturado - $totPagosGeneral;

$porEstado = $pdo->query("SELECT e.nombre, e.color, e.icono, COUNT(s.id) n FROM tbl_soporte_estado e
    LEFT JOIN tbl_soportes s ON s.state = e.id
    WHERE e.state=1 GROUP BY e.id ORDER BY e.id ASC")->fetchAll();

$porPrioridad = $pdo->query("SELECT p.nombre, p.color, COUNT(s.id) n FROM tbl_soporte_prioridad p
    LEFT JOIN tbl_soportes s ON s.id_tbl_soporte_prioridad = p.id AND s.state IN (" . ESTADO_SOPORTE_REPORTADO . "," . ESTADO_SOPORTE_EN_PROCESO . ")
    WHERE p.state=1 GROUP BY p.id ORDER BY p.orden ASC")->fetchAll();

$recientes = $pdo->query("SELECT s.*, cl.nombre_comercial cliente, e.nombre estado_nombre, e.color estado_color, e.icono estado_icono,
    p.nombre prioridad_nombre, p.color prioridad_color, u.nombre tecnico_nombre
    FROM tbl_soportes s
    JOIN tbl_clientes cl ON cl.id = s.id_tbl_clientes
    JOIN tbl_soporte_estado e ON e.id = s.state
    JOIN tbl_soporte_prioridad p ON p.id = s.id_tbl_soporte_prioridad
    LEFT JOIN tbl_admin_user u ON u.id = s.id_tbl_admin_user_asignado
    WHERE s.state != " . ESTADO_SOPORTE_ANULADO . " ORDER BY s.id DESC LIMIT 8")->fetchAll();

// ---- Gráficos de pie (soportes, excluyendo anulados) ----
$pieCliente = $pdo->query("SELECT cl.nombre_comercial nombre, COUNT(s.id) n FROM tbl_soportes s
    JOIN tbl_clientes cl ON cl.id = s.id_tbl_clientes
    WHERE s.state != " . ESTADO_SOPORTE_ANULADO . "
    GROUP BY s.id_tbl_clientes ORDER BY n DESC LIMIT 10")->fetchAll();

$pieCategoria = $pdo->query("SELECT cat.nombre nombre, COUNT(s.id) n FROM tbl_soportes s
    JOIN tbl_soporte_categoria cat ON cat.id = s.id_tbl_soporte_categoria
    WHERE s.state != " . ESTADO_SOPORTE_ANULADO . "
    GROUP BY s.id_tbl_soporte_categoria ORDER BY n DESC")->fetchAll();

$pieTipo = $pdo->query("SELECT t.nombre nombre, COUNT(s.id) n FROM tbl_soportes s
    JOIN tbl_soporte_tipo t ON t.id = s.id_tbl_soporte_tipo
    WHERE s.state != " . ESTADO_SOPORTE_ANULADO . "
    GROUP BY s.id_tbl_soporte_tipo ORDER BY n DESC")->fetchAll();

$piePrioridad = $pdo->query("SELECT p.nombre nombre, COUNT(s.id) n FROM tbl_soportes s
    JOIN tbl_soporte_prioridad p ON p.id = s.id_tbl_soporte_prioridad
    WHERE s.state != " . ESTADO_SOPORTE_ANULADO . "
    GROUP BY s.id_tbl_soporte_prioridad ORDER BY n DESC")->fetchAll();

$pieEstado = array_map(fn($e) => ['nombre' => $e['nombre'], 'n' => (int)$e['n']], $porEstado);

$doughnutRecaudacion = $pdo->query("SELECT cl.nombre_comercial nombre, sd.total_pagos monto FROM tbl_saldos sd
    JOIN tbl_clientes cl ON cl.id = sd.id_tbl_clientes
    WHERE sd.state = 1 AND sd.total_pagos > 0
    ORDER BY sd.total_pagos DESC LIMIT 10")->fetchAll();

// ---- Gráficos de línea anuales ----
$anioSeleccionado = (int)($_GET['anio'] ?? date('Y'));

$aniosSoportes = $pdo->query("SELECT DISTINCT YEAR(fecha) a FROM tbl_soportes")->fetchAll(PDO::FETCH_COLUMN);
$aniosPagos = $pdo->query("SELECT DISTINCT YEAR(fecha) a FROM tbl_pagos")->fetchAll(PDO::FETCH_COLUMN);
$aniosDisponibles = array_unique(array_merge($aniosSoportes, $aniosPagos, [(int)date('Y')]));
rsort($aniosDisponibles);
if (!$aniosDisponibles) $aniosDisponibles = [(int)date('Y')];

$stmtSoportesMes = $pdo->prepare("SELECT MONTH(fecha) mes, COUNT(*) n FROM tbl_soportes
    WHERE YEAR(fecha) = ? AND state != " . ESTADO_SOPORTE_ANULADO . " GROUP BY MONTH(fecha)");
$stmtSoportesMes->execute([$anioSeleccionado]);
$datosSoportesPorMes = $stmtSoportesMes->fetchAll(PDO::FETCH_KEY_PAIR);

$stmtPagosMes = $pdo->prepare("SELECT MONTH(fecha) mes, COALESCE(SUM(monto),0) t FROM tbl_pagos
    WHERE YEAR(fecha) = ? AND state = 1 GROUP BY MONTH(fecha)");
$stmtPagosMes->execute([$anioSeleccionado]);
$datosPagosPorMes = $stmtPagosMes->fetchAll(PDO::FETCH_KEY_PAIR);

$nombresMeses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$serieSoportes = [];
$seriePagos = [];
foreach ($nombresMeses as $i => $m) {
    $mesNum = $i + 1;
    $serieSoportes[] = (int)($datosSoportesPorMes[$mesNum] ?? 0);
    $seriePagos[] = round((float)($datosPagosPorMes[$mesNum] ?? 0), 2);
}

$tituloPagina = 'Dashboard';
$paginaActiva = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <i class="bi bi-life-preserver"></i>
            <div class="stat-label">Tickets abiertos</div>
            <div class="stat-value rojo"><?= (int)$totAbiertos ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <i class="bi bi-check-circle"></i>
            <div class="stat-label">Tickets cerrados</div>
            <div class="stat-value"><?= (int)$totCerrados ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <i class="bi bi-people-fill"></i>
            <div class="stat-label">Clientes activos</div>
            <div class="stat-value"><?= (int)$totClientes ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <i class="bi bi-calendar-check"></i>
            <div class="stat-label">Registrados hoy</div>
            <div class="stat-value"><?= (int)$totHoy ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <i class="bi bi-life-preserver"></i>
            <div class="stat-label">Total facturado (soportes finalizados)</div>
            <div class="stat-value">$<?= number_format($totSoportesFacturado, 2) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <i class="bi bi-cash-coin"></i>
            <div class="stat-label">Total pagado</div>
            <div class="stat-value">$<?= number_format($totPagosGeneral, 2) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <a href="saldos.php" class="text-decoration-none">
            <div class="stat-card h-100">
                <i class="bi bi-exclamation-circle"></i>
                <div class="stat-label">Saldo pendiente total</div>
                <div class="stat-value <?= $totSaldoGeneral > 0 ? 'rojo' : '' ?>">$<?= number_format($totSaldoGeneral, 2) ?></div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3 mb-2">
    <div class="col-12">
        <a href="soportes.php" class="btn btn-tsp w-100 py-3 d-flex align-items-center justify-content-center fs-5 fw-bold">
            <i class="bi bi-plus-circle me-2"></i> Registrar Nueva Solicitud de Soporte
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-pie-chart"></i> Tickets por Estado</h6>
            <div class="row g-2 text-center">
                <?php foreach ($porEstado as $e): ?>
                    <div class="col-6 col-md-3">
                        <div class="p-2 border rounded">
                            <span class="badge w-100 mb-1" style="background-color:<?= limpiar($e['color']) ?>;"><i class="bi <?= limpiar($e['icono']) ?>"></i> <?= limpiar($e['nombre']) ?></span>
                            <div class="fs-4 fw-bold text-tsp"><?= (int)$e['n'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-exclamation-diamond"></i> Tickets Abiertos por Prioridad</h6>
            <div class="row g-2 text-center">
                <?php foreach ($porPrioridad as $p): ?>
                    <div class="col-6 col-md-3">
                        <div class="p-2 border rounded">
                            <span class="badge bg-<?= limpiar($p['color']) ?> w-100 mb-1"><?= limpiar($p['nombre']) ?></span>
                            <div class="fs-4 fw-bold text-tsp"><?= (int)$p['n'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card-panel">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <h6 class="panel-title mb-0 border-0 pb-0"><i class="bi bi-graph-up"></i> Evolución Anual (<?= $anioSeleccionado ?>)</h6>
        <form method="get" class="d-flex align-items-center gap-2">
            <label class="form-label small mb-0">Año:</label>
            <select name="anio" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <?php foreach ($aniosDisponibles as $anio): ?>
                    <option value="<?= (int)$anio ?>" <?= $anio==$anioSeleccionado?'selected':'' ?>><?= (int)$anio ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="text-center small text-muted mb-1">Cantidad de soportes por mes</div>
            <canvas id="graficoSoportesAnual" height="200"></canvas>
        </div>
        <div class="col-lg-6">
            <div class="text-center small text-muted mb-1">Monto de pagos por mes ($)</div>
            <canvas id="graficoPagosAnual" height="200"></canvas>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-pie-chart-fill"></i> Soportes por Cliente</h6>
            <canvas id="graficoPieCliente" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-pie-chart-fill"></i> Soportes por Categoría</h6>
            <canvas id="graficoPieCategoria" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-pie-chart-fill"></i> Soportes por Tipo de Atención</h6>
            <canvas id="graficoPieTipo" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-pie-chart-fill"></i> Soportes por Prioridad</h6>
            <canvas id="graficoPiePrioridad" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-pie-chart-fill"></i> Soportes por Estado</h6>
            <canvas id="graficoPieEstado" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-cash-stack"></i> Recaudación por Cliente</h6>
            <canvas id="graficoDoughnutRecaudacion" height="220"></canvas>
        </div>
    </div>
</div>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-clock-history"></i> Solicitudes Recientes</h6>
    <div class="table-responsive">
        <table class="table table-sm table-tsp align-middle">
            <thead><tr><th>N°</th><th>Fecha</th><th>Cliente</th><th>Asunto</th><th>Prioridad</th><th>Estado</th><th>Técnico</th></tr></thead>
            <tbody>
            <?php foreach ($recientes as $r): ?>
                <tr>
                    <td><?= limpiar($r['numero']) ?></td>
                    <td><?= formatoFecha($r['fecha']) ?></td>
                    <td><?= limpiar($r['cliente']) ?></td>
                    <td><?= limpiar($r['asunto']) ?></td>
                    <td><span class="badge bg-<?= limpiar($r['prioridad_color']) ?>"><?= limpiar($r['prioridad_nombre']) ?></span></td>
                    <td><span class="badge" style="background-color:<?= limpiar($r['estado_color']) ?>;"><i class="bi <?= limpiar($r['estado_icono']) ?>"></i> <?= limpiar($r['estado_nombre']) ?></span></td>
                    <td><?= limpiar($r['tecnico_nombre'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recientes): ?><tr><td colspan="7" class="text-center text-muted">Sin solicitudes registradas.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <a href="soportes.php" class="btn btn-sm btn-outline-tsp mt-2"><i class="bi bi-list-ul"></i> Ver todas las solicitudes</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const meses = <?= json_encode($nombresMeses) ?>;
    const serieSoportes = <?= json_encode($serieSoportes) ?>;
    const seriePagos = <?= json_encode($seriePagos) ?>;

    new Chart(document.getElementById('graficoSoportesAnual').getContext('2d'), {
        type: 'line',
        data: {
            labels: meses,
            datasets: [{
                label: 'Soportes',
                data: serieSoportes,
                borderColor: '#17417a',
                backgroundColor: 'rgba(23, 65, 122, 0.12)',
                borderWidth: 2.5,
                pointBackgroundColor: '#17417a',
                pointRadius: 3,
                tension: 0.3,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    new Chart(document.getElementById('graficoPagosAnual').getContext('2d'), {
        type: 'line',
        data: {
            labels: meses,
            datasets: [{
                label: 'Pagos ($)',
                data: seriePagos,
                borderColor: '#f0803c',
                backgroundColor: 'rgba(240, 128, 60, 0.12)',
                borderWidth: 2.5,
                pointBackgroundColor: '#f0803c',
                pointRadius: 3,
                tension: 0.3,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => '$' + c.parsed.y.toLocaleString('es', {minimumFractionDigits: 2}) } }
            },
            scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString('es') } } }
        }
    });

    const paletaColores = ['#17417a', '#2563eb', '#f0803c', '#198754', '#dc3545', '#fd7e14', '#6c757d', '#0dcaf0', '#6610f2', '#20c997'];

    function crearPie(idCanvas, etiquetas, datos, tipo) {
        const el = document.getElementById(idCanvas);
        if (!el) return;
        new Chart(el.getContext('2d'), {
            type: tipo || 'pie',
            data: {
                labels: etiquetas,
                datasets: [{ data: datos, backgroundColor: paletaColores }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } }
            }
        });
    }

    crearPie('graficoPieCliente', <?= json_encode(array_column($pieCliente, 'nombre')) ?>, <?= json_encode(array_map('intval', array_column($pieCliente, 'n'))) ?>);
    crearPie('graficoPieCategoria', <?= json_encode(array_column($pieCategoria, 'nombre')) ?>, <?= json_encode(array_map('intval', array_column($pieCategoria, 'n'))) ?>);
    crearPie('graficoPieTipo', <?= json_encode(array_column($pieTipo, 'nombre')) ?>, <?= json_encode(array_map('intval', array_column($pieTipo, 'n'))) ?>);
    crearPie('graficoPiePrioridad', <?= json_encode(array_column($piePrioridad, 'nombre')) ?>, <?= json_encode(array_map('intval', array_column($piePrioridad, 'n'))) ?>, 'doughnut');
    crearPie('graficoPieEstado', <?= json_encode(array_column($pieEstado, 'nombre')) ?>, <?= json_encode(array_map('intval', array_column($pieEstado, 'n'))) ?>, 'doughnut');

    const elDoughnutRecaudacion = document.getElementById('graficoDoughnutRecaudacion');
    if (elDoughnutRecaudacion) {
        new Chart(elDoughnutRecaudacion.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($doughnutRecaudacion, 'nombre')) ?>,
                datasets: [{
                    data: <?= json_encode(array_map('floatval', array_column($doughnutRecaudacion, 'monto'))) ?>,
                    backgroundColor: paletaColores,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } },
                    tooltip: { callbacks: { label: c => c.label + ': $' + c.parsed.toLocaleString('es', {minimumFractionDigits: 2}) } }
                }
            }
        });
    }
})();

// Refresca el Dashboard automáticamente para reflejar soportes/pagos creados o editados
// desde otra pantalla o sesión (recarga la misma URL, conservando el filtro de año).
setInterval(function () {
    window.location.reload();
}, 30000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
