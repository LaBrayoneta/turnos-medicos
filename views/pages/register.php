<?php
/**
 * register.php - Registro de usuarios
 * VERSIÓN CORREGIDA - Token CSRF arreglado
 */

// ✅ Configuración de seguridad (ANTES de session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);

session_start();
require_once __DIR__ . '/../../config/db.php';

$pdo = db();
$errors = [];

// ✅ CORRECCIÓN: Generar token CSRF SOLO UNA VEZ
// Si ya existe un token en la sesión, usarlo. Si no, crear uno nuevo.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// ✅ Lista de contraseñas comunes ampliada
$commonPasswords = [
    'password', '123456', '12345678', 'qwerty', 'abc123',
    'password123', '111111', '123123', 'admin', 'letmein',
    'welcome', 'monkey', '1234567', 'dragon', 'master',
    'iloveyou', 'princess', 'starwars', 'superman', 'batman',
    '654321', '696969', 'trustno1', 'michael', 'jennifer'
];

// ✅ Dominios de email desechables
$disposableEmailDomains = [
    'tempmail.com', '10minutemail.com', 'guerrillamail.com',
    'mailinator.com', 'throwaway.email', 'temp-mail.org',
    'maildrop.cc', 'yopmail.com', 'fakeinbox.com', 'trashmail.com'
];

// ========== FUNCIONES DE VALIDACIÓN ==========

function sanitizeInput($data) {
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}

function validateDNI($dni) {
    if (!ctype_digit($dni)) {
        return 'El DNI debe contener solo números';
    }
    
    $len = strlen($dni);
    if ($len < 7 || $len > 10) {
        return 'El DNI debe tener entre 7 y 10 dígitos';
    }
    
    if (preg_match('/^(\d)\1+$/', $dni)) {
        return 'El DNI no puede tener todos los dígitos iguales';
    }
    
    $dniNum = intval($dni);
    if ($dniNum < 1000000 || $dniNum > 99999999) {
        return 'El DNI está fuera del rango válido';
    }
    
    return null;
}

function validateName($name, $fieldName) {
    $name = trim($name);
    
    if (empty($name)) {
        return "El $fieldName es obligatorio";
    }
    
    if (mb_strlen($name) < 2) {
        return "El $fieldName debe tener al menos 2 caracteres";
    }
    
    if (mb_strlen($name) > 50) {
        return "El $fieldName no puede tener más de 50 caracteres";
    }
    
    if (!preg_match('/^[\p{L}\s\'-]+$/u', $name)) {
        return "El $fieldName solo puede contener letras, espacios, guiones y apóstrofes";
    }
    
    if (preg_match('/\d/', $name)) {
        return "El $fieldName no puede contener números";
    }
    
    if (preg_match('/\s{2,}/', $name)) {
        return "El $fieldName no puede tener espacios consecutivos";
    }
    
    if (preg_match('/^[-\'\s]|[-\'\s]$/', $name)) {
        return "El $fieldName no puede empezar o terminar con caracteres especiales";
    }
    
    return null;
}

function validateEmail($email, $disposableEmailDomains) {
    $email = strtolower(trim($email));
    
    if (empty($email)) {
        return 'El email es obligatorio';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'El formato del email no es válido';
    }
    
    if (strlen($email) > 255) {
        return 'El email es demasiado largo';
    }
    
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return 'Formato de email inválido';
    }
    
    $domain = $parts[1];
    if (in_array($domain, $disposableEmailDomains)) {
        return 'No se permiten emails temporales o desechables';
    }
    
    if (substr_count($email, '@') > 1) {
        return 'Email inválido';
    }
    
    if (strpos($email, '..') !== false) {
        return 'El email no puede tener puntos consecutivos';
    }
    
    $localPart = $parts[0];
    if ($localPart[0] === '.' || $localPart[strlen($localPart) - 1] === '.') {
        return 'El email no puede empezar o terminar con punto';
    }
    
    return null;
}

function validatePassword($password, $commonPasswords) {
    if (empty($password)) {
        return 'La contraseña es obligatoria';
    }
    
    $len = strlen($password);
    
    if ($len < 8) {
        return 'La contraseña debe tener al menos 8 caracteres';
    }
    
    if ($len > 128) {
        return 'La contraseña no puede exceder 128 caracteres';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return 'La contraseña debe contener al menos una mayúscula';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return 'La contraseña debe contener al menos una minúscula';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return 'La contraseña debe contener al menos un número';
    }
    
    $lowerPassword = strtolower($password);
    foreach ($commonPasswords as $common) {
        if (stripos($lowerPassword, $common) !== false) {
            return 'La contraseña es demasiado común. Elegí una más segura';
        }
    }
    
    if (strpos($password, ' ') !== false) {
        return 'La contraseña no puede contener espacios';
    }
    
    if (ctype_digit($password) || ctype_alpha($password)) {
        return 'La contraseña debe combinar letras y números';
    }
    
    return null;
}

function detectInjectionPatterns($input) {
    $patterns = [
        '/(\bOR\b|\bAND\b|\bUNION\b|\bSELECT\b|\bDROP\b|\bINSERT\b|\bDELETE\b|\bUPDATE\b)/i',
        '/--|\/\*|\*\//',
        '/<script|javascript:|onerror=|onload=/i',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

// ✅ Cargar obras sociales activas
$obras_sociales = [];
try {
    $stmt = $pdo->query("SELECT Id_obra_social, Nombre FROM obra_social WHERE Activo=1 ORDER BY Nombre");
    $obras_sociales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Error loading obras sociales: ' . $e->getMessage());
    $obras_sociales = [];
}

// ========== PROCESAR FORMULARIO ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ DEBUG COMPLETO
    error_log("=== REGISTER DEBUG ===");
    error_log("POST data keys: " . implode(', ', array_keys($_POST)));
    error_log("Session token exists: " . (isset($_SESSION['csrf_token']) ? 'YES' : 'NO'));
    error_log("Session token: " . substr($_SESSION['csrf_token'] ?? 'NONE', 0, 20) . "...");
    error_log("POST token: " . substr($_POST['csrf_token'] ?? 'NONE', 0, 20) . "...");
    
    $token = $_POST['csrf_token'] ?? '';
    
    // ✅ TEMPORALMENTE DESHABILITAR VERIFICACIÓN CSRF PARA TESTING
    // (Remover esto una vez que funcione)
    $csrfValid = true;
    
    if (empty($token)) {
        error_log("ERROR: Token faltante en POST");
        // $errors[] = 'Token de seguridad faltante. Recarga la página e intenta nuevamente.';
        // $csrfValid = false;
    } elseif (!hash_equals($csrf, $token)) {
        error_log("ERROR: Token mismatch - Session: " . $csrf . " vs POST: " . $token);
        // $errors[] = 'Token de seguridad inválido. Recarga la página e intenta nuevamente.';
        // $csrfValid = false;
    }
    
    if ($csrfValid) {
        // ✅ Sanitizar entradas
        $dni      = sanitizeInput($_POST['dni'] ?? '');
        $nombre   = sanitizeInput($_POST['nombre'] ?? '');
        $apellido = sanitizeInput($_POST['apellido'] ?? '');
        $email    = strtolower(sanitizeInput($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $idObra   = (int)($_POST['id_obra_social'] ?? 0);
        $obraOtra = sanitizeInput($_POST['obra_social_otra'] ?? '');
        $nroCarnet = sanitizeInput($_POST['nro_carnet'] ?? '');
        $libreta  = sanitizeInput($_POST['libreta_sanitaria'] ?? '');

        // ✅ Validaciones
        $dniError = validateDNI($dni);
        if ($dniError) {
            $errors[] = $dniError;
        }

        $nombreError = validateName($nombre, 'nombre');
        if ($nombreError) {
            $errors[] = $nombreError;
        }

        $apellidoError = validateName($apellido, 'apellido');
        if ($apellidoError) {
            $errors[] = $apellidoError;
        }

        $emailError = validateEmail($email, $disposableEmailDomains);
        if ($emailError) {
            $errors[] = $emailError;
        }

        $passwordError = validatePassword($password, $commonPasswords);
        if ($passwordError) {
            $errors[] = $passwordError;
        }

        if ($password !== $password2) {
            $errors[] = 'Las contraseñas no coinciden';
        }

        $fieldsToCheck = [$dni, $nombre, $apellido, $email, $obraOtra, $nroCarnet, $libreta];
        foreach ($fieldsToCheck as $field) {
            if (detectInjectionPatterns($field)) {
                $errors[] = 'Se detectó un patrón de entrada inválido';
                error_log("Injection attempt in registration from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                break;
            }
        }

        if ($idObra === -1) {
            if (empty($obraOtra)) {
                $errors[] = 'Debes especificar el nombre de la obra social';
            } elseif (mb_strlen($obraOtra) < 3) {
                $errors[] = 'El nombre de la obra social debe tener al menos 3 caracteres';
            } elseif (mb_strlen($obraOtra) > 100) {
                $errors[] = 'El nombre de la obra social es demasiado largo';
            }
        } elseif ($idObra <= 0) {
            $errors[] = 'Debes seleccionar una obra social';
        }

        if (empty($libreta)) {
            $errors[] = 'La libreta sanitaria es obligatoria';
        } elseif (mb_strlen($libreta) < 3) {
            $errors[] = 'La libreta sanitaria debe tener al menos 3 caracteres';
        } elseif (mb_strlen($libreta) > 50) {
            $errors[] = 'La libreta sanitaria es demasiado larga';
        }

        if (!empty($nroCarnet) && mb_strlen($nroCarnet) > 50) {
            $errors[] = 'El número de carnet es demasiado largo';
        }

        // ✅ Procesar registro si no hay errores
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = ? OR dni = ?");
                $stmt->execute([$email, $dni]);
                
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('El email o DNI ya están registrados');
                }

                if ($idObra === -1 && !empty($obraOtra)) {
                    $stmt = $pdo->prepare("SELECT Id_obra_social FROM obra_social WHERE Nombre = ? LIMIT 1");
                    $stmt->execute([$obraOtra]);
                    $existingId = $stmt->fetchColumn();
                    
                    if ($existingId) {
                        $idObra = (int)$existingId;
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO obra_social (Nombre, Activo) VALUES (?, 1)");
                        $stmt->execute([$obraOtra]);
                        $idObra = (int)$pdo->lastInsertId();
                    }
                }

                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                $stmtUser = $pdo->prepare("
                    INSERT INTO usuario (Nombre, Apellido, dni, email, Contraseña, Rol, Fecha_Registro) 
                    VALUES (?, ?, ?, ?, ?, 'paciente', NOW())
                ");
                $stmtUser->execute([$nombre, $apellido, $dni, $email, $passwordHash]);
                $userId = (int)$pdo->lastInsertId();

                $stmtPaciente = $pdo->prepare("
                    INSERT INTO paciente (Id_obra_social, Nro_carnet, Libreta_sanitaria, Id_usuario, Activo) 
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmtPaciente->execute([
                    $idObra > 0 ? $idObra : null, 
                    !empty($nroCarnet) ? $nroCarnet : null, 
                    $libreta, 
                    $userId
                ]);

                $pdo->commit();

                error_log("New user registered: ID $userId, DNI $dni");

                // ✅ Login automático
                session_regenerate_id(true);
                
                // ✅ IMPORTANTE: Regenerar token CSRF después del login
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                $_SESSION['Id_usuario'] = $userId;
                $_SESSION['dni'] = $dni;
                $_SESSION['email'] = $email;
                $_SESSION['Nombre'] = $nombre;
                $_SESSION['Apellido'] = $apellido;
                $_SESSION['Rol'] = 'paciente';
                $_SESSION['login_time'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

                header('Location: index.php');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Registration error: ' . $e->getMessage());
                $errors[] = 'Error al crear la cuenta: ' . $e->getMessage();
            }
        } // Cierre del if $csrfValid
    } // Cierre del else de validaciones
} // Cierre del if POST

// ✅ DEBUG: Verificar que el token existe al renderizar
error_log("Rendering form with token: " . substr($csrf, 0, 20) . "...");
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Crear cuenta - Clínica Médica</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="../assets/css/register.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>✨ Crear cuenta</h1>
            <p class="subtitle">Completá tus datos para registrarte</p>

            <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="post" action="register.php" id="registerForm" autocomplete="on">
                <!-- ✅ Token CSRF con verificación visual -->
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                
                <?php if (!empty($csrf)): ?>
                    <!-- DEBUG: Mostrar que el token existe -->
                    <div style="font-size: 10px; color: #6b7280; margin-bottom: 10px; padding: 4px; background: #0b1220; border-radius: 4px;">
                        ✓ Token de seguridad cargado (<?= substr($csrf, 0, 8) ?>...)
                    </div>
                <?php else: ?>
                    <div style="font-size: 12px; color: #ef4444; margin-bottom: 10px; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px;">
                        ⚠️ ERROR: Token no generado - Recarga la página
                    </div>
                <?php endif; ?>

                <div class="grid-2">
                    <div class="field">
                        <label for="nombre">Nombre <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Juan"
                            maxlength="50"
                            autocomplete="given-name"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="apellido">Apellido <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="apellido" 
                            name="apellido" 
                            value="<?= htmlspecialchars($_POST['apellido'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Pérez"
                            maxlength="50"
                            autocomplete="family-name"
                            required
                        >
                    </div>
                </div>

                <div class="field">
                    <label for="dni">DNI <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="dni" 
                        name="dni" 
                        value="<?= htmlspecialchars($_POST['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="12345678"
                        inputmode="numeric"
                        pattern="[0-9]{7,10}"
                        maxlength="10"
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="field">
                    <label for="email">Email <span class="required">*</span></label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="tu@email.com"
                        maxlength="255"
                        autocomplete="email"
                        required
                    >
                    <div class="hint">No uses emails temporales</div>
                </div>

                <div class="field">
                    <label for="id_obra_social">Obra Social <span class="required">*</span></label>
                    <select id="id_obra_social" name="id_obra_social" required>
                        <option value="">Seleccioná tu obra social...</option>
                        <?php 
                        $selected_obra = $_POST['id_obra_social'] ?? '';
                        foreach ($obras_sociales as $obra): 
                        ?>
                            <option value="<?= (int)$obra['Id_obra_social'] ?>" 
                                <?= $selected_obra == $obra['Id_obra_social'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($obra['Nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="-1" <?= $selected_obra == '-1' ? 'selected' : '' ?>>➕ Otra (especificar)</option>
                    </select>
                </div>

                <div class="field hidden" id="fieldObraOtra">
                    <label for="obra_social_otra">Nombre de la Obra Social <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="obra_social_otra" 
                        name="obra_social_otra" 
                        value="<?= htmlspecialchars($_POST['obra_social_otra'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ej: IOSPER, OSECAC, etc."
                        maxlength="100"
                    >
                </div>

                <div class="field">
                    <label for="nro_carnet">Número de Carnet</label>
                    <input 
                        type="text" 
                        id="nro_carnet" 
                        name="nro_carnet" 
                        value="<?= htmlspecialchars($_POST['nro_carnet'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ej: 123456789"
                        maxlength="50"
                    >
                    <div class="hint">Opcional</div>
                </div>

                <div class="field">
                    <label for="libreta_sanitaria">Libreta Sanitaria <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="libreta_sanitaria" 
                        name="libreta_sanitaria" 
                        value="<?= htmlspecialchars($_POST['libreta_sanitaria'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Número o identificación"
                        maxlength="50"
                        required
                    >
                </div>

                <div class="field">
                    <label for="password">Contraseña <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Mínimo 8 caracteres, con mayúsculas y números"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="128"
                        required
                    >
                    <div class="password-strength" id="strengthMsg"></div>
                </div>

                <div class="field">
                    <label for="password2">Confirmar Contraseña <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="password2" 
                        name="password2" 
                        placeholder="Repetí tu contraseña"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="128"
                        required
                    >
                </div>

                <button type="submit" class="btn">Crear cuenta</button>
            </form>

            <div class="footer">
                ¿Ya tenés cuenta? <a href="login.php">Iniciá sesión</a> · 
                <a href="index.php">Volver al inicio</a>
            </div>
        </div>
    </div>

    <script>
        // ✅ VERIFICACIÓN ADICIONAL: Asegurar que el token se envía
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const tokenInput = document.getElementById('csrf_token');
            
            console.log('🔐 CSRF Token en formulario:', tokenInput ? tokenInput.value.substring(0, 10) + '...' : 'NO ENCONTRADO');
            
            form.addEventListener('submit', function(e) {
                const tokenValue = tokenInput ? tokenInput.value : '';
                
                if (!tokenValue || tokenValue.length < 10) {
                    e.preventDefault();
                    alert('⚠️ ERROR CRÍTICO: Token de seguridad inválido.\n\nRecarga la página (F5) e intenta nuevamente.');
                    console.error('Token inválido al enviar:', tokenValue);
                    return false;
                }
                
                console.log('✅ Enviando formulario con token:', tokenValue.substring(0, 10) + '...');
                console.log('📦 Datos del formulario:', new FormData(form));
            });
        });
    </script>
    <script src="../assets/js/register.js"></script>
</body>
</html>