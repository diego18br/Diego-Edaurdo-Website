<?php
/**
 * Authentication helper functions
 */

require_once __DIR__ . '/config.php';

/**
 * Hash a password using bcrypt
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against a hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['client_id']) && !empty($_SESSION['client_id']);
}

/**
 * Get current logged-in user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, email, name, stripe_customer_id, created_at FROM clients WHERE id = ?");
    $stmt->execute([$_SESSION['client_id']]);
    return $stmt->fetch();
}

/**
 * Require authentication - call at start of protected endpoints
 */
function requireAuth() {
    if (!isLoggedIn()) {
        errorResponse('Unauthorized. Please log in.', 401);
    }
}

/**
 * Log in a user (set session)
 */
function loginUser($clientId) {
    $_SESSION['client_id'] = $clientId;
    $_SESSION['login_time'] = time();
}

/**
 * Log out a user (destroy session)
 */
function logoutUser() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * - At least 8 characters
 */
function isValidPassword($password) {
    return strlen($password) >= 8;
}

/**
 * Get client by email
 */
function getClientByEmail($email) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

/**
 * Get client by ID
 */
function getClientById($id) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Create a new client
 */
function createClient($email, $passwordHash, $name, $stripeCustomerId = null) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO clients (email, password_hash, name, stripe_customer_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $passwordHash, $name, $stripeCustomerId]);
    return $pdo->lastInsertId();
}

/**
 * Update client's Stripe customer ID
 */
function updateClientStripeId($clientId, $stripeCustomerId) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("UPDATE clients SET stripe_customer_id = ? WHERE id = ?");
    $stmt->execute([$stripeCustomerId, $clientId]);
}
