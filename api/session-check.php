<?php
/**
 * Session Check Endpoint
 * GET /api/session-check.php
 *
 * Returns current user data if logged in, or 401 if not
 */

require_once __DIR__ . '/auth.php';

// Check if logged in
if (!isLoggedIn()) {
    errorResponse('Not authenticated', 401);
}

// Get current user
$client = getCurrentUser();

if (!$client) {
    // Session exists but user not found in DB (deleted account?)
    logoutUser();
    errorResponse('Session invalid', 401);
}

// Return user data
successResponse([
    'client' => [
        'id' => $client['id'],
        'email' => $client['email'],
        'name' => $client['name']
    ]
]);
