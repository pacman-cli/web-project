<?php
// instructor/schedules.php
// Operations for instructors to list and create lesson schedules

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';

$instructorId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Retrieve schedules created by this instructor
        try {
            $stmt = $pdo->prepare("
                SELECT s.*, c.title as course_title
                FROM schedules s
                JOIN courses c ON s.course_id = c.id
                WHERE s.instructor_id = :instructor_id
                ORDER BY FIELD(s.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), s.start_time ASC
            ");
            $stmt->execute(['instructor_id' => $instructorId]);
            $schedules = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $schedules]);
        } catch (Exception $e) {
            error_log('Schedule GET error: ' . $e->getMessage());
            sendJSONError('Failed to fetch schedules.', 500);
        }
        break;

    case 'POST':
        // Create a new Schedule block
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);

        $courseId = intval($input['course_id'] ?? 0);
        $dayOfWeek = trim($input['day_of_week'] ?? '');
        $startTime = trim($input['start_time'] ?? '');
        $endTime = trim($input['end_time'] ?? '');
        $locationType = trim($input['location_type'] ?? 'physical');
        $locationDetail = trim($input['location_detail'] ?? '');

        $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        if ($courseId <= 0 || !in_array($dayOfWeek, $allowedDays) || empty($startTime) || empty($endTime) || empty($locationDetail)) {
            sendJSONError('All fields (course_id, day_of_week, start_time, end_time, location_detail) are required.', 400);
        }

        try {
            $verifyStmt = $pdo->prepare("
                SELECT course_id FROM instructor_assignments 
                WHERE instructor_id = :instructor_id AND course_id = :course_id
            ");
            $verifyStmt->execute(['instructor_id' => $instructorId, 'course_id' => $courseId]);
            if (!$verifyStmt->fetch()) {
                sendJSONError('Access denied: Instructor not assigned to this course.', 403);
            }

            $stmt = $pdo->prepare("
                INSERT INTO schedules (course_id, instructor_id, day_of_week, start_time, end_time, location_type, location_detail) 
                VALUES (:course_id, :instructor_id, :day_of_week, :start_time, :end_time, :location_type, :location_detail)
            ");
            $stmt->execute([
                'course_id' => $courseId,
                'instructor_id' => $instructorId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'location_type' => $locationType === 'online' ? 'online' : 'physical',
                'location_detail' => $locationDetail
            ]);
            $scheduleId = $pdo->lastInsertId();

            echo json_encode(['success' => true, 'message' => 'Schedule created successfully.', 'id' => $scheduleId]);
        } catch (Exception $e) {
            error_log('Schedule POST error: ' . $e->getMessage());
            sendJSONError('Failed to create schedule.', 500);
        }
        break;

    case 'PUT':
        // Update an existing schedule
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $scheduleId = intval($input['id'] ?? 0);
        $courseId = intval($input['course_id'] ?? 0);
        $dayOfWeek = trim($input['day_of_week'] ?? '');
        $startTime = trim($input['start_time'] ?? '');
        $endTime = trim($input['end_time'] ?? '');
        $locationType = trim($input['location_type'] ?? 'physical');
        $locationDetail = trim($input['location_detail'] ?? '');

        $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        if ($scheduleId <= 0 || $courseId <= 0 || !in_array($dayOfWeek, $allowedDays) || empty($startTime) || empty($endTime) || empty($locationDetail)) {
            sendJSONError('All fields are required.', 400);
        }

        try {
            // Verify ownership
            $ownerStmt = $pdo->prepare("SELECT instructor_id FROM schedules WHERE id = :id");
            $ownerStmt->execute(['id' => $scheduleId]);
            $schedule = $ownerStmt->fetch();
            if (!$schedule) sendJSONError('Schedule not found.', 404);
            if (intval($schedule['instructor_id']) !== $instructorId) sendJSONError('Access denied.', 403);

            // Verify course assignment
            $verifyStmt = $pdo->prepare("SELECT 1 FROM instructor_assignments WHERE instructor_id = :iid AND course_id = :cid");
            $verifyStmt->execute(['iid' => $instructorId, 'cid' => $courseId]);
            if (!$verifyStmt->fetch()) sendJSONError('Access denied: not assigned to this course.', 403);

            $stmt = $pdo->prepare("
                UPDATE schedules SET course_id=:cid, day_of_week=:dow, start_time=:st, end_time=:et, 
                    location_type=:lt, location_detail=:ld
                WHERE id=:id
            ");
            $stmt->execute([
                'cid' => $courseId, 'dow' => $dayOfWeek, 'st' => $startTime, 'et' => $endTime,
                'lt' => $locationType === 'online' ? 'online' : 'physical', 'ld' => $locationDetail, 'id' => $scheduleId
            ]);

            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully.']);
        } catch (Exception $e) {
            error_log('Schedule PUT error: ' . $e->getMessage());
            sendJSONError('Failed to update schedule.', 500);
        }
        break;

    case 'DELETE':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $scheduleId = intval($input['id'] ?? 0);

        if ($scheduleId <= 0) sendJSONError('Valid Schedule ID is required.', 400);

        try {
            $stmt = $pdo->prepare("SELECT instructor_id FROM schedules WHERE id = :id");
            $stmt->execute(['id' => $scheduleId]);
            $schedule = $stmt->fetch();

            if (!$schedule) sendJSONError('Schedule not found.', 404);
            if (intval($schedule['instructor_id']) !== $instructorId) sendJSONError('Access denied.', 403);

            $deleteStmt = $pdo->prepare("DELETE FROM schedules WHERE id = :id");
            $deleteStmt->execute(['id' => $scheduleId]);

            echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully.']);
        } catch (Exception $e) {
            error_log('Schedule DELETE error: ' . $e->getMessage());
            sendJSONError('Failed to delete schedule.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
        break;
}
