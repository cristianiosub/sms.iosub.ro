<?php
$pageTitle  = 'Client nou';
$breadcrumb = [['Admin','/admin/clients'],['Client nou',null]];
ob_start();
?>
<div style="max-width:560px;margin:0 auto"><div class="card">
  <div class="card-header"><div class="card-title">Adauga client nou</div></div>
  <form method="POST" action="/admin/clients/new">
    <?= Auth::csrfField() ?>
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Nume client <span class="required">*</span></label>
        <input type="text" name="name" class="form-input" placeholder="Pizzeria Volare" required>
      </div>
      <div class="form-group">
        <label class="form-label">Slug (URL) <span class="required">*</span></label>
        <input type="text" name="slug" class="form-input" placeholder="volare" pattern="[a-z0-9_-]+" required>
        <div class="form-hint">Litere mici, cifre, _ sau -</div>
      </div>
    </div>
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">SendSMS Username <span class="required">*</span></label>
        <input type="text" name="sendsms_username" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">SendSMS API Key <span class="required">*</span></label>
        <input type="text" name="sendsms_apikey" class="form-input" required>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Sender implicit</label>
      <input type="text" name="default_sender" class="form-input" maxlength="11">
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <a href="/admin/clients" class="btn btn-secondary">Anuleaza</a>
      <button type="submit" class="btn btn-primary">Creeaza client</button>
    </div>
  </form>
</div></div>
<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
