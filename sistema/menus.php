<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(6);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reordenar') {
    header('Content-Type: application/json');
    $padre = (int)($_POST['padre'] ?? 0);
    $ids = $_POST['ids'] ?? [];
    if (is_array($ids) && $ids) {
        $orden = 1;
        foreach ($ids as $idItem) {
            $stmt = $pdo->prepare("UPDATE tbl_menu_admin SET orden = ? WHERE id = ? AND is_submenu = ?");
            $stmt->execute([$orden, (int)$idItem, $padre]);
            $orden++;
        }
        registrarAuditoria($pdo, 'UPD', 'tbl_menu_admin', $padre, '', 'Reordenamiento por arrastre (padre=' . $padre . '): ' . implode(',', $ids));
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $icono = trim($_POST['icono'] ?? '');
    $is_submenu = (int)($_POST['is_submenu'] ?? 0);

    if ($nombre === '' || $url === '') {
        redirigirConMensaje('menus.php', 'error', 'Nombre y URL son obligatorios.');
    }

    if ($id > 0) {
        $actual = $pdo->prepare("SELECT is_submenu, orden FROM tbl_menu_admin WHERE id = ?");
        $actual->execute([$id]);
        $filaActual = $actual->fetch();
        $orden = (int)($filaActual['orden'] ?? 0);

        if ((int)($filaActual['is_submenu'] ?? -1) !== $is_submenu) {
            // Cambió de grupo: se coloca al final del nuevo grupo
            $stmtOrden = $pdo->prepare("SELECT COALESCE(MAX(orden),0)+1 t FROM tbl_menu_admin WHERE is_submenu = ?");
            $stmtOrden->execute([$is_submenu]);
            $orden = (int)$stmtOrden->fetch()['t'];
        }

        $sqlUpd = "UPDATE tbl_menu_admin SET nombre=?, url=?, icono=?, orden=?, is_submenu=? WHERE id=?";
        $paramsUpd = [$nombre, $url, $icono, $orden, $is_submenu, $id];
        $stmt = $pdo->prepare($sqlUpd);
        $stmt->execute($paramsUpd);
        registrarAuditoria($pdo, 'UPD', 'tbl_menu_admin', $id, interpolarSql($pdo, $sqlUpd, $paramsUpd), 'Actualización de opción de menú');
        redirigirConMensaje('menus.php', 'ok', 'Opción de menú actualizada.');
    } else {
        $stmtOrden = $pdo->prepare("SELECT COALESCE(MAX(orden),0)+1 t FROM tbl_menu_admin WHERE is_submenu = ?");
        $stmtOrden->execute([$is_submenu]);
        $orden = (int)$stmtOrden->fetch()['t'];

        $sqlIns = "INSERT INTO tbl_menu_admin (nombre, url, icono, orden, is_submenu, user_ing) VALUES (?,?,?,?,?,?)";
        $paramsIns = [$nombre, $url, $icono, $orden, $is_submenu, $_SESSION['tsp_usuario_id']];
        $stmt = $pdo->prepare($sqlIns);
        $stmt->execute($paramsIns);
        $nuevoId = (int)$pdo->lastInsertId();
        registrarAuditoria($pdo, 'INS', 'tbl_menu_admin', $nuevoId, interpolarSql($pdo, $sqlIns, $paramsIns), 'Registro de nueva opción de menú');
        redirigirConMensaje('menus.php', 'ok', 'Opción de menú registrada. Recuerde asignarla a los perfiles correspondientes en el módulo Perfiles.');
    }
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $sqlToggle = "UPDATE tbl_menu_admin SET state = IF(state=1,0,1) WHERE id=?";
    $paramsToggle = [$id];
    $stmt = $pdo->prepare($sqlToggle);
    $stmt->execute($paramsToggle);
    registrarAuditoria($pdo, 'UPD', 'tbl_menu_admin', $id, interpolarSql($pdo, $sqlToggle, $paramsToggle), 'Cambio de estado (activo/inactivo) de la opción de menú');
    redirigirConMensaje('menus.php', 'ok', 'Estado actualizado.');
}

$principales = $pdo->query("SELECT * FROM tbl_menu_admin WHERE is_submenu = 0 ORDER BY orden ASC")->fetchAll();
$hijosPorPadre = [];
foreach ($principales as $p) {
    $stmt = $pdo->prepare("SELECT * FROM tbl_menu_admin WHERE is_submenu = ? ORDER BY orden ASC");
    $stmt->execute([$p['id']]);
    $hijosPorPadre[$p['id']] = $stmt->fetchAll();
}

$tituloPagina = 'Menús';
$paginaActiva = 'menus';
include __DIR__ . '/includes/header.php';
?>

<?php botonVolverMenu(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card-panel">
            <h6 class="panel-title" id="tituloForm"><i class="bi bi-plus-square"></i> Nueva Opción de Menú</h6>
            <form method="post" id="formMenu">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" id="m_id" value="0">
                <div class="mb-2">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" id="m_nombre" class="form-control" placeholder="Ej: Reportes" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">URL (archivo .php)</label>
                    <input type="text" name="url" id="m_url" class="form-control" placeholder="Ej: reportes.php" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Ícono <small class="text-muted">(Bootstrap Icons)</small></label>
                    <div class="icon-picker">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-question-circle" id="m_icono_preview"></i></span>
                            <input type="text" class="form-control" id="m_icono_buscar" placeholder="Buscar ícono... (ej: gear, person, cart)" autocomplete="off">
                        </div>
                        <input type="hidden" name="icono" id="m_icono" value="">
                        <div class="icon-picker-dropdown d-none" id="m_icono_dropdown"></div>
                    </div>
                </div>
                <input type="hidden" name="orden" id="m_orden" value="0">
                <div class="mb-2">
                    <label class="form-label">Es submenú de</label>
                    <select class="form-select" name="is_submenu" id="m_is_submenu">
                        <option value="0">-- Opción de primer nivel --</option>
                        <?php foreach ($principales as $padre): ?>
                            <option value="<?= $padre['id'] ?>"><?= limpiar($padre['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-tsp w-100" type="submit"><i class="bi bi-save"></i> Guardar</button>
                <button class="btn btn-outline-secondary w-100 mt-2 d-none" type="button" id="btnCancelar" onclick="limpiarForm()">Cancelar edición</button>
                <small class="text-muted d-block mt-2"><i class="bi bi-info-circle"></i> Después de crear una opción, asígnela a los perfiles correspondientes desde el módulo <a href="perfiles.php">Perfiles</a>. El orden se define arrastrando las filas en la lista de la derecha.</small>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-panel">
            <h6 class="panel-title"><i class="bi bi-list-ul"></i> Opciones de Menú <small class="text-muted fw-normal">(arrastra las filas para cambiar el orden dentro de cada pestaña)</small></h6>

            <ul class="nav nav-tabs" id="tabsMenus" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-principales" type="button">
                        <i class="bi bi-diagram-2"></i> Menús Principales
                    </button>
                </li>
                <?php foreach ($principales as $p): ?>
                    <?php if (!empty($hijosPorPadre[$p['id']])): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-hijo-<?= $p['id'] ?>" type="button">
                                <i class="bi <?= limpiar($p['icono'] ?: 'bi-dot') ?>"></i> <?= limpiar($p['nombre']) ?>
                            </button>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content mt-3">
                <div class="tab-pane fade show active" id="tab-principales">
                    <div class="table-responsive">
                        <table class="table table-sm table-tsp align-middle">
                            <thead><tr><th style="width:30px;"></th><th>Nombre</th><th>URL</th><th>Ícono</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody id="lista-principales">
                            <?php foreach ($principales as $m): ?>
                                <tr draggable="true" data-id="<?= $m['id'] ?>" class="fila-arrastrable">
                                    <td class="cursor-pointer text-muted"><i class="bi bi-grip-vertical"></i></td>
                                    <td><?= limpiar($m['nombre']) ?></td>
                                    <td><code><?= limpiar($m['url']) ?></code></td>
                                    <td><i class="bi <?= limpiar($m['icono']) ?>"></i> <?= limpiar($m['icono']) ?></td>
                                    <td><span class="badge <?= (int)$m['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$m['state']===1?'Activo':'Inactivo' ?></span></td>
                                    <td class="text-nowrap">
                                        <button class="btn btn-sm btn-outline-tsp" onclick='editarMenu(<?= json_encode($m, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                        <a href="menus.php?toggle=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de esta opción de menú?')"><i class="bi bi-toggle2-on"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$principales): ?><tr><td colspan="6" class="text-center text-muted">No hay opciones de primer nivel.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php foreach ($principales as $p): ?>
                    <?php if (!empty($hijosPorPadre[$p['id']])): ?>
                        <div class="tab-pane fade" id="tab-hijo-<?= $p['id'] ?>">
                            <div class="table-responsive">
                                <table class="table table-sm table-tsp align-middle">
                                    <thead><tr><th style="width:30px;"></th><th>Nombre</th><th>URL</th><th>Ícono</th><th>Estado</th><th>Acciones</th></tr></thead>
                                    <tbody id="lista-hijo-<?= $p['id'] ?>">
                                    <?php foreach ($hijosPorPadre[$p['id']] as $m): ?>
                                        <tr draggable="true" data-id="<?= $m['id'] ?>" class="fila-arrastrable">
                                            <td class="cursor-pointer text-muted"><i class="bi bi-grip-vertical"></i></td>
                                            <td><?= limpiar($m['nombre']) ?></td>
                                            <td><code><?= limpiar($m['url']) ?></code></td>
                                            <td><i class="bi <?= limpiar($m['icono']) ?>"></i> <?= limpiar($m['icono']) ?></td>
                                            <td><span class="badge <?= (int)$m['state']===1?'bg-success':'bg-secondary' ?>"><?= (int)$m['state']===1?'Activo':'Inactivo' ?></span></td>
                                            <td class="text-nowrap">
                                                <button class="btn btn-sm btn-outline-tsp" onclick='editarMenu(<?= json_encode($m, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                                                <a href="menus.php?toggle=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirmarAccion('¿Cambiar el estado de esta opción de menú?')"><i class="bi bi-toggle2-on"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/bootstrap-icons-list.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    inicializarSelectorIcono('m_icono_buscar', 'm_icono', 'm_icono_preview', 'm_icono_dropdown');
    inicializarListaOrdenable('lista-principales', 'reordenar', {padre: 0});
    <?php foreach ($principales as $p): ?>
        <?php if (!empty($hijosPorPadre[$p['id']])): ?>
            inicializarListaOrdenable('lista-hijo-<?= $p['id'] ?>', 'reordenar', {padre: <?= $p['id'] ?>});
        <?php endif; ?>
    <?php endforeach; ?>
});

function editarMenu(m) {
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-pencil-square"></i> Editar Opción de Menú';
    document.getElementById('m_id').value = m.id;
    document.getElementById('m_nombre').value = m.nombre;
    document.getElementById('m_url').value = m.url;
    document.getElementById('m_is_submenu').value = m.is_submenu;
    const buscarIcono = document.getElementById('m_icono_buscar');
    if (buscarIcono.establecerValorIcono) buscarIcono.establecerValorIcono((m.icono || '').replace(/^bi-/, ''));
    document.getElementById('btnCancelar').classList.remove('d-none');
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function limpiarForm() {
    document.getElementById('formMenu').reset();
    document.getElementById('m_id').value = 0;
    document.getElementById('m_icono').value = '';
    document.getElementById('m_icono_preview').className = 'bi bi-question-circle';
    document.getElementById('tituloForm').innerHTML = '<i class="bi bi-plus-square"></i> Nueva Opción de Menú';
    document.getElementById('btnCancelar').classList.add('d-none');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
