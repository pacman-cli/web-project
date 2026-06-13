<?php
// admin/instructors.php
// Admin operations for managing instructors

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('admin');

$pdo = require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("
                SELECT u.id, u.name, u.email, u.status, u.created_at,
                       i.bio, i.specialization, i.hourly_rate, i.hire_date
                FROM users u
                JOIN instructors i ON u.id = i.user_id
                WHERE u.role = 'instructor'
                ORDER BY u.name ASC
            ");
            $instructors = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $instructors]);
        } catch (Exception $e) {
            error_log('Instructors query failed: ' . $e->getMessage());
            sendJSONError('Failed to fetch instructors.', 500);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $action = $input['action'] ?? 'add';
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $bio = trim($input['bio'] ?? '');
        $specialization = trim($input['specialization'] ?? '');
        $hourly_rate = floatval($input['hourly_rate'] ?? 0.00);
        $hire_date = $input['hire_date'] ?? date('Y-m-d');

        if ($action === 'add') {
            $password = $input['password'] ?? '';
            if (empty($name) || empty($email) || empty($password)) {
                sendJSONError('Name, email, and password are required.');
            }

            if ($hourly_rate < 0) {
                sendJSONError('Hourly rate cannot be negative.');
            }

            if ($hire_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)) {
                sendJSONError('Hire date must be in YYYY-MM-DD format.');
            }

            try {
                $pdo->beginTransaction();

                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $checkStmt->execute(['email' => $email]);
                if ($checkStmt->fetch()) {
                    $pdo->rollBack();
                    sendJSONError('Email already registered.');
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $userStmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role) 
                    VALUES (:name, :email, :password_hash, 'instructor')
                ");
                $userStmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $passwordHash
                ]);
                $userId = $pdo->lastInsertId();

                $instStmt = $pdo->prepare("
                    INSERT INTO instructors (user_id, bio, specialization, hourly_rate, hire_date) 
                    VALUES (:user_id, :bio, :specialization, :hourly_rate, :hire_date)
                ");
                $instStmt->execute([
                    'user_id' => $userId,
                    'bio' => $bio,
                    'specialization' => $specialization,
                    'hourly_rate' => $hourly_rate,
                    'hire_date' => $hire_date
                ]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Instructor added successfully.', 'id' => $userId]);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Instructor add failed: ' . $e->getMessage());
                sendJSONError('Failed to add instructor.', 500);
            }

        } elseif ($action === 'edit') {
            $userId = intval($input['id'] ?? 0);
            if ($userId <= 0 || empty($name) || empty($email)) {
                sendJSONError('Valid ID, name, and email are required.');
            }

            if ($hourly_rate < 0) {
                sendJSONError('Hourly rate cannot be negative.');
            }

            if ($hire_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)) {
                sendJSONError('Hire date must be in YYYY-MM-DD format.');
            }

            try {
                $pdo->beginTransaction();

                $existingStmt = $pdo->prepare("SELECT status, email FROM users WHERE id = :id AND role = 'instructor'");
                $existingStmt->execute(['id' => $userId]);
                $existingUser = $existingStmt->fetch();
                if (!$existingUser) {
                    $pdo->rollBack();
                    sendJSONError('Instructor not found.', 404);
                }

                if (strtolower($email) !== strtolower($existingUser['email'])) {
                    $dupStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                    $dupStmt->execute(['email' => $email, 'id' => $userId]);
                    if ($dupStmt->fetch()) {
                        $pdo->rollBack();
                        sendJSONError('Email already registered by another user.');
                    }
                }

                $allowedStatuses = ['active', 'inactive'];
                $status = isset($input['status']) ? $input['status'] : $existingUser['status'];
                if (!in_array($status, $allowedStatuses)) {
                    $pdo->rollBack();
                    sendJSONError('Invalid status value. Allowed: ' . implode(', ', $allowedStatuses) . '.');
                }

                $userStmt = $pdo->prepare("
                    UPDATE users 
                    SET name = :name, email = :email, status = :status
                    WHERE id = :id AND role = 'instructor'
                ");
                $userStmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'status' => $status,
                    'id' => $userId
                ]);

                $instStmt = $pdo->prepare("
                    UPDATE instructors 
                    SET bio = :bio, specialization = :specialization, hourly_rate = :hourly_rate, hire_date = :hire_date
                    WHERE user_id = :user_id
                ");
                $instStmt->execute([
                    'bio' => $bio,
                    'specialization' => $specialization,
                    'hourly_rate' => $hourly_rate,
                    'hire_date' => $hire_date,
                    'user_id' => $userId
                ]);

                if (!empty($input['password'])) {
                    $passStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                    $passStmt->execute([
                        'hash' => password_hash($input['password'], PASSWORD_DEFAULT),
                        'id' => $userId
                    ]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Instructor updated successfully.']);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Instructor update failed: ' . $e->getMessage());
                sendJSONError('Failed to update instructor.', 500);
            }
        } else {
            sendJSONError('Invalid action.');
        }
        break;

    case 'DELETE':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $userId = intval($input['id'] ?? 0);

        if ($userId <= 0) {
            sendJSONError('Valid ID is required.');
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'instructor'");
            $stmt->execute(['id' => $userId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Instructor deleted successfully.']);
            } else {
                sendJSONError('Instructor not found.', 404);
            }
        } catch (Exception $e) {
            error_log('Instructor deletion failed: ' . $e->getMessage());
            sendJSONError('Failed to delete instructor.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
        break;
}
