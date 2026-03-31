<?php
$pageTitle    = 'Admin — Utilizatori';
$breadcrumb   = [['Admin',null],['Utilizatori',null]];
$topbarActions = '<a href="/admin/users/new" class="btn btn-primary btn-sm">+ Utilizator nou</a>';
ob_start();
?>
<div class="card">
  <div class="card-header"><div class="card-title">Toti utilizatorii (<?= count($users) ?>)</div></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Nume</th><th>Email</th><th>Rol</th><th>Ultimul login</th><th>Status</th><th>Creat</th></tr></thead>
    <tbody>
    <?php foreach($users as $u): ?>
    <tr>
      <td style="font-weight:600"><?= htmlspecialchars($u['full_name']) ?></td>
      <td class="mono" style="font-size:.83rem"><?= htmlspecialchars($u['email']) ?></td>
      <td><?php
        $rc = ['superadmin'=>'accent','admin'=>'blue','user'=>'gray'];
        echo '<span class="badge badge-'.($rc[$u['role']]??'gray').'">'.ucfirst($u['role']).'</span>';
      ?></td>
      <td style="font-size:.78rem;color:var(--text-muted)"><?= $u['last_login_at'] ? date('d.m.Y H:i',strtotime($u['last_login_at'])) : 'Niciodata' ?></td>
      <td><?= $u['is_active'] ? '<span class="badge badge-green">Activ</span>' : '<span class="badge badge-red">Inactiv</span>' ?></td>
      <td style="font-size:.78rem;color:var(--text-muted)"><?= date('d.m.Y',strtotime($u['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php $content = ob_get_clean(); require ROOT . '/templates/layout.php';
