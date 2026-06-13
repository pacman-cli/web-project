<?php
// student/participation.php
// API: track lesson participation (mark materials watched, sessions attended)

header('Content-Type: application/json');
require_once __DIR__ . '/../api/middleware.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $courseId = intval($_GET['course_id'] ?? 0);
        try {
            if ($courseId > 0) {
                $stmt = $pdo->prepare("
                    SELECT cs.id as session_id, cs.title, cs.date, cs.start_time, cs.end_time, cs.status as session_status,
                           lp.id as participation_id, lp.watched_duration, lp.completed, lp.last_accessed,
                           c.title as course_title
                    FROM class_sessions cs
                    JOIN courses c ON cs.course_id = c.id
                    JOIN enrollments e ON c.id = e.course_id AND e.student_id = :sid AND e.status = 'approved'
                    LEFT JOIN lesson_participation lp ON cs.id = lp.class_id AND lp.student_id = :sid2
                    WHERE cs.course_id = :cid AND cs.status = 'completed'
                    ORDER BY cs.date DESC, cs.start_time ASC
                ");
                $stmt->execute(['sid' => $studentId, 'sid2' => $studentId, 'cid' => $courseId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT cs.id as session_id, cs.title, cs.date, c.title as course_title,
                           lp.id as participation_id, lp.completed
                    FROM class_sessions cs
                    JOIN courses c ON cs.course_id = c.id
                    JOIN enrollments e ON c.id = e.course_id AND e.student_id = :sid AND e.status = 'approved'
                    LEFT JOIN lesson_participation lp ON cs.id = lp.class_id AND lp.student_id = :sid2
                    WHERE cs.status = 'completed'
                    ORDER BY cs.date DESC LIMIT 50
                ");
                $stmt->execute(['sid' => $studentId, 'sid2' => $studentId]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            error_log('Participation GET error: ' . $e->getMessage());
            sendJSONError('Failed to fetch participation.', 500);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'complete_session') {
            $sessionId = intval($input['session_id'] ?? 0);
            $classId = intval($input['class_id'] ?? $sessionId);
            if ($classId <= 0) sendJSONError('class_id required.', 400);

            try {
                // Verify student enrollment in the session's course
                $verifyStmt = $pdo->prepare("
                    SELECT 1 FROM class_sessions cs
                    JOIN enrollments e ON cs.course_id = e.course_id AND e.student_id = :sid AND e.status = 'approved'
                    WHERE cs.id = :cid
                ");
                $verifyStmt->execute(['sid' => $studentId, 'cid' => $classId]);
                if (!$verifyStmt->fetch()) sendJSONError('Access denied.', 403);

                // Upsert participation
                $existingStmt = $pdo->prepare("
                    SELECT id FROM lesson_participation WHERE student_id = :sid AND class_id = :cid AND material_id IS NULL
                ");
                $existingStmt->execute(['sid' => $studentId, 'cid' => $classId]);
                $existing = $existingStmt->fetch();

                if ($existing) {
                    $pdo->prepare("UPDATE lesson_participation SET completed = 1, last_accessed = NOW() WHERE id = :id")
                        ->execute(['id' => $existing['id']]);
                } else {
                    $csStmt = $pdo->prepare("SELECT course_id FROM class_sessions WHERE id = :id");
                    $csStmt->execute(['id' => $classId]);
                    $ci = $csStmt->fetch();

                    $pdo->prepare("
                        INSERT INTO lesson_participation (course_id, class_id, student_id, completed)
                        VALUES (:cid, :clid, :sid, 1)
                    ")->execute(['cid' => $ci['course_id'], 'clid' => $classId, 'sid' => $studentId]);
                }

                echo json_encode(['success' => true, 'message' => 'Session marked as completed.']);
            } catch (Exception $e) {
                error_log('Participation POST error: ' . $e->getMessage());
                sendJSONError('Failed to record participation.', 500);
            }
        } else {
            sendJSONError('Invalid action.');
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
}
