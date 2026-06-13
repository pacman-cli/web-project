<?php
// student/enroll.php
// Student API endpoint to request enrollment in a course

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError('Method not allowed. Only POST is supported.', 405);
}

require_once __DIR__ . '/../config/csrf.php';
require_csrf();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$courseId = intval($input['course_id'] ?? 0);

if ($courseId <= 0) {
    sendJSONError('Valid Course ID is required.');
}

try {
    $courseStmt = $pdo->prepare("SELECT status FROM courses WHERE id = :course_id");
    $courseStmt->execute(['course_id' => $courseId]);
    $course = $courseStmt->fetch();

    if (!$course || $course['status'] !== 'published') {
        sendJSONError('Course not found or not currently open for enrollment.', 404);
    }

    $checkStmt = $pdo->prepare("SELECT id, status FROM enrollments WHERE student_id = :student_id AND course_id = :course_id");
    $checkStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'approved') {
            sendJSONError('You are already enrolled in this course.');
        } elseif ($existing['status'] === 'pending') {
            sendJSONError('Your enrollment request is already pending review.');
        } else {
            $updateStmt = $pdo->prepare("
                UPDATE enrollments 
                SET status = 'pending', rejection_reason = NULL, enrolled_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $existing['id']]);
            echo json_encode([
                'success' => true,
                'message' => 'Enrollment request re-submitted successfully.'
            ]);
            exit;
        }
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO enrollments (student_id, course_id, status) 
        VALUES (:student_id, :course_id, 'pending')
    ");
    $insertStmt->execute([
        'student_id' => $studentId,
        'course_id' => $courseId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Enrollment request submitted successfully.'
    ]);

} catch (Exception $e) {
    error_log('Enrollment request failed: ' . $e->getMessage());
    sendJSONError('Failed to request enrollment.', 500);
}
