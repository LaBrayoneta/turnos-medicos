(function() {
    'use strict';

    const form = document.getElementById('loginForm');
    const dniInput = document.getElementById('dni');
    const passwordInput = document.getElementById('password');
    let attemptCount = 0;
    const MAX_ATTEMPTS = 5;
    const LOCKOUT_TIME = 15 * 60 * 1000; // 15 minutos

    // Verificar si está bloqueado
    function checkLockout() {
        const lockoutUntil = localStorage.getItem('loginLockout');
        if (lockoutUntil) {
            const now = Date.now();
            const lockTime = parseInt(lockoutUntil);
            if (now < lockTime) {
                const remainingMinutes = Math.ceil((lockTime - now) / 60000);
                alert(`⚠️ Has excedido el límite de intentos. Intentá nuevamente en ${remainingMinutes} minuto(s).`);
                return true;
            } else {
                localStorage.removeItem('loginLockout');
                localStorage.removeItem('loginAttempts');
            }
        }
        return false;
    }

    // Registrar intento fallido
    function recordFailedAttempt() {
        attemptCount = parseInt(localStorage.getItem('loginAttempts') || '0') + 1;
        localStorage.setItem('loginAttempts', attemptCount.toString());
        
        if (attemptCount >= MAX_ATTEMPTS) {
            const lockoutUntil = Date.now() + LOCKOUT_TIME;
            localStorage.setItem('loginLockout', lockoutUntil.toString());
            alert('⚠️ Has excedido el límite de intentos. Tu acceso ha sido bloqueado temporalmente por 15 minutos.');
            disableForm();
        } else {
            const remaining = MAX_ATTEMPTS - attemptCount;
            alert(`⚠️ Intento fallido. Te quedan ${remaining} intentos antes del bloqueo temporal.`);
        }
    }

    // Resetear intentos en login exitoso
    function resetAttempts() {
        localStorage.removeItem('loginAttempts');
        localStorage.removeItem('loginLockout');
    }

    // Deshabilitar formulario
    function disableForm() {
        if (form) {
            const inputs = form.querySelectorAll('input, button');
            inputs.forEach(input => input.disabled = true);
        }
    }

    // Solo números en DNI
    dniInput?.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Prevenir copiar/pegar en contraseña (opcional, según política de seguridad)
    passwordInput?.addEventListener('paste', function(e) {
        e.preventDefault();
        alert('⚠️ Por seguridad, no se permite copiar y pegar contraseñas');
    });

    // Sanitizar entrada para prevenir XSS
    function sanitizeInput(input) {
        const div = document.createElement('div');
        div.textContent = input;
        return div.innerHTML;
    }

    // Validar DNI argentino con dígito verificador
    function validateArgentineDNI(dni) {
        // DNI debe ser numérico
        if (!/^[0-9]+$/.test(dni)) return false;
        
        // Longitud válida (7-10 dígitos)
        const len = dni.length;
        if (len < 7 || len > 10) return false;
        
        // No puede ser todos ceros o todos iguales
        if (/^(\d)\1+$/.test(dni)) return false;
        
        // Rangos válidos aproximados para DNI argentino
        const dniNum = parseInt(dni);
        if (dniNum < 1000000 || dniNum > 99999999) return false;
        
        return true;
    }

    // Detectar patrones sospechosos en contraseña
    function detectSuspiciousPatterns(password) {
        // Detectar SQL injection patterns
        const sqlPatterns = /(\bOR\b|\bAND\b|\bUNION\b|\bSELECT\b|\bDROP\b|\bINSERT\b|--|\/\*|\*\/|';|")/i;
        if (sqlPatterns.test(password)) {
            return 'Patrón de entrada no válido detectado';
        }
        
        // Detectar XSS patterns
        const xssPatterns = /<script|javascript:|onerror=|onload=/i;
        if (xssPatterns.test(password)) {
            return 'Patrón de entrada no válido detectado';
        }
        
        return null;
    }

    // Validación del formulario
    form?.addEventListener('submit', function(e) {
        e.preventDefault();

        // Verificar bloqueo
        if (checkLockout()) {
            return false;
        }

        const dni = dniInput.value.trim();
        const password = passwordInput.value;

        // Validar DNI
        if (!dni) {
            alert('⚠️ Por favor, ingresá tu DNI');
            dniInput.focus();
            return false;
        }

        if (!validateArgentineDNI(dni)) {
            alert('⚠️ El DNI ingresado no es válido. Debe tener entre 7 y 10 dígitos numéricos.');
            dniInput.focus();
            recordFailedAttempt();
            return false;
        }

        // Validar contraseña
        if (!password) {
            alert('⚠️ Por favor, ingresá tu contraseña');
            passwordInput.focus();
            return false;
        }

        if (password.length < 6) {
            alert('⚠️ La contraseña debe tener al menos 6 caracteres');
            passwordInput.focus();
            return false;
        }

        if (password.length > 128) {
            alert('⚠️ La contraseña es demasiado larga');
            passwordInput.focus();
            return false;
        }

        // Detectar patrones sospechosos
        const suspiciousPattern = detectSuspiciousPatterns(password);
        if (suspiciousPattern) {
            alert('⚠️ ' + suspiciousPattern);
            passwordInput.value = '';
            passwordInput.focus();
            recordFailedAttempt();
            return false;
        }

        // Sanitizar entradas antes de enviar
        dniInput.value = sanitizeInput(dni);

        // Deshabilitar botón de envío para prevenir doble submit
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Iniciando sesión...';
        }

        // Todo OK - enviar formulario
        form.submit();
    });

    // Verificar bloqueo al cargar la página
    if (checkLockout()) {
        disableForm();
    }

    // Mostrar intentos restantes si existen
    window.addEventListener('load', function() {
        const attempts = parseInt(localStorage.getItem('loginAttempts') || '0');
        if (attempts > 0 && attempts < MAX_ATTEMPTS) {
            const remaining = MAX_ATTEMPTS - attempts;
            const warningDiv = document.createElement('div');
            warningDiv.style.cssText = 'background: rgba(251, 146, 60, 0.1); border: 1px solid #fb923c; border-radius: 10px; padding: 12px; margin-bottom: 20px; color: #fb923c; font-size: 14px; text-align: center;';
            warningDiv.textContent = `⚠️ Atención: Te quedan ${remaining} intentos antes del bloqueo temporal`;
            form?.insertBefore(warningDiv, form.firstChild);
        }
    });

})();