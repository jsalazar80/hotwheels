<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

function estaAutenticado() {
    return isset($_SESSION['tsp_usuario_id']);
}

function requerirLogin() {
    if (!estaAutenticado()) {
        header('Location: login.php');
        exit;
    }
}

function usuarioActual() {
    return [
        'id'              => $_SESSION['tsp_usuario_id'] ?? null,
        'nombre'          => $_SESSION['tsp_nombre'] ?? '',
        'id_tbl_profiles' => $_SESSION['tsp_id_tbl_profiles'] ?? null,
        'perfil_nombre'   => $_SESSION['tsp_perfil_nombre'] ?? '',
    ];
}

/**
 * Se considera "administrador" a todo perfil que tenga permiso sobre la opción
 * de menú "Usuarios" (id 4 en tbl_menu_admin). Se usa como comprobación de
 * privilegios elevados para acciones que no corresponden a una opción de menú
 * propia, por lo que no reutiliza requerirPermiso() (esa función además valida
 * que el id corresponda a la página actual, lo cual no aplica aquí).
 */
function esAdmin() {
    return tienePermiso(4);
}

function requerirAdmin() {
    requerirLogin();
    if (!tienePermiso(4)) {
        header('Location: index.php?msg_tipo=error&msg=' . urlencode('No tiene permiso para realizar esta acción.'));
        exit;
    }
}
