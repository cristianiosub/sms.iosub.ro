<?php
$pageTitle    = 'Admin — Clienti';
$breadcrumb   = [['Admin',null],['Clienti',null]];
$topbarActions = '<a href="/admin/clients/new" class="btn btn-primary btn-sm">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  Client nou
</a>';
ob_start();
?>
<div class="card">
  <div class="card-header"><div class="card-title">Toti clientii (<?= count($clients) ?>)</div></div>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>Nume</th>
        <th>Provider SMS</th>
        <th>Sender implicit</th>
        <th>Alerta sold</th>
        <th>Status</th>
        <th>Creat</th>
        <th style="width:100px"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
    <tr>
      <td>
        <div style="font-weight:700"><?= htmlspecialchars($c['name']) ?></div>
        <div class="mono" style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($c['slug']) ?></div>
      </td>
      <td>
        <?php $prov = $c['sms_provider'] ?? 'sendsms'; ?>
        <?php if ($prov === 'smsapi'): ?>
          <span class="badge badge-accent">smsapi.ro</span>
        <?php elseif ($prov === 'smso'): ?>
          <span class="badge badge-blue">smso.ro</span>
        <?php else: ?>
          <span class="badge badge-gray">sendsms.ro</span>
          <div class="mono" style="font-size:.7rem;color:var(--text-muted);margin-top:3px">
            <?= htmlspecialchars($c['sendsms_username']) ?>
          </div>
        <?php endif; ?>
      </td>
      <td class="mono"><?= htmlspecialchars($c['default_sender'] ?? '&mdash;') ?></td>
      <td style="font-size:.78rem">
        <?php if (!empty($c['alert_email'])): ?>
          <div style="color:var(--text-secondary)"><?= htmlspecialchars($c['alert_email']) ?></div>
          <div style="color:var(--text-muted)">prag: <?= number_format((float)($c['alert_threshold'] ?? 5), 2) ?> EUR</div>
        <?php else: ?>
          <span style="color:var(--text-muted)">—</span>
        <?php endif; ?>
      </td>
      <td><?= $c['is_active']
            ? '<span class="badge badge-green">Activ</span>'
            : '<span class="badge badge-red">Inactiv</span>' ?></td>
      <td style="font-size:.78rem;color:var(--text-muted)"><?= date('d.m.Y', strtotime($c['created_at'])) ?></td>
      <td>
        <a href="/admin/clients/<?= $c['id'] ?>/edit" class="btn btn-secondary btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
          </svg>
          Editeaza
        </a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
