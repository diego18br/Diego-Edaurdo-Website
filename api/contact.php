<?php
/**
 * Contact Form Submission Endpoint
 * POST /api/contact.php
 *
 * Body: { name, email, subject, service, message }
 * Stores submission in database and sends email notification
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/services/Mailer.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    errorResponse('Invalid JSON input');
}

// Extract and sanitize fields
$name = trim($input['name'] ?? '');
$email = trim(strtolower($input['email'] ?? ''));
$subject = trim($input['subject'] ?? '');
$service = trim($input['service'] ?? '');
$message = trim($input['message'] ?? '');

// Validation
if (empty($name)) {
    errorResponse('Name is required');
}

if (strlen($name) > 255) {
    errorResponse('Name is too long');
}

if (empty($email)) {
    errorResponse('Email is required');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    errorResponse('Invalid email format');
}

if (empty($subject)) {
    errorResponse('Subject is required');
}

if (strlen($subject) > 500) {
    errorResponse('Subject is too long');
}

$validServices = ['Videography', 'Audio', 'IT', 'Web Development', 'SEO', 'Other'];
if (empty($service) || !in_array($service, $validServices)) {
    errorResponse('Please select a valid service');
}

if (empty($message)) {
    errorResponse('Message is required');
}

if (strlen($message) > 10000) {
    errorResponse('Message is too long');
}

// Get client IP address
$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
if ($ipAddress && strpos($ipAddress, ',') !== false) {
    $ipAddress = trim(explode(',', $ipAddress)[0]);
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

$pdo = getDbConnection();

// Rate limiting - check submissions from this IP in the last hour
if ($ipAddress) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM contact_submissions
        WHERE ip_address = ?
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$ipAddress]);
    $result = $stmt->fetch();

    if ($result['count'] >= CONTACT_RATE_LIMIT) {
        errorResponse('Too many submissions. Please try again later.', 429);
    }
}

// Insert submission into database
$stmt = $pdo->prepare("
    INSERT INTO contact_submissions (name, email, subject, service, message, ip_address, user_agent)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([$name, $email, $subject, $service, $message, $ipAddress, $userAgent]);

$submissionId = $pdo->lastInsertId();

// Send email notification
$to = CONTACT_NOTIFICATION_EMAIL;
$emailSubject = "New Contact Form: " . $subject;

$htmlMessage = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #1a1a1a; color: #fff; padding: 25px; text-align: center; }
        .header h1 { margin: 0; color: #a2a680; font-size: 24px; }
        .content { padding: 30px; }
        .field { margin-bottom: 20px; }
        .field-label { font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .field-value { color: #333; font-size: 16px; }
        .message-box { background: #f9f9f9; border-left: 4px solid #a2a680; padding: 15px; margin-top: 10px; }
        .footer { background: #f5f5f5; padding: 15px 30px; font-size: 12px; color: #666; border-top: 1px solid #eee; }
        .badge { display: inline-block; background: #a2a680; color: #1a1a1a; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Contact Form Submission</h1>
        </div>
        <div class="content">
            <div class="field">
                <div class="field-label">From</div>
                <div class="field-value">' . htmlspecialchars($name) . '</div>
            </div>
            <div class="field">
                <div class="field-label">Email</div>
                <div class="field-value"><a href="mailto:' . htmlspecialchars($email) . '">' . htmlspecialchars($email) . '</a></div>
            </div>
            <div class="field">
                <div class="field-label">Service Interested In</div>
                <div class="field-value"><span class="badge">' . htmlspecialchars($service) . '</span></div>
            </div>
            <div class="field">
                <div class="field-label">Subject</div>
                <div class="field-value">' . htmlspecialchars($subject) . '</div>
            </div>
            <div class="field">
                <div class="field-label">Message</div>
                <div class="message-box">' . nl2br(htmlspecialchars($message)) . '</div>
            </div>
        </div>
        <div class="footer">
            Submission ID: #' . $submissionId . ' | Received: ' . date('F j, Y \a\t g:i A') . '
        </div>
    </div>
</body>
</html>';

// Plain text version
$textMessage = "New Contact Form Submission\n";
$textMessage .= "============================\n\n";
$textMessage .= "From: " . $name . "\n";
$textMessage .= "Email: " . $email . "\n";
$textMessage .= "Service: " . $service . "\n";
$textMessage .= "Subject: " . $subject . "\n\n";
$textMessage .= "Message:\n" . $message . "\n\n";
$textMessage .= "---\n";
$textMessage .= "Submission ID: #" . $submissionId . "\n";
$textMessage .= "Received: " . date('F j, Y \a\t g:i A');

// Send notification email to Diego via SMTP
sendEmail($to, $emailSubject, $htmlMessage, $textMessage, $email, $name);

// Send confirmation email to the user
$confirmSubject = "Thanks for reaching out! - Diego Eduardo";

$confirmHtml = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.8; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #1a1a1a; color: #fff; padding: 30px; text-align: center; }
        .header h1 { margin: 0; color: #a2a680; font-size: 28px; }
        .header p { margin: 10px 0 0 0; color: #888; font-size: 14px; }
        .content { padding: 35px; }
        .content p { margin: 0 0 20px 0; }
        .summary { background: #f9f9f9; border-radius: 8px; padding: 20px; margin: 25px 0; }
        .summary h3 { margin: 0 0 15px 0; color: #1a1a1a; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-item { margin-bottom: 10px; }
        .summary-label { color: #666; font-size: 12px; }
        .summary-value { color: #333; font-weight: 500; }
        .message-preview { background: #fff; border-left: 3px solid #a2a680; padding: 12px 15px; margin-top: 10px; font-style: italic; color: #555; }
        .footer { background: #f5f5f5; padding: 20px 35px; font-size: 12px; color: #666; border-top: 1px solid #eee; text-align: center; }
        .footer a { color: #a2a680; text-decoration: none; }
        .social-links { margin-top: 15px; }
        .social-links a { display: inline-block; margin: 0 8px; color: #666; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Diego Eduardo</h1>
            <p>Creative Technologist</p>
        </div>
        <div class="content">
            <p>Hi ' . htmlspecialchars($name) . ',</p>
            <p>Thank you for reaching out! I\'ve received your message and wanted to let you know that I\'ll review it and get back to you as soon as possible, typically within 1-2 business days.</p>

            <div class="summary">
                <h3>Your Message Summary</h3>
                <div class="summary-item">
                    <div class="summary-label">Service</div>
                    <div class="summary-value">' . htmlspecialchars($service) . '</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Subject</div>
                    <div class="summary-value">' . htmlspecialchars($subject) . '</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Your Message</div>
                    <div class="message-preview">' . htmlspecialchars(strlen($message) > 200 ? substr($message, 0, 200) . '...' : $message) . '</div>
                </div>
            </div>

            <p>In the meantime, feel free to check out my portfolio at <a href="https://www.diegoheduardo.com" style="color: #a2a680;">diegoheduardo.com</a>.</p>

            <p>Looking forward to connecting with you!</p>

            <p style="margin-top: 30px;">
                Best regards,<br>
                <strong>Diego Eduardo</strong><br>
                <span style="color: #666; font-size: 14px;">Creative Technologist</span>
            </p>
        </div>
        <div class="footer">
            <p>This is an automated confirmation. Please do not reply directly to this email.</p>
            <p>If you have additional questions, email me at <a href="mailto:diego@diegoheduardo.com">diego@diegoheduardo.com</a></p>
            <div class="social-links">
                <a href="https://www.instagram.com/dsudo.ai/">Instagram</a> |
                <a href="https://www.linkedin.com/in/diego-eduardo/">LinkedIn</a>
            </div>
            <p style="margin-top: 15px;">&copy; ' . date('Y') . ' Diego Eduardo. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';

$confirmText = "Hi " . $name . ",\n\n";
$confirmText .= "Thank you for reaching out! I've received your message and wanted to let you know that I'll review it and get back to you as soon as possible, typically within 1-2 business days.\n\n";
$confirmText .= "YOUR MESSAGE SUMMARY\n";
$confirmText .= "--------------------\n";
$confirmText .= "Service: " . $service . "\n";
$confirmText .= "Subject: " . $subject . "\n";
$confirmText .= "Message: " . (strlen($message) > 200 ? substr($message, 0, 200) . '...' : $message) . "\n\n";
$confirmText .= "In the meantime, feel free to check out my portfolio at https://www.diegoheduardo.com\n\n";
$confirmText .= "Looking forward to connecting with you!\n\n";
$confirmText .= "Best regards,\n";
$confirmText .= "Diego Eduardo\n";
$confirmText .= "Creative Technologist\n\n";
$confirmText .= "---\n";
$confirmText .= "This is an automated confirmation. Please do not reply directly to this email.\n";
$confirmText .= "For questions, email diego@diegoheduardo.com";

// Send confirmation to user
sendEmail($email, $confirmSubject, $confirmHtml, $confirmText);

successResponse([], 'Thank you for your message! I\'ll get back to you soon.');
