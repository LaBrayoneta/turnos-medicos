// register.js - Validaciones avanzadas con verificación CSRF y seguridad mejorada

(function() {
    'use strict';

    const form = document.getElementById('registerForm');
    const password = document.getElementById('password');
    const password2 = document.getElementById('password2');
    const strengthMsg = document.getElementById('strengthMsg');
    const dniInput = document.getElementById('dni');
    const emailInput = document.getElementById('email');
    const nombreInput = document.getElementById('nombre');
    const apellidoInput = document.getElementById('apellido');
    const obraSelect = document.getElementById('id_obra_social');
    const fieldOtraObra = document.getElementById('fieldObraOtra');
    const fieldNroCarnet = document.getElementById('fieldNroCarnet');
    const otraObraInput = document.getElementById('obra_social_otra');
    const nroCarnetInput = document.getElementById('nro_carnet');
    const csrfTokenInput = document.getElementById('csrf_token');

    // ========== VERIFICACIÓN CSRF ==========
    if (csrfTokenInput) {
        const tokenValue = csrfTokenInput.value;
        console.log('🔐 CSRF Token cargado:', tokenValue ? tokenValue.substring(0, 10) + '...' : 'VACÍO');
        
        if (!tokenValue || tokenValue.length < 32) {
            console.error('❌ ERROR: Token CSRF inválido o faltante');
            showError('ERROR CRÍTICO: El token de seguridad no se cargó correctamente. Recarga la página (F5).');
        }
    } else {
        console.error('❌ ERROR: Input del token CSRF no encontrado');
        showError('ERROR CRÍTICO: No se encontró el campo de seguridad. Recarga la página (F5).');
    }

    // ========== LISTAS DE SEGURIDAD ==========
    const commonPasswords = [
        'password', '123456', '12345678', 'qwerty', 'abc123', 
        'password123', '111111', '123123', 'admin', 'letmein',
        'welcome', 'monkey', '1234567', 'dragon', 'master',
        'iloveyou', 'princess', 'starwars', 'superman', 'batman',
        'qwerty123', 'abc12345', '12345', '654321', 'football'
    ];

    const disposableEmailDomains = [
        'tempmail.com', '10minutemail.com', 'guerrillamail.com', 
        'mailinator.com', 'throwaway.email', 'temp-mail.org',
        'maildrop.cc', 'yopmail.com', 'fakeinbox.com', 'trashmail.com',
        'getnada.com', 'emailondeck.com', 'throwawaymail.com'
    ];

    const suspiciousPatterns = {
        sql: /(\bOR\b|\bAND\b|\bUNION\b|\bSELECT\b|\bDROP\b|\bINSERT\b|\bDELETE\b|--|\/\*|\*\/|';|")/i,
        xss: /<script|javascript:|onerror=|onload=|onclick=|eval\(|alert\(/i,
        path: /\.\.\/|\.\.\\|\.\./,
        command: /\||&|;|\$\(|\`/
    };

    // ========== FUNCIONES AUXILIARES ==========
    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'errors';
        errorDiv.innerHTML = `<ul><li>${message}</li></ul>`;
        
        const existingError = form.querySelector('.errors');
        if (existingError) {
            existingError.remove();
        }
        
        form.insertBefore(errorDiv, form.firstChild);
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function removeError(input) {
        input.style.borderColor = '';
        const hint = input.parentElement.querySelector('.error-hint');
        if (hint) hint.remove();
    }

    function showFieldError(input, message) {
        input.style.borderColor = '#ef4444';
        
        const existingHint = input.parentElement.querySelector('.error-hint');
        if (existingHint) existingHint.remove();
        
        const hint = document.createElement('div');
        hint.className = 'error-hint';
        hint.style.cssText = 'color: #ef4444; font-size: 12px; margin-top: 4px;';
        hint.textContent = message;
        input.parentElement.appendChild(hint);
    }

    // ========== VALIDACIONES ==========

    // Detectar patrones sospechosos
    function detectSuspiciousPatterns(value) {
        for (const [type, pattern] of Object.entries(suspiciousPatterns)) {
            if (pattern.test(value)) {
                return `Patrón de entrada sospechoso detectado (${type})`;
            }
        }
        return null;
    }

    // Validar nombre/apellido
    function validateName(name, fieldName) {
        if (!name || name.trim().length === 0) {
            return `El ${fieldName} es obligatorio`;
        }

        const trimmed = name.trim();
        
        if (trimmed.length < 2) {
            return `El ${fieldName} debe tener al menos 2 caracteres`;
        }

        if (trimmed.length > 50) {
            return `El ${fieldName} no puede exceder 50 caracteres`;
        }

        // Solo letras, espacios, guiones y apóstrofes
        if (!/^[a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s'-]+$/u.test(trimmed)) {
            return `El ${fieldName} solo puede contener letras, espacios, guiones y apóstrofes`;
        }

        // No números
        if (/\d/.test(trimmed)) {
            return `El ${fieldName} no puede contener números`;
        }

        // No caracteres especiales peligrosos
        if (/<|>|&|;|\||\\|\$/.test(trimmed)) {
            return `El ${fieldName} contiene caracteres no permitidos`;
        }

        // No espacios múltiples
        if (/\s{2,}/.test(trimmed)) {
            return `El ${fieldName} no puede tener espacios múltiples`;
        }

        // No puede empezar/terminar con caracteres especiales
        if (/^[-'\s]|[-'\s]$/.test(trimmed)) {
            return `El ${fieldName} no puede empezar o terminar con caracteres especiales`;
        }

        return null;
    }

    // Validar DNI argentino
    function validateDNI(dni) {
        if (!/^[0-9]+$/.test(dni)) {
            return 'El DNI debe contener solo números';
        }
        
        const len = dni.length;
        if (len < 7 || len > 10) {
            return 'El DNI debe tener entre 7 y 10 dígitos';
        }
        
        // No todos iguales
        if (/^(\d)\1+$/.test(dni)) {
            return 'El DNI no puede tener todos los dígitos iguales';
        }
        
        const dniNum = parseInt(dni);
        if (dniNum < 1000000 || dniNum > 99999999) {
            return 'El DNI está fuera del rango válido argentino';
        }

        // No secuencias obvias
        if (/01234|12345|23456|34567|45678|56789|67890/.test(dni)) {
            return 'El DNI contiene una secuencia no válida';
        }
        
        return null;
    }

    // Validar email
    function validateEmail(email) {
        if (!email || email.trim().length === 0) {
            return 'El email es obligatorio';
        }

        const trimmed = email.trim().toLowerCase();

        // Formato básico
        const emailRegex = /^[a-z0-9]([a-z0-9._-]*[a-z0-9])?@[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/i;
        if (!emailRegex.test(trimmed)) {
            return 'El formato del email no es válido';
        }

        if (trimmed.length > 255) {
            return 'El email es demasiado largo';
        }

        // Verificar dominio desechable
        const domain = trimmed.split('@')[1];
        if (disposableEmailDomains.includes(domain)) {
            return 'No se permiten emails temporales o desechables';
        }

        // No múltiples @
        if ((trimmed.match(/@/g) || []).length > 1) {
            return 'El email no puede contener múltiples @';
        }

        // No puntos consecutivos
        if (/\.{2,}/.test(trimmed)) {
            return 'El email no puede tener puntos consecutivos';
        }

        // La parte local no puede empezar/terminar con punto
        const localPart = trimmed.split('@')[0];
        if (localPart.startsWith('.') || localPart.endsWith('.')) {
            return 'El email no puede empezar o terminar con punto';
        }

        // Detectar patrones sospechosos
        const suspicious = detectSuspiciousPatterns(trimmed);
        if (suspicious) {
            return suspicious;
        }

        return null;
    }

    // Calcular fortaleza de contraseña
    function calculatePasswordStrength(pwd) {
        let strength = 0;
        const feedback = [];

        if (pwd.length === 0) return { strength: 0, feedback: [] };

        // Longitud
        if (pwd.length >= 8) strength += 1;
        if (pwd.length >= 12) strength += 2;
        if (pwd.length >= 16) strength += 1;

        // Complejidad
        if (/[a-z]/.test(pwd)) strength += 1;
        if (/[A-Z]/.test(pwd)) strength += 1;
        if (/[0-9]/.test(pwd)) strength += 1;
        if (/[^a-zA-Z0-9]/.test(pwd)) strength += 2;

        // Penalizaciones
        if (/^(.)\1+$/.test(pwd)) {
            strength = 0;
            feedback.push('No uses caracteres repetidos');
        }

        if (/^(012|123|234|345|456|567|678|789|890|abc|bcd|cde)/i.test(pwd)) {
            strength -= 2;
            feedback.push('Evita secuencias obvias');
        }

        const lowerPwd = pwd.toLowerCase();
        if (commonPasswords.some(common => lowerPwd.includes(common))) {
            strength = 0;
            feedback.push('Contraseña muy común');
        }

        // Detectar repeticiones de patrones
        if (/(.{2,})\1{2,}/.test(pwd)) {
            strength -= 1;
            feedback.push('Evita repetir patrones');
        }

        return { strength: Math.max(0, strength), feedback };
    }

    // Validar fortaleza de contraseña
    function validatePassword(pwd) {
        if (pwd.length < 8) {
            return 'La contraseña debe tener al menos 8 caracteres';
        }

        if (pwd.length > 128) {
            return 'La contraseña no puede exceder 128 caracteres';
        }

        if (!/[A-Z]/.test(pwd)) {
            return 'Debe contener al menos una mayúscula';
        }

        if (!/[a-z]/.test(pwd)) {
            return 'Debe contener al menos una minúscula';
        }

        if (!/[0-9]/.test(pwd)) {
            return 'Debe contener al menos un número';
        }

        if (pwd.includes(' ')) {
            return 'No puede contener espacios';
        }

        const lowerPwd = pwd.toLowerCase();
        if (commonPasswords.some(common => lowerPwd.includes(common))) {
            return 'Esta contraseña es muy común, elige una más segura';
        }

        // Detectar patrones sospechosos
        const suspicious = detectSuspiciousPatterns(pwd);
        if (suspicious) {
            return suspicious;
        }

        return null;
    }

    // ========== EVENT LISTENERS ==========

    // Mostrar/ocultar "Otra obra social"
    obraSelect?.addEventListener('change', function() {
        const value = this.value;
        
        if (value === '-1') {
            fieldOtraObra.classList.remove('hidden');
            otraObraInput.setAttribute('required', 'required');
        } else {
            fieldOtraObra.classList.add('hidden');
            otraObraInput.removeAttribute('required');
            otraObraInput.value = '';
        }

        // Ocultar número de carnet si eligió "Sin obra social"
        // Asumimos que ID 7 es "Sin obra social" según el dump SQL
        if (value === '7') {
            fieldNroCarnet.classList.add('hidden');
            nroCarnetInput.value = '';
            nroCarnetInput.removeAttribute('required');
        } else if (value !== '' && value !== '-1') {
            fieldNroCarnet.classList.remove('hidden');
        }
    });

    // Ejecutar al cargar si ya estaba seleccionado
    if (obraSelect) {
        const currentValue = obraSelect.value;
        if (currentValue === '-1') {
            fieldOtraObra.classList.remove('hidden');
            otraObraInput.setAttribute('required', 'required');
        }
        if (currentValue === '7') {
            fieldNroCarnet.classList.add('hidden');
            nroCarnetInput.value = '';
        }
    }

    // Validar y capitalizar nombres en tiempo real
    [nombreInput, apellidoInput].forEach(input => {
        if (!input) return;

        input.addEventListener('input', function(e) {
            // Eliminar caracteres no permitidos
            this.value = this.value.replace(/[^a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s'-]/g, '');
            
            // Eliminar espacios múltiples
            this.value = this.value.replace(/\s{2,}/g, ' ');
            
            // No permitir empezar con espacio
            if (this.value.startsWith(' ')) {
                this.value = this.value.trim();
            }

            // Capitalizar primera letra
            if (this.value.length === 1) {
                this.value = this.value.toUpperCase();
            }

            removeError(this);
        });

        input.addEventListener('blur', function() {
            this.value = this.value.trim();
            
            // Capitalizar cada palabra
            this.value = this.value.split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');
            
            const fieldName = this.id === 'nombre' ? 'nombre' : 'apellido';
            const error = validateName(this.value, fieldName);
            
            if (error) {
                showFieldError(this, error);
            } else {
                removeError(this);
            }
        });
    });

    // Validar DNI en tiempo real
    dniInput?.addEventListener('input', function(e) {
        const cursorPos = this.selectionStart;
        const oldValue = this.value;
        
        // Solo números
        this.value = this.value.replace(/[^0-9]/g, '');
        
        if (oldValue.length !== this.value.length) {
            this.setSelectionRange(cursorPos - 1, cursorPos - 1);
        }

        removeError(this);
    });

    dniInput?.addEventListener('blur', function() {
        const error = validateDNI(this.value);
        if (error) {
            showFieldError(this, error);
        } else {
            removeError(this);
        }
    });

    // Validar email en tiempo real
    emailInput?.addEventListener('input', function() {
        this.value = this.value.toLowerCase().trim();
        removeError(this);
    });

    emailInput?.addEventListener('blur', function() {
        const error = validateEmail(this.value);
        if (error) {
            showFieldError(this, error);
        } else {
            removeError(this);
        }
    });

    // Fortaleza de contraseña en tiempo real
    password?.addEventListener('input', function() {
        const val = this.value;
        const { strength, feedback } = calculatePasswordStrength(val);

        if (val.length === 0) {
            strengthMsg.textContent = '';
            strengthMsg.style.color = '#94a3b8';
            return;
        }

        let message = '';
        let color = '#94a3b8';

        if (val.length < 8) {
            message = '⚠️ Muy corta (mínimo 8 caracteres)';
            color = '#ef4444';
        } else if (strength <= 3) {
            message = '🔴 Débil - Agrega mayúsculas, números y símbolos';
            color = '#ef4444';
        } else if (strength <= 5) {
            message = '🟡 Media - Mejora con más caracteres o símbolos';
            color = '#fb923c';
        } else if (strength <= 7) {
            message = '🟢 Buena - Contraseña aceptable';
            color = '#10b981';
        } else {
            message = '🔒 Excelente - Contraseña muy segura';
            color = '#22d3ee';
        }

        if (feedback.length > 0) {
            message += ' | ' + feedback.join(', ');
        }

        strengthMsg.textContent = message;
        strengthMsg.style.color = color;

        removeError(this);
    });

    // Validar coincidencia de contraseñas
    password2?.addEventListener('input', function() {
        if (this.value && password.value !== this.value) {
            showFieldError(this, 'Las contraseñas no coinciden');
        } else {
            removeError(this);
        }
    });

    // Prevenir espacios en contraseñas
    [password, password2].forEach(input => {
        input?.addEventListener('keypress', function(e) {
            if (e.key === ' ') {
                e.preventDefault();
                showFieldError(this, 'Las contraseñas no pueden contener espacios');
            }
        });

        input?.addEventListener('paste', function(e) {
            setTimeout(() => {
                if (this.value.includes(' ')) {
                    this.value = this.value.replace(/\s/g, '');
                    showFieldError(this, 'Se eliminaron los espacios de la contraseña');
                }
            }, 10);
        });
    });

    // Prevenir envío múltiple
    let isSubmitting = false;

    // ========== VALIDACIÓN COMPLETA AL ENVIAR ==========
    form?.addEventListener('submit', function(e) {
        e.preventDefault();

        // Verificar token CSRF
        const tokenValue = csrfTokenInput ? csrfTokenInput.value : '';
        
        if (!tokenValue || tokenValue.length < 32) {
            showError('ERROR DE SEGURIDAD: El token de protección es inválido. Recarga la página (F5).');
            console.error('Token CSRF inválido:', tokenValue);
            return false;
        }

        console.log('🔐 Enviando formulario con token:', tokenValue.substring(0, 10) + '...');

        if (isSubmitting) {
            console.log('⚠️ Formulario ya está siendo enviado');
            return false;
        }

        const errors = [];

        // Validar todos los campos
        const nombre = nombreInput.value;
        const nombreError = validateName(nombre, 'nombre');
        if (nombreError) {
            errors.push(nombreError);
            showFieldError(nombreInput, nombreError);
        }

        const apellido = apellidoInput.value;
        const apellidoError = validateName(apellido, 'apellido');
        if (apellidoError) {
            errors.push(apellidoError);
            showFieldError(apellidoInput, apellidoError);
        }

        const dni = dniInput.value.trim();
        const dniError = validateDNI(dni);
        if (dniError) {
            errors.push(dniError);
            showFieldError(dniInput, dniError);
        }

        const email = emailInput.value;
        const emailError = validateEmail(email);
        if (emailError) {
            errors.push(emailError);
            showFieldError(emailInput, emailError);
        }

        const pwd = password.value;
        const pwd2 = password2.value;

        const passwordError = validatePassword(pwd);
        if (passwordError) {
            errors.push(passwordError);
            showFieldError(password, passwordError);
        }

        if (pwd !== pwd2) {
            errors.push('Las contraseñas no coinciden');
            showFieldError(password2, 'Las contraseñas no coinciden');
        }

        // Validar obra social
        const obraValue = obraSelect?.value;
        
        if (!obraValue || obraValue === '') {
            errors.push('Debes seleccionar una obra social');
        }

        if (obraValue === '-1') {
            const otraObra = otraObraInput?.value.trim();
            if (!otraObra) {
                errors.push('Debes especificar el nombre de tu obra social');
            } else if (otraObra.length < 3) {
                errors.push('El nombre de la obra social debe tener al menos 3 caracteres');
            }
        }

        // Validar libreta sanitaria
        const libreta = document.getElementById('libreta_sanitaria')?.value.trim();
        if (!libreta) {
            errors.push('La libreta sanitaria es obligatoria');
        } else if (libreta.length < 3) {
            errors.push('La libreta sanitaria debe tener al menos 3 caracteres');
        }

        // Mostrar errores
        if (errors.length > 0) {
            showError('Por favor corrige los siguientes errores:\n\n• ' + errors.join('\n• '));
            
            // Focus en el primer campo con error
            const firstErrorField = form.querySelector('input[style*="border-color: rgb(239, 68, 68)"]');
            if (firstErrorField) {
                firstErrorField.focus();
                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            return false;
        }

        // Marcar como enviando
        isSubmitting = true;

        // Deshabilitar botón de envío
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creando cuenta...';
            submitBtn.style.opacity = '0.6';
            submitBtn.style.cursor = 'not-allowed';
        }

        // Deshabilitar todos los inputs
        const inputs = form.querySelectorAll('input, select, button');
        inputs.forEach(input => input.disabled = true);

        // Enviar formulario
        console.log('✅ Validación completa - Enviando formulario');
        form.submit();
    });

    // Resetear flag si volvemos a la página
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            isSubmitting = false;
            const submitBtn = form?.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Crear cuenta';
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
            
            // Re-habilitar inputs
            const inputs = form?.querySelectorAll('input, select, button');
            inputs?.forEach(input => input.disabled = false);
        }
    });

    console.log('✅ Register.js initialized');
})();