<?php
$pageTitle  = 'Utilizator nou';
$breadcrumb = [['Admin','/admin/users'],['Utilizator nou',null]];
ob_start();
?>
<div style="max-width:580px;margin:0 auto">
<div class="card">
  <div class="card-header"><div class="card-title">Adauga utilizator nou</div></div>
  <form method="POST" action="/admin/users/new">
    <?= Auth::csrfField() ?>
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Nume complet <span class="required">*</span></label>
        <input type="text" name="full_name" class="form-input" placeholder="Ion Popescu" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email <span class="required">*</span></label>
        <input type="email" name="email" class="form-input" placeholder="ion@exemplu.ro" required>
      </div>
    </div>
    <div class="grid-2">
      <div class="form-group">
        <label class="form-label">Parola <span class="required">*</span></label>
        <input type="password" name="password" class="form-input" placeholder="Minim 10 caractere" minlength="10" required>
      </div>
      <div class="form-group">
        <label class="form-label">Rol</label>
        <select name="role" class="form-select">
          <option value="user">User</option>
          <option value="admin">Admin</option>
          <option value="superadmin">Super Admin</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Acces la clienti</label>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;margin-top:4px">
        <?php foreach($clients as $c): ?>
        <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:.845rem;transition:border-color var(--transition)"
               onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
          <input type="checkbox" name="clients[]" value="<?= $c['id'] ?>" style="accent-color:var(--accent)">
          <?= htmlspecialchars($c['name']) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="form-hint">Super Admin are acces automat la toti clientii.</div>
    </div>
    <div class="divider"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <a href="/admin/users" class="btn btn-secondary">Anuleaza</a>
      <button type="submit" class="btn btn-primary">Creeaza utilizator</button>
    </div>
  </form>
</div>
</div>
<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
