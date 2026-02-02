<?php
/**
 * Analytics Data Endpoint
 * GET /api/analytics.php?website_id=X&period=7d
 *
 * Returns analytics data for a client's website
 */

require_once __DIR__ . '/auth.php';

// Require authentication
requireAuth();

// Only accept GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

$websiteId = intval($_GET['website_id'] ?? 0);
$period = $_GET['period'] ?? '7d'; // 24h, 7d, 30d, 90d

if (!$websiteId) {
    errorResponse('Website ID required');
}

// Validate period
$validPeriods = ['24h', '7d', '30d', '90d'];
if (!in_array($period, $validPeriods)) {
    $period = '7d';
}

$pdo = getDbConnection();
$clientId = $_SESSION['client_id'];

// Verify website belongs to client
$stmt = $pdo->prepare("SELECT id, name, url FROM client_websites WHERE id = ? AND client_id = ? AND is_active = 1");
$stmt->execute([$websiteId, $clientId]);
$website = $stmt->fetch();

if (!$website) {
    errorResponse('Website not found or access denied', 404);
}

// Calculate date range
$now = new DateTime();
switch ($period) {
    case '24h':
        $startDate = (clone $now)->modify('-24 hours');
        $groupBy = 'HOUR(created_at)';
        $dateFormat = '%Y-%m-%d %H:00';
        break;
    case '7d':
        $startDate = (clone $now)->modify('-7 days');
        $groupBy = 'DATE(created_at)';
        $dateFormat = '%Y-%m-%d';
        break;
    case '30d':
        $startDate = (clone $now)->modify('-30 days');
        $groupBy = 'DATE(created_at)';
        $dateFormat = '%Y-%m-%d';
        break;
    case '90d':
        $startDate = (clone $now)->modify('-90 days');
        $groupBy = 'DATE(created_at)';
        $dateFormat = '%Y-%m-%d';
        break;
}

$startDateStr = $startDate->format('Y-m-d H:i:s');

// Get overview stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as pageviews,
        COUNT(DISTINCT visitor_id) as unique_visitors,
        COUNT(DISTINCT session_id) as sessions
    FROM analytics_pageviews
    WHERE website_id = ? AND created_at >= ?
");
$stmt->execute([$websiteId, $startDateStr]);
$overview = $stmt->fetch();

// Calculate bounce rate (sessions with only 1 page view)
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_sessions,
        SUM(CASE WHEN pv_count = 1 THEN 1 ELSE 0 END) as bounced_sessions
    FROM (
        SELECT session_id, COUNT(*) as pv_count
        FROM analytics_pageviews
        WHERE website_id = ? AND created_at >= ?
        GROUP BY session_id
    ) as session_counts
");
$stmt->execute([$websiteId, $startDateStr]);
$bounceData = $stmt->fetch();
$bounceRate = $bounceData['total_sessions'] > 0
    ? round(($bounceData['bounced_sessions'] / $bounceData['total_sessions']) * 100, 1)
    : 0;

// Get pageviews over time
$stmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(created_at, ?) as date_label,
        COUNT(*) as pageviews,
        COUNT(DISTINCT visitor_id) as visitors
    FROM analytics_pageviews
    WHERE website_id = ? AND created_at >= ?
    GROUP BY date_label
    ORDER BY MIN(created_at)
");
$stmt->execute([$dateFormat, $websiteId, $startDateStr]);
$timeline = $stmt->fetchAll();

// Get top pages
$stmt = $pdo->prepare("
    SELECT
        page_url,
        page_title,
        COUNT(*) as views,
        COUNT(DISTINCT visitor_id) as unique_views
    FROM analytics_pageviews
    WHERE website_id = ? AND created_at >= ?
    GROUP BY page_url, page_title
    ORDER BY views DESC
    LIMIT 10
");
$stmt->execute([$websiteId, $startDateStr]);
$topPages = $stmt->fetchAll();

// Clean up page URLs for display
foreach ($topPages as &$page) {
    $parsed = parse_url($page['page_url']);
    $page['path'] = $parsed['path'] ?? '/';
    if (!empty($parsed['query'])) {
        $page['path'] .= '?' . $parsed['query'];
    }
}

// Get traffic sources
$stmt = $pdo->prepare("
    SELECT
        CASE
            WHEN referrer_domain IS NULL OR referrer_domain = '' THEN 'Direct'
            WHEN referrer_domain LIKE '%google%' THEN 'Google'
            WHEN referrer_domain LIKE '%bing%' THEN 'Bing'
            WHEN referrer_domain LIKE '%facebook%' OR referrer_domain LIKE '%fb.%' THEN 'Facebook'
            WHEN referrer_domain LIKE '%instagram%' THEN 'Instagram'
            WHEN referrer_domain LIKE '%linkedin%' THEN 'LinkedIn'
            WHEN referrer_domain LIKE '%twitter%' OR referrer_domain LIKE '%t.co%' THEN 'Twitter/X'
            WHEN referrer_domain LIKE '%youtube%' THEN 'YouTube'
            ELSE referrer_domain
        END as source,
        COUNT(*) as visits,
        COUNT(DISTINCT visitor_id) as visitors
    FROM analytics_pageviews
    WHERE website_id = ? AND created_at >= ?
    GROUP BY source
    ORDER BY visits DESC
    LIMIT 10
");
$stmt->execute([$websiteId, $startDateStr]);
$trafficSources = $stmt->fetchAll();

// Get device breakdown
$stmt = $pdo->prepare("
    SELECT
        device_type,
        COUNT(*) as views,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM analytics_pageviews WHERE website_id = ? AND created_at >= ?), 1) as percentage
    FROM analytics_pageviews
    WHERE website_id = ? AND created_at >= ?
    GROUP BY device_type
    ORDER BY views DESC
");
$stmt->execute([$websiteId, $startDateStr, $websiteId, $startDateStr]);
$devices = $stmt->fetchAll();

// Get browser breakdown
$stmt = $pdo->prepare("
    SELECT
        browser,
        COUNT(*) as views,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM analytics_pageviews WHERE website_id = ? AND created_at >= ?), 1) as percentage
    FROM analytics_pageviews
    WHERE website_id = ? AND created_at >= ?
    GROUP BY browser
    ORDER BY views DESC
    LIMIT 5
");
$stmt->execute([$websiteId, $startDateStr, $websiteId, $startDateStr]);
$browsers = $stmt->fetchAll();

// Check if tracker is installed (has data in last 24 hours)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as recent_views
    FROM analytics_pageviews
    WHERE website_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute([$websiteId]);
$recentData = $stmt->fetch();
$trackerInstalled = $recentData['recent_views'] > 0;

// Build response
$response = [
    'website' => $website,
    'period' => $period,
    'tracker_installed' => $trackerInstalled,
    'overview' => [
        'pageviews' => (int)$overview['pageviews'],
        'unique_visitors' => (int)$overview['unique_visitors'],
        'sessions' => (int)$overview['sessions'],
        'bounce_rate' => $bounceRate
    ],
    'timeline' => $timeline,
    'top_pages' => $topPages,
    'traffic_sources' => $trafficSources,
    'devices' => $devices,
    'browsers' => $browsers
];

// Add tracker code snippet for easy installation
if (!$trackerInstalled) {
    $response['tracker_snippet'] = '<script src="https://www.diegoheduardo.com/tracker.js" data-site="' . $websiteId . '"></script>';
}

successResponse($response);
