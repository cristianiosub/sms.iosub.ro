<?php
$pageTitle   = 'Dashboard';
$breadcrumb  = [['Dashboard', null]];
$topbarActions = '<a href="/quick-sms" class="btn btn-primary btn-sm">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
  Quick SMS
</a>';

$chartLabels = []; $chartDelivered = []; $chartTotal = []; $dateMap = [];
foreach ($chartData as $row) { $dateMap[$row['day']] = $row; }
$today = new DateTime();
for ($i = 29; $i >= 0; $i--) {
  $d   = (clone $today)->modify("-$i days")->format('Y-m-d');
  $lbl = (clone $today)->modify("-$i days")->format('d M');
  $chartLabels[]    = $lbl;
  $chartTotal[]     = isset($dateMap[$d]) ? (int)$dateMap[$d]['total'] : 0;
  $chartDelivered[] = isset($dateMap[$d]) ? (int)$dateMap[$d]['delivered'] : 0;
}
ob_start();
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Total SMS trimise</div>
    <div class="stat-value"><?= number_format((int)($stats['total_messages'] ?? 0)) ?></div>
    <div class="stat-sub">Toate timpurile</div>
    <div class="stat-icon accent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Livrate</div>
    <div class="stat-value text-green"><?= number_format((int)($stats['delivered'] ?? 0)) ?></div>
    <div class="stat-sub"><?= $stats['total_messages'] > 0 ? round($stats['delivered'] / $stats['total_messages'] * 100) : 0 ?>% rata livrare</div>
    <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Azi</div>
    <div class="stat-value"><?= number_format((int)($statsToday['total'] ?? 0)) ?></div>
    <div class="stat-sub"><?= number_format((int)($statsToday['delivered'] ?? 0)) ?> livrate azi</div>
    <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Cost luna curenta</div>
    <div class="stat-value"><?= number_format((float)($statsMonth['cost'] ?? 0), 4) ?></div>
    <div class="stat-sub"><?= number_format((int)($statsMonth['total'] ?? 0)) ?> SMS-uri &middot; EUR</div>
    <div class="stat-icon yellow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
  </div>
</div>

<div class="grid-2" style="margin-bottom:18px;align-items:start">
  <div class="card">
    <div class="card-header"><div><div class="card-title">Activitate 30 zile</div><div class="card-subtitle">SMS-uri trimise si livrate</div></div></div>
    <div class="chart-wrap"><canvas id="chart-activity"></canvas></div>
  </div>
  <div class="card">
    <div class="card-header"><div><div class="card-title">Status livrare</div><div class="card-subtitle">Distributie mesaje totale</div></div></div>
    <div class="chart-wrap"><canvas id="chart-status"></canvas></div>
  </div>
</div>

<div class="grid-2" style="align-items:start">
<div class="card">
  <div class="card-header">
    <div class="card-title">Campanii recente</div>
    <a href="/campaigns" class="btn btn-ghost btn-sm">Vezi toate &rarr;</a>
  </div>
  <?php if (empty($recentCampaigns)): ?>
    <div class="table-empty">Nicio campanie inca. <a href="/campaigns/new">Creeaza prima campanie</a></div>
  <?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Campanie</th><th>Status</th><th>Trimisi</th><th>Livrat%</th></tr></thead>
    <tbody>
    <?php foreach ($recentCampaigns as $c):
      $bmap = ['draft'=>'gray','scheduled'=>'yellow','running'=>'blue','paused'=>'yellow','completed'=>'green','failed'=>'red'];
      $blbl = ['draft'=>'Draft','scheduled'=>'Programat','running'=>'Se trimite','paused'=>'Pauzat','completed'=>'Finalizat','failed'=>'Esuat'];
      $sc = $c['status'];
    ?>
      <tr>
        <td><a href="/campaigns/<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></td>
        <td><span class="badge badge-<?= $bmap[$sc]??'gray' ?>"><?= $blbl[$sc]??$sc ?></span></td>
        <td class="mono"><?= number_format((int)$c['sent_count']) ?></td>
        <td><?= $c['sent_count'] > 0 ? round($c['delivered_count']/$c['sent_count']*100).'%' : '&mdash;' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Mesaje recente</div>
    <a href="/reports" class="btn btn-ghost btn-sm">Rapoarte &rarr;</a>
  </div>
  <?php if (empty($recentMessages)): ?>
    <div class="table-empty">Niciun mesaj trimis inca.</div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Telefon &amp; Mesaj</th>
        <th style="width:90px">Status</th>
        <th style="width:75px">Cost</th>
        <th style="width:80px">Data</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $sm = [0=>'gray',1=>'blue',4=>'green',8=>'blue',16=>'red',32=>'red',64=>'yellow'];
    $lm = [0=>'Pending',1=>'Trimis',4=>'Livrat',8=>'La retea',16=>'Esuat',32=>'Esuat',64=>'Filtrat'];
    foreach ($recentMessages as $m):
      $st = (int)$m['status'];
      $txt = $m['message_text'] ?? '';
    ?>
      <tr>
        <td>
          <div style="font-family:var(--font-mono);font-size:.8rem;font-weight:700;color:var(--text-primary);margin-bottom:5px">
            <?= htmlspecialchars($m['phone']) ?>
          </div>
          <div class="msg-bubble"><?= htmlspecialchars($txt) ?></div>
        </td>
        <td><span class="badge badge-<?= $sm[$st]??'gray' ?>"><?= $lm[$st]??'?' ?></span></td>
        <td style="font-family:var(--font-mono);font-size:.76rem;color:var(--text-muted)"><?= $m['cost'] ? number_format((float)$m['cost'],5) : '&mdash;' ?></td>
        <td style="font-size:.76rem;color:var(--text-muted);white-space:nowrap"><?= date('d.m H:i', strtotime($m['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
</div>

<style>
.msg-bubble {
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  border-left: 3px solid var(--accent-border);
  border-radius: var(--radius-sm);
  padding: 6px 10px;
  font-size: .81rem;
  color: var(--text-secondary);
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>

<?php
$content = ob_get_clean();
$extraScripts = '<script>
document.addEventListener("DOMContentLoaded", function() {
  initLineChart("chart-activity",
    ' . json_encode($chartLabels) . ',
    [
      { label: "Trimise", data: ' . json_encode($chartTotal) . ', borderColor: "#4f46e5", backgroundColor: "rgba(79,70,229,.07)" },
      { label: "Livrate", data: ' . json_encode($chartDelivered) . ', borderColor: "#16a34a", backgroundColor: "rgba(22,163,74,.06)" }
    ]
  );
  initDoughnutChart("chart-status",
    ["Livrate", "Trimise/Pending", "Esuate"],
    [' . (int)($stats['delivered']??0) . ', ' . (int)($stats['sent']??0) . ', ' . (int)($stats['failed']??0) . '],
    ["#16a34a", "#4f46e5", "#dc2626"]
  );
});
</script>';
require ROOT . '/templates/layout.php';
