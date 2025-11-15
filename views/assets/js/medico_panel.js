// views/assets/js/medico_panel.js - PANEL MÉDICO MEJORADO Y COMPLETO

(function() {
    'use strict';

    const API_URL = '../../controllers/medico_api.php';
    let medicoId = null;
    let csrf = '';
    let medicamentos = [];
    let medicamentosDB = []; // Medicamentos de la BD para autocomplete

    // Inicializar
    function init() {
        // Obtener datos del DOM
        medicoId = parseInt(document.body.dataset.medicoId);
        csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        // Cargar medicamentos de la BD
        const medicamentosData = document.querySelector('meta[name="medicamentos-data"]')?.getAttribute('content');
        if (medicamentosData) {
            try {
                medicamentosDB = JSON.parse(medicamentosData);
                console.log('✅ Medicamentos cargados:', medicamentosDB.length);
            } catch (e) {
                console.error('Error parseando medicamentos:', e);
                medicamentosDB = [];
            }
        }

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
        setupAutocomplete();

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

    // Configurar autocomplete de medicamentos
    function setupAutocomplete() {
        const input = document.getElementById('recetaMedicamento');
        const autocompleteDiv = document.getElementById('medicamentoAutocomplete');
        
        if (!input || !autocompleteDiv) return;

        input.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            
            if (query.length < 2) {
                autocompleteDiv.style.display = 'none';
                return;
            }

            const filtered = medicamentosDB.filter(med => 
                med.nombre.toLowerCase().includes(query)
            );

            if (filtered.length === 0) {
                autocompleteDiv.style.display = 'none';
                return;
            }

            autocompleteDiv.innerHTML = '';
            filtered.slice(0, 8).forEach(med => {
                const item = document.createElement('div');
                item.className = 'autocomplete-item';
                item.innerHTML = `
                    <strong>${escapeHtml(med.nombre)}</strong>
                    ${med.presentacion ? `<small>${escapeHtml(med.presentacion)}</small>` : ''}
                    ${med.dosis_usual ? `<small style="color: var(--primary);">Dosis usual: ${escapeHtml(med.dosis_usual)}</small>` : ''}
                `;
                
                item.addEventListener('click', () => {
                    input.value = med.nombre;
                    if (med.dosis_usual) {
                        document.getElementById('recetaIndicacion').value = med.dosis_usual;
                    }
                    autocompleteDiv.style.display = 'none';
                });
                
                autocompleteDiv.appendChild(item);
            });

            autocompleteDiv.style.display = 'block';
        });

        // Cerrar autocomplete al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !autocompleteDiv.contains(e.target)) {
                autocompleteDiv.style.display = 'none';
            }
        });
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

    // Formatear fecha completa
    function formatDate(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('es-AR', options);
    }

    // Formatear fecha corta
    function formatDateShort(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        return date.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric' });
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
                        <small style="color:var(--muted); display: block; margin-top: 4px;">
                            <span style="display: inline-flex; align-items: center; gap: 4px;">
                                📋 DNI: ${escapeHtml(turno.paciente_dni)}
                            </span>
                            <span style="margin: 0 8px;">•</span>
                            <span style="display: inline-flex; align-items: center; gap: 4px;">
                                🏥 ${escapeHtml(turno.obra_social || 'Sin obra social')}
                            </span>
                        </small>
                        ${turno.libreta ? `
                            <small style="color:var(--muted); display: block; margin-top: 4px;">
                                📖 Libreta: ${escapeHtml(turno.libreta)}
                            </small>
                        ` : ''}
                    </div>
                    <div style="text-align:right;">
                        <div class="turno-hora">${hora12}</div>
                        <small style="color:var(--muted); display: block; margin-top: 4px;">${fechaDisplay}</small>
                    </div>
                </div>

                ${turno.atendido ? `
                    <div style="background: rgba(16, 185, 129, 0.1); padding: 12px; border-radius: 8px; border-left: 3px solid var(--ok); margin-top: 12px;">
                        <strong style="color:var(--ok); display: flex; align-items: center; gap: 6px;">
                            ✅ Paciente Atendido
                        </strong>
                        ${turno.fecha_atencion ? `
                            <div style="color:var(--muted);font-size:13px;margin-top:4px;">
                                Fecha de atención: ${formatDate(turno.fecha_atencion.split(' ')[0])}
                            </div>
                        ` : ''}
                    </div>
                ` : `
                    <div class="turno-actions">
                        <button class="btn primary btn-atender" data-turno='${JSON.stringify(turno).replace(/'/g, "&#39;")}'>
                            👨‍⚕️ Atender Paciente
                        </button>
                    </div>
                `}
            `;

            container.appendChild(card);
        });

        // Event listeners
        document.querySelectorAll('.btn-atender').forEach(btn => {
            btn.addEventListener('click', () => {
                const turno = JSON.parse(btn.dataset.turno.replace(/&#39;/g, "'"));
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
        document.getElementById('modalPacienteLibreta').textContent = turno.libreta ? 'Libreta: ' + turno.libreta : 'Sin libreta';
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
        
        // Mensaje de confirmación
        const msgEl = document.getElementById('msgDiagnostico');
        msgEl.textContent = '✅ Medicamento agregado a la receta';
        msgEl.className = 'msg ok';
        setTimeout(() => {
            msgEl.textContent = '';
        }, 2000);
    }

    // Renderizar medicamentos
    function renderMedicamentos() {
        const lista = document.getElementById('medicamentosLista');

        if (medicamentos.length === 0) {
            lista.innerHTML = '<p style="color:var(--muted);font-size:14px;padding:12px;text-align:center;">💊 No se han agregado medicamentos a la receta</p>';
            return;
        }

        lista.innerHTML = '';

        medicamentos.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'medicamento-item';
            div.innerHTML = `
                <div class="medicamento-info">
                    <div class="medicamento-nombre">${escapeHtml(item.medicamento)}</div>
                    ${item.indicacion ? `<div class="medicamento-indicacion">${escapeHtml(item.indicacion)}</div>` : ''}
                </div>
                <button type="button" class="btn danger btn-sm" onclick="window.removeMedicamento(${index})" title="Eliminar medicamento">
                    🗑️
                </button>
            `;
            lista.appendChild(div);
        });
    }

    // Remover medicamento
    window.removeMedicamento = function(index) {
        if (confirm('¿Eliminar este medicamento de la receta?')) {
            medicamentos.splice(index, 1);
            renderMedicamentos();
        }
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
            document.getElementById('diagDiagnostico').focus();
            return;
        }

        const msgEl = document.getElementById('msgDiagnostico');
        msgEl.textContent = '⏳ Guardando consulta...';
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
                msgEl.textContent = '✅ ' + (data.mensaje || 'Consulta guardada exitosamente');
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
            msgEl.textContent = '❌ Error al guardar la consulta';
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
        container.innerHTML = '<p style="text-align:center;padding:20px;color:var(--muted)">⏳ Cargando historial...</p>';

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
                            <p>No hay consultas registradas en este período</p>
                        </div>
                    `;
                } else {
                    renderHistorial(data.historial, container);
                }
            } else {
                container.innerHTML = `<p style="color:var(--err);padding:20px">❌ ${data.error}</p>`;
            }
        } catch (e) {
            console.error('Error:', e);
            container.innerHTML = '<p style="color:var(--err);padding:20px">❌ Error al cargar historial</p>';
        }
    }

    // Renderizar historial
    function renderHistorial(items, container) {
        container.innerHTML = '';
        
        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'historial-item';
            
            const [fechaPart, horaPart] = item.fecha.split(' ');
            const fechaFormat = formatDate(fechaPart);
            const horaFormat = formatHour12(horaPart);
            
            div.innerHTML = `
                <div class="historial-header">
                    <div>
                        <div class="historial-paciente">${escapeHtml(item.paciente_nombre)}</div>
                        <div class="historial-fecha">
                            ${fechaFormat} • ${horaFormat}
                        </div>
                    </div>
                </div>
                
                <div class="historial-section">
                    <div class="historial-section-title">📋 Diagnóstico</div>
                    <div class="historial-section-content">${escapeHtml(item.diagnostico)}</div>
                </div>
                
                ${item.medicamentos ? `
                    <div class="historial-section">
                        <div class="historial-section-title">💊 Medicación Recetada</div>
                        <div class="historial-medicamentos">
                            <pre>${escapeHtml(item.medicamentos)}</pre>
                        </div>
                    </div>
                ` : ''}
            `;
            
            container.appendChild(div);
        });
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