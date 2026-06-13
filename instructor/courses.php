<?php
// instructor/courses.php
// Retrieve assigned courses and students for the authenticated instructor

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('instructor'); // Enforce Instructor RBAC

$pdo = require_once __DIR__ . '/../config/db.php';

$instructorId = $_SESSION['user_id'];

try {
    $action = $_GET['action'] ?? 'list_courses';

    if ($action === 'list_courses') {
        // Fetch courses assigned to the instructor
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.description, c.difficulty, c.price, c.status
            FROM courses c
            JOIN instructor_assignments ia ON c.id = ia.course_id
            WHERE ia.instructor_id = :instructor_id
            ORDER BY c.title ASC
        ");
        $stmt->execute(['instructor_id' => $instructorId]);
        $courses = $stmt->fetchAll();

        echo json_encode(['success' => true, 'courses' => $courses]);

    } elseif ($action === 'list_students') {
        $courseId = intval($_GET['course_id'] ?? 0);
        if ($courseId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Course ID is required.']);
            exit;
        }

        // Verify instructor is assigned to this course
        $verifyStmt = $pdo->prepare("
            SELECT course_id FROM instructor_assignments 
            WHERE instructor_id = :instructor_id AND course_id = :course_id
        ");
        $verifyStmt->execute(['instructor_id' => $instructorId, 'course_id' => $courseId]);
        if (!$verifyStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized course context access.']);
            exit;
        }

        // Fetch students enrolled in the course
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, e.enrolled_at, s.experience_level
            FROM users u
            JOIN students s ON u.id = s.user_id
            JOIN enrollments e ON u.id = e.student_id
            WHERE e.course_id = :course_id AND e.status = 'approved'
            ORDER BY u.name ASC
        ");
        $stmt->execute(['course_id' => $courseId]);
        $students = $stmt->fetchAll();

        echo json_encode(['success' => true, 'students' => $students]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action parameter.']);
    }

} catch (Exception $e) {
    error_log('Courses error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Query execution failed.']);
}
