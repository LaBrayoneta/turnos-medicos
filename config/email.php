<?php
/**
 * config/email.php - Configuración de Email con PHPMailer
 * 
 * INSTALACIÓN:
 * composer require phpmailer/phpmailer
 * 
 * O descarga manual desde:
 * https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip
 */

// Configuración de email (Gmail SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'tuclinica@gmail.com'); // ⚠️ CAMBIAR
define('SMTP_PASSWORD', 'tu_contraseña_app'); // ⚠️ CAMBIAR (usar contraseña de aplicación)
define('SMTP_FROM_EMAIL', 'tuclinica@gmail.com');
define('SMTP_FROM_NAME', 'Clínica Vida Plena');

// ========== INSTRUCCIONES PARA GMAIL ==========
/*
1. Ir a https://myaccount.google.com/security
2. Activar "Verificación en 2 pasos"
3. Buscar "Contraseñas de aplicaciones"
4. Crear una contraseña para "Correo"
5. Copiar la contraseña de 16 dígitos y usarla en SMTP_PASSWORD
*/

require_once __DIR__ . '/../vendor/autoload.php'; // Si usas Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Enviar email usando PHPMailer
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
        
        // Configuración de caracteres
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
        $mail->AltBody = strip_tags($cuerpoHTML); // Versión texto plano
        
        $mail->send();
        
        error_log("✅ Email enviado a: $destinatario - Asunto: $asunto");
        return ['ok' => true, 'mensaje' => 'Email enviado exitosamente'];
        
    } catch (Exception $e) {
        error_log("❌ Error enviando email: {$mail->ErrorInfo}");
        return ['ok' => false, 'error' => "Error al enviar email: {$mail->ErrorInfo}"];
    }
}

/**
 * Plantilla: Turno Confirmado
 */
function emailTurnoConfirmado($datosEmail) {
    extract($datosEmail); // $nombrePaciente, $fecha, $hora, $nombreMedico, $especialidad
    
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
                    <h2>✅ Turno Confirmado</h2>
                    <p>Tu turno ha sido confirmado exitosamente.</p>
                </div>
                
                <p>Estimado/a <strong>$nombrePaciente</strong>,</p>
                
                <p>Nos complace informarte que tu turno médico ha sido <strong>confirmado</strong>.</p>
                
                <div class='info-box'>
                    <div class='info-row'><strong>📅 Fecha:</strong> $fecha</div>
                    <div class='info-row'><strong>🕐 Hora:</strong> $hora</div>
                    <div class='info-row'><strong>👨‍⚕️ Médico:</strong> $nombreMedico</div>
                    <div class='info-row'><strong>🏥 Especialidad:</strong> $especialidad</div>
                </div>
                
                <h3>📋 Recomendaciones:</h3>
                <ul>
                    <li>Llegar 10 minutos antes de la hora programada</li>
                    <li>Traer DNI y carnet de obra social</li>
                    <li>Si tiene estudios previos, traerlos</li>
                    <li>En caso de no poder asistir, cancelar con 24hs de anticipación</li>
                </ul>
            </div>
            
            <div class='footer'>
                <p>Este es un correo automático, por favor no responder.</p>
                <p><strong>Clínica Vida Plena</strong> | Tel: (291) 123-4567 | info@clinicavidaplena.com</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * Plantilla: Turno Rechazado
 */
function emailTurnoRechazado($datosEmail) {
    extract($datosEmail); // $nombrePaciente, $fecha, $hora, $motivo, $nombreStaff
    
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
                    <h2>❌ Turno No Confirmado</h2>
                    <p>Lamentablemente no pudimos confirmar tu turno.</p>
                </div>
                
                <p>Estimado/a <strong>$nombrePaciente</strong>,</p>
                
                <p>Lamentamos informarte que tu solicitud de turno para la fecha <strong>$fecha a las $hora</strong> no pudo ser confirmada.</p>
                
                <div class='motivo-box'>
                    <h3 style='margin: 0 0 10px 0; color: #92400e;'>📝 Motivo:</h3>
                    <p style='margin: 0; color: #78350f;'>$motivo</p>
                </div>
                
                <p><strong>¿Qué puedo hacer?</strong></p>
                <ul>
                    <li>Puedes solicitar un nuevo turno desde nuestro sistema</li>
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
                <p style='margin-top: 10px; font-size: 12px;'>Atendido por: $nombreStaff</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * Función helper para enviar email de confirmación de turno
 */
function notificarTurnoConfirmado($turnoId, PDO $pdo) {
    try {
        // Obtener datos del turno
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
        
        // Formatear datos
        $fechaObj = new DateTime($turno['fecha']);
        $datosEmail = [
            'nombrePaciente' => trim($turno['paciente_apellido'] . ', ' . $turno['paciente_nombre']),
            'fecha' => $fechaObj->format('d/m/Y'),
            'hora' => $fechaObj->format('H:i'),
            'nombreMedico' => 'Dr/a. ' . trim($turno['medico_apellido'] . ', ' . $turno['medico_nombre']),
            'especialidad' => $turno['especialidad']
        ];
        
        // Generar HTML
        $html = emailTurnoConfirmado($datosEmail);
        
        // Enviar
        return enviarEmail(
            $turno['paciente_email'],
            $datosEmail['nombrePaciente'],
            '✅ Turno Confirmado - Clínica Vida Plena',
            $html
        );
        
    } catch (Throwable $e) {
        error_log("Error en notificarTurnoConfirmado: " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Función helper para enviar email de rechazo de turno
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
        return enviarEmail(
            $turno['paciente_email'],
            $datosEmail['nombrePaciente'],
            '❌ Turno No Confirmado - Clínica Vida Plena',
            $html
        );
        
    } catch (Throwable $e) {
        error_log("Error en notificarTurnoRechazado: " . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}