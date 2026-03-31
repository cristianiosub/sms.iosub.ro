<?php
$pageTitle  = 'Campanie noua';
$breadcrumb = [['Dashboard','/dashboard'],['Campanii','/campaigns'],['Noua',null]];
ob_start();
?>
<div style="max-width:760px;margin:0 auto">
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Creeaza campanie SMS</div>
      <div class="card-subtitle">Configureaza campania si trimite sau programeaza</div>
    </div>
    <?php if (!empty($templates)): ?>
    <select id="tpl-select" class="form-select" style="width:180px;font-size:.8rem">
      <option value="">Alege template...</option>
      <?php foreach ($templates as $t): ?>
        <option value="<?= htmlspecialchars($t['body'], ENT_QUOTES) ?>"><?= htmlspecialchars($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
  </div>

  <form method="POST" action="/campaigns/new" id="campaign-form">
    <?= Auth::csrfField() ?>

    <div class="form-group">
      <label class="form-label" for="camp-name">Nume campanie <span class="required">*</span></label>
      <input type="text" id="camp-name" name="name" class="form-input"
             placeholder="ex: Promo Vineri Volare" required>
    </div>

    <div class="grid-2">
      <div class="form-group">
        <label class="form-label" for="camp-list">Lista contacte <span class="required">*</span></label>
        <select id="camp-list" name="list_id" class="form-select" required>
          <option value="">Selecteaza lista...</option>
          <?php foreach ($lists as $l): ?>
            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?> (<?= number_format((int)$l['total_contacts']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($lists)): ?>
          <div class="form-hint"><a href="/lists/new">Creeaza mai intai o lista</a></div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label class="form-label">Sender (expeditor)</label>
        <div data-sender-select="sender"
             data-current-value="<?= htmlspecialchars($client['default_sender'] ?? '') ?>"></div>
        <div class="form-hint">Senderul aprobat care va aparea pe telefoanele destinatarilor.</div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="camp-message">Mesaj SMS <span class="required">*</span></label>
      <textarea id="camp-message" name="message_text" class="form-textarea"
                placeholder="Buna {prenume}! Avem o oferta speciala..."
                data-char-counter="camp-counter" required></textarea>
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:6px;gap:10px;flex-wrap:wrap">
        <div style="font-size:.73rem;color:var(--text-muted);line-height:1.7">
          <strong style="color:var(--text-secondary)">Variabile:</strong>
          <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{prenume}</code>
          <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{nume}</code>
          <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{telefon}</code>
          <code style="background:var(--red-subtle);color:var(--red);padding:1px 5px;border-radius:3px">{optout_url}</code><br>
          <span style="color:var(--yellow)">&#9888; Diacriticele = mod Unicode (70 chars/SMS)</span>
        </div>
        <div id="camp-counter" class="char-counter" style="float:none;flex-shrink:0"></div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="camp-schedule">Programeaza trimitere <span style="color:var(--text-muted)">(optional)</span></label>
      <input type="datetime-local" id="camp-schedule" name="scheduled_at"
             class="form-input" style="max-width:260px">
      <div class="form-hint">Lasa gol pentru a salva ca Draft si trimite manual ulterior.</div>
    </div>

    <details style="margin-bottom:16px">
      <summary style="cursor:pointer;font-size:.83rem;font-weight:600;color:var(--text-secondary);user-select:none;padding:8px 0">
        &#9881; Setari avansate — Rate limiting (optional)
      </summary>
      <div style="padding:14px;background:var(--bg-elevated);border-radius:var(--radius);margin-top:8px;border:1px solid var(--border)">
        <div class="grid-2">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">SMS per lot</label>
            <input type="number" name="batch_size" class="form-input" min="0" max="10000" value="0">
            <div class="form-hint">0 = trimite toate deodata.</div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Pauza intre loturi (secunde)</label>
            <input type="number" name="batch_interval" class="form-input" min="0" max="3600" value="0">
          </div>
        </div>
      </div>
    </details>

    <div class="divider"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <a href="/campaigns" class="btn btn-secondary">Anuleaza</a>
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/></svg>
        Salveaza campania
      </button>
    </div>
  </form>
</div>
</div>

<script>
document.getElementById('tpl-select')?.addEventListener('change', function() {
  if (this.value) {
    document.getElementById('camp-message').value = this.value;
    document.getElementById('camp-message').dispatchEvent(new Event('input'));
    this.value = '';
  }
});
</script>

<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
