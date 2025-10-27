// js/auth.js
// Utilidades de autenticación y wrapper de fetch (sesiones PHP vía cookie)
const Auth = (() => {
  const API_BASE = "apipps/";
  const STORAGE_KEY = "auth:user";

  const saveUser = (u) => localStorage.setItem(STORAGE_KEY, JSON.stringify(u));
  const clearUser = () => localStorage.removeItem(STORAGE_KEY);
  const getUser = () => {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY)); }
    catch { return null; }
  };
  const redirect = (url) => { window.location.href = url; };

  async function apiFetch(path, options = {}) {
    const opts = {
      method: "GET",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",      // importante para que viaje la cookie de sesión
      ...options,
    };

    const res = await fetch(API_BASE + path, opts);
    const text = await res.text();
    let data;
    try { data = text ? JSON.parse(text) : {}; } catch { data = { error: text || "Respuesta no válida" }; }

    if (res.status === 401 || res.status === 403) throw new Error("NO_AUTH");
    if (!res.ok || data?.error || data?.success === false) {
      const msg = data?.message || data?.error || `HTTP ${res.status}`;
      const err = new Error(msg);
      err.data = data;
      throw err;
    }
    return data;
  }

  async function login(email, password) {
    const data = await apiFetch("login.php", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    });
    if (data?.user) saveUser(data.user);
    return data;
  }

  async function register(email, password, height = null) {
    return apiFetch("users.php", {
      method: "POST",
      body: JSON.stringify({ email, password, height }),
    });
  }

  function logout() {
    clearUser();
    redirect("index.html");
  }

  function requireAuth() {
    const user = getUser();
    if (!user) redirect("index.html");
    return user;
  }

  async function authedFetch(path, options = {}) {
    const user = getUser();
    if (!user) throw new Error("NO_AUTH");
    return apiFetch(path, options);
  }

  return { login, register, logout, requireAuth, authedFetch, getUser, saveUser, clearUser };
})();
