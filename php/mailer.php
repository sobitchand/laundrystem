<?php
// ============================================================
// DD Laundry - Email Helper (PHPMailer)
// php/mailer.php
// ============================================================
// Requires: composer require phpmailer/phpmailer
// Or download PHPMailer manually into /vendor/phpmailer/

require_once __DIR__ . '/config.php';

// Use PHPMailer (ensure you've installed it via Composer or manually)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Auto-detect PHPMailer location
$phpmailerPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($phpmailerPath)) {
    require_once $phpmailerPath;
} else {
    // Fallback: manual includes
    $manualPath = __DIR__ . '/../phpmailer/src/';
    if (file_exists($manualPath)) {
        require_once $manualPath . 'Exception.php';
        require_once $manualPath . 'PHPMailer.php';
        require_once $manualPath . 'SMTP.php';
    }
}

function isSMTPConfigured() {
    return SMTP_USER !== 'your_email@gmail.com'
        && SMTP_PASS !== 'your_app_password'
        && SMTP_FROM !== 'your_email@gmail.com'
        && SMTP_USER !== ''
        && SMTP_PASS !== '';
}

function logMailFallback($toEmail, $subject, $htmlBody) {
    $dir = __DIR__ . '/../logs/';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($htmlBody)));
    $entry = "==== " . date('Y-m-d H:i:s') . " ====\n"
        . "To: {$toEmail}\n"
        . "Subject: {$subject}\n"
        . "Body: {$plain}\n\n";
    @file_put_contents($dir . 'mail.log', $entry, FILE_APPEND | LOCK_EX);
}

function sendMail($toEmail, $toName, $subject, $htmlBody, $plainBody = '') {
    if (!isSMTPConfigured()) {
        logMailFallback($toEmail, $subject, $htmlBody);
        return true;
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Fallback: mail() function plus local log for XAMPP debugging.
        logMailFallback($toEmail, $subject, $htmlBody);
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return mail($toEmail, $subject, $htmlBody, $headers);
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        // Fix SSL certificate verification issues (common with local dev)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("SMTP Mail error: " . $mail->ErrorInfo);
        // If SMTP fails (e.g. blocked port on free hosting), try PHP mail() as fallback
        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        if (mail($toEmail, $subject, $htmlBody, $headers)) {
            return true;
        }
        logMailFallback($toEmail, $subject, $htmlBody);
        return false;
    }
}

function getEmailTemplate($title, $bodyContent, $footerNote = '') {
    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Georgia,serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:40px 20px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#C0392B,#922B21);padding:35px 40px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:26px;letter-spacing:2px;font-family:Georgia,serif;">🧺 DD LAUNDRY</h1>
            <p style="margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:13px;letter-spacing:1px;">IMADOL, LALITPUR, NEPAL</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:40px;">
            ' . $bodyContent . '
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f8f8f8;padding:25px 40px;text-align:center;border-top:1px solid #eee;">
            <p style="margin:0;color:#999;font-size:12px;">' . ($footerNote ?: 'If you did not request this, please ignore this email.') . '</p>
            <p style="margin:8px 0 0;color:#bbb;font-size:11px;">© ' . date('Y') . ' DD Laundry · Imadol, Lalitpur, Nepal · +977 9749863285</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';
}

function sendOTPEmail($toEmail, $toName, $otp, $purpose = 'verification') {
    $purposeText = $purpose === 'reset' ? 'password reset' : 'account verification';
    $body = '
    <h2 style="color:#C0392B;margin:0 0 20px;font-family:Georgia,serif;">' . ucfirst($purposeText) . ' OTP</h2>
    <p style="color:#444;font-size:15px;line-height:1.6;">Hello <strong>' . htmlspecialchars($toName) . '</strong>,</p>
    <p style="color:#444;font-size:15px;line-height:1.6;">Your one-time password (OTP) for ' . $purposeText . ' is:</p>
    <div style="text-align:center;margin:30px 0;">
      <span style="display:inline-block;background:#C0392B;color:#fff;font-size:36px;font-weight:bold;letter-spacing:10px;padding:20px 40px;border-radius:8px;font-family:monospace;">' . $otp . '</span>
    </div>
    <p style="color:#666;font-size:14px;">This OTP is valid for <strong>' . OTP_EXPIRY_MINUTES . ' minutes</strong>. Do not share it with anyone.</p>';

    $html = getEmailTemplate('Your OTP - DD Laundry', $body);
    return sendMail($toEmail, $toName, 'Your OTP for ' . ucfirst($purposeText) . ' - DD Laundry', $html);
}

function sendOrderStatusEmail($toEmail, $toName, $orderNumber, $newStatus, $note = '') {
    $statusLabels = [
        'pending'    => ['label' => 'Order Received', 'color' => '#E67E22', 'icon' => '📋'],
        'confirmed'  => ['label' => 'Order Confirmed', 'color' => '#27AE60', 'icon' => '✅'],
        'picked_up'  => ['label' => 'Picked Up', 'color' => '#2980B9', 'icon' => '🚗'],
        'in_process' => ['label' => 'Being Cleaned', 'color' => '#8E44AD', 'icon' => '🧺'],
        'ready'      => ['label' => 'Ready for Delivery', 'color' => '#16A085', 'icon' => '✨'],
        'delivered'  => ['label' => 'Delivered', 'color' => '#27AE60', 'icon' => '🎉'],
        'cancelled'  => ['label' => 'Cancelled', 'color' => '#C0392B', 'icon' => '❌'],
    ];
    $s = $statusLabels[$newStatus] ?? ['label' => ucfirst($newStatus), 'color' => '#555', 'icon' => '📦'];

    $body = '
    <h2 style="color:#C0392B;margin:0 0 20px;font-family:Georgia,serif;">Order Status Update</h2>
    <p style="color:#444;font-size:15px;line-height:1.6;">Hello <strong>' . htmlspecialchars($toName) . '</strong>,</p>
    <p style="color:#444;font-size:15px;">Your order <strong style="color:#C0392B;">#' . htmlspecialchars($orderNumber) . '</strong> has been updated:</p>
    <div style="text-align:center;margin:30px 0;">
      <div style="display:inline-block;background:' . $s['color'] . ';color:#fff;padding:16px 32px;border-radius:8px;font-size:18px;font-weight:bold;">
        ' . $s['icon'] . ' ' . $s['label'] . '
      </div>
    </div>
    ' . ($note ? '<p style="background:#f9f9f9;padding:15px;border-left:4px solid #C0392B;color:#555;font-size:14px;margin:20px 0;"><strong>Note:</strong> ' . htmlspecialchars($note) . '</p>' : '') . '
    <p style="color:#666;font-size:14px;">Track your order anytime by logging into your dashboard at <a href="' . SITE_URL . '/dashboard.php" style="color:#C0392B;">' . SITE_URL . '</a></p>';

    $html = getEmailTemplate('Order Status Update - DD Laundry', $body, 'Thank you for choosing DD Laundry!');
    return sendMail($toEmail, $toName, $s['icon'] . ' Order #' . $orderNumber . ' - ' . $s['label'], $html);
}

function sendWelcomeEmail($toEmail, $toName) {
    $body = '
    <h2 style="color:#C0392B;margin:0 0 20px;font-family:Georgia,serif;">Welcome to DD Laundry! 🎉</h2>
    <p style="color:#444;font-size:15px;line-height:1.6;">Hello <strong>' . htmlspecialchars($toName) . '</strong>,</p>
    <p style="color:#444;font-size:15px;line-height:1.6;">Your account has been verified successfully. Welcome to DD Laundry — your trusted laundry partner in Imadol, Lalitpur!</p>
    <ul style="color:#555;font-size:14px;line-height:2;">
      <li>✅ Place laundry orders online</li>
      <li>✅ Track your orders in real-time</li>
      <li>✅ Pickup & drop service available</li>
    </ul>
    <div style="text-align:center;margin:30px 0;">
      <a href="' . SITE_URL . '/dashboard.php" style="background:#C0392B;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-size:15px;display:inline-block;">Go to Dashboard →</a>
    </div>';

    $html = getEmailTemplate('Welcome to DD Laundry', $body, 'Thank you for choosing DD Laundry!');
    return sendMail($toEmail, $toName, 'Welcome to DD Laundry!', $html);
}
