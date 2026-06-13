<?php
// instructor/attendance.php
// Operations for marking and fetching student attendance records

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';

$instructorId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $scheduleId = intval($_GET['schedule_id'] ?? 0);
        $date = trim($_GET['date'] ?? date('Y-m-d'));

        if ($scheduleId <= 0 || empty($date)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Schedule ID and date are required.']);
            exit;
        }

        try {
            // Verify schedule ownership
            $verifyStmt = $pdo->prepare("SELECT course_id FROM schedules WHERE id = :id AND instructor_id = :instructor_id");
            $verifyStmt->execute(['id' => $scheduleId, 'instructor_id' => $instructorId]);
            $schedule = $verifyStmt->fetch();
            if (!$schedule) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: You do not own this lesson schedule.']);
                exit;
            }

            // Fetch students enrolled and their attendance status for this date
            $stmt = $pdo->prepare("
                SELECT u.id as student_id, u.name as student_name, u.email as student_email,
                       att.status as attendance_status, att.marked_at
                FROM users u
                JOIN students s ON u.id = s.user_id
                JOIN enrollments e ON u.id = e.student_id AND e.course_id = :course_id
                LEFT JOIN attendance att ON u.id = att.student_id AND att.schedule_id = :schedule_id AND att.date = :date
                WHERE e.status = 'approved'
                ORDER BY u.name ASC
            ");
            $stmt->execute([
                'course_id' => $schedule['course_id'],
                'schedule_id' => $scheduleId,
                'date' => $date
            ]);
            $records = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $records]);
        } catch (Exception $e) {
            error_log('Attendance fetch error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed.']);
        }
        break;

    case 'POST':
        require_csrf();
        // Mark attendance list
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $scheduleId = intval($input['schedule_id'] ?? 0);
        $date = trim($input['date'] ?? date('Y-m-d'));
        $studentsList = $input['attendance'] ?? []; // Array of { student_id: X, status: 'present'|'absent'|'excused' }

        if ($scheduleId <= 0 || empty($date) || !is_array($studentsList)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Schedule ID, Date, and Attendance array are required.']);
            exit;
        }

        try {
            // Verify schedule ownership
            $verifyStmt = $pdo->prepare("SELECT id FROM schedules WHERE id = :id AND instructor_id = :instructor_id");
            $verifyStmt->execute(['id' => $scheduleId, 'instructor_id' => $instructorId]);
            if (!$verifyStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: You do not own this lesson schedule.']);
                exit;
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO attendance (schedule_id, student_id, status, date) 
                VALUES (:schedule_id, :student_id, :status, :date)
                ON DUPLICATE KEY UPDATE status = :status_new, marked_at = CURRENT_TIMESTAMP
            ");

            foreach ($studentsList as $record) {
                $studentId = intval($record['student_id'] ?? 0);
                $status = trim($record['status'] ?? '');

                if ($studentId > 0 && in_array($status, ['present', 'absent', 'excused'])) {
                    $stmt->execute([
                        'schedule_id' => $scheduleId,
                        'student_id' => $studentId,
                        'status' => $status,
                        'date' => $date,
                        'status_new' => $status
                    ]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Attendance list marked successfully.']);
        } catch (Exception $e) {
            error_log('Attendance save error: ' . $e->getMessage());
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save attendance.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
}
