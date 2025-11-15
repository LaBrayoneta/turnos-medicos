<?php
/**
 * test_email.php - Script de prueba para PHPMailer
 * Ejecutar desde el navegador: http://localhost/tu_proyecto/test_email.php
 */

require_once __DIR__ . '/config/email.php';

echo "<h1>🧪 Prueba de Sistema de Email</h1>";

// Cambiar por un email real donde quieras recibir la prueba
$emailPrueba = 'braiansalgado436@gmail.com'; // ⚠️ CAMBIAR

echo "<p>📧 Enviando email de prueba a: <strong>$emailPrueba</strong></p>";

try {
    $resultado = enviarEmail(
        $emailPrueba,
        'Usuario de Prueba',
        '🧪 Prueba de Sistema de Emails',
        '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; background: #f0f9ff; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 16px; padding: 32px; }
                h1 { color: #06b6d4; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>✅ ¡Sistema de Emails Funcionando!</h1>
                <p>Si estás leyendo este mensaje, significa que:</p>
                <ul>
                    <li>✅ PHPMailer está instalado correctamente</li>
                    <li>✅ Las credenciales de Gmail son válidas</li>
                    <li>✅ El servidor SMTP está respondiendo</li>
                    <li>✅ Tu sistema está listo para enviar notificaciones</li>
                </ul>
                <p><strong>Fecha de prueba:</strong> ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </body>
        </html>
        '
    );
    
    if ($resultado['ok']) {
        echo '<div style="background: #d1fae5; border: 2px solid #10b981; padding: 20px; border-radius: 10px; margin: 20px 0;">';
        echo '<h2 style="color: #065f46; margin: 0;">✅ EMAIL ENVIADO EXITOSAMENTE</h2>';
        echo '<p style="margin: 10px 0 0 0;">Revisa tu bandeja de entrada en: <strong>' . $emailPrueba . '</strong></p>';
        echo '<p style="margin: 10px 0 0 0; font-size: 14px; color: #059669;">Si no lo ves, revisa la carpeta de SPAM/Correo no deseado</p>';
        echo '</div>';
        
        echo '<div style="background: #e0f2fe; border: 2px solid #0891b2; padding: 20px; border-radius: 10px; margin: 20px 0;">';
        echo '<h3 style="color: #075985; margin: 0 0 10px 0;">🎉 ¡Sistema Listo!</h3>';
        echo '<p style="margin: 0;">Puedes proceder con la implementación completa del sistema de confirmación/rechazo de turnos.</p>';
        echo '</div>';
        
        echo '<p style="margin-top: 20px;"><strong>⚠️ IMPORTANTE:</strong> Elimina este archivo (test_email.php) después de la prueba por seguridad.</p>';
        
    } else {
        throw new Exception($resultado['error']);
    }
    
} catch (Throwable $e) {
    echo '<div style="background: #fee2e2; border: 2px solid #ef4444; padding: 20px; border-radius: 10px; margin: 20px 0;">';
    echo '<h2 style="color: #991b1b; margin: 0;">❌ ERROR AL ENVIAR EMAIL</h2>';
    echo '<p style="margin: 10px 0;"><strong>Mensaje de error:</strong></p>';
    echo '<pre style="background: #000; color: #fff; padding: 15px; border-radius: 8px; overflow-x: auto;">';
    echo htmlspecialchars($e->getMessage());
    echo '</pre>';
    
    echo '<h3 style="color: #991b1b; margin: 20px 0 10px 0;">🔧 Posibles soluciones:</h3>';
    echo '<ol style="line-height: 1.8;">';
    echo '<li><strong>Verifica las credenciales:</strong> Asegúrate de que SMTP_USERNAME y SMTP_PASSWORD en config/email.php sean correctos</li>';
    echo '<li><strong>Contraseña de aplicación:</strong> Debe ser una contraseña de aplicación de Gmail (16 dígitos), NO tu contraseña normal</li>';
    echo '<li><strong>Verificación en 2 pasos:</strong> Debe estar ACTIVADA en tu cuenta de Gmail</li>';
    echo '<li><strong>PHPMailer instalado:</strong> Ejecuta "composer require phpmailer/phpmailer"</li>';
    echo '<li><strong>Firewall/Antivirus:</strong> Puede estar bloqueando conexiones SMTP (puerto 587)</li>';
    echo '</ol>';
    echo '</div>';
}
?>

<div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 10px;">
    <h3>📚 Documentación Útil</h3>
    <ul>
        <li><a href="https://support.google.com/accounts/answer/185833" target="_blank">Cómo crear contraseñas de aplicación en Gmail</a></li>
        <li><a href="https://github.com/PHPMailer/PHPMailer" target="_blank">Documentación de PHPMailer</a></li>
        <li><a href="https://support.google.com/mail/answer/7126229" target="_blank">Configuración SMTP de Gmail</a></li>
    </ul>
</div>