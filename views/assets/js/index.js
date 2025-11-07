// index.js - Sistema de Turnos para Pacientes (CORREGIDO)
(function(){
  const $ = s => document.querySelector(s);
  const msg = $('#msg');
  const slotsBox = $('#slots');
  const btnReservar = $('#btnReservar');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const calTitle = $('#calTitle');
  const calGrid  = $('#calGrid');
  const calPrev  = $('#calPrev');
  const calNext  = $('#calNext');
  const selEsp   = $('#selEsp');
  const selMedico= $('#selMedico');
  const tblBody  = $('#tblTurnos tbody');

  const API_BASE = window.API_BASE_URL || '../../controllers/turnos_api.php';
  const Utils = window.TurnosUtils;
  
  console.log('🚀 Inicializando sistema de turnos');
  console.log('📡 API Base URL:', API_BASE);

  const MONTHS = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

  // Estado
  let current = new Date();
  current.setHours(0,0,0,0);
  current.setDate(1);
  
  let minMonth = new Date();
  minMonth.setDate(1);
  minMonth.setHours(0,0,0,0);
  
  let maxMonth = new Date();
  maxMonth.setMonth(maxMonth.getMonth() + 3);
  maxMonth.setDate(1);
  maxMonth.setHours(0,0,0,0);

  let selectedDate = null;
  let selectedSlot = null;
  let selectedApptId = null;
  let currentMedicoData = null;
  let especialidadesData = [];

  function setMsg(t, ok=true){
    if(!msg) return;
    msg.textContent = t || '';
    msg.classList.remove('ok','err');
    msg.classList.add(ok?'ok':'err');
  }

  function showError(message) {
    console.error('❌ Error:', message);
    setMsg(message, false);
  }

  function escapeHtml(s){
    return String(s??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // ========== ESPECIALIDADES ==========
  async function loadEspecialidades(){
    console.log('🔄 Cargando especialidades...');
    if (!selEsp) {
      console.error('❌ Elemento selEsp no encontrado');
      return;
    }
    
    selEsp.innerHTML = `<option value="">Cargando…</option>`;
    selEsp.disabled = true;
    
    try {
      const url = `${API_BASE}?action=specialties`;
      console.log('📡 Fetching:', url);
      
      const res = await fetch(url, {
        headers: {'Accept': 'application/json'}
      });
      
      console.log('📥 Response status:', res.status);
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
      }
      
      const data = await res.json();
      console.log('📦 Data recibida:', data);
      
      if(!data.ok) {
        throw new Error(data.error || 'Error al cargar especialidades');
      }
      
      especialidadesData = data.items || [];
      console.log('✅ Especialidades cargadas:', especialidadesData.length);
      
      if (especialidadesData.length === 0) {
        selEsp.innerHTML = `<option value="">No hay especialidades disponibles</option>`;
        showError('No hay especialidades disponibles');
        return;
      }
      
      selEsp.innerHTML = `<option value="">Elegí especialidad…</option>`;
      
      especialidadesData.forEach(e=>{
        const opt=document.createElement('option');
        opt.value=e.Id_Especialidad;
        opt.textContent=e.Nombre;
        selEsp.appendChild(opt);
      });
      
      selEsp.disabled = false;
      
    } catch (error) {
      console.error('❌ Error cargando especialidades:', error);
      showError('Error al cargar especialidades: ' + error.message);
      selEsp.innerHTML = `<option value="">Error - Refresca la página</option>`;
    }
  }

  // ========== MÉDICOS ==========
  async function loadMedicosByEsp(espId){
    console.log('🔄 Cargando médicos para especialidad:', espId);
    if (!selMedico) return;
    
    selMedico.innerHTML = `<option value="">Cargando…</option>`;
    selMedico.disabled = true;
    
    try {
      const url = `${API_BASE}?action=doctors&especialidad_id=${espId}`;
      console.log('📡 Fetching:', url);
      
      const res = await fetch(url, {
        headers: {'Accept': 'application/json'}
      });
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      
      const data = await res.json();
      console.log('📦 Médicos recibidos:', data);
      
      if(!data.ok) {
        throw new Error(data.error || 'Error al cargar médicos');
      }
      
      const medicos = data.items || [];
      
      if (medicos.length === 0) {
        selMedico.innerHTML = `<option value="">No hay médicos disponibles</option>`;
        showError('No hay médicos disponibles para esta especialidad');
        return;
      }
      
      selMedico.innerHTML = `<option value="">Elegí médico…</option>`;
      
      medicos.forEach(m=>{
        const opt=document.createElement('option');
        opt.value=m.Id_medico;
        opt.textContent=`${m.Apellido}, ${m.Nombre}`;
        selMedico.appendChild(opt);
      });
      
      selMedico.disabled = false;
      console.log('✅ Médicos cargados:', medicos.length);
      
    } catch (error) {
      console.error('❌ Error cargando médicos:', error);
      showError('Error al cargar médicos: ' + error.message);
      selMedico.innerHTML = `<option value="">Error</option>`;
    }
  }

  // ========== INFO MÉDICO ==========
  async function loadMedicoInfo(medicoId){
    console.log('🔄 Cargando info del médico:', medicoId);
    
    try{
      const url = `${API_BASE}?action=medico_info&medico_id=${medicoId}`;
      console.log('📡 Fetching:', url);
      
      const res = await fetch(url, {
        headers: {'Accept': 'application/json'}
      });
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      
      const data = await res.json();
      console.log('📦 Info médico:', data);
      
      if(!data.ok) {
        throw new Error(data.error || 'Error al cargar información del médico');
      }
      
      currentMedicoData = data.medico;
      console.log('✅ Médico cargado:', currentMedicoData);
      
      // Resetear al mes actual
      current = new Date();
      current.setDate(1);
      current.setHours(0,0,0,0);
      
      renderCalendar();
      
    }catch(e){
      console.error('❌ Error cargando médico:', e);
      showError('Error al cargar información del médico: ' + e.message);
      currentMedicoData = null;
      renderCalendar();
    }
  }

  // ========== CALENDARIO ==========
  function renderCalendar(){
    if (!calTitle || !calGrid) {
      console.error('❌ Elementos del calendario no encontrados');
      return;
    }
    
    console.log('🗓️ Renderizando calendario');
    
    calTitle.textContent = `${MONTHS[current.getMonth()]} ${current.getFullYear()}`;
    selectedDate = null;
    selectedSlot = null;
    if (btnReservar) btnReservar.disabled = true;
    
    const calHint = $('#calHint');
    if(!currentMedicoData){
      if (slotsBox) slotsBox.textContent='Elegí un médico primero…';
      if(calHint) calHint.textContent = '💡 Seleccioná un médico para ver disponibilidad';
    } else {
      if (slotsBox) slotsBox.textContent='Elegí un día disponible…';
      const horarios = currentMedicoData.horarios || [];
      if (horarios.length === 0) {
        if(calHint) calHint.textContent = '⚠️ Este médico no tiene horarios configurados';
      } else {
        const dias = [...new Set(horarios.map(h => h.Dia_semana))];
        const diasTexto = dias.map(d => d.charAt(0).toUpperCase() + d.slice(1)).join(', ');
        if(calHint) calHint.textContent = `📅 Días disponibles: ${diasTexto}`;
      }
    }

    // Botones de navegación
    if (calPrev) calPrev.disabled = (current <= minMonth);
    if (calNext) calNext.disabled = (current >= maxMonth);

    calGrid.innerHTML='';
    const year = current.getFullYear();
    const month = current.getMonth();
    const first = new Date(year, month, 1);
    const last  = new Date(year, month+1, 0);
    
    // Offset para lunes
    let offset = (first.getDay() + 6) % 7;
    
    // Días del mes anterior
    const prevMonth = new Date(year, month, 0);
    const prevDays = prevMonth.getDate();
    for(let i = offset - 1; i >= 0; i--){ 
      const b = document.createElement('div');
      b.className = 'day muted';
      b.textContent = prevDays - i;
      calGrid.appendChild(b);
    }
    
    const today = Utils.getToday();
    const maxDate = Utils.getMaxDate();
    
    // Días del mes actual
    for(let d = 1; d <= last.getDate(); d++){
      const cell = document.createElement('div');
      cell.className = 'day';
      cell.textContent = d;
      
      const dateObj = new Date(year, month, d);
      dateObj.setHours(0,0,0,0);
      
      const isPast = dateObj < today;
      const isTooFar = dateObj > maxDate;
      const isDayAvailable = currentMedicoData && isDayInSchedule(dateObj);
      const available = !isPast && !isTooFar && isDayAvailable;
      
      if(isPast) {
        cell.classList.add('muted');
        cell.title = 'Fecha pasada';
      } else if(isTooFar) {
        cell.classList.add('muted');
        cell.title = 'Fecha muy lejana (máximo 3 meses)';
      } else if(!currentMedicoData) {
        cell.title = 'Seleccioná un médico primero';
      } else if(!isDayAvailable) {
        cell.title = 'Médico no atiende este día';
      } else {
        cell.classList.add('available');
        cell.title = 'Día disponible - Click para ver horarios';
        cell.addEventListener('click', ()=> selectDay(dateObj, cell));
      }
      
      // Marcar hoy
      if (Utils.toYMD(dateObj) === Utils.toYMD(today)) {
        cell.style.fontWeight = 'bold';
        cell.style.border = '2px solid var(--primary)';
      }
      
      calGrid.appendChild(cell);
    }
    
    // Días del mes siguiente
    const totalCells = offset + last.getDate();
    const remainingCells = totalCells % 7;
    if (remainingCells > 0) {
      const nextDays = 7 - remainingCells;
      for(let i = 1; i <= nextDays; i++) {
        const b = document.createElement('div');
        b.className = 'day muted';
        b.textContent = i;
        calGrid.appendChild(b);
      }
    }
    
    console.log('✅ Calendario renderizado');
  }

  function isDayInSchedule(dateObj) {
    if (!currentMedicoData || !currentMedicoData.horarios) return false;
    const dayName = Utils.getDayName(Utils.toYMD(dateObj));
    return currentMedicoData.horarios.some(h => h.Dia_semana === dayName);
  }

  function highlightSelection(cell){
    document.querySelectorAll('.day.selected').forEach(el=>el.classList.remove('selected'));
    if (cell) cell.classList.add('selected');
  }

  async function selectDay(dateObj, cell){
    const dateStr = Utils.toYMD(dateObj);
    
    const validation = Utils.isValidTurnoDate(dateStr);
    if (!validation.valid) {
      alert('⚠️ ' + validation.error);
      return;
    }
    
    selectedDate = dateStr;
    selectedSlot = null;
    if (btnReservar) btnReservar.disabled = true;
    highlightSelection(cell);
    
    console.log('📅 Día seleccionado:', Utils.formatDateDisplay(selectedDate));
    setMsg(`📅 Fecha: ${Utils.formatDateDisplay(selectedDate)}`);
    
    if(!selMedico || !selMedico.value){
      if (slotsBox) slotsBox.textContent='Error: sin médico seleccionado';
      return;
    }
    
    await fetchSlots(selectedDate, selMedico.value);
  }

  // ========== SLOTS ==========
  async function fetchSlots(dateStr, medicoId){
    console.log('🔄 Cargando horarios para:', dateStr);
    if (!slotsBox) return;
    
    slotsBox.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted)">⏳ Cargando horarios...</div>';
    if (btnReservar) btnReservar.disabled = true;
    selectedSlot = null;
    
    try{
      const url = `${API_BASE}?action=slots&date=${dateStr}&medico_id=${medicoId}`;
      console.log('📡 Fetching:', url);
      
      const res = await fetch(url, {
        headers: {'Accept': 'application/json'}
      });
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      
      const data = await res.json();
      console.log('📦 Slots recibidos:', data);
      
      if(!data.ok) {
        throw new Error(data.error || 'Error al cargar horarios');
      }
      
      renderSlots(data.slots || []);
      
    }catch(e){
      console.error('❌ Error cargando horarios:', e);
      showError('Error al cargar horarios: ' + e.message);
      slotsBox.innerHTML = '<div style="padding:20px;color:var(--err);text-align:center">❌ Error al cargar horarios</div>';
    }
  }

  function renderSlots(list){
    if (!slotsBox) return;
    
    slotsBox.innerHTML = '';
    
    if(!Array.isArray(list) || list.length === 0){
      slotsBox.innerHTML = '<div style="padding:20px;color:var(--muted);text-align:center">⚠️ No hay horarios disponibles para esta fecha</div>';
      if (btnReservar) btnReservar.disabled = true;
      selectedSlot = null;
      console.log('⚠️ Sin slots');
      return;
    }
    
    console.log('✅ Renderizando', list.length, 'slots');
    
    // Agrupar por período
    const morning = list.filter(s => parseInt(s.split(':')[0]) < 13);
    const afternoon = list.filter(s => {
      const h = parseInt(s.split(':')[0]);
      return h >= 13 && h < 18;
    });
    const evening = list.filter(s => parseInt(s.split(':')[0]) >= 18);
    
    function renderGroup(slots, title, icon) {
      if (slots.length === 0) return;
      
      const group = document.createElement('div');
      group.style.marginBottom = '16px';
      
      const header = document.createElement('div');
      header.style.cssText = 'color:var(--primary);font-size:13px;font-weight:600;margin-bottom:8px';
      header.textContent = `${icon} ${title}`;
      group.appendChild(header);
      
      const container = document.createElement('div');
      container.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px';
      
      slots.forEach(hhmm=>{
        const b = document.createElement('button');
        b.type='button';
        b.className='slot';
        b.textContent = Utils.formatHour12(hhmm);
        b.dataset.time = hhmm;
        b.addEventListener('click', ()=>{
          selectedSlot = hhmm;
          document.querySelectorAll('.slot').forEach(x=>x.classList.remove('sel'));
          b.classList.add('sel');
          if (btnReservar && selMedico && selMedico.value) {
            btnReservar.disabled = false;
          }
          setMsg(`🕐 Horario: ${Utils.formatHour12(hhmm)}`);
          console.log('🕐 Slot seleccionado:', hhmm);
        });
        container.appendChild(b);
      });
      
      group.appendChild(container);
      slotsBox.appendChild(group);
    }
    
    renderGroup(morning, 'Mañana', '🌅');
    renderGroup(afternoon, 'Tarde', '☀️');
    renderGroup(evening, 'Noche', '🌙');
  }

  // ========== MIS TURNOS ==========
  async function loadMyAppointments(){
    console.log('🔄 Cargando mis turnos...');
    if (!tblBody) return;
    
    tblBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px"><div style="color:var(--muted)">⏳ Cargando...</div></td></tr>';
    
    try {
      const url = `${API_BASE}?action=my_appointments`;
      console.log('📡 Fetching:', url);
      
      const res = await fetch(url, {
        headers: {'Accept': 'application/json'}
      });
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      
      const data = await res.json();
      console.log('📦 Turnos recibidos:', data);
      
      if(!data.ok) {
        throw new Error(data.error || 'Error al cargar turnos');
      }
      
      renderAppointments(data.items || []);
      
    } catch (error) {
      console.error('❌ Error cargando turnos:', error);
      tblBody.innerHTML = `
        <tr>
          <td colspan="5" style="text-align:center;color:var(--err);padding:20px">
            ⚠️ ${escapeHtml(error.message)}
          </td>
        </tr>
      `;
    }
  }

  function renderAppointments(rows){
    if (!tblBody) return;
    tblBody.innerHTML='';
    
    if (rows.length === 0) {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td colspan="5" style="text-align:center;padding:40px 20px">
          <div style="color:var(--muted);font-size:48px;margin-bottom:12px">📅</div>
          <div style="color:var(--text);font-weight:600;margin-bottom:8px">
            No tenés turnos próximos
          </div>
          <div style="color:var(--muted);font-size:14px">
            Reservá tu primera consulta usando el calendario
          </div>
        </td>
      `;
      tblBody.appendChild(tr);
      return;
    }
    
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      
      const [fecha, hora] = r.fecha.split(' ');
      const canCancel = Utils.canCancelTurno(fecha, hora);
      
      const acciones = (r.estado === 'reservado')
        ? `
          <button class="btn ghost btn-cancel" data-id="${r.Id_turno}" ${!canCancel ? 'disabled title="Debe cancelar con 24hs de anticipación"' : ''}>
            ${canCancel ? '❌ Cancelar' : '🔒 Cancelar'}
          </button>
          <button class="btn ghost btn-reprog" data-id="${r.Id_turno}" data-med="${r.Id_medico||''}">
            🔄 Reprogramar
          </button>
        `
        : '<span style="color:var(--muted);font-size:12px">Sin acciones disponibles</span>';
      
      tr.innerHTML = `
        <td>
          <div style="font-weight:600">${escapeHtml(r.fecha_fmt||'')}</div>
          <div style="font-size:12px;color:var(--muted)">${Utils.formatDateDisplay(fecha)}</div>
        </td>
        <td>${escapeHtml(r.medico||'')}</td>
        <td>${escapeHtml(r.especialidad||'')}</td>
        <td><span class="badge ${r.estado==='reservado'?'ok':'warn'}">${escapeHtml(r.estado||'')}</span></td>
        <td class="row-actions">${acciones}</td>
      `;
      tblBody.appendChild(tr);
    });

    tblBody.querySelectorAll('.btn-cancel').forEach(b=>{
      b.addEventListener('click', ()=> onCancel(b.dataset.id));
    });
    
    tblBody.querySelectorAll('.btn-reprog').forEach(b=>{
      b.addEventListener('click', async ()=> {
        selectedApptId = b.dataset.id;
        const medId = b.dataset.med || '';
        
        console.log('🔄 Modo reprogramación activado');
        if (btnReservar) btnReservar.textContent = '✅ Confirmar Reprogramación';
        setMsg('✏️ Seleccioná nueva fecha y horario');
        
        // Cargar médico
        if (medId && selMedico) {
          for(let esp of especialidadesData) {
            try {
              const resMeds = await fetch(`${API_BASE}?action=doctors&especialidad_id=${esp.Id_Especialidad}`, {
                headers:{'Accept':'application/json'}
              });
              const dataMeds = await resMeds.json();
              if(dataMeds.ok) {
                const found = dataMeds.items.find(m => m.Id_medico == medId);
                if(found) {
                  selEsp.value = esp.Id_Especialidad;
                  await loadMedicosByEsp(esp.Id_Especialidad);
                  selMedico.value = medId;
                  await loadMedicoInfo(medId);
                  break;
                }
              }
            } catch (e) {
              console.error('Error buscando médico:', e);
            }
          }
        }
        
        document.querySelector('.card')?.scrollIntoView({behavior:'smooth', block:'start'});
      });
    });
  }

  async function onCancel(turnoId){
    if(!confirm('⚠️ ¿Estás seguro de cancelar este turno?\n\nEsta acción no se puede deshacer.')) return;
    
    console.log('🔄 Cancelando turno:', turnoId);
    setMsg('⏳ Cancelando turno...', true);
    
    try{
      const fd = new FormData();
      fd.append('action','cancel');
      fd.append('turno_id', turnoId);
      fd.append('csrf_token', csrf);
      
      const res = await fetch(API_BASE, {
        method:'POST',
        body:fd,
        headers:{'Accept':'application/json', 'X-CSRF-Token':csrf}
      });
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if(!data.ok) throw new Error(data.error||'Error');
      
      setMsg('✅ Turno cancelado exitosamente', true);
      selectedApptId = null;
      if (btnReservar) btnReservar.textContent = 'Reservar';
      
      await loadMyAppointments();
      if (selectedDate && selMedico && selMedico.value) {
        await fetchSlots(selectedDate, selMedico.value);
      }
      
    }catch(e){
      console.error('❌ Error:', e);
      showError('Error al cancelar: ' + e.message);
    }
  }

  // ========== RESERVAR/REPROGRAMAR ==========
  btnReservar?.addEventListener('click', async ()=>{
    setMsg('');
    
    if(!selMedico || !selMedico.value){
      showError('Elegí un médico');
      alert('⚠️ Debés elegir una especialidad y un médico primero');
      return;
    }
    
    if(!selectedDate || !selectedSlot){
      showError('Elegí fecha y horario');
      alert('⚠️ Debés elegir una fecha y un horario disponible');
      return;
    }

    const validation = Utils.isValidTurnoDate(selectedDate);
    if (!validation.valid) {
      showError(validation.error);
      alert('⚠️ ' + validation.error);
      return;
    }

    const isReschedule = !!selectedApptId;
    
    const medicoNombre = selMedico.options[selMedico.selectedIndex].text;
    const espNombre = selEsp.options[selEsp.selectedIndex].text;
    const summary = Utils.generateTurnoSummary(selectedDate, selectedSlot, medicoNombre, espNombre);
    
    if (!confirm(`${isReschedule ? '🔄 Confirmar Reprogramación' : '✅ Confirmar Reserva'}\n\n${summary}\n\n¿Continuar?`)) {
      return;
    }

    console.log(isReschedule ? '🔄 Reprogramando' : '✅ Reservando');
    setMsg(`⏳ ${isReschedule ? 'Reprogramando' : 'Reservando'} turno...`, true);
    btnReservar.disabled = true;

    try{
      const fd=new FormData();
      fd.append('date', selectedDate);
      fd.append('time', selectedSlot);
      fd.append('medico_id', selMedico.value);
      fd.append('csrf_token', csrf);

      if (isReschedule) {
        fd.append('action','reschedule');
        fd.append('turno_id', selectedApptId);
      } else {
        fd.append('action','book');
      }

      const res = await fetch(API_BASE, {
        method:'POST',
        body:fd,
        headers:{'Accept':'application/json', 'X-CSRF-Token':csrf}
      });
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if(!data.ok) throw new Error(data.error|| 'Error');

      const successMsg = isReschedule ? '✅ Turno reprogramado exitosamente' : '✅ Turno reservado exitosamente';
      setMsg(successMsg, true);
      
      alert(`${successMsg}\n\n${summary}\n\n💡 Recordá llegar 10 minutos antes.`);
      
      await loadMyAppointments();
      await fetchSlots(selectedDate, selMedico.value);

      selectedSlot = null;
      btnReservar.disabled = true;
      
      if (isReschedule) {
        selectedApptId = null;
        btnReservar.textContent = 'Reservar';
      }
      
      document.querySelectorAll('.slot.sel').forEach(s => s.classList.remove('sel'));
      
    }catch(e){
      console.error('❌ Error:', e);
      const errorMsg = '❌ Error al ' + (isReschedule ? 'reprogramar' : 'reservar') + ': ' + e.message;
      showError(errorMsg);
      alert(errorMsg);
      btnReservar.disabled = false;
    }
  });

  // ========== EVENTOS ==========
  selEsp?.addEventListener('change', async ()=>{
    console.log('🔄 Especialidad:', selEsp.value);
    setMsg('');
    selectedDate = null;
    selectedSlot = null;
    if (btnReservar) btnReservar.disabled = true;
    currentMedicoData = null;
    if (slotsBox) slotsBox.innerHTML = '<div style="padding:20px;color:var(--muted);text-align:center">Elegí un médico…</div>';
    
    if(!selEsp.value){
      if (selMedico) {
        selMedico.innerHTML=`<option value="">Elegí especialidad…</option>`;
        selMedico.disabled = true;
      }
      renderCalendar();
      return;
    }
    
    await loadMedicosByEsp(selEsp.value);
    renderCalendar();
  });

  selMedico?.addEventListener('change', async ()=>{
    console.log('🔄 Médico:', selMedico.value);
    setMsg('');
    selectedSlot = null;
    if (btnReservar) btnReservar.disabled = true;
    selectedDate = null;
    
    if(!selMedico.value){
      currentMedicoData = null;
      if (slotsBox) slotsBox.innerHTML = '<div style="padding:20px;color:var(--muted);text-align:center">Elegí un médico…</div>';
      renderCalendar();
      return;
    }
    
    await loadMedicoInfo(selMedico.value);
    if (slotsBox) slotsBox.innerHTML = '<div style="padding:20px;color:var(--muted);text-align:center">Elegí un día disponible en el calendario…</div>';
  });

  // Navegación calendario
  calPrev?.addEventListener('click', ()=>{
    if (current <= minMonth) return;
    console.log('⬅️ Mes anterior');
    current.setMonth(current.getMonth() - 1);
    renderCalendar();
  });
  
  calNext?.addEventListener('click', ()=>{
    if (current >= maxMonth) return;
    console.log('➡️ Mes siguiente');
    current.setMonth(current.getMonth() + 1);
    renderCalendar();
  });

  // ========== INICIAL ==========
  (async function init(){
    console.log('🚀 Inicializando aplicación de turnos');
    console.log('🔧 Verificando elementos DOM...');
    
    // Verificar elementos críticos
    const elementos = {
      'selEsp': selEsp,
      'selMedico': selMedico,
      'calGrid': calGrid,
      'slotsBox': slotsBox,
      'btnReservar': btnReservar,
      'tblBody': tblBody,
      'msg': msg
    };
    
    let faltantes = [];
    for (const [nombre, elemento] of Object.entries(elementos)) {
      if (!elemento) {
        faltantes.push(nombre);
        console.error(`❌ Elemento ${nombre} no encontrado`);
      } else {
        console.log(`✅ Elemento ${nombre} encontrado`);
      }
    }
    
    if (faltantes.length > 0) {
      const errorMsg = `Error: Faltan elementos DOM: ${faltantes.join(', ')}`;
      console.error('❌', errorMsg);
      if (msg) showError(errorMsg);
      return;
    }
    
    try {
      console.log('📡 Cargando datos iniciales...');
      await loadEspecialidades();
      await loadMyAppointments();
      renderCalendar();
      if (btnReservar) btnReservar.textContent = 'Reservar';
      console.log('✅ Aplicación inicializada correctamente');
    } catch (error) {
      console.error('❌ Error al inicializar:', error);
      showError('Error al inicializar: ' + error.message);
    }
  })();
})();