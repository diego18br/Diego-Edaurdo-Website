<?php
/**
 * Client Logout Endpoint
 * POST /api/logout.php
 */

require_once __DIR__ . '/auth.php';

// Accept both GET and POST for convenience
logoutUser();

successResponse([], 'Logged out successfully');
