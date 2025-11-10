// js/login.js
document.addEventListener('DOMContentLoaded', () => {
  
  // --- Contenedores de formularios ---
  const loginBox = document.getElementById('loginBox');
  const registerBox = document.getElementById('registerBox');

  // --- Login ---
  const loginForm = document.getElementById('loginForm');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const loginMessage = document.getElementById('mensaje');

  // --- Registro ---
  const registerForm = document.getElementById('registerForm');
  const regNameInput = document.getElementById('regName');
  const regEmailInput = document.getElementById('regEmail');
  const regPasswordInput = document.getElementById('regPassword'); // ID del campo de contraseña de registro
  const regMessage = document.getElementById('regMensaje');

  
  // ====================
  // 1. Lógica para alternar entre Login y Registro
  // ====================
  document.querySelectorAll('.toggle-link a').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault(); 
      const target = e.target.dataset.target; 

      if (target === 'register') {
        loginBox.classList.add('hidden');
        registerBox.classList.remove('hidden');
      } else { 
        registerBox.classList.add('hidden');
        loginBox.classList.remove('hidden');
      }
      
      if (loginMessage) loginMessage.textContent = '';
      if (regMessage) {
        regMessage.textContent = '';
        regMessage.style.color = ''; 
      }
      if (emailInput) emailInput.value = '';
      if (passwordInput) passwordInput.value = '';
      if (regNameInput) regNameInput.value = '';
      if (regEmailInput) regEmailInput.value = '';
      if (regPasswordInput) regPasswordInput.value = ''; // Limpiar el campo correcto
    });
  });
  
  // ====================
  // 2. HELPER: Función setLoading
  // ====================
  function setLoading(form, isLoading) {
    const btn = form ? form.querySelector('button[type="submit"]') : null;
    if (btn) {
      btn.disabled = isLoading;
      btn.textContent = isLoading ? (form.id === 'loginForm' ? 'Ingresando...' : 'Creando cuenta…') : (form.id === 'loginForm' ? 'Ingresar' : 'Registrarse');
    }
  }

  // ====================
  // 3. Lógica de Login
  // ====================
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (loginMessage) loginMessage.textContent = ''; 
      setLoading(loginForm, true);

      const emailValue = (emailInput?.value || '').trim();
      const passValue  = (passwordInput?.value || '').trim();

      if (!emailValue || !passValue) {
        if (loginMessage) loginMessage.textContent = 'Complete todos los campos.';
        setLoading(loginForm, false);
        return;
      }

      try {
        await window.Auth.login(emailValue, passValue);
        window.location.href = 'web.html'; 
      } catch (err) {
        if (loginMessage) loginMessage.textContent = err?.message || 'Error al iniciar sesión.';
        setLoading(loginForm, false);
      }
    });
  }

  // ====================
  // 4. Lógica de Registro (Con la corrección de 'regPasswordInput')
  // ====================
  if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (regMessage) {
        regMessage.textContent = ''; 
        regMessage.style.color = ''; 
      }

      const nameValue  = (regNameInput?.value || '').trim();
      const emailValue = (regEmailInput?.value || '').trim();
      // --- ¡ESTA ES LA LÍNEA CORRECTA! ---
      const passValue  = (regPasswordInput?.value || '').trim(); // Usar regPasswordInput

      
      // --- VALIDACIÓN MEJORADA ---
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
        if (regMessage) regMessage.textContent = 'Introduzca un email válido.';
        return;
      }
      if (!passValue || passValue.length < 6) {
        if (regMessage) regMessage.textContent = 'La contraseña debe tener al menos 6 caracteres.';
        return;
      }
      // --- FIN VALIDACIÓN ---

      setLoading(registerForm, true);

      try {
        const res = await fetch('./apipps/users.php', { 
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            name: nameValue,
            email: emailValue,
            password: passValue
          })
        });

        let json = null;
        try { json = await res.json(); } catch (e) {}
        
        if (!res.ok) {
          const errorMsg = (json && json.error) ? json.error : `Error HTTP ${res.status}`;
          if (res.status === 409) {
            throw new Error('El email ya está registrado.');
          }
          throw new Error(errorMsg || 'Error del servidor.');
        }

        if (regMessage) {
          regMessage.textContent = '¡Registro exitoso! Iniciando sesión...';
          regMessage.style.color = "#16a34a";
        }
        
        try {
          await window.Auth.login(emailValue, passValue);
          window.location.href = 'web.html';
        } catch (loginErr) {
          if (regMessage) regMessage.textContent = 'Cuenta creada. Por favor, inicie sesión.';
          if (regMessage) regMessage.style.color = '';
          setLoading(registerForm, false);
          setTimeout(() => {
            registerBox.classList.add('hidden');
            loginBox.classList.remove('hidden');
            if (emailInput) emailInput.value = emailValue;
          }, 2000);
        }
        
      } catch (err) {
        if (regMessage) regMessage.textContent = err.message || 'Error al registrar la cuenta.';
        if (regMessage) regMessage.style.color = "#dc2626";
        setLoading(registerForm, false);
      }
    });
  }
});
