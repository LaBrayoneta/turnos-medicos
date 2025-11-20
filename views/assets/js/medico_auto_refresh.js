// views/assets/js/medico_auto_refresh.js
// Sistema de actualización automática OPTIMIZADO

(function() {
  'use strict';

  const API_URL = '../../controllers/medico_api.php';
  const REFRESH_INTERVAL = 30000; // 30 segundos
  const RETRY_DELAY = 5000; // 5 segundos en caso de error
  const MAX_RETRIES = 3;
  
  let refreshTimer = null;
  let isRefreshing = false;
  let retryCount = 0;
  let lastSuccessTime = null;
  let isPageVisible = !document.hidden;

  // ========== ACTUALIZACIÓN AUTOMÁTICA DE ESTADÍSTICAS ==========
  async function autoRefreshStats() {
    // ✅ NO actualizar si el usuario no está viendo la página
    if (!isPageVisible) {
      console.log('⏭️ Página no visible, saltando actualización');
      return;
    }
    
    if (isRefreshing) {
      console.log('⏭️ Ya hay una actualización en curso, saltando...');
      return;
    }

    isRefreshing = true;

    try {
      console.log('🔄 Auto-actualizando estadísticas...');
      
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout
      
      const res = await fetch(`${API_URL}?action=stats`, {
        signal: controller.signal,
        headers: { 'Accept': 'application/json' }
      });
      
      clearTimeout(timeoutId);

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const data = await res.json();

      if (data.ok) {
        // Actualizar stats con animación
        updateStatWithAnimation('statHoy', data.stats.hoy || 0);
        updateStatWithAnimation('statPendientes', data.stats.pendientes || 0);
        updateStatWithAnimation('statAtendidos', data.stats.atendidos || 0);
        updateStatWithAnimation('statSemana', data.stats.semana || 0);
        
        console.log('✅ Estadísticas actualizadas:', data.stats);
        
        // Actualizar timestamp
        lastSuccessTime = Date.now();
        retryCount = 0; // Reset retry counter
        showLastUpdateTime();
      } else {
        throw new Error(data.error || 'Error en respuesta');
      }
    } catch (e) {
      console.error('❌ Error auto-actualizando stats:', e);
      
      retryCount++;
      
      if (retryCount >= MAX_RETRIES) {
        console.warn('⚠️ Máximo de reintentos alcanzado, deteniendo auto-refresh');
        stopAutoRefresh();
        showErrorIndicator();
      }
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
  
  // ========== MOSTRAR ERROR ==========
  function showErrorIndicator() {
    let indicator = document.getElementById('lastUpdateIndicator');
    if (!indicator) return;
    
    indicator.style.background = 'rgba(239, 68, 68, 0.9)';
    indicator.style.color = 'white';
    indicator.textContent = '⚠️ Error de conexión';
    indicator.style.opacity = '1';
    
    setTimeout(() => {
      indicator.style.opacity = '0';
    }, 5000);
  }

  // ========== ACTUALIZACIÓN MANUAL ==========
  window.manualRefreshStats = function() {
    console.log('🔄 Actualización manual solicitada');
    retryCount = 0; // Reset retry counter
    if (!refreshTimer) {
      startAutoRefresh(); // Reiniciar si estaba detenido
    }
    autoRefreshStats();
  };

  // ========== INICIAR AUTO-ACTUALIZACIÓN ==========
  function startAutoRefresh() {
    if (refreshTimer) {
      console.log('⚠️ Auto-refresh ya está activo');
      return;
    }
    
    console.log('🚀 Iniciando auto-actualización cada', REFRESH_INTERVAL / 1000, 'segundos');
    
    // Primera actualización inmediata
    autoRefreshStats();
    
    // Configurar timer
    refreshTimer = setInterval(() => {
      // Solo actualizar si la página está visible
      if (isPageVisible) {
        autoRefreshStats();
      }
    }, REFRESH_INTERVAL);
    
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
      
      manualRefreshStats();
      
      setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '🔄 Actualizar';
        
        // Feedback visual
        btn.style.background = 'rgba(16, 185, 129, 0.2)';
        setTimeout(() => {
          btn.style.background = '';
        }, 1000);
      }, 1000);
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
        // Actualizar stats cuando se cambia a la pestaña principal
        setTimeout(() => {
          if (tab.dataset.tab === 'hoy' && isPageVisible) {
            autoRefreshStats();
          }
        }, 500);
      });
    });
  }

  // ========== MANEJAR VISIBILIDAD DE PÁGINA ==========
  document.addEventListener('visibilitychange', () => {
    isPageVisible = !document.hidden;
    
    if (isPageVisible) {
      console.log('👁️ Pestaña visible de nuevo, actualizando...');
      
      // Si pasó mucho tiempo, actualizar inmediatamente
      if (lastSuccessTime && (Date.now() - lastSuccessTime) > REFRESH_INTERVAL) {
        autoRefreshStats();
      }
    } else {
      console.log('👁️ Pestaña oculta, pausando actualizaciones');
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