// views/assets/js/medico_auto_refresh.js
// Sistema de actualización automática de estadísticas en panel médico

(function() {
  'use strict';

  const API_URL = '../../controllers/medico_api.php';
  const REFRESH_INTERVAL = 30000; // 30 segundos
  let refreshTimer = null;
  let isRefreshing = false;

  // ========== ACTUALIZACIÓN AUTOMÁTICA DE ESTADÍSTICAS ==========
  async function autoRefreshStats() {
    if (isRefreshing) {
      console.log('⏭️ Ya hay una actualización en curso, saltando...');
      return;
    }

    isRefreshing = true;

    try {
      console.log('🔄 Auto-actualizando estadísticas...');
      
      const res = await fetch(`${API_URL}?action=stats`);
      const data = await res.json();

      if (data.ok) {
        // Actualizar stats con animación
        updateStatWithAnimation('statHoy', data.stats.hoy || 0);
        updateStatWithAnimation('statPendientes', data.stats.pendientes || 0);
        updateStatWithAnimation('statAtendidos', data.stats.atendidos || 0);
        updateStatWithAnimation('statSemana', data.stats.semana || 0);
        
        console.log('✅ Estadísticas actualizadas:', data.stats);
        
        // Actualizar timestamp en la UI
        showLastUpdateTime();
      }
    } catch (e) {
      console.error('❌ Error auto-actualizando stats:', e);
    } finally {
      isRefreshing = false;
    }
  }

  // ========== ANIMAR CAMBIO DE NÚMERO ==========
  function updateStatWithAnimation(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (!element) return;

    const currentValue = parseInt(element.textContent) || 0;
    
    if (currentValue === newValue) return; // Sin cambios

    // Animación de cambio
    element.style.transition = 'transform 0.3s, color 0.3s';
    element.style.transform = 'scale(1.2)';
    
    if (newValue > currentValue) {
      element.style.color = '#10b981'; // Verde si aumenta
    } else if (newValue < currentValue) {
      element.style.color = '#22d3ee'; // Cyan si disminuye
    }

    setTimeout(() => {
      element.textContent = newValue;
      
      setTimeout(() => {
        element.style.transform = 'scale(1)';
        element.style.color = '';
      }, 300);
    }, 150);
  }

  // ========== MOSTRAR ÚLTIMA ACTUALIZACIÓN ==========
  function showLastUpdateTime() {
    let indicator = document.getElementById('lastUpdateIndicator');
    
    if (!indicator) {
      // Crear indicador si no existe
      const statsGrid = document.querySelector('.stats-grid');
      if (!statsGrid) return;

      indicator = document.createElement('div');
      indicator.id = 'lastUpdateIndicator';
      indicator.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(34, 211, 238, 0.9);
        color: #001219;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(34, 211, 238, 0.4);
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s;
      `;
      document.body.appendChild(indicator);
    }

    const now = new Date();
    const time = now.toLocaleTimeString('es-AR', { 
      hour: '2-digit', 
      minute: '2-digit',
      second: '2-digit'
    });

    indicator.textContent = `🔄 Actualizado: ${time}`;
    indicator.style.opacity = '1';

    // Ocultar después de 3 segundos
    setTimeout(() => {
      indicator.style.opacity = '0';
    }, 3000);
  }

  // ========== ACTUALIZACIÓN MANUAL ==========
  window.manualRefreshStats = function() {
    console.log('🔄 Actualización manual solicitada');
    autoRefreshStats();
  };

  // ========== INICIAR AUTO-ACTUALIZACIÓN ==========
  function startAutoRefresh() {
    console.log('🚀 Iniciando auto-actualización cada', REFRESH_INTERVAL / 1000, 'segundos');
    
    // Primera actualización inmediata
    autoRefreshStats();
    
    // Configurar timer
    refreshTimer = setInterval(autoRefreshStats, REFRESH_INTERVAL);
    
    // Agregar botón de actualización manual
    addManualRefreshButton();
  }

  // ========== DETENER AUTO-ACTUALIZACIÓN ==========
  function stopAutoRefresh() {
    if (refreshTimer) {
      clearInterval(refreshTimer);
      refreshTimer = null;
      console.log('⏸️ Auto-actualización detenida');
    }
  }

  // ========== AGREGAR BOTÓN DE ACTUALIZACIÓN MANUAL ==========
  function addManualRefreshButton() {
    const header = document.querySelector('.hdr .actions');
    if (!header) return;

    // Verificar si ya existe
    if (document.getElementById('btnManualRefresh')) return;

    const btn = document.createElement('button');
    btn.id = 'btnManualRefresh';
    btn.className = 'btn ghost';
    btn.innerHTML = '🔄 Actualizar';
    btn.title = 'Actualizar estadísticas manualmente';
    btn.style.cssText = 'padding: 8px 14px; font-size: 14px;';
    
    btn.addEventListener('click', () => {
      btn.disabled = true;
      btn.innerHTML = '⏳ Actualizando...';
      
      autoRefreshStats().then(() => {
        btn.disabled = false;
        btn.innerHTML = '🔄 Actualizar';
        
        // Feedback visual
        btn.style.background = 'rgba(16, 185, 129, 0.2)';
        setTimeout(() => {
          btn.style.background = '';
        }, 1000);
      });
    });

    // Insertar antes del botón "Inicio"
    const inicioBtn = header.querySelector('a[href="index.php"]');
    if (inicioBtn) {
      header.insertBefore(btn, inicioBtn);
    } else {
      header.insertBefore(btn, header.firstChild);
    }
  }

  // ========== AUTO-ACTUALIZAR AL CAMBIAR DE TAB ==========
  function setupTabListeners() {
    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', () => {
        // Actualizar stats cuando se cambia de tab
        setTimeout(() => {
          if (tab.dataset.tab === 'hoy') {
            autoRefreshStats();
          }
        }, 500);
      });
    });
  }

  // ========== ACTUALIZAR AL VOLVER A LA PESTAÑA ==========
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      console.log('👁️ Pestaña visible de nuevo, actualizando...');
      autoRefreshStats();
    }
  });

  // ========== INICIALIZACIÓN ==========
  function init() {
    console.log('🚀 Inicializando sistema de auto-actualización');
    
    // Verificar que estamos en panel médico
    const medicoId = document.body.dataset.medicoId;
    if (!medicoId) {
      console.warn('⚠️ No se detectó ID de médico, auto-actualización deshabilitada');
      return;
    }

    startAutoRefresh();
    setupTabListeners();
    
    console.log('✅ Auto-actualización iniciada correctamente');
  }

  // ========== LIMPIAR AL SALIR ==========
  window.addEventListener('beforeunload', () => {
    stopAutoRefresh();
  });

  // Iniciar cuando el DOM esté listo
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Exportar funciones globales
  window.MedicoAutoRefresh = {
    start: startAutoRefresh,
    stop: stopAutoRefresh,
    refresh: autoRefreshStats
  };

  console.log('✅ Módulo de auto-actualización cargado');
})();