<?php
/**
 * login.php - Inicio de sesión con validaciones mejoradas
 */
// Configuración de seguridad (ANTES de session_start)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);

session_start();
require_once __DIR__ . '/../../config/db.php';

$pdo = db();
$error = '';

// Si ya está logueado, redirigir
if (!empty($_SESSION['Id_usuario'])) {
    header('Location: index.php');
    exit;
}

// Límite de intentos de login
$max_attempts = 5;
$lockout_time = 900; // 15 minutos en segundos

// Función para registrar intento fallido
function recordFailedAttempt($ip) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    $_SESSION['login_attempts'][$ip] = [
        'count' => ($_SESSION['login_attempts'][$ip]['count'] ?? 0) + 1,
        'time' => time()
    ];
}

// Función para verificar si está bloqueado
function isLockedOut($ip, $max_attempts, $lockout_time) {
    if (!isset($_SESSION['login_attempts'][$ip])) {
        return false;
    }
    
    $attempts = $_SESSION['login_attempts'][$ip];
    
    // Resetear si pasó el tiempo de bloqueo
    if (time() - $attempts['time'] > $lockout_time) {
        unset($_SESSION['login_attempts'][$ip]);
        return false;
    }
    
    return $attempts['count'] >= $max_attempts;
}

// Función para limpiar y validar entrada
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Función para validar DNI argentino
function validateArgentineDNI($dni) {
    // Solo números
    if (!ctype_digit($dni)) {
        return 'El DNI debe contener solo números';
    }
    
    // Longitud correcta
    $len = strlen($dni);
    if ($len < 7 || $len > 10) {
        return 'El DNI debe tener entre 7 y 10 dígitos';
    }
    
    // No todos los dígitos iguales
    if (preg_match('/^(\d)\1+$/', $dni)) {
        return 'DNI inválido';
    }
    
    // Rango válido
    $dniNum = intval($dni);
    if ($dniNum < 1000000 || $dniNum > 99999999) {
        return 'DNI fuera de rango válido';
    }
    
    return null;
}

// Función para detectar patrones de inyección
function detectInjectionPatterns($input) {
    $patterns = [
        '/(\bOR\b|\bAND\b|\bUNION\b|\bSELECT\b|\bDROP\b|\bINSERT\b|\bDELETE\b|\bUPDATE\b)/i',
        '/--|\/\*|\*\//',
        '/<script|javascript:|onerror=|onload=/i',
        '/[\'";]/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Verificar bloqueo por intentos
    if (isLockedOut($ip, $max_attempts, $lockout_time)) {
        $error = 'Has excedido el límite de intentos. Intenta nuevamente en 15 minutos.';
        http_response_code(429); // Too Many Requests
        sleep(2); // Delay adicional
    } else {
        $dni = sanitizeInput($_POST['dni'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validaciones básicas
        if (empty($dni)) {
            $error = 'El DNI es obligatorio';
        } elseif (empty($password)) {
            $error = 'La contraseña es obligatoria';
        } else {
            // Validar DNI
            $dniError = validateArgentineDNI($dni);
            if ($dniError) {
                $error = $dniError;
                recordFailedAttempt($ip);
            } else {
                // Detectar patrones de inyección
                if (detectInjectionPatterns($dni) || detectInjectionPatterns($password)) {
                    $error = 'Entrada inválida detectada';
                    recordFailedAttempt($ip);
                    error_log("Possible injection attempt from IP: $ip");
                    sleep(3); // Delay para ralentizar ataques
                } else {
                    // Validar longitud de contraseña
                    if (strlen($password) < 6 || strlen($password) > 128) {
                        $error = 'Contraseña inválida';
                        recordFailedAttempt($ip);
                    } else {
                        try {
                            // Buscar usuario por DNI
                            $stmt = $pdo->prepare("
                                SELECT Id_usuario, Nombre, Apellido, dni, email, Rol, Contraseña 
                                FROM usuario 
                                WHERE dni = ? 
                                LIMIT 1
                            ");
                            $stmt->execute([$dni]);
                            $user = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($user && password_verify($password, $user['Contraseña'])) {
                                // Login exitoso
                                
                                // Limpiar intentos fallidos
                                unset($_SESSION['login_attempts'][$ip]);
                                
                                // Regenerar ID de sesión
                                session_regenerate_id(true);
                                
                                // Guardar datos en sesión
                                $_SESSION['Id_usuario'] = (int)$user['Id_usuario'];
                                $_SESSION['dni'] = $user['dni'];
                                $_SESSION['email'] = $user['email'];
                                $_SESSION['Nombre'] = $user['Nombre'];
                                $_SESSION['Apellido'] = $user['Apellido'];
                                $_SESSION['Rol'] = $user['Rol'];
                                $_SESSION['login_time'] = time();
                                $_SESSION['ip_address'] = $ip;
                                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

                                // Actualizar último acceso
                                $updateStmt = $pdo->prepare("
                                    UPDATE usuario 
                                    SET ultimo_acceso = NOW() 
                                    WHERE Id_usuario = ?
                                ");
                                $updateStmt->execute([$user['Id_usuario']]);

                                // Registrar login exitoso
                                error_log("Successful login: User {$user['Id_usuario']} from IP $ip");

                                // Redirigir según el rol
                                if ($user['Rol'] === 'medico' || $user['Rol'] === 'secretaria') {
                                    header('Location: admin.php');
                                } else {
                                    header('Location: index.php');
                                }
                                exit;
                            } else {
                                $error = 'DNI o contraseña incorrectos';
                                recordFailedAttempt($ip);
                                
                                // Registrar intento fallido
                                error_log("Failed login attempt for DNI: $dni from IP: $ip");
                                
                                // Delay para prevenir ataques de fuerza bruta
                                sleep(rand(1, 3));
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