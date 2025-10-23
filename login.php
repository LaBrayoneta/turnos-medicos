<?php
// login.php — versión con sesión
session_start();
require_once __DIR__ . '/db.php'; // usa tu PDO existente

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $dni = trim($_POST['dni'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($dni === '' || $password === '') {
    $err = 'Completá DNI y contraseña.';
  } else {
    try {
      $pdo = db(); // debe existir en tu db.php
      // Trae al usuario por DNI
      $st = $pdo->prepare("SELECT Id_usuario, Nombre, Apellido, dni, email, Rol, Contraseña FROM usuario WHERE dni = ? LIMIT 1");
      $st->execute([$dni]);
      $u = $st->fetch(PDO::FETCH_ASSOC);

      if ($u && password_verify($password, $u['Contraseña'])) {
        // 🟢 Sesión: guardamos datos útiles
        $_SESSION['Id_usuario'] = (int)$u['Id_usuario'];
        $_SESSION['dni']        = (string)$u['dni'];
        $_SESSION['email']      = (string)$u['email'];
        $_SESSION['Rol']        = (string)($u['Rol'] ?? '');
        $_SESSION['Nombre']     = (string)$u['Nombre'];
        $_SESSION['Apellido']   = (string)$u['Apellido'];

        // Opcional: regenerar ID para seguridad
        session_regenerate_id(true);

        header('Location: index.php');
        exit;
      } else {
        $err = 'DNI o contraseña inválidos.';
      }
    } catch (Throwable $e) {
      $err = 'Error de servidor: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Iniciar sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Arial,sans-serif;margin:0;padding:24px;background:#0b1220;color:#e5e7eb}
    .card{max-width:420px;margin:0 auto;background:#111827;border:1px solid #1f2937;border-radius:12px;padding:18px}
    label{display:block;margin:10px 0 6px;color:#94a3b8}
    input{width:95%;padding:10px;border-radius:8px;border:1px solid #1f2937;background:#0b1220;color:#e5e7eb}
    .row{margin-top:12px;display:flex;gap:10px;align-items:center}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #1f2937;background:#22d3ee;color:#001219;font-weight:700;cursor:pointer}
    .msg{margin-top:10px;color:#ef4444}
    a{color:#22d3ee}
  </style>
</head>
<body>
  <div class="card">
    <h2>Ingresar</h2>
    <?php if ($err): ?><div class="msg">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post" action="login.php" autocomplete="on">
      <label for="dni">DNI</label>
      <input type="text" id="dni" name="dni" inputmode="numeric" required>

      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required>

      <div class="row">
        <button class="btn" type="submit">Entrar</button>
        <a href="index.php">Volver</a>
      </div>
    </form>
  </div>
</body>
</html>
