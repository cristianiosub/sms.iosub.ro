<?php
$pageTitle    = 'Blacklist / Opt-out';
$breadcrumb   = [['Dashboard','/dashboard'],['Blacklist',null]];
$topbarActions = '<button class="btn btn-primary btn-sm" data-modal-open="add-modal">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  Adauga numere
</button>';
ob_start();
?>

<div class="card" style="margin-bottom:18px;background:var(--yellow-subtle);border-color:var(--yellow-border)">
  <div style="display:flex;gap:12px;align-items:flex-start">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--yellow)" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <div style="font-size:.83rem;color:var(--yellow)">
      <strong>Obligatoriu legal (GDPR / Legea 506/2004):</strong> Numerele din aceasta lista nu vor primi niciodata mesaje SMS din aceasta platforma, indiferent de campanie.
      Numerele care raspund <strong>STOP</strong> la un mesaj pot fi adaugate automat daca configurezi URL-ul de opt-out.
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Numere blocate (<?= number_format($total) ?>)</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <form method="GET" style="display:flex;gap:6px">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-input" style="width:180px" placeholder="Cauta numar...">
        <button type="submit" class="btn btn-secondary btn-sm">Cauta</button>
        <?php if ($search): ?><a href="/optout" class="btn btn-ghost btn-sm">&#x2715;</a><?php endif; ?>
      </form>
      <button class="btn btn-secondary btn-sm" data-modal-open="import-modal">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Import CSV
      </button>
    </div>
  </div>

  <?php if (empty($entries)): ?>
    <div class="table-empty">Niciun numar blocat<?= $search ? " pentru \"$search\"" : '' ?>.</div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Telefon</th><th>Motiv</th><th>Adaugat</th><th style="width:60px"></th></tr></thead>
    <tbody>
    <?php foreach ($entries as $e): ?>
    <tr>
      <td class="mono"><?= htmlspecialchars($e['phone']) ?></td>
      <td>
        <?php
        $badge = ['STOP'=>'badge-red','manual'=>'badge-gray','import'=>'badge-yellow'];
        $reason = $e['reason'] ?? 'manual';
        echo '<span class="badge '.($badge[$reason]??'badge-gray').'">'.htmlspecialchars($reason).'</span>';
        ?>
      </td>
      <td style="font-size:.78rem;color:var(--text-muted)"><?= date('d.m.Y H:i', strtotime($e['created_at'])) ?></td>
      <td>
        <button class="btn btn-danger btn-sm"
                data-confirm-delete="Stergi numarul <?= htmlspecialchars($e['phone']) ?> din blacklist?"
                data-url="/optout/<?= $e['id'] ?>/delete"
                style="padding:3px 7px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($total > 50): ?>
  <div class="pagination">
    <?php $pages = ceil($total/50); for ($p=max(1,$page-3);$p<=min($pages,$page+3);$p++): ?>
      <a href="?page=<?=$p?><?=$search?'&q='.urlencode($search):''?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:18px">
  <div class="card-header"><div class="card-title">URL Opt-out (STOP link in mesaje)</div></div>
  <div style="font-size:.83rem;color:var(--text-secondary);margin-bottom:10px">
    Adauga acest text in mesajele tale pentru a permite destinatarilor sa se dezaboneze:
    <code style="display:block;margin-top:8px;background:var(--bg-elevated);padding:8px 12px;border-radius:var(--radius-sm);font-size:.78rem;border-left:3px solid var(--accent-border)">
      Stop: <?= BASE_URL ?>/stop?phone={telefon}&c=CLIENT_ID&t=TOKEN
    </code>
    Variabila <code>{telefon}</code> este inlocuita automat la trimitere. Foloseste variabila <code>{optout_url}</code> in mesaj si platforma o va genera automat.
  </div>
</div>

<!-- Modal adauga numere -->
<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Adauga numere in blacklist</div>
      <button class="modal-close" data-modal-close><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="form-group">
      <label class="form-label">Numere (unul per rand sau separate prin virgula)</label>
      <textarea id="add-phones" class="form-textarea" style="min-height:120px" placeholder="0712345678&#10;0723456789&#10;..."></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Motiv</label>
      <select id="add-reason" class="form-select">
        <option value="manual">Manual</option>
        <option value="STOP">Cerere STOP</option>
        <option value="import">Import</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-modal-close>Anuleaza</button>
      <button class="btn btn-primary" id="btn-add-optout">Adauga in blacklist</button>
    </div>
  </div>
</div>

<!-- Modal import CSV -->
<div class="modal-overlay" id="import-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Import blacklist din CSV</div>
      <button class="modal-close" data-modal-close><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:14px">Fisier CSV cu o coloana: numere de telefon, cate unul per rand.</p>
    <form id="import-optout-form" enctype="multipart/form-data">
      <?= Auth::csrfField() ?>
      <div class="dropzone" id="optout-dropzone" style="padding:24px">
        <div style="font-size:1.5rem;margin-bottom:8px">📁</div>
        <p style="font-size:.83rem">Click sau trage fisierul CSV</p>
        <input type="file" id="optout-csv" name="csv_file" accept=".csv,.txt" style="display:none">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Anuleaza</button>
        <button type="submit" class="btn btn-primary">Importa</button>
      </div>
    </form>
  </div>
</div>

<script>
const CSRF_V = document.querySelector('meta[name="csrf-token"]').content;
const CSRF_N = document.querySelector('meta[name="csrf-name"]').content;

document.getElementById('btn-add-optout').addEventListener('click', async function() {
  const phones = document.getElementById('add-phones').value.trim();
  const reason = document.getElementById('add-reason').value;
  if (!phones) { Toast.error('Introduceti cel putin un numar'); return; }
  this.disabled = true; this.textContent = 'Se adauga...';
  const fd = new FormData();
  fd.append(CSRF_N, CSRF_V);
  fd.append('phones', phones);
  fd.append('reason', reason);
  try {
    const data = await fetch('/optout/add', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(r=>r.json());
    if (data.success) {
      Toast.success(`Adaugate: ${data.added}, sarite: ${data.skipped}`);
      Modal.closeAll();
      setTimeout(() => location.reload(), 800);
    } else { Toast.error(data.error); }
  } catch(e) { Toast.error(e.message); }
  finally { this.disabled = false; this.textContent = 'Adauga in blacklist'; }
});

document.getElementById('optout-dropzone').addEventListener('click', () => document.getElementById('optout-csv').click());
document.getElementById('optout-csv').addEventListener('change', function() {
  document.getElementById('optout-dropzone').querySelector('p').textContent = this.files[0]?.name ?? '';
});

document.getElementById('import-optout-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = this.querySelector('[type=submit]');
  btn.disabled = true; btn.textContent = 'Se importa...';
  const fd = new FormData(this);
  try {
    const data = await fetch('/optout/import', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} }).then(r=>r.json());
    if (data.success) {
      Toast.success(`Importate: ${data.added}, sarite: ${data.skipped}`);
      Modal.closeAll();
      setTimeout(() => location.reload(), 1000);
    } else { Toast.error(data.error); }
  } catch(e) { Toast.error(e.message); }
  finally { btn.disabled = false; btn.textContent = 'Importa'; }
});
</script>
<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
