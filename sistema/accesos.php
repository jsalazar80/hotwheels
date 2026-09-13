<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(8);

$usuario = trim($_GET['usuario'] ?? '');
$observacion = trim($_GET['observacion'] ?? '');
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$sql = "SELECT * FROM tbl_login WHERE DATE(fecha_hora) BETWEEN ? AND ?";
$params = [$desde, $hasta];
if ($usuario !== '') { $sql .= " AND usuario LIKE ?"; $params[] = "%$usuario%"; }
if ($observacion !== '') { $sql .= " AND observacion = ?"; $params[] = $observacion; }
$sql .= " ORDER BY id DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$eventos = $stmt->fetchAll();

$tituloPagina = 'Accesos al Sistema';
$paginaActiva = 'accesos';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
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
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">Usuario</label>
            <input type="text" name="usuario" class="form-control form-control-sm" value="<?= limpiar($usuario) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Evento</label>
            <select name="observacion" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="LOGIN" <?= $observacion==='LOGIN'?'selected':'' ?>>Login</option>
                <option value="LOGOUT" <?= $observacion==='LOGOUT'?'selected':'' ?>>Logout</option>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <button class="btn btn-tsp btn-sm w-100" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-door-open"></i> Eventos de Acceso (<?= count($eventos) ?> - máx. 500)</h6>
    <div class="table-responsive">
        <table class="table table-sm table-tsp align-middle">
            <thead><tr><th>Fecha/Hora</th><th>Usuario</th><th>Evento</th><th>IP</th><th>Navegador</th><th>Dispositivo</th></tr></thead>
            <tbody>
            <?php foreach ($eventos as $e): ?>
                <tr>
                    <td class="text-nowrap"><?= date('Y/m/d H:i:s', strtotime($e['fecha_hora'])) ?></td>
                    <td><?= limpiar($e['usuario']) ?></td>
                    <td><span class="badge <?= $e['observacion']==='LOGIN'?'bg-success':'bg-secondary' ?>"><?= limpiar($e['observacion']) ?></span></td>
                    <td><?= limpiar($e['ip']) ?></td>
                    <td><?= limpiar($e['navegador']) ?></td>
                    <td><?= limpiar($e['dispositivo']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$eventos): ?><tr><td colspan="6" class="text-center text-muted">No hay eventos de acceso en el rango seleccionado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
