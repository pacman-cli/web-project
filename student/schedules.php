<?php
// student/schedules.php
// Retrieve schedules and class links for the student's enrolled courses

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';

$studentId = $_SESSION['user_id'];

try {
    // Fetch weekly schedules for courses that student is actively enrolled in
    $stmt = $pdo->prepare("
        SELECT s.id, s.day_of_week, s.start_time, s.end_time, s.location_type, s.location_detail,
               c.title as course_title, u_inst.name as instructor_name
        FROM schedules s
        JOIN courses c ON s.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN users u_inst ON s.instructor_id = u_inst.id
        WHERE e.student_id = :student_id AND e.status = 'approved'
        ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC
    ");
    $stmt->execute(['student_id' => $studentId]);
    $schedules = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'schedules' => $schedules
    ]);

} catch (Exception $e) {
    error_log('Student schedules error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load schedules.']);
}
