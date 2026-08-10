<?php
// DD Laundry - Contact Form (OWASP Hardened)
require_once __DIR__ . '/config.php';
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error'=>'Method not allowed'],405);
requireCSRF();

$ip   = filter_var($_SERVER['REMOTE_ADDR']??'',FILTER_VALIDATE_IP)?:'unknown';
checkRateLimit("contact_{$ip}", 5, 600);

$name    = sanitize($_POST['name']    ?? '');
$email   = sanitizeEmail($_POST['email'] ?? '') ?: '';
$phone   = sanitize($_POST['phone']   ?? '');
$message = sanitize($_POST['message'] ?? '');

$errors = [];
if (!$name || strlen($name) < 2)    $errors['name'] = 'Name is required (min 2 characters)';
if (strlen($name) > 100)            $errors['name'] = 'Name too long';
if ($email && !validateEmailStrict($email)) $errors['email'] = 'Enter a valid email address';
if ($phone && !validatePhone($phone)) $errors['phone'] = 'Enter a valid Nepal phone number (98XXXXXXXX or 97XXXXXXXX)';
if (strlen($message) < 5)           $errors['message'] = 'Message is required (min 5 characters)';
if (strlen($message) > 2000)        $errors['message'] = 'Message too long';
if (!empty($errors)) jsonResponse(['error' => 'Please fix the errors below', 'fields' => $errors], 400);

$db   = getDB();
$stmt = $db->prepare("INSERT INTO contact_messages (name,email,phone,message) VALUES (?,?,?,?)");
$stmt->execute([$name, $email ?: null, $phone, $message]);

jsonResponse(['success'=>true,'message'=>'Message sent! We\'ll get back to you soon.']);
