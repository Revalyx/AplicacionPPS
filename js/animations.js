// js/animations.js
(() => {
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ========== Ripple en botones ========== */
  if (!prefersReduced) {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.btn');
      if (!btn) return;
      const rect = btn.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const ripple = document.createElement('span');
      ripple.className = 'ripple';
      ripple.style.width = ripple.style.height = size + 'px';
      ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
      ripple.style.top  = (e.clientY - rect.top  - size / 2) + 'px';
      btn.appendChild(ripple);
      setTimeout(() => ripple.remove(), 500);
    });
  }

  /* ========== Timeline: animar nuevas tarjetas ========== */
  const timeline = document.getElementById('timeline');
  if (timeline && !prefersReduced) {
    const obs = new MutationObserver((muts) => {
      muts.forEach((m) => m.addedNodes.forEach((n) => {
        if (n instanceof HTMLElement && n.classList.contains('t-card')) {
          n.classList.add('will-animate');
          requestAnimationFrame(() => {
            n.classList.remove('will-animate');
            n.classList.add('animate-in');
          });
        }
      }));
    });
    obs.observe(timeline, { childList: true });
  }

  /* ========== KPIs: animación count-up segura ========== */
  const easeOutCubic = (p) => 1 - Math.pow(1 - p, 3);

  const countUp = (el, from, to, ms = 600) => {
    if (prefersReduced) { el.textContent = String(to); return; }
    const start = performance.now();
    const step = (t) => {
      const p = Math.min(1, (t - start) / ms);
      const val = from + (to - from) * easeOutCubic(p);
      el.textContent = (Math.round(val * 10) / 10).toString();
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
    const stat = el.closest('.stat');
    stat?.classList.add('kpi-flash');
    setTimeout(() => stat?.classList.remove('kpi-flash'), 700);
  };

  const attachKpiObserver = (id) => {
    const el = document.getElementById(id);
    if (!el) return;
    let last = parseFloat(el.textContent.replace(',', '.')) || 0;

    const reattach = () => obs.observe(el, { childList: true, characterData: true, subtree: true });

    const obs = new MutationObserver(() => {
      const val = parseFloat(el.textContent.replace(',', '.'));
      if (!isFinite(val) || val === last) return;
      obs.disconnect();
      countUp(el, last, val);
      last = val;
      setTimeout(reattach, 700); // evita bucles
    });

    reattach();
  };

  ['kpiCount', 'kpiLast', 'kpiAvg', 'kpiIMC'].forEach(attachKpiObserver);

  /* ========== Chip IMC: pop cuando cambia la barra ========== */
  const meter = document.getElementById('imcMeter');
  const chip  = document.getElementById('kpiIMCClass');
  if (meter && chip && !prefersReduced) {
    const obs = new MutationObserver((muts) => {
      muts.forEach((m) => {
        if (m.type === 'attributes' && m.attributeName === 'style') {
          chip.classList.add('animate-pop');
          setTimeout(() => chip.classList.remove('animate-pop'), 300);
        }
      });
    });
    obs.observe(meter, { attributes: true, attributeFilter: ['style'] });
  }

  /* ========== Feedback de guardado ========== */
  window.notifySaved = function () {
    const btn = document.querySelector('#pesoForm .btn');
    if (!prefersReduced) {
      btn?.classList.add('animate-pop');
      setTimeout(() => btn?.classList.remove('animate-pop'), 250);
    }
    const firstStat = document.querySelector('.kpi-grid .stat .stat-value');
    firstStat?.classList.add('kpi-flash');
    setTimeout(() => firstStat?.classList.remove('kpi-flash'), 700);
  };

  /* ========== IMC: color dinámico (número + chip + marcador) ========== */
  // Este bloque SIEMPRE corre (aunque reduced-motion esté activo)
  (function initIMCColorSync() {
    const num     = document.getElementById('kpiIMC');       // .imc-number
    const chip    = document.getElementById('kpiIMCClass');  // .imc-chip
    const meter   = document.getElementById('imcMeter');     // barra (relleno oscuro)
    const pointer = document.getElementById('imcPointer');   // puntito indicador
    if (!num || !chip) return;

    const CLS = ['is-blue','is-green','is-amber','is-orange','is-red'];

    // Tramos solicitados: 0–25 azul, 25–30 verde, 30–35 amarillo, 35–40 naranja, ≥40 rojo
    function classForIMC(v){
      if (v < 25) return 'is-blue';
      if (v < 30) return 'is-green';
      if (v < 35) return 'is-amber';
      if (v < 40) return 'is-orange';
      return 'is-red';
    }

    function parseIMC(){
      const raw = (num.textContent || '').trim().replace(',', '.');
      const v = parseFloat(raw);
      return Number.isFinite(v) ? v : null;
    }

    function applyColor(v){
      num.classList.remove(...CLS);
      chip.classList.remove(...CLS);
      if (v == null) return;
      const c = classForIMC(v);
      num.classList.add(c);
      chip.classList.add(c);
    }

    function placePointer(v){
      if (!pointer || !Number.isFinite(v)) return;
      const pct = Math.max(0, Math.min(100, (v / 45) * 100)); // 0..45 → 0..100%
      pointer.style.left = pct + '%';
      pointer.classList.remove(...CLS);
      pointer.classList.add(classForIMC(v));
    }

    // Limpieza: eliminar textos sueltos dentro de la barra (si los hubiera)
    if (meter) {
      Array.from(meter.childNodes).forEach(n => {
        if (n.nodeType === Node.TEXT_NODE) meter.removeChild(n);
      });
    }

    // Inicial
    const v0 = parseIMC();
    applyColor(v0);
    placePointer(v0);

    // Reaccionar cuando cambie el número
    const obs = new MutationObserver(() => {
      const v = parseIMC();
      applyColor(v);
      placePointer(v);
    });
    obs.observe(num, { childList: true, characterData: true, subtree: true });

    // API opcional para forzar desde app.js
    window.setIMCVisuals = (value) => {
      const v = typeof value === 'number' ? value : parseFloat(String(value).replace(',', '.')) || 0;
      num.textContent = v.toFixed(1);
      applyColor(v);
      placePointer(v);
    };
  })();

})();

