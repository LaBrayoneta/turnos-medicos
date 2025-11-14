<?php
/**
 * views/pages/index.php - VERSIÓN SIN BUCLES DE REDIRECCIÓN
 */

// Iniciar sesión
session_start();

// Incluir configuración
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

// Variables de sesión
$logueado = !empty($_SESSION['Id_usuario']);
$nombre = ($logueado ? ($_SESSION['Nombre'] ?? '') : '');
$apellido = ($logueado ? ($_SESSION['Apellido'] ?? '') : '');
$rol = ($logueado ? ($_SESSION['Rol'] ?? '') : '');

// ✅ IMPORTANTE: NO redirigir aquí, solo mostrar mensaje o enlace
// Eliminar cualquier header('Location: ...') para evitar bucles

// Generar token CSRF
function ensureCsrf() {
  if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
$csrf = ensureCsrf();

// API Base URL
$api_base_url = controller('turnos_api.php');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Clínica Médica - Sistema de Turnos Online</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
  
  <script>
    window.API_BASE_URL = '<?= $api_base_url ?>';
    console.log('🔧 API Base URL:', window.API_BASE_URL);
  </script>
  
  <link rel="stylesheet" href="<?= asset('css/index.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/theme_light.css') ?>">
  <script src="<?= asset('js/theme_toggle.js') ?>"></script>
</head>
<body>
  <!-- Reemplazar el header en index.php con esto: -->

<header class="hdr">
  <div class="brand">🏥 Clinica Vida Plena</div>
  <div class="actions">
    <?php if ($logueado): ?>
      <span style="color:var(--muted)">
        👋 Hola, <strong style="color:var(--primary)"><?= htmlspecialchars($nombre . ' ' . $apellido) ?></strong>
      </span>
      
      <?php if ($rol === 'medico'): ?>
        <!-- Médico: puede acceder a ambos paneles -->
        <a class="btn primary" href="medico_panel.php">👨‍⚕️ Mi Panel Médico</a>
        <a class="btn ghost" href="admin.php">📊 Panel Admin</a>
      <?php elseif ($rol === 'secretaria'): ?>
        <!-- Secretaria: solo panel admin -->
        <a class="btn primary" href="admin.php">📊 Panel Admin</a>
      <?php endif; ?>
      
      <form class="inline" action="<?= controller('logout.php') ?>" method="post" style="display:inline;margin:0">
        <button type="submit" class="btn ghost">🚪 Cerrar sesión</button>
      </form>
    <?php else: ?>
      <a class="btn primary" href="login.php">Iniciar sesión</a>
      <a class="btn ghost" href="register.php">Crear cuenta</a>
    <?php endif; ?>
  </div>
</header>
  <main class="wrap">
    <?php if (!$logueado): ?>
    <!-- ========== PORTADA PARA NO LOGUEADOS ========== -->
    <section class="hero">
      <div class="hero-content">
        <h1 class="hero-title">Tu salud, nuestra prioridad</h1>
        <p class="hero-subtitle">Sistema de turnos médicos online. Reservá tu consulta en segundos, desde cualquier lugar.</p>
        <div class="hero-actions">
          <a href="register.php" class="btn btn-hero primary">🚀 Comenzar ahora</a>
          <a href="login.php" class="btn btn-hero ghost">Ya tengo cuenta →</a>
        </div>
      </div>
    </section>

    <section class="features">
      <div class="feature-card">
        <div class="feature-icon">📅</div>
        <h3>Reserva Instantánea</h3>
        <p>Elegí el día y horario que mejor se ajuste a tu agenda. Sin esperas telefónicas.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">👨‍⚕️</div>
        <h3>Profesionales Calificados</h3>
        <p>Accedé a especialistas en diversas áreas de la medicina.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🔔</div>
        <h3>Gestión Total</h3>
        <p>Cancelá o reprogramá tus turnos cuando lo necesites.</p>
      </div>
    </section>

    <section class="card" style="margin-top: 40px; text-align: center; padding: 40px;">
      <h2 style="color: var(--primary); margin-bottom: 16px;">¿Listo para dar el primer paso?</h2>
      <p style="color: var(--muted); margin-bottom: 24px; max-width: 600px; margin-left: auto; margin-right: auto;">
        Crear una cuenta es rápido, seguro y completamente gratuito.
      </p>
      <a href="register.php" class="btn primary" style="display: inline-block; min-width: 200px; padding: 14px 28px;">Crear mi cuenta gratis</a>
    </section>
    
    <?php elseif ($rol === 'medico' || $rol === 'secretaria'): ?>
    <!-- ========== MENSAJE PARA STAFF ========== -->
    <section class="card" style="text-align: center; padding: 60px 20px;">
      <div style="font-size: 64px; margin-bottom: 20px;">
        <?= $rol === 'medico' ? '👨‍⚕️' : '👩‍💼' ?>
      </div>
      <h2>Bienvenido/a, <?= htmlspecialchars($rol) ?></h2>
      <p style="color: var(--muted); margin: 20px 0; font-size: 16px;">
        Para acceder a tus funciones, dirígete al panel administrativo.
      </p>
      <a href="admin.php" class="btn primary" style="display: inline-block; padding: 16px 32px; font-size: 17px; margin-top: 16px;">
        📊 Ir al Panel Administrativo
      </a>
    </section>
    
    <?php else: ?>
    <!-- ========== SISTEMA DE TURNOS PARA PACIENTES ========== -->
    <div class="layout">
      <section class="card">
        <h2>📅 Reservar turno</h2>

        <div class="grid grid-3">
          <div class="field">
            <label for="selEsp">Especialidad</label>
            <select id="selEsp">
              <option value="">Cargando…</option>
            </select>
          </div>
          <div class="field">
            <label for="selMedico">Médico</label>
            <select id="selMedico" disabled>
              <option value="">Elegí especialidad…</option>
            </select>
          </div>
        </div>

        <div class="cal-wrap">
          <div class="cal-header">
            <button id="calPrev" class="btn ghost" style="padding: 8px 12px;">‹</button>
            <div id="calTitle" class="cal-title">Mes Año</div>
            <button id="calNext" class="btn ghost" style="padding: 8px 12px;">›</button>
          </div>
          <div class="cal-grid cal-week">
            <div style="color: var(--primary); font-weight: 700;">Lun</div>
            <div style="color: var(--primary); font-weight: 700;">Mar</div>
            <div style="color: var(--primary); font-weight: 700;">Mié</div>
            <div style="color: var(--primary); font-weight: 700;">Jue</div>
            <div style="color: var(--primary); font-weight: 700;">Vie</div>
            <div class="muted">Sáb</div>
            <div class="muted">Dom</div>
          </div>
          <div id="calGrid" class="cal-grid cal-days"></div>
          <div id="calHint" class="cal-hint">💡 Seleccioná un médico para ver sus días disponibles</div>
        </div>

        <div class="grid">
          <div class="field">
            <label>⏰ Horarios disponibles</label>
            <div id="slots" class="slots">Elegí un médico y un día disponible…</div>
          </div>
        </div>

        <div class="actions-row">
          <button id="btnReservar" class="btn primary" disabled>✅ Reservar turno</button>
          <span id="msg" class="msg"></span>
        </div>
      </section>

      <section class="card side">
        <h3>📋 Mis próximos turnos</h3>
        <div class="table-wrap">
          <table id="tblTurnos">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Médico</th>
                <th>Especialidad</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </section>
    </div>
    <?php endif; ?>
  </main>

  <footer class="footer">
    <div class="footer-content">
      <p>&copy; <?= date('Y') ?> Clínica Médica. Todos los derechos reservados.</p>
      <p style="margin-top: 8px; color: var(--primary); font-size: 13px;">
        🏥 Atención de calidad • 📱 Turnos online • 💙 Cuidamos tu salud
      </p>
      <p class="footer-links" style="margin-top: 12px;">
        <a href="#">Términos y Condiciones</a> · 
        <a href="#">Política de Privacidad</a> · 
        <a href="#">Contacto</a>
      </p>
    </div>
  </footer>

  <?php if ($logueado && ($rol === 'paciente' || $rol === '')): ?>
  <!-- Cargar JS solo para pacientes -->
  <script src="<?= asset('js/turnos_utils.js') ?>"></script>
  <script src="<?= asset('js/index.js') ?>"></script>
  <?php endif; ?>
</body>
</html>