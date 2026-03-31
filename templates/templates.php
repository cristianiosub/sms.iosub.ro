<?php
$pageTitle   = 'Template-uri mesaje';
$breadcrumb  = [['Dashboard','/dashboard'],['Template-uri',null]];
ob_start();
?>
<div class="grid-2" style="align-items:start;gap:20px">

<!-- Lista template-uri -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Template-uri (<?= count($templates) ?>)</div>
  </div>
  <?php if (empty($templates)): ?>
    <div class="table-empty">Niciun template. Creeaza primul template din formularul alaturi.</div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ($templates as $t): ?>
    <div class="tpl-card" data-id="<?= $t['id'] ?>" data-name="<?= htmlspecialchars($t['name'],ENT_QUOTES) ?>" data-body="<?= htmlspecialchars($t['body'],ENT_QUOTES) ?>" data-cat="<?= htmlspecialchars($t['category']??'',ENT_QUOTES) ?>">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:.875rem;color:var(--text-primary);margin-bottom:3px"><?= htmlspecialchars($t['name']) ?></div>
          <?php if ($t['category']): ?><span class="badge badge-accent" style="margin-bottom:6px;font-size:.67rem"><?= htmlspecialchars($t['category']) ?></span><?php endif; ?>
          <div class="msg-bubble" style="font-size:.78rem;margin-top:4px"><?= htmlspecialchars($t['body']) ?></div>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0">
          <button class="btn btn-secondary btn-sm btn-edit-tpl" data-id="<?= $t['id'] ?>" style="padding:4px 8px" title="Editeaza">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="btn btn-danger btn-sm" data-confirm-delete="Stergi template-ul «<?= htmlspecialchars(addslashes($t['name'])) ?>»?" data-url="/templates/<?= $t['id'] ?>/delete" style="padding:4px 8px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Formular creare/editare -->
<div class="card" id="tpl-form-card">
  <div class="card-header">
    <div class="card-title" id="tpl-form-title">Template nou</div>
    <button class="btn btn-ghost btn-sm" id="btn-reset-form" style="display:none">Anuleaza</button>
  </div>
  <div class="form-group">
    <label class="form-label">Nume template <span class="required">*</span></label>
    <input type="text" id="tpl-name" class="form-input" placeholder="ex: Confirmare comanda, Promotie weekend...">
  </div>
  <div class="form-group">
    <label class="form-label">Categorie <span style="color:var(--text-muted)">(optional)</span></label>
    <input type="text" id="tpl-cat" class="form-input" placeholder="ex: Promotii, Notificari, Info...">
  </div>
  <div class="form-group">
    <label class="form-label">Continut mesaj <span class="required">*</span></label>
    <textarea id="tpl-body" class="form-textarea" data-char-counter="tpl-counter"
              placeholder="Buna ziua {prenume}! Avem o oferta speciala..."></textarea>
    <div id="tpl-counter" class="char-counter"></div>
    <div class="form-hint" style="margin-top:6px">
      Variabile disponibile:
      <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{prenume}</code>
      <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{nume}</code>
      <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{telefon}</code>
      <code style="background:var(--accent-subtle);color:var(--accent);padding:1px 5px;border-radius:3px">{optout_url}</code>
    </div>
  </div>
  <input type="hidden" id="tpl-id" value="">
  <div style="display:flex;gap:8px;justify-content:flex-end">
    <button class="btn btn-primary" id="btn-save-tpl">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/></svg>
      Salveaza template
    </button>
  </div>
</div>

</div>

<style>
.tpl-card{background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;transition:border-color var(--tr)}
.tpl-card:hover{border-color:var(--accent)}
</style>

<script>
const CSRF_V = document.querySelector('meta[name="csrf-token"]').content;
const CSRF_N = document.querySelector('meta[name="csrf-name"]').content;

document.getElementById('btn-save-tpl').addEventListener('click', async function() {
  const id   = document.getElementById('tpl-id').value;
  const name = document.getElementById('tpl-name').value.trim();
  const body = document.getElementById('tpl-body').value.trim();
  const cat  = document.getElementById('tpl-cat').value.trim();
  if (!name || !body) { Toast.error('Numele si continutul sunt obligatorii'); return; }
  this.disabled = true; this.textContent = 'Se salveaza...';
  const fd = new FormData();
  fd.append(CSRF_N, CSRF_V);
  fd.append('id', id);
  fd.append('name', name);
  fd.append('body', body);
  fd.append('category', cat);
  try {
    const data = await fetch('/templates/save', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(r=>r.json());
    if (data.success) {
      Toast.success(data.action === 'created' ? 'Template creat!' : 'Template actualizat!');
      setTimeout(() => location.reload(), 600);
    } else { Toast.error(data.error); }
  } catch(e) { Toast.error(e.message); }
  finally { this.disabled = false; this.textContent = 'Salveaza template'; }
});

document.querySelectorAll('.btn-edit-tpl').forEach(btn => {
  btn.addEventListener('click', function() {
    const card = this.closest('.tpl-card');
    document.getElementById('tpl-id').value   = card.dataset.id;
    document.getElementById('tpl-name').value = card.dataset.name;
    document.getElementById('tpl-body').value = card.dataset.body;
    document.getElementById('tpl-cat').value  = card.dataset.cat;
    document.getElementById('tpl-form-title').textContent = 'Editeaza template';
    document.getElementById('btn-reset-form').style.display = '';
    document.getElementById('tpl-form-card').scrollIntoView({ behavior:'smooth' });
    document.getElementById('tpl-name').focus();
    // Trigger char counter update
    document.getElementById('tpl-body').dispatchEvent(new Event('input'));
  });
});

document.getElementById('btn-reset-form').addEventListener('click', function() {
  document.getElementById('tpl-id').value   = '';
  document.getElementById('tpl-name').value = '';
  document.getElementById('tpl-body').value = '';
  document.getElementById('tpl-cat').value  = '';
  document.getElementById('tpl-form-title').textContent = 'Template nou';
  this.style.display = 'none';
  document.getElementById('tpl-body').dispatchEvent(new Event('input'));
});
</script>

<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
