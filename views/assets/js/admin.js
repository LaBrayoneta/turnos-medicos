// admin.js - Panel Administrativo (VERSIÓN CORREGIDA)
// ✅ CORRECCIÓN: Botones de eliminar ahora funcionan correctamente

(function(){
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
    try {
      const res = await fetch('admin.php?fetch=init', { headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) { alert('Error cargando datos: ' + (data.error || '')); return; }

      especialidades = data.especialidades || [];
      medicosData = data.medicos || [];
      secretariasData = data.secretarias || [];
      obrasSocialesData = data.obras_sociales || [];

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
    } catch (e) {
      console.error('Error en loadInit', e);
      alert('Error cargando datos iniciales');
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

        const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
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

    // ✅ CORRECCIÓN: Asegurar que los event listeners se agreguen
    $$('.btn-edit-med').forEach(b => {
      b.addEventListener('click', ()=> openEditMedico(b.dataset.id));
    });
    
    // ✅ CORRECCIÓN CRÍTICA: Usar la función global window.deleteMedico
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

    // ✅ CORRECCIÓN: Asegurar event listeners
    $$('.btn-edit-sec').forEach(b => {
      b.addEventListener('click', ()=> openEditSecretaria(b.dataset.id));
    });
    
    // ✅ CORRECCIÓN CRÍTICA: Usar la función global window.deleteSecretaria
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

  async function deleteSecretaria(id){
    if (!confirm('¿Eliminar esta secretaria? Esta acción no se puede deshacer.')) return;
    try{
      const fd = new FormData();
      fd.append('action', 'delete_secretaria');
      fd.append('id_secretaria', id);
      fd.append('csrf_token', csrf);

      const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      setMsg(msgCreateSec, data.msg || 'Secretaria eliminada', true);
      await loadInit();
    }catch(err){
      setMsg(msgCreateSec, err.message, false);
    }
  }

  // ========== TURNOS ==========
  
  async function loadDocs(espId){
    if(!fMed) return;
    fMed.innerHTML = `<option value="">Cargando…</option>`;
    fMed.disabled=true;
    if(btnNewTurno) btnNewTurno.disabled=true;
    try {
      const r = await fetch(`admin.php?fetch=doctors&especialidad_id=${encodeURIComponent(espId)}`, { headers:{ 'Accept':'application/json' }});
      const data = await r.json();
      if(!data.ok) { setMsg(msgTurns, data.error||'Error', false); return; }
      fMed.innerHTML = `<option value="">Elegí médico…</option>`;
      (data.items||[]).forEach(m=>{
        const opt = document.createElement('option');
        opt.value = m.Id_medico;
        // ✅ Soportar ambos formatos
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

  // ========== REEMPLAZAR LA FUNCIÓN renderAgenda EN admin.js ==========

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
      const pendiente = (r.estado === 'pendiente_confirmacion');
      const confirmado = (r.estado === 'confirmado');
      const rechazado = (r.estado === 'rechazado');
      
      const tr = document.createElement('tr');
      
      // Determinar color del badge según estado
      let badgeClass = 'warn';
      let estadoTexto = r.estado || 'pendiente';
      
      if (confirmado) {
        badgeClass = 'ok';
        estadoTexto = 'confirmado';
      } else if (rechazado) {
        badgeClass = 'err';
        estadoTexto = 'rechazado';
      } else if (pendiente) {
        badgeClass = 'warn';
        estadoTexto = 'pendiente confirmación';
      }
      
      tr.innerHTML = `
        <td>
          <div style="font-weight:600">${esc(r.fecha_fmt||'')}</div>
        </td>
        <td>${esc(r.paciente||'')}</td>
        <td><span class="badge ${badgeClass}">${esc(estadoTexto)}</span></td>
        <td class="row-actions">
          ${pendiente ? `
            <button class="btn primary btn-confirmar" data-id="${r.Id_turno}">✅ Confirmar</button>
            <button class="btn danger btn-rechazar" data-id="${r.Id_turno}">❌ Rechazar</button>
            <button class="btn ghost btn-reprog" data-id="${r.Id_turno}" data-med="${r.Id_medico||''}">🔄 Reprogramar</button>
            <button class="btn ghost btn-delete" data-id="${r.Id_turno}">🗑️ Eliminar</button>
          ` : confirmado ? `
            <button class="btn ghost btn-cancel" data-id="${r.Id_turno}">❌ Cancelar</button>
            <button class="btn ghost btn-reprog" data-id="${r.Id_turno}" data-med="${r.Id_medico||''}">🔄 Reprogramar</button>
            <button class="btn ghost btn-delete" data-id="${r.Id_turno}">🗑️ Eliminar</button>
          ` : `
            <button class="btn ghost btn-delete" data-id="${r.Id_turno}">🗑️ Eliminar</button>
          `}
        </td>`;
      
      if (rechazado) tr.style.opacity = '0.6';
      
      tblAgendaBody.appendChild(tr);
    });

    // Event listeners existentes
    $$('.btn-cancel').forEach(b=>b.addEventListener('click', ()=> cancelTurno(b.dataset.id)));
    $$('.btn-delete').forEach(b=>b.addEventListener('click', ()=> deleteTurno(b.dataset.id)));
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
    
    // ========== NUEVOS EVENT LISTENERS: CONFIRMAR/RECHAZAR ==========
    $$('.btn-confirmar').forEach(b => {
      b.addEventListener('click', () => confirmarTurno(b.dataset.id));
    });
    
    $$('.btn-rechazar').forEach(b => {
      b.addEventListener('click', () => rechazarTurno(b.dataset.id));
    });
  }

// ========== NUEVA FUNCIÓN: CONFIRMAR TURNO ==========
async function confirmarTurno(turnoId) {
  if (!confirm('¿Confirmar este turno?\n\nSe enviará un email de confirmación al paciente.')) {
    return;
  }
  
  try {
    const fd = new FormData();
    fd.append('action', 'confirmar_turno');
    fd.append('turno_id', turnoId);
    fd.append('csrf_token', csrf);
    
    setMsg(msgTurns, '⏳ Confirmando turno y enviando email...', true);
    
    const r = await fetch('admin.php', { 
      method: 'POST', 
      body: fd, 
      headers: { 'Accept': 'application/json' }
    });
    
    const data = await r.json();
    
    if (!data.ok) throw new Error(data.error || 'Error');
    
    setMsg(msgTurns, '✅ ' + (data.msg || 'Turno confirmado'), true);
    await loadAgenda();
    
  } catch (e) {
    console.error('Error:', e);
    setMsg(msgTurns, '❌ ' + e.message, false);
  }
}

// ========== NUEVA FUNCIÓN: RECHAZAR TURNO ==========
async function rechazarTurno(turnoId) {
  const motivo = prompt(
    '¿Por qué motivo rechazas este turno?\n\n' +
    'Este motivo se enviará al paciente por email.\n' +
    'Mínimo 10 caracteres, máximo 500.'
  );
  
  if (!motivo) return;
  
  if (motivo.trim().length < 10) {
    alert('❌ El motivo debe tener al menos 10 caracteres');
    return;
  }
  
  if (motivo.trim().length > 500) {
    alert('❌ El motivo es demasiado largo (máximo 500 caracteres)');
    return;
  }
  
  if (!confirm(`¿Confirmas el rechazo?\n\nMotivo: ${motivo}\n\nSe enviará un email al paciente.`)) {
    return;
  }
  
  try {
    const fd = new FormData();
    fd.append('action', 'rechazar_turno');
    fd.append('turno_id', turnoId);
    fd.append('motivo', motivo.trim());
    fd.append('csrf_token', csrf);
    
    setMsg(msgTurns, '⏳ Rechazando turno y enviando email...', true);
    
    const r = await fetch('admin.php', { 
      method: 'POST', 
      body: fd, 
      headers: { 'Accept': 'application/json' }
    });
    
    const data = await r.json();
    
    if (!data.ok) throw new Error(data.error || 'Error');
    
    setMsg(msgTurns, '✅ ' + (data.msg || 'Turno rechazado'), true);
    await loadAgenda();
    
  } catch (e) {
    console.error('Error:', e);
    setMsg(msgTurns, '❌ ' + e.message, false);
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
  
  btnNewTurno?.addEventListener('click', ()=>{
    if (!currentMedicoId) {
      setMsg(msgTurns, 'Seleccioná un médico primero', false);
      return;
    }
    $('#turnoMedicoId').value = currentMedicoId;
    selectedPacienteId.value = '';
    selectedPacienteInfo.textContent = 'Ninguno';
    selectedPacienteInfo.style.color = 'var(--muted)';
    turnoDate.value = '';
    turnoTime.innerHTML = `<option value="">Elegí fecha primero...</option>`;
    searchPaciente.value = '';
    pacienteResults.innerHTML = '';
    setMsg(msgModal, '');
    showModal(modalCreateTurno);
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
      
      // ✅ Soportar ambos formatos
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

  // Validaciones básicas
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

  // ✅ NUEVA VALIDACIÓN: Verificar turno existente
  console.log('🔍 Verificando turnos duplicados...');
  setMsg(msgModal, '⏳ Verificando turnos existentes...', true);
  
  const turnoExistente = await checkPacienteTurnoExistente(pacId, medId);
  
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
    
    return; // ⛔ DETENER LA CREACIÓN
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

// ========== INICIAL ==========
  (async function init(){
    console.log('🚀 Inicializando panel admin');
    await loadInit();
    console.log('✅ Panel listo');
  })();
})();