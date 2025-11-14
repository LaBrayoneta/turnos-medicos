// views/assets/js/theme_toggle.js - Sistema de alternancia de tema
(function() {
    'use strict';

    const THEME_KEY = 'medical_app_theme';
    const THEME_LIGHT = 'light';
    const THEME_DARK = 'dark';

    // Obtener tema actual
    function getCurrentTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved) return saved;

        // Detectar preferencia del sistema
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return THEME_DARK;
        }

        return THEME_DARK; // Por defecto oscuro (tu diseño actual)
    }

    // Aplicar tema Y actualizar botón
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        
        // Actualizar TODOS los botones
        const buttons = document.querySelectorAll('.theme-toggle');
        buttons.forEach(btn => {
            // MOSTRAR EL ICONO DEL TEMA ACTUAL (NO del próximo)
            // MODO OSCURO → MOSTRAR LUNA 🌙
            // MODO CLARO → MOSTRAR SOL ☀️
            if (theme === THEME_DARK) {
                btn.innerHTML = '🌙';
                btn.setAttribute('title', 'Modo oscuro (click para cambiar)');
            } else {
                btn.innerHTML = '☀️';
                btn.setAttribute('title', 'Modo claro (click para cambiar)');
            }
        });
        
        console.log('🎨 Tema actual:', theme);
        console.log('🔘 Icono mostrando:', theme === THEME_DARK ? '🌙 LUNA' : '☀️ SOL');
    }

    // Alternar tema
    function toggleTheme() {
        const current = getCurrentTheme();
        const next = current === THEME_LIGHT ? THEME_DARK : THEME_LIGHT;
        
        console.log('🔄 Cambiando de', current, 'a', next);
        
        applyTheme(next);

        // Animación suave
        document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
        setTimeout(() => {
            document.body.style.transition = '';
        }, 300);
    }

    // Crear botón de toggle
    function createToggleButton() {
        const button = document.createElement('button');
        button.className = 'theme-toggle btn ghost';
        button.type = 'button';
        button.innerHTML = '⚙️'; // Temporal hasta que se actualice
        
        button.style.cssText = `
            position: relative;
            padding: 10px 14px;
            font-size: 18px;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s;
        `;
        
        button.addEventListener('click', toggleTheme);
        
        return button;
    }

    // Inicializar
    function init() {
        console.log('🚀 Inicializando theme toggle...');
        
        // 1. Obtener tema actual
        const theme = getCurrentTheme();
        console.log('📋 Tema detectado:', theme);
        
        // 2. Aplicar tema al documento
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        
        // 3. Crear y agregar botones
        const headers = document.querySelectorAll('.hdr .actions');
        console.log('🔍 Headers encontrados:', headers.length);
        
        headers.forEach(header => {
            if (!header.querySelector('.theme-toggle')) {
                const toggleBtn = createToggleButton();
                header.insertBefore(toggleBtn, header.firstChild);
                console.log('✅ Botón agregado al header');
            }
        });
        
        // 4. Actualizar iconos de los botones
        applyTheme(theme);
        
        // 5. Escuchar cambios del sistema
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
                if (!localStorage.getItem(THEME_KEY)) {
                    applyTheme(e.matches ? THEME_DARK : THEME_LIGHT);
                }
            });
        }
        
        console.log('✅ Theme toggle inicializado correctamente');
    }

    // Exponer función global
    window.toggleTheme = toggleTheme;

    // Inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();