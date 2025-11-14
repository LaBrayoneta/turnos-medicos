<?php
// views/pages/medico_panel.php - PANEL MÉDICO CORREGIDO
session_start();
require_once __DIR__ . '/../../config/db.php';

$pdo = db();

// Verificar que sea médico
if (empty($_SESSION['Id_usuario']) || $_SESSION['Rol'] !== 'medico') {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['Id_usuario'];
$nombre = $_SESSION['Nombre'] ?? '';
$apellido = $_SESSION['Apellido'] ?? '';

// ✅ CORRECCIÓN: Obtener ID del médico sin campo Contraseña
$stmt = $pdo->prepare("SELECT Id_medico, Id_Especialidad FROM medico WHERE Id_usuario = ? AND Activo = 1 LIMIT 1");
$stmt->execute([$userId]);
$medico = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$medico) {
    die('Error: Usuario no registrado como médico activo');
}

$medicoId = (int)$medico['Id_medico'];

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Obtener especialidad del médico
$stmt = $pdo->prepare("SELECT Nombre FROM especialidad WHERE Id_Especialidad = ?");
$stmt->execute([$medico['Id_Especialidad']]);
$especialidad = $stmt->fetchColumn() ?: 'Sin especialidad';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Panel Médico - Dr. <?= htmlspecialchars($apellido) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <link rel="stylesheet" href="../assets/css/medico_panel.css">
     <link rel="stylesheet" href="<?= asset('css/theme_light.css') ?>">
  <script src="<?= asset('js/theme_toggle.js') ?>"></script>
</head>
<body data-medico-id="<?= $medicoId ?>">
    <header class="hdr">
        <div class="brand">👨‍⚕️ Panel Médico</div>
        <div class="who">Dr. <?= htmlspecialchars($apellido . ', ' . $nombre) ?> - <?= htmlspecialchars($especialidad) ?></div>
        <nav class="actions">
            <a class="btn ghost" href="admin.php">📊 Panel Admin</a>
            <a class="btn ghost" href="index.php">🏠 Inicio</a>
            <form class="inline" action="../../controllers/logout.php" method="post">
                <button class="btn ghost" type="submit">🚪 Salir</button>
            </form>
        </nav>
    </header>

    <main class="wrap">
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-number" id="statHoy">0</div>
                <div class="stat-label">📅 Turnos hoy</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" id="statPendientes">0</div>
                <div class="stat-label">⏳ Pendientes</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" id="statAtendidos">0</div>
                <div class="stat-label">✅ Atendidos</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" id="statSemana">0</div>
                <div class="stat-label">📆 Esta semana</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" data-tab="hoy">📅 Turnos de Hoy</button>
            <button class="tab" data-tab="proximos">🔜 Próximos Turnos</button>
            <button class="tab" data-tab="historial">📋 Historial</button>
        </div>

        <!-- Tab: Turnos de Hoy -->
        <section id="tab-hoy" class="card">
            <h2>📅 Turnos de Hoy</h2>
            <div id="turnosHoyContainer"></div>
        </section>

        <!-- Tab: Próximos Turnos -->
        <section id="tab-proximos" class="card hidden">
            <h2>🔜 Próximos Turnos (Próximos 7 días)</h2>
            <div class="field" style="max-width: 300px; margin-bottom: 20px;">
                <label>Filtrar por fecha</label>
                <input type="date" id="filtroFecha">
            </div>
            <div id="turnosProximosContainer"></div>
        </section>

        <!-- Tab: Historial -->
        <section id="tab-historial" class="card hidden">
            <h2>📋 Historial de Atenciones</h2>
            <div class="grid grid-3">
                <div class="field">
                    <label>Desde</label>
                    <input type="date" id="historialDesde">
                </div>
                <div class="field">
                    <label>Hasta</label>
                    <input type="date" id="historialHasta">
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <button id="btnBuscarHistorial" class="btn primary" style="width: 100%;">🔍 Buscar</button>
                </div>
            </div>
            <div id="historialContainer" style="margin-top: 20px;"></div>
        </section>
    </main>

    <!-- Modal: Atender Paciente / Diagnóstico -->
    <div id="modalDiagnostico" class="modal-diagnostico">
        <div class="modal-content-large">
            <h2 style="color: var(--primary); margin-bottom: 20px;">👨‍⚕️ Atender Paciente</h2>
            
            <div style="background: rgba(34, 211, 238, 0.1); padding: 16px; border-radius: 10px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <strong style="color: var(--primary); font-size: 18px;" id="modalPacienteNombre"></strong>
                        <div style="color: var(--muted); font-size: 14px; margin-top: 4px;">
                            <span id="modalPacienteDNI"></span> • <span id="modalPacienteObra"></span>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 20px; font-weight: 700; color: var(--primary);" id="modalTurnoHora"></div>
                        <div style="color: var(--muted); font-size: 14px;" id="modalTurnoFecha"></div>
                    </div>
                </div>
            </div>

            <form id="formDiagnostico">
                <input type="hidden" id="diagTurnoId">
                <input type="hidden" id="diagPacienteId">

                <!-- Síntomas -->
                <div class="form-section">
                    <h4>🩺 Síntomas</h4>
                    <textarea id="diagSintomas" placeholder="Describa los síntomas reportados por el paciente..."></textarea>
                </div>

                <!-- Diagnóstico -->
                <div class="form-section">
                    <h4>📋 Diagnóstico *</h4>
                    <textarea id="diagDiagnostico" required placeholder="Diagnóstico médico..." style="min-height: 120px;"></textarea>
                </div>

                <!-- Observaciones -->
                <div class="form-section">
                    <h4>📝 Observaciones</h4>
                    <textarea id="diagObservaciones" placeholder="Observaciones adicionales, recomendaciones..."></textarea>
                </div>

                <!-- Receta -->
                <div class="form-section">
                    <h4>💊 Receta Médica</h4>
                    
                    <div class="grid grid-2" style="margin-bottom: 12px;">
                        <div class="field">
                            <label>Medicamento</label>
                            <input type="text" id="recetaMedicamento" placeholder="Ej: Ibuprofeno 400mg">
                        </div>
                        <div class="field">
                            <label>Indicación</label>
                            <input type="text" id="recetaIndicacion" placeholder="Ej: 1 cada 8hs">
                        </div>
                    </div>
                    
                    <button type="button" id="btnAgregarMedicamento" class="btn ghost" style="width: 100%; margin-bottom: 12px;">
                        ➕ Agregar Medicamento
                    </button>

                    <div id="medicamentosLista"></div>

                    <div class="field" style="margin-top: 16px;">
                        <label>Duración del tratamiento</label>
                        <input type="text" id="recetaDuracion" placeholder="Ej: 7 días, 2 semanas, etc.">
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap;">
                    <button type="submit" class="btn primary">✅ Guardar y Marcar como Atendido</button>
                    <button type="button" id="btnCerrarModal" class="btn ghost">❌ Cancelar</button>
                </div>

                <div id="msgDiagnostico" class="msg" style="margin-top: 12px;"></div>
            </form>
        </div>
    </div>

    <script src="../assets/js/medico_panel.js"></script>
</body>
</html>