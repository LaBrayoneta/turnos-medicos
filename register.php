<?php
// register.php — Registro: crea usuario, crea paciente (Obra_social + Libreta_sanitaria) y sincroniza usuario.Id_paciente
session_start();
require_once __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$pdo = db();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!$token || !hash_equals($csrf, $token)) $errors[] = 'CSRF inválido';

  $dni      = trim($_POST['dni'] ?? '');
  $nombre   = trim($_POST['nombre'] ?? '');
  $apellido = trim($_POST['apellido'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';
  $obra     = trim($_POST['obra_social'] ?? '');
  $libreta  = trim($_POST['libreta_sanitaria'] ?? '');

  if ($dni==='') $errors[]='DNI requerido';
  if ($nombre==='') $errors[]='Nombre requerido';
  if ($apellido==='') $errors[]='Apellido requerido';
  if ($email==='') $errors[]='Email requerido';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[]='Email inválido';
  if ($password==='') $errors[]='Contraseña requerida';
  if ($obra==='') $errors[]='Obra social requerida';
  if ($libreta==='') $errors[]='Libreta sanitaria requerida';

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // Unicidad por email o DNI
      $st = $pdo->prepare("SELECT 1 FROM usuario WHERE email=? OR dni=? LIMIT 1");
      $st->execute([$email, $dni]);
      if ($st->fetch()) throw new Exception('Email o DNI ya registrados');

      $hash = password_hash($password, PASSWORD_DEFAULT);

      // INSERT usuario (usa columna Contraseña y Rol NOT NULL)
      $insU = $pdo->prepare("INSERT INTO usuario (Nombre, Apellido, dni, email, Contraseña, Rol) VALUES (?,?,?,?,?, 'paciente')");
      $insU->execute([$nombre, $apellido, $dni, $email, $hash]);
      $userId = (int)$pdo->lastInsertId();

      // INSERT paciente (Obra_social y Libreta_sanitaria son NOT NULL)
      $insP = $pdo->prepare("INSERT INTO paciente (Obra_social, Libreta_sanitaria, Id_usuario, Activo) VALUES (?,?,?,1)");
      $insP->execute([$obra, $libreta, $userId]);
      $pacId = (int)$pdo->lastInsertId();

      // Sincroniza usuario.Id_paciente
      $pdo->prepare("UPDATE usuario SET Id_paciente=? WHERE Id_usuario=?")->execute([$pacId, $userId]);

      $pdo->commit();

      // Login automático
      $_SESSION['Id_usuario'] = $userId;
      $_SESSION['Nombre'] = $nombre;
      $_SESSION['Apellido'] = $apellido;

      header('Location: index.php');
      exit;
    } catch(Throwable $e) {
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
  <title>Registro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
  <style>
    body{font-family:system-ui,Arial;margin:0;background:#0b1220;color:#e5e7eb}
    .wrap{max-width:520px;margin:40px auto;padding:0 16px}
    .card{background:#111827;border:1px solid #1f2937;border-radius:14px;padding:18px}
    .field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
    label{color:#94a3b8}
    input{background:#0b1220;border:1px solid #1f2937;border-radius:10px;color:#e5e7eb;padding:10px}
    .btn{padding:10px 14px;border-radius:12px;border:1px solid #1f2937;background:#22d3ee;color:#001219;font-weight:700;cursor:pointer}
    .err{color:#ef4444;margin:6px 0}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h2>Crear cuenta</h2>
      <?php if ($errors): ?>
        <div class="err"><?= htmlspecialchars(implode(' · ', $errors)) ?></div>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="field">
          <label>DNI</label>
          <input type="text" name="dni" required>
        </div>
        <div class="field">
          <label>Nombre</label>
          <input type="text" name="nombre" required>
        </div>
        <div class="field">
          <label>Apellido</label>
          <input type="text" name="apellido" required>
        </div>
        <div class="field">
          <label>Obra social</label>
          <input type="text" name="obra_social" required>
        </div>
        <div class="field">
          <label>Libreta sanitaria</label>
          <input type="text" name="libreta_sanitaria" required>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" required>
        </div>
        <div class="field">
          <label>Contraseña</label>
          <input type="password" name="password" required>
        </div>
        <button class="btn" type="submit">Registrarme</button>
      </form>
    </div>
  </div>
</body>
</html>
