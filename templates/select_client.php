<?php // select_client.php ?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars(Auth::csrfToken()) ?>">
<meta name="csrf-name" content="<?= CSRF_TOKEN_NAME ?>">
<title>Selecteaza cont — SMS Platform</title>
<link rel="stylesheet" href="/assets/css/app.css">
<style>
  /* Override pentru pagina select-client — fond mai luminos */
  body { background: #f0f2f5; }

  .sc-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: #f0f2f5;
  }

  .sc-header {
    text-align: center;
    margin-bottom: 40px;
  }

  .sc-logo {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
  }

  .sc-logo-icon {
    width: 48px; height: 48px;
    background: #4f46e5;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(79,70,229,.35);
  }

  .sc-logo-icon svg { color: #fff; }

  .sc-logo-text {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1e1b4b;
    letter-spacing: -.03em;
  }

  .sc-logo-text span { color: #4f46e5; }

  .sc-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 6px;
  }

  .sc-subtitle {
    font-size: .875rem;
    color: #6b7280;
  }

  .sc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
    width: 100%;
    max-width: 640px;
    margin-bottom: 32px;
  }

  .sc-card-btn {
    width: 100%;
    background: #fff;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    padding: 22px 20px;
    cursor: pointer;
    transition: all .18s ease;
    display: flex;
    align-items: center;
    gap: 16px;
    text-align: left;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
  }

  .sc-card-btn:hover {
    border-color: #4f46e5;
    box-shadow: 0 4px 20px rgba(79,70,229,.18);
    transform: translateY(-2px);
  }

  .sc-card-btn:active {
    transform: translateY(0);
  }

  .sc-avatar {
    width: 48px; height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    font-weight: 800;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(79,70,229,.3);
  }

  .sc-card-info { flex: 1; min-width: 0; }

  .sc-card-name {
    font-size: .95rem;
    font-weight: 700;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .sc-card-slug {
    font-size: .75rem;
    color: #9ca3af;
    font-family: 'JetBrains Mono', monospace;
    margin-top: 3px;
  }

  .sc-arrow {
    color: #d1d5db;
    transition: color .18s, transform .18s;
    flex-shrink: 0;
  }

  .sc-card-btn:hover .sc-arrow {
    color: #4f46e5;
    transform: translateX(3px);
  }

  .sc-footer {
    text-align: center;
  }

  .sc-logout {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: .835rem;
    color: #9ca3af;
    text-decoration: none;
    padding: 6px 14px;
    border-radius: 8px;
    transition: background .15s, color .15s;
  }

  .sc-logout:hover {
    background: #fee2e2;
    color: #dc2626;
  }

  .sc-logout svg { width: 14px; height: 14px; }

  .sc-user-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 99px;
    padding: 6px 14px 6px 8px;
    font-size: .8rem;
    color: #374151;
    font-weight: 500;
    margin-bottom: 32px;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
  }

  .sc-user-avatar {
    width: 26px; height: 26px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #fff;
    font-size: .7rem;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
  }
</style>
</head>
<body>
<div class="sc-page">

  <div class="sc-header">
    <div class="sc-logo">
      <div class="sc-logo-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <div class="sc-logo-text">SMS<span>Platform</span></div>
    </div>
  </div>

  <?php
  $u = Auth::user();
  $initials = strtoupper(substr($u['name'] ?? 'U', 0, 1) . substr(explode(' ', $u['name'] ?? ' ')[1] ?? '', 0, 1));
  ?>
  <div class="sc-user-badge">
    <div class="sc-user-avatar"><?= $initials ?></div>
    <?= htmlspecialchars($u['name'] ?? '') ?>
  </div>

  <div class="sc-title">Selecteaza contul de lucru</div>
  <p class="sc-subtitle" style="margin-bottom:28px">
    Ai acces la <strong><?= count($clients) ?></strong> cont<?= count($clients) > 1 ? 'uri' : '' ?>. Alege cu care lucrezi acum.
  </p>

  <div class="sc-grid">
    <?php foreach ($clients as $c): ?>
    <form method="POST" action="/select-client">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
      <button type="submit" class="sc-card-btn">
        <div class="sc-avatar"><?= mb_strtoupper(mb_substr($c['name'], 0, 1)) ?></div>
        <div class="sc-card-info">
          <div class="sc-card-name"><?= htmlspecialchars($c['name']) ?></div>
          <div class="sc-card-slug"><?= htmlspecialchars($c['slug']) ?></div>
        </div>
        <svg class="sc-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </button>
    </form>
    <?php endforeach; ?>
  </div>

  <div class="sc-footer">
    <a href="/logout" class="sc-logout">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Deconectare
    </a>
  </div>

</div>
<div id="toast-container"></div>
<script src="/assets/js/app.js"></script>
</body>
</html>
