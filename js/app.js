// --- Utilidades IMC ---
function computeBMI(kg, cm){
  const m = cm / 100;
  if (!kg || !cm || m <= 0) return null;
  return kg / (m*m);
}
function bmiCategory(bmi){
  if (bmi == null) return '—';
  if (bmi < 18.5) return 'Bajo peso';
  if (bmi < 25) return 'Normal';
  if (bmi < 30) return 'Sobrepeso';
  return 'Obesidad';
}
function bmiClass(bmi){
  if (bmi == null) return '';
  if (bmi < 18.5) return 'warn';
  if (bmi < 25) return 'ok';
  if (bmi < 30) return 'warn';
  return 'danger';
}
function toISODateLocal(d){
  // Asegura YYYY-MM-DD local
  const tz = new Date(d.getTime() - d.getTimezoneOffset()*60000);
  return tz.toISOString().slice(0,10);
}

// --- Persistencia ---
const KEY = 'pw_entries_v1';
function loadEntries(){
  try{
    const json = localStorage.getItem(KEY);
    const arr = json ? JSON.parse(json) : [];
    return Array.isArray(arr) ? arr : [];
  }catch(e){ return []; }
}
function saveEntries(entries){
  localStorage.setItem(KEY, JSON.stringify(entries));
}

// --- Estado ---
let entries = loadEntries();
let sortDesc = true; // por defecto: más recientes primero

// --- DOM refs ---
const form = document.getElementById('entryForm');
const dateEl = document.getElementById('date');
const weightEl = document.getElementById('weight');
const heightEl = document.getElementById('height');
const bmiEl = document.getElementById('bmi');
const bmiCatEl = document.getElementById('bmiCat');
const editIndexEl = document.getElementById('editIndex');
const submitBtn = document.getElementById('submitBtn');
const cancelEditBtn = document.getElementById('cancelEdit');

const tbody = document.getElementById('tbody');
const countLabel = document.getElementById('countLabel');
const sortBtn = document.getElementById('sortBtn');
const clearBtn = document.getElementById('btnClear');

const kpiLastWeight = document.getElementById('kpiLastWeight');
const kpiDelta = document.getElementById('kpiDelta');
const kpiLastBMI = document.getElementById('kpiLastBMI');

// --- Inicialización ---
dateEl.value = toISODateLocal(new Date());
recalcBMI();
render();

// --- Listeners ---
weightEl.addEventListener('input', recalcBMI);
heightEl.addEventListener('input', recalcBMI);
dateEl.addEventListener('change', recalcBMI);

form.addEventListener('submit', (e)=>{
  e.preventDefault();
  const date = dateEl.value;
  const weight = parseFloat(weightEl.value);
  const height = parseFloat(heightEl.value);
  if(!date || !(weight>0) || !(height>0)){
    alert('Por favor, complete fecha, peso (>0) y altura (>0).');
    return;
  }
  const idx = parseInt(editIndexEl.value,10);
  const rec = { date, weight: +weight.toFixed(1), height: +height.toFixed(1) };

  // Evitar duplicados exactos por fecha: si existe, actualizar
  const existingIndex = entries.findIndex(x=>x.date===date);
  const doReplaceSameDate = existingIndex>=0 && idx===-1;

  if (idx >= 0){
    entries[idx] = rec;
  } else if (doReplaceSameDate){
    if (confirm('Ya existe un registro en esa fecha. ¿Desea reemplazarlo?')){
      entries[existingIndex] = rec;
    } else return;
  } else {
    entries.push(rec);
  }
  saveEntries(entries);
  resetForm();
  render();
});

sortBtn.addEventListener('click', () => {
  sortDesc = !sortDesc;
  render();
});

clearBtn.addEventListener('click', ()=>{
  if(confirm('Esto eliminará todos los registros. ¿Continuar?')){
    entries = [];
    saveEntries(entries);
    render();
  }
});

cancelEditBtn.addEventListener('click', resetForm);

// --- Funciones UI ---
function recalcBMI(){
  const kg = parseFloat(weightEl.value);
  const cm = parseFloat(heightEl.value);
  const bmi = computeBMI(kg, cm);
  if (bmi){
    bmiEl.value = bmi.toFixed(2);
    bmiCatEl.value = bmiCategory(bmi);
  } else {
    bmiEl.value = '';
    bmiCatEl.value = '';
  }
}

function resetForm(){
  editIndexEl.value = -1;
  submitBtn.textContent = 'Guardar';
  cancelEditBtn.style.display = 'none';
  weightEl.value = '';
  heightEl.value = '';
  recalcBMI();
  weightEl.focus();
}

function render(){
  const sorted = [...entries].sort((a,b)=>{
    return sortDesc ? (b.date.localeCompare(a.date)) : (a.date.localeCompare(b.date));
  });
  tbody.innerHTML = '';
  for (let i=0;i<sorted.length;i++){
    const rec = sorted[i];
    const bmi = computeBMI(rec.weight, rec.height);
    const cat = bmiCategory(bmi);
    const cls = bmiClass(bmi);
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${rec.date}</td>
      <td>${rec.weight.toFixed(1)}</td>
      <td>${rec.height.toFixed(1)}</td>
      <td>${bmi ? bmi.toFixed(2) : '—'}</td>
      <td><span class="tag ${cls}">${cat}</span></td>
      <td>
        <button class="btn btn-ghost" data-act="edit" data-date="${rec.date}">Editar</button>
        <button class="btn btn-ghost" data-act="del" data-date="${rec.date}">Eliminar</button>
      </td>
    `;
    tbody.appendChild(tr);
  }
  countLabel.textContent = `${entries.length} registro${entries.length===1?'':'s'}`;
  bindRowActions();
  renderKPIs();
}

function bindRowActions(){
  tbody.querySelectorAll('button[data-act]').forEach(btn=>{
    btn.addEventListener('click', (e)=>{
      const act = e.currentTarget.getAttribute('data-act');
      const date = e.currentTarget.getAttribute('data-date');
      const idx = entries.findIndex(x=>x.date===date);
      if (idx<0) return;
      if (act==='edit'){
        const rec = entries[idx];
        editIndexEl.value = idx;
        dateEl.value = rec.date;
        weightEl.value = rec.weight;
        heightEl.value = rec.height;
        recalcBMI();
        submitBtn.textContent = 'Actualizar';
        cancelEditBtn.style.display = 'inline-block';
        window.scrollTo({top:0, behavior:'smooth'});
      } else if (act==='del'){
        if (confirm(`Eliminar el registro del ${date}?`)){
          entries.splice(idx,1);
          saveEntries(entries);
          render();
        }
      }
    });
  });
}

function renderKPIs(){
  if (entries.length===0){
    kpiLastWeight.textContent = '—';
    kpiDelta.textContent = '—';
    kpiLastBMI.textContent = '—';
    return;
  }
  const sorted = [...entries].sort((a,b)=>b.date.localeCompare(a.date));
  const last = sorted[0];
  const prev = sorted[1];
  kpiLastWeight.textContent = last.weight.toFixed(1);
  const delta = prev ? (last.weight - prev.weight) : 0;
  const sign = delta>0? '+' : '';
  kpiDelta.textContent = prev ? `${sign}${delta.toFixed(1)} kg` : '—';
  const bmi = computeBMI(last.weight, last.height);
  kpiLastBMI.textContent = bmi ? `${bmi.toFixed(2)} (${bmiCategory(bmi)})` : '—';
}
