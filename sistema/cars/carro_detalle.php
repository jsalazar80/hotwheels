<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requerirPermiso(34);

$id = (int)($_GET['id'] ?? 0);

// ---- Guardar (crear o actualizar) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $modelo = trim($_POST['modelo'] ?? '');
    $internalcode = trim($_POST['internalcode'] ?? '');
    $idFabricante = (int)($_POST['id_tbl_hotwheels_fabricantes'] ?? 0) ?: null;
    $idSerie = (int)($_POST['id_tbl_hotwheels_series'] ?? 0) ?: null;
    $idMarca = (int)($_POST['id_tbl_hotwheels_marcas'] ?? 0) ?: null;
    $idEscala = (int)($_POST['id_tbl_hotwheels_escalas'] ?? 0) ?: null;
    $idTipo = (int)($_POST['id_tbl_hotwheels_tipos'] ?? 0) ?: null;
    $idColor = (int)($_POST['id_tbl_hotwheels_colores'] ?? 0) ?: null;
    $cantidad = (int)($_POST['cantidad'] ?? 1) ?: 1;
    $codigoBarras = trim($_POST['codigo_barras'] ?? '');

    if ($modelo === '' || $idFabricante === null || $idSerie === null || $idMarca === null || $idEscala === null || $idTipo === null || $idColor === null || $cantidad < 1 || $codigoBarras === '') {
        redirigirConMensaje($id > 0 ? "carro_detalle.php?id=$id" : 'carro_detalle.php', 'error', 'Todos los campos son obligatorios.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_hotwheels_carros SET internalcode=?, id_tbl_hotwheels_fabricantes=?, id_tbl_hotwheels_series=?, id_tbl_hotwheels_marcas=?, modelo=?, id_tbl_hotwheels_escalas=?, id_tbl_hotwheels_tipos=?, id_tbl_hotwheels_colores=?, cantidad=?, codigo_barras=? WHERE id=?";
        $paramsUpd = [$internalcode ?: null, $idFabricante, $idSerie, $idMarca, $modelo, $idEscala, $idTipo, $idColor, $cantidad, $codigoBarras ?: null, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_carros', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de auto');
    } else {
        $sqlIns = "INSERT INTO tbl_hotwheels_carros (internalcode, id_tbl_hotwheels_fabricantes, id_tbl_hotwheels_series, id_tbl_hotwheels_marcas, modelo, id_tbl_hotwheels_escalas, id_tbl_hotwheels_tipos, id_tbl_hotwheels_colores, cantidad, codigo_barras, user_ing, fecha_hora_ing) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())";
        $paramsIns = [$internalcode ?: null, $idFabricante, $idSerie, $idMarca, $modelo, $idEscala, $idTipo, $idColor, $cantidad, $codigoBarras ?: null, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $id = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_hotwheels_carros', $id, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo auto');
    }

    procesarArchivosAdjuntosCarro($pdo, $id, $_SESSION['tsp_usuario_id']);

    redirigirConMensaje("carro_detalle.php?id=$id", 'ok', 'Auto guardado correctamente.');
}

// ---- Eliminar una foto adjunta ----
if (isset($_GET['eliminar_archivo']) && $id > 0) {
    $idArchivo = (int)$_GET['eliminar_archivo'];
    $stmtArch = $pdo->prepare("SELECT * FROM tbl_hotwheels_carros_archivos WHERE id = ?");
    $stmtArch->execute([$idArchivo]);
    $archivo = $stmtArch->fetch();
    if ($archivo && (int)$archivo['id_tbl_hotwheels_carros'] === $id) {
        $sqlDel = "UPDATE tbl_hotwheels_carros_archivos SET state=0 WHERE id=?";
        $paramsDel = [$idArchivo];
        $pdo->prepare($sqlDel)->execute($paramsDel);
        registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_carros_archivos', $idArchivo, interpolarSql($pdo, $sqlDel, $paramsDel), 'Eliminación de foto adjunta del auto');

        $carpetaCarro = __DIR__ . '/../files/carros/folder_' . $id;
        $rutaArchivo = $carpetaCarro . '/' . $archivo['archivo'];
        if (file_exists($rutaArchivo)) @unlink($rutaArchivo);
        $rutaMiniatura = $carpetaCarro . '/thumbnail/s_' . $archivo['archivo'];
        if (file_exists($rutaMiniatura)) @unlink($rutaMiniatura);
    }
    redirigirConMensaje("carro_detalle.php?id=$id", 'ok', 'Foto eliminada.');
}

// ---- Datos para los combos ----
$fabricantes = $pdo->query("SELECT * FROM tbl_hotwheels_fabricantes WHERE state=1 ORDER BY nombre ASC")->fetchAll();
$series = $pdo->query("SELECT * FROM tbl_hotwheels_series WHERE state=1 ORDER BY nombre ASC")->fetchAll();
$marcas = $pdo->query("SELECT * FROM tbl_hotwheels_marcas WHERE state=1 ORDER BY nombre ASC")->fetchAll();
$escalas = $pdo->query("SELECT * FROM tbl_hotwheels_escalas WHERE state=1 ORDER BY nombre ASC")->fetchAll();
$tipos = $pdo->query("SELECT * FROM tbl_hotwheels_tipos WHERE state=1 ORDER BY nombre ASC")->fetchAll();
$colores = $pdo->query("SELECT * FROM tbl_hotwheels_colores WHERE state=1 ORDER BY nombre ASC")->fetchAll();

// ---- Cargar el auto (edición) o valores por defecto (nuevo) ----
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM tbl_hotwheels_carros WHERE id = ?");
    $stmt->execute([$id]);
    $carro = $stmt->fetch();
    if (!$carro) {
        redirigirConMensaje('carros.php', 'error', 'Auto no encontrado.');
    }
} else {
    $carro = [
        'internalcode' => '', 'id_tbl_hotwheels_fabricantes' => null, 'id_tbl_hotwheels_series' => null,
        'id_tbl_hotwheels_marcas' => null, 'modelo' => '', 'id_tbl_hotwheels_escalas' => 1,
        'id_tbl_hotwheels_tipos' => null, 'id_tbl_hotwheels_colores' => null, 'cantidad' => 1, 'codigo_barras' => '',
    ];
}

$archivos = [];
if ($id > 0) {
    $stmtArch = $pdo->prepare("SELECT * FROM tbl_hotwheels_carros_archivos WHERE id_tbl_hotwheels_carros = ? AND state=1 ORDER BY id DESC");
    $stmtArch->execute([$id]);
    $archivos = $stmtArch->fetchAll();
}

$tituloPagina = $id > 0 ? 'Editar Auto' : 'Nuevo Auto';
$paginaActiva = 'carros';
include __DIR__ . '/../includes/header.php';
?>

<a href="cars/carros.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Volver al listado</a>

<form method="post" enctype="multipart/form-data" id="formCarro" action="cars/carro_detalle.php<?= $id > 0 ? '?id=' . $id : '' ?>">
    <input type="hidden" name="accion" value="guardar">
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card-panel">
                <h6 class="panel-title"><i class="bi bi-car-front-fill"></i> Datos del Auto</h6>
                <div class="row g-2">
                    <input type="hidden" name="internalcode" value="<?= limpiar($carro['internalcode']) ?>">
                    <div class="col-md-12">
                        <label class="form-label">Modelo</label>
                        <input type="text" name="modelo" class="form-control" value="<?= limpiar($carro['modelo']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fabricante</label>
                        <select name="id_tbl_hotwheels_fabricantes" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($fabricantes as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= (int)$carro['id_tbl_hotwheels_fabricantes'] === (int)$f['id'] ? 'selected' : '' ?>><?= limpiar($f['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Marca</label>
                        <select name="id_tbl_hotwheels_marcas" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($marcas as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= (int)$carro['id_tbl_hotwheels_marcas'] === (int)$m['id'] ? 'selected' : '' ?>><?= limpiar($m['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Serie</label>
                        <select name="id_tbl_hotwheels_series" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($series as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= (int)$carro['id_tbl_hotwheels_series'] === (int)$s['id'] ? 'selected' : '' ?>><?= limpiar($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo</label>
                        <select name="id_tbl_hotwheels_tipos" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= (int)$carro['id_tbl_hotwheels_tipos'] === (int)$t['id'] ? 'selected' : '' ?>><?= limpiar($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Color</label>
                        <select name="id_tbl_hotwheels_colores" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($colores as $co): ?>
                                <option value="<?= $co['id'] ?>" <?= (int)$carro['id_tbl_hotwheels_colores'] === (int)$co['id'] ? 'selected' : '' ?>><?= limpiar($co['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Escala</label>
                        <select name="id_tbl_hotwheels_escalas" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($escalas as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= (int)$carro['id_tbl_hotwheels_escalas'] === (int)$e['id'] ? 'selected' : '' ?>><?= limpiar($e['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cantidad</label>
                        <input type="number" name="cantidad" class="form-control" value="<?= (int)$carro['cantidad'] ?>" min="1" required>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Código de barras</label>
                        <input type="text" name="codigo_barras" class="form-control" value="<?= limpiar($carro['codigo_barras']) ?>" required>
                    </div>
                </div>
                <button class="btn btn-tsp px-4 mt-3" type="submit"><i class="bi bi-save"></i> Guardar</button>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card-panel">
                <h6 class="panel-title"><i class="bi bi-paperclip"></i> Archivos adjuntos</h6>
                <?php if ($id > 0): ?>
                    <div class="mb-2">
                        <div class="dropzone" id="dropzoneArchivos">
                            <i class="bi bi-cloud-arrow-up"></i>
                            Arrastra imágenes aquí o haz clic para seleccionar
                            <div class="small mt-1">Formatos permitidos: PNG, JPG, JPEG (se convierten a JPG)</div>
                        </div>
                        <input type="file" class="d-none" id="inputArchivos" name="archivos[]" multiple accept=".png,.jpg,.jpeg">
                        <div id="listaArchivosSeleccionados" class="mt-2"></div>
                        <button class="btn btn-tsp btn-sm mt-2" type="submit"><i class="bi bi-upload"></i> Subir fotos seleccionadas</button>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">Guarde el auto primero; luego podrá adjuntar fotos aquí.</p>
                <?php endif; ?>

                <?php if ($archivos): ?>
                    <div class="row g-2 mt-2">
                        <?php foreach ($archivos as $a): ?>
                            <?php
                                $rutaArchivo = resolverRutaArchivoCarro($id, $a['archivo']);
                                $rutaMiniatura = resolverRutaMiniaturaCarro($id, $a['archivo']);
                            ?>
                            <div class="col-6 col-md-4">
                                <div class="border rounded p-2 text-center position-relative">
                                    <a href="<?= limpiar($rutaArchivo) ?>" target="_blank">
                                        <img src="<?= limpiar($rutaMiniatura ?: $rutaArchivo) ?>" style="width:100%; max-height:120px; object-fit:cover;">
                                    </a>
                                    <a href="cars/carro_detalle.php?id=<?= $id ?>&eliminar_archivo=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger mt-1 w-100" onclick="return confirmarAccion('¿Eliminar esta foto?')"><i class="bi bi-trash"></i></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($id > 0): ?>
                    <p class="text-muted text-center small mb-0">Aún no hay fotos adjuntas.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    inicializarDropzone('dropzoneArchivos', 'inputArchivos', 'listaArchivosSeleccionados', ['png', 'jpg', 'jpeg']);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
