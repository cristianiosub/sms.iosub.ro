<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars(Auth::csrfToken()) ?>">
<meta name="csrf-name" content="<?= CSRF_TOKEN_NAME ?>">
<script>
function updateCsrf(d){if(d&&d._csrf_token){var m=document.querySelector('meta[name="csrf-token"]');if(m)m.content=d._csrf_token;document.querySelectorAll('input[name="_csrf"]').forEach(function(e){e.value=d._csrf_token;});}}
</script>
<title><?= htmlspecialchars($pageTitle ?? 'SMS Platform') ?> — SMS Platform</title>
<link rel="stylesheet" href="/assets/css/app.css">
<?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>

<div class="app-layout">
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
    </div>
    <div><div class="sidebar-logo-text">SMS<span>Platform</span></div></div>
  </div>

  <?php
  $activeClient = null;
  $clientId = Auth::activeClientId();
  if ($clientId) { $activeClient = DB::fetchOne('SELECT name, sms_provider FROM clients WHERE id=?', [$clientId]); }
  $isSuperAdmin = Auth::isSuperAdmin();
  $layoutUserId = Auth::userId();
  if ($isSuperAdmin) {
      $availableClients = DB::fetchAll('SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name');
  } else {
      $availableClients = DB::fetchAll(
          'SELECT c.id, c.name FROM clients c
           JOIN user_clients uc ON uc.client_id = c.id
           WHERE uc.user_id = ? AND c.is_active = 1 ORDER BY c.name',
          [$layoutUserId]
      );
  }
  $canSwitch = count($availableClients) > 1;
  ?>
  <?php if ($canSwitch): ?>
  <div class="client-switcher" id="client-switcher-btn" role="button" tabindex="0">
    <div class="client-dot"></div>
    <div class="client-name"><?= htmlspecialchars($activeClient['name'] ?? 'Selecteaza client') ?></div>
    <svg class="client-switch-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    <div class="client-dropdown" id="client-dropdown">
      <?php foreach ($availableClients as $c): ?>
        <button class="client-dropdown-item<?= ($c['id'] == $clientId ? ' active' : '') ?>" data-id="<?= (int)$c['id'] ?>">
          <?= htmlspecialchars($c['name']) ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="client-switcher" style="cursor:default;pointer-events:none">
    <div class="client-dot"></div>
    <div class="client-name"><?= htmlspecialchars($activeClient['name'] ?? 'Selecteaza client') ?></div>
  </div>
  <?php endif; ?>

  <nav class="sidebar-nav">
    <?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    function navItem(string $href, string $icon, string $label, string $current, ?string $badge = null): void {
      $active = ($current === $href || ($href !== '/' && str_starts_with($current, $href)));
      echo '<a class="nav-item' . ($active ? ' active' : '') . '" href="' . $href . '">'
         . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' . $icon . '</svg>'
         . '<span>' . $label . '</span>'
         . ($badge ? '<span class="nav-badge">' . $badge . '</span>' : '')
         . '</a>';
    }
    ?>
    <div class="nav-section-label">Principal</div>
    <?php navItem('/dashboard', '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>', 'Dashboard', $currentPath); ?>
    <?php navItem('/quick-sms', '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>', 'Quick SMS', $currentPath); ?>

    <div class="nav-section-label">Campanii</div>
    <?php navItem('/campaigns', '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>', 'Campanii', $currentPath); ?>
    <?php navItem('/lists', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 'Liste Contacte', $currentPath); ?>
    <?php navItem('/templates', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>', 'Template-uri', $currentPath); ?>
    <?php navItem('/optout', '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>', 'Blacklist', $currentPath); ?>

    <div class="nav-section-label">Rapoarte</div>
    <?php navItem('/reports', '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>', 'Rapoarte', $currentPath); ?>

    <div class="nav-section-label">Setari</div>
    <?php navItem('/settings', '<circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M20 12h2M2 12h2M17.66 17.66l1.41 1.41M4.93 19.07l1.41-1.41"/>', 'Setari', $currentPath); ?>
    <?php if (Auth::isSuperAdmin()): ?>
      <?php navItem('/admin/clients', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'Admin Clienti', $currentPath); ?>
      <?php navItem('/admin/users',   '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>', 'Admin Useri', $currentPath); ?>
      <?php navItem('/admin/logs',    '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>', 'Jurnal', $currentPath); ?>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-card">
      <?php
      $u = Auth::user();
      $parts = explode(' ', $u['name'] ?? 'U');
      $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1] ?? '', 0, 1));
      ?>
      <div class="user-avatar"><?= $initials ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($u['name'] ?? '') ?></div>
        <div class="user-role">
          <?= htmlspecialchars(ucfirst($u['role'] ?? '')) ?>
          <?php if (!empty($activeClient['sms_provider']) && $activeClient['sms_provider'] !== 'sendsms'): ?>
            &nbsp;<span style="font-size:.65rem;background:rgba(255,255,255,.12);padding:1px 5px;border-radius:4px;color:rgba(255,255,255,.6)"><?= htmlspecialchars($activeClient['sms_provider']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <form method="POST" action="/logout" style="display:inline">
        <?= Auth::csrfField() ?>
        <button type="submit" class="user-logout" title="Deconectare" style="background:none;border:none;cursor:pointer;padding:0">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </form>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="main-content">
  <header class="topbar">
    <button id="sidebar-toggle" class="btn btn-ghost btn-sm sidebar-toggle-btn">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div>
      <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></div>
      <?php if (!empty($breadcrumb)): ?>
        <div class="topbar-breadcrumb">
          <?php foreach ($breadcrumb as $i => [$label, $href]): ?>
            <?php if ($i > 0): ?><span>&#x203A;</span><?php endif; ?>
            <?php if ($href): ?><a href="<?= $href ?>"><?= htmlspecialchars($label) ?></a>
            <?php else: ?><span style="color:var(--text-secondary)"><?= htmlspecialchars($label) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="topbar-actions">
      <div style="font-size:.78rem;color:var(--text-muted)">
        Sold: <strong id="balance-value" style="color:var(--green)">...</strong>
      </div>
      <?php if (!empty($topbarActions)) echo $topbarActions; ?>
    </div>
  </header>

  <main class="page-body">
    <?php if (!empty($content)) echo $content; ?>
  </main>
</div>
</div>

<div id="toast-container"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"
        integrity="sha512-CQBWl4fJHWbryGE+Pc7UAxWMUMNMWzWxF4SQo9CgkJIN1kx6djDQZjh3Y8SZ1d+6I+1zze6Z7kHXO7q3UyZAWw=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="/assets/js/app.js"></script>
<script>
(function(){
  var toggle  = document.getElementById('sidebar-toggle');
  var sidebar = document.querySelector('.sidebar');
  var overlay = document.getElementById('sidebar-overlay');
  if (!toggle || !sidebar || !overlay) return;
  function openSidebar(){ sidebar.classList.add('open'); overlay.classList.add('open'); }
  function closeSidebar(){ sidebar.classList.remove('open'); overlay.classList.remove('open'); }
  toggle.addEventListener('click', function(){ sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); });
  overlay.addEventListener('click', closeSidebar);
  sidebar.querySelectorAll('.nav-item').forEach(function(a){ a.addEventListener('click', closeSidebar); });
})();
</script>
<?php if (isset($extraScripts)) echo $extraScripts; ?>
<?php if ($canSwitch ?? false): ?>
<script>
(function(){
  var btn = document.getElementById('client-switcher-btn');
  var dd  = document.getElementById('client-dropdown');
  if (!btn || !dd) return;
  function toggleDropdown(){ dd.classList.toggle('open'); btn.classList.toggle('open'); }
  function closeDropdown(){ dd.classList.remove('open'); btn.classList.remove('open'); }
  btn.addEventListener('click', function(e){ e.stopPropagation(); toggleDropdown(); });
  btn.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); toggleDropdown(); }});
  document.addEventListener('click', function(){ closeDropdown(); });
  dd.querySelectorAll('.client-dropdown-item').forEach(function(item){
    item.addEventListener('click', function(e){
      e.stopPropagation();
      closeDropdown();
      var id   = this.dataset.id;
      var meta = document.querySelector('meta[name="csrf-token"]');
      var fd   = new FormData();
      fd.append('client_id', id);
      fd.append('_csrf', meta ? meta.content : '');
      fetch('/api/switch-client',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
        .then(function(r){return r.text();})
        .then(function(t){
          var d; try{d=JSON.parse(t);}catch(e){d={error:'Raspuns invalid ('+t.substring(0,120)+')'};}
          updateCsrf(d);
          if(d.success){ location.reload(); }
          else{ alert(d.error||'Eroare necunoscuta'); }
        }).catch(function(err){ alert('Eroare retea: '+err); });
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
