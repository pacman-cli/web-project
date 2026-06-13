<?php
// admin/assignments.php
// Admin operations for assigning instructors to courses

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('admin');

$pdo = require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $stmt = $pdo->query("
                SELECT ia.assigned_at, u.id as instructor_id, u.name as instructor_name, 
                       c.id as course_id, c.title as course_title
                FROM instructor_assignments ia
                JOIN users u ON ia.instructor_id = u.id
                JOIN courses c ON ia.course_id = c.id
                ORDER BY c.title ASC, u.name ASC
            ");
            $assignments = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $assignments]);
        } catch (Exception $e) {
            error_log('Assignments query failed: ' . $e->getMessage());
            sendJSONError('Failed to fetch assignments.', 500);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $instructor_id = intval($input['instructor_id'] ?? 0);
        $course_id = intval($input['course_id'] ?? 0);

        if ($instructor_id <= 0 || $course_id <= 0) {
            sendJSONError('Valid Instructor ID and Course ID are required.');
        }

        try {
            $userCheck = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'instructor'");
            $userCheck->execute(['id' => $instructor_id]);
            if (!$userCheck->fetch()) {
                sendJSONError('Specified user is not a valid instructor.');
            }

            $courseCheck = $pdo->prepare("SELECT id FROM courses WHERE id = :id");
            $courseCheck->execute(['id' => $course_id]);
            if (!$courseCheck->fetch()) {
                sendJSONError('Course does not exist.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO instructor_assignments (instructor_id, course_id) 
                VALUES (:instructor_id, :course_id)
                ON DUPLICATE KEY UPDATE assigned_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                'instructor_id' => $instructor_id,
                'course_id' => $course_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Instructor assigned to course successfully.']);
        } catch (Exception $e) {
            error_log('Assignment failed: ' . $e->getMessage());
            sendJSONError('Failed to assign instructor to course.', 500);
        }
        break;

    case 'DELETE':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $instructor_id = intval($input['instructor_id'] ?? 0);
        $course_id = intval($input['course_id'] ?? 0);

        if ($instructor_id <= 0 || $course_id <= 0) {
            sendJSONError('Valid Instructor ID and Course ID are required.');
        }

        try {
            $stmt = $pdo->prepare("
                DELETE FROM instructor_assignments 
                WHERE instructor_id = :instructor_id AND course_id = :course_id
            ");
            $stmt->execute([
                'instructor_id' => $instructor_id,
                'course_id' => $course_id
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Assignment removed successfully.']);
            } else {
                sendJSONError('Assignment not found.', 404);
            }
        } catch (Exception $e) {
            error_log('Assignment removal failed: ' . $e->getMessage());
            sendJSONError('Failed to remove assignment.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
        break;
}
