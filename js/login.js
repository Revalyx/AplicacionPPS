// js/login.js
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("loginForm");
  const mensaje = document.getElementById("mensaje");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;

    try {
      const data = await Auth.login(email, password);
      if (data.success) {
        window.location.href = "app.html";
      } else {
        mensaje.textContent = data.message || "Credenciales incorrectas";
      }
    } catch (err) {
      mensaje.textContent = "Error: " + err.message;
    }
  });
});
