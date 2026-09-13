<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(20);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ml_username = trim($_POST['ml_username'] ?? '');
    $ml_password = trim($_POST['ml_password'] ?? '');
    $ml_sent_by_name = trim($_POST['ml_sent_by_name'] ?? '');
    $ml_host = trim($_POST['ml_host'] ?? '');
    $id_tbl_email_config_port = trim($_POST['id_tbl_email_config_port'] ?? '');
    $id_tbl_email_config_auth = trim($_POST['id_tbl_email_config_auth'] ?? '');
    $id_tbl_email_config_scrt = trim($_POST['id_tbl_email_config_scrt'] ?? '');
    $ml_smtp_relay = (int)($_POST['ml_smtp_relay'] ?? 250);
    $cc = trim($_POST['cc'] ?? '');
    $bcc = trim($_POST['bcc'] ?? '');

    if ($ml_username === '' || $ml_password === '' || $ml_host === '') {
        redirigirConMensaje('cnf_email.php', 'error', 'Correo remitente, contraseña y servidor son obligatorios.');
    }
    if ($id_tbl_email_config_port === '' || $id_tbl_email_config_auth === '' || $id_tbl_email_config_scrt === '') {
        redirigirConMensaje('cnf_email.php', 'error', 'Puerto, autorización y seguridad son obligatorios.');
    }

    $existe = $pdo->query("SELECT id FROM tbl_email_config_sender ORDER BY id LIMIT 1")->fetch();

    $paramsGuardar = [
        $ml_username, $ml_password, $ml_sent_by_name, $ml_host,
        $id_tbl_email_config_port, $id_tbl_email_config_auth, $id_tbl_email_config_scrt,
        $ml_smtp_relay, $cc ?: null, $bcc ?: null,
    ];

    if ($existe) {
        $sqlUpd = "UPDATE tbl_email_config_sender SET ml_username=?, ml_password=?, ml_sent_by_name=?, ml_host=?,
            id_tbl_email_config_port=?, id_tbl_email_config_auth=?, id_tbl_email_config_scrt=?, ml_smtp_relay=?, cc=?, bcc=?
            WHERE id=?";
        $paramsUpd = array_merge($paramsGuardar, [$existe['id']]);
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_email_config_sender', $existe['id'], interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de la configuración de correo');
    } else {
        $sqlIns = "INSERT INTO tbl_email_config_sender
            (ml_username, ml_password, ml_sent_by_name, ml_host, id_tbl_email_config_port, id_tbl_email_config_auth, id_tbl_email_config_scrt, ml_smtp_relay, cc, bcc, user_ing)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        $paramsIns = array_merge($paramsGuardar, [$_SESSION['tsp_usuario_id']]);
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        registrarAuditoria($pdo, 'INS', 'tbl_email_config_sender', (int)$pdo->lastInsertId(), interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro inicial de la configuración de correo');
    }

    redirigirConMensaje('cnf_email.php', 'ok', 'Configuración de correo guardada correctamente.');
}

$config = $pdo->query("SELECT * FROM tbl_email_config_sender ORDER BY id LIMIT 1")->fetch();
if (!$config) {
    $config = [
        'ml_username' => '', 'ml_password' => '', 'ml_sent_by_name' => '', 'ml_host' => '',
        'id_tbl_email_config_port' => '465', 'id_tbl_email_config_auth' => 'true', 'id_tbl_email_config_scrt' => 'ssl',
        'ml_smtp_relay' => 250, 'cc' => '', 'bcc' => '',
    ];
}

$puertos = $pdo->query("SELECT * FROM tbl_email_config_port WHERE state=1 ORDER BY nombre ASC")->fetchAll();
$autorizaciones = $pdo->query("SELECT * FROM tbl_email_config_auth WHERE state=1 ORDER BY id ASC")->fetchAll();
$seguridades = $pdo->query("SELECT * FROM tbl_email_config_scrt WHERE state=1 ORDER BY id ASC")->fetchAll();

$tituloPagina = 'Configurar Correo';
$paginaActiva = 'cnf_email';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-envelope-gear"></i> Configuración de Correo (SMTP)</h6>
            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Correo remitente</label>
                        <input type="email" name="ml_username" class="form-control" value="<?= limpiar($config['ml_username']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Contraseña / Clave de aplicación</label>
                        <input type="text" name="ml_password" class="form-control" value="<?= limpiar($config['ml_password']) ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Nombre a mostrar</label>
                        <input type="text" name="ml_sent_by_name" class="form-control" value="<?= limpiar($config['ml_sent_by_name']) ?>" placeholder="Ej: Hotwheels">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Servidor (host)</label>
                        <input type="text" name="ml_host" class="form-control" value="<?= limpiar($config['ml_host']) ?>" placeholder="Ej: smtp.gmail.com" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Puerto</label>
                        <select class="form-select" name="id_tbl_email_config_port" required>
                            <?php foreach ($puertos as $p): ?>
                                <option value="<?= limpiar($p['id']) ?>" <?= $config['id_tbl_email_config_port']===$p['id']?'selected':'' ?>><?= limpiar($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Autorización (SMTPAuth)</label>
                        <select class="form-select" name="id_tbl_email_config_auth" required>
                            <?php foreach ($autorizaciones as $a): ?>
                                <option value="<?= limpiar($a['id']) ?>" <?= $config['id_tbl_email_config_auth']===$a['id']?'selected':'' ?>><?= limpiar($a['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Seguridad</label>
                        <select class="form-select" name="id_tbl_email_config_scrt" required>
                            <?php foreach ($seguridades as $s): ?>
                                <option value="<?= limpiar($s['id']) ?>" <?= $config['id_tbl_email_config_scrt']===$s['id']?'selected':'' ?>><?= strtoupper(limpiar($s['nombre'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">Correos por día <small class="text-muted">(límite del proveedor)</small></label>
                        <input type="number" name="ml_smtp_relay" class="form-control" value="<?= (int)$config['ml_smtp_relay'] ?>" min="1">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">CC <small class="text-muted">(opcional)</small></label>
                        <input type="email" name="cc" class="form-control" value="<?= limpiar($config['cc']) ?>">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label class="form-label">CCO <small class="text-muted">(opcional)</small></label>
                        <input type="email" name="bcc" class="form-control" value="<?= limpiar($config['bcc']) ?>">
                    </div>
                </div>
                <button class="btn btn-tsp px-4" type="submit"><i class="bi bi-save"></i> Guardar Configuración</button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
