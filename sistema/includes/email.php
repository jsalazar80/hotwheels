<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envía un correo usando la configuración activa de tbl_email_config_sender (PHPMailer/SMTP)
 * y deja constancia en tbl_email_send: state=1 y comment='OK' si se envió correctamente,
 * o state=0 y comment=detalle del error si falló. El registro se guarda siempre,
 * incluso si el envío falla.
 *
 * @param array $datos {
 *     @var int         $tipo         Valor de type_email (por defecto 1)
 *     @var int|null    $tableId      Id del registro origen (ej. tbl_soportes.id) guardado en tbl_email_send.table_id
 *     @var string      $destinatario Correo destino
 *     @var string      $asunto       Asunto del correo
 *     @var string      $cuerpo       Cuerpo HTML del correo
 *     @var string|null $cc           Copia (si se omite, se usa la de tbl_email_config_sender)
 *     @var string|null $bcc          Copia oculta (si se omite, se usa la de tbl_email_config_sender)
 *     @var array       $adjuntos     Lista de ['nombre' => string, 'contenido' => string binario, 'tipo' => string mime]
 * }
 * @return array{ok: bool, mensaje: string}
 */
function enviarCorreo($pdo, array $datos) {
    $tipo = $datos['tipo'] ?? 1;
    $tableId = $datos['tableId'] ?? null;
    $destinatario = $datos['destinatario'] ?? '';
    $asunto = $datos['asunto'] ?? '';
    $cuerpo = $datos['cuerpo'] ?? '';
    $adjuntos = $datos['adjuntos'] ?? [];

    $config = $pdo->query("SELECT * FROM tbl_email_config_sender WHERE state = 1 ORDER BY id LIMIT 1")->fetch();

    $remitente = $config['ml_username'] ?? '';
    $cc = $datos['cc'] ?? ($config['cc'] ?? null);
    $bcc = $datos['bcc'] ?? ($config['bcc'] ?? null);
    $exito = false;
    $detalle = '';

    if (!$config) {
        $detalle = 'No hay una configuración de correo activa en tbl_email_config_sender.';
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $config['ml_host'];
            $mail->SMTPAuth = filter_var($config['id_tbl_email_config_auth'], FILTER_VALIDATE_BOOLEAN);
            $mail->Username = $config['ml_username'];
            $mail->Password = $config['ml_password'];
            $mail->SMTPSecure = $config['id_tbl_email_config_scrt'];
            $mail->Port = (int)$config['id_tbl_email_config_port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($config['ml_username'], $config['ml_sent_by_name'] ?: $config['ml_username']);
            $mail->addAddress($destinatario);
            if (!empty($cc)) $mail->addCC($cc);
            if (!empty($bcc)) $mail->addBCC($bcc);

            foreach ($adjuntos as $adj) {
                $mail->addStringAttachment($adj['contenido'], $adj['nombre'], 'base64', $adj['tipo'] ?? 'application/octet-stream');
            }

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpo;

            $mail->send();
            $exito = true;
            $detalle = 'OK';
        } catch (Exception $e) {
            $detalle = $mail->ErrorInfo ?: $e->getMessage();
        }
    }

    $sqlLog = "INSERT INTO tbl_email_send (type_email, table_id, titl_email, body_email, send_email, dest_email, cc_email, bcc_email, comment, user_ing, state) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    $paramsLog = [$tipo, $tableId, $asunto, $cuerpo, $remitente, $destinatario, $cc, $bcc, $detalle, $_SESSION['tsp_usuario_id'] ?? null, $exito ? 1 : 0];
    try {
        $stmt = $pdo->prepare($sqlLog);
        $stmt->execute($paramsLog);
        registrarAuditoria($pdo, 'INS', 'tbl_email_send', (int)$pdo->lastInsertId(), interpolarSql($pdo, $sqlLog, $paramsLog), $exito ? 'Envío de correo exitoso' : 'Intento de envío de correo fallido');
    } catch (Exception $e) {
        // El registro del envío nunca debe interrumpir el flujo principal
    }

    return ['ok' => $exito, 'mensaje' => $detalle];
}
