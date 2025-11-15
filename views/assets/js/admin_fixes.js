// admin_fixes.js - Correcciones para admin.php
// 1. Arregla eliminación de médicos y secretarias
// 2. Mejora visual de selectores de hora

(function() {
  'use strict';

  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const csrf = $('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // ========== MEJORAR SELECTORES DE HORA ==========
  
  function enhanceTimeInputs() {
    console.log('🎨 Mejorando selectores de hora...');
    
    // Seleccionar todos los inputs de tipo time
    const timeInputs = $$('input[type="time"]');
    
    timeInputs.forEach(input => {
      // Agregar estilos personalizados
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
        position: relative;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      `;
      
      // Efectos hover y focus
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
      
      // Actualizar label con formato 12h cuando cambia
      input.addEventListener('change', function() {
        updateLabelWithTime(this);
      });
      
      // Animación al seleccionar
      input.addEventListener('input', function() {
        this.style.color = '#22d3ee';
        this.style.fontWeight = '700';
      });
      
      // Inicializar con valor si existe
      if (input.value) {
        updateLabelWithTime(input);
      }
    });
    
    // Agregar estilos globales para el selector de hora
    addTimePickerStyles();
    
    console.log('✅ Selectores de hora mejorados');
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
    
    // Obtener texto original del label sin el tiempo
    const originalText = label.textContent.split('(')[0].trim();
    
    // Actualizar label con formato visual
    label.innerHTML = `
      ${originalText} 
      <span style="color: #22d3ee; font-weight: 700; margin-left: 8px;">
        (🕐 ${time12})
      </span>
    `;
  }
  
  function addTimePickerStyles() {
    if ($('#custom-time-styles')) return; // Ya existe
    
    const style = document.createElement('style');
    style.id = 'custom-time-styles';
    style.textContent = `
      /* Mejorar el dropdown del time picker en Chrome/Edge */
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
      
      input[type="time"]::-webkit-calendar-picker-indicator:active {
        transform: scale(0.95);
      }
      
      /* Mejorar el dropdown en Firefox */
      input[type="time"]::-moz-calendar-picker-indicator {
        background: linear-gradient(135deg, #22d3ee 0%, #0891b2 100%);
        border-radius: 8px;
        padding: 8px;
        cursor: pointer;
      }
      
      /* Estilos para el texto del input */
      input[type="time"]::-webkit-datetime-edit {
        padding: 0;
        color: inherit;
      }
      
      input[type="time"]::-webkit-datetime-edit-fields-wrapper {
        padding: 0;
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
      
      input[type="time"]::-webkit-datetime-edit-text {
        color: #94a3b8;
        padding: 0 4px;
      }
      
      /* Animación de entrada */
      @keyframes timePickerFadeIn {
        from {
          opacity: 0;
          transform: translateY(-10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      
      input[type="time"]:focus {
        animation: timePickerFadeIn 0.3s ease-out;
      }
    `;
    
    document.head.appendChild(style);
  }

  // ========== ARREGLAR ELIMINACIÓN DE MÉDICOS Y SECRETARIAS ==========
  
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
      
      // Recargar página para actualizar la lista
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
      
      // Recargar página para actualizar la lista
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
    
    // Mejorar selectores de hora
    enhanceTimeInputs();
    
    // Observar cambios en el DOM para nuevos inputs de tiempo
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.addedNodes.length) {
          enhanceTimeInputs();
        }
      });
    });
    
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
    
    console.log('✅ Correcciones aplicadas');
  }
  
  // Inicializar cuando el DOM esté listo
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  
})();