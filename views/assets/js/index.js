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

  const MONTHS = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  const DIAS_ES = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];

  // Estado UI
  let current = new Date(); current.setHours(0,0,0,0); current.setDate(1);
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

  function toYMD(d){ 
    const y=d.getFullYear(); 
    const m=String(d.getMonth()+1).padStart(2,'0'); 
    const dd=String(d.getDate()).padStart(2,'0'); 
    return `${y}-${m}-${dd}`; 
  }
  
  function isPast(d){ 
    const t=new Date(); 
    t.setHours(0,0,0,0); 
    return d<t; 
  }
  
  function isDayAvailable(dateObj){
    if(!currentMedicoData || !currentMedicoData.horarios) return false;
    const dayName = DIAS_ES[dateObj.getDay()];
    return currentMedicoData.horarios.some(h => h.Dia_semana === dayName);
  }

  async function loadEspecialidades(){
    selEsp.innerHTML = `<option value="">Cargando…</option>`;
    selMedico.innerHTML = `<option value="">Elegí especialidad…</option>`;
    selMedico.disabled = true;
    
    const res = await fetch('turnos_api.php?action=specialties',{headers:{'Accept':'application/json'}});
    const data = await res.json();
    if(!data.ok){ setMsg(data.error||'Error cargando especialidades', false); return; }
    
    especialidadesData = data.items || [];
    selEsp.innerHTML = `<option value="">Elegí especialidad…</option>`;
    especialidadesData.forEach(e=>{
      const opt=document.createElement('option');
      opt.value=e.Id_Especialidad; 
      opt.textContent=e.Nombre;
      selEsp.appendChild(opt);
    });
  }

  async function loadMedicosByEsp(espId){
    selMedico.innerHTML = `<option value="">Cargando…</option>`;
    selMedico.disabled = true;
    
    const res = await fetch(`turnos_api.php?action=doctors&especialidad_id=${encodeURIComponent(espId)}`,{headers:{'Accept':'application/json'}});
    const data = await res.json();
    if(!data.ok){ setMsg(data.error||'Error cargando médicos', false); return; }
    
    selMedico.innerHTML = `<option value="">Elegí médico…</option>`;
    (data.items||[]).forEach(m=>{
      const opt=document.createElement('option');
      opt.value=m.Id_medico; 
      opt.textContent=`${m.Apellido}, ${m.Nombre}`;
      selMedico.appendChild(opt);
    });
    selMedico.disabled = false;
  }

  async function loadMedicoInfo(medicoId){
    try{
      const res = await fetch(`turnos_api.php?action=medico_info&medico_id=${encodeURIComponent(medicoId)}`,{headers:{'Accept':'application/json'}});
      const data = await res.json();
      if(!data.ok) throw new Error(data.error||'Error cargando info del médico');
      currentMedicoData = data.medico;
      renderCalendar();
    }catch(e){
      setMsg(e.message, false);
      currentMedicoData = null;
    }
  }

  async function loadMyAppointments(){
    const res = await fetch('turnos_api.php?action=my_appointments',{headers:{'Accept':'application/json'}});
    const data = await res.json();
    if(!data.ok){ setMsg(data.error||'Error cargando mis turnos', false); return; }
    renderAppointments(data.items||[]);
  }

  function renderAppointments(rows){
    tblBody.innerHTML='';
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      const acciones = (r.estado === 'reservado')
        ? `<button class="btn ghost btn-cancel" data-id="${r.Id_turno}">Cancelar</button>
           <button class="btn ghost btn-reprog" data-id="${r.Id_turno}" data-med="${r.Id_medico||''}">Elegir nuevo horario</button>`
        : '';
      tr.innerHTML = `
        <td>${escapeHtml(r.fecha_fmt||'')}</td>
        <td>${escapeHtml(r.medico||'')}</td>
        <td>${escapeHtml(r.especialidad||'')}</td>
        <td><span class="badge ${r.estado==='reservado'?'ok':'warn'}">${escapeHtml(r.estado||'')}</span></td>
        <td class="row-actions">${acciones}</td>`;
      tblBody.appendChild(tr);
    });

    tblBody.querySelectorAll('.btn-cancel').forEach(b=>{
      b.addEventListener('click', ()=> onCancel(b.dataset.id));
    });
    
    tblBody.querySelectorAll('.btn-reprog').forEach(b=>{
      b.addEventListener('click', async ()=> {
        selectedApptId = b.dataset.id;
        const medId = b.dataset.med || '';
        
        if (medId) {
          // Buscar especialidad del médico
          try {
            const res = await fetch(`turnos_api.php?action=medico_info&medico_id=${medId}`,{headers:{'Accept':'application/json'}});
            const data = await res.json();
            
            if(data.ok && data.medico) {
              // Obtener médico completo para encontrar su especialidad
              const resDoctors = await fetch('turnos_api.php?action=specialties',{headers:{'Accept':'application/json'}});
              const dataEsp = await resDoctors.json();
              
              // Cargar todas las especialidades si no están cargadas
              if(dataEsp.ok) {
                for(let esp of dataEsp.items) {
                  const resMeds = await fetch(`turnos_api.php?action=doctors&especialidad_id=${esp.Id_Especialidad}`,{headers:{'Accept':'application/json'}});
                  const dataMeds = await resMeds.json();
                  if(dataMeds.ok) {
                    const medicoEncontrado = dataMeds.items.find(m => m.Id_medico == medId);
                    if(medicoEncontrado) {
                      // Seleccionar especialidad
                      selEsp.value = esp.Id_Especialidad;
                      await loadMedicosByEsp(esp.Id_Especialidad);
                      // Seleccionar médico
                      selMedico.value = medId;
                      await loadMedicoInfo(medId);
                      break;
                    }
                  }
                }
              }
            }
          } catch(e) {
            console.error('Error al cargar médico:', e);
          }
        }
        
        btnReservar.textContent = 'Confirmar reprogramación';
        setMsg('✏️ Seleccioná un día y horario nuevo, luego confirmá la reprogramación');
        
        if (selectedDate && selMedico.value) {
          await fetchSlots(selectedDate, selMedico.value);
        }
      });
    });
  }

  function renderCalendar(){
    calTitle.textContent = `${MONTHS[current.getMonth()]} ${current.getFullYear()}`;
    selectedDate = null; 
    selectedSlot=null; 
    btnReservar.disabled=true;
    
    const calHint = $('#calHint');
    if(!currentMedicoData){
      slotsBox.textContent='Elegí un médico primero…';
      if(calHint) calHint.textContent = 'Seleccioná un médico para ver sus días disponibles';
    } else {
      slotsBox.textContent='Elegí un día disponible…';
      const diasTexto = currentMedicoData.dias_disponibles.split(',').map(d=>d.trim()).join(', ');
      if(calHint) calHint.textContent = `Días habilitados: ${diasTexto}`;
    }

    calGrid.innerHTML='';
    const year = current.getFullYear();
    const month = current.getMonth();
    const first = new Date(year, month, 1);
    const last  = new Date(year, month+1, 0);
    let offset = (first.getDay()+6)%7;
    
    for(let i=0;i<offset;i++){ 
      const b=document.createElement('div'); 
      b.className='day muted'; 
      calGrid.appendChild(b); 
    }
    
    for(let d=1; d<=last.getDate(); d++){
      const cell = document.createElement('div');
      cell.className='day';
      cell.textContent=d;
      const dateObj = new Date(year, month, d); 
      dateObj.setHours(0,0,0,0);
      
      const available = !isPast(dateObj) && isDayAvailable(dateObj);
      
      if(available){ 
        cell.classList.add('available'); 
        cell.addEventListener('click', ()=> selectDay(dateObj, cell)); 
      }
      calGrid.appendChild(cell);
    }
  }

  function highlightSelection(cell){ 
    document.querySelectorAll('.day.selected').forEach(el=>el.classList.remove('selected')); 
    cell?.classList.add('selected'); 
  }

  async function selectDay(dateObj, cell){
    selectedDate = toYMD(dateObj);
    selectedSlot = null;
    btnReservar.disabled = true;
    highlightSelection(cell);
    
    if(!selMedico.value){ 
      slotsBox.textContent='Elegí especialidad y médico…'; 
      return; 
    }
    await fetchSlots(selectedDate, selMedico.value);
  }

  function renderSlots(list){
    slotsBox.innerHTML = '';
    if(!Array.isArray(list) || list.length===0){
      slotsBox.textContent = 'No hay horarios disponibles';
      btnReservar.disabled = true;
      selectedSlot = null;
      return;
    }
    list.forEach(hhmm=>{
      const b = document.createElement('button');
      b.type='button';
      b.className='slot';
      b.textContent=hhmm;
      b.addEventListener('click', ()=>{
        selectedSlot = hhmm;
        document.querySelectorAll('.slot').forEach(x=>x.classList.remove('sel'));
        b.classList.add('sel');
        btnReservar.disabled = !selMedico.value;
        setMsg('');
      });
      slotsBox.appendChild(b);
    });
  }

  async function fetchSlots(dateStr, medicoId){
    slotsBox.textContent='Cargando…';
    btnReservar.disabled = true;
    selectedSlot = null;
    try{
      const res = await fetch(`turnos_api.php?action=slots&date=${encodeURIComponent(dateStr)}&medico_id=${encodeURIComponent(medicoId)}`,{headers:{'Accept':'application/json'}});
      const data = await res.json();
      if(!data.ok) throw new Error(data.error||'Error al cargar');
      renderSlots(data.slots||[]);
    }catch(e){
      setMsg(e.message,false);
      slotsBox.textContent='Error al cargar horarios';
    }
  }

  async function onCancel(turnoId){
    if(!confirm('¿Estás seguro de cancelar este turno?')) return;
    try{
      const fd = new FormData();
      fd.append('action','cancel');
      fd.append('turno_id', turnoId);
      fd.append('csrf_token', csrf);
      
      const res = await fetch('turnos_api.php',{method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':csrf}});
      const data = await res.json();
      if(!data.ok) throw new Error(data.error||'No se pudo cancelar');
      
      setMsg('✅ Turno cancelado', true);
      selectedApptId = null;
      btnReservar.textContent = 'Reservar';
      await loadMyAppointments();
      if (selectedDate && selMedico.value) await fetchSlots(selectedDate, selMedico.value);
    }catch(e){
      setMsg(e.message,false);
    }
  }

  btnReservar?.addEventListener('click', async ()=>{
    setMsg('');
    if(!selMedico.value){ setMsg('Elegí un médico', false); return; }
    if(!selectedDate || !selectedSlot){ setMsg('Elegí un día y un horario', false); return; }

    const isReschedule = !!selectedApptId;

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

      const res = await fetch('turnos_api.php',{method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':csrf}});
      const data = await res.json();
      if(!data.ok) throw new Error(data.error|| (isReschedule ? 'No se pudo reprogramar' : 'No se pudo reservar'));

      setMsg(isReschedule ? '✅ Turno reprogramado' : '✅ Turno reservado', true);
      await loadMyAppointments();
      await fetchSlots(selectedDate, selMedico.value);

      btnReservar.disabled = true; 
      selectedSlot=null;
      if (isReschedule) {
        selectedApptId = null;
        btnReservar.textContent = 'Reservar';
      }
    }catch(e){ setMsg(e.message,false); }
  });

  // Selectores
  selEsp?.addEventListener('change', async ()=>{
    setMsg('');
    selectedDate=null; 
    selectedSlot=null; 
    btnReservar.disabled=true;
    currentMedicoData = null;
    slotsBox.textContent='Elegí un médico…';
    
    if(!selEsp.value){ 
      selMedico.innerHTML=`<option value="">Elegí especialidad…</option>`; 
      selMedico.disabled=true; 
      renderCalendar();
      return; 
    }
    await loadMedicosByEsp(selEsp.value);
    renderCalendar();
  });

  selMedico?.addEventListener('change', async ()=>{
    setMsg('');
    selectedSlot=null; 
    btnReservar.disabled=true;
    selectedDate=null;
    
    if(!selMedico.value){
      currentMedicoData = null;
      slotsBox.textContent = 'Elegí un médico…';
      renderCalendar();
      return;
    }
    
    await loadMedicoInfo(selMedico.value);
    slotsBox.textContent = 'Elegí un día disponible…';
  });

  // Calendario navegación
  calPrev?.addEventListener('click', ()=>{ current.setMonth(current.getMonth()-1); renderCalendar(); });
  calNext?.addEventListener('click', ()=>{ current.setMonth(current.getMonth()+1); renderCalendar(); });

  function escapeHtml(s){ return String(s??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // Inicial
  (async function init(){
    await loadEspecialidades();
    await loadMyAppointments();
    renderCalendar();
    btnReservar.textContent = 'Reservar';
  })();
})();