<?php
// api/auth/register.php
// Registration API endpoint for student account creation

header('Content-Type: application/json');

$pdo = require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError('Request method not supported. Only POST is allowed.', 405);
}

require_once __DIR__ . '/../../config/csrf.php';
require_csrf();

if (!register_rate_check()) {
    sendJSONError('Too many registration attempts. Please try again later.', 429);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendJSONError('Invalid JSON input.');
}

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$role = trim($input['role'] ?? 'student');

if (empty($name) || empty($email) || empty($password)) {
    sendJSONError('Name, email, and password are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSONError('Invalid email format.');
}

if (strlen($password) < 6) {
    sendJSONError('Password must be at least 6 characters.');
}

if ($role !== 'student') {
    $currentUser = getCurrentUser();
    if (!$currentUser || $currentUser['role'] !== 'admin') {
        sendJSONError('Access denied: Unauthorized role assignment.', 403);
    }
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        sendJSONError('Email already registered.', 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $insertStmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role)
            VALUES (:name, :email, :password_hash, :role)
        ");
        $insertStmt->execute([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role
        ]);

        $newId = $pdo->lastInsertId();

        if ($role === 'student') {
            $studentStmt = $pdo->prepare("
                INSERT INTO students (user_id, experience_level, enrollment_date)
                VALUES (:user_id, 'beginner', :enroll_date)
            ");
            $studentStmt->execute([
                'user_id' => $newId,
                'enroll_date' => date('Y-m-d')
            ]);
        }

        $pdo->commit();
        register_rate_reset();
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Registration DB error: ' . $e->getMessage());
        sendJSONError('A database error occurred.', 500);
    }

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'User registered successfully.',
        'user' => [
            'id' => $newId,
            'name' => $name,
            'email' => $email,
            'role' => $role
        ]
    ]);
} catch (\PDOException $e) {
    if (!isset($pdo) || !$pdo->inTransaction()) {
        error_log('Registration DB error: ' . $e->getMessage());
        sendJSONError('A database error occurred.', 500);
    }
}
