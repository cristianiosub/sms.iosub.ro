<?php
$pageTitle  = htmlspecialchars($campaign['name']);
$breadcrumb = [['Dashboard','/dashboard'],['Campanii','/campaigns'],[$campaign['name'],null]];
$statusColors = ['draft'=>'gray','scheduled'=>'yellow','running'=>'blue','paused'=>'yellow','completed'=>'green','failed'=>'red'];
$statusLabels = ['draft'=>'Draft','scheduled'=>'Programat','running'=>'Se trimite','paused'=>'Pauzat','completed'=>'Finalizat','failed'=>'Esuat'];
$sc = $campaign['status'];
$topbarActions = '';

// Buton Export CSV mereu vizibil cand exista mesaje
if ($totalMsg > 0) {
  $topbarActions .= '<a href="/campaigns/' . $campaign['id'] . '/export" class="btn btn-secondary btn-sm">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Export CSV
  </a>';
}
if (in_array($sc, ['draft','paused','scheduled'])) {
  $topbarActions .= '<button class="btn btn-primary btn-sm" id="btn-send-campaign">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    Trimite acum
  </button>';
}
if ($sc === 'running') {
  $topbarActions .= '<button class="btn btn-secondary btn-sm" id="btn-pause-campaign">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
    Pauza
  </button>';
}
ob_start();
?>

<?php if ($sc === 'running'): ?>
<div class="card" style="margin-bottom:18px" id="progress-card">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
    <span class="fw-600">Trimitere in progres...</span>
    <span id="progress-pct" class="mono text-accent">0%</span>
  </div>
  <div class="progress-wrap"><div class="progress-bar" id="progress-bar" style="width:0%"></div></div>
  <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.77rem;color:var(--text-muted)">
    <span>Trimisi: <strong id="prog-sent">0</strong></span>
    <span>Livrate: <strong id="prog-delivered">0</strong></span>
    <span>Esuate: <strong id="prog-failed">0</strong></span>
    <span>Total: <strong><?= number_format((int)$campaign['total_recipients']) ?></strong></span>
  </div>
</div>
<?php endif; ?>

<div class="grid-2" style="margin-bottom:18px;align-items:start">
<div class="card">
  <div class="card-header"><div class="card-title">Detalii campanie</div></div>
  <div style="display:grid;grid-template-columns:130px 1fr;gap:9px 14px;font-size:.84rem">
    <span style="color:var(--text-muted)">Status</span>
    <span><span class="badge badge-<?= $statusColors[$sc]??'gray' ?>"><?= $statusLabels[$sc]??$sc ?></span></span>
    <span style="color:var(--text-muted)">Sender</span>
    <span class="mono"><?= htmlspecialchars($campaign['sender'] ?: '&mdash;') ?></span>
    <span style="color:var(--text-muted)">Provider</span>
    <span class="badge badge-<?= ($campaignProvider??'sendsms')==='smsapi'?'accent':'gray' ?>"><?= htmlspecialchars($campaignProvider??'sendsms') ?></span>
    <span style="color:var(--text-muted)">Creat</span>
    <span><?= date('d.m.Y H:i', strtotime($campaign['created_at'])) ?></span>
    <?php if ($campaign['scheduled_at']): ?>
    <span style="color:var(--text-muted)">Programat</span>
    <span><?= date('d.m.Y H:i', strtotime($campaign['scheduled_at'])) ?></span>
    <?php endif; ?>
    <?php if ($campaign['started_at']): ?>
    <span style="color:var(--text-muted)">Pornit</span>
    <span><?= date('d.m.Y H:i', strtotime($campaign['started_at'])) ?></span>
    <?php endif; ?>
    <?php if ($campaign['completed_at']): ?>
    <span style="color:var(--text-muted)">Finalizat</span>
    <span><?= date('d.m.Y H:i', strtotime($campaign['completed_at'])) ?></span>
    <?php endif; ?>
    <?php if ($campaign['batch_size'] > 0): ?>
    <span style="color:var(--text-muted)">Rate limit</span>
    <span><?= $campaign['batch_size'] ?> SMS / <?= $campaign['batch_interval'] ?>s</span>
    <?php endif; ?>
    <span style="color:var(--text-muted)">Mesaj</span>
    <div class="msg-bubble"><?= nl2br(htmlspecialchars($campaign['message_text'])) ?></div>
  </div>
</div>
<div class="card">
  <div class="card-header"><div class="card-title">Statistici</div></div>
  <div class="stats-grid" style="grid-template-columns:1fr 1fr;gap:10px">
    <?php
    $total_r  = (int)$campaign['total_recipients'];
    $sent     = (int)$campaign['sent_count'];
    $deliv    = (int)$campaign['delivered_count'];
    $fail     = (int)$campaign['failed_count'];
    $delivPct = $sent > 0 ? round($deliv/$sent*100) : 0;
    $cost     = (float)($msgStats['total_cost'] ?? 0);
    ?>
    <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
      <div class="stat-label">Destinatari</div><div class="stat-value"><?= number_format($total_r) ?></div>
    </div>
    <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
      <div class="stat-label">Trimisi</div><div class="stat-value text-blue"><?= number_format($sent) ?></div>
    </div>
    <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
      <div class="stat-label">Livrate</div><div class="stat-value text-green"><?= number_format($deliv) ?></div>
      <div class="stat-sub"><?= $delivPct ?>% din trimisi</div>
    </div>
    <div style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:var(--radius);padding:14px">
      <div class="stat-label">Cost (EUR)</div>
      <div class="stat-value <?= $cost > 0 ? '' : 'text-muted' ?>"><?= $cost > 0 ? number_format($cost,4) : '&mdash;' ?></div>
    </div>
  </div>
</div>
</div>

<!-- Tabel mesaje cu paginare -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Mesaje trimise (<?= number_format($totalMsg) ?> total)</div>
    <?php if ($totalMsg > 100): ?>
    <div style="font-size:.78rem;color:var(--text-muted)">
      Pagina <?= $page ?> / <?= ceil($totalMsg/100) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if (empty($messages)): ?>
    <div class="table-empty">Niciun mesaj trimis inca.</div>
  <?php else:
  $sm = [0=>'gray',1=>'blue',4=>'green',8=>'blue',16=>'red',32=>'red',64=>'yellow'];
  $lm = [0=>'Pending',1=>'Trimis',4=>'Livrat',8=>'La retea',16=>'Esuat retea',32=>'Esuat',64=>'Filtrat'];
  ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Telefon</th><th>Mesaj trimis</th><th>Status</th><th>Cost</th><th>Trimis</th><th>Livrat</th></tr></thead>
    <tbody>
    <?php foreach ($messages as $m): $st=(int)$m['status']; ?>
      <tr>
        <td class="mono"><a href="/reports/contact?phone=<?= urlencode($m['phone']) ?>" style="font-size:.82rem"><?= htmlspecialchars($m['phone']) ?></a></td>
        <td><div class="msg-bubble"><?= nl2br(htmlspecialchars($m['message_text'] ?? '')) ?></div></td>
        <td><span class="badge badge-<?= $sm[$st]??'gray' ?>"><?= $lm[$st]??'?' ?></span></td>
        <td class="mono" style="font-size:.77rem;color:var(--text-muted)"><?= $m['cost'] ? number_format((float)$m['cost'],5) : '&mdash;' ?></td>
        <td style="font-size:.77rem;color:var(--text-muted)"><?= $m['sent_at'] ? date('d.m H:i', strtotime($m['sent_at'])) : '&mdash;' ?></td>
        <td style="font-size:.77rem;color:var(--text-muted)"><?= $m['delivered_at'] ? date('d.m H:i', strtotime($m['delivered_at'])) : '&mdash;' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($totalMsg > 100): ?>
  <div class="pagination">
    <?php $pages = ceil($totalMsg/100); for ($p=max(1,$page-3);$p<=min($pages,$page+3);$p++): ?>
      <a href="?page=<?=$p?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<style>
.msg-bubble{background:var(--bg-elevated);border:1px solid var(--border);border-left:3px solid var(--accent-border);border-radius:var(--radius-sm);padding:6px 10px;font-size:.81rem;color:var(--text-primary);line-height:1.5;white-space:pre-wrap;word-break:break-word;max-width:360px}
</style>

<script>
const CAMPAIGN_ID     = <?= (int)$campaign['id'] ?>;
const CAMPAIGN_STATUS = '<?= $sc ?>';

document.getElementById('btn-send-campaign')?.addEventListener('click', async function() {
  if (!confirm('Trimiti campania catre toti destinatarii?')) return;
  this.disabled = true; this.textContent = 'Se trimite...';
  try {
    const fd = new FormData();
    fd.append('<?= CSRF_TOKEN_NAME ?>', document.querySelector('meta[name="csrf-token"]').content);
    const data = await fetch(`/campaigns/${CAMPAIGN_ID}/send`, {
      method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'}
    }).then(r => r.json());
    if (data.success) {
      Toast.success(`Trimis: ${data.sent}, Esuate: ${data.failed}${data.blocked?' ('+data.blocked+' blacklist)':''}`);
      setTimeout(() => location.reload(), 1200);
    } else { Toast.error(data.error || 'Eroare'); this.disabled = false; this.textContent = 'Trimite acum'; }
  } catch(e) { Toast.error(e.message); this.disabled = false; }
});

document.getElementById('btn-pause-campaign')?.addEventListener('click', async function() {
  const fd = new FormData();
  fd.append('<?= CSRF_TOKEN_NAME ?>', document.querySelector('meta[name="csrf-token"]').content);
  await fetch(`/campaigns/${CAMPAIGN_ID}/pause`, {method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
  Toast.info('Campanie pauzata');
  setTimeout(() => location.reload(), 800);
});

if (CAMPAIGN_STATUS === 'running') {
  pollCampaign(CAMPAIGN_ID, (data) => {
    const c = data.campaign;
    if (!c) return;
    document.getElementById('prog-sent').textContent      = c.sent_count.toLocaleString();
    document.getElementById('prog-delivered').textContent = c.delivered_count.toLocaleString();
    document.getElementById('prog-failed').textContent    = c.failed_count.toLocaleString();
    document.getElementById('progress-bar').style.width   = data.progress_pct + '%';
    document.getElementById('progress-pct').textContent   = data.progress_pct + '%';
    if (c.status !== 'running') setTimeout(() => location.reload(), 1500);
  });
}
</script>

<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
