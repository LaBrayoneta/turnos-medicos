<?php
/**
 * login.php - VERSIÓN CORREGIDA Y OPTIMIZADA
 * Sincronizado con register.php y BD
 */
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);

session_start();
require_once __DIR__ . '/../../config/db.php';

$pdo = db();
$error = '';

// Redirigir si ya está logueado
if (!empty($_SESSION['Id_usuario'])) {
    $rol = $_SESSION['Rol'] ?? '';
    if ($rol === 'medico' || $rol === 'secretaria') {
        header('Location: admin.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

// Configuración de seguridad
$max_attempts = 5;
$lockout_time = 900; // 15 minutos

// ========== FUNCIONES DE SEGURIDAD ==========

function recordFailedAttempt($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE usuario 
            SET failed_login_attempts = failed_login_attempts + 1,
                last_failed_login = NOW()
            WHERE Id_usuario = ?
        ");
        $stmt->execute([$userId]);
        
        // Verificar si debe bloquearse
        $stmt = $pdo->prepare("SELECT failed_login_attempts FROM usuario WHERE Id_usuario = ?");
        $stmt->execute([$userId]);
        $attempts = $stmt->fetchColumn();
        
        if ($attempts >= 5) {
            $stmt = $pdo->prepare("
                UPDATE usuario 
                SET account_locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                WHERE Id_usuario = ?
            ");
            $stmt->execute([$userId]);
        }
    } catch (Throwable $e) {
        error_log("Error recording failed attempt: " . $e->getMessage());
    }
}

function resetFailedAttempts($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE usuario 
            SET failed_login_attempts = 0,
                last_failed_login = NULL,
                account_locked_until = NULL
            WHERE Id_usuario = ?
        ");
        $stmt->execute([$userId]);
    } catch (Throwable $e) {
        error_log("Error resetting attempts: " . $e->getMessage());
    }
}

function isAccountLocked($user) {
    if (empty($user['account_locked_until'])) {
        return false;
    }
    
    $lockTime = strtotime($user['account_locked_until']);
    return $lockTime > time();
}

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
    
    return null;
}

// ========== PROCESAR LOGIN ==========

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $dni = sanitizeInput($_POST['dni'] ?? '');
    $password = $_POST['password'] ?? ''; // NO sanitizar la contraseña
    
    // Validaciones básicas
    if (empty($dni)) {
        $error = 'El DNI es obligatorio';
    } elseif (empty($password)) {
        $error = 'La contraseña es obligatoria';
    } else {
        // Validar DNI
        $dniError = validateDNI($dni);
        if ($dniError) {
            $error = $dniError;
            sleep(2);
        } else {
            // Validaciones de contraseña
            if (strlen($password) < 6 || strlen($password) > 128) {
                $error = 'Contraseña inválida';
                sleep(2);
            } else {
                try {
                    // ✅ QUERY MEJORADO: Incluye verificación de bloqueo
                    $stmt = $pdo->prepare("
                        SELECT 
                            u.Id_usuario, 
                            u.nombre, 
                            u.apellido, 
                            u.dni, 
                            u.email, 
                            u.rol, 
                            u.password,
                            u.failed_login_attempts,
                            u.account_locked_until,
                            CASE 
                                WHEN m.Id_medico IS NOT NULL AND m.Activo = 1 THEN 1
                                ELSE 0
                            END AS is_medico_activo,
                            CASE 
                                WHEN s.Id_secretaria IS NOT NULL AND s.Activo = 1 THEN 1
                                ELSE 0
                            END AS is_secretaria_activa,
                            CASE 
                                WHEN p.Id_paciente IS NOT NULL AND p.Activo = 1 THEN 1
                                ELSE 0
                            END AS is_paciente_activo
                        FROM usuario u
                        LEFT JOIN medico m ON m.Id_usuario = u.Id_usuario
                        LEFT JOIN secretaria s ON s.Id_usuario = u.Id_usuario
                        LEFT JOIN paciente p ON p.Id_usuario = u.Id_usuario
                        WHERE u.dni = ? 
                        LIMIT 1
                    ");
                    $stmt->execute([$dni]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user) {
                        $userId = (int)$user['Id_usuario'];
                        
                        // ✅ Verificar bloqueo de cuenta
                        if (isAccountLocked($user)) {
                            $remainingMinutes = ceil((strtotime($user['account_locked_until']) - time()) / 60);
                            $error = "Cuenta bloqueada temporalmente. Intenta nuevamente en {$remainingMinutes} minuto(s).";
                            error_log("Locked account login attempt: User $userId from IP $ip");
                            sleep(2);
                        }
                        // ✅ Verificar que el hash sea válido
                        elseif (strlen($user['password']) < 60) {
                            error_log("Invalid hash format for user $userId");
                            $error = 'Error en la cuenta. Contacta al administrador.';
                        }
                        // ✅ Verificar contraseña
                        elseif (password_verify($password, $user['password'])) {
                            // ✅ Verificar que el usuario tenga rol activo
                            $hasActiveRole = false;
                            $actualRole = '';
                            
                            if ($user['rol'] === 'medico' && $user['is_medico_activo']) {
                                $hasActiveRole = true;
                                $actualRole = 'medico';
                            } elseif ($user['rol'] === 'secretaria' && $user['is_secretaria_activa']) {
                                $hasActiveRole = true;
                                $actualRole = 'secretaria';
                            } elseif ($user['rol'] === 'paciente' && $user['is_paciente_activo']) {
                                $hasActiveRole = true;
                                $actualRole = 'paciente';
                            } elseif ($user['rol'] === '') {
                                // Usuario sin rol específico pero con registro de paciente activo
                                if ($user['is_paciente_activo']) {
                                    $hasActiveRole = true;
                                    $actualRole = 'paciente';
                                }
                            }
                            
                            if (!$hasActiveRole) {
                                $error = 'Usuario inactivo o sin rol asignado. Contacta al administrador.';
                                error_log("Inactive user login attempt: User $userId, Rol: {$user['rol']}");
                                sleep(2);
                            } else {
                                // ✅ LOGIN EXITOSO
                                
                                // Resetear intentos fallidos
                                resetFailedAttempts($pdo, $userId);
                                
                                // Regenerar ID de sesión
                                session_regenerate_id(true);
                                
                                // Guardar datos en sesión
                                $_SESSION['Id_usuario'] = $userId;
                                $_SESSION['dni'] = $user['dni'];
                                $_SESSION['email'] = $user['email'];
                                $_SESSION['Nombre'] = $user['nombre'];
                                $_SESSION['Apellido'] = $user['apellido'];
                                $_SESSION['Rol'] = $actualRole;
                                $_SESSION['login_time'] = time();
                                $_SESSION['ip_address'] = $ip;

                                // Actualizar último acceso
                                $updateStmt = $pdo->prepare("
                                    UPDATE usuario 
                                    SET ultimo_acceso = NOW() 
                                    WHERE Id_usuario = ?
                                ");
                                $updateStmt->execute([$userId]);

                                // Log exitoso
                                error_log("Successful login: User $userId ($actualRole) from IP $ip");

                                // Redirigir según el rol
                                if ($actualRole === 'medico' || $actualRole === 'secretaria') {
                                    header('Location: admin.php');
                                } else {
                                    header('Location: index.php');
                                }
                                exit;
                            }
                        } else {
                            // Contraseña incorrecta
                            recordFailedAttempt($pdo, $userId);
                            
                            $remainingAttempts = 5 - ($user['failed_login_attempts'] + 1);
                            if ($remainingAttempts > 0) {
                                $error = "DNI o contraseña incorrectos. Te quedan {$remainingAttempts} intento(s).";
                            } else {
                                $error = "DNI o contraseña incorrectos. Tu cuenta ha sido bloqueada por 15 minutos.";
                            }
                            
                            error_log("Password mismatch for user $userId from IP $ip");
                            sleep(rand(2, 4));
                        }
                    } else {
                        // Usuario no encontrado
                        $error = 'DNI o contraseña incorrectos';
                        error_log("Login attempt for non-existent DNI: $dni from IP $ip");
                        sleep(rand(2, 4));
                    }
                } catch (Throwable $e) {
                    error_log('Login error: ' . $e->getMessage());
                    $error = 'Error al procesar el inicio de sesión. Intenta nuevamente.';
                    sleep(2);
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Iniciar sesión - Turnos Médicos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
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
            max-width: 420px;
            width: 100%;
        }

        .card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .logo {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo-icon {
            font-size: 48px;
            margin-bottom: 8px;
        }

        h1 {
            color: #22d3ee;
            margin-bottom: 8px;
            font-size: 28px;
            text-align: center;
        }

        .subtitle {
            color: #94a3b8;
            margin-bottom: 24px;
            font-size: 14px;
            text-align: center;
        }

        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 20px;
            color: #ef4444;
            font-size: 14px;
            text-align: center;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .error:before {
            content: "⚠️ ";
        }

        .field {
            margin-bottom: 16px;
        }

        label {
            display: block;
            color: #94a3b8;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 12px;
            background: #0b1220;
            border: 1px solid #1f2937;
            border-radius: 10px;
            color: #e5e7eb;
            font-size: 15px;
            transition: all 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #22d3ee;
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.1);
        }

        input::placeholder {
            color: #6b7280;
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

        @media (max-width: 600px) {
            .card {
                padding: 24px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <div class="logo-icon">🏥</div>
            </div>
            
            <h1>Bienvenido</h1>
            <p class="subtitle">Ingresá tus datos para continuar</p>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" id="loginForm" autocomplete="on">
                <div class="field">
                    <label for="dni">DNI</label>
                    <input 
                        type="text" 
                        id="dni" 
                        name="dni" 
                        value="<?= htmlspecialchars($_POST['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ingresá tu DNI"
                        inputmode="numeric"
                        pattern="[0-9]{7,10}"
                        maxlength="10"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>

                <div class="field">
                    <label for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Ingresá tu contraseña"
                        autocomplete="current-password"
                        maxlength="128"
                        required
                    >
                </div>

                <button type="submit" class="btn">Iniciar sesión</button>
            </form>

            <div class="footer">
                ¿No tenés cuenta? <a href="register.php">Crear cuenta</a> · 
                <a href="index.php">Volver al inicio</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>