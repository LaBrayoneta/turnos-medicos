<?php
session_start();
require_once __DIR__ . '/config/db.php';

$logueado = !empty($_SESSION['Id_usuario']);
$nombre = ($logueado ? ($_SESSION['Nombre'] ?? '') : '');
$apellido = ($logueado ? ($_SESSION['Apellido'] ?? '') : '');
$rol = ($logueado ? ($_SESSION['Rol'] ?? '') : '');

// Redirigir a admin si es médico o secretaria
if ($logueado && ($rol === 'medico' || $rol === 'secretaria')) {
  header('Location: admin.php');
  exit;
}

function ensureCsrf() {
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf_token'];
}
$csrf = ensureCsrf();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Clínica Médica - Sistema de Turnos Online</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
  <link rel="stylesheet" href="index.css">
  <style>
    /* Mejoras visuales adicionales */
    :root {
      --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.3);
      --shadow-xl: 0 30px 80px rgba(0, 0, 0, 0.4);
    }

    .hero {
      position: relative;
      background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0f172a 100%);
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(34,211,238,0.15) 0%, transparent 70%);
      border-radius: 50%;
      animation: float 20s ease-in-out infinite;
    }

    .hero::after {
      content: '';
      position: absolute;
      bottom: -30%;
      left: -10%;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(139,92,246,0.1) 0%, transparent 70%);
      border-radius: 50%;
      animation: float 15s ease-in-out infinite reverse;
    }

    @keyframes float {
      0%, 100% { transform: translate(0, 0) rotate(0deg); }
      50% { transform: translate(30px, -30px) rotate(10deg); }
    }

    .hero-title {
      background: linear-gradient(135deg, #22d3ee 0%, #8b5cf6 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: gradient-shift 3s ease infinite;
    }

    @keyframes gradient-shift {
      0%, 100% { filter: hue-rotate(0deg); }
      50% { filter: hue-rotate(20deg); }
    }

    .feature-card {
      position: relative;
      background: linear-gradient(145deg, #111827 0%, #1f2937 100%);
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: var(--gradient-3);
      transform: scaleX(0);
      transition: transform 0.3s;
    }

    .feature-card:hover::before {
      transform: scaleX(1);
    }

    .feature-icon {
      background: var(--gradient-3);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: pulse-icon 2s ease-in-out infinite;
    }

    @keyframes pulse-icon {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }

    .day.available {
      position: relative;
      overflow: hidden;
    }

    .day.available::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(34,211,238,0.3);
      transform: translate(-50%, -50%);
      transition: width 0.3s, height 0.3s;
    }

    .day.available:hover::before {
      width: 100%;
      height: 100%;
    }

    .slot {
      position: relative;
      overflow: hidden;
      background: linear-gradient(145deg, #0f172a 0%, #1a2332 100%);
    }

    .slot::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(34,211,238,0.3), transparent);
      transition: left 0.5s;
    }

    .slot:hover::before {
      left: 100%;
    }

    .slot.sel {
      background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
      box-shadow: 0 0 20px rgba(34,211,238,0.4);
    }

    .card {
      background: linear-gradient(145deg, #111827 0%, #1a2332 100%);
      box-shadow: var(--shadow-lg);
    }

    .btn.primary {
      background: linear-gradient(135deg, #22d3ee 0%, #0891b2 100%);
      box-shadow: 0 4px 15px rgba(34,211,238,0.4);
      position: relative;
      overflow: hidden;
    }

    .btn.primary::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      border-radius: 50%;
      background: rgba(255,255,255,0.3);
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }

    .btn.primary:active::before {
      width: 300px;
      height: 300px;
    }

    .table-wrap {
      background: #0a0f1a;
    }

    tbody tr {
      transition: all 0.2s;
    }

    tbody tr:hover {
      background: linear-gradient(90deg, rgba(34,211,238,0.05) 0%, rgba(34,211,238,0.1) 50%, rgba(34,211,238,0.05) 100%);
      transform: translateX(4px);
    }

    .badge.ok {
      background: linear-gradient(135deg, rgba(16,185,129,0.2) 0%, rgba(5,150,105,0.2) 100%);
      box-shadow: 0 0 10px rgba(16,185,129,0.3);
    }

    .badge.warn {
      background: linear-gradient(135deg, rgba(251,146,60,0.2) 0%, rgba(234,88,12,0.2) 100%);
      box-shadow: 0 0 10px rgba(251,146,60,0.3);
    }

    /* Animación de carga */
    @keyframes shimmer {
      0% { background-position: -1000px 0; }
      100% { background-position: 1000px 0; }
    }

    .loading {
      background: linear-gradient(90deg, #1a2332 25%, #2a3342 50%, #1a2332 75%);
      background-size: 1000px 100%;
      animation: shimmer 2s infinite;
    }

    /* Stats en hero para usuarios logueados */
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-top: 32px;
    }

    .stat-card {
      background: rgba(17, 24, 39, 0.6);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      backdrop-filter: blur(10px);
    }

    .stat-number {
      font-size: 36px;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 8px;
    }

    .stat-label {
      color: var(--muted);
      font-size: 14px;
    }

    /* Mejoras en calendario */
    .cal-grid {
      gap: 8px;
    }

    .cal-header {
      background: rgba(15, 23, 42, 0.5);
      padding: 12px;
      border-radius: 12px;
      margin-bottom: 16px;
    }

    .cal-title {
      font-size: 18px;
      background: linear-gradient(135deg, #22d3ee 0%, #8b5cf6 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
  </style>
</head>
<body>
  <header class="hdr">
    <div class="brand">🏥 Clínica Médica</div>
    <div class="actions">
      <?php if ($logueado): ?>
        <span style="color:var(--muted)">👋 Hola, <strong style="color:var(--primary)"><?= htmlspecialchars($nombre . ' ' . $apellido) ?></strong></span>
        <form class="inline" action="logout.php" method="post" style="display:inline;margin:0">
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
    <!-- PORTADA MEJORADA -->
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
        <p>Elegí el día y horario que mejor se ajuste a tu agenda. Sin esperas telefónicas, todo desde tu dispositivo.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">👨‍⚕️</div>
        <h3>Profesionales Calificados</h3>
        <p>Accedé a especialistas en clínica médica, pediatría, cardiología, traumatología y más disciplinas.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🔔</div>
        <h3>Gestión Total</h3>
        <p>Cancelá o reprogramá tus turnos cuando lo necesites. Control total de tus citas médicas.</p>
      </div>
    </section>

    <section class="card" style="margin-top: 40px; text-align: center; padding: 40px;">
      <h2 style="color: var(--primary); margin-bottom: 16px;">¿Listo para dar el primer paso?</h2>
      <p style="color: var(--muted); margin-bottom: 24px; max-width: 600px; margin-left: auto; margin-right: auto;">
        Únete a cientos de pacientes que ya confían en nuestro sistema. Crear una cuenta es rápido, seguro y completamente gratuito.
      </p>
      <a href="register.php" class="btn primary" style="display: inline-block; min-width: 200px; padding: 14px 28px;">Crear mi cuenta gratis</a>
    </section>
    
    <?php else: ?>
    <!-- SISTEMA DE TURNOS PARA PACIENTES LOGUEADOS -->
    <div class="layout">
      <!-- Columna izquierda: Reserva -->
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

        <!-- Calendario mejorado -->
        <div class="cal-wrap">
          <div class="cal-header">
            <button id="calPrev" class="btn ghost" aria-label="Mes anterior" style="padding: 8px 12px;">‹</button>
            <div id="calTitle" class="cal-title">Mes Año</div>
            <button id="calNext" class="btn ghost" aria-label="Mes siguiente" style="padding: 8px 12px;">›</button>
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

      <!-- Columna derecha: Mis turnos -->
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

  <?php if ($logueado): ?>
  <script>
  // Función helper para formato 12h
  function formatHour12(time24) {
    if (!time24) return '';
    const parts = time24.split(':');
    let h = parseInt(parts[0]);
    const m = parts[1];
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${m} ${ampm}`;
  }
  </script>
  <script src="index.js"></script>
  <script>
  // Modificar la renderización de slots para mostrar AM/PM
  (function() {
    const originalRenderSlots = window.renderSlots;
    if (typeof originalRenderSlots === 'function') {
      window.renderSlots = function(list) {
        const slotsBox = document.getElementById('slots');
        if (!slotsBox) return;
        
        slotsBox.innerHTML = '';
        if(!Array.isArray(list) || list.length===0){
          slotsBox.textContent = 'No hay horarios disponibles';
          const btnReservar = document.getElementById('btnReservar');
          if (btnReservar) btnReservar.disabled = true;
          return;
        }
        
        list.forEach(hhmm=>{
          const b = document.createElement('button');
          b.type='button';
          b.className='slot';
          b.textContent = formatHour12(hhmm);
          b.dataset.value = hhmm;
          b.addEventListener('click', ()=>{
            window.selectedSlot = hhmm; // Guardamos el valor original 24h
            document.querySelectorAll('.slot').forEach(x=>x.classList.remove('sel'));
            b.classList.add('sel');
            const btnReservar = document.getElementById('btnReservar');
            const selMedico = document.getElementById('selMedico');
            if (btnReservar) btnReservar.disabled = !selMedico?.value;
            const msg = document.getElementById('msg');
            if (msg) {
              msg.textContent = '';
              msg.className = 'msg';
            }
          });
          slotsBox.appendChild(b);
        });
      };
    }
  })();
  </script>
  <?php endif; ?>
</body>
</html>