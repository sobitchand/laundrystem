<?php
// ============================================================
// DD Laundry - Profile API (OWASP Hardened)
// A01: requireLogin + scoped to session user
// A03: Sanitised inputs, parameterised queries
// A07: CSRF on all mutations
// ============================================================
require_once __DIR__ . '/config.php';
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) jsonResponse(['error'=>'Authentication required'],401);

$ga = $_GET['action'] ?? '';
if ($ga === 'get') { getProfile(); exit; }

$action = $_POST['action'] ?? '';
switch ($action) {
    case 'update':          requireCSRF(); updateProfile();    break;
    case 'change_password': requireCSRF(); changePassword();   break;
    default: jsonResponse(['error'=>'Invalid action'],400);
}

function getProfile() {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id,full_name,email,phone,address,created_at FROM users WHERE id=?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['error'=>'User not found'],404);
    jsonResponse(['success'=>true,'user'=>$user]);
}

function updateProfile() {
    $db   = getDB();
    $name = sanitize($_POST['full_name'] ?? '');
    $phone= sanitize($_POST['phone']     ?? '');
    $addr = sanitize($_POST['address']   ?? '');

    $errors = [];
    if (!$name || strlen($name) < 2)    $errors['full_name'] = 'Full name is required (min 2 characters)';
    if (strlen($name) > 100)            $errors['full_name'] = 'Name too long (max 100 characters)';
    if (!$phone)                          $errors['phone'] = 'Phone number is required';
    if (!validatePhone($phone))         $errors['phone'] = 'Enter a valid Nepal phone number (98XXXXXXXX or 97XXXXXXXX)';
    if (strlen($phone) > 20)            $errors['phone'] = 'Phone number too long';
    if (strlen($addr) > 500)            $errors['address'] = 'Address too long (max 500 characters)';
    if (!empty($errors)) jsonResponse(['error' => 'Please fix the errors below', 'fields' => $errors], 400);

    $db->prepare("UPDATE users SET full_name=?,phone=?,address=? WHERE id=?")
       ->execute([$name,$phone,$addr,(int)$_SESSION['user_id']]);
    $_SESSION['user_name'] = $name;
    jsonResponse(['success'=>true,'message'=>'Profile updated successfully!']);
}

function changePassword() {
    $db      = getDB();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (!$current)                        $errors['current_password'] = 'Current password is required';
    if (strlen($new) < 8)                 $errors['new_password'] = 'Password must be at least 8 characters';
    if (strlen($new) > 128)               $errors['new_password'] = 'Password too long';
    if ($new !== $confirm)                $errors['confirm_password'] = 'Passwords do not match';
    if (!empty($errors)) jsonResponse(['error' => 'Please fix the errors below', 'fields' => $errors], 400);

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id=?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current, $user['password_hash'])) {
        securityLog('CHANGE_PASS_FAILED',['user_id'=>$_SESSION['user_id']]);
        jsonResponse(['error'=>'Current password is incorrect'],400);
    }

    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
    $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,(int)$_SESSION['user_id']]);
    securityLog('PASSWORD_CHANGED',['user_id'=>$_SESSION['user_id']]);
    jsonResponse(['success'=>true,'message'=>'Password changed successfully!']);
}
