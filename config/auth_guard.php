<?php
// config/auth_guard.php
// Middleware-like access control and session verification helper

if (session_status() === PHP_SESSION_NONE) {
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
 * Check if the user is authenticated. If not, redirect to the login page.
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

/**
 * Enforce role-based access control. Dies with 403 or redirects if role unauthorized.
 * @param string|array $roles
 */
function requireRole($roles) {
    requireAuth();
    
    $allowedRoles = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['role'] ?? '';
    
    if (!in_array($userRole, $allowedRoles)) {
        http_response_code(403);
        echo "<div style='font-family: sans-serif; text-align: center; margin-top: 100px;'>";
        echo "<h2>Access Denied</h2>";
        echo "<p>Insufficient permissions. Active role '<strong>" . htmlspecialchars($userRole) . "</strong>' is not authorized to access this page.</p>";
        echo "<p><a href='/auth/logout.php'>Logout</a> or <a href='javascript:history.back()'>Go Back</a></p>";
        echo "</div>";
        exit;
    }
}

/**
 * Check if the user is logged in.
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}
