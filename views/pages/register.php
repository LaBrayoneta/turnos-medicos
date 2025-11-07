<?php
/**
 * register.php - Registro de usuarios con validaciones mejoradas
 */
// Configuración de seguridad (ANTES de session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);

session_start();
require_once __DIR__ . '/../../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$pdo = db();
$errors = [];

// Lista de contraseñas comunes (ampliada)
$commonPasswords = [
    'password', '123456', '12345678', 'qwerty', 'abc123',
    'password123', '111111', '123123', 'admin', 'letmein',
    'welcome', 'monkey', '1234567', 'dragon', 'master'
];

// Dominios de email desechables
$disposableEmailDomains = [
    'tempmail.com', '10minutemail.com', 'guerrillamail.com',
    'mailinator.com', 'throwaway.email', 'temp-mail.org'
];

// Función para limpiar y validar entrada
function sanitizeInput($data) {
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}

// Función para validar DNI argentino
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

// Función para validar nombres
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
    
    // Solo letras, espacios, guiones y apóstrofes (acepta acentos)
    if (!preg_match('/^[\p{L}\s\'-]+$/u', $name)) {
        return "El $fieldName solo puede contener letras, espacios, guiones y apóstrofes";
    }
    
    // No números
    if (preg_match('/\d/', $name)) {
        return "El $fieldName no puede contener números";
    }
    
    // No espacios consecutivos
    if (preg_match('/\s{2,}/', $name)) {
        return "El $fieldName no puede tener espacios consecutivos";
    }
    
    return null;
}

// Función para validar email
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
    
    // Verificar dominio desechable
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return 'Formato de email inválido';
    }
    
    $domain = $parts[1];
    if (in_array($domain, $disposableEmailDomains)) {
        return 'No se permiten emails temporales o desechables';
    }
    
    // Verificar que el dominio tenga registros MX
    if (!checkdnsrr($domain, "MX")) {
        return 'El dominio del email no existe';
    }
    
    // No múltiples @
    if (substr_count($email, '@') > 1) {
        return 'Email inválido';
    }
    
    // No puntos consecutivos
    if (strpos($email, '..') !== false) {
        return 'El email no puede tener puntos consecutivos';
    }
    
    return null;
}

// Función para validar fortaleza de contraseña
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
    
    // Verificar contraseñas comunes
    foreach ($commonPasswords as $common) {
        if (stripos($password, $common) !== false) {
            return 'La contraseña es demasiado común. Elegí una más segura';
        }
    }
    
    // No permitir espacios
    if (strpos($password, ' ') !== false) {
        return 'La contraseña no puede contener espacios';
    }
    
    return null;
}

// Función para detectar patrones de inyección
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

// Cargar obras sociales activas
$obras_sociales = [];
try {
    $stmt = $pdo->query("SELECT Id_obra_social, Nombre FROM obra_social WHERE Activo=1 ORDER BY Nombre");
    $obras_sociales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Error loading obras sociales: ' . $e->getMessage());
    $obras_sociales = [];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($csrf, $token)) {
        $errors[] = 'Token de seguridad inválido. Recarga la página e intenta nuevamente.';
    } else {
        // Sanitizar entradas
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

        // Validar DNI
        $dniError = validateDNI($dni);
        if ($dniError) {
            $errors[] = $dniError;
        }

        // Validar nombre
        $nombreError = validateName($nombre, 'nombre');
        if ($nombreError) {
            $errors[] = $nombreError;
        }

        // Validar apellido
        $apellidoError = validateName($apellido, 'apellido');
        if ($apellidoError) {
            $errors[] = $apellidoError;
        }

        // Validar email
        $emailError = validateEmail($email, $disposableEmailDomains);
        if ($emailError) {
            $errors[] = $emailError;
        }

        // Validar contraseña
        $passwordError = validatePassword($password, $commonPasswords);
        if ($passwordError) {
            $errors[] = $passwordError;
        }

        // Verificar que las contraseñas coincidan
        if ($password !== $password2) {
            $errors[] = 'Las contraseñas no coinciden';
        }

        // Detectar patrones de inyección en todos los campos
        $fieldsToCheck = [$dni, $nombre, $apellido, $email, $obraOtra, $nroCarnet, $libreta];
        foreach ($fieldsToCheck as $field) {
            if (detectInjectionPatterns($field)) {
                $errors[] = 'Se detectó un patrón de entrada inválido';
                error_log("Possible injection attempt in registration from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                break;
            }
        }

        // Validar obra social
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

        // Validar libreta sanitaria
        if (empty($libreta)) {
            $errors[] = 'La libreta sanitaria es obligatoria';
        } elseif (mb_strlen($libreta) < 3) {
            $errors[] = 'La libreta sanitaria debe tener al menos 3 caracteres';
        } elseif (mb_strlen($libreta) > 50) {
            $errors[] = 'La libreta sanitaria es demasiado larga';
        }

        // Validar número de carnet (opcional)
        if (!empty($nroCarnet) && mb_strlen($nroCarnet) > 50) {
            $errors[] = 'El número de carnet es demasiado largo';
        }

        // Procesar registro si no hay errores
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Verificar si el DNI o email ya existen
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = ? OR dni = ?");
                $stmt->execute([$email, $dni]);
                
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('El email o DNI ya están registrados');
                }

                // Si eligió "Otra", crear la nueva obra social
                if ($idObra === -1 && !empty($obraOtra)) {
                    // Verificar si ya existe
                    $stmt = $pdo->prepare("SELECT Id_obra_social FROM obra_social WHERE Nombre = ? LIMIT 1");
                    $stmt->execute([$obraOtra]);
                    $existingId = $stmt->fetchColumn();
                    
                    if ($existingId) {
                        $idObra = (int)$existingId;
                    } else {
                        // Crear nueva obra social
                        $stmt = $pdo->prepare("INSERT INTO obra_social (Nombre, Activo) VALUES (?, 1)");
                        $stmt->execute([$obraOtra]);
                        $idObra = (int)$pdo->lastInsertId();
                    }
                }

                // Hash de contraseña con Bcrypt (costo 12 para mayor seguridad)
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                // Insertar usuario
                $stmtUser = $pdo->prepare("
                    INSERT INTO usuario (Nombre, Apellido, dni, email, Contraseña, Rol, fecha_registro) 
                    VALUES (?, ?, ?, ?, ?, 'paciente', NOW())
                ");
                $stmtUser->execute([$nombre, $apellido, $dni, $email, $passwordHash]);
                $userId = (int)$pdo->lastInsertId();

                // Insertar paciente
                $stmtPaciente = $pdo->prepare("
                    INSERT INTO paciente (Id_obra_social, Nro_carnet, Libreta_sanitaria, Id_usuario, Activo) 
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmtPaciente->execute([
                    $idObra > 0 ? $idObra : null, 
                    $nroCarnet !== '' ? $nroCarnet : null, 
                    $libreta, 
                    $userId
                ]);

                $pdo->commit();

                // Registrar registro exitoso
                error_log("New user registered: ID $userId, DNI $dni");

                // Login automático
                session_regenerate_id(true);
                
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
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Crear cuenta - Clínica Médica</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, Arial, sans-serif;
            background: linear-gradient(135deg, #0b1220 0%, #1a2332 100%);
            color: #e5e7eb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 560px;
            width: 100%;
        }

        .card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        h1 {
            color: #22d3ee;
            margin-bottom: 8px;
            font-size: 28px;
        }

        .subtitle {
            color: #94a3b8;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .errors {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .errors ul {
            list-style: none;
            padding: 0;
        }

        .errors li {
            color: #ef4444;
            font-size: 14px;
            padding: 4px 0;
        }

        .errors li:before {
            content: "⚠️ ";
            margin-right: 6px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field.hidden {
            display: none;
        }

        label {
            display: block;
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        label .required {
            color: #ef4444;
        }

        input, select {
            width: 100%;
            padding: 12px;
            background: #0b1220;
            border: 1px solid #1f2937;
            border-radius: 10px;
            color: #e5e7eb;
            font-size: 15px;
            transition: all 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #22d3ee;
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.1);
        }

        input::placeholder {
            color: #6b7280;
        }

        select {
            cursor: pointer;
        }

        select option {
            background: #111827;
            color: #e5e7eb;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #22d3ee;
            color: #001219;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }

        .btn:hover:not(:disabled) {
            background: #0891b2;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(34, 211, 238, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #1f2937;
        }

        .footer a {
            color: #22d3ee;
            text-decoration: none;
            font-weight: 500;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 600px) {
            .card {
                padding: 24px;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 24px;
            }
        }

        .password-strength {
            font-size: 12px;
            margin-top: 4px;
            color: #94a3b8;
        }

        .hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }
    </style>
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
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

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
                    <div class="hint">Ingresá el nombre completo de tu obra social</div>
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
                    <div class="hint">Opcional - Si tenés obra social, ingresá tu número de carnet</div>
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

    <script src="../assets/js/register.js"></script>
</body>
</html>