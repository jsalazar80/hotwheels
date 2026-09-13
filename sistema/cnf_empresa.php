<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(10);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $prefijo_ticket = trim($_POST['prefijo_ticket'] ?? '');
    $siguiente_numero = (int)($_POST['siguiente_numero'] ?? 1);

    if ($nombre_empresa === '') {
        redirigirConMensaje('cnf_empresa.php', 'error', 'El nombre de la empresa es obligatorio.');
    }

    $logoNombre = null;
    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            $logoNombre = 'logo_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/assets/img/' . $logoNombre);
        }
    }

    $existe = $pdo->query("SELECT id FROM tbl_configuracion ORDER BY id LIMIT 1")->fetch();

    if ($existe) {
        if ($logoNombre) {
            $sqlUpd = "UPDATE tbl_configuracion SET nombre_empresa=?, prefijo_ticket=?, siguiente_numero=?, logo=? WHERE id=?";
            $paramsUpd = [$nombre_empresa, $prefijo_ticket, $siguiente_numero, $logoNombre, $existe['id']];
        } else {
            $sqlUpd = "UPDATE tbl_configuracion SET nombre_empresa=?, prefijo_ticket=?, siguiente_numero=? WHERE id=?";
            $paramsUpd = [$nombre_empresa, $prefijo_ticket, $siguiente_numero, $existe['id']];
        }
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_configuracion', $existe['id'], interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de datos de la empresa');
    } else {
        $sqlIns = "INSERT INTO tbl_configuracion (nombre_empresa, prefijo_ticket, siguiente_numero, logo, user_ing) VALUES (?,?,?,?,?)";
        $paramsIns = [$nombre_empresa, $prefijo_ticket, $siguiente_numero, $logoNombre, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        registrarAuditoria($pdo, 'INS', 'tbl_configuracion', (int)$pdo->lastInsertId(), interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro inicial de datos de la empresa');
    }

    redirigirConMensaje('cnf_empresa.php', 'ok', 'Configuración guardada correctamente.');
}

$config = $pdo->query("SELECT * FROM tbl_configuracion ORDER BY id LIMIT 1")->fetch();
if (!$config) {
    $config = ['nombre_empresa' => 'Hotwheels', 'logo' => '', 'prefijo_ticket' => 'TK-', 'siguiente_numero' => 1];
}

$tituloPagina = 'Datos de la Empresa';
$paginaActiva = 'cnf_empresa';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-building"></i> Datos de la Empresa</h6>
            <form method="post" enctype="multipart/form-data">
                <div class="mb-2">
                    <label class="form-label">Nombre de la empresa</label>
                    <input type="text" name="nombre_empresa" class="form-control" value="<?= limpiar($config['nombre_empresa']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Logo de la empresa <small class="text-muted">(se usa en los PDF de soporte)</small></label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    <?php if (!empty($config['logo'])): ?>
                        <img src="assets/img/<?= limpiar($config['logo']) ?>" style="height:60px;margin-top:8px;border-radius:6px;">
                    <?php endif; ?>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Prefijo de ticket</label>
                        <input type="text" name="prefijo_ticket" class="form-control" value="<?= limpiar($config['prefijo_ticket']) ?>" placeholder="Ej: TK-">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Siguiente número</label>
                        <input type="number" name="siguiente_numero" class="form-control" value="<?= (int)$config['siguiente_numero'] ?>" min="1">
                    </div>
                </div>
                <button class="btn btn-tsp px-4" type="submit"><i class="bi bi-save"></i> Guardar Configuración</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
