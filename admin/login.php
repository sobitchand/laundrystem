<?php
require_once __DIR__ . '/../php/config.php';
sendSecurityHeaders();
if (isAdminLoggedIn()) { header('Location: index.php'); exit; }
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= $csrf ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Admin Login &mdash; DD Laundry</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
</head>
<body style="background:var(--charcoal);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;">

<div style="width:100%;max-width:420px;">
  <div class="text-center" style="margin-bottom:32px;">
    <div style="font-size:3rem;margin-bottom:10px;">&#x1F510;</div>
    <h1 style="font-family:'Playfair Display',serif;color:#fff;font-size:1.8rem;margin-bottom:4px;">Admin Panel</h1>
    <p style="color:rgba(255,255,255,0.4);font-size:.88rem;">DD Laundry Management System</p>
  </div>

  <div class="card">
    <div class="card-body" style="padding:36px;">
      <div id="adminAlert" role="alert" aria-live="polite"></div>
      <form id="adminLoginForm" novalidate autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action"     value="admin_login">

        <div class="form-group">
          <label class="form-label" for="adminUser">Username or Email</label>
          <div class="input-group">
            <span class="input-icon" aria-hidden="true">&#x1F464;</span>
            <input type="text" class="form-control" id="adminUser" name="username"
                   placeholder="admin" autocomplete="username" maxlength="150" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="adminPass">Password</label>
          <div class="input-group">
            <span class="input-icon" aria-hidden="true">&#x1F512;</span>
            <input type="password" class="form-control" id="adminPass" name="password"
                   placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                   autocomplete="current-password" maxlength="128" required>
            <button type="button" class="input-toggle" data-target="adminPass"
                    aria-label="Show password">&#x1F441;&#xFE0F;</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg mt-2" id="adminLoginBtn">
          Login to Admin Panel
        </button>
      </form>
    </div>
  </div>

  <p class="text-center" style="margin-top:20px;font-size:.82rem;">
    <a href="../index.php" style="color:rgba(255,255,255,.35);text-decoration:none;">
      &larr; Back to Website
    </a>
  </p>
</div>

<script src="../js/main.js"></script>
<script>
document.getElementById('adminLoginForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('adminLoginBtn');
  setLoading(btn, true);
  const res = await apiCall('../php/admin_api.php', {
    action:   'admin_login',
    username: document.getElementById('adminUser').value,
    password: document.getElementById('adminPass').value,
  });
  setLoading(btn, false);
  if (res.success) {
    window.location.href = res.redirect || 'index.php';
  } else {
    showAlert('adminAlert', res.error || 'Login failed', 'error');
  }
});
</script>
</body>
</html>
