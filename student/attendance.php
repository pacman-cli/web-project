<?php
// student/attendance.php
// Retrieve attendance logs and calculate attendance ratios for the student

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

    // Fetch attendance records for this course
    $stmt = $pdo->prepare("
        SELECT a.status, a.date, s.day_of_week, s.start_time, s.end_time, s.location_detail
        FROM attendance a
        JOIN schedules s ON a.schedule_id = s.id
        WHERE a.student_id = :student_id AND s.course_id = :course_id
        ORDER BY a.date DESC
    ");
    $stmt->execute([
        'student_id' => $studentId,
        'course_id' => $courseId
    ]);
    $records = $stmt->fetchAll();

    // Calculate metrics: (present + excused) / total marked sessions
    $totalSessions = count($records);
    $presentCount = 0;
    $absentCount = 0;
    $excusedCount = 0;

    foreach ($records as $record) {
        if ($record['status'] === 'present') {
            $presentCount++;
        } elseif ($record['status'] === 'absent') {
            $absentCount++;
        } elseif ($record['status'] === 'excused') {
            $excusedCount++;
        }
    }

    $attendanceRate = 0;
    if ($totalSessions > 0) {
        // Excused absences do not count against the student's record
        $denominator = $totalSessions - $excusedCount;
        if ($denominator > 0) {
            $attendanceRate = round(($presentCount / $denominator) * 100);
        } else {
            $attendanceRate = 100; // All marked classes were excused
        }
    } else {
        $attendanceRate = 100; // No marked attendance sessions yet
    }

    echo json_encode([
        'success' => true,
        'attendance_rate' => $attendanceRate,
        'metrics' => [
            'total_sessions' => $totalSessions,
            'present' => $presentCount,
            'absent' => $absentCount,
            'excused' => $excusedCount
        ],
        'records' => $records
    ]);

} catch (Exception $e) {
    error_log('Student attendance error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load attendance records.']);
}
