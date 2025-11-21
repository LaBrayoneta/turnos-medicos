// views/assets/js/admin_horarios.js
// Sistema de gestión de horarios para médicos

(function() {
  'use strict';

  console.log('🕐 Cargando sistema de horarios...');

  // Arrays globales para almacenar horarios
  const horariosCreate = [];
  const horariosEdit = [];

  // ========== UTILIDADES ==========

  /**
   * Convierte hora de 24h a 12h con AM/PM
   */
  function formatHour12(time24) {
    if (!time24) return '';
    const [hours, minutes] = time24.split(':');
    let h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${minutes} ${ampm}`;
  }

  /**
   * Verifica si un horario ya existe en la lista
   */
  function horarioExists(list, dia, inicio, fin) {
    return list.some(h => h.dia === dia && h.inicio === inicio && h.fin === fin);
  }

  /**
   * Verifica si un horario se solapa con otros existentes
   */
  function horarioOverlaps(list, dia, inicioNuevo, finNuevo) {
    return list.some(h => {
      if (h.dia !== dia) return false;
      // Verifica solapamiento: nuevo inicio < existente fin Y nuevo fin > existente inicio
      return (inicioNuevo < h.fin && finNuevo > h.inicio);
    });
  }

  /**
   * Renderiza la lista de horarios en el contenedor especificado
   */
  function renderHorarios(list, containerId) {
    const container = document.getElementById(containerId);
    if (!container) {
      console.warn(`Contenedor ${containerId} no encontrado`);
      return;
    }

    container.innerHTML = '';

    if (list.length === 0) {
      container.innerHTML = '<p class="horarios-empty">⚠️ No hay horarios agregados. Agregá al menos uno.</p>';
      return;
    }

    // Ordenar horarios por día y hora
    const diasOrden = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
    const sortedList = [...list].sort((a, b) => {
      const diaCompare = diasOrden.indexOf(a.dia) - diasOrden.indexOf(b.dia);
      if (diaCompare !== 0) return diaCompare;
      return a.inicio.localeCompare(b.inicio);
    });

    sortedList.forEach((h) => {
      const realIdx = list.indexOf(h); // Índice real en el array original
      const div = document.createElement('div');
      div.className = 'horario-item';

      const horaInicio = formatHour12(h.inicio.substring(0, 5));
      const horaFin = formatHour12(h.fin.substring(0, 5));

      div.innerHTML = `
        <div class="horario-info">
          <strong style="text-transform:capitalize;color:var(--primary);font-size:14px">${h.dia}</strong>
          <br>
          <span style="color:var(--text);font-size:13px">🕒 ${horaInicio} - ${horaFin}</span>
        </div>
        <button type="button" class="btn-remove-horario" data-idx="${realIdx}" data-container="${containerId}">
          🗑️ Eliminar
        </button>
      `;

      container.appendChild(div);
    });

    // Agregar event listeners a los botones de eliminar
    container.querySelectorAll('.btn-remove-horario').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.idx);
        const containerTarget = btn.dataset.container;

        if (containerTarget === 'horariosListCreate') {
          horariosCreate.splice(idx, 1);
          renderHorarios(horariosCreate, 'horariosListCreate');
          showMessage('msgCreateMed', '✅ Horario eliminado', true);
        } else {
          horariosEdit.splice(idx, 1);
          renderHorarios(horariosEdit, 'horariosListEdit');
          showMessage('msgMedicoModal', '✅ Horario eliminado', true);
        }
      });
    });
  }

  /**
   * Muestra un mensaje en el elemento especificado
   */
  function showMessage(elementId, message, isSuccess = true) {
    const msgEl = document.getElementById(elementId);
    if (msgEl) {
      msgEl.textContent = message;
      msgEl.className = isSuccess ? 'msg ok' : 'msg err';
    }
  }

  // ========== AGREGAR HORARIO (CREAR MÉDICO) ==========

  document.getElementById('btnAgregarHorario')?.addEventListener('click', () => {
    const dia = document.getElementById('diaHorario').value;
    const inicio = document.getElementById('horaInicio').value;
    const fin = document.getElementById('horaFin').value;

    if (!inicio || !fin) {
      alert('⚠️ Completá las horas de inicio y fin');
      return;
    }

    if (inicio >= fin) {
      alert('⚠️ La hora de inicio debe ser menor que la de fin');
      return;
    }

    const inicioFull = inicio + ':00';
    const finFull = fin + ':00';

    if (horarioExists(horariosCreate, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario ya fue agregado');
      return;
    }

    if (horarioOverlaps(horariosCreate, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario se solapa con uno existente');
      return;
    }

    horariosCreate.push({ dia, inicio: inicioFull, fin: finFull });
    renderHorarios(horariosCreate, 'horariosListCreate');

    showMessage('msgCreateMed', '✅ Horario agregado', true);
  });

  // ========== AGREGAR HORARIO (EDITAR MÉDICO) ==========

  document.getElementById('btnAgregarHorarioEdit')?.addEventListener('click', () => {
    const dia = document.getElementById('editDiaHorario').value;
    const inicio = document.getElementById('editHoraInicio').value;
    const fin = document.getElementById('editHoraFin').value;

    if (!inicio || !fin) {
      alert('⚠️ Completá las horas de inicio y fin');
      return;
    }

    if (inicio >= fin) {
      alert('⚠️ La hora de inicio debe ser menor que la de fin');
      return;
    }

    const inicioFull = inicio + ':00';
    const finFull = fin + ':00';

    if (horarioExists(horariosEdit, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario ya fue agregado');
      return;
    }

    if (horarioOverlaps(horariosEdit, dia, inicioFull, finFull)) {
      alert('⚠️ Este horario se solapa con uno existente');
      return;
    }

    horariosEdit.push({ dia, inicio: inicioFull, fin: finFull });
    renderHorarios(horariosEdit, 'horariosListEdit');

    showMessage('msgMedicoModal', '✅ Horario agregado', true);
  });

  // ========== CREAR MÉDICO ==========

  document.getElementById('btnCrearMedico')?.addEventListener('click', async () => {
    const form = document.getElementById('createMedicoForm');
    const msgEl = document.getElementById('msgCreateMed');

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    if (horariosCreate.length === 0) {
      showMessage('msgCreateMed', '⚠️ Debe agregar al menos un horario', false);
      alert('⚠️ Debe agregar al menos un horario de atención');
      return;
    }

    showMessage('msgCreateMed', '⏳ Creando médico...', true);

    const fd = new FormData(form);
    fd.set('action', 'create_medico');
    fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.set('horarios', JSON.stringify(horariosCreate));

    try {
      const res = await fetch('admin.php', { method: 'POST', body: fd });
      const data = await res.json();
      
      if (!data.ok) throw new Error(data.error || 'Error al crear médico');

      showMessage('msgCreateMed', '✅ ' + (data.msg || 'Médico creado'), true);

      form.reset();
      horariosCreate.length = 0;
      renderHorarios(horariosCreate, 'horariosListCreate');

      setTimeout(() => window.location.reload(), 1500);
    } catch (e) {
      console.error('Error creando médico:', e);
      showMessage('msgCreateMed', '❌ ' + e.message, false);
    }
  });

  // ========== CARGAR HORARIOS PARA EDICIÓN ==========

  window.loadMedicoHorarios = function(horarios) {
    horariosEdit.length = 0;
    
    if (horarios && Array.isArray(horarios)) {
      horarios.forEach(h => {
        horariosEdit.push({
          dia: h.Dia_semana || h.dia_semana,
          inicio: h.Hora_inicio || h.hora_inicio,
          fin: h.Hora_fin || h.hora_fin
        });
      });
    }
    
    renderHorarios(horariosEdit, 'horariosListEdit');
    console.log(`✅ ${horariosEdit.length} horarios cargados para edición`);
  };

  // ========== ACTUALIZAR MÉDICO ==========

  document.getElementById('formEditMedico')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (horariosEdit.length === 0) {
      showMessage('msgMedicoModal', '⚠️ Debe tener al menos un horario', false);
      alert('⚠️ Debe tener al menos un horario de atención');
      return;
    }

    showMessage('msgMedicoModal', '⏳ Actualizando...', true);

    const fd = new FormData();
    fd.append('action', 'update_medico');
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('id_medico', document.getElementById('editMedId').value);
    fd.append('nombre', document.getElementById('editMedNombre').value);
    fd.append('apellido', document.getElementById('editMedApellido').value);
    fd.append('email', document.getElementById('editMedEmail').value);
    fd.append('legajo', document.getElementById('editMedLegajo').value);
    fd.append('especialidad', document.getElementById('editMedEsp').value);
    fd.append('horarios', JSON.stringify(horariosEdit));

    try {
      const res = await fetch('admin.php', { method: 'POST', body: fd });
      const data = await res.json();
      
      if (!data.ok) throw new Error(data.error || 'Error al actualizar');

      showMessage('msgMedicoModal', '✅ ' + (data.msg || 'Actualizado'), true);

      setTimeout(() => window.location.reload(), 1500);
    } catch (e) {
      console.error('Error actualizando médico:', e);
      showMessage('msgMedicoModal', '❌ ' + e.message, false);
    }
  });

  // ========== INICIALIZACIÓN ==========

  // Renderizar vistas iniciales vacías
  renderHorarios(horariosCreate, 'horariosListCreate');
  renderHorarios(horariosEdit, 'horariosListEdit');

  console.log('✅ Sistema de horarios inicializado correctamente');
})();