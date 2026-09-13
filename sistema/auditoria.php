<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(7);

$tabla = trim($_GET['tabla'] ?? '');
$accion = trim($_GET['accion'] ?? '');
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

$sql = "SELECT * FROM tbl_general_auditory WHERE DATE(fecha_hora) BETWEEN ? AND ?";
$params = [$desde, $hasta];
if ($tabla !== '') { $sql .= " AND tabla = ?"; $params[] = $tabla; }
if ($accion !== '') { $sql .= " AND accion = ?"; $params[] = $accion; }
$sql .= " ORDER BY id DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

$tablasDisponibles = $pdo->query("SELECT DISTINCT tabla FROM tbl_general_auditory ORDER BY tabla ASC")->fetchAll(PDO::FETCH_COLUMN);

$tituloPagina = 'Auditoría';
$paginaActiva = 'auditoria';
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
            <label class="form-label small mb-0">Tabla</label>
            <select name="tabla" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach ($tablasDisponibles as $t): ?>
                    <option value="<?= limpiar($t) ?>" <?= $tabla===$t?'selected':'' ?>><?= limpiar($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-0">Acción</label>
            <select name="accion" class="form-select form-select-sm">
                <option value="">Todas</option>
                <option value="INS" <?= $accion==='INS'?'selected':'' ?>>Insertar</option>
                <option value="UPD" <?= $accion==='UPD'?'selected':'' ?>>Actualizar</option>
                <option value="DEL" <?= $accion==='DEL'?'selected':'' ?>>Eliminar</option>
                <option value="SEL" <?= $accion==='SEL'?'selected':'' ?>>Reporte</option>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <button class="btn btn-tsp btn-sm w-100" type="submit"><i class="bi bi-search"></i></button>
        </div>
    </form>
</div>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-clock-history"></i> Registros de Auditoría (<?= count($registros) ?> - máx. 500)</h6>
    <div class="table-responsive">
        <table class="table table-sm table-tsp align-middle">
            <thead><tr><th>Fecha/Hora</th><th>Usuario</th><th>Acción</th><th>Tabla</th><th>ID Registro</th><th>Observaciones</th><th>Consulta Ejecutada</th></tr></thead>
            <tbody>
            <?php foreach ($registros as $r): ?>
                <tr>
                    <td class="text-nowrap"><?= date('Y/m/d H:i:s', strtotime($r['fecha_hora'])) ?></td>
                    <td><?= limpiar($r['usuario']) ?></td>
                    <td>
                        <?php $badgeClase = $r['accion']==='INS' ? 'bg-success' : ($r['accion']==='UPD' ? 'bg-warning text-dark' : ($r['accion']==='DEL' ? 'bg-danger' : 'bg-info text-dark')); ?>
                        <span class="badge <?= $badgeClase ?>"><?= limpiar($r['accion']) ?></span>
                    </td>
                    <td><?= limpiar($r['tabla']) ?></td>
                    <td><?= limpiar($r['id_registro']) ?></td>
                    <td><?= limpiar($r['observaciones']) ?></td>
                    <td><code style="font-size:0.72rem;"><?= limpiar($r['query_ejecutado']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$registros): ?><tr><td colspan="7" class="text-center text-muted">No hay registros de auditoría en el rango seleccionado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
