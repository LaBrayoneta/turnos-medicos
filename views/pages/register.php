<?php
/**
 * register.php - Versión mejorada con soporte de tema y validaciones avanzadas
 */

session_start();
require_once __DIR__ . '/../../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$pdo = db();
$errors = [];
$success = false;

// Cargar obras sociales
$obras_sociales = [];
try {
    $stmt = $pdo->query("SELECT Id_obra_social, nombre FROM obra_social WHERE activo=1 ORDER BY nombre");
    $obras_sociales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Error loading obras sociales: " . $e->getMessage());
    $obras_sociales = [];
}

// Funciones de validación
function sanitizeName($name) {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
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
        return "El $fieldName no puede exceder 50 caracteres";
    }
    
    if (!preg_match('/^[a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s\'-]+$/u', $name)) {
        return "El $fieldName solo puede contener letras, espacios, guiones y apóstrofes";
    }
    
    if (preg_match('/\d/', $name)) {
        return "El $fieldName no puede contener números";
    }
    
    if (preg_match('/\s{2,}/', $name)) {
        return "El $fieldName no puede tener espacios múltiples";
    }
    
    return null;
}

function validateDNI($dni) {
    $dni = trim($dni);
    
    if (empty($dni)) {
        return 'El DNI es obligatorio';
    }
    
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

function validateEmail($email) {
    $email = trim(strtolower($email));
    
    if (empty($email)) {
        return 'El email es obligatorio';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'El formato del email es inválido';
    }
    
    if (strlen($email) > 255) {
        return 'El email es demasiado largo';
    }
    
    $domain = substr(strrchr($email, "@"), 1);
    if ($domain && !checkdnsrr($domain, "MX") && !checkdnsrr($domain, "A")) {
        return 'El dominio del email no existe o no es válido';
    }
    
    return null;
}

function validatePassword($password) {
    if (empty($password)) {
        return 'La contraseña es obligatoria';
    }
    
    if (strlen($password) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres';
    }
    
    if (strlen($password) > 128) {
        return 'La contraseña es demasiado larga';
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
    
    $commonPasswords = ['password', '12345678', 'qwerty123', 'abc12345', 'password123'];
    if (in_array(strtolower($password), $commonPasswords)) {
        return 'Esta contraseña es demasiado común, elige una más segura';
    }
    
    return null;
}

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($csrf, $token)) {
        $errors[] = 'Token de seguridad inválido. Recarga la página e intenta nuevamente.';
    } else {
        $nombre = sanitizeName($_POST['nombre'] ?? '');
        $apellido = sanitizeName($_POST['apellido'] ?? '');
        
        $dni = trim($_POST['dni'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        
        $idObra = (int)($_POST['id_obra_social'] ?? 0);
        $obraOtra = trim($_POST['obra_social_otra'] ?? '');
        $nroCarnet = trim($_POST['nro_carnet'] ?? '');
        $libreta = trim($_POST['libreta_sanitaria'] ?? '');
        
        $nombreError = validateName($nombre, 'nombre');
        if ($nombreError) $errors[] = $nombreError;
        
        $apellidoError = validateName($apellido, 'apellido');
        if ($apellidoError) $errors[] = $apellidoError;
        
        $dniError = validateDNI($dni);
        if ($dniError) $errors[] = $dniError;
        
        $emailError = validateEmail($email);
        if ($emailError) $errors[] = $emailError;
        
        $passwordError = validatePassword($password);
        if ($passwordError) $errors[] = $passwordError;
        
        if ($password !== $password2) {
            $errors[] = 'Las contraseñas no coinciden';
        }
        
        if ($idObra === -1) {
            if (empty($obraOtra)) {
                $errors[] = 'Debes especificar el nombre de la obra social';
            } elseif (strlen($obraOtra) < 3) {
                $errors[] = 'El nombre de la obra social debe tener al menos 3 caracteres';
            }
        } elseif ($idObra <= 0) {
            $errors[] = 'Debes seleccionar una obra social';
        }
        
        $stmt = $pdo->prepare("SELECT nombre FROM obra_social WHERE Id_obra_social = ?");
        $stmt->execute([$idObra]);
        $obraNombre = $stmt->fetchColumn();
        
        if ($obraNombre && stripos($obraNombre, 'sin obra social') !== false) {
            $nroCarnet = null;
        }
        
        if (empty($libreta)) {
            $errors[] = 'La libreta sanitaria es obligatoria';
        } elseif (strlen($libreta) < 3) {
            $errors[] = 'La libreta sanitaria debe tener al menos 3 caracteres';
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = ? OR dni = ?");
                $stmt->execute([$email, $dni]);
                
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('El email o DNI ya están registrados. Si ya tienes una cuenta, puedes iniciar sesión.');
                }
                
                if ($idObra === -1 && !empty($obraOtra)) {
                    $stmt = $pdo->prepare("SELECT Id_obra_social FROM obra_social WHERE nombre = ? LIMIT 1");
                    $stmt->execute([$obraOtra]);
                    $existingId = $stmt->fetchColumn();
                    
                    if ($existingId) {
                        $idObra = (int)$existingId;
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO obra_social (nombre, activo) VALUES (?, 1)");
                        $stmt->execute([$obraOtra]);
                        $idObra = (int)$pdo->lastInsertId();
                    }
                }
                
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                $stmtUser = $pdo->prepare("
                    INSERT INTO usuario (nombre, apellido, dni, email, password, rol, fecha_registro) 
                    VALUES (?, ?, ?, ?, ?, 'paciente', NOW())
                ");
                $stmtUser->execute([$nombre, $apellido, $dni, $email, $passwordHash]);
                $userId = (int)$pdo->lastInsertId();
                
                $stmtPaciente = $pdo->prepare("
                    INSERT INTO paciente (Id_obra_social, nro_carnet, libreta_sanitaria, Id_usuario, activo) 
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmtPaciente->execute([
                    $idObra > 0 ? $idObra : null,
                    !empty($nroCarnet) ? $nroCarnet : null,
                    $libreta,
                    $userId
                ]);
                
                $pdo->commit();
                
                session_regenerate_id(true);
                $_SESSION['Id_usuario'] = $userId;
                $_SESSION['dni'] = $dni;
                $_SESSION['email'] = $email;
                $_SESSION['Nombre'] = $nombre;
                $_SESSION['Apellido'] = $apellido;
                $_SESSION['Rol'] = 'paciente';
                $_SESSION['login_time'] = time();
                
                error_log("✅ New user registered: ID $userId, DNI $dni, Email $email");
                
                header('Location: index.php');
                exit;
                
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                error_log("Registration error: " . $e->getMessage());
                $errors[] = $e->getMessage();
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
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/theme_light.css">
</head>
<body>
    <!-- Botón de cambio de tema -->
    <button class="theme-toggle" type="button" title="Cambiar tema">🌙</button>

    <div class="container">
        <div class="card">
            <h1>✨ Crear cuenta</h1>
            <p class="subtitle">Completa tus datos para registrarte</p>

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
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="grid-2">
                    <div class="field">
                        <label for="nombre">Nombre <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Juan"
                            autocomplete="given-name"
                            maxlength="50"
                            required
                            autofocus
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
                            autocomplete="family-name"
                            maxlength="50"
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
                        autocomplete="email"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="field">
                    <label for="id_obra_social">Obra Social <span class="required">*</span></label>
                    <select id="id_obra_social" name="id_obra_social" required>
                        <option value="">Selecciona tu obra social...</option>
                        <?php 
                        $selected_obra = $_POST['id_obra_social'] ?? '';
                        foreach ($obras_sociales as $obra): 
                        ?>
                            <option value="<?= (int)$obra['Id_obra_social'] ?>" 
                                <?= $selected_obra == $obra['Id_obra_social'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($obra['nombre'], ENT_QUOTES, 'UTF-8') ?>
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
                    <div class="hint">Ingresa el nombre completo de tu obra social</div>
                </div>

                <div class="field" id="fieldNroCarnet">
                    <label for="nro_carnet">Número de Carnet</label>
                    <input 
                        type="text" 
                        id="nro_carnet" 
                        name="nro_carnet" 
                        value="<?= htmlspecialchars($_POST['nro_carnet'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ej: 123456789"
                        maxlength="50"
                    >
                    <div class="hint">Opcional - Ingresa tu número de carnet de la obra social</div>
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
                        placeholder="Mínimo 8 caracteres"
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
                        placeholder="Repite tu contraseña"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="128"
                        required
                    >
                </div>

                <button type="submit" class="btn">Crear cuenta</button>
            </form>

            <div class="footer">
                ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a> · 
                <a href="index.php">Volver al inicio</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/theme_toggle.js"></script>
    <script src="../assets/js/register.js"></script>
</body>
</html>