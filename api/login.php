<?php
/**
 * Client Login Endpoint
 * POST /api/login.php
 *
 * Body: { email, password }
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

// Extract fields
$email = trim(strtolower($input['email'] ?? ''));
$password = $input['password'] ?? '';

// Validation
if (empty($email)) {
    errorResponse('Email is required');
}

if (empty($password)) {
    errorResponse('Password is required');
}

// Get client by email
$client = getClientByEmail($email);

if (!$client) {
    errorResponse('Invalid email or password', 401);
}

// Verify password
if (!verifyPassword($password, $client['password_hash'])) {
    errorResponse('Invalid email or password', 401);
}

// Log the user in
loginUser($client['id']);

// Return success with user data (excluding password)
successResponse([
    'client' => [
        'id' => $client['id'],
        'email' => $client['email'],
        'name' => $client['name']
    ]
], 'Login successful');
