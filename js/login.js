document.addEventListener('DOMContentLoaded', () => {
  
  // --- Contenedores Principales ---
  const authCardContainer = document.getElementById('authCardContainer');
  const flipCardInner = document.querySelector('.flip-card-inner'); 
  const cardFront = document.getElementById('loginBox');
  const cardBack = document.getElementById('registerBox');

  // --- Login ---
  const loginForm = document.getElementById('loginForm');
  const emailInput = document.getElementById('email');
  const passwordInput = document.getElementById('password');
  const loginMessage = document.getElementById('mensaje');

  // --- Registro ---
  const registerForm = document.getElementById('registerForm');
  const regNameInput = document.getElementById('regName');
  const regEmailInput = document.getElementById('regEmail');
  const regPasswordInput = document.getElementById('regPassword');
  // --- AÑADIDO ---
  const regBirthdateInput = document.getElementById('regBirthdate');
  // --- /AÑADIDO ---
  const regMessage = document.getElementById('regMensaje');

  
  // ====================
  // 1. FUNCIÓN CLAVE: IGUALAR ALTURA (CORREGIDA V2)
  // ====================
  function setEqualCardHeight() {
    if (!authCardContainer || !flipCardInner || !cardFront || !cardBack) {
      console.error("Faltan elementos del DOM para el 'flip'");
      return;
    }

    // 1. Resetear alturas para medir contenido natural
    authCardContainer.style.height = 'auto'; 
    flipCardInner.style.height = 'auto';
    cardFront.style.position = 'relative'; // Temporalmente relativo para medir
    cardBack.style.position = 'relative'; // Temporalmente relativo para medir
    cardBack.style.visibility = 'hidden'; // Ocultar mientras se mide
    cardBack.style.transform = 'none'; // Quitar rotación temporalmente

    // 2. Medir ambas alturas
    const frontHeight = cardFront.offsetHeight;
    const backHeight = cardBack.offsetHeight;

    // 3. Encontrar la altura máxima
    const maxHeight = Math.max(frontHeight, backHeight);

    // 4. Aplicar la altura máxima al CONTENEDOR EXTERNO
    authCardContainer.style.height = `${maxHeight}px`;

    // 5. Restaurar los estilos absolutos para que el CSS del flip funcione
    flipCardInner.style.height = '100%'; // El inner ocupa el 100% del contenedor
    cardFront.style.position = 'absolute';
    cardBack.style.position = 'absolute';
    cardBack.style.visibility = 'visible'; // Volver a mostrar
    cardBack.style.transform = 'rotateY(180deg)'; // Restaurar rotación
  }

  // Ejecutar la función de altura (con un pequeño retraso por si el DOM tarda)
  requestAnimationFrame(() => {
    setTimeout(setEqualCardHeight, 50); 
  });
  window.addEventListener('resize', setEqualCardHeight);


  // ====================
  // 2. Lógica para VOLTEAR (FLIP)
  // ====================
  document.querySelectorAll('.toggle-link a').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault(); 
      authCardContainer.classList.toggle('is-flipped');
      
      if (loginMessage) loginMessage.textContent = '';
      if (regMessage) {
        regMessage.textContent = '';
        regMessage.style.color = ''; 
      }
    });
  });
  
  // ====================
  // 3. HELPER: Función setLoading
  // ====================
  function setLoading(form, isLoading) {
    const btn = form ? form.querySelector('button[type="submit"]') : null;
    if (btn) {
      btn.disabled = isLoading;
      btn.textContent = isLoading ? (form.id === 'loginForm' ? 'Ingresando...' : 'Creando cuenta…') : (form.id === 'loginForm' ? 'Ingresar' : 'Registrarse');
    }
  }

  // ====================
  // 4. Lógica de Login
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
  // 5. Lógica de Registro (Llamando a register.php)
  // ====================
  if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (regMessage) {
        regMessage.textContent = ''; 
        regMessage.style.color = ''; 
      }

      // --- MODIFICADO ---
      const nameValue  = (regNameInput?.value || '').trim();
      const emailValue = (regEmailInput?.value || '').trim();
      const passValue  = (regPasswordInput?.value || '').trim();
      const birthdateValue = (regBirthdateInput?.value || '').trim(); // <-- AÑADIDO
      // --- /MODIFICADO ---

      
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
        if (regMessage) regMessage.textContent = 'Introduzca un email válido.';
        return;
      }
      if (!passValue || passValue.length < 6) {
        if (regMessage) regMessage.textContent = 'La contraseña debe tener al menos 6 caracteres.';
        return;
      }

      setLoading(registerForm, true);

      try {
        const res = await fetch('./apipps/register.php', { 
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          // --- MODIFICADO ---
          body: JSON.stringify({
            name: nameValue,
            email: emailValue,
            password: passValue,
            fecha_nacimiento: birthdateValue // <-- AÑADIDO
          })
          // --- /MODIFICADO ---
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
            authCardContainer.classList.remove('is-flipped'); // Voltear de vuelta
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
