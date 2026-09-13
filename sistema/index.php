<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirLogin();

$totUsuarios = $pdo->query("SELECT COUNT(*) t FROM tbl_admin_user WHERE state=1")->fetch()['t'];
$totPerfiles = $pdo->query("SELECT COUNT(*) t FROM tbl_profiles WHERE state=1")->fetch()['t'];
$totAccesosHoy = $pdo->query("SELECT COUNT(*) t FROM tbl_login WHERE DATE(fecha_hora) = CURDATE()")->fetch()['t'];
$totAuditoriaHoy = $pdo->query("SELECT COUNT(*) t FROM tbl_general_auditory WHERE DATE(fecha_hora) = CURDATE()")->fetch()['t'];

$actividadReciente = $pdo->query("SELECT usuario, accion, tabla, observaciones, fecha_hora
    FROM tbl_general_auditory ORDER BY id DESC LIMIT 8")->fetchAll();

// ---- Gráficos de pie: autos por marca / serie / tipo (top 5 + "Otras", <=6 porciones) ----
$pieMarcaTop = $pdo->query("SELECT m.nombre nombre, COUNT(*) n FROM tbl_hotwheels_carros c
    JOIN tbl_hotwheels_marcas m ON m.id = c.id_tbl_hotwheels_marcas
    WHERE c.state = 1 GROUP BY c.id_tbl_hotwheels_marcas ORDER BY n DESC LIMIT 5")->fetchAll();
$totMarcaAsignada = (int)$pdo->query("SELECT COUNT(*) t FROM tbl_hotwheels_carros WHERE state = 1 AND id_tbl_hotwheels_marcas IS NOT NULL")->fetch()['t'];
$otrasMarca = $totMarcaAsignada - array_sum(array_column($pieMarcaTop, 'n'));

$pieSerieTop = $pdo->query("SELECT s.nombre nombre, COUNT(*) n FROM tbl_hotwheels_carros c
    JOIN tbl_hotwheels_series s ON s.id = c.id_tbl_hotwheels_series
    WHERE c.state = 1 GROUP BY c.id_tbl_hotwheels_series ORDER BY n DESC LIMIT 5")->fetchAll();
$totSerieAsignada = (int)$pdo->query("SELECT COUNT(*) t FROM tbl_hotwheels_carros WHERE state = 1 AND id_tbl_hotwheels_series IS NOT NULL")->fetch()['t'];
$otrasSerie = $totSerieAsignada - array_sum(array_column($pieSerieTop, 'n'));

$pieTipoTop = $pdo->query("SELECT t.nombre nombre, COUNT(*) n FROM tbl_hotwheels_carros c
    JOIN tbl_hotwheels_tipos t ON t.id = c.id_tbl_hotwheels_tipos
    WHERE c.state = 1 GROUP BY c.id_tbl_hotwheels_tipos ORDER BY n DESC LIMIT 5")->fetchAll();
$totTipoAsignado = (int)$pdo->query("SELECT COUNT(*) t FROM tbl_hotwheels_carros WHERE state = 1 AND id_tbl_hotwheels_tipos IS NOT NULL")->fetch()['t'];
$otrasTipo = $totTipoAsignado - array_sum(array_column($pieTipoTop, 'n'));

$tituloPagina = 'Dashboard';
$paginaActiva = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <i class="bi bi-people-fill"></i>
            <div class="stat-label">Usuarios activos</div>
            <div class="stat-value"><?= (int)$totUsuarios ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <i class="bi bi-shield-lock"></i>
            <div class="stat-label">Perfiles registrados</div>
            <div class="stat-value"><?= (int)$totPerfiles ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <i class="bi bi-door-open"></i>
            <div class="stat-label">Accesos hoy</div>
            <div class="stat-value"><?= (int)$totAccesosHoy ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <i class="bi bi-journal-text"></i>
            <div class="stat-label">Acciones auditadas hoy</div>
            <div class="stat-value"><?= (int)$totAuditoriaHoy ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-pie-chart-fill"></i> Autos por Marca</h6>
            <canvas id="graficoPieMarca" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-pie-chart-fill"></i> Autos por Serie</h6>
            <canvas id="graficoPieSerie" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-pie-chart-fill"></i> Autos por Tipo</h6>
            <canvas id="graficoPieTipo" height="220"></canvas>
        </div>
    </div>
</div>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-clock-history"></i> Actividad Reciente</h6>
    <div class="table-responsive">
        <table class="table table-sm table-tsp align-middle">
            <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Tabla</th><th>Observaciones</th></tr></thead>
            <tbody>
            <?php foreach ($actividadReciente as $a): ?>
                <tr>
                    <td><?= formatoFechaHora($a['fecha_hora']) ?></td>
                    <td><?= limpiar($a['usuario']) ?></td>
                    <td><?= limpiar($a['accion']) ?></td>
                    <td><?= limpiar($a['tabla']) ?></td>
                    <td><?= limpiar($a['observaciones'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$actividadReciente): ?><tr><td colspan="5" class="text-center text-muted">Sin actividad registrada.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <a href="auditoria.php" class="btn btn-sm btn-outline-tsp mt-2"><i class="bi bi-list-ul"></i> Ver auditoría completa</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    // Paleta categórica fija (identidad, no ranking) + gris neutro para "Otras" (agregado, no una marca/serie/tipo real).
    const paletaCategorica = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];
    const colorOtras = '#898781';

    function crearPie(idCanvas, etiquetas, datos, colores) {
        const el = document.getElementById(idCanvas);
        if (!el) return;
        new Chart(el.getContext('2d'), {
            type: 'pie',
            data: { labels: etiquetas, datasets: [{ data: datos, backgroundColor: colores }] },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } },
                    tooltip: { callbacks: { label: function (ctx) {
                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                        const pct = total ? Math.round((ctx.parsed / total) * 100) : 0;
                        return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                    } } }
                }
            }
        });
    }

    function armarSerie(top, otras) {
        const etiquetas = top.map(function (r) { return r.nombre; });
        const datos = top.map(function (r) { return parseInt(r.n, 10); });
        const colores = paletaCategorica.slice(0, top.length);
        if (otras > 0) {
            etiquetas.push('Otras');
            datos.push(otras);
            colores.push(colorOtras);
        }
        return { etiquetas: etiquetas, datos: datos, colores: colores };
    }

    const marca = armarSerie(<?= json_encode($pieMarcaTop) ?>, <?= (int)$otrasMarca ?>);
    crearPie('graficoPieMarca', marca.etiquetas, marca.datos, marca.colores);

    const serie = armarSerie(<?= json_encode($pieSerieTop) ?>, <?= (int)$otrasSerie ?>);
    crearPie('graficoPieSerie', serie.etiquetas, serie.datos, serie.colores);

    const tipo = armarSerie(<?= json_encode($pieTipoTop) ?>, <?= (int)$otrasTipo ?>);
    crearPie('graficoPieTipo', tipo.etiquetas, tipo.datos, tipo.colores);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
