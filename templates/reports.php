<?php
$pageTitle  = 'Rapoarte';
$breadcrumb = [['Dashboard','/dashboard'],['Rapoarte',null]];
$chartLabels = []; $chartTotal = []; $chartDelivered = []; $chartFailed = [];
foreach ($daily as $row) {
  $chartLabels[]    = date('d M', strtotime($row['day']));
  $chartTotal[]     = (int)$row['total'];
  $chartDelivered[] = (int)$row['delivered'];
  $chartFailed[]    = (int)$row['failed'];
}
$pending = (int)($summary['pending'] ?? 0);
ob_start();
?>

<!-- Filtre + cautare -->
<form method="GET" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:20px;flex-wrap:wrap">
  <div class="form-group" style="margin-bottom:0">
    <label class="form-label">De la</label>
    <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-input" style="width:148px">
  </div>
  <div class="form-group" style="margin-bottom:0">
    <label class="form-label">Pana la</label>
    <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>" class="form-input" style="width:148px">
  </div>
  <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    Filtreaza
  </button>
  <div style="margin-left:auto;display:flex;gap:8px;align-items:flex-end">
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label">Cauta numar</label>
      <input type="text" id="contact-search" class="form-input" placeholder="+40712345678" style="width:170px"
             onkeydown="if(event.key==='Enter'){const v=this.value.trim();if(v)location.href='/reports/contact?phone='+encodeURIComponent(v);}">
    </div>
    <button type="button" class="btn btn-secondary btn-sm" style="align-self:flex-end"
            onclick="const v=document.getElementById('contact-search').value.trim();if(v)location.href='/reports/contact?phone='+encodeURIComponent(v)">
      Istoricul numarului
    </button>
  </div>
</form>

<?php if (($syncableCount ?? 0) > 0): ?>
<!-- Banner sync — apare cand exista mesaje in tranzit sau fara cost -->
<div style="background:var(--blue-subtle);border:1px solid var(--blue-border);border-radius:var(--radius);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  <span style="font-size:.83rem;color:var(--blue);flex:1">
    <strong><?= $syncableCount ?></strong> mesaje cu status sau cost neactualizat
    <?php if ($pending > 0): ?>(<?= $pending ?> inca in tranzit)<?php endif; ?>.
    Apasa <strong>Sync acum</strong> pentru a prelua statusul real din sendsms.ro.
  </span>
  <button class="btn btn-sm" id="btn-sync-costs"
          style="background:var(--blue);color:#fff;border-color:var(--blue);flex-shrink:0">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.68"/></svg>
    Sync acum
  </button>
</div>
<?php endif; ?>

<!-- Stat cards -->
<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-label">Total trimise</div>
    <div class="stat-value"><?= number_format((int)($summary['total']??0)) ?></div>
    <div class="stat-sub"><?= htmlspecialchars($dateFrom) ?> &mdash; <?= htmlspecialchars($dateTo) ?></div>
    <div class="stat-icon accent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Livrate</div>
    <div class="stat-value text-green"><?= number_format((int)($summary['delivered']??0)) ?></div>
    <div class="stat-sub"><?= ($summary['total']??0) > 0 ? round(($summary['delivered']??0)/($summary['total']??1)*100,1).'% rata livrare' : '0%' ?></div>
    <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">
      Esuate
      <?php if ($pending > 0): ?>
        <span class="badge badge-yellow" style="margin-left:6px;font-size:.65rem"><?= $pending ?> pending</span>
      <?php endif; ?>
    </div>
    <div class="stat-value text-red"><?= number_format((int)($summary['failed']??0)) ?></div>
    <?php if (($summary['failed']??0) > 0): ?>
    <div class="stat-sub" style="margin-top:5px">
      <button class="btn btn-danger btn-sm" style="padding:2px 8px;font-size:.7rem" id="btn-delete-failed">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="10" height="10"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
        Sterge esuate
      </button>
    </div>
    <?php endif; ?>
    <div class="stat-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Cost total (EUR)</div>
    <div class="stat-value"><?= number_format((float)($summary['total_cost']??0),4) ?></div>
    <div class="stat-sub"><?= number_format((int)($summary['total_parts']??0)) ?> SMS-parts &middot; EUR</div>
    <div class="stat-icon yellow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
  </div>
</div>

<!-- Grafic activitate -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div class="card-title">Activitate zilnica</div>
    <span style="font-size:.77rem;color:var(--text-muted)"><?= htmlspecialchars($dateFrom) ?> &rarr; <?= htmlspecialchars($dateTo) ?></span>
  </div>
  <?php if (empty($daily)): ?>
    <div class="table-empty" style="padding:40px">Nicio activitate in perioada selectata.</div>
  <?php else: ?>
    <div class="chart-wrap" style="height:220px"><canvas id="chart-daily"></canvas></div>
  <?php endif; ?>
</div>

<!-- Performanta campanii -->
<?php if (!empty($byCampaign)): ?>
<div class="card">
  <div class="card-header"><div class="card-title">Performanta pe campanii</div></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Campanie</th><th>Trimise</th><th>Livrate</th><th>Rata livrare</th><th>Cost (EUR)</th></tr></thead>
    <tbody>
    <?php foreach ($byCampaign as $row):
      $pct = (int)$row['total'] > 0 ? round((int)$row['delivered'] / (int)$row['total'] * 100) : 0;
    ?>
      <tr>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td class="mono"><?= number_format((int)$row['total']) ?></td>
        <td class="mono"><?= number_format((int)$row['delivered']) ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="progress-wrap" style="width:70px">
              <div class="progress-bar <?= $pct>=80?'green':($pct<40?'red':'') ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <span class="mono" style="font-size:.77rem"><?= $pct ?>%</span>
          </div>
        </td>
        <td class="mono"><?= ($row['cost']??0) > 0 ? number_format((float)$row['cost'],4) : '&mdash;' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<script>
const REPORT_FROM = '<?= htmlspecialchars($dateFrom) ?>';
const REPORT_TO   = '<?= htmlspecialchars($dateTo) ?>';
const CSRF_VAL    = document.querySelector('meta[name="csrf-token"]').content;
const CSRF_NAME   = document.querySelector('meta[name="csrf-name"]').content;

// Sterge mesaje esuate
document.getElementById('btn-delete-failed')?.addEventListener('click', async function() {
  if (!confirm('Stergi toate mesajele esuate din aceasta perioada?')) return;
  this.disabled = true; this.textContent = 'Se sterge...';
  const fd = new FormData();
  fd.append(CSRF_NAME, CSRF_VAL);
  fd.append('from', REPORT_FROM);
  fd.append('to',   REPORT_TO);
  try {
    const data = await fetch('/reports/delete-failed', {
      method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}
    }).then(r => r.json());
    if (data.success) {
      Toast.success(`Sterse ${data.deleted} mesaje esuate`);
      setTimeout(() => location.reload(), 800);
    } else {
      Toast.error(data.error || 'Eroare');
      this.disabled = false; this.textContent = 'Sterge esuate';
    }
  } catch(e) { Toast.error(e.message); this.disabled = false; }
});

// Sync status + costuri
document.getElementById('btn-sync-costs')?.addEventListener('click', async function() {
  this.disabled = true;
  this.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" style="animation:spin 1s linear infinite"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.68"/></svg> Se sincronizeaza...';

  // Adauga animatie spin
  const style = document.createElement('style');
  style.textContent = '@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}';
  document.head.appendChild(style);

  const fd = new FormData();
  fd.append(CSRF_NAME, CSRF_VAL);
  try {
    const data = await fetch('/reports/sync-costs', {
      method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}
    }).then(r => r.json());

    if (data.success) {
      let msg = `Verificate: ${data.checked} mesaje`;
      if (data.updated_status > 0) msg += ` · ${data.updated_status} statusuri actualizate`;
      if (data.synced_cost   > 0) msg += ` · ${data.synced_cost} costuri sincronizate`;
      if (data.updated_status === 0 && data.synced_cost === 0) msg = 'Nicio schimbare — statusurile sunt la zi';
      Toast.success(msg, 6000);
      setTimeout(() => location.reload(), 1500);
    } else {
      Toast.error(data.error || 'Eroare la sincronizare');
      this.disabled = false;
      this.textContent = 'Sync acum';
    }
  } catch(e) {
    Toast.error(e.message);
    this.disabled = false;
    this.textContent = 'Sync acum';
  }
});
</script>

<?php
$content = ob_get_clean();
$extraScripts = !empty($daily) ? '<script>
document.addEventListener("DOMContentLoaded", function() {
  initLineChart("chart-daily",
    ' . json_encode($chartLabels) . ',
    [
      {label:"Trimise", data:' . json_encode($chartTotal) . ',     borderColor:"#4f46e5", backgroundColor:"rgba(79,70,229,.07)"},
      {label:"Livrate", data:' . json_encode($chartDelivered) . ', borderColor:"#16a34a", backgroundColor:"rgba(22,163,74,.06)"},
      {label:"Esuate",  data:' . json_encode($chartFailed) . ',    borderColor:"#dc2626", backgroundColor:"rgba(220,38,38,.04)", fill:false}
    ]
  );
});
</script>' : '';
require ROOT . '/templates/layout.php';
