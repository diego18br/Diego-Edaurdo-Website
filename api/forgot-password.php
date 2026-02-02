<?php
/**
 * Forgot Password Endpoint
 * POST /api/forgot-password.php
 *
 * Body: { email }
 * Sends a password reset email with a secure token
 */

require_once __DIR__ . '/auth.php';
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

$email = trim(strtolower($input['email'] ?? ''));

if (empty($email)) {
    errorResponse('Email is required');
}

if (!isValidEmail($email)) {
    errorResponse('Invalid email format');
}

// Check if client exists
$client = getClientByEmail($email);

// Always return success to prevent email enumeration attacks
// But only send email if client exists
if ($client) {
    // Generate secure token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);

    $pdo = getDbConnection();

    // Invalidate any existing unused tokens for this client
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE client_id = ? AND used_at IS NULL");
    $stmt->execute([$client['id']]);

    // Insert new token
    $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (client_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$client['id'], $token, $expiresAt]);

    // Build reset URL
    $resetUrl = SITE_URL . '/reset-password.html?token=' . $token;

    // Send email
    $to = $client['email'];
    $subject = 'Reset Your Password - Diego Eduardo Client Portal';

    $htmlMessage = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; padding: 20px 0; border-bottom: 2px solid #a2a680; }
            .content { padding: 30px 0; }
            .button { display: inline-block; padding: 12px 30px; background-color: #a2a680; color: #1a1a1a !important; text-decoration: none; border-radius: 5px; font-weight: bold; }
            .footer { padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 style="color: #1a1a1a; margin: 0;">Diego Eduardo</h1>
                <p style="color: #666; margin: 5px 0 0 0;">Client Portal</p>
            </div>
            <div class="content">
                <p>Hi ' . htmlspecialchars($client['name']) . ',</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <p style="text-align: center; margin: 30px 0;">
                    <a href="' . $resetUrl . '" class="button">Reset Password</a>
                </p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn\'t request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
                <p style="font-size: 12px; color: #666; margin-top: 20px;">If the button doesn\'t work, copy and paste this link into your browser:<br>' . $resetUrl . '</p>
            </div>
            <div class="footer">
                <p>This email was sent by Diego Eduardo Client Portal.<br>
                &copy; ' . date('Y') . ' Diego Eduardo. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';

    // Plain text version
    $textMessage = "Hi " . $client['name'] . ",\n\n";
    $textMessage .= "We received a request to reset your password.\n\n";
    $textMessage .= "Click this link to reset your password:\n";
    $textMessage .= $resetUrl . "\n\n";
    $textMessage .= "This link will expire in 1 hour.\n\n";
    $textMessage .= "If you didn't request a password reset, you can safely ignore this email.\n\n";
    $textMessage .= "- Diego Eduardo Client Portal";

    // Send email via SMTP
    sendEmail($to, $subject, $htmlMessage, $textMessage);
}

// Always return success (prevents email enumeration)
successResponse([], 'If an account with that email exists, we\'ve sent password reset instructions.');
