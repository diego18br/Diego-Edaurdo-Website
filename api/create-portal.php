<?php
/**
 * Create Stripe Customer Portal Session
 * POST /api/create-portal.php
 *
 * Creates a Stripe Customer Portal session and returns the URL
 * Client must be logged in and have a Stripe customer ID
 */

require_once __DIR__ . '/auth.php';

// Require authentication
requireAuth();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// Get current user
$client = getCurrentUser();

if (!$client) {
    errorResponse('User not found', 404);
}

// Check if client has Stripe customer ID
if (empty($client['stripe_customer_id'])) {
    // Try to create a Stripe customer now
    $stripeData = [
        'email' => $client['email'],
        'name' => $client['name'],
        'metadata[source]' => 'website_portal',
        'metadata[client_id]' => $client['id']
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

        // Update in database
        updateClientStripeId($client['id'], $stripeCustomerId);
        $client['stripe_customer_id'] = $stripeCustomerId;
    } else {
        error_log("Failed to create Stripe customer: " . $response);
        errorResponse('Unable to connect to payment system. Please contact support.', 500);
    }
}

// Create Stripe Customer Portal session
$portalData = [
    'customer' => $client['stripe_customer_id'],
    'return_url' => PORTAL_RETURN_URL
];

$ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($portalData));
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $error = json_decode($response, true);
    error_log("Stripe portal session error: " . $response);

    // Check for common errors
    if (isset($error['error']['code']) && $error['error']['code'] === 'portal_not_activated') {
        errorResponse('Payment portal is not configured. Please contact support.', 500);
    }

    errorResponse('Unable to access payment portal. Please try again.', 500);
}

$portalSession = json_decode($response, true);

// Return the portal URL
successResponse([
    'url' => $portalSession['url']
], 'Portal session created');
