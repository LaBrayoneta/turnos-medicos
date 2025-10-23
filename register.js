const msg = document.getElementById("msg") || (function(){ const s=document.createElement('div'); s.id='msg'; s.style.marginTop='10px'; s.style.color='#ef4444'; document.querySelector('.card')?.appendChild(s); return s; })();
const form = document.getElementById("registerForm");

form.addEventListener("submit", (e) => {
  e.preventDefault();

  const nombre   = document.getElementById("regNombre").value.trim();
  const apellido = document.getElementById("regApellido").value.trim();
  const email    = document.getElementById("regEmail").value.trim();
  const dni      = document.getElementById("regDni").value.trim();
  const obra     = document.getElementById("regObra").value.trim();
  const libreta  = document.getElementById("regLibreta").value.trim();
  const pass     = document.getElementById("regPassword").value.trim();
  const pass2    = document.getElementById("regPassword2").value.trim();

  if (!nombre || !apellido || !email || !dni || !obra || !pass || !pass2) {
    msg.style.color = "red"; msg.textContent = "⚠️ Completa todos los campos obligatorios"; return;
  }
  if (!/^[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+$/.test(nombre)) {
    msg.style.color = "red"; msg.textContent = "⚠️ El nombre solo puede contener letras"; return;
  }
  if (!/^[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+$/.test(apellido)) {
    msg.style.color = "red"; msg.textContent = "⚠️ El apellido solo puede contener letras"; return;
  }
  if (!/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email)) {
    msg.style.color = "red"; msg.textContent = "⚠️ Correo electrónico inválido"; return;
  }
  if (!/^[0-9]{7,10}$/.test(dni)) {
    msg.style.color = "red"; msg.textContent = "⚠️ DNI inválido (7-10 dígitos)"; return;
  }
  if (obra.length < 2) {
    msg.style.color = "red"; msg.textContent = "⚠️ Ingresá una Obra Social válida"; return;
  }
  if (pass.length < 6) {
    msg.style.color = "red"; msg.textContent = "⚠️ La contraseña debe tener al menos 6 caracteres"; return;
  }
  if (pass !== pass2) {
    msg.style.color = "red"; msg.textContent = "⚠️ Las contraseñas no coinciden"; return;
  }

  msg.style.color = "green";
  msg.textContent = "✅ Enviando...";
  form.submit();
});
