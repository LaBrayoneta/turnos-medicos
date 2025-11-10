<?php
/**
 * login.php - VERSIÓN CORREGIDA
 * Sincronizado con register.php
 */
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

// Límite de intentos
$max_attempts = 5;
$lockout_time = 900; // 15 minutos

function recordFailedAttempt($ip) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    $_SESSION['login_attempts'][$ip] = [
        'count' => ($_SESSION['login_attempts'][$ip]['count'] ?? 0) + 1,
        'time' => time()
    ];
}

function isLockedOut($ip, $max_attempts, $lockout_time) {
    if (!isset($_SESSION['login_attempts'][$ip])) {
        return false;
    }
    
    $attempts = $_SESSION['login_attempts'][$ip];
    
    if (time() - $attempts['time'] > $lockout_time) {
        unset($_SESSION['login_attempts'][$ip]);
        return false;
    }
    
    return $attempts['count'] >= $max_attempts;
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ✅ CORRECCIÓN 1: Validación de DNI más permisiva (solo para login)
function validateDNI($dni) {
    // Solo verificar que sea numérico y tenga longitud correcta
    if (!ctype_digit($dni)) {
        return 'El DNI debe contener solo números';
    }
    
    $len = strlen($dni);
    if ($len < 7 || $len > 10) {
        return 'El DNI debe tener entre 7 y 10 dígitos';
    }
    
    return null; // ✅ Validación más simple para login
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Verificar bloqueo
    if (isLockedOut($ip, $max_attempts, $lockout_time)) {
        $error = 'Has excedido el límite de intentos. Intenta nuevamente en 15 minutos.';
        http_response_code(429);
        sleep(2);
    } else {
        $dni = sanitizeInput($_POST['dni'] ?? '');
        $password = $_POST['password'] ?? ''; // ✅ NO sanitizar la contraseña
        
        // Log para debugging
        error_log("Login attempt - DNI: $dni");

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
                recordFailedAttempt($ip);
            } else {
                // ✅ CORRECCIÓN 2: Validaciones más simples para login
                if (strlen($password) < 6 || strlen($password) > 128) {
                    $error = 'Contraseña inválida';
                    recordFailedAttempt($ip);
                } else {
                    try {
                        // ✅ CORRECCIÓN 3: Query más específica
                        $stmt = $pdo->prepare("
                            SELECT 
                                Id_usuario, 
                                Nombre, 
                                Apellido, 
                                dni, 
                                email, 
                                Rol, 
                                Contraseña 
                            FROM usuario 
                            WHERE dni = ? 
                            LIMIT 1
                        ");
                        $stmt->execute([$dni]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        // Log para debugging
                        if ($user) {
                            error_log("User found - ID: {$user['Id_usuario']}, Hash length: " . strlen($user['Contraseña']));
                        } else {
                            error_log("User not found for DNI: $dni");
                        }

                        // ✅ CORRECCIÓN 4: Verificación mejorada del hash
                        if ($user) {
                            $storedHash = $user['Contraseña'];
                            
                            // Verificar que el hash tenga el formato correcto
                            if (strlen($storedHash) < 60) {
                                error_log("Invalid hash format for user {$user['Id_usuario']}");
                                $error = 'Error en la cuenta. Contacta al administrador.';
                            } else {
                                // Verificar contraseña
                                if (password_verify($password, $storedHash)) {
                                    // ✅ Login exitoso
                                    
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

                                    // Actualizar último acceso
                                    $updateStmt = $pdo->prepare("
                                        UPDATE usuario 
                                        SET ultimo_acceso = NOW() 
                                        WHERE Id_usuario = ?
                                    ");
                                    $updateStmt->execute([$user['Id_usuario']]);

                                    // Log exitoso
                                    error_log("Successful login: User {$user['Id_usuario']} from IP $ip");

                                    // Redirigir según el rol
                                    if ($user['Rol'] === 'medico' || $user['Rol'] === 'secretaria') {
                                        header('Location: admin.php');
                                    } else {
                                        header('Location: index.php');
                                    }
                                    exit;
                                } else {
                                    // Contraseña incorrecta
                                    error_log("Password mismatch for DNI: $dni");
                                    $error = 'DNI o contraseña incorrectos';
                                    recordFailedAttempt($ip);
                                    sleep(rand(1, 3));
                                }
                            }
                        } else {
                            // Usuario no encontrado
                            $error = 'DNI o contraseña incorrectos';
                            recordFailedAttempt($ip);
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

        /* ✅ Botón de ayuda para debugging */
        .debug-info {
            margin-top: 20px;
            padding: 12px;
            background: rgba(34, 211, 238, 0.1);
            border: 1px solid rgba(34, 211, 238, 0.3);
            border-radius: 8px;
            font-size: 12px;
            color: #94a3b8;
            display: none;
        }

        .debug-info.show {
            display: block;
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

            <!-- ✅ Info de ayuda para debugging -->
            <div class="debug-info" id="debugInfo">
                <strong>💡 ¿Problemas para iniciar sesión?</strong><br>
                • Verificá que el DNI sea correcto (solo números)<br>
                • Verificá que la contraseña sea la que usaste al registrarte<br>
                • Si acabás de registrarte, probá con la misma contraseña<br>
                • Si el problema persiste, contactá al administrador
            </div>

            <div class="footer">
                ¿No tenés cuenta? <a href="register.php">Crear cuenta</a> · 
                <a href="index.php">Volver al inicio</a>
                <br><br>
                <a href="#" onclick="document.getElementById('debugInfo').classList.toggle('show'); return false;" style="font-size: 12px;">¿Problemas para ingresar?</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>