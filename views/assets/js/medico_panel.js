// views/assets/js/medico_panel.js - Panel Médico COMPLETO

(function() {
    'use strict';

    const API_URL = '../../controllers/medico_api.php';
    let medicoId = null;
    let csrf = '';
    let medicamentos = [];

    // Inicializar
    function init() {
        // Obtener datos del DOM
        medicoId = parseInt(document.body.dataset.medicoId);
        csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        if (!medicoId) {
            console.error('ID de médico no encontrado');
            return;
        }

        console.log('🚀 Inicializando panel médico ID:', medicoId);

        // Event listeners
        setupTabs();
        setupModal();
        setupMedicamentos();
        setupFiltros();

        // Cargar datos iniciales
        loadStats();
        loadTurnosHoy();
    }

    // Configurar tabs
    function setupTabs() {
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('section.card').forEach(s => s.classList.add('hidden'));

                tab.classList.add('active');
                document.getElementById('tab-' + tab.dataset.tab).classList.remove('hidden');

                if (tab.dataset.tab === 'hoy') loadTurnosHoy();
                if (tab.dataset.tab === 'proximos') loadTurnosProximos();
                if (tab.dataset.tab === 'historial') setupHistorial();
            });
        });
    }

    // Configurar modal
    function setupModal() {
        document.getElementById('btnCerrarModal')?.addEventListener('click', closeModal);

        document.getElementById('formDiagnostico')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            await guardarDiagnostico();
        });
    }

    // Configurar medicamentos
    function setupMedicamentos() {
        document.getElementById('btnAgregarMedicamento')?.addEventListener('click', agregarMedicamento);
    }

    // Configurar filtros
    function setupFiltros() {
        document.getElementById('filtroFecha')?.addEventListener('change', (e) => {
            loadTurnosProximos(e.target.value);
        });

        document.getElementById('btnBuscarHistorial')?.addEventListener('click', loadHistorial);
    }

    // Formatear hora 12h
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
        const date = new Date(dateStr + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('es-AR', options);
    }

    // Cargar estadísticas
    async function loadStats() {
        try {
            const res = await fetch(`${API_URL}?action=stats`);
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
        container.innerHTML = '<p style="text-align:center;padding:20px;color:var(--muted)">⏳ Cargando...</p>';

        try {
            const res = await fetch(`${API_URL}?action=turnos_hoy`);
            const data = await res.json();

            if (data.ok) {
                renderTurnos(data.turnos, container);
            } else {
                container.innerHTML = `<p style="color:var(--err);padding:20px">❌ ${data.error}</p>`;
            }
        } catch (e) {
            console.error('Error:', e);
            container.innerHTML = '<p style="color:var(--err);padding:20px">❌ Error al cargar turnos</p>';
        }
    }

    // Cargar próximos turnos
    async function loadTurnosProximos(fecha = null) {
        const container = document.getElementById('turnosProximosContainer');
        container.innerHTML = '<p style="text-align:center;padding:20px;color:var(--muted)">⏳ Cargando...</p>';

        try {
            let url = `${API_URL}?action=turnos_proximos`;
            if (fecha) url += `&fecha=${fecha}`;

            const res = await fetch(url);
            const data = await res.json();

            if (data.ok) {
                renderTurnos(data.turnos, container);
            } else {
                container.innerHTML = `<p style="color:var(--err);padding:20px">❌ ${data.error}</p>`;
            }
        } catch (e) {
            console.error('Error:', e);
            container.innerHTML = '<p style="color:var(--err);padding:20px">❌ Error al cargar turnos</p>';
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
                        <div class="paciente-nombre">${escapeHtml(turno.paciente_nombre)}</div>
                        <small style="color:var(--muted)">
                            DNI: ${escapeHtml(turno.paciente_dni)} • ${escapeHtml(turno.obra_social || 'Sin obra social')}
                        </small>
                        ${turno.libreta ? `<br><small style="color:var(--muted)">Libreta: ${escapeHtml(turno.libreta)}</small>` : ''}
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
    }

    // Abrir modal diagnóstico
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
        document.getElementById('msgDiagnostico').textContent = '';
        
        medicamentos = [];
        renderMedicamentos();

        document.getElementById('modalDiagnostico').style.display = 'flex';
    }

    // Cerrar modal
    function closeModal() {
        document.getElementById('modalDiagnostico').style.display = 'none';
    }

    // Agregar medicamento
    function agregarMedicamento() {
        const med = document.getElementById('recetaMedicamento').value.trim();
        const ind = document.getElementById('recetaIndicacion').value.trim();

        if (!med) {
            alert('⚠️ Ingrese el nombre del medicamento');
            return;
        }

        medicamentos.push({ medicamento: med, indicacion: ind });

        document.getElementById('recetaMedicamento').value = '';
        document.getElementById('recetaIndicacion').value = '';

        renderMedicamentos();
    }

    // Renderizar medicamentos
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
                    <strong>${escapeHtml(item.medicamento)}</strong>
                    ${item.indicacion ? `<br><small style="color:var(--muted)">${escapeHtml(item.indicacion)}</small>` : ''}
                </div>
                <button type="button" class="btn danger btn-sm" onclick="window.removeMedicamento(${index})">
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
    async function guardarDiagnostico() {
        const turnoId = document.getElementById('diagTurnoId').value;
        const pacienteId = document.getElementById('diagPacienteId').value;
        const sintomas = document.getElementById('diagSintomas').value.trim();
        const diagnostico = document.getElementById('diagDiagnostico').value.trim();
        const observaciones = document.getElementById('diagObservaciones').value.trim();
        const duracion = document.getElementById('recetaDuracion').value.trim();

        if (!diagnostico) {
            alert('⚠️ El diagnóstico es obligatorio');
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
            fd.append('sintomas', sintomas);
            fd.append('diagnostico', diagnostico);
            fd.append('observaciones', observaciones);
            fd.append('medicamentos', JSON.stringify(medicamentos));
            fd.append('duracion_tratamiento', duracion);

            const res = await fetch(API_URL, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.ok) {
                msgEl.textContent = '✅ ' + (data.mensaje || 'Guardado exitosamente');
                msgEl.className = 'msg ok';

                setTimeout(() => {
                    closeModal();
                    loadTurnosHoy();
                    loadStats();
                }, 1500);
            } else {
                msgEl.textContent = '❌ ' + (data.error || 'Error al guardar');
                msgEl.className = 'msg err';
            }
        } catch (e) {
            console.error('Error:', e);
            msgEl.textContent = '❌ Error al guardar';
            msgEl.className = 'msg err';
        }
    }

    // Configurar historial
    function setupHistorial() {
        const desde = document.getElementById('historialDesde');
        const hasta = document.getElementById('historialHasta');

        // Establecer fechas por defecto (último mes)
        const hoy = new Date();
        const haceUnMes = new Date();
        haceUnMes.setMonth(haceUnMes.getMonth() - 1);

        desde.value = haceUnMes.toISOString().split('T')[0];
        hasta.value = hoy.toISOString().split('T')[0];

        loadHistorial();
    }

    // Cargar historial
    async function loadHistorial() {
        const container = document.getElementById('historialContainer');
        container.innerHTML = '<p style="text-align:center;padding:20px;color:var(--muted)">⏳ Cargando...</p>';

        const desde = document.getElementById('historialDesde').value;
        const hasta = document.getElementById('historialHasta').value;

        try {
            let url = `${API_URL}?action=historial`;
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
                                <strong>${escapeHtml(item.paciente_nombre)}</strong>
                                <div style="margin-top: 8px; color: var(--text);">
                                    <strong>Diagnóstico:</strong> ${escapeHtml(item.diagnostico)}
                                </div>
                                ${item.medicamentos ? `
                                    <div style="margin-top: 8px;">
                                        <strong>Receta:</strong><br>
                                        <div style="white-space: pre-line; margin-top: 4px; padding: 8px; background: rgba(10,14,26,0.4); border-radius: 6px; font-size: 13px;">
                                            ${escapeHtml(item.medicamentos)}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                        container.appendChild(div);
                    });
                }
            } else {
                container.innerHTML = `<p style="color:var(--err);padding:20px">❌ ${data.error}</p>`;
            }
        } catch (e) {
            console.error('Error:', e);
            container.innerHTML = '<p style="color:var(--err);padding:20px">❌ Error al cargar historial</p>';
        }
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();