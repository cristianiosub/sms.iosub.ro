<?php
$pageTitle   = 'Campanii';
$breadcrumb  = [['Dashboard','/dashboard'],['Campanii',null]];
$topbarActions = '<a href="/campaigns/new" class="btn btn-primary btn-sm">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  Campanie noua
</a>';
ob_start();
$statusColors = ['draft'=>'gray','scheduled'=>'yellow','running'=>'blue','paused'=>'yellow','completed'=>'green','failed'=>'red'];
$statusLabels = ['draft'=>'Draft','scheduled'=>'Programat','running'=>'Se trimite','paused'=>'Pauzat','completed'=>'Finalizat','failed'=>'Esuat'];
?>

<div class="section-header">
  <div>
    <div class="section-title">Toate campaniile</div>
    <div class="section-sub"><?= $total ?> campanii in total</div>
  </div>
  <div style="display:flex;gap:4px">
    <?php foreach ([''=>'Toate','draft'=>'Draft','scheduled'=>'Programate','running'=>'Active','completed'=>'Finalizate'] as $s => $l): ?>
      <a href="?status=<?= $s ?>" class="btn btn-sm <?= ($status??'')===$s?'btn-secondary':'btn-ghost' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (empty($campaigns)): ?>
  <div class="card" style="text-align:center;padding:60px 20px">
    <p style="color:var(--text-muted);margin-bottom:20px">Nicio campanie gasita.</p>
    <a href="/campaigns/new" class="btn btn-primary">Creeaza prima campanie</a>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Campanie</th><th>Status</th><th>Destinatari</th><th>Trimisi</th><th>Livrat %</th><th>Programat</th><th>Creat</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($campaigns as $c): ?>
        <tr>
          <td>
            <a href="/campaigns/<?= $c['id'] ?>" style="font-weight:600"><?= htmlspecialchars($c['name']) ?></a>
            <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px;font-family:var(--font-mono)"><?= htmlspecialchars(substr($c['message_text'], 0, 60)) ?>...</div>
          </td>
          <td><span class="badge badge-<?= $statusColors[$c['status']]??'gray' ?>"><?= $statusLabels[$c['status']]??$c['status'] ?></span></td>
          <td class="mono"><?= number_format((int)$c['total_recipients']) ?></td>
          <td class="mono"><?= number_format((int)$c['sent_count']) ?></td>
          <td>
            <?php $pct = $c['sent_count'] > 0 ? round($c['delivered_count']/$c['sent_count']*100) : 0; ?>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="progress-wrap" style="width:60px">
                <div class="progress-bar <?= $pct>=80?'green':($pct<40?'red':'') ?>" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="mono" style="font-size:.78rem"><?= $pct ?>%</span>
            </div>
          </td>
          <td style="color:var(--text-muted);font-size:.78rem"><?= $c['scheduled_at'] ? date('d.m.y H:i', strtotime($c['scheduled_at'])) : '&#x2014;' ?></td>
          <td style="color:var(--text-muted);font-size:.78rem"><?= date('d.m.y', strtotime($c['created_at'])) ?></td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="/campaigns/<?= $c['id'] ?>" class="btn btn-ghost btn-sm" title="Vezi">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </a>
              <?php if (!in_array($c['status'], ['running','completed'])): ?>
              <button class="btn btn-ghost btn-sm text-red"
                      data-confirm-delete="Stergi campania «<?= htmlspecialchars(addslashes($c['name'])) ?>»?"
                      data-url="/campaigns/<?= $c['id'] ?>/delete">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total > 20): ?>
  <div class="pagination">
    <?php $totalPages = ceil($total/20); for ($p=1;$p<=$totalPages;$p++): ?>
      <a href="?page=<?= $p ?><?= $status?'&status='.$status:'' ?>" class="page-btn <?= $page===$p?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
