// js/login.js
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("loginForm");
  const mensaje = document.getElementById("mensaje");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    mensaje.textContent = "";

    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;

    try {
      const data = await Auth.login(email, password);
      if (data?.success) {
        window.location.href = "web.html"; // redirige a la app
      } else {
        mensaje.textContent = data?.message || "Credenciales incorrectas";
      }
    } catch (err) {
      if (err.message === "NO_AUTH") {
        mensaje.textContent = "No autenticado";
      } else {
        mensaje.textContent = "Error: " + err.message;
      }
    }
  });
});
