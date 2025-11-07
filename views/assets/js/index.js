// index.js - Sistema de Turnos para Pacientes (MEJORADO)
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

  // ========== ESPECIALIDADES ==========
  async function loadEspecialidades(){
    console.log('🔄 Cargando especialidades...');
    if (!selEsp) return;
    
    selEsp.innerHTML = `<option value="">Cargando…</option>`;
    selEsp.disabled = true;
    
    try {
      const res = await fetch(`${API_BASE}?action=specialties`, {
        headers: {'Accept': 'application/json'}
      });
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if(!data.ok) throw new Error(data.error || 'Error cargando');
      
      especialidadesData = data.items || [];
      selEsp.innerHTML = `<option value="">Elegí especialidad…</option>`;
      
      especialidadesData.forEach(e=>{
        const opt=document.createElement('option');
        opt.value=e.Id_Especialidad;
        opt.textContent=e.Nombre;
        selEsp.appendChild(opt);
      });
      
      selEsp.disabled = false;
      console.log('✅ Especialidades cargadas:', especialidadesData.length);
      
    } catch (error) {
      console.error('❌ Error:', error);
      setMsg('Error cargando especialidades', false);
      selEsp.innerHTML = `<option value="">Error - Refresca</option>`;
    }
  }

  // ========== MÉDICOS ==========
  async function loadMedicosByEsp(espId){
    console.log('🔄 Cargando médicos...');
    if (!selMedico) return;
    
    selMedico.innerHTML = `<option value="">Cargando…</option>`;
    selMedico.disabled = true;
    
    try {
      const res = await fetch(`${API_BASE}?action=doctors&especialidad_id=${espId}`, {
        headers: {'Accept': 'application/json'}
      });
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if(!data.ok) throw new Error(data.error || 'Error');
      
      selMedico.innerHTML = `<option value="">Elegí médico…</option>`;
      (data.items||[]).forEach(m=>{
        const opt=document.createElement('option');
        opt.value=m.Id_medico;
        opt.textContent=`${m.Apellido}, ${m.Nombre}`;
        selMedico.appendChild(opt);
      });
      
      selMedico.disabled = false;
      console.log('✅ Médicos cargados:', data.items?.length || 0);
      
    } catch (error) {
      console.error('❌ Error:', error);
      setMsg('Error cargando médicos', false);
    }
  }

  // ========== INFO MÉDICO ==========
  async function loadMedicoInfo(medicoId){
    console.log('🔄 Cargando info médico...');
    
    try{
      const res = await fetch(`${API_BASE}?action=medico_info&medico_id=${medicoId}`, {
        headers: {'Accept': 'application/json'}
      });
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if(!data.ok) throw new Error(data.error||'Error');
      
      currentMedicoData = data.medico;
      
      // Resetear al mes actual cuando se cambia de médico
      current = new Date();
      current.setDate(1);
      current.setHours(0,0,0,0);
      
      renderCalendar();
      console.log('✅ Info médico cargada');
      
    }catch(e){
      console.error('❌ Error:', e);
      setMsg('Error cargando médico', false);
      currentMedicoData = null;
    }
  }

  // ========== CALENDARIO MEJORADO ==========
  function renderCalendar(){
    if (!calTitle || !calGrid) return;
    
    calTitle.textContent = `${MONTHS[current.getMonth()]} ${current.getFullYear()}`;
    selectedDate = null;
    selectedSlot = null;
    btnReservar.disabled = true;
    
    const calHint = $('#calHint');
    if(!currentMedicoData){
      slotsBox.textContent='Elegí un médico primero…';
      if(calHint) calHint.textContent = '💡 Seleccioná un médico para ver disponibilidad';
    } else {
      slotsBox.textContent='Elegí un día disponible…';
      const horarios = currentMedicoData.horarios || [];
      if (horarios.length === 0) {
        if(calHint) calHint.textContent = '⚠️ Este médico no tiene horarios configurados';
      } else {
        const dias = [...new Set(horarios.map(h => h.Dia_semana))];
        const diasTexto = dias.map(d => d.charAt(0).toUpperCase() + d.slice(1)).join(', ');
        if(calHint) calHint.textContent = `📅 Días disponibles: ${diasTexto}`;
      }
    }

    // Habilitar/deshabilitar botones de navegación
    if (calPrev) calPrev.disabled = (current <= minMonth);
    if (calNext) calNext.disabled = (current >= maxMonth);

    calGrid.innerHTML='';
    const year = current.getFullYear();
    const month = current.getMonth();
    const first = new Date(year, month, 1);
    const last  = new Date(year, month+1, 0);
    
    // Calcular offset para comenzar en lunes
    let offset = (first.getDay() + 6) % 7;
    
    // Días del mes anterior (en gris)
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
      
      // Validaciones
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
      
      // Marcar día de hoy
      if (Utils.toYMD(dateObj) === Utils.toYMD(today)) {
        cell.style.fontWeight = 'bold';
        cell.style.border = '2px solid var(--primary)';
      }
      
      calGrid.appendChild(cell);
    }
    
    // Días del mes siguiente (en gris)
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
    cell?.classList.add('selected');
  }

  async function selectDay(dateObj, cell){
    const dateStr = Utils.toYMD(dateObj);
    
    // Validación adicional
    const validation = Utils.isValidTurnoDate(dateStr);
    if (!validation.valid) {
      alert('⚠️ ' + validation.error);
      return;
    }
    
    selectedDate = dateStr;
    selectedSlot = null;
    btnReservar.disabled = true;
    highlightSelection(cell);
    
    console.log('📅 Día seleccionado:', Utils.formatDateDisplay(selectedDate));
    setMsg(`📅 Fecha: ${Utils.formatDateDisplay(selectedDate)}`);
    
    if(!selMedico.value){
      slotsBox.textContent='Error: sin médico seleccionado';
      return;
    }
    
    await fetchSlots(selectedDate, selMedico.value);
  }

  // ========== SLOTS ==========
  async function fetchSlots(dateStr, medicoId){
    console.log('🔄 Cargando horarios para:', dateStr);
    slotsBox.innerHTML = '<div class="loading" style="padding:20px;text-align:center">⏳ Cargando horarios...</div>';
    btnReservar.disabled = true;
    selectedSlot = null;
    
    try{
      const res = await fetch(`${API_BASE}?action=slots&date=${dateStr}&medico_id=${medicoId}`, {
        headers: {'Accept': 'application/json'}
      });
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if(!data.ok) throw new Error(data.error||'Error');
      
      renderSlots(data.slots||[]);
      
    }catch(e){
      console.error('❌ Error:', e);
      setMsg('Error cargando horarios', false);
      slotsBox.innerHTML = '<div style="padding:20px;color:var(--err);text-align:center">❌ Error al cargar horarios</div>';
    }
  }

  function renderSlots(list){
    slotsBox.innerHTML = '';
    
    if(!Array.isArray(list) || list.length === 0){
      slotsBox.innerHTML = '<div style="padding:20px;color:var(--muted);text-align:center">⚠️ No hay horarios disponibles para esta fecha</div>';
      btnReservar.disabled = true;
      selectedSlot = null;
      console.log('⚠️ Sin slots');
      return;
    }
    
    console.log('✅ Renderizando', list.length, 'slots');
    
    // Agrupar por mañana/tarde/noche
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
          btnReservar.disabled = !selMedico.value;
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
    
    tblBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px"><div class="loading">⏳ Cargando...</div></td></tr>';
    
    try {
      const res = await fetch(`${API_BASE}?action=my_appointments`, {
        headers: {'Accept': 'application/json'}
      });
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      if(!data.ok) throw new Error(data.error || 'Error');
      
      renderAppointments(data.items||[]);
      
    } catch (error) {
      console.error('❌ Error:', error);
      tblBody.innerHTML = `
        <tr>
          <td colspan="5" style="text-align:center;color:var(--err);padding:20px">
            ⚠️ Error al cargar turnos<br>
            <small>${escapeHtml(error.message)}</small>
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
      
      // Verificar si se puede cancelar (24hs antes)
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
        btnReservar.textContent = '✅ Confirmar Reprogramación';
        setMsg('✏️ Seleccioná nueva fecha y horario, luego confirmá');
        
        // Cargar médico actual
        if (medId && selMedico) {
          // Buscar y seleccionar especialidad del médico
          for(let esp of especialidadesData) {
            const resMeds = await fetch(`${API_BASE}?action=doctors&especialidad_id=${esp.Id_Especialidad}`, {headers:{'Accept':'application/json'}});
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
          }
        }
        
        // Scroll al calendario
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
      btnReservar.textContent = 'Reservar';
      
      await loadMyAppointments();
      if (selectedDate && selMedico.value) await fetchSlots(selectedDate, selMedico.value);
      
    }catch(e){
      console.error('❌ Error:', e);
      setMsg('❌ Error al cancelar: ' + e.message, false);
    }
  }

  // ========== RESERVAR/REPROGRAMAR ==========
  btnReservar?.addEventListener('click', async ()=>{
    setMsg('');
    
    if(!selMedico.value){
      setMsg('❌ Elegí un médico', false);
      alert('⚠️ Debés elegir una especialidad y un médico primero');
      return;
    }
    
    if(!selectedDate || !selectedSlot){
      setMsg('❌ Elegí fecha y horario', false);
      alert('⚠️ Debés elegir una fecha y un horario disponible');
      return;
    }

    // Validación final de fecha
    const validation = Utils.isValidTurnoDate(selectedDate);
    if (!validation.valid) {
      setMsg('❌ ' + validation.error, false);
      alert('⚠️ ' + validation.error);
      return;
    }

    const isReschedule = !!selectedApptId;
    const actionText = isReschedule ? 'Reprogramando' : 'Reservando';
    
    // Confirmar acción
    const medicoNombre = selMedico.options[selMedico.selectedIndex].text;
    const espNombre = selEsp.options[selEsp.selectedIndex].text;
    const summary = Utils.generateTurnoSummary(selectedDate, selectedSlot, medicoNombre, espNombre);
    
    if (!confirm(`${isReschedule ? '🔄 Confirmar Reprogramación' : '✅ Confirmar Reserva'}\n\n${summary}\n\n¿Continuar?`)) {
      return;
    }

    console.log(isReschedule ? '🔄 Reprogramando' : '✅ Reservando');
    setMsg(`⏳ ${actionText} turno...`, true);
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
      
      // Mostrar resumen
      alert(`${successMsg}\n\n${summary}\n\n💡 Recordá llegar 10 minutos antes.`);
      
      await loadMyAppointments();
      await fetchSlots(selectedDate, selMedico.value);

      selectedSlot = null;
      btnReservar.disabled = true;
      
      if (isReschedule) {
        selectedApptId = null;
        btnReservar.textContent = 'Reservar';
      }
      
      // Limpiar selección visual
      document.querySelectorAll('.slot.sel').forEach(s => s.classList.remove('sel'));
      
    }catch(e){
      console.error('❌ Error:', e);
      const errorMsg = '❌ Error al ' + (isReschedule ? 'reprogramar' : 'reservar') + ': ' + e.message;
      setMsg(errorMsg, false);
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
    btnReservar.disabled = true;
    currentMedicoData = null;
    slotsBox.innerHTML = '<div style="padding:20px;color:var(--muted);text-align:center">Elegí un médico…</div>';
    
    if(!selEsp.value){
      selMedico.innerHTML=`<option value="">Elegí especialidad…</option>`;
      selMedico.disabled = true;
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
    btnReservar.disabled = true;
    selectedDate = null;
    
    if(!selMedico.value){
      currentMedicoData = null;
      slotsBox.innerHTML = '<div style="padding:20px;color:var(--muted);text-align:center">Elegí un médico…</div>';
      renderCalendar();
      return;
    }
    
    await loadMedicoInfo(selMedico.value);
    slotsBox.innerHTML = '<div style="padding:20px;color:var(--muted);text-align:center">Elegí un día disponible en el calendario…</div>';
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

  function escapeHtml(s){
    return String(s??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // ========== INICIAL ==========
  (async function init(){
    console.log('🚀 Inicializando aplicación de turnos');
    
    try {
      await loadEspecialidades();
      await loadMyAppointments();
      renderCalendar();
      btnReservar.textContent = 'Reservar';
      console.log('✅ Aplicación inicializada');
    } catch (error) {
      console.error('❌ Error:', error);
      setMsg('Error al inicializar', false);
    }
  })();
})();