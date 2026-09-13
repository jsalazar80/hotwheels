<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(19);

$busqueda = trim($_GET['q'] ?? '');

$sql = "SELECT cl.id, cl.nombre_comercial, cl.razon_social, cl.rucci, cl.correo,
        COALESCE(s.total_soportes, 0) total_soportes,
        COALESCE(s.total_pagos, 0) total_pagos,
        COALESCE(s.total_soportes, 0) - COALESCE(s.total_pagos, 0) saldo
        FROM tbl_clientes cl
        LEFT JOIN tbl_saldos s ON s.id_tbl_clientes = cl.id
        WHERE cl.state = 1";
$params = [];
if ($busqueda !== '') {
    $sql .= " AND (cl.nombre_comercial LIKE ? OR cl.razon_social LIKE ? OR cl.rucci LIKE ?)";
    $params[] = "%$busqueda%"; $params[] = "%$busqueda%"; $params[] = "%$busqueda%";
}
$sql .= " ORDER BY saldo DESC, cl.nombre_comercial ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$saldos = $stmt->fetchAll();

$totalSoportes = array_sum(array_column($saldos, 'total_soportes'));
$totalPagos = array_sum(array_column($saldos, 'total_pagos'));
$totalSaldo = $totalSoportes - $totalPagos;

$tituloPagina = 'Saldos';
$paginaActiva = 'saldos';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>

<div class="row g-3 mb-2">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <i class="bi bi-life-preserver"></i>
            <div class="stat-label">Total facturado (soportes finalizados)</div>
            <div class="stat-value">$<?= number_format($totalSoportes, 2) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <i class="bi bi-cash-coin"></i>
            <div class="stat-label">Total pagado</div>
            <div class="stat-value">$<?= number_format($totalPagos, 2) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="stat-card">
            <i class="bi bi-exclamation-circle"></i>
            <div class="stat-label">Saldo pendiente total</div>
            <div class="stat-value <?= $totalSaldo > 0 ? 'rojo' : '' ?>">$<?= number_format($totalSaldo, 2) ?></div>
        </div>
    </div>
</div>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-search"></i> Buscar</h6>
    <form method="get" class="row g-2 align-items-end">
        <div class="col-12 col-md-6">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por nombre, razón social o RUC/cédula..." value="<?= limpiar($busqueda) ?>">
        </div>
        <div class="col-6 col-md-2">
            <button class="btn btn-tsp btn-sm w-100" type="submit"><i class="bi bi-search"></i> Buscar</button>
        </div>
        <div class="col-6 col-md-2">
            <a href="saldos.php" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a>
        </div>
    </form>
</div>

<div class="card-panel">
    <h6 class="panel-title"><i class="bi bi-cash-stack"></i> Saldo por Cliente (<?= count($saldos) ?>)</h6>
    <div class="table-responsive">
        <table class="table table-sm table-tsp align-middle">
            <thead><tr><th>Cliente</th><th>RUC/Cédula</th><th>Total Soportes</th><th>Total Pagos</th><th>Saldo</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($saldos as $s): ?>
                <tr>
                    <td>
                        <?= limpiar($s['nombre_comercial']) ?>
                        <?php if ($s['razon_social']): ?><div class="small text-muted"><?= limpiar($s['razon_social']) ?></div><?php endif; ?>
                    </td>
                    <td><?= limpiar($s['rucci']) ?></td>
                    <td>$<?= number_format($s['total_soportes'], 2) ?></td>
                    <td>$<?= number_format($s['total_pagos'], 2) ?></td>
                    <td>
                        <?php if ($s['saldo'] > 0.009): ?>
                            <span class="badge bg-danger">$<?= number_format($s['saldo'], 2) ?></span>
                        <?php elseif ($s['saldo'] < -0.009): ?>
                            <span class="badge bg-info text-dark">-$<?= number_format(abs($s['saldo']), 2) ?> (a favor)</span>
                        <?php else: ?>
                            <span class="badge bg-success">$0.00</span>
                        <?php endif; ?>
                    </td>
                    <td><a href="pagos.php?cliente=<?= $s['id'] ?>" class="btn btn-sm btn-outline-tsp"><i class="bi bi-cash-coin"></i> Ver pagos</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$saldos): ?><tr><td colspan="6" class="text-center text-muted">No se encontraron clientes.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
