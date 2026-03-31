/* ============================================================
   SMS Platform — app.js  (Light Theme)
   ============================================================ */

const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
// updateCsrf() e definit inline in layout.php <head> — disponibil inainte de orice script

const Toast = {
  show(msg, type = 'info', duration = 4000) {
    const icons = {
      success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
      error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
      info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `${icons[type] || icons.info}<span>${msg}</span>`;
    container.appendChild(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 220); }, duration);
  },
  success: (m, d) => Toast.show(m, 'success', d),
  error:   (m, d) => Toast.show(m, 'error', d),
  info:    (m, d) => Toast.show(m, 'info', d),
};

async function api(url, options = {}) {
  const defaults = {
    method: 'GET',
    headers: { 'X-CSRF-Token': CSRF(), 'X-Requested-With': 'XMLHttpRequest' },
  };
  if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
    options.body = JSON.stringify(options.body);
    defaults.headers['Content-Type'] = 'application/json';
  }
  const res = await fetch(url, { ...defaults, ...options, headers: { ...defaults.headers, ...(options.headers || {}) } });
  const text = await res.text();
  let data;
  try { data = text ? JSON.parse(text) : {}; } catch { data = { error: 'Raspuns invalid de la server' }; }
  updateCsrf(data);
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}

const Modal = {
  open(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
  },
  close(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
  },
  closeAll() {
    document.querySelectorAll('.modal-overlay.open').forEach(el => { el.classList.remove('open'); });
    document.body.style.overflow = '';
  }
};

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) Modal.closeAll();
  if (e.target.closest('[data-modal-close]')) Modal.closeAll();
  if (e.target.closest('[data-modal-open]')) Modal.open(e.target.closest('[data-modal-open]').dataset.modalOpen);
});

// ── SMS Character counter — GSM7 corect cu diacritice ────────
const GSM7_CHARSET = new Set([
  '@','£','$','¥','è','é','ù','ì','ò','Ç','\n','Ø','ø','\r','Å','å',
  'Δ','_','Φ','Γ','Λ','Ω','Π','Ψ','Σ','Θ','Ξ','Æ','æ','ß','É',
  ' ','!','"','#','¤','%','&',"'",'(',')','*','+',',','-','.','/',
  '0','1','2','3','4','5','6','7','8','9',':',';','<','=','>','?',
  '¡','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O',
  'P','Q','R','S','T','U','V','W','X','Y','Z','Ä','Ö','Ñ','Ü','§',
  '¿','a','b','c','d','e','f','g','h','i','j','k','l','m','n','o',
  'p','q','r','s','t','u','v','w','x','y','z','ä','ö','ñ','ü','à'
]);
const GSM7_EXTENDED = new Set(['\f','[','\\',']','^','{','|','}','~','€']);

function calcSmsUnits(text) {
  let isUnicode = false;
  let units = 0;
  for (const ch of text) {
    if (GSM7_EXTENDED.has(ch)) { units += 2; }
    else if (GSM7_CHARSET.has(ch)) { units += 1; }
    else { isUnicode = true; break; }
  }
  if (isUnicode) { return { units: [...text].length, isUnicode: true }; }
  return { units, isUnicode: false };
}

function initCharCounter(textarea, counterEl) {
  const update = () => {
    const text = textarea.value;
    const { units, isUnicode } = calcSmsUnits(text);
    const singleLimit = isUnicode ? 70 : 160;
    const multiLimit  = isUnicode ? 67 : 153;
    const smsCount    = units === 0 ? 0 : (units <= singleLimit ? 1 : Math.ceil(units / multiLimit));
    const remaining   = smsCount <= 1 ? singleLimit - units : (smsCount * multiLimit) - units;
    const encoding    = isUnicode ? ' · Unicode ⚠' : ' · GSM7';
    counterEl.textContent = `${units} chars · ${smsCount} SMS${smsCount > 1 ? '-uri' : ''}${encoding} · ${remaining} ramase`;
    counterEl.className   = 'char-counter' + (isUnicode || units > singleLimit ? (units > singleLimit * 2 ? ' over' : ' warn') : '');
  };
  textarea.addEventListener('input', update);
  update();
}
document.querySelectorAll('[data-char-counter]').forEach(ta => {
  const counter = document.getElementById(ta.dataset.charCounter);
  if (counter) initCharCounter(ta, counter);
});

document.addEventListener('click', async e => {
  const btn = e.target.closest('[data-confirm-delete]');
  if (!btn) return;
  e.preventDefault();
  const msg = btn.dataset.confirmDelete || 'Esti sigur ca vrei sa stergi?';
  if (!confirm(msg)) return;
  const url = btn.dataset.url || btn.getAttribute('href');
  if (!url) return;
  try {
    const form = new FormData();
    form.append(document.querySelector('meta[name="csrf-name"]')?.content || '_csrf', CSRF());
    await fetch(url, { method: 'POST', body: form, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    Toast.success('Sters cu succes');
    setTimeout(() => location.reload(), 800);
  } catch (err) {
    Toast.error('Eroare la stergere: ' + err.message);
  }
});

// ── #1 FIX: Sold — robust, cu retry, accepta ambele formate de raspuns ────
async function loadBalance(retryCount = 0) {
  const el = document.getElementById('balance-value');
  if (!el) return;

  try {
    const res  = await fetch('/api/balance', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await res.json().catch(() => null);

    if (!data) { el.textContent = 'eroare'; return; }

    let balance = null;
    if (data.balance !== undefined && data.balance !== null) {
      balance = parseFloat(data.balance);
    } else if (data.details !== undefined && data.details !== null && data.details !== '') {
      balance = parseFloat(data.details);
    }

    if (balance !== null && !isNaN(balance)) {
      el.textContent = balance.toFixed(4) + ' EUR';
      el.style.color = balance < 1 ? 'var(--red)' : 'var(--green)';
    } else {
      if (retryCount < 2) {
        el.textContent = '...';
        setTimeout(() => loadBalance(retryCount + 1), 3000);
      } else {
        el.textContent = 'N/A';
        el.style.color = 'var(--text-muted)';
      }
    }
  } catch (e) {
    if (retryCount < 2) {
      setTimeout(() => loadBalance(retryCount + 1), 3000);
    } else {
      el.textContent = 'N/A';
      el.style.color = 'var(--text-muted)';
    }
  }
}

function pollCampaign(id, onUpdate) {
  const poll = async () => {
    try {
      const data = await api(`/api/campaign/${id}/status`);
      onUpdate(data);
      if (!['running', 'scheduled'].includes(data.campaign?.status)) return;
      setTimeout(poll, 3000);
    } catch { setTimeout(poll, 5000); }
  };
  poll();
}

// Phone tag input cu flush la submit
function initPhoneTagInput() {
  const wrap   = document.getElementById('phone-tag-wrap');
  const hidden = document.getElementById('phones-hidden');
  if (!wrap || !hidden) return;

  const tags  = new Set();
  const input = document.createElement('input');
  input.className   = 'form-input';
  input.style.cssText = 'flex:1;min-width:160px;background:transparent;border:none;box-shadow:none;padding:4px 6px;font-size:.84rem';
  input.placeholder = 'Adauga numar si apasa Enter...';
  wrap.appendChild(input);

  const render = () => {
    wrap.querySelectorAll('.phone-tag').forEach(t => t.remove());
    tags.forEach(phone => {
      const tag = document.createElement('span');
      tag.className = 'phone-tag';
      tag.innerHTML = `${phone}<button type="button">&#x2715;</button>`;
      tag.querySelector('button').onclick = () => { tags.delete(phone); render(); sync(); };
      wrap.insertBefore(tag, input);
    });
  };
  const sync = () => { hidden.value = [...tags].join('\n'); };
  const add  = (val) => {
    val.split(/[\s,;]+/).map(v => v.trim()).filter(Boolean).forEach(v => {
      const norm = v.replace(/\D/g,'');
      if (norm.length >= 9) { tags.add(norm); }
    });
    input.value = ''; render(); sync();
  };
  const flushInput = () => { if (input.value.trim()) { add(input.value); } };

  input.addEventListener('keydown', e => { if (['Enter','Tab',',',';'].includes(e.key)) { e.preventDefault(); add(input.value); } });
  input.addEventListener('paste',   e => { e.preventDefault(); add(e.clipboardData.getData('text')); });
  wrap.addEventListener('click', () => input.focus());
  wrap._flushInput = flushInput;
  wrap._getTags    = () => tags;
}
initPhoneTagInput();

// CSV Dropzone
function initDropzone() {
  const zone   = document.getElementById('dropzone');
  const fileIn = document.getElementById('csv-file-input');
  const mapper = document.getElementById('column-mapper');
  if (!zone || !fileIn) return;

  zone.addEventListener('click', () => fileIn.click());
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
  zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('drag-over'); handleFile(e.dataTransfer.files[0]); });
  fileIn.addEventListener('change', () => handleFile(fileIn.files[0]));

  async function handleFile(file) {
    if (!file) return;
    zone.querySelector('p').textContent = file.name + ' — analizez...';
    const fd = new FormData();
    fd.append('csv_file', file);
    fd.append(document.querySelector('meta[name="csrf-name"]')?.content || '_csrf', CSRF());
    try {
      const data = await fetch('/api/lists/import-preview', {
        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(r => r.json());
      zone.querySelector('p').textContent = `${file.name} · Delimiter: "${data.delimiter}"`;
      renderMapper(data.rows);
    } catch { zone.querySelector('p').textContent = 'Eroare la citire fisier'; }
  }

  function renderMapper(rows) {
    if (!mapper) return;
    const headers = rows[0] || [];
    const fields  = ['ignore','phone','first_name','last_name','email','extra1','extra2'];
    const labels  = {'ignore':'— Ignora —','phone':'Telefon','first_name':'Prenume','last_name':'Nume','email':'Email','extra1':'Extra 1','extra2':'Extra 2'};
    let html = '<div class="card" style="margin-top:16px"><div class="card-header"><span class="card-title">Mapare coloane</span></div>';
    html += '<div class="grid-4" style="gap:12px;margin-bottom:16px">';
    headers.forEach((h, i) => {
      html += `<div><label class="form-label">${h || 'Coloana ' + (i+1)}</label>
        <select class="form-select" name="mapping[${i}]">
          ${fields.map(f => `<option value="${f}" ${f==='phone'&&(h.toLowerCase().includes('tel')||h.toLowerCase().includes('phone'))?'selected':''}>${labels[f]}</option>`).join('')}
        </select></div>`;
    });
    html += '</div><div class="table-wrap"><table><thead><tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr></thead><tbody>';
    rows.slice(1).forEach(row => { html += '<tr>' + row.map(c => `<td class="mono">${c}</td>`).join('') + '</tr>'; });
    html += '</tbody></table></div></div>';
    mapper.innerHTML = html;
    mapper.style.display = 'block';
  }
}
initDropzone();

document.querySelectorAll('input[type="datetime-local"]').forEach(inp => {
  const pad = n => String(n).padStart(2, '0');
  const now = new Date();
  inp.min = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
});

document.querySelectorAll('[data-ajax-form]').forEach(form => {
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn  = form.querySelector('[type=submit]');
    const orig = btn?.textContent;
    if (btn) { btn.disabled = true; btn.textContent = 'Se proceseaza...'; }
    const fd = new FormData(form);
    try {
      const res = await fetch(form.action || window.location.href, {
        method: 'POST', body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF() }
      });
      const text = await res.text();
      let data;
      try { data = text ? JSON.parse(text) : {}; } catch { data = { error: 'Raspuns invalid de la server' }; }
      updateCsrf(data);
      if (data.redirect) { location.href = data.redirect; return; }
      if (data.success) {
        Toast.success(form.dataset.successMsg || 'Salvat cu succes!');
        if (form.dataset.redirect) setTimeout(() => location.href = form.dataset.redirect, 800);
        if (form.dataset.reload)   setTimeout(() => location.reload(), 800);
      } else { Toast.error(data.error || 'Eroare la salvare'); }
    } catch (err) { Toast.error('Eroare: ' + err.message); }
    finally { if (btn) { btn.disabled = false; btn.textContent = orig; } }
  });
});

const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebar       = document.querySelector('.sidebar');
if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  document.addEventListener('click', e => {
    if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target))
      sidebar.classList.remove('open');
  });
}

// ── Charts — Light theme ──────────────────────────────────────
window.initLineChart = function(canvasId, labels, datasets) {
  const ctx = document.getElementById(canvasId);
  if (!ctx || !window.Chart) return;
  new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: datasets.map(d => ({
      ...d, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, tension: .35, fill: d.fill !== false,
    }))},
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: '#6b7280', font: { family: 'Inter', size: 12 }, boxWidth: 12 } },
        tooltip: { backgroundColor: '#fff', borderColor: '#e2e8f0', borderWidth: 1, titleColor: '#0f172a', bodyColor: '#6b7280', padding: 12, cornerRadius: 8, titleFont: { family: 'Inter', weight: '600' }, bodyFont: { family: 'Inter' } }
      },
      scales: {
        x: { ticks: { color: '#94a3b8', font: { family: 'Inter', size: 11 } }, grid: { color: '#f1f5f9' }, border: { color: '#e2e8f0' } },
        y: { ticks: { color: '#94a3b8', font: { family: 'Inter', size: 11 } }, grid: { color: '#f1f5f9' }, border: { color: '#e2e8f0' }, beginAtZero: true },
      }
    }
  });
};

window.initDoughnutChart = function(canvasId, labels, data, colors) {
  const ctx = document.getElementById(canvasId);
  if (!ctx || !window.Chart) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '72%',
      plugins: {
        legend: { position: 'right', labels: { color: '#6b7280', font: { family: 'Inter', size: 12 }, padding: 16, boxWidth: 12 } },
        tooltip: { backgroundColor: '#fff', borderColor: '#e2e8f0', borderWidth: 1, titleColor: '#0f172a', bodyColor: '#6b7280', padding: 12, cornerRadius: 8 }
      }
    }
  });
};

document.addEventListener('click', e => {
  const btn = e.target.closest('[data-copy]');
  if (!btn) return;
  navigator.clipboard.writeText(btn.dataset.copy).then(() => Toast.success('Copiat!'));
});

// ── SenderSelect — dropdown cu senderi live din provider ─────
/**
 * Initializeaza un camp sender cu:
 * - Dropdown de senderi preluati live din API
 * - Buton refresh pentru reinterogare
 * - Fallback la input text manual daca API esueaza
 *
 * Folosire in HTML:
 *   <div data-sender-select="FIELD_NAME" data-current-value="CyberShield"></div>
 *
 * Sau pe un input existent:
 *   <input type="text" name="sender" data-sender-input>
 */

let _sendersCache = null;  // Cache global per sesiune de pagina

async function fetchSenders(forceRefresh = false) {
  if (_sendersCache && !forceRefresh) return _sendersCache;
  try {
    const data = await fetch('/api/senders', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json());
    _sendersCache = data;
    return data;
  } catch (e) {
    return { success: false, senders: [], default: '', error: e.message };
  }
}

function buildSenderWidget(container, fieldName, currentValue) {
  container.innerHTML = `
    <div class="sender-select-wrap" style="display:flex;gap:6px;align-items:flex-start">
      <div style="flex:1;position:relative">
        <select name="${fieldName}" class="form-select sender-select-dropdown">
          <option value="">Se incarca...</option>
        </select>
        <input type="text" name="${fieldName}" class="form-input sender-manual-input"
               placeholder="Introdu manual..." style="display:none"
               value="${currentValue || ''}">
      </div>
      <button type="button" class="btn btn-secondary btn-sm sender-refresh-btn" title="Reinterogheaza providerul"
              style="flex-shrink:0;padding:8px 10px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
          <polyline points="1 4 1 10 7 10"/>
          <path d="M3.51 15a9 9 0 1 0 .49-3.68"/>
        </svg>
      </button>
      <button type="button" class="btn btn-ghost btn-sm sender-toggle-btn" title="Introdu manual"
              style="flex-shrink:0;padding:8px 10px;font-size:.72rem">
        manual
      </button>
    </div>
    <div class="sender-status" style="font-size:.72rem;color:var(--text-muted);margin-top:4px"></div>
  `;

  const dropdown   = container.querySelector('.sender-select-dropdown');
  const manualInput= container.querySelector('.sender-manual-input');
  const refreshBtn = container.querySelector('.sender-refresh-btn');
  const toggleBtn  = container.querySelector('.sender-toggle-btn');
  const statusEl   = container.querySelector('.sender-status');
  let isManual = false;

  async function load(forceRefresh = false) {
    dropdown.disabled = true;
    refreshBtn.disabled = true;
    statusEl.textContent = 'Se interogheaza providerul...';

    const data = await fetchSenders(forceRefresh);

    if (data.senders && data.senders.length > 0) {
      dropdown.innerHTML = '';
      // Optiune goala daca nu e nimic selectat
      if (!currentValue) {
        dropdown.appendChild(new Option('— Selecteaza sender —', ''));
      }
      data.senders.forEach(s => {
        const opt = new Option(s, s);
        if (s === (currentValue || data.default)) opt.selected = true;
        dropdown.appendChild(opt);
      });
      // Daca valoarea curenta nu e in lista, adaug-o
      if (currentValue && !data.senders.includes(currentValue)) {
        const opt = new Option(currentValue + ' (curent)', currentValue, true, true);
        dropdown.insertBefore(opt, dropdown.firstChild);
      }
      statusEl.textContent = `${data.senders.length} sender${data.senders.length !== 1 ? 'i' : ''} disponibil${data.senders.length !== 1 ? 'i' : ''}`;
      statusEl.style.color = 'var(--green)';
    } else {
      // Fallback la manual
      dropdown.innerHTML = '<option value="">Nu s-au gasit senderi</option>';
      statusEl.textContent = data.error ? `Eroare API: ${data.error} — introdu manual` : 'Nu s-au gasit senderi — introdu manual';
      statusEl.style.color = 'var(--yellow)';
      // Switch automat la manual
      if (!isManual) toggleManual();
    }

    dropdown.disabled = false;
    refreshBtn.disabled = false;
  }

  function toggleManual() {
    isManual = !isManual;
    dropdown.style.display    = isManual ? 'none' : '';
    manualInput.style.display = isManual ? '' : 'none';
    // Dezactiveaza name pe cel ascuns ca sa nu se trimita
    dropdown.name    = isManual ? '' : fieldName;
    manualInput.name = isManual ? fieldName : '';
    toggleBtn.textContent = isManual ? 'din lista' : 'manual';
    if (!isManual && dropdown.value) {
      manualInput.value = dropdown.value;
    }
  }

  refreshBtn.addEventListener('click', () => load(true));
  toggleBtn.addEventListener('click', toggleManual);

  // Sincronizeaza manual input cu dropdown la schimbare
  dropdown.addEventListener('change', () => { manualInput.value = dropdown.value; });

  load();
}

// Auto-initializare pentru toate elementele cu data-sender-select
function initAllSenderSelects() {
  document.querySelectorAll('[data-sender-select]').forEach(container => {
    const fieldName    = container.dataset.senderSelect;
    const currentValue = container.dataset.currentValue || '';
    buildSenderWidget(container, fieldName, currentValue);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  loadBalance();
  initAllSenderSelects();
});
