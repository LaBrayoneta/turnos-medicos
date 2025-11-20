// login.js - JavaScript para página de login
(function() {
    'use strict';

    const form = document.getElementById('loginForm');
    const dniInput = document.getElementById('dni');
    const passwordInput = document.getElementById('password');
    let attemptCount = 0;
    const MAX_ATTEMPTS = 5;
    const LOCKOUT_TIME = 15 * 60 * 1000;

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

    function validateArgentineDNI(dni) {
        if (!/^[0-9]+$/.test(dni)) return false;
        const len = dni.length;
        if (len < 7 || len > 10) return false;
        if (/^(\d)\1+$/.test(dni)) return false;
        const dniNum = parseInt(dni);
        if (dniNum < 1000000 || dniNum > 99999999) return false;
        return true;
    }

    function detectSuspiciousPatterns(password) {
        const sqlPatterns = /(\bOR\b|\bAND\b|\bUNION\b|\bSELECT\b|\bDROP\b|\bINSERT\b|--|\/\*|\*\/|';|")/i;
        if (sqlPatterns.test(password)) return 'Patrón de entrada no válido detectado';
        const xssPatterns = /<script|javascript:|onerror=|onload=/i;
        if (xssPatterns.test(password)) return 'Patrón de entrada no válido detectado';
        return null;
    }

    // Validación del formulario
    form?.addEventListener('submit', function(e) {
        if (checkLockout()) {
            e.preventDefault();
            return false;
        }

        const dni = dniInput.value.trim();
        const password = passwordInput.value;

        if (!dni) {
            e.preventDefault();
            alert('⚠️ Por favor, ingresá tu DNI');
            dniInput.focus();
            return false;
        }

        if (!validateArgentineDNI(dni)) {
            e.preventDefault();
            alert('⚠️ El DNI ingresado no es válido. Debe tener entre 7 y 10 dígitos numéricos.');
            dniInput.focus();
            recordFailedAttempt();
            return false;
        }

        if (!password) {
            e.preventDefault();
            alert('⚠️ Por favor, ingresá tu contraseña');
            passwordInput.focus();
            return false;
        }

        if (password.length < 6) {
            e.preventDefault();
            alert('⚠️ La contraseña debe tener al menos 6 caracteres');
            passwordInput.focus();
            return false;
        }

        const suspiciousPattern = detectSuspiciousPatterns(password);
        if (suspiciousPattern) {
            e.preventDefault();
            alert('⚠️ ' + suspiciousPattern);
            passwordInput.value = '';
            passwordInput.focus();
            recordFailedAttempt();
            return false;
        }

        // Deshabilitar botón para evitar doble envío
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Iniciando sesión...';
        }
    });

    // Verificar bloqueo al cargar
    if (checkLockout()) {
        disableForm();
    }

    // Mostrar intentos restantes
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

    console.log('🔐 Login form initialized');
})();