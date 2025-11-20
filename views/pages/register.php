<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$pdo = db();
$errors = [];

$obras_sociales = [];
try {
    $stmt = $pdo->query("SELECT Id_obra_social, nombre FROM obra_social WHERE activo=1 ORDER BY nombre");
    $obras_sociales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $obras_sociales = [];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($csrf, $token)) {
        $errors[] = 'Token de seguridad inválido';
    }

    $dni      = trim($_POST['dni'] ?? '');
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $idObra   = (int)($_POST['id_obra_social'] ?? 0);
    $obraOtra = trim($_POST['obra_social_otra'] ?? '');
    $nroCarnet = trim($_POST['nro_carnet'] ?? '');
    $libreta  = trim($_POST['libreta_sanitaria'] ?? '');

    // Validaciones
    if (empty($dni)) $errors[] = 'El DNI es obligatorio';
    elseif (!filter_var($dni, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1000000, 'max_range' => 99999999999]])) $errors[] = 'El DNI no es válido';

    if (empty($nombre)) $errors[] = 'El nombre es obligatorio';
    elseif (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 50) $errors[] = 'El nombre debe tener entre 2 y 50 caracteres';

    if (empty($apellido)) $errors[] = 'El apellido es obligatorio';
    elseif (mb_strlen($apellido) < 2 || mb_strlen($apellido) > 50) $errors[] = 'El apellido debe tener entre 2 y 50 caracteres';

    if (empty($email)) $errors[] = 'El email es obligatorio';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido';

    if (empty($password)) $errors[] = 'La contraseña es obligatoria';
    elseif (strlen($password) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres';
    elseif (!preg_match('/[A-Z]/', $password)) $errors[] = 'La contraseña debe contener una mayúscula';
    elseif (!preg_match('/[a-z]/', $password)) $errors[] = 'La contraseña debe contener una minúscula';
    elseif (!preg_match('/[0-9]/', $password)) $errors[] = 'La contraseña debe contener un número';

    if ($password !== $password2) $errors[] = 'Las contraseñas no coinciden';

    if ($idObra === -1 && empty($obraOtra)) $errors[] = 'Especificá el nombre de la obra social';
    elseif ($idObra <= 0 && $idObra !== -1) $errors[] = 'Seleccioná una obra social';

    if (empty($libreta)) $errors[] = 'La libreta sanitaria es obligatoria';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email = ? OR dni = ?");
            $stmt->execute([$email, $dni]);
            if ($stmt->fetchColumn() > 0) throw new Exception('El email o DNI ya están registrados');

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

            $stmtUser = $pdo->prepare("INSERT INTO usuario (nombre, apellido, dni, email, password, rol) VALUES (?, ?, ?, ?, ?, 'paciente')");
            $stmtUser->execute([$nombre, $apellido, $dni, $email, $passwordHash]);
            $userId = (int)$pdo->lastInsertId();

            $stmtPaciente = $pdo->prepare("INSERT INTO paciente (Id_obra_social, nro_carnet, libreta_sanitaria, Id_usuario, activo) VALUES (?, ?, ?, ?, 1)");
            $stmtPaciente->execute([$idObra > 0 ? $idObra : null, $nroCarnet !== '' ? $nroCarnet : null, $libreta, $userId]);

            $pdo->commit();

            $_SESSION['Id_usuario'] = $userId;
            $_SESSION['dni'] = $dni;
            $_SESSION['email'] = $email;
            $_SESSION['Nombre'] = $nombre;
            $_SESSION['Apellido'] = $apellido;
            $_SESSION['Rol'] = 'paciente';
            session_regenerate_id(true);

            header('Location: index.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
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
    <link rel="stylesheet" href="<?= asset('css/register.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/theme_light.css') ?>">
    <script src="<?= asset('js/theme_toggle.js') ?>"></script>
</head>
<body>
    <header class="hdr">
        <div class="brand">🏥 Clínica Vida Plena</div>
        <div class="actions"></div>
    </header>
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
                        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Juan" autocomplete="given-name" required>
                    </div>
                    <div class="field">
                        <label for="apellido">Apellido <span class="required">*</span></label>
                        <input type="text" id="apellido" name="apellido" value="<?= htmlspecialchars($_POST['apellido'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Pérez" autocomplete="family-name" required>
                    </div>
                </div>

                <div class="field">
                    <label for="dni">DNI <span class="required">*</span></label>
                    <input type="text" id="dni" name="dni" value="<?= htmlspecialchars($_POST['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="12345678" inputmode="numeric" pattern="[0-9]{7,10}" maxlength="10" required>
                </div>

                <div class="field">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="tu@email.com" autocomplete="email" required>
                </div>

                <div class="field">
                    <label for="id_obra_social">Obra Social <span class="required">*</span></label>
                    <select id="id_obra_social" name="id_obra_social" required>
                        <option value="">Seleccioná tu obra social...</option>
                        <?php foreach ($obras_sociales as $obra): ?>
                            <option value="<?= (int)$obra['Id_obra_social'] ?>" <?= ($_POST['id_obra_social'] ?? '') == $obra['Id_obra_social'] ? 'selected' : '' ?>><?= htmlspecialchars($obra['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                        <option value="-1" <?= ($_POST['id_obra_social'] ?? '') == '-1' ? 'selected' : '' ?>>➕ Otra (especificar)</option>
                    </select>
                </div>

                <div class="field hidden" id="fieldObraOtra">
                    <label for="obra_social_otra">Nombre de la Obra Social <span class="required">*</span></label>
                    <input type="text" id="obra_social_otra" name="obra_social_otra" value="<?= htmlspecialchars($_POST['obra_social_otra'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: IOSPER, OSECAC, etc.">
                    <div class="hint">Ingresá el nombre completo de tu obra social</div>
                </div>

                <div class="field">
                    <label for="nro_carnet">Número de Carnet</label>
                    <input type="text" id="nro_carnet" name="nro_carnet" value="<?= htmlspecialchars($_POST['nro_carnet'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 123456789">
                    <div class="hint">Opcional - Si tenés obra social, ingresá tu número de carnet</div>
                </div>

                <div class="field">
                    <label for="libreta_sanitaria">Libreta Sanitaria <span class="required">*</span></label>
                    <input type="text" id="libreta_sanitaria" name="libreta_sanitaria" value="<?= htmlspecialchars($_POST['libreta_sanitaria'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Número o identificación" required>
                </div>

                <div class="field">
                    <label for="password">Contraseña <span class="required">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Mínimo 8 caracteres" autocomplete="new-password" minlength="8" required>
                    <div class="password-strength" id="strengthMsg"></div>
                </div>

                <div class="field">
                    <label for="password2">Confirmar Contraseña <span class="required">*</span></label>
                    <input type="password" id="password2" name="password2" placeholder="Repetí tu contraseña" autocomplete="new-password" minlength="8" required>
                </div>

                <button type="submit" class="btn">Crear cuenta</button>
            </form>

            <div class="footer">
                ¿Ya tenés cuenta? <a href="login.php">Iniciá sesión</a> · 
                <a href="index.php">Volver al inicio</a>
            </div>
        </div>
    </div>

    <script src="<?= asset('js/register.js') ?>"></script>
</body>
</html>