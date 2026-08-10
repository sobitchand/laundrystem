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

// ── Security Headers (A05) ────────────────────────────────
function sendSecurityHeaders() {
    if (headers_sent()) return;
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(self), camera=(), microphone=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src https://fonts.gstatic.com; img-src 'self' data: https: https://*.openstreetmap.org; connect-src 'self' https://*.openstreetmap.org; frame-src https://www.google.com;");
}

// ── PDO (A03: Injection — emulate_prepares OFF) ───────────
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

// ── CSRF (A01: Broken Access Control) ─────────────────────
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCSRF() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($token)) {
        securityLog('CSRF_VIOLATION', ['url' => $_SERVER['REQUEST_URI'] ?? '']);
        jsonResponse(['error' => 'Invalid security token. Refresh and try again.'], 403);
    }
}

// ── Rate Limiting (A07: Brute Force) ─────────────────────
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

function clearRateLimit($key) {
    $dir  = __DIR__ . '/../logs/rate_limits/';
    $file = $dir . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key) . '.json';
    if (file_exists($file)) @unlink($file);
}

// ── Input Sanitisation (A03) ──────────────────────────────
function sanitize($input) {
    if (is_array($input)) return array_map('sanitize', $input);
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitizeEmail($email) {
    $email = filter_var(trim((string)$email), FILTER_SANITIZE_EMAIL);
    $validated = filter_var($email, FILTER_VALIDATE_EMAIL);
    return $validated ? strtolower($validated) : false;
}

function validateDate($date) {
    if (empty($date)) return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return ($d && $d->format('Y-m-d') === $date) ? $date : null;
}

function validatePaymentMethod($m) {
    return in_array($m, ['cash', 'online'], true) ? $m : 'cash';
}

function validatePhone($phone) {
    // Nepal: 98xxxxxxxx or 97xxxxxxxx (10 digits), or empty
    if (empty($phone)) return true;
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) !== 10) return false;
    return in_array(substr($digits, 0, 2), ['98', '97'], true);
}

function validateEmailStrict($email) {
    if (empty($email)) return false;
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ── Helpers ───────────────────────────────────────────────
function generateOTP($length = 6) {
    return str_pad(random_int(0, (int)(pow(10, $length) - 1)), $length, '0', STR_PAD_LEFT);
}

function generateOrderNumber() {
    return 'DDL-' . strtoupper(bin2hex(random_bytes(3))) . '-' . date('ymd');
}

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

// ── Auth Guards ───────────────────────────────────────────
function isLoggedIn() {
    return isset($_SESSION['user_id'])
        && is_int($_SESSION['user_id'])
        && $_SESSION['user_id'] > 0;
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_id'])
        && is_int($_SESSION['admin_id'])
        && $_SESSION['admin_id'] > 0;
}

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

// ── Security Logging (A09) ────────────────────────────────
function securityLog($event, $details = []) {
    $dir = __DIR__ . '/../logs/';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $ip    = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
    $ua    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);
    $entry = date('Y-m-d H:i:s') . " [$event] IP:$ip | " . json_encode($details) . " | UA:$ua\n";
    @file_put_contents($dir . 'security.log', $entry, FILE_APPEND | LOCK_EX);
}
