// admin_validation.js - Validaciones estrictas para formularios de admin

(function() {
    'use strict';

    // ========== VALIDACIONES ==========
    
    // Validar nombre/apellido
    function validateName(value, fieldName) {
        value = value.trim();
        
        if (!value) {
            return `El ${fieldName} es obligatorio`;
        }
        
        if (value.length < 2) {
            return `El ${fieldName} debe tener al menos 2 caracteres`;
        }
        
        if (value.length > 50) {
            return `El ${fieldName} no puede exceder 50 caracteres`;
        }
        
        // Solo letras, espacios, guiones y apóstrofes
        if (!/^[a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s'-]+$/.test(value)) {
            return `El ${fieldName} solo puede contener letras, espacios, guiones y apóstrofes`;
        }
        
        // No números
        if (/\d/.test(value)) {
            return `El ${fieldName} no puede contener números`;
        }
        
        // No espacios múltiples
        if (/\s{2,}/.test(value)) {
            return `El ${fieldName} no puede tener espacios múltiples`;
        }
        
        return null;
    }
    
    // Validar DNI
    function validateDNI(dni) {
        dni = dni.trim();
        
        if (!dni) {
            return 'El DNI es obligatorio';
        }
        
        if (!/^\d+$/.test(dni)) {
            return 'El DNI debe contener solo números';
        }
        
        const len = dni.length;
        if (len < 7 || len > 10) {
            return 'El DNI debe tener entre 7 y 10 dígitos';
        }
        
        // No todos los dígitos iguales
        if (/^(\d)\1+$/.test(dni)) {
            return 'DNI inválido';
        }
        
        const dniNum = parseInt(dni);
        if (dniNum < 1000000 || dniNum > 99999999) {
            return 'DNI fuera de rango válido';
        }
        
        return null;
    }
    
    // Validar email
    function validateEmail(email) {
        email = email.trim().toLowerCase();
        
        if (!email) {
            return 'El email es obligatorio';
        }
        
        const emailRegex = /^[a-z0-9]([a-z0-9._-]*[a-z0-9])?@[a-z0-9]([a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/i;
        if (!emailRegex.test(email)) {
            return 'Formato de email inválido';
        }
        
        if (email.length > 255) {
            return 'El email es demasiado largo';
        }
        
        // No puntos consecutivos
        if (/\.{2,}/.test(email)) {
            return 'Email inválido';
        }
        
        return null;
    }
    
    // Validar legajo
    function validateLegajo(legajo) {
        legajo = legajo.trim();
        
        if (!legajo) {
            return 'El legajo es obligatorio';
        }
        
        if (legajo.length < 2) {
            return 'El legajo debe tener al menos 2 caracteres';
        }
        
        if (legajo.length > 20) {
            return 'El legajo no puede exceder 20 caracteres';
        }
        
        // Alfanumérico, guiones y guiones bajos
        if (!/^[a-zA-Z0-9_-]+$/.test(legajo)) {
            return 'El legajo solo puede contener letras, números, guiones y guiones bajos';
        }
        
        return null;
    }
    
    // Validar contraseña
    function validatePassword(password) {
        if (!password) {
            return 'La contraseña es obligatoria';
        }
        
        if (password.length < 8) {
            return 'La contraseña debe tener al menos 8 caracteres';
        }
        
        if (password.length > 128) {
            return 'La contraseña es demasiado larga';
        }
        
        if (!/[A-Z]/.test(password)) {
            return 'La contraseña debe contener al menos una mayúscula';
        }
        
        if (!/[a-z]/.test(password)) {
            return 'La contraseña debe contener al menos una minúscula';
        }
        
        if (!/[0-9]/.test(password)) {
            return 'La contraseña debe contener al menos un número';
        }
        
        return null;
    }
    
    // Validar hora
    function validateTime(time) {
        if (!time) {
            return 'La hora es obligatoria';
        }
        
        if (!/^\d{2}:\d{2}$/.test(time)) {
            return 'Formato de hora inválido (HH:MM)';
        }
        
        const [hours, minutes] = time.split(':').map(Number);
        
        if (hours < 0 || hours > 23) {
            return 'Hora inválida';
        }
        
        if (minutes < 0 || minutes > 59) {
            return 'Minutos inválidos';
        }
        
        // Restringir a horario laboral (6 AM - 10 PM)
        if (hours < 6 || hours >= 22) {
            return 'El horario debe estar entre 6:00 AM y 10:00 PM';
        }
        
        return null;
    }
    
    // Validar rango de horario
    function validateTimeRange(inicio, fin) {
        const errorInicio = validateTime(inicio);
        if (errorInicio) return errorInicio;
        
        const errorFin = validateTime(fin);
        if (errorFin) return errorFin;
        
        if (inicio >= fin) {
            return 'La hora de inicio debe ser anterior a la hora de fin';
        }
        
        // Duración mínima de 1 hora
        const [hInicio, mInicio] = inicio.split(':').map(Number);
        const [hFin, mFin] = fin.split(':').map(Number);
        
        const minutosInicio = hInicio * 60 + mInicio;
        const minutosFin = hFin * 60 + mFin;
        const duracion = minutosFin - minutosInicio;
        
        if (duracion < 60) {
            return 'El bloque horario debe tener al menos 1 hora de duración';
        }
        
        if (duracion > 720) { // 12 horas
            return 'El bloque horario no puede exceder 12 horas';
        }
        
        return null;
    }
    
    // Formatear hora a 12h con AM/PM
    function formatHour12(time24) {
        if (!time24) return '';
        const [hours, minutes] = time24.split(':');
        let h = parseInt(hours);
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${h}:${minutes} ${ampm}`;
    }
    
    // Validar día de la semana (solo lunes a viernes)
    function validateWeekday(dia) {
        const diasValidos = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'];
        
        if (!diasValidos.includes(dia)) {
            return 'Solo se pueden agregar horarios de lunes a viernes';
        }
        
        return null;
    }
    
    // ========== APLICAR VALIDACIONES A CAMPOS ==========
    
    // Solo letras en nombres
    function setupNameInput(input) {
        if (!input) return;
        
        input.addEventListener('input', function(e) {
            // Eliminar números y caracteres especiales (excepto acentos, espacios, guiones, apóstrofes)
            this.value = this.value.replace(/[^a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s'-]/g, '');
            
            // Evitar espacios múltiples
            this.value = this.value.replace(/\s{2,}/g, ' ');
            
            // Capitalizar primera letra
            if (this.value.length === 1) {
                this.value = this.value.toUpperCase();
            }
        });
        
        input.addEventListener('blur', function() {
            this.value = this.value.trim();
        });
    }
    
    // Solo números en DNI
    function setupDNIInput(input) {
        if (!input) return;
        
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });
    }
    
    // Email lowercase
    function setupEmailInput(input) {
        if (!input) return;
        
        input.addEventListener('input', function(e) {
            // Eliminar espacios
            this.value = this.value.replace(/\s/g, '');
        });
        
        input.addEventListener('blur', function() {
            this.value = this.value.toLowerCase().trim();
        });
    }
    
    // Legajo alfanumérico
    function setupLegajoInput(input) {
        if (!input) return;
        
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^a-zA-Z0-9_-]/g, '');
            if (this.value.length > 20) {
                this.value = this.value.slice(0, 20);
            }
        });
    }
    
    // ========== RESTRICCIONES DE DÍAS (SOLO LUNES A VIERNES) ==========
    
    function setupDaySelect(select) {
        if (!select) return;
        
        // Limpiar opciones existentes
        select.innerHTML = '';
        
        // Solo días laborales
        const diasLaborales = [
            { value: 'lunes', text: 'Lunes' },
            { value: 'martes', text: 'Martes' },
            { value: 'miercoles', text: 'Miércoles' },
            { value: 'jueves', text: 'Jueves' },
            { value: 'viernes', text: 'Viernes' }
        ];
        
        diasLaborales.forEach(dia => {
            const option = document.createElement('option');
            option.value = dia.value;
            option.textContent = dia.text;
            select.appendChild(option);
        });
    }
    
    // ========== MOSTRAR AM/PM EN HORAS ==========
    
    function setupTimeInputWithLabel(input) {
        if (!input) return;
        
        const updateLabel = () => {
            const value = input.value;
            if (value) {
                const label = input.closest('.field')?.querySelector('label');
                if (label) {
                    const originalText = label.textContent.split('(')[0].trim();
                    label.textContent = `${originalText} (${formatHour12(value)})`;
                }
            }
        };
        
        input.addEventListener('change', updateLabel);
        input.addEventListener('input', updateLabel);
    }
    
    // ========== EXPORTAR FUNCIONES ==========
    
    window.AdminValidation = {
        validateName,
        validateDNI,
        validateEmail,
        validateLegajo,
        validatePassword,
        validateTime,
        validateTimeRange,
        validateWeekday,
        formatHour12,
        setupNameInput,
        setupDNIInput,
        setupEmailInput,
        setupLegajoInput,
        setupDaySelect,
        setupTimeInputWithLabel
    };
    
    console.log('✅ Validaciones de admin cargadas');
})();