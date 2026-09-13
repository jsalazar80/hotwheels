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

<?php include __DIR__ . '/includes/footer.php'; ?>
