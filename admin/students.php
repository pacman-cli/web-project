<?php
// admin/students.php
// Admin operations for managing students

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
                       s.date_of_birth, s.parent_name, s.parent_contact,
                       s.experience_level, s.enrollment_date
                FROM users u
                JOIN students s ON u.id = s.user_id
                WHERE u.role = 'student'
                ORDER BY u.name ASC
            ");
            $students = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $students]);
        } catch (Exception $e) {
            error_log('Students query failed: ' . $e->getMessage());
            sendJSONError('Failed to fetch students.', 500);
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
        $dateOfBirth = $input['date_of_birth'] ?? null;
        $parentName = trim($input['parent_name'] ?? '');
        $parentContact = trim($input['parent_contact'] ?? '');
        $experienceLevel = $input['experience_level'] ?? 'beginner';

        if ($action === 'add') {
            $password = $input['password'] ?? '';
            if (empty($name) || empty($email) || empty($password)) {
                sendJSONError('Name, email, and password are required.');
            }

            $allowedLevels = ['beginner', 'intermediate', 'advanced'];
            if (!in_array($experienceLevel, $allowedLevels)) {
                sendJSONError('Invalid experience level. Allowed: ' . implode(', ', $allowedLevels) . '.');
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
                    VALUES (:name, :email, :password_hash, 'student')
                ");
                $userStmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => $passwordHash
                ]);
                $userId = $pdo->lastInsertId();

                $stuStmt = $pdo->prepare("
                    INSERT INTO students (user_id, date_of_birth, parent_name, parent_contact, experience_level, enrollment_date) 
                    VALUES (:user_id, :date_of_birth, :parent_name, :parent_contact, :experience_level, CURDATE())
                ");
                $stuStmt->execute([
                    'user_id' => $userId,
                    'date_of_birth' => $dateOfBirth ?: null,
                    'parent_name' => $parentName ?: null,
                    'parent_contact' => $parentContact ?: null,
                    'experience_level' => $experienceLevel
                ]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Student added successfully.', 'id' => $userId]);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Student add failed: ' . $e->getMessage());
                sendJSONError('Failed to add student.', 500);
            }

        } elseif ($action === 'edit') {
            $userId = intval($input['id'] ?? 0);
            if ($userId <= 0 || empty($name) || empty($email)) {
                sendJSONError('Valid ID, name, and email are required.');
            }

            $allowedLevels = ['beginner', 'intermediate', 'advanced'];
            if (!in_array($experienceLevel, $allowedLevels)) {
                sendJSONError('Invalid experience level. Allowed: ' . implode(', ', $allowedLevels) . '.');
            }

            try {
                $pdo->beginTransaction();

                $existingStmt = $pdo->prepare("SELECT status FROM users WHERE id = :id AND role = 'student'");
                $existingStmt->execute(['id' => $userId]);
                $existingUser = $existingStmt->fetch();
                if (!$existingUser) {
                    $pdo->rollBack();
                    sendJSONError('Student not found.', 404);
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
                    WHERE id = :id AND role = 'student'
                ");
                $userStmt->execute([
                    'name' => $name,
                    'email' => $email,
                    'status' => $status,
                    'id' => $userId
                ]);

                $stuStmt = $pdo->prepare("
                    UPDATE students 
                    SET date_of_birth = :date_of_birth, parent_name = :parent_name, 
                        parent_contact = :parent_contact, experience_level = :experience_level
                    WHERE user_id = :user_id
                ");
                $stuStmt->execute([
                    'date_of_birth' => $dateOfBirth ?: null,
                    'parent_name' => $parentName ?: null,
                    'parent_contact' => $parentContact ?: null,
                    'experience_level' => $experienceLevel,
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
                echo json_encode(['success' => true, 'message' => 'Student updated successfully.']);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Student update failed: ' . $e->getMessage());
                sendJSONError('Failed to update student.', 500);
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
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'student'");
            $stmt->execute(['id' => $userId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Student deleted successfully.']);
            } else {
                sendJSONError('Student not found.', 404);
            }
        } catch (Exception $e) {
            error_log('Student deletion failed: ' . $e->getMessage());
            sendJSONError('Failed to delete student.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
        break;
}
