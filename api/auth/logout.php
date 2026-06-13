<?php
// api/auth/logout.php
// Session destruction endpoint to sign users out

header('Content-Type: application/json');

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'GET method not allowed. Use POST.']);
    exit;
}

require_csrf();

unset($_SESSION['csrf_token']);
unset($_SESSION['csrf_token_expires']);
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully.'
]);
