// views/assets/js/admin_turno_confirmation.js
// Sistema de confirmación/rechazo de turnos con envío de emails

(function() {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // ========== VERIFICAR TURNO DUPLICADO ==========
  window.checkPacienteTurnoExistente = async function(pacienteId, medicoId) {
    try {
      const url = `admin.php?fetch=check_turno_existente&paciente_id=${pacienteId}&medico_id=${medicoId}`;
      
      console.log('🔍 Verificando turnos duplicados...');
      
      const res = await fetch(url, {
        headers: { 'Accept': 'application/json' }
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      
      if (data.ok && data.tiene_turno) {
        console.log('⚠️ Turno duplicado encontrado:', data.turno);
        return data.turno;
      }

      console.log('✅ No hay turnos duplicados');
      return null;

    } catch (e) {
      console.error('Error verificando turno:', e);
      return null;
    }
  };

  // ========== CONFIRMAR TURNO ==========
  window.confirmarTurno = async function(turnoId) {
    if (!confirm('✅ ¿CONFIRMAR ESTE TURNO?\n\n• Se enviará un email de confirmación al paciente\n• El turno quedará confirmado en el sistema\n\n¿Deseas continuar?')) {
      return;
    }
    
    const msgEl = document.getElementById('msgTurns');
    if (msgEl) {
      msgEl.textContent = '⏳ Confirmando turno y enviando email...';
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
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      
      if (!data.ok) throw new Error(data.error || 'Error al confirmar');
      
      if (msgEl) {
        msgEl.textContent = '✅ ' + (data.msg || 'Turno confirmado y email enviado');
        msgEl.className = 'msg ok';
      }
      
      // Recargar agenda después de 1.5 segundos
      setTimeout(() => {
        const btnRefresh = document.getElementById('btnRefresh');
        if (btnRefresh) btnRefresh.click();
      }, 1500);
      
    } catch (e) {
      console.error('Error confirmando turno:', e);
      if (msgEl) {
        msgEl.textContent = '❌ Error: ' + e.message;
        msgEl.className = 'msg err';
      }
      alert('❌ Error al confirmar el turno:\n\n' + e.message);
    }
  };

  // ========== RECHAZAR TURNO ==========
  window.rechazarTurno = async function(turnoId) {
    const motivo = prompt(
      '❌ RECHAZAR TURNO\n\n' +
      'Por favor, indica el motivo del rechazo.\n' +
      'Este mensaje se enviará al paciente por email.\n\n' +
      'Ejemplos:\n' +
      '• El médico no está disponible en ese horario\n' +
      '• Necesitamos reagendar por urgencia médica\n' +
      '• El turno fue solicitado fuera de horario\n\n' +
      '(Mínimo 10 caracteres, máximo 500):'
    );
    
    if (!motivo) {
      console.log('Rechazo cancelado por el usuario');
      return;
    }
    
    const motivoTrim = motivo.trim();
    
    if (motivoTrim.length < 10) {
      alert('❌ ERROR\n\nEl motivo debe tener al menos 10 caracteres para que el paciente entienda la razón del rechazo.');
      return;
    }
    
    if (motivoTrim.length > 500) {
      alert('❌ ERROR\n\nEl motivo es demasiado largo (máximo 500 caracteres).\n\nActual: ' + motivoTrim.length + ' caracteres');
      return;
    }
    
    if (!confirm(`⚠️ CONFIRMAR RECHAZO\n\nMotivo que se enviará al paciente:\n"${motivoTrim}"\n\n¿Deseas continuar?`)) {
      return;
    }
    
    const msgEl = document.getElementById('msgTurns');
    if (msgEl) {
      msgEl.textContent = '⏳ Rechazando turno y enviando email...';
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
      
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      
      if (!data.ok) throw new Error(data.error || 'Error al rechazar');
      
      if (msgEl) {
        msgEl.textContent = '✅ ' + (data.msg || 'Turno rechazado y email enviado');
        msgEl.className = 'msg ok';
      }
      
      // Recargar agenda después de 1.5 segundos
      setTimeout(() => {
        const btnRefresh = document.getElementById('btnRefresh');
        if (btnRefresh) btnRefresh.click();
      }, 1500);
      
    } catch (e) {
      console.error('Error rechazando turno:', e);
      if (msgEl) {
        msgEl.textContent = '❌ Error: ' + e.message;
        msgEl.className = 'msg err';
      }
      alert('❌ Error al rechazar el turno:\n\n' + e.message);
    }
  };

  console.log('✅ Sistema de confirmación/rechazo de turnos cargado');
})();