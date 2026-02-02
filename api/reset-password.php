<?php
/**
 * Reset Password Endpoint
 * POST /api/reset-password.php
 *
 * Body: { token, password }
 * Validates the token and updates the user's password
 */

require_once __DIR__ . '/auth.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    errorResponse('Invalid JSON input');
}

$token = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

// Validation
if (empty($token)) {
    errorResponse('Reset token is required');
}

if (empty($password)) {
    errorResponse('New password is required');
}

if (!isValidPassword($password)) {
    errorResponse('Password must be at least 8 characters');
}

$pdo = getDbConnection();

// Find valid token
$stmt = $pdo->prepare("
    SELECT prt.*, c.email, c.name
    FROM password_reset_tokens prt
    JOIN clients c ON c.id = prt.client_id
    WHERE prt.token = ?
    AND prt.used_at IS NULL
    AND prt.expires_at > NOW()
");
$stmt->execute([$token]);
$resetToken = $stmt->fetch();

if (!$resetToken) {
    errorResponse('Invalid or expired reset link. Please request a new password reset.', 400);
}

// Update password
$passwordHash = hashPassword($password);
$stmt = $pdo->prepare("UPDATE clients SET password_hash = ? WHERE id = ?");
$stmt->execute([$passwordHash, $resetToken['client_id']]);

// Mark token as used
$stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
$stmt->execute([$resetToken['id']]);

// Invalidate all other tokens for this user (security measure)
$stmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE client_id = ? AND used_at IS NULL");
$stmt->execute([$resetToken['client_id']]);

successResponse([], 'Your password has been reset successfully. You can now log in with your new password.');
