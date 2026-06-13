<?php
// admin/courses.php
// Admin operations for managing courses

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('admin');

$pdo = require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("
                SELECT c.*, i.name as instrument_name 
                FROM courses c
                LEFT JOIN instruments i ON c.instrument_id = i.id
                ORDER BY c.title ASC
            ");
            $courses = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $courses]);
        } catch (Exception $e) {
            error_log('Courses query failed: ' . $e->getMessage());
            sendJSONError('Failed to fetch courses.', 500);
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
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $instrument_id = !empty($input['instrument_id']) ? intval($input['instrument_id']) : null;
        $difficulty = trim($input['difficulty'] ?? 'beginner');
        $price = floatval($input['price'] ?? 0.00);
        $status = trim($input['status'] ?? 'draft');

        if (empty($title)) {
            sendJSONError('Course title is required.');
        }

        if (!in_array($difficulty, ['beginner', 'intermediate', 'advanced'], true)) {
            sendJSONError('Invalid difficulty. Allowed: beginner, intermediate, advanced.');
        }

        if (!in_array($status, ['draft', 'published'], true)) {
            sendJSONError('Invalid status. Allowed: draft, published.');
        }

        if ($price < 0) {
            sendJSONError('Course price cannot be negative.');
        }

        if ($action === 'add') {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO courses (title, description, instrument_id, difficulty, price, status) 
                    VALUES (:title, :description, :instrument_id, :difficulty, :price, :status)
                ");
                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'instrument_id' => $instrument_id,
                    'difficulty' => $difficulty,
                    'price' => $price,
                    'status' => $status
                ]);
                $courseId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Course created successfully.', 'id' => $courseId]);
            } catch (Exception $e) {
                error_log('Course creation failed: ' . $e->getMessage());
                sendJSONError('Failed to create course.', 500);
            }

        } elseif ($action === 'edit') {
            $courseId = intval($input['id'] ?? 0);
            if ($courseId <= 0) {
                sendJSONError('Valid Course ID is required.');
            }

            try {
                $existing = $pdo->prepare("SELECT instrument_id, status FROM courses WHERE id = :id");
                $existing->execute(['id' => $courseId]);
                $existingCourse = $existing->fetch();
                if (!$existingCourse) {
                    sendJSONError('Course not found.', 404);
                }

                $instrument_id = !empty($input['instrument_id']) ? intval($input['instrument_id']) : $existingCourse['instrument_id'];
                $status = isset($input['status']) ? trim($input['status']) : $existingCourse['status'];

                $stmt = $pdo->prepare("
                    UPDATE courses 
                    SET title = :title, description = :description, instrument_id = :instrument_id, 
                        difficulty = :difficulty, price = :price, status = :status
                    WHERE id = :id
                ");
                $stmt->execute([
                    'title' => $title,
                    'description' => $description,
                    'instrument_id' => $instrument_id,
                    'difficulty' => $difficulty,
                    'price' => $price,
                    'status' => $status,
                    'id' => $courseId
                ]);
                echo json_encode(['success' => true, 'message' => 'Course updated successfully.']);
            } catch (Exception $e) {
                error_log('Course update failed: ' . $e->getMessage());
                sendJSONError('Failed to update course.', 500);
            }
        } else {
            sendJSONError('Invalid action.');
        }
        break;

    case 'DELETE':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $courseId = intval($input['id'] ?? 0);

        if ($courseId <= 0) {
            sendJSONError('Valid Course ID is required.');
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
            $stmt->execute(['id' => $courseId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Course deleted successfully.']);
            } else {
                sendJSONError('Course not found.', 404);
            }
        } catch (Exception $e) {
            error_log('Course deletion failed: ' . $e->getMessage());
            sendJSONError('Failed to delete course.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
        break;
}
