<?php
// student/materials.php
// Retrieve syllabus lesson materials (PDFs, sheet music, audio tracks) for student's enrolled courses

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';

$studentId = $_SESSION['user_id'];

try {
    $courseId = intval($_GET['course_id'] ?? 0);
    if ($courseId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid Course ID is required.']);
        exit;
    }

    // Verify student enrollment status
    $verifyStmt = $pdo->prepare("
        SELECT id FROM enrollments 
        WHERE student_id = :student_id AND course_id = :course_id AND status = 'approved'
    ");
    $verifyStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    if (!$verifyStmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied: You are not actively enrolled in this course.']);
        exit;
    }

    // Fetch materials
    $stmt = $pdo->prepare("
        SELECT m.id, m.title, m.file_path, m.file_type, m.uploaded_at, u.name as uploaded_by_name
        FROM materials m
        JOIN users u ON m.uploaded_by = u.id
        WHERE m.course_id = :course_id
        ORDER BY m.uploaded_at DESC
    ");
    $stmt->execute(['course_id' => $courseId]);
    $materials = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'materials' => $materials
    ]);

} catch (Exception $e) {
    error_log('Student materials error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load materials.']);
}
