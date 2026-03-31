<?php
$pageTitle  = 'Quick SMS';
$breadcrumb = [['Dashboard','/dashboard'],['Quick SMS',null]];
ob_start();
?>
<div style="max-width:700px;margin:0 auto">
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Trimitere rapida SMS</div>
      <div class="card-subtitle">Trimite imediat sau programeaza catre unul sau mai multe numere</div>
    </div>
    <?php if (!empty($templates)): ?>
    <select id="tpl-select" class="form-select" style="width:180px;font-size:.8rem">
      <option value="">Foloseste template...</option>
      <?php foreach ($templates as $t): ?>
        <option value="<?= htmlspecialchars($t['body'], ENT_QUOTES) ?>"><?= htmlspecialchars($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
  </div>

  <form id="quick-sms-form">
    <?= Auth::csrfField() ?>

    <div class="form-group">
      <label class="form-label">Numere de telefon <span class="required">*</span></label>
      <div class="tag-input-wrap" id="phone-tag-wrap" style="min-height:44px"></div>
      <input type="hidden" name="phones" id="phones-hidden">
      <div class="form-hint">Tastati numarul si apasati Enter sau virgula. Formate: 07xx, +407xx.</div>
    </div>

    <div class="form-group">
      <label class="form-label">Sender (expeditor)</label>
      <div data-sender-select="sender"
           data-current-value="<?= htmlspecialchars($client['default_sender'] ?? '') ?>"></div>
      <div class="form-hint">Senderul aprobat care va aparea pe telefonul destinatarului.</div>
    </div>

    <div class="form-group">
      <label class="form-label" for="qs-message">Mesaj <span class="required">*</span></label>
      <textarea id="qs-message" name="message_text" class="form-textarea" style="min-height:90px"
                placeholder="Buna ziua! Avem o oferta speciala..."
                data-char-counter="qs-counter" required></textarea>
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-top:6px;gap:12px;flex-wrap:wrap">
        <div style="font-size:.73rem;color:var(--text-muted);line-height:1.7">
          <strong style="color:var(--text-secondary)">Variabile:</strong>
          <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{prenume}</code>
          <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{nume}</code>
          <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{telefon}</code>
          <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{optout_url}</code><br>
          <span style="color:var(--yellow)">&#9888; Diacriticele = mod Unicode (70 chars/SMS)</span>
        </div>
        <div id="qs-counter" class="char-counter" style="float:none;flex-shrink:0"></div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="qs-scheduled">Programeaza trimitere <span style="color:var(--text-muted)">(optional)</span></label>
      <input type="datetime-local" id="qs-scheduled" name="scheduled_at" class="form-input" style="max-width:260px">
    </div>

    <div class="divider"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button type="button" class="btn btn-secondary" id="qs-reset">Reseteaza</button>
      <button type="submit" class="btn btn-primary" id="qs-submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Trimite SMS
      </button>
    </div>
  </form>
</div>

<div id="qs-results" style="display:none;margin-top:16px" class="card">
  <div class="card-header"><div class="card-title">Rezultate trimitere</div></div>
  <div id="qs-results-body"></div>
</div>
</div>

<script>
document.getElementById('tpl-select')?.addEventListener('change', function() {
  if (this.value) {
    document.getElementById('qs-message').value = this.value;
    document.getElementById('qs-message').dispatchEvent(new Event('input'));
    this.value = '';
  }
});

document.getElementById('qs-reset').addEventListener('click', function() {
  document.getElementById('quick-sms-form').reset();
  const wrap = document.getElementById('phone-tag-wrap');
  if (wrap?._getTags) { wrap._getTags().clear(); }
  document.getElementById('phones-hidden').value = '';
  document.getElementById('qs-results').style.display = 'none';
});

document.getElementById('quick-sms-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const wrap = document.getElementById('phone-tag-wrap');
  if (wrap?._flushInput) wrap._flushInput();
  const phones = document.getElementById('phones-hidden').value.trim();
  const msg    = document.getElementById('qs-message').value.trim();
  if (!phones) { Toast.error('Adauga cel putin un numar de telefon'); return; }
  if (!msg)    { Toast.error('Completeaza textul mesajului'); return; }

  const btn = document.getElementById('qs-submit');
  btn.disabled = true; btn.textContent = 'Se trimite...';
  try {
    const fd = new FormData(this);
    const res = await fetch('/quick-sms', {
      method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}
    });
    const text = await res.text();
    let data;
    try { data = text ? JSON.parse(text) : {}; } catch { data = { error: 'Raspuns invalid de la server' }; }
    updateCsrf(data);

    if (data.success) {
      const results = data.results || [];
      const ok   = results.filter(r => ['sent','scheduled'].includes(r.status)).length;
      const fail = results.filter(r => ['failed','invalid','blocked','duplicate'].includes(r.status)).length;
      Toast.success(`Procesat: ${ok} trimise, ${fail} cu probleme`);
      const badges = {sent:'badge-blue',scheduled:'badge-yellow',failed:'badge-red',invalid:'badge-red',blocked:'badge-yellow',duplicate:'badge-gray'};
      const labels = {sent:'Trimis',scheduled:'Programat',failed:'Esuat',invalid:'Invalid',blocked:'Blacklist',duplicate:'Duplicat'};
      let html = '<div class="table-wrap"><table><thead><tr><th>Telefon</th><th>Status</th><th>Detalii</th></tr></thead><tbody>';
      results.forEach(r => {
        html += `<tr><td class="mono">${r.phone}</td><td><span class="badge ${badges[r.status]||'badge-gray'}">${labels[r.status]||r.status}</span></td><td style="font-size:.77rem;color:var(--text-muted)">${r.uuid||r.error||''}</td></tr>`;
      });
      html += '</tbody></table></div>';
      document.getElementById('qs-results-body').innerHTML = html;
      document.getElementById('qs-results').style.display = 'block';
    } else { Toast.error(data.error || 'Eroare'); }
  } catch(err) { Toast.error('Eroare: ' + err.message); }
  finally {
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Trimite SMS';
  }
});
</script>

<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
