<?php
// ============================================================
// DD Laundry - Configuration File (OWASP Top 10 Hardened)
// php/config.php
// ============================================================

// A05: Security Misconfiguration — disable error display to users
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) @mkdir($logsDir, 0750, true);

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', $logsDir . '/php_errors.log');

// ── Database ──────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'dd_laundry');
define('DB_CHARSET', 'utf8mb4');

// ── Site ──────────────────────────────────────────────────
define('SITE_NAME',  'DD Laundry');
define('SITE_URL',   'http://localhost/dd_laundry');
define('SITE_EMAIL', 'noreply@ddlaundry.com');
define('ADMIN_EMAIL','admin@ddlaundry.com');

// ── SMTP ──────────────────────────────────────────────────
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'chandsobit70@gmail.com');
define('SMTP_PASS',      'nrpibwgaahmgdved');
define('SMTP_FROM',      'chandsobit70@gmail.com');
define('SMTP_FROM_NAME', 'DD Laundry');

// ── Limits ────────────────────────────────────────────────
define('SESSION_LIFETIME',    86400);
define('OTP_EXPIRY_MINUTES',  15);
define('RATE_LIMIT_WINDOW',   300);
define('RATE_LIMIT_MAX_LOGIN',10);
define('RATE_LIMIT_MAX_OTP',  5);
define('MAX_ORDER_ITEMS',     20);
define('MAX_QTY_PER_ITEM',    50);

date_default_timezone_set('Asia/Kathmandu');

// ── Secure Session (A07: Auth Failures) ───────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', 0);
    session_start();
}

/**
 * Send security HTTP headers to protect against common attacks.
 * 
 * Headers set:
 * - X-Content-Type-Options: Prevents MIME-type sniffing
 * - X-Frame-Options: Prevents clickjacking (SAMEORIGIN)
 * - X-XSS-Protection: Enables browser XSS filter
 * - Referrer-Policy: Controls referrer information
 * - Permissions-Policy: Restricts browser features
 * - Content-Security-Policy: Prevents unauthorized script execution
 * 
 * OWASP A05: Security Misconfiguration
 */
function sendSecurityHeaders() {
    if (headers_sent()) return;
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(self), camera=(), microphone=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src https://fonts.gstatic.com; img-src 'self' data: https: https://*.openstreetmap.org; connect-src 'self' https://*.openstreetmap.org; frame-src https://www.google.com;");
}

/**
 * Get PDO database connection instance (singleton pattern).
 * 
 * Returns a cached PDO connection to prevent multiple connections.
 * Uses UTF-8 charset, strict error mode, and disables emulated prepares
 * to prevent SQL injection via multi-byte character attacks.
 * 
 * @return PDO The database connection
 * @throws PDOException If connection fails (logs error and exits with JSON error)
 * 
 * OWASP A03: Injection Prevention
 */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $opts = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            error_log("DB Connection failed: " . $e->getMessage());
            die(json_encode(['error' => 'Service temporarily unavailable']));
        }
    }
    return $pdo;
}

/**
 * Generate a CSRF token for the current session.
 * 
 * Creates a cryptographically secure random token (64 hex chars)
 * and stores it in the session. Returns existing token if already set.
 * 
 * @return string The CSRF token (64-character hex string)
 * 
 * OWASP A01: Broken Access Control Prevention
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token against the session token.
 * 
 * Uses timing-safe comparison (hash_equals) to prevent timing attacks.
 * 
 * @param string $token The token to validate
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require a valid CSRF token in the current request.
 * 
 * Checks $_POST['csrf_token'] or HTTP_X_CSRF_TOKEN header.
 * If invalid, logs security violation and returns 403 JSON error.
 * 
 * OWASP A01: CSRF Protection
 */
function requireCSRF() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($token)) {
        securityLog('CSRF_VIOLATION', ['url' => $_SERVER['REQUEST_URI'] ?? '']);
        jsonResponse(['error' => 'Invalid security token. Refresh and try again.'], 403);
    }
}

/**
 * Check and enforce rate limiting for a given action.
 * 
 * Tracks attempts in a JSON file per key (sanitized). If attempts exceed
 * maxAttempts within windowSeconds, blocks further attempts for the window duration.
 * 
 * @param string $key Unique identifier for the rate limit (e.g., "login_email_ip")
 * @param int $maxAttempts Maximum attempts allowed in the window (default: 10)
 * @param int $windowSeconds Time window in seconds (default: 300 = 5 minutes)
 * @return void Exits with 429 JSON error if rate limit exceeded
 * 
 * OWASP A07: Identification and Authentication Failures (Brute Force Prevention)
 */
function checkRateLimit($key, $maxAttempts = 10, $windowSeconds = 300) {
    $dir = __DIR__ . '/../logs/rate_limits/';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    $file    = $dir . $safeKey . '.json';
    $now     = time();
    $data    = ['attempts' => [], 'blocked_until' => 0];

    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw) $data = json_decode($raw, true) ?: $data;
    }

    if (isset($data['blocked_until']) && $data['blocked_until'] > $now) {
        $wait = (int)ceil(($data['blocked_until'] - $now) / 60);
        jsonResponse(['error' => "Too many attempts. Try again in {$wait} minute(s)."], 429);
    }

    $data['attempts'] = array_values(array_filter(
        $data['attempts'] ?? [],
        fn($t) => ($now - $t) < $windowSeconds
    ));

    if (count($data['attempts']) >= $maxAttempts) {
        $data['blocked_until'] = $now + $windowSeconds;
        securityLog('RATE_LIMIT_TRIGGERED', ['key' => $safeKey]);
        @file_put_contents($file, json_encode($data), LOCK_EX);
        jsonResponse(['error' => "Too many attempts. Try again in " . ceil($windowSeconds/60) . " minute(s)."], 429);
    }

    $data['attempts'][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * Clear rate limit tracking for a given key.
 * 
 * Called after successful authentication to reset attempt counter.
 * 
 * @param string $key The rate limit key to clear
 */
function clearRateLimit($key) {
    $dir  = __DIR__ . '/../logs/rate_limits/';
    $file = $dir . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.json';
    if (file_exists($file)) @unlink($file);
}

/**
 * Sanitize user input to prevent XSS and injection attacks.
 * 
 * Applies:
 * - trim() to remove whitespace
 * - strip_tags() to remove HTML/PHP tags
 * - htmlspecialchars() with ENT_QUOTES to encode special characters
 * 
 * Handles arrays recursively.
 * 
 * @param string|array $input The input to sanitize
 * @return string|array The sanitized input
 * 
 * OWASP A03: Injection Prevention
 */
function sanitize($input) {
    if (is_array($input)) return array_map('sanitize', $input);
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize and validate an email address.
 * 
 * Applies FILTER_SANITIZE_EMAIL and validates with FILTER_VALIDATE_EMAIL.
 * Returns lowercase email if valid, false otherwise.
 * 
 * @param string $email The email to validate
 * @return string|false The validated lowercase email, or false if invalid
 */
function sanitizeEmail($email) {
    $email = filter_var(trim((string)$email), FILTER_SANITIZE_EMAIL);
    $validated = filter_var($email, FILTER_VALIDATE_EMAIL);
    return $validated ? strtolower($validated) : false;
}

/**
 * Validate a date string in Y-m-d format.
 * 
 * @param string $date The date to validate
 * @return string|null The validated date string, or null if invalid
 */
function validateDate($date) {
    if (empty($date)) return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return ($d && $d->format('Y-m-d') === $date) ? $date : null;
}

/**
 * Validate payment method against whitelist.
 * 
 * @param string $m The payment method to validate
 * @return string 'cash' or 'online', defaults to 'cash' if invalid
 */
function validatePaymentMethod($m) {
    return in_array($m, ['cash', 'online'], true) ? $m : 'cash';
}

/**
 * Validate a Nepal phone number.
 * 
 * Accepts 10-digit numbers starting with 98 or 97.
 * Returns true if empty (phone is optional in some contexts).
 * 
 * @param string $phone The phone number to validate
 * @return bool True if valid or empty, false otherwise
 */
function validatePhone($phone) {
    // Nepal: 98xxxxxxxx or 97xxxxxxxx (10 digits), or empty
    if (empty($phone)) return true;
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) !== 10) return false;
    return in_array(substr($digits, 0, 2), ['98', '97'], true);
}

/**
 * Strict email validation.
 * 
 * @param string $email The email to validate
 * @return bool True if valid, false otherwise
 */
function validateEmailStrict($email) {
    if (empty($email)) return false;
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate a cryptographically secure OTP code.
 * 
 * Uses random_int() for cryptographic security.
 * Pads with leading zeros to ensure consistent length.
 * 
 * @param int $length Number of digits (default: 6)
 * @return string The OTP code (e.g., "048291")
 */
function generateOTP($length = 6) {
    return str_pad(random_int(0, (int)(pow(10, $length) - 1)), $length, '0', STR_PAD_LEFT);
}

/**
 * Generate a unique order number.
 * 
 * Format: DDL-XXXXXX-YYMMDD
 * Where XXXXXX is 6 random hex characters, YYMMDD is current date.
 * 
 * @return string The order number (e.g., "DDL-A3F2B1-260810")
 */
function generateOrderNumber() {
    return 'DDL-' . strtoupper(bin2hex(random_bytes(3))) . '-' . date('ymd');
}

/**
 * Generate a sequential invoice number with yearly reset.
 * 
 * Format: DD-YYYY-NNNNNN
 * Uses atomic INSERT ... ON DUPLICATE KEY UPDATE to prevent race conditions.
 * 
 * @return string The invoice number (e.g., "DD-2026-000001")
 */
function generateInvoiceNumber() {
    $db = getDB();
    $year = date('Y');
    // Atomic increment using INSERT ... ON DUPLICATE KEY UPDATE
    $stmt = $db->prepare("INSERT INTO invoice_sequence (year_val, last_num) VALUES (?, 1)
        ON DUPLICATE KEY UPDATE last_num = last_num + 1");
    $stmt->execute([$year]);
    $num = (int)$db->lastInsertId();
    if ($num === 0) {
        // ON DUPLICATE KEY UPDATE path — fetch the new value
        $chk = $db->prepare("SELECT last_num FROM invoice_sequence WHERE year_val = ?");
        $chk->execute([$year]);
        $num = (int)$chk->fetchColumn();
    }
    return 'DD-' . $year . '-' . str_pad($num, 6, '0', STR_PAD_LEFT);
}

// ── JSON Response ─────────────────────────────────────────
function jsonResponse($data, $code = 200) {
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Check if a customer is logged in.
 * 
 * Validates that user_id exists in session, is an integer, and is positive.
 * 
 * @return bool True if customer is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id'])
        && is_int($_SESSION['user_id'])
        && $_SESSION['user_id'] > 0;
}

/**
 * Check if an admin is logged in.
 * 
 * Validates that admin_id exists in session, is an integer, and is positive.
 * 
 * @return bool True if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id'])
        && is_int($_SESSION['admin_id'])
        && $_SESSION['admin_id'] > 0;
}

/**
 * Require customer authentication.
 * 
 * Redirects to login page if not logged in.
 * Regenerates session ID every 15 minutes to prevent session fixation.
 * 
 * OWASP A07: Session Fixation Prevention
 */
function requireLogin() {
    if (!isLoggedIn()) {
        if (!headers_sent()) header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
    // Session fixation defence — regenerate ID periodically
    if (empty($_SESSION['last_regen']) || (time() - $_SESSION['last_regen']) > 900) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

/**
 * Require admin authentication.
 * 
 * Redirects to admin login if not logged in.
 * Regenerates session ID every 15 minutes to prevent session fixation.
 * 
 * OWASP A07: Session Fixation Prevention
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        if (!headers_sent()) header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
    if (empty($_SESSION['admin_last_regen']) || (time() - $_SESSION['admin_last_regen']) > 900) {
        session_regenerate_id(true);
        $_SESSION['admin_last_regen'] = time();
    }
}

/**
 * Log security events to file.
 * 
 * Records timestamp, event type, IP address, details (JSON), and user agent.
 * Used for audit trail and intrusion detection.
 * 
 * @param string $event Event type (e.g., 'LOGIN_FAILED', 'CSRF_VIOLATION')
 * @param array $details Additional event details
 * 
 * OWASP A09: Security Logging and Monitoring
 */
function securityLog($event, $details = []) {
    $dir = __DIR__ . '/../logs/';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $ip    = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
    $ua    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);
    $entry = date('Y-m-d H:i:s') . " [$event] IP:$ip | " . json_encode($details) . " | UA:$ua\n";
    @file_put_contents($dir . 'security.log', $entry, FILE_APPEND | LOCK_EX);
}
