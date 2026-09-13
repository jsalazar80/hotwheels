<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(5);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $permisosArray = array_map('intval', $_POST['permisos'] ?? []);
    $permisos = implode(',', $permisosArray);

    if ($nombre === '') {
        redirigirConMensaje('perfiles.php', 'error', 'El nombre del perfil es obligatorio.');
    }

    if ($id > 0) {
        $sqlUpd = "UPDATE tbl_profiles SET nombre=?, permisos=? WHERE id=?";
        $paramsUpd = [$nombre, $permisos, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_profiles', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de perfil y sus permisos de menú');
        redirigirConMensaje('perfiles.php', 'ok', 'Perfil actualizado correctamente.');
    } else {
        $sqlIns = "INSERT INTO tbl_profiles (nombre, permisos, user_ing) VALUES (?,?,?)";
        $paramsIns = [$nombre, $permisos, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_profiles', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nuevo perfil');
        redirigirConMensaje('perfiles.php', 'ok', 'Perfil registrado correctamente.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id === (int)($_SESSION['tsp_id_tbl_profiles'] ?? 0)) {
        redirigirConMensaje('perfiles.php', 'error', 'No puede desactivar el perfil que tiene asignado actualmente.');
    }
    $sqlToggle = "UPDATE tbl_profiles SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_profiles', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) del perfil');
    redirigirConMensaje('perfiles.php', 'ok', 'Estado actualizado.');
}

$perfiles = $pdo->query("SELECT * FROM tbl_profiles ORDER BY nombre ASC")->fetchAll();
$todasLasOpciones = $pdo->query("SELECT * FROM tbl_menu_admin WHERE state = 1 ORDER BY orden ASC")->fetchAll();
$opcionesPadre = array_values(array_filter($todasLasOpciones, fn($o) => (int)$o['is_submenu'] === 0));
// Nombrada distinto de $opcionesMenu a propósito: includes/sidebar.php (incluido más abajo vía
// header.php) declara su propia $opcionesMenu en este mismo scope (los include comparten scope
// con el archivo que los llama) y la sobrescribiría si usáramos el mismo nombre aquí.
$opcionesMenuPermisos = [];
foreach ($opcionesPadre as $padre) {
    $opcionesMenuPermisos[] = $padre;
    foreach ($todasLasOpciones as $hijo) {
        if ((int)$hijo['is_submenu'] === (int)$padre['id']) {
            $opcionesMenuPermisos[] = $hijo;
        }
    }
}

$tituloPagina = 'Perfiles';
$paginaActiva = 'perfiles';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-shield-plus"></i> Nuevo Perfil</h6>
            <form method="post" id="formPerfil">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="pf_id" value="0">
                <div class="mb-3">
                    <label class="form-label">Nombre del perfil</label>
                    <input type="text" name="nombre" id="pf_nombre" class="form-control" placeholder="Ej: Supervisor" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Permisos de menú</label>
                    <div class="border rounded p-2" style="max-height:300px; overflow-y:auto;">
                        <?php foreach ($opcionesMenuPermisos as $opcion): ?>
                            <div class="form-check <?= $opcion['is_submenu'] ? '' : 'mt-2' ?>" style="<?= $opcion['is_submenu'] ? 'margin-left:20px;' : '' ?>">
                                <input class="form-check-input permiso-checkbox" type="checkbox" name="permisos[]" value="<?= $opcion['id'] ?>" id="permiso_<?= $opcion['id'] ?>">
                                <label class="form-check-label <?= $opcion['is_submenu'] ? '' : 'fw-semibold' ?>" for="permiso_<?= $opcion['id'] ?>">
                                    <i class="bi <?= limpiar($opcion['icono']) ?>"></i> <?= limpiar($opcion['nombre']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-shield-lock"></i> Perfiles Registrados</h6>
            <div class="table-responsive">
                <table class="table table-sm table-tsp align-middle">
                    <thead><tr><th>Nombre</th><th>Permisos</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($perfiles as $perfil): ?>
                        <?php
                            $idsPermitidos = array_filter(array_map('trim', explode(',', $perfil['permisos'] ?? '')));
                            $nombresPermitidos = array_filter($opcionesMenuPermisos, fn($o) => in_array((string)$o['id'], $idsPermitidos));
                        ?>
                        <tr>
                            <td><?= limpiar($perfil['nombre']) ?></td>
                            <td>
                                <?php foreach ($nombresPermitidos as $np): ?>
                                    <span class="badge bg-secondary mb-1"><?= limpiar($np['nombre']) ?></span>
                                <?php endforeach; ?>
                                <?php if (!$nombresPermitidos): ?><span class="text-muted small">Sin permisos</span><?php endif; ?>
                            </td>
                            <td><span class="badge <?= (int)$perfil['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$perfil['state']===1?'Activo':'Inactivo' ?></span></td>
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-tsp" onclick='editarPerfil(<?= json_encode($perfil, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                <a href="perfiles.php?toggle=<?= $perfil['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de este perfil?')"><i class="bi bi-toggle2-on"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$perfiles): ?><tr><td colspan="4" class="text-center text-muted">No hay perfiles registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editarPerfil(p) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Perfil';
    document.getElementById('pf_id').value = p.id;
    document.getElementById('pf_nombre').value = p.nombre;

    const idsPermitidos = (p.permisos || '').split(',').map(s => s.trim()).filter(Boolean);
    document.querySelectorAll('.permiso-checkbox').forEach(function (chk) {
        chk.checked = idsPermitidos.includes(chk.value);
    });

    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formPerfil').reset();
    document.getElementById('pf_id').value = 0;
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-shield-plus"></i> Nuevo Perfil';
    document.querySelectorAll('.permiso-checkbox').forEach(chk => chk.checked = false);
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
