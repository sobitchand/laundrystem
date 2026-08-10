<?php


 * ============================================================
 * DD Laundry - Customer Login Page
 * login.php
 *
 * PURPOSE:
 * Provides the customer login interface. Redirects already
 * logged-in users to dashboard. Handles email/password
 * authentication via AJAX call to php/auth.php.
 *
 * FEATURES:
 * - Split-screen layout (visual left side, form right side)
 * - Email and password fields with validation
 * - Password show/hide toggle button
 * - "Forgot password?" link to forgot-password.php
 * - AJAX login via apiCall() to php/auth.php (action: 'login')
 * - Loading state on submit button during API call
 * - Alert display for success/error messages
 * - Special handling for unverified accounts (shows verification link)
 * - CSRF token embedded in hidden form field and meta tag
 * - Responsive design (stacks on mobile)
 *
 * DATA FLOW:
 * 1. PHP: Check if already logged in -> redirect to dashboard
 * 2. PHP: Generate CSRF token, embed in form
 * 3. User submits email + password
 * 4. JS: apiCall() sends POST to php/auth.php with action=login
 * 5. php/auth.php: Validates CSRF, checks rate limit, verifies credentials
 * 6. On success: Redirect to dashboard.php
 * 7. On unverified account: Show email verification link
 * 8. On failure: Show error alert
 *
 * SECURITY:
 * - CSRF token on form (hidden input + meta tag)
 * - Security headers via sendSecurityHeaders()
 * - Password field uses autocomplete='current-password'
 * - Rate limiting handled server-side in php/auth.php
 * - No sensitive data stored in client-side JavaScript
 * - XSS prevention via textContent for alerts (not innerHTML)
 *
 * OWASP: A01 (CSRF), A03 (XSS prevention), A05 (security headers),
 *        A07 (rate limiting, session security)
 * ============================================================
require_once __DIR__ . '/php/config.php';
sendSecurityHeaders();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }
$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= $csrf ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Login &mdash; DD Laundry</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-page">

  <!-- Visual side -->
  <div class="auth-visual" aria-hidden="true">
    <div class="auth-visual-content">
      <span class="auth-visual-emoji">&#x1F9BA;</span>
      <div class="auth-logo">DD Laundry</div>
      <p class="auth-tagline">Your trusted laundry partner<br>in Imadol, Lalitpur, Nepal.</p>
      <ul class="auth-perks">
        <li>&#x2713; Free Pickup &amp; Delivery</li>
        <li>&#x2713; Real-time Order Tracking</li>
        <li>&#x2713; Professional Care</li>
      </ul>
    </div>
  </div>

  <!-- Form side -->
  <main class="auth-form-side">
    <div class="auth-form-container">
      <a href="index.php" class="back-link">&larr; Back to Home</a>
      <h1 class="auth-form-title">Welcome back</h1>
      <p class="auth-form-subtitle">Login to access your laundry dashboard</p>

      <div id="loginAlert" role="alert" aria-live="polite"></div>

      <form id="loginForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="action"     value="login">

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-group">
            <span class="input-icon" aria-hidden="true">&#x1F4E7;</span>
            <input type="email" class="form-control" id="email" name="email"
                   placeholder="you@example.com" autocomplete="email"
                   maxlength="150" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">
            Password
            <a href="forgot-password.php" class="form-label-link">Forgot password?</a>
          </label>
          <div class="input-group">
            <span class="input-icon" aria-hidden="true">&#x1F512;</span>
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
                   autocomplete="current-password" maxlength="128" required>
            <button type="button" class="input-toggle" data-target="password"
                    aria-label="Show password">&#x1F441;&#xFE0F;</button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" id="loginBtn">
          Login to Dashboard
        </button>
      </form>

      <div class="auth-divider"><span>or</span></div>
      <p class="auth-switch">
        Don&rsquo;t have an account?
        <a href="register.php">Create one free &rarr;</a>
      </p>
    </div>
  </main>
</div>

<script src="js/main.js"></script>
<script>
document.getElementById('loginForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('loginBtn');
  const alertEl = document.getElementById('loginAlert');
  alertEl.innerHTML = '';
  setLoading(btn, true);

  const res = await apiCall('./php/auth.php', {
    action:   'login',
    email:    document.getElementById('email').value,
    password: document.getElementById('password').value,
  });
  setLoading(btn, false);

  if (res.success) {
    window.location.href = res.redirect || 'dashboard.php';
  } else if (res.needs_verification) {
    showAlert('loginAlert', res.error + ' — click here to verify your email.', 'warning');
    const link = document.createElement('a');
    link.href = 'register.php?email=' + encodeURIComponent(res.email || '');
    link.textContent = 'Verify now';
    link.style.marginLeft = '4px';
    link.style.color = 'var(--red)';
    link.style.fontWeight = '600';
    alertEl.querySelector('.alert')?.appendChild(link);
  } else {
    showAlert('loginAlert', res.error || 'Login failed. Please try again.', 'error');
  }
});
</script>
</body>
</html>
