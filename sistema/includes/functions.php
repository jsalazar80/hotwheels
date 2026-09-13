<?php
/**
 * Funciones auxiliares del sistema
 */

// ---- Ids fijos del catálogo tbl_soporte_estado ----
define('ESTADO_SOPORTE_ANULADO', 0);
define('ESTADO_SOPORTE_REPORTADO', 1);
define('ESTADO_SOPORTE_EN_PROCESO', 2);
define('ESTADO_SOPORTE_SOLUCIONADO', 3);
define('ESTADO_SOPORTE_FINALIZADO', 4);

function obtenerConfiguracion($pdo) {
    static $config = null;
    if ($config === null) {
        $stmt = $pdo->query("SELECT * FROM tbl_configuracion ORDER BY id LIMIT 1");
        $config = $stmt->fetch();
        if (!$config) {
            $config = [
                'nombre_empresa' => 'TechSupport', 'logo' => '',
                'prefijo_ticket' => 'TK-', 'siguiente_numero' => 1,
            ];
        }
    }
    return $config;
}

function generarNumeroTicket($pdo) {
    $config = obtenerConfiguracion($pdo);
    $siguiente = (int)$config['siguiente_numero'];
    $numero = $config['prefijo_ticket'] . str_pad($siguiente, 6, '0', STR_PAD_LEFT);
    return [$numero, $siguiente];
}

function incrementarNumeroTicket($pdo, $siguiente) {
    $stmt = $pdo->prepare("UPDATE tbl_configuracion SET siguiente_numero = ? WHERE id = (SELECT id FROM (SELECT id FROM tbl_configuracion ORDER BY id LIMIT 1) t)");
    $stmt->execute([$siguiente + 1]);
}

function formatoFecha($fecha) {
    if (!$fecha) return '';
    $t = strtotime($fecha);
    return date('Y/m/d', $t);
}

function formatoFechaHora($fechaHora) {
    if (!$fechaHora) return '';
    $t = strtotime($fechaHora);
    return date('Y/m/d H:i:s', $t);
}

function limpiar($valor) {
    return htmlspecialchars(trim($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Ruta (relativa al webroot sistema/) del archivo adjunto real de un soporte, con
 *  compatibilidad hacia atrás: si no existe en el esquema nuevo (files/soportes/folder_{id}/),
 *  cae al esquema plano anterior (files/soportes/{archivo}) usado antes de esta carpeta. */
function resolverRutaArchivoSoporte($idSoporte, $nombreArchivo) {
    $relNueva = 'files/soportes/folder_' . $idSoporte . '/' . $nombreArchivo;
    if (file_exists(__DIR__ . '/../' . $relNueva)) return $relNueva;
    return 'files/soportes/' . $nombreArchivo;
}

function redirigirConMensaje($url, $tipo, $mensaje) {
    $separador = str_contains($url, '?') ? '&' : '?';
    header("Location: $url{$separador}msg_tipo=$tipo&msg=" . urlencode($mensaje));
    exit;
}

function mostrarAlertas() {
    if (isset($_GET['msg']) && isset($_GET['msg_tipo'])) {
        $tipo = $_GET['msg_tipo'] === 'ok' ? 'success' : ($_GET['msg_tipo'] === 'error' ? 'danger' : 'warning');
        $mensaje = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
        echo "<div class='alert alert-$tipo alert-dismissible fade show m-3' role='alert'>
                $mensaje
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

/**
 * Sustituye los "?" de una sentencia preparada por sus valores reales, solo para
 * dejar constancia legible en la auditoría (tbl_general_auditory.query_ejecutado).
 * No debe usarse para ejecutar SQL: los valores se citan con PDO::quote(), no se
 * vuelven a parametrizar.
 */
function interpolarSql($pdo, $sql, array $params) {
    if (!$params) return $sql;
    $partes = explode('?', $sql);
    if (count($partes) !== count($params) + 1) return $sql;

    $resultado = $partes[0];
    foreach (array_values($params) as $i => $valor) {
        if ($valor === null) {
            $resultado .= 'NULL';
        } elseif (is_bool($valor)) {
            $resultado .= $valor ? '1' : '0';
        } elseif (is_int($valor) || is_float($valor)) {
            $resultado .= $valor;
        } else {
            $resultado .= $pdo->quote((string)$valor);
        }
        $resultado .= $partes[$i + 1];
    }
    return $resultado;
}

/**
 * Registra un movimiento (INS/UPD/DEL/SEL) en la tabla de auditoría general.
 *
 * @param string $query         Sentencia SQL completa (ya con los valores reales,
 *                               ver interpolarSql()); dejar '' si la acción no
 *                               corresponde a una sentencia SQL puntual.
 * @param string $observaciones Descripción de la tarea realizada (ej: "Recálculo
 *                               de totales tras editar pago") o, para reportes,
 *                               el nombre del reporte generado.
 */
function registrarAuditoria($pdo, $accion, $tabla, $id_registro, $query = '', $observaciones = '') {
    try {
        $usuarioNombre = $_SESSION['tsp_nombre'] ?? 'sistema';
        $usuarioId = $_SESSION['tsp_usuario_id'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO tbl_general_auditory (usuario, accion, tabla, id_registro, query_ejecutado, observaciones, user_ing) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$usuarioNombre, $accion, $tabla, $id_registro, $query !== '' ? $query : null, $observaciones !== '' ? $observaciones : null, $usuarioId]);
    } catch (Exception $e) {
        // La auditoría nunca debe interrumpir la operación principal
    }
}

/**
 * Detecta un nombre amigable de navegador a partir del User-Agent.
 */
function detectarNavegador($userAgent) {
    $userAgent = $userAgent ?? '';
    $navegadores = [
        'Edg/'     => 'Microsoft Edge',
        'OPR/'     => 'Opera',
        'Opera'    => 'Opera',
        'Chrome/'  => 'Google Chrome',
        'CriOS'    => 'Google Chrome (iOS)',
        'Firefox/' => 'Mozilla Firefox',
        'FxiOS'    => 'Mozilla Firefox (iOS)',
        'Safari/'  => 'Safari',
    ];
    foreach ($navegadores as $firma => $nombre) {
        if (stripos($userAgent, $firma) !== false) {
            return $nombre;
        }
    }
    return 'Desconocido';
}

/**
 * Detecta el tipo de dispositivo (móvil, tablet, escritorio) a partir del User-Agent.
 */
function detectarDispositivo($userAgent) {
    $userAgent = $userAgent ?? '';
    if (preg_match('/iPad|Tablet/i', $userAgent)) {
        return 'Tablet';
    }
    if (preg_match('/Mobile|Android|iPhone|iPod|BlackBerry|IEMobile/i', $userAgent)) {
        return 'Móvil';
    }
    return 'Escritorio';
}

/**
 * Obtiene la IP real del cliente, considerando proxies comunes.
 */
function obtenerIpCliente() {
    $encabezados = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($encabezados as $encabezado) {
        if (!empty($_SERVER[$encabezado])) {
            $ip = explode(',', $_SERVER[$encabezado])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

/**
 * Registra un evento de acceso (LOGIN / LOGOUT) en tbl_login.
 */
function registrarEventoAcceso($pdo, $usuarioId, $usuarioNombre, $observacion) {
    try {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO tbl_login (session_id, usuario, ip, navegador, dispositivo, observacion, user_ing) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            session_id(),
            $usuarioNombre,
            obtenerIpCliente(),
            detectarNavegador($userAgent),
            detectarDispositivo($userAgent),
            $observacion,
            $usuarioId,
        ]);
    } catch (Exception $e) {
        // No debe interrumpir el flujo de login/logout
    }
}

/**
 * Valida el formato de un correo electrónico.
 */
function validarFormatoCorreo($correo) {
    return (bool) filter_var($correo, FILTER_VALIDATE_EMAIL);
}

/**
 * Obtiene los ids de menú (tbl_menu_admin) que tiene asignado un perfil.
 */
function obtenerPermisosDePerfil($pdo, $idPerfil) {
    $stmt = $pdo->prepare("SELECT permisos FROM tbl_profiles WHERE id = ?");
    $stmt->execute([$idPerfil]);
    $fila = $stmt->fetch();
    if (!$fila || !$fila['permisos']) return [];
    return array_map('intval', array_filter(array_map('trim', explode(',', $fila['permisos']))));
}

/**
 * Indica si el usuario en sesión tiene permiso sobre un id de menú específico.
 */
function tienePermiso($idMenu) {
    $permisos = $_SESSION['tsp_permisos'] ?? [];
    return in_array((int)$idMenu, $permisos, true);
}

/**
 * Exige que el usuario esté logueado y tenga permiso sobre el id de menú indicado
 * (id del registro de tbl_menu_admin, enviado como parámetro literal desde cada
 * pantalla); de lo contrario, redirige al Dashboard.
 */
function requerirPermiso($idMenu) {
    requerirLogin();
    if (!tienePermiso((int)$idMenu)) {
        header('Location: index.php?msg_tipo=error&msg=' . urlencode('No tiene permiso para acceder a esta sección.'));
        exit;
    }
}

/**
 * Indica si una opción de menú tiene submenús (hijos) activos.
 */
function menuTieneHijos($pdo, $idMenu) {
    $stmt = $pdo->prepare("SELECT COUNT(*) n FROM tbl_menu_admin WHERE is_submenu = ? AND state = 1");
    $stmt->execute([$idMenu]);
    return (int)$stmt->fetch()['n'] > 0;
}

/**
 * Imprime (si corresponde) el botón "Volver atrás" al inicio de un formulario/pantalla.
 * Se muestra únicamente cuando se llegó a la página desde la "pantalla de opciones"
 * (menu_opciones.php), lo cual se identifica por la presencia del parámetro GET "padre".
 */
function botonVolverMenu() {
    if (isset($_GET['padre']) && (int)$_GET['padre'] > 0) {
        $padre = (int)$_GET['padre'];
        echo '<a href="menu_opciones.php?padre=' . $padre . '" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Volver atrás</a>';
    }
}

/**
 * Recalcula el saldo de un cliente (tbl_saldos) a partir de:
 * - total_soportes: SUMA de tbl_soportes.monto_total donde id_tbl_clientes = X y state = 4 (FINALIZADO)
 * - total_pagos: SUMA de tbl_pagos.monto donde id_tbl_clientes = X y state = 1
 * Crea el registro en tbl_saldos si el cliente aún no tiene uno.
 */
function recalcularSaldoCliente($pdo, $clienteId, $motivo = 'Recálculo de saldo del cliente') {
    $stmtSoportes = $pdo->prepare("SELECT COALESCE(SUM(monto_total),0) t FROM tbl_soportes WHERE id_tbl_clientes = ? AND state = " . ESTADO_SOPORTE_FINALIZADO);
    $stmtSoportes->execute([$clienteId]);
    $totalSoportes = round((float)$stmtSoportes->fetch()['t'], 2);

    $stmtPagos = $pdo->prepare("SELECT COALESCE(SUM(monto),0) t FROM tbl_pagos WHERE id_tbl_clientes = ? AND state = 1");
    $stmtPagos->execute([$clienteId]);
    $totalPagos = round((float)$stmtPagos->fetch()['t'], 2);

    $existe = $pdo->prepare("SELECT id FROM tbl_saldos WHERE id_tbl_clientes = ?");
    $existe->execute([$clienteId]);
    $fila = $existe->fetch();

    if ($fila) {
        $sqlSaldo = "UPDATE tbl_saldos SET total_soportes = ?, total_pagos = ? WHERE id = ?";
        $paramsSaldo = [$totalSoportes, $totalPagos, $fila['id']];
        $upd = $pdo->prepare($sqlSaldo);
        $upd->execute($paramsSaldo);
        registrarAuditoria($pdo, 'UPD', 'tbl_saldos', (int)$fila['id'], interpolarSql($pdo, $sqlSaldo, $paramsSaldo), $motivo);
    } else {
        $sqlSaldo = "INSERT INTO tbl_saldos (id_tbl_clientes, total_soportes, total_pagos) VALUES (?,?,?)";
        $paramsSaldo = [$clienteId, $totalSoportes, $totalPagos];
        $ins = $pdo->prepare($sqlSaldo);
        $ins->execute($paramsSaldo);
        registrarAuditoria($pdo, 'INS', 'tbl_saldos', (int)$pdo->lastInsertId(), interpolarSql($pdo, $sqlSaldo, $paramsSaldo), $motivo);
    }

    return ['total_soportes' => $totalSoportes, 'total_pagos' => $totalPagos, 'saldo' => round($totalSoportes - $totalPagos, 2)];
}
