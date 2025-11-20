<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // 0 para desarrollo local HTTP
ini_set('session.use_only_cookies', 1);

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

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
    $password = $_POST['password'] ?? '';
    
    error_log("🔐 Login attempt - DNI: $dni from IP: $ip");
    
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
        } elseif (strlen($password) < 1 || strlen($password) > 255) {
            $error = 'Contraseña inválida';
            sleep(2);
        } else {
            try {
                // ✅ CORRECCIÓN: Usar columnas en MINÚSCULAS
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
                            WHEN m.Id_medico IS NOT NULL AND m.activo = 1 THEN 1
                            ELSE 0
                        END AS is_medico_activo,
                        CASE 
                            WHEN s.Id_secretaria IS NOT NULL AND s.activo = 1 THEN 1
                            ELSE 0
                        END AS is_secretaria_activa,
                        CASE 
                            WHEN p.Id_paciente IS NOT NULL AND p.activo = 1 THEN 1
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
                    
                    error_log("👤 Usuario encontrado: ID $userId, Rol: {$user['rol']}");
                    
                    // Verificar bloqueo
                    if (isAccountLocked($user)) {
                        $remainingMinutes = ceil((strtotime($user['account_locked_until']) - time()) / 60);
                        $error = "Cuenta bloqueada temporalmente. Intenta nuevamente en {$remainingMinutes} minuto(s).";
                        error_log("🔒 Locked account: User $userId from IP $ip");
                        sleep(2);
                    }
                    // Verificar que exista un hash válido
                    elseif (empty($user['password']) || strlen($user['password']) < 10) {
                        error_log("❌ Invalid password hash for user $userId");
                        $error = 'Error en la cuenta. Contacta al administrador.';
                    }
                    // Verificar contraseña
                    elseif (password_verify($password, $user['password'])) {
                        error_log("✅ Password verified for user $userId");
                        
                        // Verificar que el usuario tenga rol activo
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
                        } elseif ($user['rol'] === '' && $user['is_paciente_activo']) {
                            $hasActiveRole = true;
                            $actualRole = 'paciente';
                        }
                        
                        if (!$hasActiveRole) {
                            $error = 'Usuario inactivo o sin rol asignado. Contacta al administrador.';
                            error_log("❌ Inactive user: User $userId, Rol: {$user['rol']}");
                            sleep(2);
                        } else {
                            // ✅ LOGIN EXITOSO
                            error_log("🎉 Login successful: User $userId ($actualRole)");
                            
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

                            error_log("✅ Session created for user $userId");

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
                        error_log("❌ Wrong password for user $userId");
                        recordFailedAttempt($pdo, $userId);
                        
                        $remainingAttempts = 5 - ($user['failed_login_attempts'] + 1);
                        if ($remainingAttempts > 0) {
                            $error = "DNI o contraseña incorrectos. Te quedan {$remainingAttempts} intento(s).";
                        } else {
                            $error = "DNI o contraseña incorrectos. Tu cuenta ha sido bloqueada por 15 minutos.";
                        }
                        
                        sleep(rand(2, 4));
                    }
                } else {
                    // Usuario no encontrado
                    $error = 'DNI o contraseña incorrectos';
                    error_log("❌ DNI not found: $dni from IP $ip");
                    sleep(rand(2, 4));
                }
            } catch (Throwable $e) {
                error_log('❌ Login error: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                $error = 'Error al procesar el inicio de sesión. Intenta nuevamente.';
                sleep(2);
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
    <link rel="stylesheet" href="<?= asset('css/auth.css') ?>">
<link rel="stylesheet" href="<?= asset('css/theme_light.css') ?>">
<script src="<?= asset('js/theme_toggle.js') ?>"></script>
</head>
<body>
   <header class="hdr" style="position:fixed;top:0;right:0;background:transparent;border:none;padding:16px;">
    <div class="actions"></div>
</header>
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
    <script>
        (function() {
            'use strict';
            const form = document.getElementById('loginForm');
            const dniInput = document.getElementById('dni');
            const passwordInput = document.getElementById('password');
            // Solo números en DNI
            dniInput?.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
            // Validación del formulario
            form?.addEventListener('submit', function(e) {
                const dni = dniInput.value.trim();
                const password = passwordInput.value;
                if (!dni) {
                    e.preventDefault();
                    alert('⚠️ Por favor, ingresá tu DNI');
                    dniInput.focus();
                    return false;
                }
                if (dni.length < 7 || dni.length > 10) {
                    e.preventDefault();
                    alert('⚠️ El DNI debe tener entre 7 y 10 dígitos');
                    dniInput.focus();
                    return false;
                }
                if (!password) {
                    e.preventDefault();
                    alert('⚠️ Por favor, ingresá tu contraseña');
                    passwordInput.focus();
                    return false;
                }
                // Deshabilitar botón para evitar doble envío
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Iniciando sesión...';
                }
            });

            // Log para debug
            console.log('🔐 Login form initialized');
        })();
    </script>
</body>
</html>