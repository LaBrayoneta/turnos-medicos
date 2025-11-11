// register.js - Validaciones de formulario de registro con verificación CSRF

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
    const otraObraInput = document.getElementById('obra_social_otra');
    const csrfTokenInput = document.getElementById('csrf_token');

    // ✅ VERIFICACIÓN CSRF AL CARGAR
    if (csrfTokenInput) {
        const tokenValue = csrfTokenInput.value;
        console.log('🔐 CSRF Token cargado:', tokenValue ? tokenValue.substring(0, 10) + '...' : 'VACÍO');
        
        if (!tokenValue || tokenValue.length < 32) {
            console.error('❌ ERROR: Token CSRF inválido o faltante');
            alert('⚠️ ERROR CRÍTICO\n\nEl token de seguridad no se cargó correctamente.\n\nRecarga la página (F5) antes de continuar.');
        }
    } else {
        console.error('❌ ERROR: Input del token CSRF no encontrado');
        alert('⚠️ ERROR CRÍTICO\n\nNo se encontró el campo de seguridad.\n\nRecarga la página (F5).');
    }

    // Lista de contraseñas comunes a evitar
    const commonPasswords = [
        'password', '123456', '12345678', 'qwerty', 'abc123', 
        'password123', '111111', '123123', 'admin', 'letmein',
        'welcome', 'monkey', '1234567', 'dragon', 'master',
        'iloveyou', 'princess', 'starwars', 'superman', 'batman'
    ];

    // Dominios de email desechables conocidos
    const disposableEmailDomains = [
        'tempmail.com', '10minutemail.com', 'guerrillamail.com', 
        'mailinator.com', 'throwaway.email', 'temp-mail.org',
        'maildrop.cc', 'yopmail.com', 'fakeinbox.com'
    ];

    // Mostrar/ocultar campo "Otra obra social"
    obraSelect?.addEventListener('change', function() {
        if (this.value === '-1') {
            fieldOtraObra.classList.remove('hidden');
            otraObraInput.setAttribute('required', 'required');
        } else {
            fieldOtraObra.classList.add('hidden');
            otraObraInput.removeAttribute('required');
            otraObraInput.value = '';
        }
    });

    // Ejecutar al cargar si ya estaba seleccionado
    if (obraSelect && obraSelect.value === '-1') {
        fieldOtraObra.classList.remove('hidden');
        otraObraInput.setAttribute('required', 'required');
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
            return `El ${fieldName} no puede tener más de 50 caracteres`;
        }

        if (!/^[a-záéíóúñü\s'-]+$/i.test(trimmed)) {
            return `El ${fieldName} solo puede contener letras, espacios, guiones y apóstrofes`;
        }

        if (/\d/.test(trimmed)) {
            return `El ${fieldName} no puede contener números`;
        }

        if (/\s{2,}/.test(trimmed)) {
            return `El ${fieldName} no puede tener espacios consecutivos`;
        }

        if (/^[-'\s]|[-'\s]$/.test(trimmed)) {
            return `El ${fieldName} no puede empezar o terminar con caracteres especiales`;
        }

        return null;
    }

    // Validar DNI argentino
    function validateArgentineDNI(dni) {
        if (!/^[0-9]+$/.test(dni)) {
            return 'El DNI debe contener solo números';
        }
        
        const len = dni.length;
        if (len < 7 || len > 10) {
            return 'El DNI debe tener entre 7 y 10 dígitos';
        }
        
        if (/^(\d)\1+$/.test(dni)) {
            return 'El DNI no puede tener todos los dígitos iguales';
        }
        
        const dniNum = parseInt(dni);
        if (dniNum < 1000000 || dniNum > 99999999) {
            return 'El DNI está fuera del rango válido';
        }
        
        return null;
    }

    // Validar email
    function validateEmail(email) {
        if (!email || email.trim().length === 0) {
            return 'El email es obligatorio';
        }

        const trimmed = email.trim().toLowerCase();

        const emailRegex = /^[a-z0-9]([a-z0-9._-]*[a-z0-9])?@[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/i;
        if (!emailRegex.test(trimmed)) {
            return 'El formato del email no es válido';
        }

        if (trimmed.length > 255) {
            return 'El email es demasiado largo';
        }

        const domain = trimmed.split('@')[1];
        if (disposableEmailDomains.includes(domain)) {
            return 'No se permiten emails temporales o desechables';
        }

        if ((trimmed.match(/@/g) || []).length > 1) {
            return 'El email no puede contener múltiples @';
        }

        if (/\.{2,}/.test(trimmed)) {
            return 'El email no puede tener puntos consecutivos';
        }

        const localPart = trimmed.split('@')[0];
        if (localPart.startsWith('.') || localPart.endsWith('.')) {
            return 'El email no puede empezar o terminar con punto';
        }

        return null;
    }

    // Calcular fortaleza de contraseña
    function calculatePasswordStrength(pwd) {
        let strength = 0;
        const feedback = [];

        if (pwd.length === 0) return { strength: 0, feedback: [] };

        if (pwd.length >= 8) strength += 1;
        if (pwd.length >= 12) strength += 1;
        if (pwd.length >= 16) strength += 1;

        if (/[a-z]/.test(pwd)) strength += 1;
        if (/[A-Z]/.test(pwd)) strength += 1;
        if (/[0-9]/.test(pwd)) strength += 1;
        if (/[^a-zA-Z0-9]/.test(pwd)) strength += 1;

        if (/^(.)\1+$/.test(pwd)) {
            strength = 0;
            feedback.push('No uses caracteres repetidos');
        }

        if (/^(012|123|234|345|456|567|678|789|890|abc|bcd|cde)/i.test(pwd)) {
            strength -= 2;
            feedback.push('Evitá secuencias obvias');
        }

        const lowerPwd = pwd.toLowerCase();
        if (commonPasswords.some(common => lowerPwd.includes(common))) {
            strength = 0;
            feedback.push('Contraseña demasiado común');
        }

        return { strength: Math.max(0, strength), feedback };
    }

    // Validar fortaleza de contraseña en tiempo real
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
            message = '🔴 Débil - Agregá mayúsculas, números y símbolos';
            color = '#ef4444';
        } else if (strength <= 5) {
            message = '🟡 Media - Mejorá con más caracteres o símbolos';
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
    });

    // Validar coincidencia de contraseñas en tiempo real
    password2?.addEventListener('input', function() {
        if (this.value && password.value !== this.value) {
            this.setCustomValidity('Las contraseñas no coinciden');
            this.style.borderColor = '#ef4444';
        } else {
            this.setCustomValidity('');
            this.style.borderColor = '#1f2937';
        }
    });

    // Prevenir espacios en contraseña
    [password, password2].forEach(input => {
        input?.addEventListener('keypress', function(e) {
            if (e.key === ' ') {
                e.preventDefault();
                alert('⚠️ Las contraseñas no pueden contener espacios');
            }
        });

        input?.addEventListener('paste', function(e) {
            setTimeout(() => {
                if (this.value.includes(' ')) {
                    this.value = this.value.replace(/\s/g, '');
                    alert('⚠️ Se eliminaron los espacios de la contraseña');
                }
            }, 10);
        });
    });

    // Solo números en DNI
    dniInput?.addEventListener('input', function(e) {
        const cursorPos = this.selectionStart;
        const oldValue = this.value;
        this.value = this.value.replace(/[^0-9]/g, '');
        
        if (oldValue.length !== this.value.length) {
            this.setSelectionRange(cursorPos - 1, cursorPos - 1);
        }
    });

    // Validar y limpiar nombres en tiempo real
    [nombreInput, apellidoInput].forEach(input => {
        input?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-záéíóúñü\s'-]/gi, '');
            this.value = this.value.replace(/\s{2,}/g, ' ');
            
            if (this.value.startsWith(' ')) {
                this.value = this.value.trim();
            }
            
            if (this.value.length === 1) {
                this.value = this.value.toUpperCase();
            }
        });

        input?.addEventListener('blur', function() {
            this.value = this.value.trim();
            
            const fieldName = this.id === 'nombre' ? 'nombre' : 'apellido';
            const error = validateName(this.value, fieldName);
            
            if (error) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#1f2937';
            }
        });
    });

    // Validar email en tiempo real
    emailInput?.addEventListener('blur', function() {
        const error = validateEmail(this.value);
        if (error) {
            this.style.borderColor = '#ef4444';
        } else {
            this.style.borderColor = '#1f2937';
        }
    });

    // Prevenir envío múltiple del formulario
    let isSubmitting = false;

    // Validación completa del formulario antes de enviar
    form?.addEventListener('submit', function(e) {
        e.preventDefault();

        // ✅ VERIFICAR TOKEN CSRF ANTES DE ENVIAR
        const tokenValue = csrfTokenInput ? csrfTokenInput.value : '';
        
        if (!tokenValue || tokenValue.length < 32) {
            alert('⚠️ ERROR DE SEGURIDAD\n\nEl token de protección es inválido.\n\nRecarga la página (F5) e intenta nuevamente.');
            console.error('Token CSRF inválido:', tokenValue);
            return false;
        }

        console.log('🔐 Enviando formulario con token:', tokenValue.substring(0, 10) + '...');

        if (isSubmitting) {
            console.log('⚠️ Formulario ya está siendo enviado');
            return false;
        }

        const errors = [];

        // Validar nombre
        const nombre = nombreInput.value;
        const nombreError = validateName(nombre, 'nombre');
        if (nombreError) errors.push(nombreError);

        // Validar apellido
        const apellido = apellidoInput.value;
        const apellidoError = validateName(apellido, 'apellido');
        if (apellidoError) errors.push(apellidoError);

        // Validar DNI
        const dni = dniInput.value.trim();
        const dniError = validateArgentineDNI(dni);
        if (dniError) errors.push(dniError);

        // Validar email
        const email = emailInput.value;
        const emailError = validateEmail(email);
        if (emailError) errors.push(emailError);

        // Validar contraseñas
        const pwd = password.value;
        const pwd2 = password2.value;

        if (pwd.length < 8) {
            errors.push('La contraseña debe tener al menos 8 caracteres');
        }

        if (pwd.length > 128) {
            errors.push('La contraseña no puede exceder 128 caracteres');
        }

        if (!/[A-Z]/.test(pwd)) {
            errors.push('La contraseña debe contener al menos una mayúscula');
        }

        if (!/[a-z]/.test(pwd)) {
            errors.push('La contraseña debe contener al menos una minúscula');
        }

        if (!/[0-9]/.test(pwd)) {
            errors.push('La contraseña debe contener al menos un número');
        }

        const lowerPwd = pwd.toLowerCase();
        if (commonPasswords.some(common => lowerPwd.includes(common))) {
            errors.push('La contraseña es demasiado común. Elegí una más segura');
        }

        if (pwd !== pwd2) {
            errors.push('Las contraseñas no coinciden');
        }

        if (pwd.includes(' ') || pwd2.includes(' ')) {
            errors.push('Las contraseñas no pueden contener espacios');
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
            } else if (otraObra.length > 100) {
                errors.push('El nombre de la obra social es demasiado largo');
            }
        }

        // Validar libreta sanitaria
        const libreta = document.getElementById('libreta_sanitaria')?.value.trim();
        if (!libreta) {
            errors.push('La libreta sanitaria es obligatoria');
        } else if (libreta.length < 3) {
            errors.push('La libreta sanitaria debe tener al menos 3 caracteres');
        } else if (libreta.length > 50) {
            errors.push('La libreta sanitaria es demasiado larga');
        }

        // Validar número de carnet (opcional)
        const nroCarnet = document.getElementById('nro_carnet')?.value.trim();
        if (nroCarnet && nroCarnet.length > 50) {
            errors.push('El número de carnet es demasiado largo');
        }

        // Mostrar errores
        if (errors.length > 0) {
            alert('⚠️ Por favor corregí los siguientes errores:\n\n• ' + errors.join('\n• '));
            
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
        }

        // Deshabilitar todos los inputs
        const inputs = form.querySelectorAll('input, select, button');
        inputs.forEach(input => input.disabled = true);

        // Todo OK - enviar formulario
        console.log('✅ Validación completa - Enviando formulario');
        form.submit();
    });

    // Resetear el flag si hay un error de servidor y volvemos a la página
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            isSubmitting = false;
            const submitBtn = form?.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Crear cuenta';
            }
        }
    });

})();