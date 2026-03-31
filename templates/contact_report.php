<?php
$pageTitle  = 'Istoricul numarului';
$breadcrumb = [['Rapoarte','/reports'],['Contact',null]];
ob_start();
?>

<div class="card" style="margin-bottom:18px">
  <div style="display:flex;align-items:center;gap:14px">
    <div style="font-size:1.4rem;font-weight:800;font-family:var(--font-mono);color:var(--accent);letter-spacing:-.02em"><?= htmlspecialchars($phone) ?></div>
    <span class="badge badge-<?= empty($messages)?'gray':'blue' ?>"><?= count($messages) ?> mesaje</span>
    <a href="/reports" class="btn btn-ghost btn-sm" style="margin-left:auto">&larr; Inapoi la rapoarte</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><div class="card-title">Toate mesajele trimise</div></div>
  <?php if (empty($messages)): ?>
    <div class="table-empty">Niciun mesaj gasit pentru acest numar.</div>
  <?php else:
  $sm=[0=>'gray',1=>'blue',4=>'green',8=>'blue',16=>'red',32=>'red',64=>'yellow'];
  $lm=[0=>'Pending',1=>'Trimis',4=>'Livrat',8=>'La retea',16=>'Esuat retea',32=>'Esuat',64=>'Filtrat'];
  ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th style="width:120px">Data</th>
        <th style="width:110px">Campanie</th>
        <th style="width:90px">Sender</th>
        <th>Mesaj</th>
        <th style="width:90px">Status</th>
        <th style="width:80px">Cost</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($messages as $m): $st=(int)$m['status']; ?>
      <tr>
        <td style="font-size:.77rem;white-space:nowrap;color:var(--text-muted)"><?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></td>
        <td style="font-size:.77rem;color:var(--text-secondary)"><?= htmlspecialchars($m['campaign_name']??'Quick SMS') ?></td>
        <td class="mono" style="font-size:.77rem"><?= htmlspecialchars($m['sender']??'&mdash;') ?></td>
        <td>
          <div class="msg-bubble"><?= nl2br(htmlspecialchars($m['message_text'] ?? '')) ?></div>
        </td>
        <td><span class="badge badge-<?=$sm[$st]??'gray' ?>"><?=$lm[$st]??'?'?></span></td>
        <td class="mono" style="font-size:.77rem;color:var(--text-muted)"><?= $m['cost'] ? number_format((float)$m['cost'],5) : '&mdash;' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<style>
.msg-bubble {
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  border-left: 3px solid var(--accent-border);
  border-radius: var(--radius-sm);
  padding: 7px 10px;
  font-size: .82rem;
  color: var(--text-primary);
  line-height: 1.55;
  white-space: pre-wrap;
  word-break: break-word;
  max-width: 480px;
}
</style>

<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
