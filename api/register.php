<?php
/**
 * Client Registration Endpoint
 * POST /api/register.php
 *
 * Body: { name, email, password, confirmPassword }
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

// Extract and validate fields
$name = trim($input['name'] ?? '');
$email = trim(strtolower($input['email'] ?? ''));
$password = $input['password'] ?? '';
$confirmPassword = $input['confirmPassword'] ?? '';

// Validation
if (empty($name)) {
    errorResponse('Name is required');
}

if (empty($email)) {
    errorResponse('Email is required');
}

if (!isValidEmail($email)) {
    errorResponse('Please enter a valid email address');
}

if (empty($password)) {
    errorResponse('Password is required');
}

if (!isValidPassword($password)) {
    errorResponse('Password must be at least 8 characters');
}

if ($password !== $confirmPassword) {
    errorResponse('Passwords do not match');
}

// Check if email already exists
$existingClient = getClientByEmail($email);
if ($existingClient) {
    errorResponse('An account with this email already exists');
}

try {
    // Create Stripe customer
    $stripeCustomerId = null;

    // Initialize Stripe (using cURL since we don't have Composer)
    $stripeData = [
        'email' => $email,
        'name' => $name,
        'metadata[source]' => 'website_portal'
    ];

    $ch = curl_init('https://api.stripe.com/v1/customers');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($stripeData));
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $stripeCustomer = json_decode($response, true);
        $stripeCustomerId = $stripeCustomer['id'];
    } else {
        // Log error but continue - we can create Stripe customer later
        error_log("Stripe customer creation failed: " . $response);
    }

    // Hash password
    $passwordHash = hashPassword($password);

    // Create client in database
    $clientId = createClient($email, $passwordHash, $name, $stripeCustomerId);

    // Log the user in
    loginUser($clientId);

    // Send notification email to admin
    $adminEmail = 'diego@diegoheduardo.com';
    $notifySubject = 'New Client Registration - ' . $name;

    $notifyHtml = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #1a1a1a; color: #fff; padding: 25px; text-align: center; }
        .header h1 { margin: 0; color: #4ade80; font-size: 22px; }
        .content { padding: 30px; }
        .info-box { background: #f9f9f9; border-radius: 8px; padding: 20px; margin: 15px 0; }
        .info-item { margin-bottom: 12px; }
        .info-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 16px; color: #333; font-weight: 500; margin-top: 2px; }
        .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Client Registration</h1>
        </div>
        <div class="content">
            <p>A new client has registered on your portal:</p>
            <div class="info-box">
                <div class="info-item">
                    <div class="info-label">Name</div>
                    <div class="info-value">' . htmlspecialchars($name) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value">' . htmlspecialchars($email) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Client ID</div>
                    <div class="info-value">#' . $clientId . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Stripe Customer</div>
                    <div class="info-value">' . ($stripeCustomerId ?: 'Not created') . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Registered At</div>
                    <div class="info-value">' . date('F j, Y \a\t g:i A') . '</div>
                </div>
            </div>
            <p style="margin-top: 20px; font-size: 14px; color: #666;">You may want to assign websites to this client in the database.</p>
        </div>
        <div class="footer">
            Diego Eduardo Client Portal
        </div>
    </div>
</body>
</html>';

    $notifyText = "New Client Registration\n";
    $notifyText .= "=======================\n\n";
    $notifyText .= "Name: " . $name . "\n";
    $notifyText .= "Email: " . $email . "\n";
    $notifyText .= "Client ID: #" . $clientId . "\n";
    $notifyText .= "Stripe Customer: " . ($stripeCustomerId ?: 'Not created') . "\n";
    $notifyText .= "Registered At: " . date('F j, Y \a\t g:i A') . "\n";

    sendEmail($adminEmail, $notifySubject, $notifyHtml, $notifyText);

    // Return success
    successResponse([
        'client' => [
            'id' => $clientId,
            'email' => $email,
            'name' => $name
        ]
    ], 'Account created successfully');

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    errorResponse('Registration failed. Please try again.', 500);
}
