<?php
/**
 * Refresh Metrics Endpoint
 * POST /api/refresh-metrics.php
 *
 * Force refresh metrics from external APIs (rate limited)
 * Request body: { "website_id": 1 }
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/services/MetricsCache.php';
require_once __DIR__ . '/services/UptimeService.php';
require_once __DIR__ . '/services/PerformanceService.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
$websiteId = filter_var($input['website_id'] ?? null, FILTER_VALIDATE_INT);

if (!$websiteId) {
    errorResponse('Invalid website_id parameter', 400);
}

$pdo = getDbConnection();

// Verify this website belongs to the client
$stmt = $pdo->prepare("
    SELECT id, url, uptime_monitor_id
    FROM client_websites
    WHERE id = ? AND client_id = ? AND is_active = 1
");
$stmt->execute([$websiteId, $client['id']]);
$website = $stmt->fetch();

if (!$website) {
    errorResponse('Website not found or access denied', 403);
}

// Initialize cache service and check rate limit
$cache = new MetricsCache($pdo);
$refreshStatus = $cache->canRefresh($websiteId, $client['id']);

if (!$refreshStatus['allowed']) {
    http_response_code(429);
    jsonResponse([
        'error' => 'Rate limit exceeded. Please wait before refreshing again.',
        'retry_after' => 3600,
        'next_available' => $refreshStatus['next_available']
    ]);
}

// Log this refresh attempt
$cache->logRefresh($websiteId, $client['id']);

// Initialize services
$uptimeService = new UptimeService();
$performanceService = new PerformanceService();

$metrics = [
    'uptime' => null,
    'performance' => null
];
$refreshed = [];

// Fetch fresh uptime data
$uptimeData = $uptimeService->getMonitorStatus($website['uptime_monitor_id']);
if ($uptimeData['available']) {
    $cache->set($websiteId, 'uptime', $uptimeData);
    $refreshed[] = 'uptime';
}
$uptimeData['cached_at'] = date('c');
$uptimeData['is_stale'] = false;
$metrics['uptime'] = $uptimeData;

// Fetch fresh performance data
$performanceData = $performanceService->getPerformanceMetrics($website['url']);
if ($performanceData['available']) {
    $cache->set($websiteId, 'performance', $performanceData);
    $refreshed[] = 'performance';
}
$performanceData['cached_at'] = date('c');
$performanceData['is_stale'] = false;
$metrics['performance'] = $performanceData;

// Get updated refresh status
$newRefreshStatus = $cache->canRefresh($websiteId, $client['id']);

successResponse([
    'refreshed' => $refreshed,
    'metrics' => $metrics,
    'refresh' => [
        'allowed' => $newRefreshStatus['allowed'],
        'remaining' => $newRefreshStatus['remaining'],
        'next_available' => $newRefreshStatus['next_available']
    ]
], 'Metrics refreshed successfully');
