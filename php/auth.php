<?php
// ============================================================
// DD Laundry - Authentication API (OWASP Hardened)
// php/auth.php
//
// OWASP mitigations applied:
//   A01 - CSRF tokens on all state-changing requests
//   A02 - Bcrypt cost-12 hashing
//   A03 - PDO prepared statements, strict email validation
//   A05 - Security headers via sendSecurityHeaders()
//   A07 - Rate limiting on login/OTP, session regeneration,
//          constant-time compare for OTP (hash_equals),
//          timing-safe login (always run password_verify)
//   A09 - Security event logging
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

// Only accept POST for state-changing actions
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'register':    handleRegister();   break;
    case 'verify_otp':  handleVerifyOTP();  break;
    case 'resend_otp':  handleResendOTP();  break;
    case 'login':       handleLogin();      break;
    case 'logout':      requireCSRF(); handleLogout();      break;
    case 'forgot':      handleForgot();      break;
    case 'reset':       handleReset();      break;
    default:            jsonResponse(['error' => 'Invalid action'], 400);
}

// ──────────────────────────────────────────────────────────
function handleRegister() {
    requireCSRF();

    $db   = getDB();
    $name = sanitize($_POST['full_name'] ?? '');
    $rawEmail = $_POST['email'] ?? '';
    $email    = sanitizeEmail($rawEmail);
    $phone    = sanitize($_POST['phone'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $pass2    = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (!$name || strlen($name) < 2)          $errors['full_name'] = 'Full name is required (min 2 characters)';
    if (strlen($name) > 100)                   $errors['full_name'] = 'Name too long (max 100 characters)';
    if (!$email)                               $errors['email'] = 'Valid email address is required';
    if (!$phone)                               $errors['phone'] = 'Phone number is required';
    if (!validatePhone($phone))                $errors['phone'] = 'Enter a valid Nepal phone number (98XXXXXXXX or 97XXXXXXXX)';
    if (strlen($phone) > 20)                   $errors['phone'] = 'Phone number too long';
    if (strlen($pass) < 8)                     $errors['password'] = 'Password must be at least 8 characters';
    if (strlen($pass) > 128)                   $errors['password'] = 'Password too long';
    if ($pass !== $pass2)                      $errors['confirm_password'] = 'Passwords do not match';
    if (!empty($errors)) jsonResponse(['error' => 'Please fix the errors below', 'fields' => $errors], 400);

    // Rate limit registrations per IP
    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
    checkRateLimit("register_ip_{$ip}", 10, 3600);

    $stmt = $db->prepare("SELECT id, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['is_verified']) {
            jsonResponse(['error' => 'An account with this email already exists. Please login or use forgot password.'], 409);
        }
        // Resend OTP for unverified account
        $otp = generateOTP();
        $exp = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        $db->prepare("UPDATE users SET otp_code=?, otp_expires_at=?, full_name=?, phone=? WHERE id=?")
           ->execute([$otp, $exp, $name, $phone, $existing['id']]);
        sendOTPEmail($email, $name, $otp);
        jsonResponse(['success' => true, 'message' => 'OTP sent. Please verify your email.', 'email' => $email]);
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $otp  = generateOTP();
    $exp  = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

    $stmt = $db->prepare("INSERT INTO users (full_name, email, phone, password_hash, otp_code, otp_expires_at) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$name, $email, $phone, $hash, $otp, $exp]);

    sendOTPEmail($email, $name, $otp);
    securityLog('USER_REGISTERED', ['email' => $email]);
    jsonResponse(['success' => true, 'message' => 'Registration successful! Check your email for the OTP.', 'email' => $email]);
}

// ──────────────────────────────────────────────────────────
function handleVerifyOTP() {
    requireCSRF();

    $db    = getDB();
    $rawEmail = $_POST['email'] ?? '';
    $email = sanitizeEmail($rawEmail);
    $otp   = preg_replace('/\D/', '', $_POST['otp'] ?? '');  // digits only

    if (!$email || strlen($otp) !== 6) jsonResponse(['error' => 'Email and 6-digit OTP are required'], 400);

    // Rate limit OTP attempts
    checkRateLimit("otp_verify_{$email}", RATE_LIMIT_MAX_OTP, RATE_LIMIT_WINDOW);

    $stmt = $db->prepare("SELECT id, full_name, otp_code, otp_expires_at, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Constant-time path — always check, never short-circuit on user-not-found (timing attack)
    $storedOtp = $user['otp_code'] ?? '000000';
    $isExpired = !$user || strtotime($user['otp_expires_at'] ?? '0') < time();
    $otpMatch  = hash_equals($storedOtp, $otp);

    if (!$user || $user['is_verified']) jsonResponse(['error' => 'Verification failed. Please re-register.'], 400);
    if ($isExpired)                     jsonResponse(['error' => 'OTP expired. Request a new one.'], 400);
    if (!$otpMatch) {
        securityLog('OTP_INVALID', ['email' => $email]);
        jsonResponse(['error' => 'Invalid OTP. Please try again.'], 400);
    }

    $db->prepare("UPDATE users SET is_verified=1, otp_code=NULL, otp_expires_at=NULL WHERE id=?")
       ->execute([$user['id']]);
    clearRateLimit("otp_verify_{$email}");
    sendWelcomeEmail($email, $user['full_name']);
    securityLog('EMAIL_VERIFIED', ['email' => $email]);
    jsonResponse(['success' => true, 'message' => 'Email verified! You can now login.']);
}

// ──────────────────────────────────────────────────────────
function handleResendOTP() {
    requireCSRF();

    $db    = getDB();
    $email = sanitizeEmail($_POST['email'] ?? '');
    if (!$email) jsonResponse(['error' => 'Valid email is required'], 400);

    checkRateLimit("resend_otp_{$email}", RATE_LIMIT_MAX_OTP, RATE_LIMIT_WINDOW);

    $stmt = $db->prepare("SELECT id, full_name, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Same response whether user exists or not (prevents email enumeration)
    if (!$user || $user['is_verified']) {
        jsonResponse(['success' => true, 'message' => 'If applicable, a new OTP has been sent.']);
    }

    $otp = generateOTP();
    $exp = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $db->prepare("UPDATE users SET otp_code=?, otp_expires_at=? WHERE id=?")->execute([$otp, $exp, $user['id']]);
    sendOTPEmail($email, $user['full_name'], $otp);
    jsonResponse(['success' => true, 'message' => 'New OTP sent to your email.']);
}

// ──────────────────────────────────────────────────────────
function handleLogin() {
    requireCSRF();

    $db    = getDB();
    $email = sanitizeEmail($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) jsonResponse(['error' => 'Email and password are required'], 400);
    if (strlen($pass) > 128) jsonResponse(['error' => 'Invalid credentials'], 400);

    $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
    checkRateLimit("login_{$email}_{$ip}", RATE_LIMIT_MAX_LOGIN, RATE_LIMIT_WINDOW);

    $stmt = $db->prepare("SELECT id, full_name, email, password_hash, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // A07: ALWAYS run password_verify to prevent timing attacks
    $hash = $user['password_hash'] ?? '$2y$12$invalidhashpadding000000000000000000000000000000000000';
    $valid = password_verify($pass, $hash);

    if (!$user || !$valid) {
        securityLog('LOGIN_FAILED', ['email' => $email, 'ip' => $ip]);
        jsonResponse(['error' => 'Invalid email or password'], 401);
    }

    if (!$user['is_verified']) {
        jsonResponse(['error' => 'Please verify your email before logging in.', 'needs_verification' => true, 'email' => $email], 403);
    }

    // Successful login — regenerate session to prevent fixation
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['user_name']  = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['last_regen'] = time();

    clearRateLimit("login_{$email}_{$ip}");
    securityLog('LOGIN_SUCCESS', ['email' => $email]);
    jsonResponse(['success' => true, 'message' => 'Login successful!', 'redirect' => SITE_URL . '/dashboard.php']);
}

// ──────────────────────────────────────────────────────────
function handleLogout() {
    // CSRF validated in the switch dispatcher
    $userId = $_SESSION['user_id'] ?? null;
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    securityLog('LOGOUT', ['user_id' => $userId]);
    jsonResponse(['success' => true, 'redirect' => SITE_URL . '/login.php']);
}

// ──────────────────────────────────────────────────────────
function handleForgot() {
    requireCSRF();

    $db    = getDB();
    $email = sanitizeEmail($_POST['email'] ?? '');
    if (!$email) jsonResponse(['error' => 'Valid email is required'], 400);

    checkRateLimit("forgot_{$email}", RATE_LIMIT_MAX_OTP, RATE_LIMIT_WINDOW);

    $stmt = $db->prepare("SELECT id, full_name, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // A07: Same response whether email exists or not (prevents enumeration)
    if (!$user || !$user['is_verified']) {
        jsonResponse(['success' => true, 'message' => 'If that email is registered and verified, a reset OTP has been sent.', 'email' => $email]);
    }

    $otp = generateOTP();
    $exp = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $db->prepare("UPDATE users SET otp_code=?, otp_expires_at=? WHERE id=?")->execute([$otp, $exp, $user['id']]);
    sendOTPEmail($email, $user['full_name'], $otp, 'reset');
    securityLog('PASSWORD_RESET_REQUESTED', ['email' => $email]);
    jsonResponse(['success' => true, 'message' => 'If that email is registered and verified, a reset OTP has been sent.', 'email' => $email]);
}

// ──────────────────────────────────────────────────────────
function handleReset() {
    requireCSRF();

    $db    = getDB();
    $email = sanitizeEmail($_POST['email'] ?? '');
    $otp   = preg_replace('/\D/', '', $_POST['otp'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['confirm_password'] ?? '';

    if (!$email || strlen($otp) !== 6 || !$pass) jsonResponse(['error' => 'All fields are required'], 400);
    if ($pass !== $pass2)       jsonResponse(['error' => 'Passwords do not match'], 400);
    if (strlen($pass) < 8)      jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
    if (strlen($pass) > 128)    jsonResponse(['error' => 'Password too long'], 400);

    checkRateLimit("reset_{$email}", RATE_LIMIT_MAX_OTP, RATE_LIMIT_WINDOW);

    $stmt = $db->prepare("SELECT id, otp_code, otp_expires_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $storedOtp = $user['otp_code'] ?? '000000';
    $isExpired = !$user || strtotime($user['otp_expires_at'] ?? '0') < time();
    $otpMatch  = hash_equals($storedOtp, $otp);

    if (!$user || $isExpired || !$otpMatch) {
        securityLog('RESET_FAILED', ['email' => $email]);
        jsonResponse(['error' => 'Invalid or expired OTP. Please request a new one.'], 400);
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->prepare("UPDATE users SET password_hash=?, otp_code=NULL, otp_expires_at=NULL WHERE id=?")
       ->execute([$hash, $user['id']]);
    clearRateLimit("reset_{$email}");
    securityLog('PASSWORD_RESET_SUCCESS', ['email' => $email]);
    jsonResponse(['success' => true, 'message' => 'Password reset successful! You can now login.']);
}
