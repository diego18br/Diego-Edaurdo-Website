<?php
/**
 * Client Registration Endpoint
 * POST /api/register.php
 *
 * Body: { name, email, password, confirmPassword }
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
