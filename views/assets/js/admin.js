// admin.js - Panel Administrativo (VERSIÓN COMPLETAMENTE CORREGIDA)
// ✅ Soluciona el problema de congelamiento de pantalla
// Función para obtener nombre del día
function getDayNameES(dateStr) {
  const dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
  const date = new Date(dateStr + 'T00:00:00');
  return dias[date.getDay()];
}

// ✅ MODIFICAR LA SECCIÓN DE CAMBIO DE FECHA (turnoDate change event)
// BUSCAR ESTA PARTE en admin.js y AGREGAR la validación:

turnoDate?.addEventListener('change', async ()=>{
    const turnoTimeSelect = document.getElementById('turnoTime');
    const medicoIdInput = document.getElementById('turnoMedicoId');
    
    if (!turnoTimeSelect) return;
    
    if (turnoDate.value) {
      const validation = Utils.isValidTurnoDate(turnoDate.value);
      if (!validation.valid) {
        setMsg(msgModal, '❌ ' + validation.error, false);
        turnoDate.value = '';
        return;
      }
      
      // ✅ NUEVA VALIDACIÓN: Verificar que sea día laboral del médico
      const medicoId = medicoIdInput?.value;
      if (medicoId && window.currentMedicoDiasDisponibles) {
        const diaSemana = getDayNameES(turnoDate.value);
        const diasDisponibles = window.currentMedicoDiasDisponibles;
        
        if (!diasDisponibles.includes(diaSemana)) {
          const diasCapitalizados = diasDisponibles.map(d => 
            d.charAt(0).toUpperCase() + d.slice(1)
          ).join(', ');
          
          alert(`⚠️ EL MÉDICO NO ATIENDE LOS ${diaSemana.toUpperCase()}S\n\nDías disponibles: ${diasCapitalizados}`);
          turnoDate.value = '';
          turnoTimeSelect.innerHTML = '<option value="">Elegí un día válido...</option>';
          return;
        }
      }
    }
    
    turnoTimeSelect.innerHTML = `<option value="">Cargando…</option>`;
    turnoTimeSelect.disabled = true;

    });

(function(){
  'use strict';
  
  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const csrf = $('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const Utils = window.TurnosUtils;

  // Estado global
  let especialidades = [];
  let medicosData = [];
  let secretariasData = [];
  let obrasSocialesData = [];
  let selectedTurnoId = null;
  let currentMedicoId = null;
  let isLoading = false; // ✅ Prevenir carga múltiple

  // Elementos DOM principales
  const createSecretariaForm = $('#createSecretariaForm');
  const createObraForm = $('#createObraForm');
  const tblMedicos = $('#tblMedicos');
  const tblSecretarias = $('#tblSecretarias');
  const tblObras = $('#tblObras');
  const tblAgendaBody = $('#tblAgenda tbody');
  const noData = $('#noData');
  
  // Mensajes
  const msgCreateSec = $('#msgCreateSec');
  const msgCreateObra = $('#msgCreateObra');
  const msgTurns = $('#msgTurns');
  const msgModal = $('#msgModal');

  // Filtros turnos
  const fEsp = $('#fEsp');
  const fMed = $('#fMed');
  const fFrom = $('#fFrom');
  const fTo = $('#fTo');
  const btnRefresh = $('#btnRefresh');
  const btnClearDates = $('#btnClearDates');
  const btnNewTurno = $('#btnNewTurno');

  // Reprogramación
  const reprogSection = $('#reprogSection');
  const newDate = $('#newDate');
  const newTime = $('#newTime');
  const btnReprog = $('#btnReprog');
  const btnCancelReprog = $('#btnCancelReprog');

  // Modales
  const modalCreateTurno = $('#modalCreateTurno');
  const formCreateTurno = $('#formCreateTurno');
  const searchPaciente = $('#searchPaciente');
  const pacienteResults = $('#pacienteResults');
  const selectedPacienteId = $('#selectedPacienteId');
  const selectedPacienteInfo = $('#selectedPacienteInfo');
  const turnoDate = $('#turnoDate');
  const turnoTime = $('#turnoTime');
  const btnCloseModal = $('#btnCloseModal');

  const modalEditMedico = $('#modalEditMedico');
  const formEditMedico = $('#formEditMedico');
  const btnCloseMedicoModal = $('#btnCloseMedicoModal');

  const modalEditSecretaria = $('#modalEditSecretaria');
  const formEditSecretaria = $('#formEditSecretaria');
  const btnCloseSecretariaModal = $('#btnCloseSecretariaModal');

  // ========== UTILIDADES ==========
  function esc(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  
  function setMsg(el, t, ok=true){
    if(!el) return;
    el.textContent=t||'';
    el.classList.remove('ok','err');
    el.classList.add(ok?'ok':'err');
  }
  
  function showModal(modal){ if(modal) modal.style.display='flex'; }
  function hideModal(modal){ if(modal) modal.style.display='none'; }

  // ========== CONFIGURAR INPUTS DE FECHA ==========
  function setupDateInputs() {
    console.log('🔧 Configurando inputs de fecha...');
    
    if (fFrom) Utils.setupDateInput(fFrom);
    if (fTo) Utils.setupDateInput(fTo);
    if (newDate) Utils.setupDateInput(newDate);
    if (turnoDate) Utils.setupDateInput(turnoDate);
    
    console.log('✅ Inputs configurados');
  }

  // ========== TABS ==========
  $$('.tab').forEach(t=>{
    t.addEventListener('click', ()=>{
      $$('section.card').forEach(sec=>sec.classList.add('hidden'));
      $$('.tab').forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      $('#tab-'+t.dataset.tab).classList.remove('hidden');
    });
  });

  // ========== CARGA INICIAL ==========
  async function loadInit(){
    if (isLoading) {
      console.log('⏭️ Ya hay una carga en proceso');
      return;
    }
    
    isLoading = true;
    console.log('🔄 Iniciando carga de datos...');
    
    try {
      const res = await fetch('admin.php?fetch=init', { 
        headers:{ 'Accept':'application/json' },
        signal: AbortSignal.timeout(10000) // ✅ Timeout de 10 segundos
      });
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      
      const data = await res.json();
      
      if (!data.ok) { 
        throw new Error(data.error || 'Error desconocido'); 
      }

      especialidades = data.especialidades || [];
      medicosData = data.medicos || [];
      secretariasData = data.secretarias || [];
      obrasSocialesData = data.obras_sociales || [];

      console.log('✅ Datos cargados:', {
        especialidades: especialidades.length,
        medicos: medicosData.length,
        secretarias: secretariasData.length,
        obras: obrasSocialesData.length
      });

      // Cargar especialidades en los selects
      const espSelects = ['#espCreateSelect', '#fEsp', '#editMedEsp'];
      espSelects.forEach(sel => {
        const element = $(sel);
        if (element) {
          element.innerHTML = `<option value="">Elegir…</option>`;
          especialidades.forEach(e=>{
            const opt = document.createElement('option');
            opt.value = e.Id_Especialidad;
            opt.textContent = e.nombre || e.Nombre || 'Sin nombre';
            element.appendChild(opt);
          });
        }
      });

      renderMedicos(medicosData);
      renderSecretarias(secretariasData);
      renderObras(obrasSocialesData);
      setupDateInputs();
      
      console.log('✅ Inicialización completa');
      
    } catch (e) {
      console.error('❌ Error en loadInit:', e);
      alert('Error al cargar datos: ' + e.message);
    } finally {
      isLoading = false;
    }
  }

  // ========== OBRAS SOCIALES ==========
  function renderObras(rows){
    if(!tblObras) return;
    tblObras.innerHTML='';
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      const nombreObra = r.nombre || r.Nombre || '';
      const activo = r.activo !== undefined ? r.activo : (r.Activo !== undefined ? r.Activo : false);
      const estadoTexto = activo ? 'Activa' : 'Inactiva';
      const badgeClass = activo ? 'ok' : 'warn';
      
      tr.innerHTML = `
        <td>${esc(nombreObra)}</td>
        <td><span class="badge ${badgeClass}">${estadoTexto}</span></td>
        <td class="row-actions">
          <button class="btn ghost btn-toggle-obra" data-id="${r.Id_obra_social}">
            ${activo ? '❌ Desactivar' : '✅ Activar'}
          </button>
          <button class="btn danger btn-delete-obra" data-id="${r.Id_obra_social}">🗑️ Eliminar</button>
        </td>`;
      tblObras.appendChild(tr);
    });

    $$('.btn-toggle-obra').forEach(b=>b.addEventListener('click', ()=> toggleObra(b.dataset.id)));
    $$('.btn-delete-obra').forEach(b=>b.addEventListener('click', ()=> deleteObra(b.dataset.id)));
  }

  if (createObraForm) {
    createObraForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      setMsg(msgCreateObra, '');
      try{
        const fd = new FormData(createObraForm);
        fd.set('action','create_obra_social');
        fd.set('csrf_token', csrf);

        const res = await fetch('admin.php', { 
          method:'POST', 
          body:fd, 
          headers:{ 'Accept':'application/json' }
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Error');
        setMsg(msgCreateObra, data.msg || 'Obra social creada', true);
        createObraForm.reset();
        await loadInit();
      }catch(err){
        setMsg(msgCreateObra, err.message || 'Error', false);
      }
    });
  }

  async function toggleObra(id){
    try{
      const fd = new FormData();
      fd.append('action', 'toggle_obra_social');
      fd.append('id_obra_social', id);
      fd.append('csrf_token', csrf);

      const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      setMsg(msgCreateObra, data.msg || 'Estado actualizado', true);
      await loadInit();
    }catch(err){
      setMsg(msgCreateObra, err.message, false);
    }
  }

  async function deleteObra(id){
    if (!confirm('¿Eliminar esta obra social? Esta acción no se puede deshacer.')) return;
    try{
      const fd = new FormData();
      fd.append('action', 'delete_obra_social');
      fd.append('id_obra_social', id);
      fd.append('csrf_token', csrf);

      const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      setMsg(msgCreateObra, data.msg || 'Obra social eliminada', true);
      await loadInit();
    }catch(err){
      setMsg(msgCreateObra, err.message, false);
    }
  }

  // ========== MÉDICOS ==========
  function renderMedicos(rows){
    if(!tblMedicos) return;
    tblMedicos.innerHTML='';
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      
      const apellido = r.apellido || r.Apellido || '';
      const nombre = r.nombre || r.Nombre || '';
      const dni = r.dni || '';
      const especialidad = r.Especialidad || '';
      const legajo = r.legajo || r.Legajo || '';
      
      let horariosHTML = '<div style="display:flex;flex-direction:column;gap:4px">';
      if (r.horarios && r.horarios.length > 0) {
        const horariosPorDia = {};
        r.horarios.forEach(h => {
          const diaSemana = h.dia_semana || h.Dia_semana;
          if (!horariosPorDia[diaSemana]) horariosPorDia[diaSemana] = [];
          
          const horaInicio = (h.hora_inicio || h.Hora_inicio || '').substring(0,5);
          const horaFin = (h.hora_fin || h.Hora_fin || '').substring(0,5);
          
          horariosPorDia[diaSemana].push({
            inicio: horaInicio,
            fin: horaFin
          });
        });
        
        Object.entries(horariosPorDia).forEach(([dia, horarios]) => {
          const diaCapital = dia.charAt(0).toUpperCase() + dia.slice(1);
          const horariosStr = horarios.map(h => 
            `${Utils.formatHour12(h.inicio)}-${Utils.formatHour12(h.fin)}`
          ).join(', ');
          horariosHTML += `<span style="font-size:12px;color:var(--muted)">
            <strong style="color:var(--primary)">${diaCapital}:</strong> ${horariosStr}
          </span>`;
        });
      } else {
        horariosHTML += '<span style="color:var(--err);font-size:12px">⚠️ Sin horarios</span>';
      }
      horariosHTML += '</div>';
      
      tr.innerHTML = `
        <td>${esc(apellido + ', ' + nombre)}</td>
        <td>${esc(dni)}</td>
        <td>${esc(especialidad)}</td>
        <td>${esc(legajo)}</td>
        <td>${horariosHTML}</td>
        <td class="row-actions">
          <button class="btn ghost btn-edit-med" data-id="${r.Id_medico}">✏️ Editar</button>
          <button class="btn danger btn-delete-med" data-id="${r.Id_medico}">🗑️ Eliminar</button>
        </td>`;
      tblMedicos.appendChild(tr);
    });

    $$('.btn-edit-med').forEach(b => {
      b.addEventListener('click', ()=> openEditMedico(b.dataset.id));
    });
    
    $$('.btn-delete-med').forEach(b => {
      b.addEventListener('click', () => {
        if (window.deleteMedico) {
          window.deleteMedico(b.dataset.id);
        } else {
          console.error('❌ Función deleteMedico no encontrada');
          alert('Error: Función de eliminación no disponible. Recarga la página.');
        }
      });
    });
  }

  function openEditMedico(id){
    const medico = medicosData.find(m => m.Id_medico == id);
    if (!medico) {alert('Médico no encontrado'); return;}

    $('#editMedId').value = medico.Id_medico;
    $('#editMedNombre').value = medico.nombre || medico.Nombre || '';
    $('#editMedApellido').value = medico.apellido || medico.Apellido || '';
    $('#editMedEmail').value = medico.email || '';
    $('#editMedLegajo').value = medico.legajo || medico.Legajo || '';
    $('#editMedEsp').value = medico.Id_Especialidad || '';

    if (window.loadMedicoHorarios) {
      window.loadMedicoHorarios(medico.horarios || []);
    }

    showModal(modalEditMedico);
  }

  // ========== SECRETARIAS ==========
  function renderSecretarias(rows){
    if(!tblSecretarias) return;
    tblSecretarias.innerHTML='';
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      
      const apellido = r.apellido || r.Apellido || '';
      const nombre = r.nombre || r.Nombre || '';
      const dni = r.dni || '';
      const email = r.email || '';
      
      tr.innerHTML = `
        <td>${esc(apellido + ', ' + nombre)}</td>
        <td>${esc(dni)}</td>
        <td>${esc(email)}</td>
        <td class="row-actions">
          <button class="btn ghost btn-edit-sec" data-id="${r.Id_secretaria}">✏️ Editar</button>
          <button class="btn danger btn-delete-sec" data-id="${r.Id_secretaria}">🗑️ Eliminar</button>
        </td>`;
      tblSecretarias.appendChild(tr);
    });

    $$('.btn-edit-sec').forEach(b => {
      b.addEventListener('click', ()=> openEditSecretaria(b.dataset.id));
    });
    
    $$('.btn-delete-sec').forEach(b => {
      b.addEventListener('click', () => {
        if (window.deleteSecretaria) {
          window.deleteSecretaria(b.dataset.id);
        } else {
          console.error('❌ Función deleteSecretaria no encontrada');
          alert('Error: Función de eliminación no disponible. Recarga la página.');
        }
      });
    });
  }

  if (createSecretariaForm) {
    createSecretariaForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      setMsg(msgCreateSec, '');

      const fd = new FormData(createSecretariaForm);
      fd.append('action', 'create_secretaria');
      fd.append('csrf_token', csrf);

      try {
        const res = await fetch('admin.php', { method: 'POST', body: fd, headers:{ 'Accept':'application/json' }});
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'No se pudo crear');
        setMsg(msgCreateSec, data.msg || 'Secretaria creada', true);
        createSecretariaForm.reset();
        await loadInit();
      } catch (err) {
        setMsg(msgCreateSec, err.message, false);
      }
    });
  }

  function openEditSecretaria(id){
    const sec = secretariasData.find(s => s.Id_secretaria == id);
    if (!sec) return;

    $('#editSecId').value = sec.Id_secretaria;
    $('#editSecNombre').value = sec.nombre || sec.Nombre || '';
    $('#editSecApellido').value = sec.apellido || sec.Apellido || '';
    $('#editSecEmail').value = sec.email || '';

    showModal(modalEditSecretaria);
  }

  if (formEditSecretaria) {
    formEditSecretaria.addEventListener('submit', async (ev)=>{
      ev.preventDefault();
      const msgEl = $('#msgSecretariaModal');
      setMsg(msgEl, '');
      try{
        const fd = new FormData();
        fd.append('action', 'update_secretaria');
        fd.append('csrf_token', csrf);
        fd.append('id_secretaria', $('#editSecId').value);
        fd.append('nombre', $('#editSecNombre').value);
        fd.append('apellido', $('#editSecApellido').value);
        fd.append('email', $('#editSecEmail').value);

        const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Error');
        
        setMsg(msgEl, data.msg || 'Secretaria actualizada', true);
        setTimeout(()=>{ hideModal(modalEditSecretaria); loadInit(); }, 1000);
      }catch(err){
        setMsg(msgEl, err.message, false);
      }
    });
  }

  // ========== TURNOS ==========
  
  async function loadDocs(espId){
    if(!fMed) return;
    fMed.innerHTML = `<option value="">Cargando…</option>`;
    fMed.disabled=true;
    if(btnNewTurno) btnNewTurno.disabled=true;
    try {
      const r = await fetch(`admin.php?fetch=doctors&especialidad_id=${encodeURIComponent(espId)}`, { 
        headers:{ 'Accept':'application/json' }
      });
      const data = await r.json();
      if(!data.ok) { setMsg(msgTurns, data.error||'Error', false); return; }
      fMed.innerHTML = `<option value="">Elegí médico…</option>`;
      (data.items||[]).forEach(m=>{
        const opt = document.createElement('option');
        opt.value = m.Id_medico;
        const apellido = m.apellido || m.Apellido || '';
        const nombre = m.nombre || m.Nombre || '';
        opt.textContent = `${apellido}, ${nombre}`;
        fMed.appendChild(opt);
      });
      fMed.disabled = false;
    } catch (e) {
      setMsg(msgTurns, 'Error', false);
      console.error(e);
    }
  }

  async function loadAgenda(){
    setMsg(msgTurns, '');
    if(tblAgendaBody) tblAgendaBody.innerHTML='';
    if(noData) noData.style.display='none';
    
    if(!fMed || !fMed.value){
      if(noData) {
        noData.style.display='block';
        noData.textContent='Seleccioná un médico.';
      }
      return;
    }

    if (fFrom.value) {
      const validation = Utils.isValidTurnoDate(fFrom.value);
      if (!validation.valid) {
        setMsg(msgTurns, '❌ Fecha desde inválida: ' + validation.error, false);
        fFrom.value = '';
        return;
      }
    }

    if (fTo.value) {
      const validation = Utils.isValidTurnoDate(fTo.value);
      if (!validation.valid) {
        setMsg(msgTurns, '❌ Fecha hasta inválida: ' + validation.error, false);
        fTo.value = '';
        return;
      }
    }

    currentMedicoId = fMed.value;
    const qs = new URLSearchParams({
      fetch:'agenda',
      medico_id:fMed.value,
      from:(fFrom.value||''),
      to:(fTo.value||'')
    });

    try {
      const r = await fetch(`admin.php?${qs.toString()}`, { headers:{ 'Accept':'application/json' }});
      const data = await r.json();
      if(!data.ok){ setMsg(msgTurns, data.error||'Error', false); return; }
      renderAgenda(data.items||[]);
    } catch (e) {
      setMsg(msgTurns, 'Error', false);
      console.error(e);
    }
  }

  // ========== RENDERIZAR AGENDA (CORREGIDO) ==========
  function renderAgenda(rows){
    if(!tblAgendaBody) return;
    tblAgendaBody.innerHTML='';
    if (!rows.length){
      if(noData) {
        noData.style.display = 'block';
        noData.textContent = 'No se encontraron turnos.';
      }
      return;
    }
    if(noData) noData.style.display = 'none';
    
    rows.forEach(r=>{
      const estado = (r.estado || 'pendiente').toLowerCase();
      const confirmado = (estado === 'confirmado');
      const pendiente = (estado === 'pendiente_confirmacion' || estado === 'pendiente');
      const atendido = r.atendido || false;
      
      const tr = document.createElement('tr');
      
      let badgeClass = 'warn';
      let estadoTexto = estado;
      let estadoIcono = '⏳';
      
      if (confirmado) {
        badgeClass = 'ok';
        estadoTexto = 'confirmado';
        estadoIcono = '✅';
      } else if (pendiente) {
        badgeClass = 'warn';
        estadoTexto = 'pendiente';
        estadoIcono = '⏳';
      }
      
      if (atendido) {
        badgeClass = 'ok';
        estadoTexto = 'atendido';
        estadoIcono = '✔️';
      }
      
      // Botones según estado
      let botonesHTML = '';
      
      if (atendido) {
        // Turno ya atendido - solo ver/eliminar
        botonesHTML = `
          <span class="badge ok">✔️ Atendido</span>
          <button class="btn ghost btn-sm btn-delete" data-id="${r.Id_turno}">🗑️</button>
        `;
      } else if (confirmado) {
        // Turno confirmado - puede cancelar, reprogramar o eliminar
        botonesHTML = `
          <button class="btn ghost btn-sm btn-cancel" data-id="${r.Id_turno}">❌ Cancelar</button>
          <button class="btn ghost btn-sm btn-reprog" data-id="${r.Id_turno}">🔄 Reprogramar</button>
          <button class="btn ghost btn-sm btn-delete" data-id="${r.Id_turno}">🗑️</button>
        `;
      } else if (pendiente) {
        // Turno pendiente - puede confirmar, rechazar o eliminar
        botonesHTML = `
          <button class="btn primary btn-sm btn-confirm" data-id="${r.Id_turno}">✅ Confirmar</button>
          <button class="btn danger btn-sm btn-reject" data-id="${r.Id_turno}">❌ Rechazar</button>
          <button class="btn ghost btn-sm btn-delete" data-id="${r.Id_turno}">🗑️</button>
        `;
      } else {
        // Otro estado - solo eliminar
        botonesHTML = `
          <button class="btn ghost btn-sm btn-delete" data-id="${r.Id_turno}">🗑️ Eliminar</button>
        `;
      }
      
      tr.innerHTML = `
        <td>
          <div style="font-weight:600">${esc(r.fecha_fmt||'')}</div>
        </td>
        <td>${esc(r.paciente||'')}</td>
        <td><span class="badge ${badgeClass}">${estadoIcono} ${esc(estadoTexto)}</span></td>
        <td class="row-actions">${botonesHTML}</td>
      `;
      
      tblAgendaBody.appendChild(tr);
    });

    // Event listeners
    $$('.btn-cancel').forEach(b=>b.addEventListener('click', ()=> cancelTurno(b.dataset.id)));
    $$('.btn-delete').forEach(b=>b.addEventListener('click', ()=> deleteTurno(b.dataset.id)));
    $$('.btn-confirm').forEach(b=>b.addEventListener('click', ()=> {
      if(window.confirmarTurno) window.confirmarTurno(b.dataset.id);
    }));
    $$('.btn-reject').forEach(b=>b.addEventListener('click', ()=> {
      if(window.rechazarTurno) window.rechazarTurno(b.dataset.id);
    }));
    $$('.btn-reprog').forEach(b=>{
      b.addEventListener('click', ()=> {
        selectedTurnoId = b.dataset.id;
        if(reprogSection) {
          reprogSection.style.display = 'block';
          if(newDate) {
            newDate.disabled=false;
            newDate.value='';
          }
          if(newTime) {
            newTime.disabled=true;
            newTime.innerHTML=`<option value="">Elegí fecha…</option>`;
          }
          if(btnReprog) btnReprog.disabled=true;
        }
        setMsg(msgTurns, '🔄 Seleccioná nueva fecha y horario');
        reprogSection?.scrollIntoView({behavior:'smooth', block:'center'});
      });
    });
  }

  async function cancelTurno(id){
    if (!confirm('¿Cancelar este turno?')) return;
    try {
      const fd = new FormData();
      fd.append('action','cancel_turno');
      fd.append('turno_id', id);
      fd.append('csrf_token', csrf);
      
      const r = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await r.json();
      if(!data.ok) throw new Error(data.error||'Error');
      
      setMsg(msgTurns, '✅ Turno cancelado', true);
      await loadAgenda();
    } catch (e) {
      setMsg(msgTurns, e.message, false);
    }
  }

  async function deleteTurno(id){
    if (!confirm('¿ELIMINAR permanentemente? No se puede deshacer.')) return;
    try {
      const fd = new FormData();
      fd.append('action','delete_turno');
      fd.append('turno_id', id);
      fd.append('csrf_token', csrf);
      
      const r = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await r.json();
      if(!data.ok) throw new Error(data.error||'Error');
      
      setMsg(msgTurns, '✅ Turno eliminado', true);
      await loadAgenda();
    } catch (e) {
      setMsg(msgTurns, e.message, false);
    }
  }

  newDate?.addEventListener('change', async ()=>{
  setMsg(msgTurns, '');
  
  if (newDate.value) {
    const validation = Utils.isValidTurnoDate(newDate.value);
    if (!validation.valid) {
      setMsg(msgTurns, '❌ ' + validation.error, false);
      newDate.value = '';
      return;
    }
    
    // ✅ VERIFICAR QUE EL MÉDICO ATIENDA ESE DÍA
    if (currentMedicoId) {
      const diaSemana = Utils.getDayName(newDate.value);
      
      try {
        const resMedInfo = await fetch(`admin.php?fetch=medico_horarios&medico_id=${currentMedicoId}`, {
          headers: { 'Accept': 'application/json' }
        });
        const dataMedInfo = await resMedInfo.json();
        
        if (dataMedInfo.ok && dataMedInfo.horarios) {
          const atiendeEsteDia = dataMedInfo.horarios.some(h => 
            (h.dia_semana || h.Dia_semana) === diaSemana
          );
          
          if (!atiendeEsteDia) {
            const diasDisponibles = [...new Set(dataMedInfo.horarios.map(h => 
              (h.dia_semana || h.Dia_semana).charAt(0).toUpperCase() + 
              (h.dia_semana || h.Dia_semana).slice(1)
            ))].join(', ');
            
            alert(`⚠️ El médico no atiende los ${diaSemana}s\n\nDías disponibles: ${diasDisponibles}`);
            newDate.value = '';
            if(newTime) newTime.innerHTML=`<option value="">Elegí un día válido...</option>`;
            return;
          }
        }
      } catch (e) {
        console.error('Error verificando horarios:', e);
      }
    }
  }
  
  if(newTime) {
    newTime.innerHTML=`<option value="">Cargando…</option>`;
    newTime.disabled=true;
  }
  if(btnReprog) btnReprog.disabled=true;
  
  if(!newDate.value || !currentMedicoId){
    if(newTime) newTime.innerHTML=`<option value="">Elegí fecha…</option>`;
    return;
  }
  
  const qs = new URLSearchParams({
    fetch:'slots',
    date:newDate.value,
    medico_id:currentMedicoId
  });
  
  try {
    const r = await fetch(`admin.php?${qs.toString()}`, { headers:{ 'Accept':'application/json' }});
    const data = await r.json();
    if(!data.ok) {
      setMsg(msgTurns, data.error||'Error', false);
      if(newTime) newTime.innerHTML=`<option value="">Error</option>`;
      return;
    }
    if(newTime) {
      newTime.innerHTML = `<option value="">Elegí horario…</option>`;
      (data.slots||[]).forEach(h => {
        const opt=document.createElement('option');
        opt.value=h;
        opt.textContent=Utils.formatHour12(h);
        newTime.appendChild(opt);
      });
      newTime.disabled = false;
    }
  } catch (e) {
    setMsg(msgTurns, 'Error', false);
    console.error(e);
  }
});


  newTime?.addEventListener('change', ()=> {
    if(btnReprog) btnReprog.disabled = !(selectedTurnoId && newDate?.value && newTime?.value);
  });

  btnReprog?.addEventListener('click', async ()=>{
    if(!selectedTurnoId || !currentMedicoId || !newDate?.value || !newTime?.value){
      setMsg(msgTurns, 'Completá todos los campos', false);
      return;
    }
    
    const validation = Utils.isValidTurnoDate(newDate.value);
    if (!validation.valid) {
      setMsg(msgTurns, '❌ ' + validation.error, false);
      alert('⚠️ ' + validation.error);
      return;
    }
    
    if (!confirm(`¿Reprogramar turno?\n\n📅 ${Utils.formatDateDisplay(newDate.value)}\n🕐 ${Utils.formatHour12(newTime.value)}`)) {
      return;
    }
    
    try {
      const fd = new FormData();
      fd.append('action','reschedule_turno');
      fd.append('turno_id', selectedTurnoId);
      fd.append('medico_id', currentMedicoId);
      fd.append('date', newDate.value);
      fd.append('time', newTime.value);
      fd.append('csrf_token', csrf);
      
      const r = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await r.json();
      if(!data.ok) throw new Error(data.error||'Error');
      
      setMsg(msgTurns, '✅ Turno reprogramado', true);
      selectedTurnoId=null;
      if(btnReprog) btnReprog.disabled=true;
      if(reprogSection) reprogSection.style.display='none';
      await loadAgenda();
      if(newTime) {
        newTime.innerHTML=`<option value="">Elegí fecha…</option>`;
        newTime.disabled=true;
      }
      if(newDate) newDate.value='';
    } catch (e) {
      setMsg(msgTurns, e.message, false);
    }
  });

  btnCancelReprog?.addEventListener('click', ()=>{
    selectedTurnoId = null;
    if(reprogSection) reprogSection.style.display = 'none';
    if(newDate) newDate.value = '';
    if(newTime) newTime.innerHTML = `<option value="">Elegí fecha…</option>`;
    setMsg(msgTurns, '');
  });

  // ========== CREAR TURNO ==========
  
  btnNewTurno?.addEventListener('click', async ()=>{
  if (!currentMedicoId) {
    setMsg(msgTurns, 'Seleccioná un médico primero', false);
    return;
  }
  
  // Obtener información del médico seleccionado
  const medicoActual = medicosData.find(m => m.Id_medico == currentMedicoId);
  
  if (!medicoActual) {
    setMsg(msgTurns, 'Error: médico no encontrado', false);
    return;
  }
  
  // Extraer días disponibles del médico
  const diasDisponibles = [];
  if (medicoActual.horarios && medicoActual.horarios.length > 0) {
    medicoActual.horarios.forEach(h => {
      const dia = h.dia_semana || h.Dia_semana;
      if (dia && !diasDisponibles.includes(dia)) {
        diasDisponibles.push(dia);
      }
    });
  }
  
  // Guardar días disponibles globalmente
  window.currentMedicoDiasDisponibles = diasDisponibles;
  
  $('#turnoMedicoId').value = currentMedicoId;
  selectedPacienteId.value = '';
  selectedPacienteInfo.textContent = 'Ninguno';
  selectedPacienteInfo.style.color = 'var(--muted)';
  turnoDate.value = '';
  turnoTime.innerHTML = `<option value="">Elegí fecha primero...</option>`;
  searchPaciente.value = '';
  pacienteResults.innerHTML = '';
  setMsg(msgModal, '');
  
  // Mostrar días disponibles
  if (diasDisponibles.length > 0) {
    const diasCapitalizados = diasDisponibles.map(d => 
      d.charAt(0).toUpperCase() + d.slice(1)
    ).join(', ');
    setMsg(msgModal, `ℹ️ Este médico atiende: ${diasCapitalizados}`, true);
  }
  
  showModal(modalCreateTurno);
  
  // Configurar restricciones del calendario
  setTimeout(() => setupMedicoDateRestrictions(), 100);
});

  let searchTimeout;
  searchPaciente?.addEventListener('input', ()=>{
    clearTimeout(searchTimeout);
    const query = searchPaciente.value.trim();
    
    if (query.length < 2) {
      pacienteResults.innerHTML = '';
      return;
    }

    searchTimeout = setTimeout(async ()=>{
      try {
        const res = await fetch(`admin.php?fetch=search_pacientes&q=${encodeURIComponent(query)}`, {
          headers:{ 'Accept':'application/json' }
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Error');
        
        renderPacienteResults(data.items || []);
      } catch (e) {
        pacienteResults.innerHTML = `<div style="padding:10px;color:var(--err)">${e.message}</div>`;
      }
    }, 300);
  });

  function renderPacienteResults(items){
    pacienteResults.innerHTML = '';
    if (!items.length) {
      pacienteResults.innerHTML = '<div style="padding:10px;color:var(--muted)">No se encontraron pacientes</div>';
      selectedPacienteId.value = '';
      return;
    }

    items.forEach(p=>{
      const div = document.createElement('div');
      div.className = 'paciente-item';
      
      const apellido = p.apellido || p.Apellido || '';
      const nombre = p.nombre || p.Nombre || '';
      const dni = p.dni || '';
      const email = p.email || '';
      const obraSocial = p.Obra_social || '';
      
      div.innerHTML = `
        <strong>${esc(apellido)}, ${esc(nombre)}</strong><br>
        <small style="color:var(--muted)">DNI: ${esc(dni)} | ${esc(email)}</small>
      `;
      div.addEventListener('click', ()=>{
        selectedPacienteId.value = p.Id_paciente;
        selectedPacienteInfo.innerHTML = `<strong>${esc(apellido)}, ${esc(nombre)}</strong><br><small>DNI: ${esc(dni)} | ${esc(obraSocial || 'Sin obra social')}</small>`;
        selectedPacienteInfo.style.color = 'var(--ok)';
        document.querySelectorAll('.paciente-item').forEach(item => item.classList.remove('selected'));
        div.classList.add('selected');
        
        pacienteResults.innerHTML = '';
        setMsg(msgModal, '✅ Paciente seleccionado', true);
      });
      pacienteResults.appendChild(div);
    });
  }

  turnoDate?.addEventListener('change', async ()=>{
    const turnoTimeSelect = document.getElementById('turnoTime');
    const medicoIdInput = document.getElementById('turnoMedicoId');
    
    if (!turnoTimeSelect) return;
    
    if (turnoDate.value) {
      const validation = Utils.isValidTurnoDate(turnoDate.value);
      if (!validation.valid) {
        setMsg(msgModal, '❌ ' + validation.error, false);
        turnoDate.value = '';
        return;
      }
    }
    
    turnoTimeSelect.innerHTML = `<option value="">Cargando…</option>`;
    turnoTimeSelect.disabled = true;
    
    const medicoId = medicoIdInput?.value;
    const fechaSeleccionada = turnoDate.value;
    
    if (!fechaSeleccionada) {
      turnoTimeSelect.innerHTML = `<option value="">Elegí fecha</option>`;
      return;
    }
    
    if (!medicoId) {
      turnoTimeSelect.innerHTML = `<option value="">Error: sin médico</option>`;
      return;
    }

    try {
      const url = `admin.php?fetch=slots&date=${encodeURIComponent(fechaSeleccionada)}&medico_id=${encodeURIComponent(medicoId)}`;
      
      const res = await fetch(url, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      const slots = data.slots || [];
      
      if (slots.length === 0) {
        turnoTimeSelect.innerHTML = `<option value="">Sin horarios</option>`;
        setMsg(msgModal, 'No hay horarios disponibles', false);
        return;
      }
      
      turnoTimeSelect.innerHTML = `<option value="">Elegí horario…</option>`;
      slots.forEach(slot => {
        const opt = document.createElement('option');
        opt.value = slot;
        opt.textContent = Utils.formatHour12(slot);
        turnoTimeSelect.appendChild(opt);
      });
      
      turnoTimeSelect.disabled = false;
      
    } catch (e) {
      console.error('Error:', e);
      setMsg(msgModal, 'Error: ' + e.message, false);
      turnoTimeSelect.innerHTML = `<option value="">Error</option>`;
    }
  });

  formCreateTurno?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    setMsg(msgModal, '');

    const pacId = selectedPacienteId.value;
    const date = turnoDate.value;
    const time = turnoTime.value;
    const medId = $('#turnoMedicoId').value;

    if (!pacId || pacId === '') {
      setMsg(msgModal, '❌ Seleccioná un paciente', false);
      alert('Debés buscar y hacer click en un paciente');
      return;
    }
    if (!date || date === '') {
      setMsg(msgModal, '❌ Seleccioná una fecha', false);
      return;
    }
    
    const validation = Utils.isValidTurnoDate(date);
    if (!validation.valid) {
      setMsg(msgModal, '❌ ' + validation.error, false);
      alert('⚠️ ' + validation.error);
      return;
    }
    
    if (!time || time === '') {
      setMsg(msgModal, '❌ Seleccioná un horario', false);
      return;
    }

    console.log('🔍 Verificando turnos duplicados...');
    setMsg(msgModal, '⏳ Verificando turnos existentes...', true);
    
    const turnoExistente = await window.checkPacienteTurnoExistente(pacId, medId);
    
    if (turnoExistente) {
      const pacienteNombre = selectedPacienteInfo.textContent.split('\n')[0];
      const medicoSelect = document.getElementById('fMed');
      const medicoNombre = medicoSelect ? medicoSelect.options[medicoSelect.selectedIndex].text : 'este médico';
      
      setMsg(msgModal, '⚠️ El paciente ya tiene un turno activo con este médico', false);
      
      alert(
        `⚠️ TURNO DUPLICADO DETECTADO\n\n` +
        `El paciente ${pacienteNombre} ya tiene un turno activo con ${medicoNombre}.\n\n` +
        `📅 Turno existente: ${turnoExistente.fecha_fmt}\n` +
        `📍 Estado: ${turnoExistente.estado}\n\n` +
        `💡 Para crear un nuevo turno, primero debés:\n` +
        `• Cancelar el turno anterior desde la agenda, o\n` +
        `• Reprogramarlo en lugar de crear uno nuevo\n\n` +
        `Esta restricción evita turnos duplicados por médico.`
      );
      
      return;
    }
    
    console.log('✅ Validación pasada - puede crear turno');
    
    const pacienteNombre = selectedPacienteInfo.textContent.split('\n')[0];
    if (!confirm(`¿Crear turno?\n\nPaciente: ${pacienteNombre}\n📅 ${Utils.formatDateDisplay(date)}\n🕐 ${Utils.formatHour12(time)}`)) {
      return;
    }
    
    setMsg(msgModal, '⏳ Creando turno...', true);
    
    try {
      const fd = new FormData();
      fd.append('action', 'create_turno');
      fd.append('medico_id', medId);
      fd.append('paciente_id', pacId);
      fd.append('date', date);
      fd.append('time', time);
      fd.append('csrf_token', csrf);

      const res = await fetch('admin.php', { 
        method: 'POST', 
        body: fd, 
        headers: { 'Accept': 'application/json' }
      });
      
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');

      setMsg(msgModal, '✅ ' + (data.msg || 'Turno creado'), true);
      
      setTimeout(() => {
        hideModal(modalCreateTurno);
        loadAgenda();
      }, 1500);
      
    } catch (e) {
      console.error('Error creando turno:', e);
      setMsg(msgModal, '❌ ' + e.message, false);
    }
  });

  // ========== CERRAR MODALES ==========
  btnCloseModal?.addEventListener('click', ()=> hideModal(modalCreateTurno));
  btnCloseMedicoModal?.addEventListener('click', ()=> hideModal(modalEditMedico));
  btnCloseSecretariaModal?.addEventListener('click', ()=> hideModal(modalEditSecretaria));

  [modalCreateTurno, modalEditMedico, modalEditSecretaria].forEach(modal=>{
    modal?.addEventListener('click', (e)=>{
      if (e.target === modal) hideModal(modal);
    });
  });

  // ========== EVENTOS FILTROS ==========
  
  btnRefresh?.addEventListener('click', loadAgenda);
  
  btnClearDates?.addEventListener('click', ()=>{
    if(fFrom) fFrom.value='';
    if(fTo) fTo.value='';
    loadAgenda();
  });

  fEsp?.addEventListener('change', async ()=>{
    setMsg(msgTurns, '');
    if(tblAgendaBody) tblAgendaBody.innerHTML='';
    if(noData) {
      noData.style.display='block';
      noData.textContent='Seleccioná un médico.';
    }
    selectedTurnoId=null;
    if(btnReprog) btnReprog.disabled=true;
    if(reprogSection) reprogSection.style.display='none';
    if(newDate) newDate.value='';
    if(newTime) {
      newTime.innerHTML=`<option value="">Elegí fecha…</option>`;
      newTime.disabled=true;
    }
    currentMedicoId = null;
    if(btnNewTurno) btnNewTurno.disabled = true;
    
    if(!fEsp.value){
      if(fMed) {
        fMed.innerHTML=`<option value="">Elegí especialidad…</option>`;
        fMed.disabled=true;
      }
      if(noData) noData.textContent='Seleccioná una especialidad.';
      return;
    }
    await loadDocs(fEsp.value);
  });

  fMed?.addEventListener('change', async ()=>{
    setMsg(msgTurns, '');
    if(reprogSection) reprogSection.style.display='none';
    if(newDate) newDate.value='';
    if(newTime) {
      newTime.innerHTML=`<option value="">Elegí fecha…</option>`;
      newTime.disabled=true;
    }
    currentMedicoId = fMed.value || null;
    if(btnNewTurno) btnNewTurno.disabled = !currentMedicoId;
    await loadAgenda();
  });

  fFrom?.addEventListener('change', loadAgenda);
  fTo?.addEventListener('change', loadAgenda);

// ========== SISTEMA DE TURNOS PENDIENTES ==========
(function(){
  'use strict';
  
  const $ = s => document.querySelector(s);
  const csrf = $('meta[name="csrf-token"]')?.getAttribute('content') || '';
  
  function setMsg(el, t, ok=true){
    if(!el) return;
    el.textContent=t||'';
    el.classList.remove('ok','err');
    el.classList.add(ok?'ok':'err');
  }
  
  async function loadTurnosPendientes() {
    const msgEl = $('#msgPendientes');
    const tbody = $('#tblTurnosPendientes tbody');
    const noData = $('#noPendientes');
    
    if(!tbody) return;
    
    setMsg(msgEl, '⏳ Cargando turnos pendientes...', true);
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px">⏳ Cargando...</td></tr>';
    if(noData) noData.style.display = 'none';
    
    try {
      const espId = $('#fEspPendientes')?.value || '';
      const medId = $('#fMedPendientes')?.value || '';
      const from = $('#fFromPendientes')?.value || '';
      const to = $('#fToPendientes')?.value || '';
      
      let url = 'admin.php?fetch=turnos_pendientes';
      if(espId) url += `&especialidad_id=${espId}`;
      if(medId) url += `&medico_id=${medId}`;
      if(from) url += `&from=${from}`;
      if(to) url += `&to=${to}`;
      
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
      if(!res.ok) throw new Error(`HTTP ${res.status}`);
      
      const data = await res.json();
      if(!data.ok) throw new Error(data.error || 'Error');
      
      renderTurnosPendientes(data.items || []);
      setMsg(msgEl, `✅ ${data.items.length} turno(s) pendiente(s)`, true);
      
    } catch(e) {
      console.error('Error:', e);
      setMsg(msgEl, '❌ ' + e.message, false);
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--err)">❌ Error al cargar</td></tr>';
    }
  }
  
  function renderTurnosPendientes(items) {
    const tbody = $('#tblTurnosPendientes tbody');
    const noData = $('#noPendientes');
    
    if(!tbody) return;
    
    tbody.innerHTML = '';
    
    if(items.length === 0) {
      if(noData) noData.style.display = 'block';
      return;
    }
    
    if(noData) noData.style.display = 'none';
    
    items.forEach(t => {
      const tr = document.createElement('tr');
      tr.style.background = 'rgba(251,146,60,0.05)';
      
      tr.innerHTML = `
        <td>
          <div style="font-weight:600;color:var(--primary)">${t.fecha_fmt}</div>
        </td>
        <td>${t.paciente}</td>
        <td>${t.paciente_dni}</td>
        <td>${t.medico}</td>
        <td>${t.especialidad}</td>
        <td>${t.obra_social}</td>
        <td class="row-actions">
          <button class="btn primary btn-sm btn-confirmar-turno" data-id="${t.Id_turno}">
            ✅ Confirmar
          </button>
          <button class="btn danger btn-sm btn-rechazar-turno" data-id="${t.Id_turno}">
            ❌ Rechazar
          </button>
        </td>
      `;
      
      tbody.appendChild(tr);
    });
    
    // Event listeners
    tbody.querySelectorAll('.btn-confirmar-turno').forEach(btn => {
      btn.addEventListener('click', () => {
        if(window.confirmarTurno) {
          window.confirmarTurno(btn.dataset.id);
        }
      });
    });
    
    tbody.querySelectorAll('.btn-rechazar-turno').forEach(btn => {
      btn.addEventListener('click', () => {
        if(window.rechazarTurno) {
          window.rechazarTurno(btn.dataset.id);
        }
      });
    });
  }
  
  // Cargar especialidades en el filtro
  async function loadEspecialidadesPendientes() {
    const sel = $('#fEspPendientes');
    if(!sel) return;
    
    try {
      const res = await fetch('admin.php?fetch=init', { headers: { 'Accept': 'application/json' }});
      const data = await res.json();
      
      if(data.ok && data.especialidades) {
        sel.innerHTML = '<option value="">Todas las especialidades</option>';
        data.especialidades.forEach(e => {
          const opt = document.createElement('option');
          opt.value = e.Id_Especialidad;
          opt.textContent = e.nombre;
          sel.appendChild(opt);
        });
      }
    } catch(e) {
      console.error('Error loading especialidades:', e);
    }
  }
  
  // Event listeners
  $('#fEspPendientes')?.addEventListener('change', async function() {
    const medSel = $('#fMedPendientes');
    if(!medSel) return;
    
    if(!this.value) {
      medSel.innerHTML = '<option value="">Todos los médicos</option>';
      medSel.disabled = true;
      return;
    }
    
    medSel.innerHTML = '<option value="">Cargando…</option>';
    medSel.disabled = true;
    
    try {
      const res = await fetch(`admin.php?fetch=doctors&especialidad_id=${this.value}`, { 
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      
      if(data.ok) {
        medSel.innerHTML = '<option value="">Todos los médicos</option>';
        (data.items || []).forEach(m => {
          const opt = document.createElement('option');
          opt.value = m.Id_medico;
          opt.textContent = `${m.Apellido}, ${m.Nombre}`;
          medSel.appendChild(opt);
        });
        medSel.disabled = false;
      }
    } catch(e) {
      console.error('Error:', e);
      medSel.innerHTML = '<option value="">Error</option>';
    }
  });
  
  $('#fMedPendientes')?.addEventListener('change', loadTurnosPendientes);
  $('#fFromPendientes')?.addEventListener('change', loadTurnosPendientes);
  $('#fToPendientes')?.addEventListener('change', loadTurnosPendientes);
  $('#btnRefreshPendientes')?.addEventListener('click', loadTurnosPendientes);
  
  $('#btnClearFiltersPendientes')?.addEventListener('click', () => {
    const fEsp = $('#fEspPendientes');
    const fMed = $('#fMedPendientes');
    const fFrom = $('#fFromPendientes');
    const fTo = $('#fToPendientes');
    
    if(fEsp) fEsp.value = '';
    if(fMed) {
      fMed.innerHTML = '<option value="">Elegí especialidad primero</option>';
      fMed.disabled = true;
    }
    if(fFrom) fFrom.value = '';
    if(fTo) fTo.value = '';
    
    loadTurnosPendientes();
  });
  
  // Inicializar cuando se muestra el tab
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      if(tab.dataset.tab === 'turnos-pendientes') {
        loadEspecialidadesPendientes();
        loadTurnosPendientes();
      }
    });
  });
  
  console.log('✅ Sistema de Turnos Pendientes inicializado');
  })();

  // ========== INICIALIZACIÓN ==========
  (async function init(){
    console.log('🚀 Inicializando panel admin');
    
    // ✅ Esperar a que el DOM esté completamente listo
    if (document.readyState === 'loading') {
      await new Promise(resolve => {
        document.addEventListener('DOMContentLoaded', resolve);
      });
    }
    
    console.log('📄 DOM listo, iniciando carga...');
    
    try {
      await loadInit();
      console.log('✅ Panel listo');
    } catch (error) {
      console.error('❌ Error fatal en inicialización:', error);
      alert('Error al inicializar el panel. Recarga la página.');
    }
    function setupMedicoDateRestrictions() {
  const dateInput = document.getElementById('turnoDate');
  if (!dateInput) return;
  
  const diasDisponibles = window.currentMedicoDiasDisponibles || [];
  if (diasDisponibles.length === 0) {
    console.warn('⚠️ No hay días disponibles configurados');
    return;
  }
  
  console.log('📅 Días disponibles del médico:', diasDisponibles);
  
  // Mapeo de días a números (0=domingo, 6=sábado)
  const diasMap = {
    'domingo': 0, 'lunes': 1, 'martes': 2, 'miercoles': 3,
    'jueves': 4, 'viernes': 5, 'sabado': 6
  };
  
  const diasNumeros = diasDisponibles.map(d => diasMap[d]).filter(n => n !== undefined);
  console.log('🔢 Días en números:', diasNumeros);
  
  // ✅ CONFIGURAR RESTRICCIONES DEL INPUT
  const today = new Date();
  const maxDate = new Date();
  maxDate.setMonth(maxDate.getMonth() + 3);
  
  dateInput.min = today.toISOString().split('T')[0];
  dateInput.max = maxDate.toISOString().split('T')[0];
  
  // ✅ VALIDACIÓN EN TIEMPO REAL
  dateInput.addEventListener('input', function(e) {
    const value = e.target.value;
    if (!value) return;
    
    const date = new Date(value + 'T00:00:00');
    const dayOfWeek = date.getDay();
    
    console.log('📆 Fecha seleccionada:', value, '- Día de semana:', dayOfWeek);
    
    if (!diasNumeros.includes(dayOfWeek)) {
      const diasCapitalizados = diasDisponibles.map(d => 
        d.charAt(0).toUpperCase() + d.slice(1)
      ).join(', ');
      
      alert(`⚠️ EL MÉDICO NO ATIENDE ESE DÍA\n\nEl médico seleccionado solo atiende:\n${diasCapitalizados}\n\nPor favor, elegí otro día.`);
      e.target.value = '';
      
      // Limpiar horarios
      const timeSelect = document.getElementById('turnoTime');
      if (timeSelect) {
        timeSelect.innerHTML = '<option value="">Elegí un día válido...</option>';
        timeSelect.disabled = true;
      }
    }
  });
  
  // ✅ VALIDACIÓN AL CAMBIAR (doble seguridad)
  dateInput.addEventListener('change', function(e) {
    const value = e.target.value;
    if (!value) return;
    
    const date = new Date(value + 'T00:00:00');
    const dayOfWeek = date.getDay();
    
    if (!diasNumeros.includes(dayOfWeek)) {
      const diasCapitalizados = diasDisponibles.map(d => 
        d.charAt(0).toUpperCase() + d.slice(1)
      ).join(', ');
      
      alert(`⚠️ DÍA NO DISPONIBLE\n\nDías de atención: ${diasCapitalizados}`);
      e.target.value = '';
    }
  });
  
  console.log('✅ Restricciones de calendario configuradas');

})(); // cierre IIFE turnos pendientes
})(); // cierre IIFE sistema principal
})(); // cierre IIFE init