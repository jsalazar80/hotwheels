<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(23);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'actualizar') {
    $clientes = $pdo->query("SELECT id FROM tbl_clientes WHERE state=1")->fetchAll(PDO::FETCH_COLUMN);

    try {
        $pdo->beginTransaction();
        foreach ($clientes as $clienteId) {
            recalcularSaldoCliente($pdo, (int)$clienteId, 'Recálculo manual de saldos desde "Actualizar saldos"');
        }
        $pdo->commit();
        redirigirConMensaje('actualizar_saldos.php', 'ok', 'Saldos actualizados correctamente para ' . count($clientes) . ' cliente(s).');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirigirConMensaje('actualizar_saldos.php', 'error', 'No se pudo actualizar los saldos: ' . $e->getMessage());
    }
}

$totalClientesActivos = (int)$pdo->query("SELECT COUNT(*) t FROM tbl_clientes WHERE state=1")->fetch()['t'];
$ultimaActualizacion = $pdo->query("SELECT MAX(fecha_hora) t FROM tbl_general_auditory WHERE tabla='tbl_saldos'")->fetch()['t'];

$tituloPagina = 'Actualizar Saldos';
$paginaActiva = 'actualizar_saldos';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-arrow-repeat"></i> Actualizar Saldos</h6>
            <p>
                Esta herramienta recalcula desde cero la información de la tabla <code>tbl_saldos</code>
                (total de soportes finalizados y total de pagos) para <strong>todos los clientes activos</strong>,
                útil si se sospecha que algún saldo quedó desactualizado.
            </p>
            <ul class="text-muted small">
                <li>Actualmente hay <strong><?= $totalClientesActivos ?></strong> cliente(s) activo(s).</li>
                <li>Última actualización registrada: <strong><?= $ultimaActualizacion ? formatoFechaHora($ultimaActualizacion) : 'N/D' ?></strong>.</li>
            </ul>
            <form method="post" onsubmit="return confirmarAccion('¿Actualizar los saldos de todos los clientes activos?')">
                <input type="hidden" name="accion" value="actualizar">
                <button class="btn btn-tsp" type="submit"><i class="bi bi-arrow-repeat"></i> Actualizar saldos</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
