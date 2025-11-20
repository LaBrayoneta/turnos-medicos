// admin_fixes.js - VERSIÓN SIN BUCLES INFINITOS

(function() {
  'use strict';

  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const csrf = $('meta[name="csrf-token"]')?.getAttribute('content') || '';

  console.log('🔧 Cargando admin_fixes.js');

  // ========== TRACK ENHANCED INPUTS ==========
  const enhancedInputs = new WeakSet();

  // ========== MEJORAR SELECTORES DE HORA ==========
  
  function enhanceTimeInputs() {
    console.log('🎨 Mejorando selectores de hora...');
    
    const timeInputs = $$('input[type="time"]');
    let enhancedCount = 0;
    
    timeInputs.forEach(input => {
      // ✅ EVITAR RE-PROCESAR: Skip si ya fue mejorado
      if (enhancedInputs.has(input)) {
        return;
      }
      
      enhancedInputs.add(input);
      enhancedCount++;
      
      input.style.cssText = `
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background: linear-gradient(145deg, #1a2332 0%, #0f172a 100%);
        border: 2px solid #1f2937;
        border-radius: 12px;
        padding: 12px 16px;
        color: #22d3ee;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        width: 100%;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      `;
      
      input.addEventListener('mouseenter', function() {
        this.style.borderColor = '#22d3ee';
        this.style.boxShadow = '0 0 0 3px rgba(34, 211, 238, 0.1)';
        this.style.transform = 'translateY(-1px)';
      });
      
      input.addEventListener('mouseleave', function() {
        if (document.activeElement !== this) {
          this.style.borderColor = '#1f2937';
          this.style.boxShadow = 'none';
          this.style.transform = 'translateY(0)';
        }
      });
      
      input.addEventListener('focus', function() {
        this.style.borderColor = '#22d3ee';
        this.style.boxShadow = '0 0 0 4px rgba(34, 211, 238, 0.15)';
        this.style.background = 'linear-gradient(145deg, #0f172a 0%, #1a2332 100%)';
      });
      
      input.addEventListener('blur', function() {
        this.style.borderColor = '#1f2937';
        this.style.boxShadow = 'none';
        this.style.background = 'linear-gradient(145deg, #1a2332 0%, #0f172a 100%)';
        this.style.transform = 'translateY(0)';
      });
      
      input.addEventListener('change', function() {
        updateLabelWithTime(this);
      });
      
      input.addEventListener('input', function() {
        this.style.color = '#22d3ee';
        this.style.fontWeight = '700';
      });
      
      if (input.value) {
        updateLabelWithTime(input);
      }
    });
    
    if (enhancedCount > 0) {
      console.log(`✅ ${enhancedCount} nuevos selectores mejorados`);
    }
    
    addTimePickerStyles();
  }
  
  function updateLabelWithTime(input) {
    const label = input.closest('.field')?.querySelector('label');
    if (!label || !input.value) return;
    
    const time24 = input.value;
    const [hours, minutes] = time24.split(':');
    let h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    const time12 = `${h}:${minutes} ${ampm}`;
    
    const originalText = label.textContent.split('(')[0].trim();
    
    label.innerHTML = `
      ${originalText} 
      <span style="color: #22d3ee; font-weight: 700; margin-left: 8px;">
        (🕐 ${time12})
      </span>
    `;
  }
  
  function addTimePickerStyles() {
    if ($('#custom-time-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'custom-time-styles';
    style.textContent = `
      input[type="time"]::-webkit-calendar-picker-indicator {
        background: linear-gradient(135deg, #22d3ee 0%, #0891b2 100%);
        border-radius: 8px;
        padding: 8px;
        cursor: pointer;
        transition: all 0.3s;
        filter: brightness(1);
        margin-left: 8px;
      }
      
      input[type="time"]::-webkit-calendar-picker-indicator:hover {
        background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(34, 211, 238, 0.4);
      }
      
      input[type="time"]::-webkit-datetime-edit-hour-field,
      input[type="time"]::-webkit-datetime-edit-minute-field,
      input[type="time"]::-webkit-datetime-edit-ampm-field {
        padding: 4px 6px;
        border-radius: 6px;
        background: rgba(34, 211, 238, 0.1);
        color: #22d3ee;
        font-weight: 700;
        transition: all 0.2s;
      }
      
      input[type="time"]::-webkit-datetime-edit-hour-field:focus,
      input[type="time"]::-webkit-datetime-edit-minute-field:focus,
      input[type="time"]::-webkit-datetime-edit-ampm-field:focus {
        background: rgba(34, 211, 238, 0.2);
        outline: 2px solid #22d3ee;
        outline-offset: 2px;
      }
    `;
    
    document.head.appendChild(style);
  }

  // ========== FUNCIONES DE ELIMINACIÓN ==========
  
  async function deleteMedico(id) {
    if (!confirm('⚠️ ATENCIÓN\n\n¿Estás seguro de eliminar este médico?\n\nSi tiene turnos asignados, solo se desactivará.\nSi no tiene turnos, se eliminará permanentemente.\n\nEsta acción no se puede deshacer.')) {
      return;
    }
    
    console.log('🗑️ Eliminando médico ID:', id);
    
    try {
      const fd = new FormData();
      fd.append('action', 'delete_medico');
      fd.append('id_medico', id);
      fd.append('csrf_token', csrf);
      
      const res = await fetch('admin.php', { 
        method: 'POST', 
        body: fd,
        headers: { 'Accept': 'application/json' }
      });
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      
      const data = await res.json();
      
      if (!data.ok) {
        throw new Error(data.error || 'Error al eliminar');
      }
      
      console.log('✅ Médico eliminado:', data.msg);
      alert('✅ ' + (data.msg || 'Médico eliminado correctamente'));
      
      window.location.reload();
      
    } catch (e) {
      console.error('❌ Error eliminando médico:', e);
      alert('❌ Error al eliminar médico: ' + e.message);
    }
  }
  
  async function deleteSecretaria(id) {
    if (!confirm('⚠️ ATENCIÓN\n\n¿Estás seguro de eliminar esta secretaria?\n\nSi ha creado turnos, solo se desactivará.\nSi no tiene turnos asociados, se eliminará permanentemente.\n\nEsta acción no se puede deshacer.')) {
      return;
    }
    
    console.log('🗑️ Eliminando secretaria ID:', id);
    
    try {
      const fd = new FormData();
      fd.append('action', 'delete_secretaria');
      fd.append('id_secretaria', id);
      fd.append('csrf_token', csrf);
      
      const res = await fetch('admin.php', { 
        method: 'POST', 
        body: fd,
        headers: { 'Accept': 'application/json' }
      });
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      
      const data = await res.json();
      
      if (!data.ok) {
        throw new Error(data.error || 'Error al eliminar');
      }
      
      console.log('✅ Secretaria eliminada:', data.msg);
      alert('✅ ' + (data.msg || 'Secretaria eliminada correctamente'));
      
      window.location.reload();
      
    } catch (e) {
      console.error('❌ Error eliminando secretaria:', e);
      alert('❌ Error al eliminar secretaria: ' + e.message);
    }
  }
  
  // ========== INYECTAR FUNCIONES GLOBALES ==========
  
  window.deleteMedico = deleteMedico;
  window.deleteSecretaria = deleteSecretaria;
  
  // ========== INICIALIZAR ==========
  
  function init() {
    console.log('🔧 Inicializando correcciones de admin...');
    
    // Mejorar selectores de hora existentes
    enhanceTimeInputs();
    
    // ✅ SOLUCIÓN: Observador MÁS ESPECÍFICO y con DEBOUNCE
    let observerTimeout;
    const observer = new MutationObserver((mutations) => {
      clearTimeout(observerTimeout);
      
      observerTimeout = setTimeout(() => {
        let hasNewTimeInputs = false;
        
        mutations.forEach((mutation) => {
          if (mutation.addedNodes.length) {
            mutation.addedNodes.forEach(node => {
              if (node.nodeType === 1) {
                // Solo verificar si hay NUEVOS time inputs
                if (node.matches && node.matches('input[type="time"]')) {
                  hasNewTimeInputs = true;
                } else if (node.querySelectorAll) {
                  const timeInputs = node.querySelectorAll('input[type="time"]');
                  if (timeInputs.length > 0) {
                    hasNewTimeInputs = true;
                  }
                }
              }
            });
          }
        });
        
        // Solo ejecutar si realmente hay nuevos inputs
        if (hasNewTimeInputs) {
          console.log('🔄 Detectados nuevos time inputs');
          enhanceTimeInputs();
        }
      }, 200); // Debounce de 200ms
    });
    
    // ✅ Observar SOLO los contenedores de modales
    const modals = document.querySelectorAll('.modal, .modal-content, #modalEditMedico, #modalCreateTurno');
    modals.forEach(modal => {
      if (modal) {
        observer.observe(modal, {
          childList: true,
          subtree: true
        });
      }
    });
    
    console.log(`✅ Observando ${modals.length} contenedores de modales`);
    console.log('✅ Correcciones aplicadas (sin bucles)');
  }
  
  // ✅ IMPORTANTE: Inicializar SOLO UNA VEZ
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  
  console.log('✅ admin_fixes.js cargado correctamente');
  
})();