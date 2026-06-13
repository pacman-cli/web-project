<?php
// api/auth/login.php
// Authentication endpoint to check credentials and initiate sessions

header('Content-Type: application/json');

$pdo = require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError('Request method not supported. Only POST is allowed.', 405);
}

require_once __DIR__ . '/../../config/csrf.php';
require_csrf();

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJSONError('Invalid JSON input.');
}

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    sendJSONError('Email and password are required.');
}

if (!login_rate_check()) {
    $retryAfter = 15 * 60 - (time() - ($_SESSION['login_attempt_window'] ?? time()));
    $retryAfter = ceil(max(0, $retryAfter) / 60);
    sendJSONError("Too many login attempts. Try again in {$retryAfter} minute(s).", 429);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        login_rate_failed();
        sendJSONError('Invalid email or password.', 401);
    }

    if ($user['status'] !== 'active') {
        login_rate_failed();
        sendJSONError('Your account has been deactivated. Please contact support.', 403);
    }

    login_rate_reset();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];

    echo json_encode([
        'success' => true,
        'message' => 'Logged in successfully.',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
} catch (\PDOException $e) {
    error_log('Login DB error: ' . $e->getMessage());
    sendJSONError('A database error occurred.', 500);
}
