<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['tsp_nombre'])) {
    registrarEventoAcceso($pdo, $_SESSION['tsp_usuario_id'] ?? null, $_SESSION['tsp_nombre'], 'LOGOUT');
}

session_destroy();
header('Location: login.php');
exit;
