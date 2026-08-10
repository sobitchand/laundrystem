<?php


 * ============================================================
 * DD Laundry - Customer Registration Page
 * register.php
 *
 * PURPOSE:
 * Two-step registration flow: (1) Account creation with form
 * validation, (2) Email OTP verification. Sends OTP via
 * Gmail SMTP and verifies it before activating the account.
 *
 * FEATURES:
 * - Step 1: Registration form with fields:
 *   - Full Name (required, min 2 chars)
 *   - Phone Number (required, Nepal format: 98XXXXXXXX)
 *   - Email Address (required, validated)
 *   - Password (required, min 8 chars)
 *   - Confirm Password (must match)
 * - Step 2: 6-digit OTP verification with:
 *   - 6 separate input boxes with auto-advance
 *   - Paste support for full OTP
 *   - Resend OTP button with 60-second countdown
 * - Visual left panel showing registration benefits
 * - AJAX calls to php/auth.php for register, verify_otp, resend_otp
 * - Loading states on all buttons
 * - Field-level error highlighting
 * - Pre-fills email from URL parameter (?email=...)
 *
 * DATA FLOW:
 * 1. PHP: Check if logged in -> redirect to dashboard
 * 2. PHP: Generate CSRF token
 * 3. User fills registration form
 * 4. JS: apiCall() POST to php/auth.php (action: 'register')
 * 5. php/auth.php: Validates, creates user, sends OTP email
 * 6. JS: Hides Step 1, shows Step 2 OTP form
 * 7. User enters 6-digit OTP from email
 * 8. JS: apiCall() POST to php/auth.php (action: 'verify_otp')
 * 9. php/auth.php: Verifies OTP with timing-safe comparison
 * 10. On success: Redirect to login.php
 * 11. On OTP failure: Resend available after 60s cooldown
 *
 * SECURITY:
 * - CSRF token on all forms
 * - Rate limiting: 10 registrations per IP per hour
 * - OTP: 6 digits, 15-min expiry, single-use
 * - Password hashed with bcrypt cost-12 server-side
 * - No passwords stored/transmitted in plain text
 * - Email enumeration prevention (same response for existing/unregistered)
 * - All user input sanitized server-side
 *
 * OWASP: A01 (CSRF), A02 (bcrypt), A03 (prepared statements),
 *        A07 (rate limiting, OTP expiry, timing-safe comparison),
 *        A09 (security logging)
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
  <title>Register &mdash; DD Laundry</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-visual" aria-hidden="true">
    <div class="auth-visual-content">
      <span class="auth-visual-emoji">&#x2728;</span>
      <div class="auth-logo">Join Us Today</div>
      <p class="auth-tagline">Create your free account and experience professional laundry at your doorstep.</p>
      <div class="auth-perk-box">
        <div class="auth-perk-title">First Order Perks</div>
        <ul class="auth-perks">
          <li>&#x1F697; Free Pickup &amp; Delivery</li>
          <li>&#x26A1; 24-Hour Turnaround</li>
          <li>&#x1F4F1; Live Order Tracking</li>
        </ul>
      </div>
    </div>
  </div>

  <main class="auth-form-side">
    <div class="auth-form-container">
      <a href="index.php" class="back-link">&larr; Back to Home</a>

      <!-- Step 1: Registration -->
      <div id="step1">
        <h1 class="auth-form-title">Create Account</h1>
        <p class="auth-form-subtitle">Join hundreds of satisfied customers in Lalitpur</p>
        <div id="regAlert" role="alert" aria-live="polite"></div>

        <form id="registerForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="action"     value="register">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="fullName">Full Name <span class="req">*</span></label>
              <input type="text" class="form-control" id="fullName" name="full_name"
                     placeholder="Ram Bahadur" autocomplete="name" maxlength="100" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="phone">Phone Number <span class="req">*</span></label>
              <input type="tel" class="form-control" id="phone" name="phone"
                     placeholder="98XXXXXXXX" autocomplete="tel" maxlength="20" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="regEmail">Email Address <span class="req">*</span></label>
            <div class="input-group">
              <span class="input-icon" aria-hidden="true">&#x1F4E7;</span>
              <input type="email" class="form-control" id="regEmail" name="email"
                     placeholder="you@example.com" autocomplete="email" maxlength="150" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="regPass">Password <span class="req">*</span></label>
              <div class="input-group">
                <span class="input-icon" aria-hidden="true">&#x1F512;</span>
                <input type="password" class="form-control" id="regPass" name="password"
                       placeholder="Min. 8 characters" autocomplete="new-password"
                       maxlength="128" minlength="8" required>
                <button type="button" class="input-toggle" data-target="regPass"
                        aria-label="Show password">&#x1F441;&#xFE0F;</button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" for="regPass2">Confirm Password <span class="req">*</span></label>
              <div class="input-group">
                <span class="input-icon" aria-hidden="true">&#x1F512;</span>
                <input type="password" class="form-control" id="regPass2" name="confirm_password"
                       placeholder="Repeat password" autocomplete="new-password"
                       maxlength="128" required>
              </div>
            </div>
          </div>
          <p class="form-hint">Password must be at least 8 characters.</p>

          <button type="submit" class="btn btn-primary btn-full btn-lg mt-2" id="registerBtn">
            Create Account &amp; Get OTP
          </button>
        </form>
      </div>

      <!-- Step 2: OTP Verification -->
      <div id="step2" class="hidden">
        <div class="otp-header text-center">
          <div class="otp-emoji">&#x1F4EC;</div>
          <h2 class="auth-form-title">Check Your Email</h2>
          <p class="auth-form-subtitle">We sent a 6-digit OTP to <strong id="emailDisplay"></strong></p>
        </div>
        <div id="otpAlert" role="alert" aria-live="polite"></div>

        <form id="otpForm" novalidate>
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
          <button type="submit" class="btn btn-primary btn-full btn-lg" id="verifyBtn">
            Verify &amp; Continue
          </button>
          <p class="text-center mt-2" style="font-size:.88rem;color:var(--gray-500)">
            Didn&rsquo;t receive it?
            <a href="#" id="resendOtpBtn" style="color:var(--red);font-weight:600;">Resend OTP</a>
          </p>
        </form>
      </div>

      <div class="auth-divider"><span>or</span></div>
      <p class="auth-switch">Already have an account? <a href="login.php">Login &rarr;</a></p>
    </div>
  </main>
</div>

<script src="js/main.js"></script>
<script>
let registeredEmail = '';
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const emailParam = new URLSearchParams(window.location.search).get('email');
if (emailParam) document.getElementById('regEmail').value = emailParam;

document.getElementById('registerForm').addEventListener('submit', async e => {
  e.preventDefault();
  clearFieldErrors();
  const pass  = document.getElementById('regPass').value;
  const pass2 = document.getElementById('regPass2').value;

  const btn = document.getElementById('registerBtn');
  setLoading(btn, true);

  const res = await apiCall('./php/auth.php', {
    action: 'register',
    full_name: document.getElementById('fullName').value,
    email:     document.getElementById('regEmail').value,
    phone:     document.getElementById('phone').value,
    password:  pass,
    confirm_password: pass2,
  });
  setLoading(btn, false);

  if (res.success) {
    registeredEmail = res.email || document.getElementById('regEmail').value;
    document.getElementById('emailDisplay').textContent = registeredEmail;
    document.getElementById('step1').classList.add('hidden');
    document.getElementById('step2').classList.remove('hidden');
    initOTPInputs();
    startCountdown('resendOtpBtn', 60);
  } else {
    if (res.fields) {
      showFieldErrors(res.fields);
    } else {
      showAlert('regAlert', res.error || 'Registration failed', 'error');
    }
  }
});

document.getElementById('otpForm').addEventListener('submit', async e => {
  e.preventDefault();
  const otp = getOTPValue();
  if (otp.length < 6) { showAlert('otpAlert','Please enter the complete 6-digit OTP','error'); return; }
  const btn = document.getElementById('verifyBtn');
  setLoading(btn, true);
  const res = await apiCall('./php/auth.php', { action: 'verify_otp', email: registeredEmail, otp });
  setLoading(btn, false);
  if (res.success) {
    showAlert('otpAlert', 'Email verified! Redirecting to login...', 'success');
    setTimeout(() => window.location.href = 'login.php', 1800);
  } else {
    showAlert('otpAlert', res.error || 'Verification failed', 'error');
  }
});

document.getElementById('resendOtpBtn').addEventListener('click', async e => {
  e.preventDefault();
  const res = await apiCall('./php/auth.php', { action: 'resend_otp', email: registeredEmail });
  ToastManager.show(res.success ? 'New OTP sent!' : (res.error || 'Failed'), res.success ? 'success' : 'error');
  if (res.success) startCountdown('resendOtpBtn', 60);
});
</script>
</body>
</html>
