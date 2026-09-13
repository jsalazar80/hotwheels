<?php
/**
 * INSTALADOR INICIAL
 * Ejecute este archivo UNA SOLA VEZ después de importar migrations/database.sql
 * para generar correctamente la contraseña del usuario administrador.
 * Por seguridad, elimine este archivo después de usarlo.
 */
require_once __DIR__ . '/config/db.php';

$mensaje = '';
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? 'admin');
    $nombre = trim($_POST['nombre'] ?? 'Administrador');
    $clave   = $_POST['password'] ?? '';

    if (strlen($clave) < 6) {
        $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $perfilMaster = $pdo->query("SELECT id FROM tbl_profiles WHERE nombre = 'Master' LIMIT 1")->fetch();
        if ($perfilMaster) {
            $idPerfilMaster = (int)$perfilMaster['id'];
        } else {
            $idsMenu = $pdo->query("SELECT id FROM tbl_menu_admin WHERE state = 1")->fetchAll(PDO::FETCH_COLUMN);
            $permisos = implode(',', $idsMenu);
            $ins = $pdo->prepare("INSERT INTO tbl_profiles (nombre, permisos) VALUES ('Master', ?)");
            $ins->execute([$permisos]);
            $idPerfilMaster = (int)$pdo->lastInsertId();
        }

        $hash = password_hash($clave, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("SELECT id FROM tbl_admin_user WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $existe = $stmt->fetch();

        if ($existe) {
            $upd = $pdo->prepare("UPDATE tbl_admin_user SET nombre = ?, password = ?, id_tbl_profiles = ?, state = 1 WHERE usuario = ?");
            $upd->execute([$nombre, $hash, $idPerfilMaster, $usuario]);
        } else {
            $ins = $pdo->prepare("INSERT INTO tbl_admin_user (nombre, usuario, password, id_tbl_profiles) VALUES (?, ?, ?, ?)");
            $ins->execute([$nombre, $usuario, $hash, $idPerfilMaster]);
        }
        $exito = true;
        $mensaje = "Usuario '$usuario' configurado correctamente con perfil Master. Ya puede iniciar sesión.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Instalación - Hotwheels</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width:480px;margin-top:80px;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-3">🛠️ Instalación Hotwheels</h4>
            <p class="text-muted">Configure el usuario administrador inicial del sistema.</p>
            <?php if ($mensaje): ?>
                <div class="alert alert-<?= $exito ? 'success' : 'danger' ?>"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            <?php if (!$exito): ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" name="nombre" class="form-control" value="Administrador" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="usuario" class="form-control" value="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Configurar administrador</button>
            </form>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary w-100">Ir a iniciar sesión</a>
            <?php endif; ?>
            <hr>
            <p class="small text-danger mb-0"><i class="bi bi-exclamation-triangle"></i> Por seguridad, elimine el archivo <code>install.php</code> del servidor después de configurar.</p>
        </div>
    </div>
</div>
</body>
</html>
