<?php
// api/middleware.php
// Middleware helpers for user session handling and Role-Based Access Control (RBAC)

if (session_status() === PHP_SESSION_NONE) {
    // Session security configurations
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * Send a JSON error response and exit.
 */
function sendJSONError($message, $statusCode = 400) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit;
}

/**
 * Require a user to be authenticated.
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendJSONError('Authentication required.', 401);
    }
}

/**
 * Require a user to have a specific role or list of roles.
 * @param string|array $roles
 */
function requireRole($roles) {
    requireAuth();
    
    $allowedRoles = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['role'] ?? '';
    
    if (!in_array($userRole, $allowedRoles)) {
        sendJSONError('Access denied: Insufficient permissions.', 403);
    }
}

/**
 * Helper to get the current authenticated user's details.
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['name'] ?? '',
        'role' => $_SESSION['role'] ?? '',
    ];
}
