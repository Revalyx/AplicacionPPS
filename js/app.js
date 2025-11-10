document.addEventListener('DOMContentLoaded', () => {
  if (!window.Auth) return;
  window.Auth.requireAuth();

  const user = window.Auth.getUser();
  const userLabel = document.getElementById('userLabel');
  if (userLabel) userLabel.textContent = user.name || user.email || 'Usuario';

  const logoutBtn = document.getElementById('logout');
  if (logoutBtn) logoutBtn.addEventListener('click', () => window.Auth.logout());

  const fechaEl   = document.getElementById('fecha');
  const pesoEl    = document.getElementById('peso');
  // --- AÑADIDO ---
  const alturaEl  = document.getElementById('altura');
  const kpiAge    = document.getElementById('kpiAge');
  // --- /AÑADIDO ---
  const formError = document.getElementById('formError');
  const kpiCount  = document.getElementById('kpiCount');
  const kpiLast   = document.getElementById('kpiLast');
  const kpiAvg    = document.getElementById('kpiAvg');
  const timeline  = document.getElementById('timeline');
  const pesoForm  = document.getElementById('pesoForm');
  const kpiIMC      = document.getElementById('kpiIMC');
  const kpiIMCClass = document.getElementById('kpiIMCClass');
  const imcMeter    = document.getElementById('imcMeter');


  // Gráfico
  const btnPeso = document.getElementById('btnPeso');
  const btnIMC  = document.getElementById('btnIMC');
  const chartCanvas = document.getElementById('evoChart');

  let chartInstance = null;
  let currentMetric = 'peso'; // 'peso' | 'imc'
  let records = []; // {fecha, peso, altura?, imc?}

  // ================================
  // CÁLCULO DE EDAD (AÑADIDO)
  // ================================
  if (kpiAge && user.fecha_nacimiento) {
    const age = calculateAge(user.fecha_nacimiento);
    kpiAge.textContent = age;
  } else if (kpiAge) {
    kpiAge.textContent = '–';
  }

  // ================================
  // Helpers
  // ================================
  function calcIMC(peso, altura) {
    if (!altura || altura <= 0) return null;
    const imc = peso / (altura * altura);
    return isFinite(imc) ? imc : null;
  }

  function imcClass(imc) {
    if (imc == null || isNaN(imc)) return '–';
    if (imc < 18.5) return 'Bajo peso';
    if (imc < 25)   return 'Normopeso';
    if (imc < 30)   return 'Sobrepeso';
    if (imc < 35)   return 'Obesidad I';
    if (imc < 40)   return 'Obesidad II';
    return 'Obesidad III';
  }
  
  // --- FUNCIÓN AÑADIDA ---
  function calculateAge(birthdateString) {
    if (!birthdateString) return '–';
    try {
      const birthDate = new Date(birthdateString);
      if (isNaN(birthDate.getTime())) return '–';
      const today = new Date();
      let age = today.getFullYear() - birthDate.getFullYear();
      const m = today.getMonth() - birthDate.getMonth();
      if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
        age--;
      }
      return age;
    } catch (e) {
      return '–';
    }
  }
  // --- /FUNCIÓN AÑADIDA ---


  function normalizeRecords(rawList) {
    const list = (rawList || []).map(r => {
      const fecha  = r.fecha ?? r.date ?? '';
      const peso   = r.peso ?? r.weight ?? null;
      const altura = r.altura ?? null;
      let imc = r.imc ?? null;
      const pesoN = peso != null ? parseFloat(peso) : null;
      const altN  = altura != null ? parseFloat(altura) : null;
      if ((imc == null || isNaN(imc)) && pesoN != null && altN != null) {
        imc = calcIMC(pesoN, altN);
      }
      return {
        fecha,
        peso:   pesoN,
        altura: altN,
        imc:    imc != null ? parseFloat(imc) : null
      };
    });

    // orden ascendente por fecha para series temporales
    list.sort((a,b) => (a.fecha > b.fecha ? 1 : a.fecha < b.fecha ? -1 : 0));
    return list;
  }

  function updateKPIs(list) {
    if (kpiCount) kpiCount.textContent = String(list.length);

    if (kpiLast) {
      const last = list.length ? list[list.length - 1] : null;
      kpiLast.textContent = (last && last.peso != null) ? `${last.peso.toFixed(1)} kg` : '–';
    }

    if (kpiAvg) {
      if (!list.length) {
        kpiAvg.textContent = '–';
      } else {
        const now = new Date();
        const cut = new Date(now.getTime() - 30*24*60*60*1000);
        const last30 = list.filter(x => {
          const d = new Date(x.fecha);
          return !isNaN(d) && d >= cut;
        });
        const source = last30.length ? last30 : list;
        const vals = source.map(x => x.peso).filter(v => v != null);
        if (!vals.length) kpiAvg.textContent = '–';
        else {
          const avg = vals.reduce((s,v)=>s+v,0)/vals.length;
          kpiAvg.textContent = `${avg.toFixed(1)} kg`;
        }
      }
    }

    // ⬇️ NUEVOS KPI: IMC actual + clasificación
    if (kpiIMC || kpiIMCClass) {
      const last = list.length ? list[list.length - 1] : null;
      let imcVal = null;
      if (last) {
        imcVal = (last.imc != null) ? last.imc : calcIMC(last.peso, last.altura);
      }
      if (kpiIMC)      kpiIMC.textContent = (imcVal != null) ? imcVal.toFixed(2) : '–';
      if (kpiIMCClass) kpiIMCClass.textContent = imcClass(imcVal);
    }
  }

  function renderTimeline(list) {
    if (!timeline) return;
    timeline.innerHTML = '';
    const last = list.slice(-10).reverse();
    if (!last.length) {
      const div = document.createElement('div');
      div.className = 't-item';
      div.textContent = 'Sin entradas recientes';
      timeline.appendChild(div);
      return;
    }
    last.forEach(m => {
      const item = document.createElement('div');
      item.className = 't-item';
      item.innerHTML = `
        <div class="t-card">
          <div class="t-date">${m.fecha}</div>
          <div class="t-weight">${m.peso != null ? m.peso.toFixed(1)+' kg' : '—'}</div>
        </div>`;
      timeline.appendChild(item);
    });
  }

  function renderChart(metric) {
    if (!chartCanvas) return;

    const labels = records.map(r => r.fecha);
    const data   = records.map(r => metric === 'imc' ? r.imc : r.peso);

    if (chartInstance) {
      chartInstance.destroy();
      chartInstance = null;
    }

    chartInstance = new Chart(chartCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: metric === 'imc' ? 'IMC' : 'Peso (kg)',
          data,
          tension: 0.35,
          fill: false,
          borderWidth: 3,
          // colores suaves (puede ajustarlos a su paleta)
          borderColor: metric === 'imc' ? '#f39c12' : '#007b83',
          pointBackgroundColor: metric === 'imc' ? '#f39c12' : '#007b83',
          pointRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: {
          x: { ticks: { autoSkip: true, maxTicksLimit: 8 } },
          y: { beginAtZero: false }
        }
      }
    });

    if (btnPeso) btnPeso.classList.toggle('primary', metric === 'peso');
    if (btnIMC)  btnIMC.classList.toggle('primary',  metric === 'imc');
  }

  // ================================
  // Cargar registros desde la API
  // ================================
  async function loadMeasures() {
    const r = await window.Auth.apiFetch('medidas.php', { method: 'GET' });
    if (!r.ok) {
      console.error('GET medidas.php fallo →', r.status, r.txt);
      if (formError) formError.textContent = (r.json && r.json.error) || 'Error al cargar datos';
      records = [];
      renderTimeline(records);
      renderChart(currentMetric);
      updateKPIs(records);
      return;
    }

    const listRaw = (r.json && (r.json.records || r.json.measures)) ? (r.json.records || r.json.measures) : [];
    records = normalizeRecords(listRaw);

    updateKPIs(records);
    renderTimeline(records);
    renderChart(currentMetric);
  }

  // ================================
  // Guardar nuevo registro
  // ================================
  if (pesoForm) {
    pesoForm.addEventListener('submit', async e => {
      e.preventDefault();
      if (formError) formError.textContent = '';

      // --- MODIFICADO ---
      const fecha = fechaEl.value;
      const peso  = parseFloat(pesoEl.value);
      const alturaCm = parseFloat(alturaEl.value); // <-- AÑADIDO
      // --- /MODIFICADO ---

      if (!fecha || !Number.isFinite(peso) || peso <= 0) {
        if (formError) formError.textContent = 'Fecha y peso válidos son requeridos.';
        return;
      }

      // --- MODIFICADO ---
      let alturaMetros = null;
      if (Number.isFinite(alturaCm) && alturaCm > 0) {
        alturaMetros = alturaCm / 100; // Convertir cm a metros
      }
      
      const payload = { 
        fecha, 
        peso,
        altura: alturaMetros // <-- AÑADIDO (será null si está vacío)
      }; 
      // --- /MODIFICADO ---

      const r = await window.Auth.apiFetch('medidas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!r.ok) {
        console.error('POST medidas.php fallo →', r.status, r.txt);
        if (formError) formError.textContent = (r.json && r.json.error) || 'Error al guardar';
        return;
      }

      // limpiar inputs
      fechaEl.value = '';
      pesoEl.value  = '';
      // --- AÑADIDO ---
      if (alturaEl) alturaEl.value = '';
      // --- /AÑADIDO ---

      await loadMeasures();
    });
  }

  // Botones de métrica
  if (btnPeso) btnPeso.addEventListener('click', () => {
    currentMetric = 'peso';
    renderChart(currentMetric);
  });
  if (btnIMC) btnIMC.addEventListener('click', () => {
    currentMetric = 'imc';
    renderChart(currentMetric);
  });
  
  function animateCountUp(el, to, duration = 600) {
  if (!el) return;
  const isNumber = typeof to === 'number';
  const start = performance.now();
  const from = parseFloat(el.dataset.from || 0) || 0;

  function frame(t) {
    const p = Math.min(1, (t - start) / duration);
    const val = from + (to - from) * (1 - Math.pow(1 - p, 3)); // easeOutCubic
    el.textContent = isNumber ? (Math.round(val * 10) / 10).toString() : to;
    if (p < 1) requestAnimationFrame(frame);
    else el.dataset.from = isNumber ? to : el.textContent;
  }
  requestAnimationFrame(frame);
  // Flash agradable
  el.closest('.stat')?.classList.add('kpi-flash');
  setTimeout(() => el.closest('.stat')?.classList.remove('kpi-flash'), 750);
}

function animateIMC(imcValue) {
  const meter = document.getElementById('imcMeter');
  const chip  = document.getElementById('kpiIMCClass');
  if (!meter || !chip) return;

  // Mapeo rápido IMC→% aproximado (0..45 mostrado en la escala)
  const min = 0, max = 45;
  const pct = Math.max(0, Math.min(100, ((imcValue - min) / (max - min)) * 100));
  meter.style.width = pct + '%';

  chip.classList.add('animate-pop');
  setTimeout(()=> chip.classList.remove('animate-pop'), 300);
}


  

  // Inicial
  loadMeasures();
});
