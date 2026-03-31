<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dezabonare SMS</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:36px;max-width:420px;width:100%;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.08)}
  .icon{font-size:2.5rem;margin-bottom:16px}
  h1{font-size:1.2rem;font-weight:700;color:#0f172a;margin-bottom:10px}
  p{font-size:.875rem;color:#64748b;line-height:1.6;margin-bottom:20px}
  .phone{font-family:monospace;font-size:1rem;font-weight:700;color:#4f46e5;background:#eef2ff;padding:6px 14px;border-radius:8px;display:inline-block;margin-bottom:20px}
  .btn{display:inline-block;padding:12px 28px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;width:100%}
  .btn:hover{background:#b91c1c}
  .err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:10px;border-radius:8px;font-size:.83rem;margin-bottom:16px}
</style>
</head>
<body>
<div class="card">
  <?php if (!$valid): ?>
    <div class="icon">⚠️</div>
    <h1>Link invalid</h1>
    <p>Acest link de dezabonare nu este valid sau a expirat.</p>
  <?php else: ?>
    <div class="icon">🚫</div>
    <h1>Dezabonare SMS</h1>
    <p>Confirma ca nu mai doresti sa primesti mesaje SMS:</p>
    <div class="phone"><?= htmlspecialchars($phone) ?></div>
    <form method="POST" action="/stop">
      <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
      <input type="hidden" name="client_id" value="<?= $clientId ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <button type="submit" class="btn">Confirm dezabonarea</button>
    </form>
    <p style="margin-top:14px;font-size:.75rem;color:#94a3b8">Dupa confirmare nu vei mai primi mesaje de la noi.</p>
  <?php endif; ?>
</div>
</body>
</html>
