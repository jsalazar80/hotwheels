<?php
require_once __DIR__ . '/../vendor/autoload.php';

class SoportePDF extends TCPDF {
    public $rutaLogo = null;
    public $nombreEmpresa = 'TechSupport';

    public function Header() {
        if ($this->rutaLogo) {
            $this->Image($this->rutaLogo, 12, 8, 22, 0, '', '', '', false, 300, '', false, false, 0);
        }
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(23, 65, 122);
        $this->SetXY(38, 10);
        $this->Cell(0, 8, $this->nombreEmpresa, 0, 1, 'L');
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->SetX(38);
        $this->Cell(0, 6, 'Reporte de Solicitud de Soporte Técnico', 0, 1, 'L');
        $this->SetDrawColor(23, 65, 122);
        $this->SetY(24);
        $this->Line(12, 24, $this->getPageWidth() - 12, 24);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages() . ' - Generado el ' . date('Y/m/d H:i:s'), 0, 0, 'C');
    }

    public function marcaDeAgua() {
        $xPrevio = $this->GetX();
        $yPrevio = $this->GetY();
        $this->SetAlpha(0.08);
        if ($this->rutaLogo) {
            $anchoPagina = $this->getPageWidth();
            $altoPagina = $this->getPageHeight();
            $tam = 120;
            $this->Image($this->rutaLogo, ($anchoPagina - $tam) / 2, ($altoPagina - $tam) / 2, $tam, 0, '', '', '', false, 300);
        } else {
            $this->StartTransform();
            $this->SetFont('helvetica', 'B', 60);
            $this->SetTextColor(23, 65, 122);
            $this->Rotate(45, $this->getPageWidth() / 2, $this->getPageHeight() / 2);
            $this->Text($this->getPageWidth() / 2 - 70, $this->getPageHeight() / 2, strtoupper($this->nombreEmpresa));
            $this->StopTransform();
        }
        $this->SetAlpha(1);
        // Text() dentro de la marca de agua reposiciona el cursor de escritura;
        // se restaura para que el contenido del ticket siga después del encabezado.
        $this->SetXY($xPrevio, $yPrevio);
    }
}

/**
 * Construye el PDF de una solicitud de soporte (requiere state = FINALIZADO).
 * Usado tanto por soporte_pdf.php (visualización) como por soporte_email.php (envío por correo).
 * Deja constancia en tbl_general_auditory (accion SEL) cada vez que el reporte se genera con éxito.
 *
 * @return array{error: string|null, soporte: array|null, pdf: SoportePDF|null}
 */
function construirPdfSoporte($pdo, $id) {
    $sqlReporte = "SELECT s.*, cl.nombre_comercial cliente, cl.razon_social cliente_razon_social, cl.rucci cliente_rucci,
        cl.telefono cliente_telefono, cl.celular cliente_celular, cl.correo cliente_correo, cl.direccion cliente_direccion,
        e.nombre estado_nombre, e.color estado_color,
        p.nombre prioridad_nombre,
        cat.nombre categoria_nombre,
        tp.nombre tipo_nombre,
        u.nombre tecnico_nombre
        FROM tbl_soportes s
        JOIN tbl_clientes cl ON cl.id = s.id_tbl_clientes
        JOIN tbl_soporte_estado e ON e.id = s.state
        JOIN tbl_soporte_prioridad p ON p.id = s.id_tbl_soporte_prioridad
        LEFT JOIN tbl_soporte_categoria cat ON cat.id = s.id_tbl_soporte_categoria
        LEFT JOIN tbl_soporte_tipo tp ON tp.id = s.id_tbl_soporte_tipo
        LEFT JOIN tbl_admin_user u ON u.id = s.id_tbl_admin_user_asignado
        WHERE s.id = ?";
    $paramsReporte = [$id];
    $stmt = $pdo->prepare($sqlReporte);
    $stmt->execute($paramsReporte);
    $s = $stmt->fetch();

    if (!$s) {
        return ['error' => 'Solicitud no encontrada.', 'soporte' => null, 'pdf' => null];
    }
    if ((int)$s['state'] !== ESTADO_SOPORTE_FINALIZADO) {
        return ['error' => 'El PDF solo se puede generar cuando la solicitud está en estado Finalizado.', 'soporte' => $s, 'pdf' => null];
    }

    registrarAuditoria($pdo, 'SEL', 'tbl_soportes', $s['id'], interpolarSql($pdo, $sqlReporte, $paramsReporte), 'Reporte de Solicitud de Soporte N° ' . $s['numero']);

    $config = obtenerConfiguracion($pdo);
    $rutaLogo = null;
    if (!empty($config['logo'])) {
        $candidato = __DIR__ . '/../assets/img/' . $config['logo'];
        if (file_exists($candidato)) $rutaLogo = $candidato;
    }

    $pdf = new SoportePDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->rutaLogo = $rutaLogo;
    $pdf->nombreEmpresa = $config['nombre_empresa'];
    $pdf->SetCreator('TechSupport');
    $pdf->SetAuthor($config['nombre_empresa']);
    $pdf->SetTitle('Soporte ' . $s['numero']);
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(12, 28, 12);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->AddPage();
    $pdf->marcaDeAgua();

    $moneda = '$';

    // ---- Encabezado del ticket ----
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(23, 65, 122);
    $pdf->Cell(0, 8, 'Ticket N° ' . $s['numero'] . ' - ' . $s['asunto'], 0, 1, 'L');

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(23, 65, 122);
    $pdf->Cell(0, 7, '  Estado: ' . strtoupper($s['estado_nombre']), 0, 1, 'L', true);
    $pdf->Ln(2);

    // ---- Datos generales ----
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetFont('helvetica', '', 10);

    $filas = [
        ['Fecha', formatoFecha($s['fecha'])],
        ['Cliente', $s['cliente'] . ($s['cliente_razon_social'] ? ' - ' . $s['cliente_razon_social'] : '')],
        ['RUC/Cédula', $s['cliente_rucci']],
        ['Teléfono / Celular', trim(($s['cliente_telefono'] ?: '-') . ' / ' . ($s['cliente_celular'] ?: '-'))],
        ['Correo', $s['cliente_correo'] ?: '-'],
        ['Solicitante', $s['solicitante'] ?: '-'],
        ['Categoría', $s['categoria_nombre'] ?: '-'],
        ['Tipo de atención', $s['tipo_nombre'] ?: '-'],
        ['Prioridad', $s['prioridad_nombre']],
        ['Técnico asignado', $s['tecnico_nombre'] ?: '-'],
    ];
    foreach ($filas as $fila) {
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->Cell(45, 6.5, $fila[0] . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->MultiCell(0, 6.5, $fila[1], 0, 'L', false, 1);
    }

    $pdf->Ln(2);
    $secciones = [
        ['Descripción', $s['descripcion']],
        ['Análisis', $s['analisis']],
        ['Solución', $s['solucion']],
        ['Observación', $s['observacion']],
        ['Recomendación', $s['recomendacion']],
    ];
    foreach ($secciones as $sec) {
        if (trim((string)$sec[1]) === '') continue;
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(23, 65, 122);
        $pdf->Cell(0, 7, $sec[0], 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->MultiCell(0, 6, $sec[1], 0, 'L', false, 1);
        $pdf->Ln(1);
    }

    // ---- Tiempo y costo ----
    if ($s['fecha_hora_solved_str'] || $s['fecha_hora_solved_end'] || (float)$s['monto_total'] > 0) {
        $pdf->Ln(1);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(23, 65, 122);
        $pdf->Cell(0, 7, 'Tiempo y Costo de Atención', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->Cell(60, 6, 'Inicio: ' . ($s['fecha_hora_solved_str'] ? formatoFechaHora($s['fecha_hora_solved_str']) : '-'), 0, 0);
        $pdf->Cell(60, 6, 'Fin: ' . ($s['fecha_hora_solved_end'] ? formatoFechaHora($s['fecha_hora_solved_end']) : '-'), 0, 1);
        $pdf->Cell(60, 6, 'Tiempo total: ' . (int)$s['total_minutos'] . ' min', 0, 0);
        $pdf->Cell(60, 6, 'Valor/hora: ' . $moneda . number_format((float)$s['valor_por_hora'], 2), 0, 1);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, 'Monto total: ' . $moneda . number_format((float)$s['monto_total'], 2), 0, 1);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(0, 5, 'Precios no incluyen IVA', 0, 1);
    }

    // ---- Fotos adjuntas (hoja final, cuadrícula de 3 columnas) ----
    $stmtFotos = $pdo->prepare("SELECT archivo, nombre_original FROM tbl_soportes_archivos WHERE id_tbl_soportes = ? AND tipo_archivo = 'imagen' AND state = 1 ORDER BY id ASC");
    $stmtFotos->execute([$s['id']]);
    $fotos = $stmtFotos->fetchAll();

    if ($fotos) {
        $columnas = 3;
        $gutter = 4;
        $altoImagen = 45;
        $altoCelda = $altoImagen + 6;
        $margenIzq = 12;
        $anchoUtil = $pdf->getPageWidth() - 24;
        $anchoCelda = ($anchoUtil - ($columnas - 1) * $gutter) / $columnas;
        $limiteInferior = $pdf->getPageHeight() - 18;

        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(23, 65, 122);
        $pdf->Cell(0, 8, 'Fotos Adjuntas', 0, 1, 'L');
        $pdf->Ln(2);

        $col = 0;
        $y = $pdf->GetY();
        foreach ($fotos as $foto) {
            $rutaFoto = __DIR__ . '/../' . resolverRutaArchivoSoporte($s['id'], $foto['archivo']);
            if (!file_exists($rutaFoto)) continue;

            if ($col === 0 && ($y + $altoCelda) > $limiteInferior) {
                $pdf->AddPage();
                $y = $pdf->GetY();
            }

            $x = $margenIzq + $col * ($anchoCelda + $gutter);
            $pdf->Rect($x, $y, $anchoCelda, $altoImagen, 'D');
            $pdf->Image($rutaFoto, $x, $y, $anchoCelda, $altoImagen, '', '', '', true, 150, '', false, false, 0, 'CM');
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetXY($x, $y + $altoImagen + 1);
            $pdf->MultiCell($anchoCelda, 4, $foto['nombre_original'], 0, 'C');

            $col++;
            if ($col >= $columnas) {
                $col = 0;
                $y += $altoCelda + $gutter;
            }
        }
    }

    return ['error' => null, 'soporte' => $s, 'pdf' => $pdf];
}
