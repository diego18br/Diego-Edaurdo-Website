<?php
/**
 * Metrics Endpoint
 * GET /api/metrics.php?website_id=1
 *
 * Returns cached metrics for a specific website
 * Fetches fresh data if cache is empty/expired
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/services/MetricsCache.php';
require_once __DIR__ . '/services/UptimeService.php';
require_once __DIR__ . '/services/PerformanceService.php';

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

// Validate website_id parameter
$websiteId = filter_input(INPUT_GET, 'website_id', FILTER_VALIDATE_INT);
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

// Initialize services
$cache = new MetricsCache($pdo);
$uptimeService = new UptimeService();
$performanceService = new PerformanceService();

$metrics = [
    'uptime' => null,
    'performance' => null
];

// Get uptime metrics
$uptimeData = $cache->get($websiteId, 'uptime');
if (!$uptimeData || $uptimeData['is_stale']) {
    // Fetch fresh data
    $freshUptime = $uptimeService->getMonitorStatus($website['uptime_monitor_id']);
    if ($freshUptime['available']) {
        $cache->set($websiteId, 'uptime', $freshUptime);
        $uptimeData = $freshUptime;
        $uptimeData['cached_at'] = date('c');
        $uptimeData['is_stale'] = false;
    } elseif ($uptimeData) {
        // Keep stale data if fresh fetch failed
        $uptimeData['is_stale'] = true;
    } else {
        $uptimeData = $freshUptime; // No cache, use error response
    }
}
$metrics['uptime'] = $uptimeData;

// Get performance metrics
$performanceData = $cache->get($websiteId, 'performance');
if (!$performanceData || $performanceData['is_stale']) {
    // Fetch fresh data
    $freshPerformance = $performanceService->getPerformanceMetrics($website['url']);
    if ($freshPerformance['available']) {
        $cache->set($websiteId, 'performance', $freshPerformance);
        $performanceData = $freshPerformance;
        $performanceData['cached_at'] = date('c');
        $performanceData['is_stale'] = false;
    } elseif ($performanceData) {
        // Keep stale data if fresh fetch failed
        $performanceData['is_stale'] = true;
    } else {
        $performanceData = $freshPerformance; // No cache, use error response
    }
}
$metrics['performance'] = $performanceData;

// Check refresh availability
$refreshStatus = $cache->canRefresh($websiteId, $client['id']);

successResponse([
    'website_id' => $websiteId,
    'metrics' => $metrics,
    'refresh' => [
        'allowed' => $refreshStatus['allowed'],
        'remaining' => $refreshStatus['remaining'],
        'next_available' => $refreshStatus['next_available']
    ]
]);
