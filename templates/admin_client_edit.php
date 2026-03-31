<?php
$pageTitle  = 'Editeaza client — ' . htmlspecialchars($client['name']);
$breadcrumb = [['Admin','/admin/clients'],[$client['name'],null]];
$prov = $client['sms_provider'] ?? 'sendsms';
ob_start();
?>
<div style="max-width:640px;margin:0 auto">
<div class="card">
  <div class="card-header"><div class="card-title">Editeaza client</div></div>
  <form id="edit-client-form">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="_method" value="POST">

    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Nume client <span class="required">*</span></label>
        <input type="text" name="name" class="form-input"
               value="<?= htmlspecialchars($client['name']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Sender implicit</label>
        <input type="text" name="default_sender" class="form-input" maxlength="11"
               value="<?= htmlspecialchars($client['default_sender'] ?? '') ?>"
               placeholder="ex: CyberShield">
      </div>
    </div>

    <div class="divider"></div>
    <div style="font-weight:600;margin-bottom:14px;font-size:.9rem">Provider SMS</div>

    <div class="form-group">
      <label class="form-label">Provider activ</label>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <label class="provider-pill <?= $prov==='sendsms'?'active':'' ?>">
          <input type="radio" name="sms_provider" value="sendsms" <?= $prov==='sendsms'?'checked':'' ?>>
          sendsms.ro
        </label>
        <label class="provider-pill <?= $prov==='smsapi'?'active':'' ?>">
          <input type="radio" name="sms_provider" value="smsapi" <?= $prov==='smsapi'?'checked':'' ?>>
          smsapi.ro
        </label>
        <label class="provider-pill <?= $prov==='smso'?'active':'' ?>">
          <input type="radio" name="sms_provider" value="smso" <?= $prov==='smso'?'checked':'' ?>>
          smso.ro
        </label>
      </div>
    </div>

    <!-- sendsms.ro -->
    <div id="fields-sendsms" style="display:<?= $prov==='sendsms'?'block':'none' ?>">
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Username sendsms.ro</label>
          <input type="text" name="sendsms_username" class="form-input"
                 value="<?= htmlspecialchars($client['sendsms_username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">API Key sendsms.ro</label>
          <input type="text" name="sendsms_apikey" class="form-input"
                 value="<?= htmlspecialchars($client['sendsms_apikey'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- smsapi.ro -->
    <div id="fields-smsapi" style="display:<?= $prov==='smsapi'?'block':'none' ?>">
      <div class="form-group">
        <label class="form-label">Bearer Token smsapi.ro</label>
        <input type="text" name="smsapi_token" class="form-input"
               value="<?= htmlspecialchars($client['smsapi_token'] ?? '') ?>"
               placeholder="Token OAuth2 din portal.smsapi.ro">
        <div class="form-hint">
          Genereaza din <a href="https://portal.smsapi.ro" target="_blank">portal.smsapi.ro</a> → OAuth Tokens
        </div>
      </div>
    </div>

    <!-- smso.ro -->
    <div id="fields-smso" style="display:<?= $prov==='smso'?'block':'none' ?>">
      <div class="form-group">
        <label class="form-label">API Key smso.ro</label>
        <input type="text" name="smso_apikey" class="form-input"
               value="<?= htmlspecialchars($client['smso_token'] ?? '') ?>"
               placeholder="ex: x9iKqN4ScIf125LsJczJSg...">
        <div class="form-hint">
          Gasesti cheia in <a href="https://app.smso.ro" target="_blank">app.smso.ro</a> → Settings → API
        </div>
      </div>
    </div>

    <div class="divider"></div>
    <div style="font-weight:600;margin-bottom:14px;font-size:.9rem">Alerta sold scazut prin SMS</div>

    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Numar telefon alerta</label>
        <input type="text" name="alert_email" class="form-input"
               value="<?= htmlspecialchars($client['alert_email'] ?? '') ?>"
               placeholder="07xx xxx xxx">
        <div class="form-hint">Primesti SMS cand soldul scade sub prag. Lasa gol pentru a dezactiva.</div>
      </div>
      <div class="form-group">
        <label class="form-label">Prag sold (EUR)</label>
        <input type="number" name="alert_threshold" class="form-input" step="0.01" min="0"
               value="<?= htmlspecialchars((string)($client['alert_threshold'] ?? '5')) ?>">
      </div>
    </div>

    <div class="divider"></div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.855rem">
        <input type="checkbox" name="is_active" value="1"
               <?= $client['is_active'] ? 'checked' : '' ?>
               style="accent-color:var(--accent)">
        Client activ
      </label>
    </div>

    <div style="display:flex;gap:8px;justify-content:flex-end">
      <a href="/admin/clients" class="btn btn-secondary">Anuleaza</a>
      <button type="submit" class="btn btn-primary">Salveaza modificarile</button>
    </div>
  </form>
</div>
</div>

<style>
.provider-pill {
  display:flex;align-items:center;gap:8px;padding:8px 16px;
  border:1px solid var(--color-border-tertiary);border-radius:var(--border-radius-md);
  cursor:pointer;font-size:.855rem;font-weight:500;color:var(--color-text-secondary);
  transition:all .15s;user-select:none;
}
.provider-pill:hover { border-color:var(--color-border-primary); color:var(--color-text-primary); }
.provider-pill.active, .provider-pill:has(input:checked) {
  border-color:#4f46e5;background:#eef2ff;color:#4f46e5;
}
.provider-pill input[type=radio] { display:none; }
</style>

<script>
// Arata/ascunde campurile in functie de providerul selectat
document.querySelectorAll('input[name="sms_provider"]').forEach(radio => {
  radio.addEventListener('change', function() {
    document.getElementById('fields-sendsms').style.display = this.value === 'sendsms' ? 'block' : 'none';
    document.getElementById('fields-smsapi').style.display  = this.value === 'smsapi'  ? 'block' : 'none';
    document.getElementById('fields-smso').style.display    = this.value === 'smso'    ? 'block' : 'none';
    // Actualizeaza stilul pill-urilor
    document.querySelectorAll('.provider-pill').forEach(p => p.classList.remove('active'));
    this.closest('.provider-pill')?.classList.add('active');
  });
});

// Override data-ajax-form sa trimita la URL-ul corect
document.getElementById('edit-client-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn  = this.querySelector('[type=submit]');
  const orig = btn.textContent;
  btn.disabled = true; btn.textContent = 'Se salveaza...';

  const fd = new FormData(this);
  try {
    const resp = await fetch('/admin/clients/<?= (int)$client['id'] ?>/edit', {
      method: 'POST', body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF() }
    });
    const data = await resp.json().catch(() => ({}));
    if (data.success) {
      Toast.success('Client salvat cu succes!');
      setTimeout(() => location.href = '/admin/clients', 800);
    } else {
      Toast.error(data.error || 'Eroare la salvare');
    }
  } catch(err) {
    Toast.error('Eroare: ' + err.message);
  } finally {
    btn.disabled = false; btn.textContent = orig;
  }
});
</script>

<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
