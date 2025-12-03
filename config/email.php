<?php
/**
 * config/email.php - Sistema de Notificaciones por Email
 * 
 * INSTALACIÓN DE PHPMAILER:
 * composer require phpmailer/phpmailer
 * 
 * CONFIGURACIÓN DE GMAIL:
 * 1. Ir a https://myaccount.google.com/security
 * 2. Activar "Verificación en 2 pasos"
 * 3. Buscar "Contraseñas de aplicaciones"
 * 4. Crear contraseña para "Correo"
 * 5. Usar esa contraseña en SMTP_PASSWORD
 */

// ========== CONFIGURACIÓN ==========
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'braiansalgado436@gmail.com'); // ⚠️ CAMBIAR
define('SMTP_PASSWORD', 'hwnx falj bceo ettn'); // ⚠️ CAMBIAR
define('SMTP_FROM_EMAIL', 'tuclinica@gmail.com');
define('SMTP_FROM_NAME', 'Clínica Vida Plena');

// Cargar PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Función principal para enviar emails
 */
function enviarEmail($destinatario, $nombreDestinatario, $asunto, $cuerpoHTML) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Configuración de encoding
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // Remitente
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Destinatario
        $mail->addAddress($destinatario, $nombreDestinatario);
        
        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHTML;
        $mail->AltBody = strip_tags($cuerpoHTML);
        
        $mail->send();
        
        error_log("✅ Email enviado a: $destinatario - Asunto: $asunto");
        return ['ok' => true, 'mensaje' => 'Email enviado exitosamente'];
        
    } catch (Exception $e) {
        error_log("❌ Error enviando email: {$mail->ErrorInfo}");
        return ['ok' => false, 'error' => "Error al enviar email: {$mail->ErrorInfo}"];
    }
}

/**
 * PLANTILLA: Turno Confirmado
 */
function emailTurnoConfirmado($datosEmail) {
    extract($datosEmail);
    // $nombrePaciente, $fecha, $hora, $nombreMedico, $especialidad, $nombreStaff
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f9ff; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .content { padding: 30px; }
            .status { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 8px; }
            .status h2 { color: #065f46; margin: 0 0 10px 0; font-size: 20px; }
            .info-box { background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .info-row { margin: 10px 0; font-size: 16px; }
            .info-row strong { color: #0891b2; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 14px; }
            .button { display: inline-block; background: #06b6d4; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏥 Clínica Vida Plena</h1>
            </div>
            
            <div class='content'>
                <div class='status'>
                    <h2>✅ ¡Tu turno ha sido CONFIRMADO!</h2>
                    <p>Tu solicitud de turno ha sido revisada y aprobada por nuestro equipo.</p>
                </div>
                
                <p>Estimado/a <strong>$nombrePaciente</strong>,</p>
                
                <p>Nos complace informarte que tu turno médico ha sido <strong>confirmado</strong>.</p>
                
                <div class='info-box'>
                    <div class='info-row'><strong>📅 Fecha:</strong> $fecha</div>
                    <div class='info-row'><strong>🕐 Hora:</strong> $hora</div>
                    <div class='info-row'><strong>👨‍⚕️ Médico:</strong> $nombreMedico</div>
                    <div class='info-row'><strong>🏥 Especialidad:</strong> $especialidad</div>
                </div>
                
                <h3>📋 Recordatorios Importantes:</h3>
                <ul>
                    <li><strong>Llegar 10 minutos antes</strong> de la hora programada</li>
                    <li>Traer <strong>DNI y carnet de obra social</strong></li>
                    <li>Si tiene estudios previos, traerlos</li>
                    <li>En caso de no poder asistir, <strong>cancelar con 24hs de anticipación</strong></li>
                    <li>Usar barbijo/tapabocas en las instalaciones</li>
                </ul>
                
                <p style='margin-top: 20px;'>Si necesitas reprogramar o tienes alguna consulta, no dudes en contactarnos.</p>
            </div>
            
            <div class='footer'>
                <p>Este es un correo automático, por favor no responder.</p>
                <p><strong>Clínica Vida Plena</strong> | Tel: (291) 123-4567 | info@clinicavidaplena.com</p>
                <p style='margin-top: 10px; font-size: 12px;'>Confirmado por: $nombreStaff</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * PLANTILLA: Turno Rechazado
 */
function emailTurnoRechazado($datosEmail) {
    extract($datosEmail);
    // $nombrePaciente, $fecha, $hora, $motivo, $nombreStaff
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f9ff; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .content { padding: 30px; }
            .status { background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 8px; }
            .status h2 { color: #991b1b; margin: 0 0 10px 0; font-size: 20px; }
            .info-box { background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .motivo-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 8px; }
            .button { display: inline-block; background: #06b6d4; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; text-align: center; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏥 Clínica Vida Plena</h1>
            </div>
            
            <div class='content'>
                <div class='status'>
                    <h2>❌ Tu turno no pudo ser confirmado</h2>
                    <p>Lamentablemente no pudimos confirmar tu solicitud de turno.</p>
                </div>
                
                <p>Estimado/a <strong>$nombrePaciente</strong>,</p>
                
                <p>Lamentamos informarte que tu solicitud de turno para la fecha <strong>$fecha a las $hora</strong> no pudo ser confirmada.</p>
                
                <div class='motivo-box'>
                    <h3 style='margin: 0 0 10px 0; color: #92400e;'>📝 Motivo:</h3>
                    <p style='margin: 0; color: #78350f;'>$motivo</p>
                </div>
                
                <p><strong>¿Qué puedo hacer?</strong></p>
                <ul>
                    <li>Puedes <strong>solicitar un nuevo turno</strong> desde nuestro sistema</li>
                    <li>Contactarnos telefónicamente para más opciones</li>
                    <li>Consultar disponibilidad con otros profesionales</li>
                </ul>
                
                <div style='text-align: center;'>
                    <a href='https://tuclinica.com/turnos' class='button'>Reservar Nuevo Turno</a>
                </div>
                
                <p style='margin-top: 20px;'>Si tienes dudas o consultas, no dudes en contactarnos.</p>
            </div>
            
            <div class='footer'>
                <p>Este es un correo automático, por favor no responder.</p>
                <p><strong>Clínica Vida Plena</strong> | Tel: (291) 123-4567 | info@clinicavidaplena.com</p>
                <p style='margin-top: 10px; font-size: 12px;'>Procesado por: $nombreStaff</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * Función helper para notificar confirmación de turno
 */
function notificarTurnoConfirmado($turnoId, $staffNombre, PDO $pdo) {
    try {
        // Obtener datos del turno con información del paciente
        $stmt = $pdo->prepare("
            SELECT 
                t.fecha,
                u.nombre as paciente_nombre,
                u.apellido as paciente_apellido,
                u.email as paciente_email,
                um.nombre as medico_nombre,
                um.apellido as medico_apellido,
                e.nombre as especialidad
            FROM turno t
            JOIN paciente p ON p.Id_paciente = t.Id_paciente
            JOIN usuario u ON u.Id_usuario = p.Id_usuario
            JOIN medico m ON m.Id_medico = t.Id_medico
            JOIN usuario um ON um.Id_usuario = m.Id_usuario
            LEFT JOIN especialidad e ON e.Id_Especialidad = m.Id_Especialidad
            WHERE t.Id_turno = ?
        ");
        $stmt->execute([$turnoId]);
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$turno) {
            return ['ok' => false, 'error' => 'Turno no encontrado'];
        }
        
        // Formatear fecha y hora
        $fechaObj = new DateTime($turno['fecha']);
        $datosEmail = [
            'nombrePaciente' => trim($turno['paciente_apellido'] . ', ' . $turno['paciente_nombre']),
            'fecha' => $fechaObj->format('d/m/Y'),
            'hora' => $fechaObj->format('H:i'),
            'nombreMedico' => 'Dr/a. ' . trim($turno['medico_apellido'] . ', ' . $turno['medico_nombre']),
            'especialidad' => $turno['especialidad'] ?? 'Sin especialidad',
            'nombreStaff' => $staffNombre
        ];
        
        // Generar HTML
        $html = emailTurnoConfirmado($datosEmail);
        
        // Enviar
        $resultado = enviarEmail(
            $turno['paciente_email'],
            $datosEmail['nombrePaciente'],
            '✅ Turno Confirmado - Clínica Vida Plena',
            $html
        );
        
        // Marcar email como enviado
        if ($resultado['ok']) {
            $stmt = $pdo->prepare("UPDATE turno SET email_enviado = 1 WHERE Id_turno = ?");
            $stmt->execute([$turnoId]);
        }
        
        return $resultado;
        
    } catch (Throwable $e) {
        error_log("Error en notificarTurnoConfirmado: " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Función helper para notificar rechazo de turno
 */
function notificarTurnoRechazado($turnoId, $motivo, $staffNombre, PDO $pdo) {
    try {
        // Obtener datos del turno
        $stmt = $pdo->prepare("
            SELECT 
                t.fecha,
                u.nombre as paciente_nombre,
                u.apellido as paciente_apellido,
                u.email as paciente_email
            FROM turno t
            JOIN paciente p ON p.Id_paciente = t.Id_paciente
            JOIN usuario u ON u.Id_usuario = p.Id_usuario
            WHERE t.Id_turno = ?
        ");
        $stmt->execute([$turnoId]);
        $turno = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$turno) {
            return ['ok' => false, 'error' => 'Turno no encontrado'];
        }
        
        // Formatear datos
        $fechaObj = new DateTime($turno['fecha']);
        $datosEmail = [
            'nombrePaciente' => trim($turno['paciente_apellido'] . ', ' . $turno['paciente_nombre']),
            'fecha' => $fechaObj->format('d/m/Y'),
            'hora' => $fechaObj->format('H:i'),
            'motivo' => $motivo,
            'nombreStaff' => $staffNombre
        ];
        
        // Generar HTML
        $html = emailTurnoRechazado($datosEmail);
        
        // Enviar
        $resultado = enviarEmail(
            $turno['paciente_email'],
            $datosEmail['nombrePaciente'],
            '❌ Turno No Confirmado - Clínica Vida Plena',
            $html
        );
        
        // Marcar email como enviado
        if ($resultado['ok']) {
            $stmt = $pdo->prepare("UPDATE turno SET email_enviado = 1 WHERE Id_turno = ?");
            $stmt->execute([$turnoId]);
        }
        
        return $resultado;
        
    } catch (Throwable $e) {
        error_log("Error en notificarTurnoRechazado: " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
function emailTurnoReprogramado($datosEmail) {
    extract($datosEmail);
    // $nombrePaciente, $fecha_anterior, $fecha_nueva, $nombreMedico, $especialidad, $nombreStaff
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f9ff; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .content { padding: 30px; }
            .status { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 8px; }
            .status h2 { color: #92400e; margin: 0 0 10px 0; font-size: 20px; }
            .info-box { background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .info-row { margin: 10px 0; font-size: 16px; }
            .info-row strong { color: #0891b2; }
            .comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
            .date-card { background: #f8fafc; padding: 15px; border-radius: 8px; border: 2px solid #e2e8f0; }
            .date-card.old { border-color: #ef4444; }
            .date-card.new { border-color: #10b981; background: #d1fae5; }
            .date-card h3 { margin: 0 0 8px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
            .date-card.old h3 { color: #991b1b; }
            .date-card.new h3 { color: #065f46; }
            .date-card .date { font-size: 18px; font-weight: bold; color: #1e293b; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏥 Clínica Vida Plena</h1>
            </div>
            
            <div class='content'>
                <div class='status'>
                    <h2>🔄 Tu turno ha sido REPROGRAMADO</h2>
                    <p>El horario de tu consulta médica ha sido modificado.</p>
                </div>
                
                <p>Estimado/a <strong>$nombrePaciente</strong>,</p>
                
                <p>Te informamos que tu turno médico ha sido <strong>reprogramado</strong> por nuestro equipo.</p>
                
                <div class='comparison'>
                    <div class='date-card old'>
                        <h3>❌ Fecha Anterior</h3>
                        <div class='date'>$fecha_anterior</div>
                    </div>
                    <div class='date-card new'>
                        <h3>✅ Nueva Fecha</h3>
                        <div class='date'>$fecha_nueva</div>
                    </div>
                </div>
                
                <div class='info-box'>
                    <div class='info-row'><strong>👨‍⚕️ Médico:</strong> $nombreMedico</div>
                    <div class='info-row'><strong>🏥 Especialidad:</strong> $especialidad</div>
                </div>
                
                <h3>📋 Recordatorios:</h3>
                <ul>
                    <li><strong>Llegar 10 minutos antes</strong> de la hora programada</li>
                    <li>Traer <strong>DNI y carnet de obra social</strong></li>
                    <li>Si tiene estudios previos, traerlos</li>
                    <li>Usar barbijo/tapabocas en las instalaciones</li>
                </ul>
                
                <p style='margin-top: 20px;'>Si necesitas consultar algo o no puedes asistir, por favor contactanos.</p>
            </div>
            
            <div class='footer'>
                <p>Este es un correo automático, por favor no responder.</p>
                <p><strong>Clínica Vida Plena</strong> | Tel: (291) 123-4567 | info@clinicavidaplena.com</p>
                <p style='margin-top: 10px; font-size: 12px;'>Reprogramado por: $nombreStaff</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}


function emailTurnoCancelado($datosEmail) {
    extract($datosEmail);
    // $nombrePaciente, $fecha, $hora, $nombreMedico, $especialidad, $motivo, $nombreStaff
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f9ff; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .content { padding: 30px; }
            .status { background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; border-radius: 8px; }
            .status h2 { color: #991b1b; margin: 0 0 10px 0; font-size: 20px; }
            .info-box { background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .motivo-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 8px; }
            .button { display: inline-block; background: #06b6d4; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; text-align: center; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; color: #64748b; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏥 Clínica Vida Plena</h1>
            </div>
            
            <div class='content'>
                <div class='status'>
                    <h2>❌ Tu turno ha sido CANCELADO</h2>
                    <p>Lamentablemente tu consulta programada no se realizará.</p>
                </div>
                
                <p>Estimado/a <strong>$nombrePaciente</strong>,</p>
                
                <p>Te informamos que tu turno médico programado para el <strong>$fecha a las $hora</strong> ha sido <strong>cancelado</strong>.</p>
                
                <div class='info-box'>
                    <div style='margin: 10px 0; font-size: 16px;'><strong style='color: #0891b2;'>👨‍⚕️ Médico:</strong> $nombreMedico</div>
                    <div style='margin: 10px 0; font-size: 16px;'><strong style='color: #0891b2;'>🏥 Especialidad:</strong> $especialidad</div>
                </div>
                
                <div class='motivo-box'>
                    <h3 style='margin: 0 0 10px 0; color: #92400e;'>📝 Motivo de la cancelación:</h3>
                    <p style='margin: 0; color: #78350f;'>$motivo</p>
                </div>
                
                <p><strong>¿Qué puedo hacer?</strong></p>
                <ul>
                    <li>Puedes <strong>solicitar un nuevo turno</strong> desde nuestro sistema</li>
                    <li>Contactarnos telefónicamente para más información</li>
                    <li>Consultar disponibilidad con otros profesionales</li>
                </ul>
                
                <div style='text-align: center;'>
                    <a href='https://tuclinica.com/turnos' class='button'>Reservar Nuevo Turno</a>
                </div>
                
                <p style='margin-top: 20px;'>Lamentamos las molestias que esto pueda ocasionar. Si tienes dudas, no dudes en contactarnos.</p>
            </div>
            
            <div class='footer'>
                <p>Este es un correo automático, por favor no responder.</p>
                <p><strong>Clínica Vida Plena</strong> | Tel: (291) 123-4567 | info@clinicavidaplena.com</p>
                <p style='margin-top: 10px; font-size: 12px;'>Cancelado por: $nombreStaff</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}
?>