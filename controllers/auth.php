<?php
/**
 * auth.php - API de autenticación
 * VERSIÓN MODERNIZADA Y SINCRONIZADA
 */

// ✅ Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// ✅ Limpiar output buffer
if (ob_get_level()) ob_end_clean();
ob_start();

session_start();
require_once __DIR__ . '/../config/db.php';

// ✅ Headers seguros
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$pdo = db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ========== FUNCIONES AUXILIARES ==========

function json_out($data, $code = 200) {
    if (ob_get_level()) ob_end_clean();
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitize($data) {
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
        return 'DNI inválido';
    }
    
    $dniNum = intval($dni);
    if ($dniNum < 1000000 || $dniNum > 99999999) {
        return 'DNI fuera de rango válido';
    }
    
    return null;
}

function validateEmail($email) {
    $email = strtolower(trim($email));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Email inválido';
    }
    
    if (strlen($email) > 255) {
        return 'Email demasiado largo';
    }
    
    return null;
}

function validatePassword($password) {
    $len = strlen($password);
    
    if ($len < 8) {
        return 'La contraseña debe tener al menos 8 caracteres';
    }
    
    if ($len > 128) {
        return 'Contraseña demasiado larga';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Debe contener al menos una mayúscula';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return 'Debe contener al menos una minúscula';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return 'Debe contener al menos un número';
    }
    
    return null;
}

// ========== REGISTRO ==========

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitizar inputs
        $nombre = sanitize($_POST['nombre'] ?? '');
        $apellido = sanitize($_POST['apellido'] ?? '');
        $dni = sanitize($_POST['dni'] ?? '');
        $email = strtolower(sanitize($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $idObra = (int)($_POST['id_obra_social'] ?? 0);
        $obraOtra = sanitize($_POST['obra_social_otra'] ?? '');
        $nroCarnet = sanitize($_POST['nro_carnet'] ?? '');
        $libreta = sanitize($_POST['libreta_sanitaria'] ?? '');

        // Validaciones
        if (empty($nombre) || empty($apellido)) {
            json_out(['ok' => false, 'error' => 'Nombre y apellido son obligatorios'], 400);
        }

        $dniError = validateDNI($dni);
        if ($dniError) {
            json_out(['ok' => false, 'error' => $dniError], 400);
        }

        $emailError = validateEmail($email);
        if ($emailError) {
            json_out(['ok' => false, 'error' => $emailError], 400);
        }

        $passwordError = validatePassword($password);
        if ($passwordError) {
            json_out(['ok' => false, 'error' => $passwordError], 400);
        }

        if (empty($libreta)) {
            json_out(['ok' => false, 'error' => 'Libreta sanitaria es obligatoria'], 400);
        }

        // Verificar unicidad
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = ? OR dni = ?");
        $stmt->execute([$email, $dni]);
        if ($stmt->fetchColumn() > 0) {
            json_out(['ok' => false, 'error' => 'Email o DNI ya registrados'], 409);
        }

        // Iniciar transacción
        $pdo->beginTransaction();

        // Si eligió "Otra" obra social, crearla
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

        // Hash de contraseña
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Insertar usuario
        $stmt = $pdo->prepare("
            INSERT INTO usuario (nombre, apellido, dni, email, password, rol, fecha_registro) 
            VALUES (?, ?, ?, ?, ?, 'paciente', NOW())
        ");
        $stmt->execute([$nombre, $apellido, $dni, $email, $passwordHash]);
        $userId = (int)$pdo->lastInsertId();

        // Insertar paciente
        $stmt = $pdo->prepare("
            INSERT INTO paciente (Id_obra_social, Nro_carnet, Libreta_sanitaria, Id_usuario, Activo) 
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $idObra > 0 ? $idObra : null,
            !empty($nroCarnet) ? $nroCarnet : null,
            $libreta,
            $userId
        ]);

        $pdo->commit();

        // Log
        error_log("New user registered via API: ID $userId, DNI $dni");

        // Crear sesión automática
        session_regenerate_id(true);
        $_SESSION['Id_usuario'] = $userId;
        $_SESSION['dni'] = $dni;
        $_SESSION['email'] = $email;
        $_SESSION['Nombre'] = $nombre;
        $_SESSION['Apellido'] = $apellido;
        $_SESSION['Rol'] = 'paciente';
        $_SESSION['login_time'] = time();

        json_out([
            'ok' => true, 
            'usuario_id' => $userId,
            'mensaje' => 'Cuenta creada exitosamente'
        ], 201);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Registration API error: ' . $e->getMessage());
        json_out(['ok' => false, 'error' => 'Error al crear cuenta'], 500);
    }
}

// ========== LOGIN ==========

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dni = sanitize($_POST['dni'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($dni) || empty($password)) {
            json_out(['ok' => false, 'error' => 'DNI y contraseña son obligatorios'], 400);
        }

        $dniError = validateDNI($dni);
        if ($dniError) {
            json_out(['ok' => false, 'error' => $dniError], 400);
        }

        // Buscar usuario
        $stmt = $pdo->prepare("
            SELECT 
                u.Id_usuario, 
                u.nombre, 
                u.apellido, 
                u.dni, 
                u.email, 
                u.rol, 
                u.password,
                u.account_locked_until,
                CASE WHEN p.Id_paciente IS NOT NULL AND p.Activo = 1 THEN 1 ELSE 0 END AS is_paciente_activo,
                CASE WHEN m.Id_medico IS NOT NULL AND m.Activo = 1 THEN 1 ELSE 0 END AS is_medico_activo,
                CASE WHEN s.Id_secretaria IS NOT NULL AND s.Activo = 1 THEN 1 ELSE 0 END AS is_secretaria_activa
            FROM usuario u
            LEFT JOIN paciente p ON p.Id_usuario = u.Id_usuario
            LEFT JOIN medico m ON m.Id_usuario = u.Id_usuario
            LEFT JOIN secretaria s ON s.Id_usuario = u.Id_usuario
            WHERE u.dni = ?
            LIMIT 1
        ");
        $stmt->execute([$dni]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sleep(2);
            json_out(['ok' => false, 'error' => 'Credenciales inválidas'], 401);
        }

        // Verificar bloqueo
        if (!empty($user['account_locked_until']) && strtotime($user['account_locked_until']) > time()) {
            json_out(['ok' => false, 'error' => 'Cuenta bloqueada temporalmente'], 403);
        }

        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            sleep(2);
            json_out(['ok' => false, 'error' => 'Credenciales inválidas'], 401);
        }

        // Verificar rol activo
        $hasActiveRole = false;
        if ($user['is_paciente_activo']) {
            $hasActiveRole = true;
        } elseif ($user['rol'] === 'medico' && $user['is_medico_activo']) {
            $hasActiveRole = true;
        } elseif ($user['rol'] === 'secretaria' && $user['is_secretaria_activa']) {
            $hasActiveRole = true;
        }

        if (!$hasActiveRole) {
            json_out(['ok' => false, 'error' => 'Usuario inactivo'], 403);
        }

        // Crear sesión
        session_regenerate_id(true);
        $_SESSION['Id_usuario'] = (int)$user['Id_usuario'];
        $_SESSION['dni'] = $user['dni'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['Nombre'] = $user['nombre'];
        $_SESSION['Apellido'] = $user['apellido'];
        $_SESSION['Rol'] = $user['rol'];
        $_SESSION['login_time'] = time();

        // Actualizar último acceso
        $stmt = $pdo->prepare("UPDATE usuario SET ultimo_acceso = NOW() WHERE Id_usuario = ?");
        $stmt->execute([$user['Id_usuario']]);

        // Log
        error_log("API login: User {$user['Id_usuario']} ({$user['rol']})");

        json_out([
            'ok' => true,
            'usuario' => [
                'id' => (int)$user['Id_usuario'],
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'email' => $user['email'],
                'rol' => $user['rol']
            ]
        ]);

    } catch (Throwable $e) {
        error_log('Login API error: ' . $e->getMessage());
        json_out(['ok' => false, 'error' => 'Error en login'], 500);
    }
}

// ========== LOGOUT ==========

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    json_out(['ok' => true, 'mensaje' => 'Sesión cerrada']);
}

// ========== VERIFICAR SESIÓN ==========

if ($action === 'check_session') {
    if (!empty($_SESSION['Id_usuario'])) {
        json_out([
            'ok' => true,
            'logged_in' => true,
            'usuario' => [
                'id' => $_SESSION['Id_usuario'],
                'nombre' => $_SESSION['Nombre'] ?? '',
                'apellido' => $_SESSION['Apellido'] ?? '',
                'rol' => $_SESSION['Rol'] ?? ''
            ]
        ]);
    } else {
        json_out(['ok' => true, 'logged_in' => false]);
    }
}

// Acción no soportada
json_out(['ok' => false, 'error' => 'Acción no soportada'], 400);