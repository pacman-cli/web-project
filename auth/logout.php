<?php
// auth/logout.php
// Logs the user out of the session and redirects to the login page

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['csrf_token']);
unset($_SESSION['csrf_token_expires']);
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: /auth/login.php');
exit;
