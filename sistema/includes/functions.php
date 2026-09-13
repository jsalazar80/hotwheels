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
                'nombre_empresa' => 'Hotwheels', 'logo' => '',
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

/**
 * Valida y convierte una imagen subida (jpg/jpeg/png) a JPG, guardando el archivo final
 * y una miniatura JPG a partir de las rutas indicadas. La transparencia de los PNG se
 * aplana sobre fondo blanco, ya que JPG no soporta canal alfa.
 * Devuelve true si tanto la imagen como la miniatura se guardaron correctamente.
 */
function guardarImagenJpgConMiniatura($rutaTmpSubida, $extensionOriginal, $rutaDestino, $rutaDestinoMiniatura, $ladoMaxMiniatura = 200) {
    $info = @getimagesize($rutaTmpSubida);
    if (!$info) return false;

    $ext = strtolower($extensionOriginal);
    if ($ext === 'png' && $info[2] === IMAGETYPE_PNG) {
        $origen = @imagecreatefrompng($rutaTmpSubida);
    } elseif (in_array($ext, ['jpg', 'jpeg'], true) && $info[2] === IMAGETYPE_JPEG) {
        $origen = @imagecreatefromjpeg($rutaTmpSubida);
    } else {
        return false;
    }
    if (!$origen) return false;

    $ancho = imagesx($origen);
    $alto = imagesy($origen);

    $lienzo = imagecreatetruecolor($ancho, $alto);
    imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
    imagecopy($lienzo, $origen, 0, 0, 0, 0, $ancho, $alto);
    imagedestroy($origen);

    $guardadoOk = imagejpeg($lienzo, $rutaDestino, 90);

    $escala = min(1, $ladoMaxMiniatura / max($ancho, $alto));
    $anchoMini = max(1, (int) round($ancho * $escala));
    $altoMini = max(1, (int) round($alto * $escala));
    $miniatura = imagecreatetruecolor($anchoMini, $altoMini);
    imagecopyresampled($miniatura, $lienzo, 0, 0, 0, 0, $anchoMini, $altoMini, $ancho, $alto);
    $miniaturaOk = imagejpeg($miniatura, $rutaDestinoMiniatura, 85);

    imagedestroy($lienzo);
    imagedestroy($miniatura);

    return $guardadoOk && $miniaturaOk;
}

/**
 * Sube y guarda las fotos adjuntas de un auto (jpg/jpeg/png, siempre convertidas a jpg),
 * en files/carros/folder_{idCarro}/ con nombre foto_{n}_{idCarro}.jpg (numeración
 * secuencial según los archivos ya existentes en esa carpeta) y su miniatura JPG
 * s_foto_{n}_{idCarro}.jpg en la subcarpeta thumbnail/. Comparte esta lógica el alta y
 * la edición de tbl_hotwheels_carros (cars/carro_detalle.php).
 */
function procesarArchivosAdjuntosCarro($pdo, $idCarro, $userId) {
    if (empty($_FILES['archivos']['name'][0])) return;

    $extensionesPermitidas = ['png', 'jpg', 'jpeg'];
    $carpetaCarro = __DIR__ . '/../files/carros/folder_' . $idCarro;
    $carpetaMiniaturas = $carpetaCarro . '/thumbnail';
    if (!is_dir($carpetaMiniaturas)) mkdir($carpetaMiniaturas, 0755, true);

    $siguienteFoto = count(glob($carpetaCarro . '/foto_*.jpg')) + 1;

    foreach ($_FILES['archivos']['name'] as $i => $nombreOriginal) {
        if ($_FILES['archivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensionesPermitidas, true)) continue;

        $nombreGuardado = 'foto_' . $siguienteFoto . '_' . $idCarro . '.jpg';
        $rutaDestino = $carpetaCarro . '/' . $nombreGuardado;
        $rutaMiniatura = $carpetaMiniaturas . '/s_' . $nombreGuardado;
        $guardadoOk = guardarImagenJpgConMiniatura($_FILES['archivos']['tmp_name'][$i], $ext, $rutaDestino, $rutaMiniatura);

        if ($guardadoOk) {
            $siguienteFoto++;
            $sqlArch = "INSERT INTO tbl_hotwheels_carros_archivos (id_tbl_hotwheels_carros, archivo, nombre_original, user_ing, fecha_hora_ing) VALUES (?,?,?,?,NOW())";
            $paramsArch = [$idCarro, $nombreGuardado, $nombreOriginal, $userId];
            $insArch = $pdo->prepare($sqlArch);
            $insArch->execute($paramsArch);
            registrarAuditoria($pdo, 'INS', 'tbl_hotwheels_carros_archivos', (int)$pdo->lastInsertId(), interpolarSql($pdo, $sqlArch, $paramsArch), 'Adjunto de foto al auto');
        }
    }
}

/** Ruta (relativa al webroot sistema/) de la foto real de un auto. */
function resolverRutaArchivoCarro($idCarro, $nombreArchivo) {
    return 'files/carros/folder_' . $idCarro . '/' . $nombreArchivo;
}

/** Ruta de la miniatura de una foto de un auto, o null si aún no existe. */
function resolverRutaMiniaturaCarro($idCarro, $nombreArchivo) {
    $rel = 'files/carros/folder_' . $idCarro . '/thumbnail/s_' . $nombreArchivo;
    return file_exists(__DIR__ . '/../' . $rel) ? $rel : null;
}

/** Ruta (relativa al webroot sistema/) del logo real de una marca, o null si no existe. */
function resolverRutaLogoMarca($idMarca) {
    $rel = 'files/marcas/folder_' . $idMarca . '/fot_' . $idMarca . '.jpg';
    return file_exists(__DIR__ . '/../' . $rel) ? $rel : null;
}

/** Ruta de la miniatura del logo de una marca, o null si no existe. */
function resolverRutaMiniaturaLogoMarca($idMarca) {
    $rel = 'files/marcas/folder_' . $idMarca . '/thumbnail/s_fot_' . $idMarca . '.jpg';
    return file_exists(__DIR__ . '/../' . $rel) ? $rel : null;
}

/**
 * Sube y guarda el logo de una marca (jpg/jpeg/png, siempre convertido a jpg) desde
 * $_FILES['logo'], en files/marcas/folder_{idMarca}/fot_{idMarca}.jpg (reemplaza el
 * anterior si ya existía) y su miniatura JPG s_fot_{idMarca}.jpg en la subcarpeta
 * thumbnail/. A diferencia de las fotos de un auto, aquí solo hay un logo por marca,
 * así que el nombre de archivo no lleva numeración secuencial.
 */
function procesarLogoMarca($pdo, $idMarca, $userId) {
    if (empty($_FILES['logo']['name']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) return false;

    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) return false;

    $carpetaMarca = __DIR__ . '/../files/marcas/folder_' . $idMarca;
    $carpetaMiniaturas = $carpetaMarca . '/thumbnail';
    if (!is_dir($carpetaMiniaturas)) mkdir($carpetaMiniaturas, 0755, true);

    $nombreGuardado = 'fot_' . $idMarca . '.jpg';
    $rutaDestino = $carpetaMarca . '/' . $nombreGuardado;
    $rutaMiniatura = $carpetaMiniaturas . '/s_' . $nombreGuardado;

    $guardadoOk = guardarImagenJpgConMiniatura($_FILES['logo']['tmp_name'], $ext, $rutaDestino, $rutaMiniatura);
    if ($guardadoOk) {
        registrarAuditoria($pdo, 'UPD', 'tbl_hotwheels_marcas', $idMarca, '', 'Actualización del logo de la marca');
    }
    return $guardadoOk;
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
 * Ruta del script actual relativa a la raíz de sistema/ (p.ej. "settings/colores.php"
 * o "usuarios.php"), para construir enlaces que naveguen a la propia pantalla y sigan
 * resolviendo bien bajo el <base href> de includes/header.php (que apunta a la raíz de
 * sistema/, no al directorio del script — ver esa nota para el porqué).
 */
function rutaScriptActual() {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $marcador = '/sistema/';
    $pos = strpos($scriptName, $marcador);
    if ($pos !== false) {
        return substr($scriptName, $pos + strlen($marcador));
    }
    return ltrim($scriptName, '/');
}

/** Tamaños de página permitidos en el combo "Registros por página" de las listas. */
const OPCIONES_POR_PAGINA = [25, 50, 100, 200];

/**
 * Lee de $_GET los parámetros de paginación ("pagina" y "por_pagina", limitado a
 * OPCIONES_POR_PAGINA, 25 por defecto) y devuelve [pagina, porPagina, offset].
 * Usar el resultado como enteros literales en "LIMIT $porPagina OFFSET $offset" es
 * seguro (valores ya validados contra una lista fija) y evita el problema conocido
 * de PDO con MySQL al pasar LIMIT/OFFSET como parámetros "?" con prepares nativos
 * (PDO::ATTR_EMULATE_PREPARES está en false en config/db.php).
 */
function obtenerPaginacion() {
    $porPagina = (int)($_GET['por_pagina'] ?? 25);
    if (!in_array($porPagina, OPCIONES_POR_PAGINA, true)) $porPagina = 25;
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $offset = ($pagina - 1) * $porPagina;
    return [$pagina, $porPagina, $offset];
}

/**
 * Calcula qué números de página mostrar alrededor de la página actual, con "..."
 * donde se salten páginas intermedias (ventana de $delta páginas a cada lado, más
 * siempre la primera y la última).
 */
function paginasVentana($actual, $total, $delta = 1) {
    $rango = [];
    for ($i = max(1, $actual - $delta); $i <= min($total, $actual + $delta); $i++) $rango[] = $i;

    $conBordes = [];
    if ($rango[0] > 1) {
        $conBordes[] = 1;
        if ($rango[0] > 2) $conBordes[] = '...';
    }
    foreach ($rango as $r) $conBordes[] = $r;
    if (end($rango) < $total) {
        if (end($rango) < $total - 1) $conBordes[] = '...';
        $conBordes[] = $total;
    }
    return $conBordes;
}

/**
 * Imprime el paginador estándar de las listas: combo "registros por página"
 * (OPCIONES_POR_PAGINA) + navegación de páginas, preservando en la URL todos los
 * parámetros GET actuales (filtros de búsqueda, etc.) salvo "pagina"/"por_pagina".
 */
function renderizarPaginador($totalRegistros, $pagina, $porPagina) {
    $totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));

    // Enlace con ruta explícita (no solo "?query") porque <base href> (ver includes/header.php)
    // apunta a la raíz de sistema/, no al directorio del script actual: un href "?pagina=2"
    // resolvería contra esa raíz y perdería la subcarpeta (p.ej. settings/, cars/).
    $rutaActual = rutaScriptActual();
    $parametrosBase = $_GET;
    unset($parametrosBase['pagina'], $parametrosBase['por_pagina']);
    $construirUrl = function ($paginaDestino, $porPaginaDestino) use ($rutaActual, $parametrosBase) {
        return $rutaActual . '?' . http_build_query(array_merge($parametrosBase, ['pagina' => $paginaDestino, 'por_pagina' => $porPaginaDestino]));
    };

    echo '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">';

    echo '<span class="small text-muted">';
    if ($totalRegistros > 0) {
        $desde = ($pagina - 1) * $porPagina + 1;
        $hasta = min($totalRegistros, $pagina * $porPagina);
        echo "Mostrando $desde-$hasta de $totalRegistros";
    } else {
        echo 'Sin registros';
    }
    echo '</span>';

    echo '<div class="d-flex align-items-center gap-2">';
    echo '<label class="small text-muted mb-0" for="selectPorPagina">Por página</label>';
    echo '<select class="form-select form-select-sm" id="selectPorPagina" style="width:auto;" onchange="location.href=this.value">';
    foreach (OPCIONES_POR_PAGINA as $opcion) {
        echo '<option value="' . limpiar($construirUrl(1, $opcion)) . '" ' . ($porPagina === $opcion ? 'selected' : '') . '>' . $opcion . '</option>';
    }
    echo '</select>';

    if ($totalPaginas > 1) {
        echo '<ul class="pagination pagination-sm mb-0">';
        echo '<li class="page-item' . ($pagina <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . limpiar($construirUrl(max(1, $pagina - 1), $porPagina)) . '">&laquo;</a></li>';
        foreach (paginasVentana($pagina, $totalPaginas) as $p) {
            if ($p === '...') {
                echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            } else {
                echo '<li class="page-item' . ($p === $pagina ? ' active' : '') . '"><a class="page-link" href="' . limpiar($construirUrl($p, $porPagina)) . '">' . $p . '</a></li>';
            }
        }
        echo '<li class="page-item' . ($pagina >= $totalPaginas ? ' disabled' : '') . '"><a class="page-link" href="' . limpiar($construirUrl(min($totalPaginas, $pagina + 1), $porPagina)) . '">&raquo;</a></li>';
        echo '</ul>';
    }
    echo '</div>';
    echo '</div>';
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
