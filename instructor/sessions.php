<?php
// instructor/sessions.php
// Class sessions CRUD for instructors

header('Content-Type: application/json');
require_once __DIR__ . '/../api/middleware.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $courseId = intval($_GET['course_id'] ?? 0);
        try {
            if ($courseId > 0) {
                $stmt = $pdo->prepare("
                    SELECT cs.*, c.title as course_title,
                           (SELECT COUNT(*) FROM lesson_participation lp 
                            WHERE lp.class_id = cs.id AND lp.completed = 1) as completed_count,
                           (SELECT COUNT(*) FROM enrollments e 
                            WHERE e.course_id = cs.course_id AND e.status = 'approved') as enrolled_count
                    FROM class_sessions cs
                    JOIN courses c ON cs.course_id = c.id
                    JOIN instructor_assignments ia ON cs.course_id = ia.course_id
                    WHERE ia.instructor_id = :iid AND cs.course_id = :cid
                    ORDER BY cs.date DESC, cs.start_time ASC
                ");
                $stmt->execute(['iid' => $instructorId, 'cid' => $courseId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT cs.*, c.title as course_title
                    FROM class_sessions cs
                    JOIN courses c ON cs.course_id = c.id
                    JOIN instructor_assignments ia ON cs.course_id = ia.course_id
                    WHERE ia.instructor_id = :iid
                    ORDER BY cs.date DESC, cs.start_time ASC LIMIT 50
                ");
                $stmt->execute(['iid' => $instructorId]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            error_log('Sessions GET error: ' . $e->getMessage());
            sendJSONError('Failed to fetch sessions.', 500);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $courseId = intval($input['course_id'] ?? 0);
        $scheduleId = intval($input['schedule_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $date = trim($input['date'] ?? '');
        $startTime = trim($input['start_time'] ?? '');
        $endTime = trim($input['end_time'] ?? '');
        $locationType = trim($input['location_type'] ?? 'physical');
        $locationDetail = trim($input['location_detail'] ?? '');
        $status = trim($input['status'] ?? 'scheduled');
        $notes = trim($input['notes'] ?? '');

        if ($courseId <= 0 || empty($date) || empty($startTime) || empty($endTime)) {
            sendJSONError('course_id, date, start_time, and end_time are required.', 400);
        }

        try {
            $verifyStmt = $pdo->prepare("SELECT 1 FROM instructor_assignments WHERE instructor_id = :iid AND course_id = :cid");
            $verifyStmt->execute(['iid' => $instructorId, 'cid' => $courseId]);
            if (!$verifyStmt->fetch()) sendJSONError('Access denied.', 403);

            $stmt = $pdo->prepare("
                INSERT INTO class_sessions (schedule_id, course_id, instructor_id, title, date, start_time, end_time, location_type, location_detail, status, notes)
                VALUES (:sid, :cid, :iid, :title, :date, :st, :et, :lt, :ld, :s, :notes)
            ");
            $stmt->execute([
                'sid' => $scheduleId > 0 ? $scheduleId : null,
                'cid' => $courseId,
                'iid' => $instructorId,
                'title' => $title ?: null,
                'date' => $date,
                'st' => $startTime,
                'et' => $endTime,
                'lt' => $locationType,
                'ld' => $locationDetail ?: null,
                's' => $status,
                'notes' => $notes ?: null
            ]);

            echo json_encode(['success' => true, 'message' => 'Session created.', 'id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            error_log('Sessions POST error: ' . $e->getMessage());
            sendJSONError('Failed to create session.', 500);
        }
        break;

    case 'PUT':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $sessionId = intval($input['id'] ?? 0);

        try {
            $ownerStmt = $pdo->prepare("SELECT instructor_id FROM class_sessions WHERE id = :id");
            $ownerStmt->execute(['id' => $sessionId]);
            $s = $ownerStmt->fetch();
            if (!$s) sendJSONError('Session not found.', 404);
            if (intval($s['instructor_id']) !== $instructorId) sendJSONError('Access denied.', 403);

            $fields = ['title', 'date', 'start_time', 'end_time', 'location_type', 'location_detail', 'status', 'notes'];
            $sets = [];
            $params = ['id' => $sessionId];
            foreach ($fields as $f) {
                if (isset($input[$f])) {
                    $v = trim($input[$f]);
                    if ($v !== '') {
                        $sets[] = "$f = :$f";
                        $params[$f] = $v;
                    }
                }
            }
            if (empty($sets)) sendJSONError('No fields to update.', 400);

            $stmt = $pdo->prepare("UPDATE class_sessions SET " . implode(', ', $sets) . " WHERE id = :id");
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'Session updated.']);
        } catch (Exception $e) {
            error_log('Sessions PUT error: ' . $e->getMessage());
            sendJSONError('Failed to update session.', 500);
        }
        break;

    case 'DELETE':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $sessionId = intval($input['id'] ?? 0);

        try {
            $ownerStmt = $pdo->prepare("SELECT instructor_id FROM class_sessions WHERE id = :id");
            $ownerStmt->execute(['id' => $sessionId]);
            $s = $ownerStmt->fetch();
            if (!$s) sendJSONError('Session not found.', 404);
            if (intval($s['instructor_id']) !== $instructorId) sendJSONError('Access denied.', 403);

            $pdo->prepare("DELETE FROM class_sessions WHERE id = :id")->execute(['id' => $sessionId]);
            echo json_encode(['success' => true, 'message' => 'Session deleted.']);
        } catch (Exception $e) {
            error_log('Sessions DELETE error: ' . $e->getMessage());
            sendJSONError('Failed to delete session.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
}
