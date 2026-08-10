<?php


 * ============================================================
 * DD Laundry - Admin Login Page
 * admin/login.php
 *
 * PURPOSE:
 * Separate authentication page for administrators. Uses
 * dedicated admin session (separate from customer sessions)
 * with its own CSRF tokens, rate limiting, and security logging.
 *
 * FEATURES:
 * - Dark charcoal background (visually distinct from customer login)
 * - Username or Email field (accepts either)
 * - Password field with show/hide toggle
 * - AJAX login via apiCall() to php/admin_api.php (action: 'admin_login')
 * - Loading state on submit button
 * - Alert display for success/error
 * - "Back to Website" link to main site
 * - CSRF token in hidden form field and meta tag
 *
 * DATA FLOW:
 * 1. PHP: Check if admin already logged in -> redirect to admin dashboard
 * 2. PHP: Generate CSRF token, render login form
 * 3. User enters username/email + password
 * 4. JS: apiCall() POST to php/admin_api.php (action: 'admin_login')
 * 5. php/admin_api.php: Validates CSRF, checks rate limit (per IP)
 * 6. Server: Queries admins table (by username OR email)
 * 7. Server: password_verify() with timing-safe comparison
 * 8. On success: Regenerate session, set admin_id in session
 * 9. JS: Redirect to admin/index.php
 * 10. On failure: Show error, rate limit counter incremented
 *
 * SECURITY:
 * - Separate admin session (admin_id vs user_id)
 * - Rate limiting per IP address (10 attempts per 5 minutes)
 * - CSRF token validation
 * - Session regeneration on successful login
 * - Security logging for all login attempts (success and failure)
 * - Password hashed with bcrypt cost-12
 * - Constant-time password verification (prevents timing attacks)
 * - No sensitive data in JavaScript or URL parameters
 * - Security headers via sendSecurityHeaders()
 *
 * OWASP: A01 (CSRF), A02 (bcrypt), A03 (prepared statements),
 *        A07 (rate limiting, session regeneration, timing-safe),
 *        A09 (security logging)
 * ============================================================
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
