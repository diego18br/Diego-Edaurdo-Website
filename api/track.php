<?php
/**
 * Analytics Tracking Endpoint
 * POST /api/track.php
 *
 * Receives page view data from the JS tracker
 * Designed to be fast and lightweight
 */

// Minimal config - don't load full config to keep it fast
$dbHost = 'localhost';
$dbName = 'u641299609_DiegoEduardo';
$dbUser = 'u641299609_deduardo';
$dbPass = '!D12031994e!2810';

// CORS headers for cross-origin tracking
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['site'])) {
    http_response_code(400);
    exit;
}

// Extract data
$siteId = intval($input['site']);
$visitorId = substr($input['vid'] ?? '', 0, 64);
$sessionId = substr($input['sid'] ?? '', 0, 64);
$pageUrl = substr($input['url'] ?? '', 0, 2000);
$pageTitle = substr($input['title'] ?? '', 0, 500);
$referrer = isset($input['ref']) ? substr($input['ref'], 0, 2000) : null;
$referrerDomain = isset($input['ref_domain']) ? substr($input['ref_domain'], 0, 255) : null;
$utmSource = isset($input['utm_source']) ? substr($input['utm_source'], 0, 255) : null;
$utmMedium = isset($input['utm_medium']) ? substr($input['utm_medium'], 0, 255) : null;
$utmCampaign = isset($input['utm_campaign']) ? substr($input['utm_campaign'], 0, 255) : null;
$deviceType = in_array($input['device'] ?? '', ['desktop', 'tablet', 'mobile']) ? $input['device'] : 'desktop';
$browser = substr($input['browser'] ?? '', 0, 100);
$os = substr($input['os'] ?? '', 0, 100);
$screenWidth = isset($input['sw']) ? intval($input['sw']) : null;
$screenHeight = isset($input['sh']) ? intval($input['sh']) : null;

// Get IP hash (privacy-friendly - we don't store the actual IP)
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}
$ipHash = hash('sha256', $ip . date('Y-m-d')); // Daily rotation for privacy

// Validate required fields
if (empty($visitorId) || empty($sessionId) || empty($pageUrl)) {
    http_response_code(400);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verify website exists and is active
    $stmt = $pdo->prepare("SELECT id FROM client_websites WHERE id = ? AND is_active = 1");
    $stmt->execute([$siteId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        exit;
    }

    // Insert page view
    $stmt = $pdo->prepare("
        INSERT INTO analytics_pageviews
        (website_id, session_id, visitor_id, page_url, page_title, referrer, referrer_domain,
         utm_source, utm_medium, utm_campaign, device_type, browser, os, screen_width, screen_height, ip_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $siteId,
        $sessionId,
        $visitorId,
        $pageUrl,
        $pageTitle,
        $referrer,
        $referrerDomain,
        $utmSource,
        $utmMedium,
        $utmCampaign,
        $deviceType,
        $browser,
        $os,
        $screenWidth,
        $screenHeight,
        $ipHash
    ]);

    // Return success (1x1 transparent gif for compatibility)
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

} catch (PDOException $e) {
    error_log("Analytics tracking error: " . $e->getMessage());
    http_response_code(500);
}
