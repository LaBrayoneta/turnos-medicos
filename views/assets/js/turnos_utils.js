// turnos_utils.js - Utilidades compartidas para validación de fechas y turnos

const TurnosUtils = {
  /**
   * Obtiene la fecha actual sin hora
   */
  getToday() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return today;
  },

  /**
   * Obtiene la fecha mínima permitida (hoy)
   */
  getMinDate() {
    return this.getToday();
  },

  /**
   * Obtiene la fecha máxima permitida (3 meses adelante)
   */
  getMaxDate() {
    const max = new Date();
    max.setMonth(max.getMonth() + 3);
    max.setHours(23, 59, 59, 999);
    return max;
  },

  /**
   * Convierte Date a formato YYYY-MM-DD
   */
  toYMD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  },

  /**
   * Valida si una fecha es válida para turnos
   */
  isValidTurnoDate(dateStr) {
    if (!dateStr || !/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
      return { valid: false, error: 'Formato de fecha inválido' };
    }

    const date = new Date(dateStr + 'T00:00:00');
    const today = this.getMinDate();
    const maxDate = this.getMaxDate();

    if (isNaN(date.getTime())) {
      return { valid: false, error: 'Fecha inválida' };
    }

    if (date < today) {
      return { valid: false, error: 'No se pueden reservar turnos en fechas pasadas' };
    }

    if (date > maxDate) {
      return { valid: false, error: 'Solo se pueden reservar turnos hasta 3 meses adelante' };
    }

    return { valid: true };
  },

  /**
   * Configura un input de fecha con restricciones
   */
  setupDateInput(inputElement) {
    if (!inputElement) return;

    const today = this.toYMD(this.getMinDate());
    const max = this.toYMD(this.getMaxDate());

    inputElement.setAttribute('min', today);
    inputElement.setAttribute('max', max);
    inputElement.value = ''; // Limpiar valor inicial

    // Validación en tiempo real
    inputElement.addEventListener('input', (e) => {
      const value = e.target.value;
      if (!value) return;

      const validation = this.isValidTurnoDate(value);
      if (!validation.valid) {
        e.target.setCustomValidity(validation.error);
        e.target.reportValidity();
        e.target.value = '';
      } else {
        e.target.setCustomValidity('');
      }
    });

    // Validación al cambiar
    inputElement.addEventListener('change', (e) => {
      const value = e.target.value;
      if (!value) return;

      const validation = this.isValidTurnoDate(value);
      if (!validation.valid) {
        alert('⚠️ ' + validation.error);
        e.target.value = '';
      }
    });
  },

  /**
   * Formatea una fecha para mostrar
   */
  formatDateDisplay(dateStr) {
    const date = new Date(dateStr);
    const options = { 
      weekday: 'long', 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    };
    return date.toLocaleDateString('es-AR', options);
  },

  /**
   * Formatea hora de 24h a 12h con AM/PM
   */
  formatHour12(time24) {
    if (!time24) return '';
    const [hours, minutes] = time24.split(':');
    let h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${h}:${minutes} ${ampm}`;
  },

  /**
   * Valida si una hora está dentro del rango laboral (6 AM - 10 PM)
   */
  isValidBusinessHour(time24) {
    const [hours] = time24.split(':');
    const h = parseInt(hours);
    return h >= 6 && h < 22;
  },

  /**
   * Obtiene el nombre del día en español
   */
  getDayName(dateStr) {
    const dias = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
    const date = new Date(dateStr + 'T00:00:00');
    return dias[date.getDay()];
  },

  /**
   * Verifica si una fecha es fin de semana
   */
  isWeekend(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    const day = date.getDay();
    return day === 0 || day === 6; // Domingo o Sábado
  },

  /**
   * Genera un resumen legible del turno
   */
  generateTurnoSummary(fecha, hora, medico, especialidad) {
    const fechaDisplay = this.formatDateDisplay(fecha);
    const horaDisplay = this.formatHour12(hora);
    return `
      📅 ${fechaDisplay}
      🕐 ${horaDisplay}
      👨‍⚕️ ${medico}
      🏥 ${especialidad}
    `.trim();
  },

  /**
   * Calcula la diferencia en días entre dos fechas
   */
  daysDifference(date1Str, date2Str) {
    const date1 = new Date(date1Str + 'T00:00:00');
    const date2 = new Date(date2Str + 'T00:00:00');
    const diffTime = Math.abs(date2 - date1);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
  },

  /**
   * Verifica si un turno se puede cancelar (al menos 24hs antes)
   */
  canCancelTurno(fechaTurno, horaTurno) {
    const now = new Date();
    const turnoDateTime = new Date(`${fechaTurno}T${horaTurno}:00`);
    const hoursUntil = (turnoDateTime - now) / (1000 * 60 * 60);
    return hoursUntil >= 24;
  },

  /**
   * Sanitiza entrada de texto
   */
  sanitize(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
};

// Exportar para uso global
if (typeof window !== 'undefined') {
  window.TurnosUtils = TurnosUtils;
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = TurnosUtils;
}