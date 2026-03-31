<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars(Auth::csrfToken()) ?>">
<meta name="csrf-name" content="<?= CSRF_TOKEN_NAME ?>">
<title>Autentificare — SMS Platform</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="login-logo-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <div class="login-title">SMS<span>Platform</span></div>
    </div>
    <p class="login-subtitle">Platforma profesionala de trimitere SMS-uri</p>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($show2fa): ?>
    <?php $is_totp = (($_SESSION['pending_2fa_type'] ?? 'sms') === 'totp'); ?>
    <!-- Step 2: Cod 2FA -->
    <div style="text-align:center;margin-bottom:20px">
      <div style="width:48px;height:48px;background:var(--accent-subtle);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
        <?php if ($is_totp): ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <?php else: ?>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        <?php endif; ?>
      </div>
      <div style="font-weight:700;font-size:.95rem;margin-bottom:5px">Verificare in doi pasi</div>
      <?php if ($is_totp): ?>
      <div style="font-size:.82rem;color:var(--text-muted)">Introdu codul din aplicatia Authenticator (Google Authenticator, Authy etc.).</div>
      <?php else: ?>
      <div style="font-size:.82rem;color:var(--text-muted)">Am trimis un cod SMS la numarul de telefon asociat contului tau.</div>
      <?php endif; ?>
    </div>
    <form method="POST" action="/login/verify-2fa">
      <?= Auth::csrfField() ?>
      <div class="form-group">
        <label class="form-label" for="code">Cod de verificare (6 cifre)</label>
        <input type="text" id="code" name="code" class="form-input"
               placeholder="000000" maxlength="6" pattern="[0-9]{6}"
               autocomplete="one-time-code" inputmode="numeric"
               style="font-size:1.4rem;letter-spacing:.3em;text-align:center;font-family:var(--font-mono)"
               autofocus required>
        <?php if (!$is_totp): ?>
        <div class="form-hint" style="text-align:center">Codul expira in 5 minute.</div>
        <?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:8px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Verifica codul
      </button>
    </form>
    <div style="text-align:center;margin-top:16px">
      <a href="/login" style="font-size:.78rem;color:var(--text-muted)">&larr; Inapoi la login</a>
    </div>

    <?php else: ?>
    <!-- Step 1: Email + parola -->
    <form method="POST" action="/login">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
      <!-- Honeypot: invizibil pentru utilizatori, completat de boti -->
      <div style="position:absolute;left:-9999px;top:-9999px;overflow:hidden" aria-hidden="true">
        <input type="text" name="_hp" value="" tabindex="-1" autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label" for="email">Adresa email <span class="required">*</span></label>
        <input type="email" id="email" name="email" class="form-input"
               placeholder="admin@exemplu.ro" autocomplete="email" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Parola <span class="required">*</span></label>
        <input type="password" id="password" name="password" class="form-input"
               placeholder="••••••••••" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:8px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Autentificare
      </button>
    </form>
    <?php endif; ?>

    <p style="text-align:center;font-size:.75rem;color:var(--text-muted);margin-top:24px">
      Acces restrictionat. Platforma este dedicata utilizatorilor autorizati.
    </p>
  </div>
</div>
<div id="toast-container"></div>
<script src="/assets/js/app.js"></script>
</body>
</html>
