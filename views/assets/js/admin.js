(function(){
  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const csrf = $('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // Estado global
  let especialidades = [];
  let medicosData = [];
  let secretariasData = [];
  let obrasSocialesData = [];
  let selectedTurnoId = null;
  let currentMedicoId = null;

  // Elementos DOM
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

  // Utilidades
  function esc(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function setMsg(el, t, ok=true){ 
    if(!el) return; 
    el.textContent=t||''; 
    el.classList.remove('ok','err'); 
    el.classList.add(ok?'ok':'err'); 
  }
  function showModal(modal){ if(modal) modal.style.display='flex'; }
  function hideModal(modal){ if(modal) modal.style.display='none'; }

  // Tabs
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

      // Llenar selects de especialidad
      const espSelects = ['#espCreateSelect', '#fEsp', '#editMedEsp'];
      espSelects.forEach(sel => {
        const element = $(sel);
        if (element) {
          element.innerHTML = `<option value="">Elegir…</option>`;
          especialidades.forEach(e=>{
            const opt = document.createElement('option');
            opt.value=e.Id_Especialidad; opt.textContent=e.Nombre;
            element.appendChild(opt);
          });
        }
      });

      renderMedicos(medicosData);
      renderSecretarias(secretariasData);
      renderObras(obrasSocialesData);
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
      const estadoTexto = r.Activo ? 'Activa' : 'Inactiva';
      const badgeClass = r.Activo ? 'ok' : 'warn';
      tr.innerHTML = `
        <td>${esc(r.Nombre||'')}</td>
        <td><span class="badge ${badgeClass}">${estadoTexto}</span></td>
        <td class="row-actions">
          <button class="btn ghost btn-toggle-obra" data-id="${r.Id_obra_social}">
            ${r.Activo ? '❌ Desactivar' : '✅ Activar'}
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
        if (!data.ok) throw new Error(data.error || 'Error creando obra social');
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
      if (!data.ok) throw new Error(data.error || 'Error cambiando estado');
      
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
      if (!data.ok) throw new Error(data.error || 'Error eliminando');
      
      setMsg(msgCreateObra, data.msg || 'Obra social eliminada', true);
      await loadInit();
    }catch(err){
      setMsg(msgCreateObra, err.message, false);
    }
  }

  // ========== MÉDICOS ==========
  function formatHour12(time24) {
    if (!time24) return '';
    const [hours, minutes] = time24.split(':');
    let h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${minutes} ${ampm}`;
  }

  function renderMedicos(rows){
    if(!tblMedicos) return;
    tblMedicos.innerHTML='';
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      
      // Construir resumen de horarios mejorado con AM/PM
      let horariosHTML = '<div style="display:flex;flex-direction:column;gap:4px">';
      if (r.horarios && r.horarios.length > 0) {
        // Agrupar por día
        const horariosPorDia = {};
        r.horarios.forEach(h => {
          if (!horariosPorDia[h.Dia_semana]) {
            horariosPorDia[h.Dia_semana] = [];
          }
          horariosPorDia[h.Dia_semana].push({
            inicio: h.Hora_inicio.substring(0,5),
            fin: h.Hora_fin.substring(0,5)
          });
        });
        
        // Renderizar cada día con sus horarios en formato 12h
        Object.entries(horariosPorDia).forEach(([dia, horarios]) => {
          const diaCapital = dia.charAt(0).toUpperCase() + dia.slice(1);
          const horariosStr = horarios.map(h => 
            `${formatHour12(h.inicio)}-${formatHour12(h.fin)}`
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
        <td>${esc((r.Apellido||'')+', '+(r.Nombre||''))}</td>
        <td>${esc(r.dni||'')}</td>
        <td>${esc(r.Especialidad||'')}</td>
        <td>${esc(r.Legajo||'')}</td>
        <td>${horariosHTML}</td>
        <td class="row-actions">
          <button class="btn ghost btn-edit-med" data-id="${r.Id_medico}">✏️ Editar</button>
          <button class="btn danger btn-delete-med" data-id="${r.Id_medico}">🗑️</button>
        </td>`;
      tblMedicos.appendChild(tr);
    });

    $$('.btn-edit-med').forEach(b=>b.addEventListener('click', ()=> openEditMedico(b.dataset.id)));
    $$('.btn-delete-med').forEach(b=>b.addEventListener('click', ()=> deleteMedico(b.dataset.id)));
  }

  function openEditMedico(id){
    const medico = medicosData.find(m => m.Id_medico == id);
    if (!medico) {
      alert('Médico no encontrado');
      return;
    }

    $('#editMedId').value = medico.Id_medico;
    $('#editMedNombre').value = medico.Nombre || '';
    $('#editMedApellido').value = medico.Apellido || '';
    $('#editMedEmail').value = medico.email || '';
    $('#editMedLegajo').value = medico.Legajo || '';
    $('#editMedEsp').value = medico.Id_Especialidad || '';

    // Cargar horarios usando la función global
    if (window.loadMedicoHorarios) {
      window.loadMedicoHorarios(medico.horarios || []);
    }

    showModal(modalEditMedico);
  }

  async function deleteMedico(id){
    if (!confirm('¿Eliminar este médico? Esta acción no se puede deshacer.')) return;
    try{
      const fd = new FormData();
      fd.append('action', 'delete_medico');
      fd.append('id_medico', id);
      fd.append('csrf_token', csrf);

      const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error eliminando');
      
      alert('✅ Médico eliminado');
      await loadInit();
    }catch(err){
      alert('❌ ' + err.message);
    }
  }

  // ========== SECRETARIAS ==========
  function renderSecretarias(rows){
    if(!tblSecretarias) return;
    tblSecretarias.innerHTML='';
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML = `
        <td>${esc((r.Apellido||'')+', '+(r.Nombre||''))}</td>
        <td>${esc(r.dni||'')}</td>
        <td>${esc(r.email||'')}</td>
        <td class="row-actions">
          <button class="btn ghost btn-edit-sec" data-id="${r.Id_secretaria}">✏️ Editar</button>
          <button class="btn danger btn-delete-sec" data-id="${r.Id_secretaria}">🗑️</button>
        </td>`;
      tblSecretarias.appendChild(tr);
    });

    $$('.btn-edit-sec').forEach(b=>b.addEventListener('click', ()=> openEditSecretaria(b.dataset.id)));
    $$('.btn-delete-sec').forEach(b=>b.addEventListener('click', ()=> deleteSecretaria(b.dataset.id)));
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
    $('#editSecNombre').value = sec.Nombre || '';
    $('#editSecApellido').value = sec.Apellido || '';
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
        if (!data.ok) throw new Error(data.error || 'Error actualizando');
        
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
      if (!data.ok) throw new Error(data.error || 'Error eliminando');
      
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
      if(!data.ok) { setMsg(msgTurns, data.error||'Error cargando médicos', false); return; }
      fMed.innerHTML = `<option value="">Elegí médico…</option>`;
      (data.items||[]).forEach(m=>{
        const opt = document.createElement('option'); 
        opt.value=m.Id_medico; 
        opt.textContent=`${m.Apellido}, ${m.Nombre}`; 
        fMed.appendChild(opt);
      });
      fMed.disabled = false;
    } catch (e) { 
      setMsg(msgTurns, 'Error cargando médicos', false); 
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
        noData.textContent='Seleccioná una especialidad y un médico.';
      }
      return; 
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
      if(!data.ok){ 
        setMsg(msgTurns, data.error||'Error cargando agenda', false); 
        return; 
      }
      renderAgenda(data.items||[]);
    } catch (e) { 
      setMsg(msgTurns, 'Error cargando agenda', false); 
      console.error(e); 
    }
  }

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
      const reservado = (r.estado==='reservado');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${esc(r.fecha_fmt||'')}</td>
        <td>${esc(r.paciente||'')}</td>
        <td><span class="badge ${reservado?'ok':'warn'}">${esc(r.estado||'')}</span></td>
        <td class="row-actions">
          ${reservado ? `
            <button class="btn ghost btn-cancel" data-id="${r.Id_turno}">❌ Cancelar</button>
            <button class="btn ghost btn-reprog" data-id="${r.Id_turno}" data-med="${r.Id_medico||''}">🔄 Reprogramar</button>
            <button class="btn danger btn-delete" data-id="${r.Id_turno}">🗑️ Eliminar</button>
          ` : `
            <button class="btn danger btn-delete" data-id="${r.Id_turno}">🗑️ Eliminar</button>
          `}
        </td>`;
      if(!reservado) tr.classList.add('is-cancelado');
      tblAgendaBody.appendChild(tr);
    });

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
        setMsg(msgTurns, '🔄 Seleccioná nueva fecha y horario para reprogramar');
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
      if(!data.ok) throw new Error(data.error||'No se pudo cancelar');
      
      setMsg(msgTurns, '✅ Turno cancelado', true); 
      await loadAgenda();
    } catch (e) { 
      setMsg(msgTurns, e.message, false); 
    }
  }

  async function deleteTurno(id){
    if (!confirm('¿ELIMINAR este turno permanentemente? Esta acción no se puede deshacer.')) return;
    try {
      const fd = new FormData(); 
      fd.append('action','delete_turno'); 
      fd.append('turno_id', id); 
      fd.append('csrf_token', csrf);
      
      const r = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await r.json(); 
      if(!data.ok) throw new Error(data.error||'No se pudo eliminar');
      
      setMsg(msgTurns, '✅ Turno eliminado', true); 
      await loadAgenda();
    } catch (e) { 
      setMsg(msgTurns, e.message, false); 
    }
  }

  newDate?.addEventListener('change', async ()=>{
    setMsg(msgTurns, ''); 
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
        setMsg(msgTurns, data.error||'Error cargando horarios', false); 
        if(newTime) newTime.innerHTML=`<option value="">Error</option>`; 
        return; 
      }
      if(newTime) {
        newTime.innerHTML = `<option value="">Elegí horario…</option>`;
        (data.slots||[]).forEach(h => { 
          const opt=document.createElement('option'); 
          opt.value=h; 
          opt.textContent=h; 
          newTime.appendChild(opt); 
        });
        newTime.disabled = false;
      }
    } catch (e) { 
      setMsg(msgTurns, 'Error cargando horarios', false); 
      console.error(e); 
    }
  });

  newTime?.addEventListener('change', ()=> { 
    if(btnReprog) btnReprog.disabled = !(selectedTurnoId && newDate?.value && newTime?.value); 
  });

  btnReprog?.addEventListener('click', async ()=>{
    if(!selectedTurnoId || !currentMedicoId || !newDate?.value || !newTime?.value){ 
      setMsg(msgTurns, 'Completá turno, fecha y hora', false); 
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
      if(!data.ok) throw new Error(data.error||'No se pudo reprogramar');
      
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
        if (!data.ok) throw new Error(data.error || 'Error buscando');
        
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
      div.innerHTML = `
        <strong>${esc(p.Apellido)}, ${esc(p.Nombre)}</strong><br>
        <small style="color:var(--muted)">DNI: ${esc(p.dni)} | ${esc(p.email)}</small>
      `;
      div.addEventListener('click', ()=>{
        selectedPacienteId.value = p.Id_paciente;
        selectedPacienteInfo.innerHTML = `<strong>${esc(p.Apellido)}, ${esc(p.Nombre)}</strong><br><small>DNI: ${esc(p.dni)} | ${esc(p.Obra_social || 'Sin obra social')}</small>`;
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
    
    if (!turnoTimeSelect) {
      console.error('❌ No se encontró el select turnoTime');
      return;
    }
    
    turnoTimeSelect.innerHTML = `<option value="">Cargando…</option>`;
    turnoTimeSelect.disabled = true;
    
    const medicoId = medicoIdInput?.value;
    const fechaSeleccionada = turnoDate.value;
    
    if (!fechaSeleccionada) {
      turnoTimeSelect.innerHTML = `<option value="">Elegí una fecha primero</option>`;
      return;
    }
    
    if (!medicoId) {
      turnoTimeSelect.innerHTML = `<option value="">Error: no hay médico seleccionado</option>`;
      return;
    }

    try {
      const url = `admin.php?fetch=slots&date=${encodeURIComponent(fechaSeleccionada)}&medico_id=${encodeURIComponent(medicoId)}`;
      
      const res = await fetch(url, {
        headers: { 'Accept': 'application/json' }
      });
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      
      if (!data.ok) {
        throw new Error(data.error || 'Error cargando horarios');
      }
      
      const slots = data.slots || [];
      
      if (slots.length === 0) {
        turnoTimeSelect.innerHTML = `<option value="">No hay horarios disponibles</option>`;
        setMsg(msgModal, 'No hay horarios disponibles para esta fecha', false);
        return;
      }
      
      turnoTimeSelect.innerHTML = `<option value="">Elegí horario…</option>`;
      slots.forEach(slot => {
        const opt = document.createElement('option');
        opt.value = slot;
        opt.textContent = slot;
        turnoTimeSelect.appendChild(opt);
      });
      
      turnoTimeSelect.disabled = false;
      
    } catch (e) {
      console.error('❌ Error cargando horarios:', e);
      setMsg(msgModal, 'Error: ' + e.message, false);
      turnoTimeSelect.innerHTML = `<option value="">Error al cargar</option>`;
    }
  });

  formCreateTurno?.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    setMsg(msgModal, '');

    const pacId = selectedPacienteId.value;
    const date = turnoDate.value;
    const time = turnoTime.value;

    if (!pacId || pacId === '') {
      setMsg(msgModal, '❌ Seleccioná un paciente de la lista', false);
      alert('Debés buscar y hacer click en un paciente de la lista');
      return;
    }
    if (!date || date === '') {
      setMsg(msgModal, '❌ Seleccioná una fecha', false);
      return;
    }
    if (!time || time === '') {
      setMsg(msgModal, '❌ Seleccioná un horario', false);
      return;
    }
    try {
      const fd = new FormData();
      fd.append('action', 'create_turno');
      fd.append('medico_id', $('#turnoMedicoId').value);
      fd.append('paciente_id', pacId);
      fd.append('date', date);
      fd.append('time', time);
      fd.append('csrf_token', csrf);

      const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error creando turno');

      setMsg(msgModal, '✅ ' + (data.msg || 'Turno creado'), true);
      setTimeout(()=>{
        hideModal(modalCreateTurno);
        loadAgenda();
      }, 1000);
    } catch (e) {
      setMsg(msgModal, e.message, false);
    }
  });

  // Cerrar modales
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
      noData.textContent='Seleccioná un médico para ver sus turnos.';
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
      if(noData) noData.textContent='Seleccioná una especialidad primero.';
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
    await loadInit();
  })();

})();