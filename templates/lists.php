<?php
$pageTitle   = 'Liste contacte';
$breadcrumb  = [['Dashboard','/dashboard'],['Liste',null]];
$topbarActions = '<a href="/lists/new" class="btn btn-primary btn-sm">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  Lista noua
</a>';
ob_start();
?>
<div class="section-header">
  <div>
    <div class="section-title">Liste de contacte</div>
    <div class="section-sub"><?= count($lists) ?> liste disponibile</div>
  </div>
</div>

<?php if (empty($lists)): ?>
<div class="card" style="text-align:center;padding:60px 20px">
  <p style="color:var(--text-muted);margin-bottom:20px">Nicio lista inca.</p>
  <a href="/lists/new" class="btn btn-primary">Creeaza prima lista</a>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
  <?php foreach ($lists as $l): ?>
  <div class="card">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
      <div>
        <div style="font-weight:700;font-size:.95rem;margin-bottom:4px"><?= htmlspecialchars($l['name']) ?></div>
        <?php if ($l['description']): ?><div style="font-size:.78rem;color:var(--text-muted)"><?= htmlspecialchars(substr($l['description'],0,80)) ?></div><?php endif; ?>
      </div>
    </div>
    <div style="display:flex;gap:20px;margin-bottom:16px">
      <div>
        <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:600">Contacte</div>
        <div style="font-size:1.6rem;font-weight:700;letter-spacing:-.03em;color:var(--accent)"><?= number_format((int)$l['total_contacts']) ?></div>
      </div>
      <div>
        <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:600">Creata</div>
        <div style="font-size:.855rem;font-weight:600;margin-top:4px"><?= date('d.m.Y', strtotime($l['created_at'])) ?></div>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="/lists/<?= $l['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        Gestioneaza
      </a>
      <button class="btn btn-danger btn-sm"
              data-confirm-delete="Stergi lista «<?= htmlspecialchars(addslashes($l['name'])) ?>» si toate contactele?"
              data-url="/lists/<?= $l['id'] ?>/delete">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
