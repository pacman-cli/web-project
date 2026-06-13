<?php
// student/courses.php
// Retrieve enrolled courses and calculate progress for the logged-in student

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('student'); // Enforce Student RBAC

$pdo = require_once __DIR__ . '/../config/db.php';

$studentId = $_SESSION['user_id'];

try {
    // Fetch courses with approved enrollments
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.difficulty, i.name as instrument_name, e.enrolled_at
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN instruments i ON c.instrument_id = i.id
        WHERE e.student_id = :student_id AND e.status = 'approved'
        ORDER BY c.title ASC
    ");
    $stmt->execute(['student_id' => $studentId]);
    $courses = $stmt->fetchAll();

    $coursesWithProgress = [];

    foreach ($courses as $course) {
        $courseId = $course['id'];

        // 1. Calculate progress: (submissions / total assignments) * 100
        $assignStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE course_id = :course_id");
        $assignStmt->execute(['course_id' => $courseId]);
        $totalAssignments = intval($assignStmt->fetchColumn());

        $subStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM submissions s
            JOIN assignments a ON s.assignment_id = a.id
            WHERE a.course_id = :course_id AND s.student_id = :student_id
        ");
        $subStmt->execute(['course_id' => $courseId, 'student_id' => $studentId]);
        $submittedAssignments = intval($subStmt->fetchColumn());

        $progressPercent = 0;
        if ($totalAssignments > 0) {
            $progressPercent = round(($submittedAssignments / $totalAssignments) * 100);
        }

        // 2. Fetch Instructor details for this course
        $instStmt = $pdo->prepare("
            SELECT u.name, u.email 
            FROM users u
            JOIN instructor_assignments ia ON u.id = ia.instructor_id
            WHERE ia.course_id = :course_id
        ");
        $instStmt->execute(['course_id' => $courseId]);
        $instructors = $instStmt->fetchAll();

        $course['progress_percent'] = $progressPercent;
        $course['instructors'] = $instructors;
        $coursesWithProgress[] = $course;
    }

    echo json_encode([
        'success' => true,
        'data' => $coursesWithProgress
    ]);

} catch (Exception $e) {
    error_log('Student courses error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load courses.']);
}
