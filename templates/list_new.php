<?php
$pageTitle  = 'Lista noua';
$breadcrumb = [['Liste','/lists'],['Noua',null]];
ob_start();
?>
<div style="max-width:560px;margin:0 auto">
<div class="card">
  <div class="card-header"><div class="card-title">Creeaza lista contacte</div></div>
  <form method="POST" action="/lists/new">
    <?= Auth::csrfField() ?>
    <div class="form-group">
      <label class="form-label">Nume lista <span class="required">*</span></label>
      <input type="text" name="name" class="form-input" placeholder="ex: Clienti Volare Iunie 2025" required>
    </div>
    <div class="form-group">
      <label class="form-label">Descriere</label>
      <textarea name="description" class="form-textarea" style="min-height:70px" placeholder="Optional"></textarea>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <a href="/lists" class="btn btn-secondary">Anuleaza</a>
      <button type="submit" class="btn btn-primary">Creeaza lista</button>
    </div>
  </form>
</div>
</div>
<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
