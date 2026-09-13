<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(2);
require_once __DIR__ . '/includes/soporte_pdf_builder.php';
require_once __DIR__ . '/includes/email.php';

$id = (int)($_GET['id'] ?? 0);
$resultado = construirPdfSoporte($pdo, $id);

if ($resultado['error']) {
    redirigirConMensaje('soportes.php', 'error', $resultado['error']);
}

$s = $resultado['soporte'];
if (empty($s['cliente_correo'])) {
    redirigirConMensaje('soportes.php', 'error', 'El cliente no tiene un correo electrónico registrado.');
}

$nombrePdf = 'soporte_' . $s['numero'] . '.pdf';
$pdfBytes = $resultado['pdf']->Output($nombrePdf, 'S');

$asunto = 'Reporte de Soporte N° ' . $s['numero'] . ' - ' . $s['asunto'];
$cuerpo = '<p>Estimado(a) ' . limpiar($s['cliente']) . ',</p>'
    . '<p>Adjuntamos el reporte de su solicitud de soporte técnico <strong>N° ' . limpiar($s['numero']) . '</strong>.</p>'
    . '<p>Saludos cordiales.</p>';

$envio = enviarCorreo($pdo, [
    'tipo' => 1,
    'tableId' => $s['id'],
    'destinatario' => $s['cliente_correo'],
    'asunto' => $asunto,
    'cuerpo' => $cuerpo,
    'adjuntos' => [['nombre' => $nombrePdf, 'contenido' => $pdfBytes, 'tipo' => 'application/pdf']],
]);

if ($envio['ok']) {
    redirigirConMensaje('soportes.php', 'ok', 'Correo enviado correctamente a ' . $s['cliente_correo'] . '.');
} else {
    redirigirConMensaje('soportes.php', 'error', 'No se pudo enviar el correo: ' . $envio['mensaje']);
}
