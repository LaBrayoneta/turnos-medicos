const msg = document.getElementById("msg");
const form = document.getElementById("loginForm");

form.addEventListener("submit", (e) => {
  e.preventDefault();
  const dni = document.getElementById("loginDni").value.trim();
  const password = document.getElementById("loginPassword").value.trim();

  if (!dni || !password) { msg.style.color = "red"; msg.textContent = "⚠️ Completa todos los campos"; return; }
  if (!/^[0-9]{7,10}$/.test(dni)) { msg.style.color = "red"; msg.textContent = "⚠️ DNI inválido (7-10 dígitos)"; return; }
  if (password.length < 6) { msg.style.color = "red"; msg.textContent = "⚠️ La contraseña debe tener al menos 6 caracteres"; return; }

  msg.style.color = "green";
  msg.textContent = "✅ Enviando...";
  form.submit(); // envía por POST a auth.php?action=login
});
