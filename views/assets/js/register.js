// register.js - JavaScript para página de registro
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
    const csrfTokenInput = document.querySelector('input[name="csrf_token"]');

    // Verificación CSRF
    if (csrfTokenInput) {
        const tokenValue = csrfTokenInput.value;
        if (!tokenValue || tokenValue.length < 32) {
            console.error('❌ Token CSRF inválido');
            alert('⚠️ Error de seguridad. Recargá la página (F5).');
        }
    }

    // Contraseñas comunes
    const commonPasswords = ['password', '123456', '12345678', 'qwerty', 'abc123', 'password123', '111111', '123123', 'admin', 'letmein'];

    // Emails desechables
    const disposableEmailDomains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 'yopmail.com'];

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

    if (obraSelect && obraSelect.value === '-1') {
        fieldOtraObra.classList.remove('hidden');
        otraObraInput.setAttribute('required', 'required');
    }

    // Validar nombre/apellido
    function validateName(name, fieldName) {
        if (!name || name.trim().length === 0) return `El ${fieldName} es obligatorio`;
        const trimmed = name.trim();
        if (trimmed.length < 2) return `El ${fieldName} debe tener al menos 2 caracteres`;
        if (trimmed.length > 50) return `El ${fieldName} no puede tener más de 50 caracteres`;
        if (!/^[a-záéíóúñü\s'-]+$/i.test(trimmed)) return `El ${fieldName} solo puede contener letras`;
        if (/\d/.test(trimmed)) return `El ${fieldName} no puede contener números`;
        return null;
    }

    // Validar DNI
    function validateDNI(dni) {
        if (!/^[0-9]+$/.test(dni)) return 'El DNI debe contener solo números';
        if (dni.length < 7 || dni.length > 10) return 'El DNI debe tener entre 7 y 10 dígitos';
        if (/^(\d)\1+$/.test(dni)) return 'El DNI no puede tener todos los dígitos iguales';
        return null;
    }

    // Validar email
    function validateEmail(email) {
        if (!email || email.trim().length === 0) return 'El email es obligatorio';
        const trimmed = email.trim().toLowerCase();
        const emailRegex = /^[a-z0-9]([a-z0-9._-]*[a-z0-9])?@[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/i;
        if (!emailRegex.test(trimmed)) return 'El formato del email no es válido';
        const domain = trimmed.split('@')[1];
        if (disposableEmailDomains.includes(domain)) return 'No se permiten emails temporales';
        return null;
    }

    // Fortaleza de contraseña
    function calculateStrength(pwd) {
        let strength = 0;
        if (pwd.length >= 8) strength++;
        if (pwd.length >= 12) strength++;
        if (/[a-z]/.test(pwd)) strength++;
        if (/[A-Z]/.test(pwd)) strength++;
        if (/[0-9]/.test(pwd)) strength++;
        if (/[^a-zA-Z0-9]/.test(pwd)) strength++;
        const lowerPwd = pwd.toLowerCase();
        if (commonPasswords.some(c => lowerPwd.includes(c))) strength = 0;
        return strength;
    }

    // Validar contraseña en tiempo real
    password?.addEventListener('input', function() {
        const val = this.value;
        const strength = calculateStrength(val);

        if (val.length === 0) {
            strengthMsg.textContent = '';
            return;
        }

        if (val.length < 8) {
            strengthMsg.textContent = '⚠️ Muy corta (mínimo 8 caracteres)';
            strengthMsg.style.color = '#ef4444';
        } else if (strength <= 3) {
            strengthMsg.textContent = '🔴 Débil';
            strengthMsg.style.color = '#ef4444';
        } else if (strength <= 5) {
            strengthMsg.textContent = '🟡 Media';
            strengthMsg.style.color = '#fb923c';
        } else {
            strengthMsg.textContent = '🟢 Fuerte';
            strengthMsg.style.color = '#10b981';
        }
    });

    // Validar coincidencia
    password2?.addEventListener('input', function() {
        if (this.value && password.value !== this.value) {
            this.setCustomValidity('Las contraseñas no coinciden');
            this.style.borderColor = '#ef4444';
        } else {
            this.setCustomValidity('');
            this.style.borderColor = '#1f2937';
        }
    });

    // Solo números en DNI
    dniInput?.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Limpiar nombres
    [nombreInput, apellidoInput].forEach(input => {
        input?.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-záéíóúñü\s'-]/gi, '');
            if (this.value.length === 1) this.value = this.value.toUpperCase();
        });
    });

    // Prevenir envío múltiple
    let isSubmitting = false;

    // Validación al enviar
    form?.addEventListener('submit', function(e) {
        if (isSubmitting) { e.preventDefault(); return false; }

        const errors = [];

        const nombreError = validateName(nombreInput?.value, 'nombre');
        if (nombreError) errors.push(nombreError);

        const apellidoError = validateName(apellidoInput?.value, 'apellido');
        if (apellidoError) errors.push(apellidoError);

        const dniError = validateDNI(dniInput?.value.trim());
        if (dniError) errors.push(dniError);

        const emailError = validateEmail(emailInput?.value);
        if (emailError) errors.push(emailError);

        const pwd = password?.value || '';
        const pwd2 = password2?.value || '';

        if (pwd.length < 8) errors.push('La contraseña debe tener al menos 8 caracteres');
        if (!/[A-Z]/.test(pwd)) errors.push('La contraseña debe contener al menos una mayúscula');
        if (!/[a-z]/.test(pwd)) errors.push('La contraseña debe contener al menos una minúscula');
        if (!/[0-9]/.test(pwd)) errors.push('La contraseña debe contener al menos un número');
        if (pwd !== pwd2) errors.push('Las contraseñas no coinciden');

        const obraValue = obraSelect?.value;
        if (!obraValue || obraValue === '') errors.push('Debés seleccionar una obra social');
        if (obraValue === '-1' && !otraObraInput?.value.trim()) errors.push('Debés especificar tu obra social');

        const libreta = document.getElementById('libreta_sanitaria')?.value.trim();
        if (!libreta) errors.push('La libreta sanitaria es obligatoria');

        if (errors.length > 0) {
            e.preventDefault();
            alert('⚠️ Corregí los siguientes errores:\n\n• ' + errors.join('\n• '));
            return false;
        }

        isSubmitting = true;
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creando cuenta...';
        }
    });

    console.log('📝 Register form initialized');
})();