<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(4);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $id_tbl_profiles = (int)($_POST['id_tbl_profiles'] ?? 0);
    $clave = $_POST['password'] ?? '';

    if ($nombre === '' || $usuario === '' || $id_tbl_profiles <= 0) {
        redirigirConMensaje('usuarios.php', 'error', 'Nombre, usuario y perfil son obligatorios.');
    }

    try {
        if ($id > 0) {
            if ($clave !== '') {
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                $sqlUpd = "UPDATE tbl_admin_user SET nombre=?, usuario=?, id_tbl_profiles=?, password=? WHERE id=?";
                $paramsUpd = [$nombre, $usuario, $id_tbl_profiles, $hash, $id];
            } else {
                $sqlUpd = "UPDATE tbl_admin_user SET nombre=?, usuario=?, id_tbl_profiles=? WHERE id=?";
                $paramsUpd = [$nombre, $usuario, $id_tbl_profiles, $id];
            }
            $stmt = $pdo->prepare($sqlUpd);
            $stmt->execute($paramsUpd);
            registrarAuditoria($pdo, 'UPD', 'tbl_admin_user', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de usuario');
            redirigirConMensaje('usuarios.php', 'ok', 'Usuario actualizado correctamente.');
        } else {
            if (strlen($clave) < 6) {
                redirigirConMensaje('usuarios.php', 'error', 'La contraseña debe tener al menos 6 caracteres.');
            }
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $sqlIns = "INSERT INTO tbl_admin_user (nombre, usuario, password, id_tbl_profiles) VALUES (?,?,?,?)";
            $paramsIns = [$nombre, $usuario, $hash, $id_tbl_profiles];
            $stmt = $pdo->prepare($sqlIns);
            $stmt->execute($paramsIns);
            $nuevoId = (int)$pdo->lastInsertId();
            registrarAuditoria($pdo, 'INS', 'tbl_admin_user', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo usuario');
            redirigirConMensaje('usuarios.php', 'ok', 'Usuario registrado correctamente.');
        }
    } catch (PDOException $e) {
        $texto = str_contains($e->getMessage(), 'Duplicate') ? 'Ese nombre de usuario ya existe.' : 'Error: ' . $e->getMessage();
        redirigirConMensaje('usuarios.php', 'error', $texto);
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id === (int)$_SESSION['tsp_usuario_id']) {
        redirigirConMensaje('usuarios.php', 'error', 'No puede desactivar su propio usuario.');
    }
    $sqlToggle = "UPDATE tbl_admin_user SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_admin_user', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del usuario');
    redirigirConMensaje('usuarios.php', 'ok', 'Estado actualizado.');
}

[$pagina, $porPagina, $offset] = obtenerPaginacion();
$totalUsuarios = (int)$pdo->query("SELECT COUNT(*) t FROM tbl_admin_user")->fetch()['t'];
$usuarios = $pdo->query("SELECT u.*, p.nombre AS perfil_nombre FROM tbl_admin_user u
    JOIN tbl_profiles p ON p.id = u.id_tbl_profiles ORDER BY u.nombre ASC LIMIT $porPagina OFFSET $offset")->fetchAll();
$perfiles = $pdo->query("SELECT * FROM tbl_profiles WHERE state = 1 ORDER BY nombre ASC")->fetchAll();

$tituloPagina = 'Usuarios';
$paginaActiva = 'usuarios';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-person-plus"></i> Nuevo Usuario</h6>
            <form method="post" id="formUsuario">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="u_id" value="0">
                <div class="mb-2">
                    <label class="form-label">Nombre completo</label>
                    <input type="text" name="nombre" id="u_nombre" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Usuario (login)</label>
                    <input type="text" name="usuario" id="u_usuario" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Perfil</label>
                    <select class="form-select" name="id_tbl_profiles" id="u_perfil" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($perfiles as $perfil): ?>
                            <option value="<?= $perfil['id'] ?>"><?= limpiar($perfil['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña <small class="text-muted" id="notaClave">(mínimo 6 caracteres)</small></label>
                    <input type="password" name="password" id="u_password" class="form-control">
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-person-badge"></i> Usuarios del Sistema</h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Nombre</th><th>Usuario</th><th>Perfil</th><th>Último acceso</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= limpiar($u['nombre']) ?></td>
                            <td><?= limpiar($u['usuario']) ?></td>
                            <td><span class="badge bg-dark"><?= limpiar($u['perfil_nombre']) ?></span></td>
                            <td><?= $u['ultimo_acceso'] ? date('Y/m/d H:i:s', strtotime($u['ultimo_acceso'])) : '-' ?></td>
                            <td><span class="badge <?= (int)$u['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$u['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarUsuario(<?= json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="usuarios.php?toggle=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este usuario?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php renderizarPaginador($totalUsuarios, $pagina, $porPagina); ?>
        </div>
    </div>
</div>

<script>
function editarUsuario(u) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Usuario';
    document.getElementById('u_id').value = u.id;
    document.getElementById('u_nombre').value = u.nombre;
    document.getElementById('u_usuario').value = u.usuario;
    document.getElementById('u_perfil').value = u.id_tbl_profiles;
    document.getElementById('u_password').value = '';
    document.getElementById('notaClave').textContent = '(dejar en blanco para no cambiar)';
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formUsuario').reset();
    document.getElementById('u_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-person-plus"></i> Nuevo Usuario';
    document.getElementById('notaClave').textContent = '(mínimo 6 caracteres)';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
