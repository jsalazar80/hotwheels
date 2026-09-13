<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (estaAutenticado()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = $_POST['password'] ?? '';

    if ($usuario === '' || $clave === '') {
        $error = 'Ingrese usuario y contraseña.';
    } else {
        $stmt = $pdo->prepare("SELECT u.*, p.nombre AS perfil_nombre, p.permisos
            FROM tbl_admin_user u
            JOIN tbl_profiles p ON p.id = u.id_tbl_profiles
            WHERE u.usuario = ? LIMIT 1");
        $stmt->execute([$usuario]);
        $u = $stmt->fetch();

        if ($u && (int)$u['state'] === 1 && password_verify($clave, $u['password'])) {
            $_SESSION['tsp_usuario_id']      = $u['id'];
            $_SESSION['tsp_nombre']          = $u['nombre'];
            $_SESSION['tsp_id_tbl_profiles'] = $u['id_tbl_profiles'];
            $_SESSION['tsp_perfil_nombre']   = $u['perfil_nombre'];
            $_SESSION['tsp_permisos']        = array_map('intval', array_filter(array_map('trim', explode(',', $u['permisos'] ?? ''))));

            $upd = $pdo->prepare("UPDATE tbl_admin_user SET ultimo_acceso = NOW() WHERE id = ?");
            $upd->execute([$u['id']]);

            registrarEventoAcceso($pdo, $u['id'], $u['nombre'], 'LOGIN');

            header('Location: index.php');
            exit;
        } elseif ($u && (int)$u['state'] !== 1) {
            $error = 'Su usuario se encuentra inactivo. Contacte al administrador.';
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión - Hotwheels</title>
<link rel="icon" href="favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="192x192" href="assets/img/icon-192.png">
<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#17417a">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-wrapper">
    <div class="login-box">
        <img src="assets/img/logo.svg" alt="Logo">
        <h4 class="text-center fw-bold text-tsp mb-0">Hotwheels</h4>
        <p class="text-center text-muted mb-4" style="font-size:0.85rem;">Panel de administración</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input type="text" name="usuario" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-tsp w-100 py-2 fw-bold">Ingresar</button>
        </form>
        <p class="text-center text-muted mt-4 mb-0" style="font-size:0.75rem;">&copy; <?= date('Y') ?> Hotwheels. Todos los derechos reservados.</p>
    </div>
</div>
</body>
</html>
