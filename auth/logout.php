<?php
// auth/logout.php
// Destroys session and redirects to the public homepage

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

require_once __DIR__ . '/../config/app.php';

// Clear all session data
$_SESSION = [];
unset($_SESSION['csrf_token']);
unset($_SESSION['csrf_token_expires']);

// Delete the session cookie — force path='/' so it clears site-wide
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        '/', $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: ' . BASE_URL . '/43_Public_Homepage/index.php');
exit;
