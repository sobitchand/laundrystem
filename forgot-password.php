<?php


 * ============================================================
 * DD Laundry - Forgot Password Page
 * forgot-password.php
 *
 * PURPOSE:
 * Three-step password recovery flow: (1) Enter email,
 * (2) Enter OTP received via email, (3) Set new password.
 * Uses the same OTP mechanism as registration.
 *
 * FEATURES:
 * - Step 1 (fp1): Email input form
 *   - Sends reset OTP via php/auth.php (action: 'forgot')
 *   - Returns same response whether email exists (anti-enumeration)
 * - Step 2 (fp2): 6-digit OTP entry
 *   - 6 auto-advancing input boxes
 *   - Paste support
 *   - Verify button triggers Step 3
 * - Step 3 (fp3): New password form
 *   - New Password (min 8 chars)
 *   - Confirm Password (must match)
 *   - Password show/hide toggle
 *   - Submits to php/auth.php (action: 'reset')
 * - Visual left panel with key icon and tagline
 * - All steps use AJAX (no page reloads)
 * - Redirects to login.php on success
 *
 * DATA FLOW:
 * 1. PHP: Check if logged in -> redirect to dashboard
 * 2. PHP: Generate CSRF token
 * 3. User enters email in Step 1
 * 4. JS: apiCall() POST to php/auth.php (action: 'forgot')
 * 5. php/auth.php: Generates OTP, sends email via PHPMailer
 * 6. JS: Hides Step 1, shows Step 2 OTP form
 * 7. User enters OTP from email
 * 8. JS: Hides Step 2, shows Step 3 new password form
 * 9. User enters new password + confirmation
 * 10. JS: apiCall() POST to php/auth.php (action: 'reset')
 * 11. php/auth.php: Verifies OTP, hashes new password (bcrypt)
 * 12. On success: Redirect to login.php after 2s
 *
 * SECURITY:
 * - CSRF token on all forms
 * - Rate limiting on forgot and reset endpoints
 * - OTP: 6-digit, 15-min expiry, timing-safe comparison
 * - Password hashed with bcrypt cost-12
 * - Same response for existing/non-existing emails (anti-enumeration)
 * - All validation done server-side in php/auth.php
 *
 * OWASP: A01 (CSRF), A02 (bcrypt), A03 (prepared statements),
 *        A07 (OTP expiry, timing-safe, rate limiting), A09 (logging)
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
  <title>Forgot Password &mdash; DD Laundry</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-visual" aria-hidden="true">
    <div class="auth-visual-content">
      <span class="auth-visual-emoji">&#x1F511;</span>
      <div class="auth-logo">Reset Password</div>
      <p class="auth-tagline">Don&rsquo;t worry &mdash; we&rsquo;ll help you get back into your account in a few easy steps.</p>
    </div>
  </div>

  <main class="auth-form-side">
    <div class="auth-form-container">
      <a href="login.php" class="back-link">&larr; Back to Login</a>
      <div id="fpAlert" role="alert" aria-live="polite"></div>

      <!-- Step 1: Email -->
      <div id="fp1">
        <h1 class="auth-form-title">Forgot Password?</h1>
        <p class="auth-form-subtitle">Enter your registered email to receive a reset OTP.</p>
        <form id="forgotForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <div class="form-group">
            <label class="form-label" for="fpEmail">Email Address</label>
            <div class="input-group">
              <span class="input-icon" aria-hidden="true">&#x1F4E7;</span>
              <input type="email" class="form-control" id="fpEmail" name="email"
                     placeholder="you@example.com" autocomplete="email" maxlength="150" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-full btn-lg" id="sendBtn">
            Send Reset OTP
          </button>
        </form>
      </div>

      <!-- Step 2: OTP -->
      <div id="fp2" class="hidden">
        <h1 class="auth-form-title">Enter OTP</h1>
        <p class="auth-form-subtitle">Enter the 6-digit OTP sent to <strong id="fpEmailDisplay"></strong></p>
        <fieldset>
          <legend class="sr-only">Enter OTP</legend>
          <div class="otp-inputs" role="group" aria-label="6-digit OTP">
            <input type="text" class="otp-input" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="OTP digit 1">
            <input type="text" class="otp-input" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="OTP digit 2">
            <input type="text" class="otp-input" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="OTP digit 3">
            <input type="text" class="otp-input" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="OTP digit 4">
            <input type="text" class="otp-input" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="OTP digit 5">
            <input type="text" class="otp-input" inputmode="numeric" pattern="[0-9]" maxlength="1" aria-label="OTP digit 6">
          </div>
        </fieldset>
        <button class="btn btn-primary btn-full btn-lg" id="verifyOtpBtn">Verify OTP</button>
      </div>

      <!-- Step 3: New Password -->
      <div id="fp3" class="hidden">
        <h1 class="auth-form-title">New Password</h1>
        <p class="auth-form-subtitle">Create a strong new password for your account.</p>
        <form id="resetForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <div class="form-group">
            <label class="form-label" for="newPwd">New Password</label>
            <div class="input-group">
              <span class="input-icon" aria-hidden="true">&#x1F512;</span>
              <input type="password" class="form-control" id="newPwd" name="password"
                     placeholder="Min. 8 characters" autocomplete="new-password"
                     maxlength="128" minlength="8" required>
              <button type="button" class="input-toggle" data-target="newPwd"
                      aria-label="Show password">&#x1F441;&#xFE0F;</button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="confPwd">Confirm New Password</label>
            <div class="input-group">
              <span class="input-icon" aria-hidden="true">&#x1F512;</span>
              <input type="password" class="form-control" id="confPwd" name="confirm_password"
                     placeholder="Repeat password" autocomplete="new-password"
                     maxlength="128" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-full btn-lg" id="resetBtn">
            Reset Password
          </button>
        </form>
      </div>
    </div>
  </main>
</div>

<script src="js/main.js"></script>
<script>
let fpEmail = '', fpOtp = '';

document.getElementById('forgotForm').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('sendBtn');
  setLoading(btn, true);
  const res = await apiCall('./php/auth.php', {
    action: 'forgot',
    email:  document.getElementById('fpEmail').value,
  });
  setLoading(btn, false);
  if (res.success) {
    fpEmail = document.getElementById('fpEmail').value;
    document.getElementById('fpEmailDisplay').textContent = fpEmail;
    document.getElementById('fp1').classList.add('hidden');
    document.getElementById('fp2').classList.remove('hidden');
    document.getElementById('fpAlert').innerHTML = '';
    initOTPInputs();
  } else {
    showAlert('fpAlert', res.error || 'Failed to send OTP', 'error');
  }
});

document.getElementById('verifyOtpBtn').addEventListener('click', () => {
  fpOtp = getOTPValue();
  if (fpOtp.length < 6) { showAlert('fpAlert','Enter complete 6-digit OTP','error'); return; }
  document.getElementById('fp2').classList.add('hidden');
  document.getElementById('fp3').classList.remove('hidden');
  document.getElementById('fpAlert').innerHTML = '';
  initPasswordToggles();
});

document.getElementById('resetForm').addEventListener('submit', async e => {
  e.preventDefault();
  const pass  = document.getElementById('newPwd').value;
  const conf  = document.getElementById('confPwd').value;
  if (pass !== conf) { showAlert('fpAlert','Passwords do not match','error'); return; }
  const btn = document.getElementById('resetBtn');
  setLoading(btn, true);
  const res = await apiCall('./php/auth.php', {
    action:           'reset',
    email:            fpEmail,
    otp:              fpOtp,
    password:         pass,
    confirm_password: conf,
  });
  setLoading(btn, false);
  if (res.success) {
    showAlert('fpAlert', 'Password reset successfully! Redirecting to login...', 'success');
    setTimeout(() => window.location.href = 'login.php', 2000);
  } else {
    showAlert('fpAlert', res.error || 'Reset failed', 'error');
  }
});
</script>
</body>
</html>
