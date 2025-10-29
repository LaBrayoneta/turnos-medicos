// views/assets/js/index.js - VERSIÓN CORREGIDA
// @ts-nocheck
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

  // ✅ SOLUCIÓN: Obtener la URL base de la API desde la variable global
  const API_BASE = window.API_BASE_URL || '../../controllers/turnos_api.php';
  
  console.log('🚀 Inicializando index.js');
  console.log('📡 API Base URL:', API_BASE);

  const MONTHS = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  const DIAS_ES = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];

  // Estado UI
  let current = new Date(); 
  current.setHours(0,0,0,0); 
  current.setDate(1);
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

  // ✅ MEJORADO: Carga de especialidades con mejor manejo de errores
  async function loadEspecialidades(){
    console.log('🔄 Cargando especialidades...');
    
    if (!selEsp) {
      console.error('❌ Elemento selEsp no encontrado');
      return;
    }
    
    selEsp.innerHTML = `<option value="">Cargando…</option>`;
    selEsp.disabled = true;
    
    if (selMedico) {
      selMedico.innerHTML = `<option value="">Elegí especialidad…</option>`;
      selMedico.disabled = true;
    }
    
    try {
      const url = `${API_BASE}?action=specialties`;
      console.log('📡 Fetching especialidades:', url);
      
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache'
        }
      });
      
      console.log('📥 Response status:', res.status, res.statusText);
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status} ${res.statusText}`);
      }
      
      const contentType = res.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        const text = await res.text();
        console.error('❌ Respuesta no es JSON:', text.substring(0, 200));
        throw new Error('La respuesta del servidor no es JSON válido');
      }
      
      const data = await res.json();
      console.log('✅ Especialidades recibidas:', data);
      
      if(!data.ok){ 
        throw new Error(data.error || 'Error cargando especialidades');
      }
      
      especialidadesData = data.items || [];
      selEsp.innerHTML = `<option value="">Elegí especialidad…</option>`;
      
      especialidadesData.forEach(e=>{
        const opt=document.createElement('option');
        opt.value=e.Id_Especialidad; 
        opt.textContent=e.Nombre;
        selEsp.appendChild(opt);
      });
      
      selEsp.disabled = false;
      console.log('✅ Especialidades cargadas en el select:', especialidadesData.length);
      
    } catch (error) {
      console.error('❌ Error cargando especialidades:', error);
      setMsg('Error de conexión: ' + error.message, false);
      selEsp.innerHTML = `<option value="">Error al cargar - Refresca la página</option>`;
      
      // ✅ Mostrar alerta para el usuario
      setTimeout(() => {
        if (confirm('Error al cargar especialidades. ¿Deseas reintentar?')) {
          loadEspecialidades();
        }
      }, 1000);
    }
  }

  // ✅ MEJORADO: Carga de médicos con mejor manejo de errores
  async function loadMedicosByEsp(espId){
    console.log('🔄 Cargando médicos de especialidad:', espId);
    
    if (!selMedico) return;
    
    selMedico.innerHTML = `<option value="">Cargando…</option>`;
    selMedico.disabled = true;
    
    try {
      const url = `${API_BASE}?action=doctors&especialidad_id=${encodeURIComponent(espId)}`;
      console.log('📡 Fetching médicos:', url);
      
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache'
        }
      });
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      console.log('✅ Médicos recibidos:', data);
      
      if(!data.ok){ 
        throw new Error(data.error || 'Error cargando médicos');
      }
      
      selMedico.innerHTML = `<option value="">Elegí médico…</option>`;
      
      (data.items||[]).forEach(m=>{
        const opt=document.createElement('option');
        opt.value=m.Id_medico; 
        opt.textContent=`${m.Apellido}, ${m.Nombre}`;
        selMedico.appendChild(opt);
      });
      
      selMedico.disabled = false;
      console.log('✅ Médicos cargados en el select:', data.items?.length || 0);
      
    } catch (error) {
      console.error('❌ Error cargando médicos:', error);
      setMsg('Error al cargar médicos: ' + error.message, false);
      selMedico.innerHTML = `<option value="">Error al cargar</option>`;
    }
  }

  // ✅ MEJORADO: Carga de info del médico
  async function loadMedicoInfo(medicoId){
    console.log('🔄 Cargando info del médico:', medicoId);
    
    try{
      const url = `${API_BASE}?action=medico_info&medico_id=${encodeURIComponent(medicoId)}`;
      console.log('📡 Fetching info médico:', url);
      
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache'
        }
      });
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      console.log('✅ Info del médico recibida:', data);
      
      if(!data.ok) throw new Error(data.error||'Error cargando info del médico');
      
      currentMedicoData = data.medico;
      renderCalendar();
      console.log('✅ Calendario renderizado con horarios del médico');
      
    }catch(e){
      console.error('❌ Error cargando info del médico:', e);
      setMsg('Error: ' + e.message, false);
      currentMedicoData = null;
    }
  }

  // ✅ MEJORADO: Carga de turnos
  async function loadMyAppointments(){
    console.log('🔄 Cargando mis turnos...');
    
    if (!tblBody) return;
    
    try {
      const url = `${API_BASE}?action=my_appointments`;
      console.log('📡 Fetching turnos:', url);
      
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache'
        }
      });
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      console.log('✅ Mis turnos recibidos:', data);
      
      if(!data.ok){ 
        throw new Error(data.error || 'Error cargando turnos');
      }
      
      renderAppointments(data.items||[]);
      
    } catch (error) {
      console.error('❌ Error cargando mis turnos:', error);
      setMsg('Error al cargar turnos: ' + error.message, false);
      
      // Mostrar mensaje en la tabla
      if (tblBody) {
        tblBody.innerHTML = `
          <tr>
            <td colspan="5" style="text-align:center;color:var(--err);padding:20px">
              ⚠️ Error al cargar turnos<br>
              <small>${error.message}</small>
            </td>
          </tr>
        `;
      }
    }
  }

  function renderAppointments(rows){
    if (!tblBody) return;
    tblBody.innerHTML='';
    
    if (rows.length === 0) {
      const tr = document.createElement('tr');
      tr.innerHTML = '<td colspan="5" style="text-align:center;color:var(--muted)">No tenés turnos registrados</td>';
      tblBody.appendChild(tr);
      return;
    }
    
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
        
        console.log('🔄 Reprogramando turno:', selectedApptId, 'Médico:', medId);
        
        if (medId) {
          try {
            const url = `${API_BASE}?action=medico_info&medico_id=${medId}`;
            const res = await fetch(url, {headers:{'Accept':'application/json'}});
            const data = await res.json();
            
            if(data.ok && data.medico) {
              const resEsp = await fetch(`${API_BASE}?action=specialties`, {headers:{'Accept':'application/json'}});
              const dataEsp = await resEsp.json();
              
              if(dataEsp.ok) {
                for(let esp of dataEsp.items) {
                  const resMeds = await fetch(`${API_BASE}?action=doctors&especialidad_id=${esp.Id_Especialidad}`, {headers:{'Accept':'application/json'}});
                  const dataMeds = await resMeds.json();
                  if(dataMeds.ok) {
                    const medicoEncontrado = dataMeds.items.find(m => m.Id_medico == medId);
                    if(medicoEncontrado) {
                      selEsp.value = esp.Id_Especialidad;
                      await loadMedicosByEsp(esp.Id_Especialidad);
                      selMedico.value = medId;
                      await loadMedicoInfo(medId);
                      break;
                    }
                  }
                }
              }
            }
          } catch(e) {
            console.error('❌ Error al cargar médico para reprogramación:', e);
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
    if (!calTitle || !calGrid) return;
    
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
      const diasTexto = currentMedicoData.dias_disponibles ? 
        currentMedicoData.dias_disponibles.split(',').map(d=>d.trim()).join(', ') : 
        'No configurados';
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
    console.log('✅ Calendario renderizado');
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
    
    console.log('📅 Día seleccionado:', selectedDate);
    
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
      console.log('⚠️ No hay slots disponibles');
      return;
    }
    console.log('✅ Renderizando', list.length, 'slots');
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
        console.log('🕐 Slot seleccionado:', hhmm);
      });
      slotsBox.appendChild(b);
    });
  }

  async function fetchSlots(dateStr, medicoId){
    console.log('🔄 Cargando slots para:', dateStr, 'médico:', medicoId);
    slotsBox.textContent='Cargando…';
    btnReservar.disabled = true;
    selectedSlot = null;
    
    try{
      const url = `${API_BASE}?action=slots&date=${encodeURIComponent(dateStr)}&medico_id=${encodeURIComponent(medicoId)}`;
      console.log('📡 Fetching slots:', url);
      
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache'
        }
      });
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      console.log('✅ Slots recibidos:', data);
      
      if(!data.ok) throw new Error(data.error||'Error al cargar horarios');
      
      renderSlots(data.slots||[]);
      
    }catch(e){
      console.error('❌ Error cargando slots:', e);
      setMsg('Error: ' + e.message, false);
      slotsBox.textContent='Error al cargar horarios';
    }
  }

  async function onCancel(turnoId){
    if(!confirm('¿Estás seguro de cancelar este turno?')) return;
    
    console.log('🔄 Cancelando turno:', turnoId);
    
    try{
      const fd = new FormData();
      fd.append('action','cancel');
      fd.append('turno_id', turnoId);
      fd.append('csrf_token', csrf);
      
      const url = `${API_BASE}`;
      console.log('📡 Posting to:', url);
      
      const res = await fetch(url, {
        method:'POST', 
        body:fd, 
        headers:{
          'Accept':'application/json',
          'X-CSRF-Token':csrf
        }
      });
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      console.log('✅ Respuesta de cancelación:', data);
      
      if(!data.ok) throw new Error(data.error||'No se pudo cancelar');
      
      setMsg('✅ Turno cancelado', true);
      selectedApptId = null;
      btnReservar.textContent = 'Reservar';
      
      await loadMyAppointments();
      if (selectedDate && selMedico.value) await fetchSlots(selectedDate, selMedico.value);
      
    }catch(e){
      console.error('❌ Error cancelando turno:', e);
      setMsg('Error: ' + e.message, false);
    }
  }

  btnReservar?.addEventListener('click', async ()=>{
    setMsg('');
    
    if(!selMedico.value){ 
      setMsg('Elegí un médico', false); 
      return; 
    }
    
    if(!selectedDate || !selectedSlot){ 
      setMsg('Elegí un día y un horario', false); 
      return; 
    }

    const isReschedule = !!selectedApptId;
    console.log(isReschedule ? '🔄 Reprogramando turno' : '✅ Reservando turno nuevo');

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

      const url = `${API_BASE}`;
      console.log('📡 Posting to:', url);
      
      const res = await fetch(url, {
        method:'POST', 
        body:fd, 
        headers:{
          'Accept':'application/json',
          'X-CSRF-Token':csrf
        }
      });
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const data = await res.json();
      console.log('✅ Respuesta de reserva:', data);
      
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
      
    }catch(e){ 
      console.error('❌ Error en reserva/reprogramación:', e);
      setMsg('Error: ' + e.message, false); 
    }
  });

  // Selectores
  selEsp?.addEventListener('change', async ()=>{
    console.log('🔄 Especialidad seleccionada:', selEsp.value);
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
    console.log('🔄 Médico seleccionado:', selMedico.value);
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
  calPrev?.addEventListener('click', ()=>{ 
    console.log('⬅️ Mes anterior');
    current.setMonth(current.getMonth()-1); 
    renderCalendar(); 
  });
  
  calNext?.addEventListener('click', ()=>{ 
    console.log('➡️ Mes siguiente');
    current.setMonth(current.getMonth()+1); 
    renderCalendar(); 
  });

  function escapeHtml(s){ 
    return String(s??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); 
  }

  // ✅ Inicial con manejo de errores
  (async function init(){
    console.log('🚀 Inicializando aplicación...');
    console.log('📡 API Base URL:', API_BASE);
    
    try {
      await loadEspecialidades();
      await loadMyAppointments();
      renderCalendar();
      btnReservar.textContent = 'Reservar';
      console.log('✅ Aplicación inicializada correctamente');
    } catch (error) {
      console.error('❌ Error inicializando aplicación:', error);
      setMsg('Error al inicializar la aplicación: ' + error.message, false);
    }
  })();
})();