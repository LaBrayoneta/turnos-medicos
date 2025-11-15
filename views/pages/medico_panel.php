<?php
// views/pages/medico_panel.php - PANEL MÉDICO CORREGIDO
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

$pdo = db();

// Verificar que sea médico
if (empty($_SESSION['Id_usuario']) || $_SESSION['Rol'] !== 'medico') {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['Id_usuario'];
$nombre = $_SESSION['Nombre'] ?? '';
$apellido = $_SESSION['Apellido'] ?? '';

// Obtener ID del médico
$stmt = $pdo->prepare("SELECT Id_medico, Id_Especialidad FROM medico WHERE Id_usuario = ? AND activo = 1 LIMIT 1");
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
$stmt = $pdo->prepare("SELECT nombre FROM especialidad WHERE Id_Especialidad = ?");
$stmt->execute([$medico['Id_Especialidad']]);
$especialidad = $stmt->fetchColumn() ?: 'Sin especialidad';

// Obtener medicamentos disponibles para el autocompletado
$medicamentos = [];
try {
    $stmt = $pdo->query("SELECT nombre, dosis_usual, presentacion FROM medicamento WHERE activo = 1 ORDER BY nombre");
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $medicamentos = [];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Panel Médico - Dr. <?= htmlspecialchars($apellido) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <meta name="medicamentos-data" content='<?= htmlspecialchars(json_encode($medicamentos), ENT_QUOTES, 'UTF-8') ?>'>
    
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
            <button class="tab" data-tab="historial">📋 Historial de Consultas</button>
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
                <label>Filtrar por fecha específica</label>
                <input type="date" id="filtroFecha">
            </div>
            <div id="turnosProximosContainer"></div>
        </section>

        <!-- Tab: Historial -->
        <section id="tab-historial" class="card hidden">
            <h2>📋 Historial de Consultas</h2>
            <p style="color: var(--muted); margin-bottom: 20px;">
                Visualiza el detalle completo de todas las consultas realizadas, incluyendo diagnósticos y medicación recetada.
            </p>
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
            
            <!-- Información del Paciente y Turno -->
            <div class="patient-info-header">
                <div class="patient-details">
                    <div class="patient-name" id="modalPacienteNombre">Cargando...</div>
                    <div class="patient-meta">
                        <span id="modalPacienteDNI">DNI: ---</span>
                        <span class="separator">•</span>
                        <span id="modalPacienteObra">---</span>
                        <span class="separator">•</span>
                        <span id="modalPacienteLibreta">Libreta: ---</span>
                    </div>
                </div>
                <div class="appointment-time">
                    <div class="time-display" id="modalTurnoHora">--:--</div>
                    <div class="date-display" id="modalTurnoFecha">--- -- de ----</div>
                </div>
            </div>

            <form id="formDiagnostico">
                <input type="hidden" id="diagTurnoId">
                <input type="hidden" id="diagPacienteId">

                <!-- Síntomas -->
                <div class="form-section">
                    <h4>🩺 Síntomas Reportados</h4>
                    <textarea 
                        id="diagSintomas" 
                        placeholder="Describa los síntomas que reporta el paciente (fiebre, dolor, malestar, etc.)..."
                        rows="3"
                    ></textarea>
                </div>

                <!-- Diagnóstico -->
                <div class="form-section">
                    <h4>📋 Diagnóstico Médico <span style="color: var(--err);">*</span></h4>
                    <textarea 
                        id="diagDiagnostico" 
                        required 
                        placeholder="Escriba el diagnóstico médico completo..."
                        rows="4"
                    ></textarea>
                    <small style="color: var(--muted); display: block; margin-top: 8px;">
                        * Campo obligatorio - Este diagnóstico quedará registrado en el historial clínico
                    </small>
                </div>

                <!-- Observaciones -->
                <div class="form-section">
                    <h4>📝 Observaciones y Recomendaciones</h4>
                    <textarea 
                        id="diagObservaciones" 
                        placeholder="Observaciones adicionales, recomendaciones para el paciente, controles sugeridos..."
                        rows="3"
                    ></textarea>
                </div>

                <!-- Receta Médica -->
                <div class="form-section">
                    <h4>💊 Receta Médica</h4>
                    
                    <div class="grid grid-2" style="margin-bottom: 12px; position: relative;">
                        <div class="field" style="position: relative;">
                            <label>Medicamento</label>
                            <input 
                                type="text" 
                                id="recetaMedicamento" 
                                placeholder="Ej: Ibuprofeno 400mg"
                                autocomplete="off"
                            >
                            <div id="medicamentoAutocomplete" class="autocomplete-list" style="display: none;"></div>
                        </div>
                        <div class="field">
                            <label>Indicación / Posología</label>
                            <input 
                                type="text" 
                                id="recetaIndicacion" 
                                placeholder="Ej: 1 cada 8 horas después de comer"
                            >
                        </div>
                    </div>
                    
                    <button type="button" id="btnAgregarMedicamento" class="btn ghost" style="width: 100%; margin-bottom: 16px;">
                        ➕ Agregar Medicamento a la Receta
                    </button>

                    <div id="medicamentosLista" style="min-height: 60px;">
                        <p style="color: var(--muted); font-size: 14px; padding: 12px; text-align: center;">
                            No se han agregado medicamentos
                        </p>
                    </div>

                    <div class="field" style="margin-top: 16px;">
                        <label>Duración del Tratamiento</label>
                        <input 
                            type="text" 
                            id="recetaDuracion" 
                            placeholder="Ej: 7 días, 2 semanas, 1 mes"
                        >
                        <small style="color: var(--muted); display: block; margin-top: 4px;">
                            Indique por cuánto tiempo debe seguirse el tratamiento
                        </small>
                    </div>
                </div>

                <div class="section-divider"></div>

                <div style="display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap;">
                    <button type="submit" class="btn primary" style="flex: 1; min-width: 200px;">
                        ✅ Guardar Consulta y Marcar como Atendido
                    </button>
                    <button type="button" id="btnCerrarModal" class="btn ghost">
                        ❌ Cancelar
                    </button>
                </div>

                <div id="msgDiagnostico" class="msg" style="margin-top: 16px;"></div>
            </form>
        </div>
    </div>

    <script src="../assets/js/medico_panel.js"></script>
</body>
</html>