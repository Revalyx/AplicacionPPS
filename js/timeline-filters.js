// js/timeline-filters.js
(() => {
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  const state = { filter: 'all' }; // 'all' | 'down' | 'up'
  const timeline = $('#timeline');
  if (!timeline) return;

  /* -------- Utils -------- */
  const parseWeight = (card) => {
    // 1) intenta con .t-weight
    const el = $('.t-weight', card);
    const txt = (el ? el.textContent : card.textContent) || '';
    const m = txt.replace(',', '.').match(/(-?\d+(\.\d+)?)\s*kg/i);
    return m ? parseFloat(m[1]) : null;
  };

  const getChipDelta = (card) => {
    const chip = $('.t-diff', card);
    if (!chip) return null;
    if (chip.classList.contains('down')) return 'down';
    if (chip.classList.contains('up'))   return 'up';
    if (chip.classList.contains('same')) return 'same';
    return null;
  };

  const computeDeltas = () => {
    const cards = $$('.t-card', timeline);
    // Recorremos de arriba (más reciente) a abajo (más antiguo)
    for (let i = 0; i < cards.length; i++) {
      const c = cards[i];
      // 1) Si ya hay chip con clase, úsala
      let delta = getChipDelta(c);

      // 2) Si no hay chip, calculamos por pesos
      if (!delta) {
        const w = parseWeight(c);
        const next = cards[i + 1]; // tarjeta más antigua
        const wNext = next ? parseWeight(next) : null;

        if (w != null && wNext != null) {
          if (w < wNext) delta = 'down';      // peso BAJA respecto a la anterior
          else if (w > wNext) delta = 'up';   // peso SUBE
          else delta = 'same';
        } else {
          delta = 'same';
        }
      }

      c.dataset.delta = delta; // 'down' | 'up' | 'same'
    }
  };

  const applyFilter = (filter) => {
    state.filter = filter;
    const cards = $$('.t-card', timeline);

    cards.forEach(c => {
      const d = c.dataset.delta || 'same';
      const show =
        filter === 'all' ? true :
        filter === 'down' ? d === 'down' :
        filter === 'up'   ? d === 'up' : true;

      c.style.display = show ? '' : 'none';
    });

    // Estado vacío (opcional): si no hay nada visible, mostramos aviso
    let anyVisible = cards.some(c => c.style.display !== 'none');
    let empty = $('.timeline-empty', timeline);
    if (!anyVisible) {
      if (!empty) {
        empty = document.createElement('div');
        empty.className = 'timeline-empty';
        empty.innerHTML = '<span class="emoji">📝</span>No hay entradas para este filtro.';
        timeline.appendChild(empty);
      }
    } else {
      empty?.remove();
    }

    // Botones activos
    $('#tlAll')?.classList.toggle('active', filter === 'all');
    $('#tlDown')?.classList.toggle('active', filter === 'down');
    $('#tlUp')?.classList.toggle('active', filter === 'up');
  };

  const refresh = () => {
    computeDeltas();
    applyFilter(state.filter);
  };

  /* -------- Eventos de los botones -------- */
  $('#tlAll')?.addEventListener('click', () => applyFilter('all'));
  $('#tlDown')?.addEventListener('click', () => applyFilter('down'));
  $('#tlUp')?.addEventListener('click', () => applyFilter('up'));

  /* -------- Observer: si su app añade tarjetas dinámicamente -------- */
  const debounced = (fn, ms = 50) => {
    let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  };
  const onMut = debounced(refresh, 30);

  const mo = new MutationObserver(onMut);
  mo.observe(timeline, { childList: true, subtree: false });

  // Exponer un pequeño hook opcional por si quiere forzar recálculo desde app.js
  window.timelineFilters = { refresh };

  // Primer render
  refresh();
})();
