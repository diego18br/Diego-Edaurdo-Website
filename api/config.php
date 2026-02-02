<?php
/**
 * Configuration file for Diego Eduardo Payment Portal
 *
 * IMPORTANT: Update these values with your actual credentials
 * Keep this file secure and never commit real credentials to version control
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// DATABASE CONFIGURATION
// ============================================
// Get these from your cPanel when you create the MySQL database

define('DB_HOST', 'localhost');                    // Usually 'localhost' for shared hosting
define('DB_NAME', 'u641299609_DiegoEduardo');           // Replace with your database name
define('DB_USER', 'u641299609_deduardo');       // Replace with your database username
define('DB_PASS', '!D12031994e!2810');       // Replace with your database password

// ============================================
// STRIPE CONFIGURATION
// ============================================
// Get these from: Stripe Dashboard -> Developers -> API keys

define('STRIPE_SECRET_KEY', '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');
define('STRIPE_PUBLISHABLE_KEY', 'pk_live_51SvrTL9x8bzC08KKf1zEw9o176vCHMy4bwiwcqneU3xzssyoQvL6hLtVyq15CLt1e49Hp8m1tArCmFzlmtUmBWiq00unmGaLWU');

// Portal return URL - where clients go after visiting Stripe portal
define('PORTAL_RETURN_URL', 'https://www.diegoheduardo.com/portal.html');

// ============================================
// APPLICATION SETTINGS
// ============================================

define('SITE_URL', 'https://www.diegoheduardo.com');
define('SESSION_TIMEOUT', 3600); // Session timeout in seconds (1 hour)

// ============================================
// WEBSITE DASHBOARD CONFIGURATION
// ============================================

// UptimeRobot API Key - Get from: uptimerobot.com -> My Settings -> API Settings
define('UPTIMEROBOT_API_KEY', 'u3291274-870f379b9dc406540a06ac8a');

// Cache durations in seconds
define('METRICS_CACHE_DURATION_UPTIME', 300);       // 5 minutes
define('METRICS_CACHE_DURATION_PERFORMANCE', 3600); // 1 hour

// Rate limiting - max refreshes per hour per website
define('REFRESH_RATE_LIMIT', 5);

// ============================================
// PASSWORD RESET CONFIGURATION
// ============================================

define('PASSWORD_RESET_EXPIRY', 3600); // Token expires in 1 hour
define('FROM_EMAIL', 'noreply@diegoheduardo.com');
define('FROM_NAME', 'Diego Eduardo');

// ============================================
// CONTACT FORM CONFIGURATION
// ============================================

define('CONTACT_NOTIFICATION_EMAIL', 'diego@diegoheduardo.com');
define('CONTACT_RATE_LIMIT', 5); // Max submissions per IP per hour

// ============================================
// SMTP EMAIL CONFIGURATION
// ============================================

define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl'); // 'ssl' for port 465, 'tls' for port 587
define('SMTP_USERNAME', 'diego@diegoheduardo.com');
define('SMTP_PASSWORD', '!D12031994e');

// ============================================
// DATABASE CONNECTION
// ============================================

function getDbConnection() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }

    return $pdo;
}

// ============================================
// RESPONSE HELPERS
// ============================================

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function errorResponse($message, $statusCode = 400) {
    jsonResponse(['error' => $message], $statusCode);
}

function successResponse($data = [], $message = 'Success') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

// ============================================
// CORS HEADERS (for API requests from frontend)
// ============================================

header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
