<?php
$pageTitle  = htmlspecialchars($list['name']);
$breadcrumb = [['Dashboard','/dashboard'],['Liste','/lists'],[$list['name'],null]];
$topbarActions = '<button class="btn btn-primary btn-sm" data-modal-open="import-modal">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
  Import CSV
</button>';
ob_start();
?>

<div class="card" style="margin-bottom:20px">
  <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
    <div style="flex:1">
      <div style="font-size:1.2rem;font-weight:700;letter-spacing:-.03em"><?= htmlspecialchars($list['name']) ?></div>
      <?php if ($list['description']): ?><div style="font-size:.83rem;color:var(--text-muted);margin-top:4px"><?= htmlspecialchars($list['description']) ?></div><?php endif; ?>
    </div>
    <div style="text-align:center">
      <div style="font-size:.7rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:600">Total contacte</div>
      <div style="font-size:2rem;font-weight:700;color:var(--accent);letter-spacing:-.04em"><?= number_format((int)$list['total_contacts']) ?></div>
    </div>
    <a href="/campaigns/new" class="btn btn-secondary btn-sm">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      Campanie cu lista asta
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Contacte</div>
    <form method="GET" style="display:flex;gap:8px">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-input" style="width:220px" placeholder="Cauta telefon, nume...">
      <button type="submit" class="btn btn-secondary btn-sm">Cauta</button>
      <?php if ($search): ?><a href="?" class="btn btn-ghost btn-sm">&#x2715;</a><?php endif; ?>
    </form>
  </div>

  <div class="table-wrap">
  <table>
    <thead><tr><th>#</th><th>Telefon</th><th>Prenume</th><th>Nume</th><th>Extra</th><th>Status</th><th>Adaugat</th></tr></thead>
    <tbody>
    <?php if (empty($contacts)): ?>
      <tr><td colspan="7" class="table-empty">
        <?php if ($search): ?>Niciun contact gasit pentru "<?= htmlspecialchars($search) ?>".
        <?php else: ?>Lista e goala. <button class="btn btn-ghost btn-sm" data-modal-open="import-modal">Importa contacte</button><?php endif; ?>
      </td></tr>
    <?php else: ?>
      <?php foreach ($contacts as $i => $c): ?>
      <tr>
        <td style="color:var(--text-muted);font-family:var(--font-mono);font-size:.75rem"><?= (($page-1)*50)+$i+1 ?></td>
        <td><a href="/reports/contact?phone=<?= urlencode($c['phone']) ?>" class="mono"><?= htmlspecialchars($c['phone']) ?></a></td>
        <td><?= htmlspecialchars($c['first_name'] ?? '&#x2014;') ?></td>
        <td><?= htmlspecialchars($c['last_name'] ?? '&#x2014;') ?></td>
        <td style="font-size:.75rem;color:var(--text-muted)">
          <?php if ($c['extra_data']): $ex = json_decode($c['extra_data'],true); echo htmlspecialchars(implode(', ', array_values($ex??[]))); endif; ?>
        </td>
        <td><?= $c['is_blocked'] ? '<span class="badge badge-red">Blocat</span>' : '<span class="badge badge-green">Activ</span>' ?></td>
        <td style="font-size:.75rem;color:var(--text-muted)"><?= date('d.m.Y', strtotime($c['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
  </div>

  <?php if ($total > 50): ?>
  <div class="pagination">
    <?php $totalPages = ceil($total/50); for($p=max(1,$page-3);$p<=min($totalPages,$page+3);$p++): ?>
      <a href="?page=<?=$p?><?=$search?'&q='.urlencode($search):''?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <div style="text-align:center;font-size:.78rem;color:var(--text-muted);margin-top:8px"><?= number_format($total) ?> contacte total</div>
  <?php endif; ?>
</div>

<!-- Import Modal -->
<div class="modal-overlay" id="import-modal">
  <div class="modal" style="max-width:660px">
    <div class="modal-header">
      <div class="modal-title">Import contacte CSV</div>
      <button class="modal-close" data-modal-close><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <form id="import-form" enctype="multipart/form-data" action="/lists/<?= $list['id'] ?>/import" method="POST">
      <?= Auth::csrfField() ?>
      <div class="dropzone" id="dropzone">
        <div class="dropzone-icon">&#x1F4E4;</div>
        <h3>Trage fisierul CSV / Excel aici sau click</h3>
        <p>Suportat: .csv, .txt · Max <?= UPLOAD_MAX_ROWS ?> randuri · Delimiter auto-detectat</p>
        <input type="file" id="csv-file-input" name="csv_file" accept=".csv,.txt,.tsv" style="display:none">
      </div>
      <div style="display:flex;gap:16px;margin-top:16px">
        <label style="display:flex;align-items:center;gap:8px;font-size:.845rem;cursor:pointer">
          <input type="checkbox" name="has_header" value="1" checked style="accent-color:var(--accent)">
          Primul rand = antet coloane
        </label>
      </div>
      <div id="column-mapper" style="display:none"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Anuleaza</button>
        <button type="submit" class="btn btn-primary" id="btn-import">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/></svg>
          Importa contacte
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('import-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-import');
  btn.disabled = true; btn.textContent = 'Se importa...';
  const fd = new FormData(this);
  document.querySelectorAll('#column-mapper select').forEach(sel => { fd.append(sel.name, sel.value); });
  try {
    const data = await fetch(this.action, {
      method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content}
    }).then(r => r.json());
    if (data.success) {
      Toast.success(`Import reusit: ${data.imported.toLocaleString()} contacte adaugate, ${data.skipped} sarite`);
      Modal.closeAll();
      setTimeout(() => location.reload(), 1000);
    } else { Toast.error(data.error || 'Eroare la import'); }
  } catch(err) { Toast.error(err.message); }
  finally { btn.disabled = false; btn.textContent = 'Importa contacte'; }
});
</script>

<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
