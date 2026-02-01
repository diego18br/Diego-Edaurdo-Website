<?php
/**
 * Websites Endpoint
 * GET /api/websites.php
 *
 * Returns all websites assigned to the authenticated client
 */

require_once __DIR__ . '/auth.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

// Check if logged in
if (!isLoggedIn()) {
    errorResponse('Unauthorized. Please log in.', 401);
}

// Get current user
$client = getCurrentUser();
if (!$client) {
    logoutUser();
    errorResponse('Session invalid', 401);
}

$pdo = getDbConnection();

// Fetch websites assigned to this client
$stmt = $pdo->prepare("
    SELECT id, name, url, uptime_monitor_id, is_active, created_at
    FROM client_websites
    WHERE client_id = ? AND is_active = 1
    ORDER BY name ASC
");
$stmt->execute([$client['id']]);
$websites = $stmt->fetchAll();

// Format response
$formattedWebsites = array_map(function($website) {
    return [
        'id' => (int)$website['id'],
        'name' => $website['name'],
        'url' => $website['url'],
        'has_uptime_monitor' => !empty($website['uptime_monitor_id']),
        'created_at' => $website['created_at']
    ];
}, $websites);

successResponse([
    'websites' => $formattedWebsites,
    'count' => count($formattedWebsites)
]);
