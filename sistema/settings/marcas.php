<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso(31);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');

    if ($nombre === '') {
        redirigirConMensaje('marcas.php', 'error', 'El nombre es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_hotwheels_marcas SET nombre=? WHERE id=?";
        $paramsUpd = [$nombre, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_marcas', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de marca');
        redirigirConMensaje('marcas.php', 'ok', 'Marca actualizada.');
    } else {
        $sqlIns = "INSERT INTO tbl_hotwheels_marcas (nombre, user_ing, fecha_hora_ing) VALUES (?,?,NOW())";
        $paramsIns = [$nombre, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_hotwheels_marcas', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nueva marca');
        redirigirConMensaje('marcas.php', 'ok', 'Marca registrada.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'subir_logo') {
    $idLogo = (int)($_POST['id'] ?? 0);
    $ok = $idLogo > 0 && procesarLogoMarca($pdo, $idLogo, $_SESSION['tsp_usuario_id']);
    redirigirConMensaje('marcas.php', $ok ? 'ok' : 'error', $ok ? 'Logo actualizado.' : 'No se pudo subir el logo (verifique que sea PNG, JPG o JPEG).');
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_hotwheels_marcas SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_marcas', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) de la marca');
    redirigirConMensaje('marcas.php', 'ok', 'Estado actualizado.');
}

[$pagina, $porPagina, $offset] = obtenerPaginacion();
$totalMarcas = (int)$pdo->query("SELECT COUNT(*) t FROM tbl_hotwheels_marcas")->fetch()['t'];
$marcas = $pdo->query("SELECT * FROM tbl_hotwheels_marcas ORDER BY nombre ASC LIMIT $porPagina OFFSET $offset")->fetchAll();

$tituloPagina = 'Marcas';
$paginaActiva = 'marcas';
include __DIR__ . '/../includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-tags"></i> Nueva Marca</h6>
            <form method="post" id="formMarca" action="settings/marcas.php">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="mar_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="mar_nombre" class="form-control" required>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Marcas Registradas</h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th style="width:50px;">Logo</th><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($marcas as $m): ?>
                        <?php $rutaLogo = resolverRutaMiniaturaLogoMarca($m['id']) ?: resolverRutaLogoMarca($m['id']); ?>
                        <tr>
                            <td>
                                <form method="post" enctype="multipart/form-data" action="settings/marcas.php">
                                    <input type="hidden" name="accion" value="subir_logo">
                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                    <label class="cursor-pointer d-inline-block mb-0" title="Subir/cambiar logo">
                                        <?php if ($rutaLogo): ?>
                                            <img src="<?= limpiar($rutaLogo) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;">
                                        <?php else: ?>
                                            <i class="bi bi-camera text-muted" style="font-size:1.4rem;"></i>
                                        <?php endif; ?>
                                        <input type="file" name="logo" class="d-none" accept=".png,.jpg,.jpeg" onchange="this.form.submit()">
                                    </label>
                                </form>
                            </td>
                            <td><?= limpiar($m['nombre']) ?></td>
                            <td><span class="badge <?= (int)$m['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$m['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarMarca(<?= json_encode($m, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="settings/marcas.php?toggle=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de esta marca?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$marcas): ?><tr><td colspan="4" class="text-center text-muted">No hay marcas registradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php renderizarPaginador($totalMarcas, $pagina, $porPagina); ?>
        </div>
    </div>
</div>

<script>
function editarMarca(m) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Marca';
    document.getElementById('mar_id').value = m.id;
    document.getElementById('mar_nombre').value = m.nombre;
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formMarca').reset();
    document.getElementById('mar_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-tags"></i> Nueva Marca';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
