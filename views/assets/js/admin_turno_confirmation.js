// views/assets/js/admin_turno_confirmation.js
// Sistema de confirmación/rechazo de turnos -

(function() {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // ========== FUNCIÓN PARA RECARGAR TURNOS PENDIENTES ==========
  async function recargarTurnosPendientes() {
    const tbody = document.querySelector('#tblTurnosPendientes tbody');
    const noData = document.getElementById('noPendientes');
    const msgEl = document.getElementById('msgPendientes');
    
    if (!tbody) return;
    
    try {
      const espId = document.getElementById('fEspPendientes')?.value || '';
      const medId = document.getElementById('fMedPendientes')?.value || '';
      const from = document.getElementById('fFromPendientes')?.value || '';
      const to = document.getElementById('fToPendientes')?.value || '';
      
      let url = 'admin.php?fetch=turnos_pendientes';
      if (espId) url += `&especialidad_id=${espId}`;
      if (medId) url += `&medico_id=${medId}`;
      if (from) url += `&from=${from}`;
      if (to) url += `&to=${to}`;
      
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      tbody.innerHTML = '';
      
      if (!data.items || data.items.length === 0) {
        if (noData) noData.style.display = 'block';
        return;
      }
      
      if (noData) noData.style.display = 'none';
      
      data.items.forEach(t => {
        const tr = document.createElement('tr');
        tr.style.background = 'rgba(251,146,60,0.05)';
        
        tr.innerHTML = `
          <td><div style="font-weight:600;color:var(--primary)">${t.fecha_fmt}</div></td>
          <td>${t.paciente}</td>
          <td>${t.paciente_dni}</td>
          <td>${t.medico}</td>
          <td>${t.especialidad}</td>
          <td>${t.obra_social}</td>
          <td class="row-actions">
            <button class="btn primary btn-sm btn-confirmar" data-id="${t.Id_turno}">✅ Confirmar</button>
            <button class="btn danger btn-sm btn-rechazar" data-id="${t.Id_turno}">❌ Rechazar</button>
          </td>
        `;
        
        tbody.appendChild(tr);
      });
      
      tbody.querySelectorAll('.btn-confirmar').forEach(btn => {
        btn.addEventListener('click', () => confirmarTurno(btn.dataset.id));
      });
      
      tbody.querySelectorAll('.btn-rechazar').forEach(btn => {
        btn.addEventListener('click', () => rechazarTurno(btn.dataset.id));
      });
      
      console.log('✅ Lista actualizada:', data.items.length, 'turnos');
      
    } catch (e) {
      console.error('Error recargando:', e);
    }
  }

  // ========== CONFIRMAR TURNO ==========
  async function confirmarTurno(turnoId) {
    if (!confirm('✅ ¿CONFIRMAR ESTE TURNO?')) return;
    
    const msgEl = document.getElementById('msgPendientes');
    if (msgEl) {
      msgEl.textContent = '⏳ Confirmando...';
      msgEl.className = 'msg';
    }
    
    try {
      const fd = new FormData();
      fd.append('action', 'confirmar_turno');
      fd.append('turno_id', turnoId);
      fd.append('csrf_token', csrf);
      
      const res = await fetch('admin.php', { 
        method: 'POST', 
        body: fd, 
        headers: { 'Accept': 'application/json' }
      });
      
      if (!res.ok) throw new Error(`Error (${res.status})`);
      
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      if (msgEl) {
        msgEl.textContent = '✅ ' + (data.msg || 'Turno confirmado');
        msgEl.className = 'msg ok';
      }
      
      // RECARGAR LISTA
      setTimeout(recargarTurnosPendientes, 500);
      
    } catch (e) {
      console.error('Error:', e);
      if (msgEl) {
        msgEl.textContent = '❌ ' + e.message;
        msgEl.className = 'msg err';
      }
      alert('❌ ' + e.message);
    }
  }

  // ========== RECHAZAR TURNO ==========
  async function rechazarTurno(turnoId) {
    const motivo = prompt('❌ RECHAZAR TURNO\n\nIndica el motivo (mínimo 10 caracteres):');
    
    if (!motivo) return;
    
    const motivoTrim = motivo.trim();
    if (motivoTrim.length < 10) {
      alert('❌ El motivo debe tener al menos 10 caracteres');
      return;
    }
    
    if (!confirm(`⚠️ ¿RECHAZAR?\n\nMotivo: "${motivoTrim}"`)) return;
    
    const msgEl = document.getElementById('msgPendientes');
    if (msgEl) {
      msgEl.textContent = '⏳ Rechazando...';
      msgEl.className = 'msg';
    }
    
    try {
      const fd = new FormData();
      fd.append('action', 'rechazar_turno');
      fd.append('turno_id', turnoId);
      fd.append('motivo', motivoTrim);
      fd.append('csrf_token', csrf);
      
      const res = await fetch('admin.php', { 
        method: 'POST', 
        body: fd, 
        headers: { 'Accept': 'application/json' }
      });
      
      if (!res.ok) throw new Error(`Error (${res.status})`);
      
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error');
      
      if (msgEl) {
        msgEl.textContent = '✅ ' + (data.msg || 'Turno rechazado');
        msgEl.className = 'msg ok';
      }
      
      // RECARGAR LISTA
      setTimeout(recargarTurnosPendientes, 500);
      
    } catch (e) {
      console.error('Error:', e);
      if (msgEl) {
        msgEl.textContent = '❌ ' + e.message;
        msgEl.className = 'msg err';
      }
      alert('❌ ' + e.message);
    }
  }

  // ========== VERIFICAR TURNO DUPLICADO ==========
  window.checkPacienteTurnoExistente = async function(pacienteId, medicoId) {
    try {
      const url = `admin.php?fetch=check_turno_existente&paciente_id=${pacienteId}&medico_id=${medicoId}`;
      const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
      if (!res.ok) return null;
      const data = await res.json();
      return (data.ok && data.tiene_turno) ? data.turno : null;
    } catch (e) {
      return null;
    }
  };

  // ========== EXPORTAR FUNCIONES GLOBALES ==========
  window.confirmarTurno = confirmarTurno;
  window.rechazarTurno = rechazarTurno;
  window.recargarTurnosPendientes = recargarTurnosPendientes;

  // ========== INICIALIZAR ==========
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      if (tab.dataset.tab === 'turnos-pendientes') {
        setTimeout(recargarTurnosPendientes, 100);
      }
    });
  });

  document.getElementById('btnRefreshPendientes')?.addEventListener('click', recargarTurnosPendientes);

  console.log('✅ admin_turno_confirmation.js cargado');
})();