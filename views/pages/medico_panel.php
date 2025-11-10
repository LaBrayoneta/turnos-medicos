<?php
// views/pages/medico_panel.php - PANEL MÉDICO COMPLETO
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

// Obtener ID del médico
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
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Estilos específicos del panel médico */
        .turno-card {
            background: linear-gradient(145deg, #1a2332 0%, #0f172a 100%);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s;
        }
        
        .turno-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .turno-card.atendido {
            opacity: 0.7;
            border-color: var(--ok);
        }
        
        .turno-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .paciente-info {
            flex: 1;
        }
        
        .paciente-nombre {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }
        
        .turno-hora {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
        }
        
        .turno-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #1a2332 0%, #0f172a 100%);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
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
        
        .modal-diagnostico {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        
        .modal-content-large {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 16px;
            padding: 32px;
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .form-section {
            margin-bottom: 24px;
            padding: 16px;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        
        .form-section h4 {
            color: var(--primary);
            margin-bottom: 12px;
            font-size: 16px;
        }
        
        textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            background: #0b1220;
            border: 1px solid #1f2937;
            border-radius: 10px;
            color: #e5e7eb;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(34, 211, 238, 0.1);
        }
        
        .medicamento-item {
            background: #0b1220;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .historial-item {
            background: rgba(15, 23, 42, 0.3);
            border-left: 3px solid var(--primary);
            padding: 12px;
            margin-bottom: 12px;
            border-radius: 6px;
        }
        
        .historial-fecha {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 6px;
        }
        
        .historial-contenido {
            color: var(--text);
            font-size: 14px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <header class="hdr">
        <div class="brand">👨‍⚕️ Panel Médico</div>
        <div class="who">Dr. <?= htmlspecialchars($apellido . ', ' . $nombre) ?> - <?= htmlspecialchars($especialidad) ?></div>
        <nav class="actions">
            <a class="btn ghost" href="admin.php">📊 Panel Admin</a>
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
                <input type="date" id="filtroFecha" style="padding: 10px;">
            </div>
            <div id="turnosProximosContainer"></div>
        </section>

        <!-- Tab: Historial -->
        <section id="tab-historial" class="card hidden">
            <h2>📋 Historial de Atenciones</h2>
            <div class="grid" style="grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px;">
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
            <div id="historialContainer"></div>
        </section>
    </main>

    <!-- Modal: Atender Paciente / Diagnóstico -->
    <div id="modalDiagnostico" class="modal-diagnostico">
        <div class="modal-content-large">
            <h2>👨‍⚕️ Atender Paciente</h2>
            
            <div style="background: rgba(34, 211, 238, 0.1); padding: 16px; border-radius: 10px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
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
                    <h4>📋 Diagnóstico</h4>
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
                    
                    <div class="grid" style="grid-template-columns: 2fr 1fr auto; gap: 12px; margin-bottom: 12px;">
                        <div class="field">
                            <label>Medicamento</label>
                            <input type="text" id="recetaMedicamento" placeholder="Ej: Ibuprofeno 400mg">
                        </div>
                        <div class="field">
                            <label>Indicación</label>
                            <input type="text" id="recetaIndicacion" placeholder="Ej: 1 cada 8hs">
                        </div>
                        <div style="display: flex; align-items: flex-end;">
                            <button type="button" id="btnAgregarMedicamento" class="btn ghost">➕</button>
                        </div>
                    </div>

                    <div id="medicamentosLista"></div>

                    <div class="field" style="margin-top: 16px;">
                        <label>Duración del tratamiento</label>
                        <input type="text" id="recetaDuracion" placeholder="Ej: 7 días, 2 semanas, etc.">
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn primary">✅ Guardar y Marcar como Atendido</button>
                    <button type="button" id="btnCerrarModal" class="btn ghost">❌ Cancelar</button>
                </div>

                <div id="msgDiagnostico" class="msg" style="margin-top: 12px;"></div>
            </form>
        </div>
    </div>

    <script>
        const medicoId = <?= $medicoId ?>;
        const csrf = '<?= $csrf ?>';
        
        // Medicamentos temporales
        let medicamentos = [];
        
        // Formatear hora a 12h con AM/PM
        function formatHour12(time24) {
            if (!time24) return '';
            const [hours, minutes] = time24.split(':');
            let h = parseInt(hours);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${minutes} ${ampm}`;
        }
        
        // Formatear fecha
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('es-AR', options);
        }
        
        // Tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('section.card').forEach(s => s.classList.add('hidden'));
                
                tab.classList.add('active');
                document.getElementById('tab-' + tab.dataset.tab).classList.remove('hidden');
                
                if (tab.dataset.tab === 'hoy') loadTurnosHoy();
                if (tab.dataset.tab === 'proximos') loadTurnosProximos();
                if (tab.dataset.tab === 'historial') loadHistorial();
            });
        });
        
        // Cargar estadísticas
        async function loadStats() {
            try {
                const res = await fetch(`medico_api.php?action=stats&medico_id=${medicoId}`);
                const data = await res.json();
                if (data.ok) {
                    document.getElementById('statHoy').textContent = data.stats.hoy || 0;
                    document.getElementById('statPendientes').textContent = data.stats.pendientes || 0;
                    document.getElementById('statAtendidos').textContent = data.stats.atendidos || 0;
                    document.getElementById('statSemana').textContent = data.stats.semana || 0;
                }
            } catch (e) {
                console.error('Error loading stats:', e);
            }
        }
        
        // Cargar turnos de hoy
        async function loadTurnosHoy() {
            const container = document.getElementById('turnosHoyContainer');
            container.innerHTML = '<p style="text-align:center;padding:20px;">⏳ Cargando...</p>';
            
            try {
                const res = await fetch(`medico_api.php?action=turnos_hoy&medico_id=${medicoId}`);
                const data = await res.json();
                
                if (data.ok) {
                    renderTurnos(data.turnos, container);
                } else {
                    container.innerHTML = `<p style="color:var(--err)">❌ ${data.error}</p>`;
                }
            } catch (e) {
                console.error('Error:', e);
                container.innerHTML = '<p style="color:var(--err)">❌ Error al cargar turnos</p>';
            }
        }
        
        // Cargar próximos turnos
        async function loadTurnosProximos(fecha = null) {
            const container = document.getElementById('turnosProximosContainer');
            container.innerHTML = '<p style="text-align:center;padding:20px;">⏳ Cargando...</p>';
            
            try {
                let url = `medico_api.php?action=turnos_proximos&medico_id=${medicoId}`;
                if (fecha) url += `&fecha=${fecha}`;
                
                const res = await fetch(url);
                const data = await res.json();
                
                if (data.ok) {
                    renderTurnos(data.turnos, container);
                } else {
                    container.innerHTML = `<p style="color:var(--err)">❌ ${data.error}</p>`;
                }
            } catch (e) {
                console.error('Error:', e);
                container.innerHTML = '<p style="color:var(--err)">❌ Error al cargar turnos</p>';
            }
        }
        
        // Renderizar turnos
        function renderTurnos(turnos, container) {
            if (!turnos || turnos.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📅</div>
                        <p>No hay turnos para mostrar</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = '';
            
            turnos.forEach(turno => {
                const card = document.createElement('div');
                card.className = 'turno-card' + (turno.atendido ? ' atendido' : '');
                
                const [fecha, hora] = turno.fecha.split(' ');
                const hora12 = formatHour12(hora);
                const fechaDisplay = formatDate(fecha);
                
                card.innerHTML = `
                    <div class="turno-header">
                        <div class="paciente-info">
                            <div class="paciente-nombre">${turno.paciente_nombre}</div>
                            <small style="color:var(--muted)">
                                DNI: ${turno.paciente_dni} • ${turno.obra_social || 'Sin obra social'}
                            </small>
                        </div>
                        <div style="text-align:right;">
                            <div class="turno-hora">${hora12}</div>
                            <small style="color:var(--muted)">${fechaDisplay}</small>
                        </div>
                    </div>
                    
                    ${turno.atendido ? `
                        <div style="background: rgba(16, 185, 129, 0.1); padding: 12px; border-radius: 8px; border-left: 3px solid var(--ok);">
                            <strong style="color:var(--ok)">✅ Atendido</strong>
                            <div style="color:var(--muted);font-size:13px;margin-top:4px;">
                                ${turno.fecha_atencion ? formatDate(turno.fecha_atencion.split(' ')[0]) : ''}
                            </div>
                        </div>
                    ` : `
                        <div class="turno-actions">
                            <button class="btn primary btn-atender" data-turno='${JSON.stringify(turno)}'>
                                👨‍⚕️ Atender
                            </button>
                            <button class="btn ghost btn-ver-historia" data-paciente="${turno.paciente_id}">
                                📋 Ver Historia
                            </button>
                        </div>
                    `}
                `;
                
                container.appendChild(card);
            });
            
            // Event listeners
            document.querySelectorAll('.btn-atender').forEach(btn => {
                btn.addEventListener('click', () => {
                    const turno = JSON.parse(btn.dataset.turno);
                    openModalDiagnostico(turno);
                });
            });
            
            document.querySelectorAll('.btn-ver-historia').forEach(btn => {
                btn.addEventListener('click', () => {
                    alert('Función de historia clínica en desarrollo');
                });
            });
        }
        
        // Abrir modal de diagnóstico
        function openModalDiagnostico(turno) {
            const [fecha, hora] = turno.fecha.split(' ');
            const hora12 = formatHour12(hora);
            const fechaDisplay = formatDate(fecha);
            
            document.getElementById('diagTurnoId').value = turno.id;
            document.getElementById('diagPacienteId').value = turno.paciente_id;
            document.getElementById('modalPacienteNombre').textContent = turno.paciente_nombre;
            document.getElementById('modalPacienteDNI').textContent = 'DNI: ' + turno.paciente_dni;
            document.getElementById('modalPacienteObra').textContent = turno.obra_social || 'Sin obra social';
            document.getElementById('modalTurnoHora').textContent = hora12;
            document.getElementById('modalTurnoFecha').textContent = fechaDisplay;
            
            // Limpiar form
            document.getElementById('diagSintomas').value = '';
            document.getElementById('diagDiagnostico').value = '';
            document.getElementById('diagObservaciones').value = '';
            document.getElementById('recetaMedicamento').value = '';
            document.getElementById('recetaIndicacion').value = '';
            document.getElementById('recetaDuracion').value = '';
            medicamentos = [];
            renderMedicamentos();
            
            document.getElementById('modalDiagnostico').style.display = 'flex';
        }
        
        // Cerrar modal
        document.getElementById('btnCerrarModal').addEventListener('click', () => {
            document.getElementById('modalDiagnostico').style.display = 'none';
        });
        
        // Agregar medicamento
        document.getElementById('btnAgregarMedicamento').addEventListener('click', () => {
            const med = document.getElementById('recetaMedicamento').value.trim();
            const ind = document.getElementById('recetaIndicacion').value.trim();
            
            if (!med) {
                alert('Ingrese el nombre del medicamento');
                return;
            }
            
            medicamentos.push({ medicamento: med, indicacion: ind });
            
            document.getElementById('recetaMedicamento').value = '';
            document.getElementById('recetaIndicacion').value = '';
            
            renderMedicamentos();
        });
        
        // Renderizar lista de medicamentos
        function renderMedicamentos() {
            const lista = document.getElementById('medicamentosLista');
            
            if (medicamentos.length === 0) {
                lista.innerHTML = '<p style="color:var(--muted);font-size:14px;padding:12px;">No se agregaron medicamentos</p>';
                return;
            }
            
            lista.innerHTML = '';
            
            medicamentos.forEach((item, index) => {
                const div = document.createElement('div');
                div.className = 'medicamento-item';
                div.innerHTML = `
                    <div>
                        <strong>${item.medicamento}</strong>
                        ${item.indicacion ? `<br><small style="color:var(--muted)">${item.indicacion}</small>` : ''}
                    </div>
                    <button type="button" class="btn danger btn-sm" onclick="removeMedicamento(${index})">
                        🗑️
                    </button>
                `;
                lista.appendChild(div);
            });
        }
        
        // Remover medicamento
        window.removeMedicamento = function(index) {
            medicamentos.splice(index, 1);
            renderMedicamentos();
        };
        
        // Guardar diagnóstico
        document.getElementById('formDiagnostico').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const turnoId = document.getElementById('diagTurnoId').value;
            const pacienteId = document.getElementById('diagPacienteId').value;
            const sintomas = document.getElementById('diagSintomas').value.trim();
            const diagnostico = document.getElementById('diagDiagnostico').value.trim();
            const observaciones = document.getElementById('diagObservaciones').value.trim();
            const duracion = document.getElementById('recetaDuracion').value.trim();
            
            if (!diagnostico) {
                alert('El diagnóstico es obligatorio');
                return;
            }
            
            const msgEl = document.getElementById('msgDiagnostico');
            msgEl.textContent = '⏳ Guardando...';
            msgEl.className = 'msg';
            
            try {
                const fd = new FormData();
                fd.append('action', 'guardar_diagnostico');
                fd.append('csrf_token', csrf);
                fd.append('turno_id', turnoId);
                fd.append('paciente_id', pacienteId);
                fd.append('medico_id', medicoId);
                fd.append('sintomas', sintomas);
                fd.append('diagnostico', diagnostico);
                fd.append('observaciones', observaciones);
                fd.append('medicamentos', JSON.stringify(medicamentos));
                fd.append('duracion_tratamiento', duracion);
                
                const res = await fetch('medico_api.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.ok) {
                    msgEl.textContent = '✅ ' + (data.mensaje || 'Guardado exitosamente');
                    msgEl.className = 'msg ok';
                    
                    setTimeout(() => {
                        document.getElementById('modalDiagnostico').style.display = 'none';
                        loadTurnosHoy();
                        loadStats();
                    }, 1500);
                } else {
                    msgEl.textContent = '❌ ' + (data.error || 'Error');
                    msgEl.className = 'msg err';
                }
            } catch (e) {
                console.error('Error:', e);
                msgEl.textContent = '❌ Error al guardar';
                msgEl.className = 'msg err';
            }
        });
        
        // Filtro de fecha en próximos turnos
        document.getElementById('filtroFecha').addEventListener('change', (e) => {
            loadTurnosProximos(e.target.value);
        });
        
        // Buscar historial
        document.getElementById('btnBuscarHistorial').addEventListener('click', loadHistorial);
        
        // Cargar historial
        async function loadHistorial() {
            const container = document.getElementById('historialContainer');
            container.innerHTML = '<p style="text-align:center;padding:20px;">⏳ Cargando...</p>';
            
            const desde = document.getElementById('historialDesde').value;
            const hasta = document.getElementById('historialHasta').value;
            
            try {
                let url = `medico_api.php?action=historial&medico_id=${medicoId}`;
                if (desde) url += `&desde=${desde}`;
                if (hasta) url += `&hasta=${hasta}`;
                
                const res = await fetch(url);
                const data = await res.json();
                
                if (data.ok) {
                    if (data.historial.length === 0) {
                        container.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-icon">📋</div>
                                <p>No hay atenciones en este período</p>
                            </div>
                        `;
                    } else {
                        container.innerHTML = '';
                        data.historial.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'historial-item';
                            div.innerHTML = `
                                <div class="historial-fecha">
                                    📅 ${formatDate(item.fecha.split(' ')[0])} • ${formatHour12(item.fecha.split(' ')[1])}
                                </div>
                                <div class="historial-contenido">
                                    <strong>${item.paciente_nombre}</strong>
                                    <div style="margin-top: 8px; color: var(--text);">
                                        <strong>Diagnóstico:</strong> ${item.diagnostico}
                                    </div>
                                    ${item.medicamentos ? `
                                        <div style="margin-top: 8px;">
                                            <strong>Receta:</strong> ${item.medicamentos}
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                            container.appendChild(div);
                        });
                    }
                } else {
                    container.innerHTML = `<p style="color:var(--err)">❌ ${data.error}</p>`;
                }
            } catch (e) {
                console.error('Error:', e);
                container.innerHTML = '<p style="color:var(--err)">❌ Error al cargar historial</p>';
            }
        }
        
        // Cargar datos iniciales
        loadStats();
        loadTurnosHoy();
    </script>
</body>
</html>