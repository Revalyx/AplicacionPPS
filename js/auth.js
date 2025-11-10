// js/auth.js
(function () {
  const API_BASE = './apipps/';

  async function parseResponse(res) {
    const txt = await res.text();
    let json = null;
    try { json = JSON.parse(txt); } catch {}
    return { ok: res.ok, status: res.status, json, txt };
  }

  function saveSession(token, userObj) {
    if (token) localStorage.setItem('token', token);
    if (userObj && typeof userObj === 'object') {
      localStorage.setItem('user', JSON.stringify(userObj));
      if (userObj.id != null) localStorage.setItem('user_id', String(userObj.id));
    }
  }
  function getUser() {
    try { return JSON.parse(localStorage.getItem('user') || '{}'); }
    catch { return {}; }
  }
  function getUserId() {
    const s = localStorage.getItem('user_id');
    if (s != null) return Number(s);
    const u = getUser();
    return (u && u.id != null) ? Number(u.id) : null;
  }

  const Auth = {
    async login(email, password) {
      const res = await fetch(API_BASE + 'login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });
      const { ok, status, json } = await parseResponse(res);
      if (!ok) throw new Error((json && (json.error || json.message)) || `HTTP ${status}`);

      const token = json && (json.token || json.jwt || json.access_token);
      const user  = json && json.user;
      if (!user || user.id == null) throw new Error('Login sin user.id');

      saveSession(token, user);
      return true;
    },

    getUser,
    getUserId,
    getToken() { return localStorage.getItem('token'); },

    isAuth() { return this.getUserId() != null || this.getToken() != null; },

    logout() {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      localStorage.removeItem('user_id');
      location.href = 'index.html';
    },

    requireAuth() {
      if (!this.isAuth()) location.href = 'index.html';
    },

    // fetch que SIEMPRE añade user_id en medidas.php
    async apiFetch(path, opts = {}) {
      const headers = opts.headers ? { ...opts.headers } : {};
      const token = this.getToken();
      if (token) headers['Authorization'] = 'Bearer ' + token;

      let url = API_BASE + path;
      const method = (opts.method || 'GET').toUpperCase();
      const isMedidas = /(^|\/)medidas\.php(\?|$)/.test(path);

      const uid = this.getUserId();

      // GET → añade ?user_id=...
      if (isMedidas && method === 'GET') {
        const sep = url.includes('?') ? '&' : '?';
        url = url + sep + 'user_id=' + encodeURIComponent(uid ?? '');
      }

      // POST → inyecta user_id en cuerpo JSON
      let body = opts.body;
      if (isMedidas && method === 'POST') {
        const ct = (headers['Content-Type'] || headers['content-type'] || '').toLowerCase();
        let data = {};
        if (typeof body === 'string' && ct.includes('application/json')) {
          try { data = JSON.parse(body || '{}'); } catch { data = {}; }
        } else if (body && typeof body === 'object') {
          data = body;
        }
        if (uid != null) data.user_id = uid;
        body = JSON.stringify(data);
        headers['Content-Type'] = 'application/json';
      }

      const res = await fetch(url, { ...opts, headers, body });
      const parsed = await parseResponse(res);
      return parsed;
    }
  };

  window.Auth = Auth;
})();

