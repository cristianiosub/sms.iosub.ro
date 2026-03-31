<?php
$pageTitle  = 'Jurnal activitate';
$breadcrumb = [['Admin',null],['Jurnal',null]];
ob_start();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Jurnal activitate (<?= number_format($total) ?> intrari)</div>
    <form method="GET" style="display:flex;gap:6px">
      <input type="text" name="filter" value="<?= htmlspecialchars($filter) ?>" class="form-input" style="width:200px" placeholder="Filtreaza actiune...">
      <button type="submit" class="btn btn-secondary btn-sm">Cauta</button>
      <?php if ($filter): ?><a href="/admin/logs" class="btn btn-ghost btn-sm">&#x2715;</a><?php endif; ?>
    </form>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Data</th><th>Utilizator</th><th>Client</th><th>Actiune</th><th>Entitate</th><th>IP</th></tr></thead>
    <tbody>
    <?php if (empty($logs)): ?>
      <tr><td colspan="6" class="table-empty">Nicio intrare gasita.</td></tr>
    <?php else: foreach ($logs as $l): ?>
    <tr>
      <td style="font-size:.77rem;white-space:nowrap;color:var(--text-muted)"><?= date('d.m.Y H:i:s', strtotime($l['created_at'])) ?></td>
      <td style="font-size:.82rem">
        <?php if ($l['full_name']): ?>
          <div style="font-weight:600"><?= htmlspecialchars($l['full_name']) ?></div>
          <div style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($l['email'] ?? '') ?></div>
        <?php else: ?><span style="color:var(--text-muted)">sistem</span><?php endif; ?>
      </td>
      <td style="font-size:.78rem;color:var(--text-secondary)"><?= htmlspecialchars($l['client_name'] ?? '&mdash;') ?></td>
      <td>
        <?php
        $actionColors = [
          'login'=>'badge-blue','logout'=>'badge-gray',
          'send_campaign'=>'badge-accent','quick_sms'=>'badge-accent',
          'create_campaign'=>'badge-green','delete_campaign'=>'badge-red',
          'add_optout'=>'badge-yellow','import_optout'=>'badge-yellow',
          'create_client'=>'badge-green','update_client'=>'badge-blue',
          'create_user'=>'badge-green','change_password'=>'badge-yellow',
          'toggle_2fa'=>'badge-yellow',
        ];
        $col = $actionColors[$l['action']] ?? 'badge-gray';
        ?>
        <span class="badge <?= $col ?>"><?= htmlspecialchars($l['action']) ?></span>
      </td>
      <td style="font-size:.77rem;color:var(--text-muted)">
        <?php if ($l['entity_type']): ?>
          <?= htmlspecialchars($l['entity_type']) ?>#<?= htmlspecialchars($l['entity_id'] ?? '') ?>
        <?php else: ?>&mdash;<?php endif; ?>
      </td>
      <td class="mono" style="font-size:.72rem;color:var(--text-muted)"><?= htmlspecialchars($l['ip'] ?? '') ?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table></div>

  <?php if ($total > 50): ?>
  <div class="pagination">
    <?php $pages = ceil($total/50); for ($p=max(1,$page-3); $p<=min($pages,$page+3); $p++): ?>
      <a href="?page=<?=$p?><?=$filter?'&filter='.urlencode($filter):''?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
