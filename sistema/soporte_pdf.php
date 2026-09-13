<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requerirPermiso(2);
require_once __DIR__ . '/includes/soporte_pdf_builder.php';

$id = (int)($_GET['id'] ?? 0);
$resultado = construirPdfSoporte($pdo, $id);

if ($resultado['error']) die($resultado['error']);

$resultado['pdf']->Output('soporte_' . $resultado['soporte']['numero'] . '.pdf', 'I');
