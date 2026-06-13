<?php
// instructor/quizzes.php
// JSON API: CRUD quizzes with nested questions and options

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $courseId = intval($_GET['course_id'] ?? 0);
        $quizId = intval($_GET['quiz_id'] ?? 0);
        try {
            if ($quizId > 0) {
                // Fetch single quiz with questions and options
                $stmt = $pdo->prepare("
                    SELECT q.* FROM quizzes q
                    JOIN instructor_assignments ia ON q.course_id = ia.course_id
                    WHERE q.id = :qid AND ia.instructor_id = :iid
                ");
                $stmt->execute(['qid' => $quizId, 'iid' => $instructorId]);
                $quiz = $stmt->fetch();
                if (!$quiz) {
                    sendJSONError('Quiz not found.', 404);
                }
                
                // Fetch questions
                $qStmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = :qid ORDER BY order_index ASC");
                $qStmt->execute(['qid' => $quizId]);
                $questions = $qStmt->fetchAll();
                
                // Fetch options for each question
                foreach ($questions as &$q) {
                    $oStmt = $pdo->prepare("SELECT * FROM quiz_question_options WHERE question_id = :qid ORDER BY order_index ASC");
                    $oStmt->execute(['qid' => $q['id']]);
                    $q['options'] = $oStmt->fetchAll();
                }
                
                $quiz['questions'] = $questions;
                echo json_encode(['success' => true, 'data' => $quiz]);
            } elseif ($courseId > 0) {
                $stmt = $pdo->prepare("
                    SELECT q.*, 
                           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count
                    FROM quizzes q
                    JOIN instructor_assignments ia ON q.course_id = ia.course_id
                    WHERE ia.instructor_id = :iid AND q.course_id = :cid
                    ORDER BY q.created_at DESC
                ");
                $stmt->execute(['iid' => $instructorId, 'cid' => $courseId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT q.*, c.title as course_title,
                           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count
                    FROM quizzes q
                    JOIN instructor_assignments ia ON q.course_id = ia.course_id
                    JOIN courses c ON q.course_id = c.id
                    WHERE ia.instructor_id = :iid
                    ORDER BY q.created_at DESC
                ");
                $stmt->execute(['iid' => $instructorId]);
            }
            if ($quizId > 0) {
                // Already output above
            } else {
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            }
        } catch (Exception $e) {
            error_log('Quiz list error: ' . $e->getMessage());
            sendJSONError('Failed to fetch quizzes.', 500);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $action = $input['action'] ?? '';
        $quizId = intval($input['id'] ?? 0);
        $courseId = intval($input['course_id'] ?? 0);
        $classId = !empty($input['class_id']) ? intval($input['class_id']) : null;
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $timeLimit = !empty($input['time_limit_minutes']) ? intval($input['time_limit_minutes']) : null;
        $passingScore = intval($input['passing_score'] ?? 70);
        $maxAttempts = intval($input['max_attempts'] ?? 1);
        $status = trim($input['status'] ?? 'draft');

        if ($action === 'save') {
            if (empty($title) || $courseId <= 0) {
                sendJSONError('Title and course are required.');
            }

            // Verify instructor owns this course
            $authStmt = $pdo->prepare("SELECT 1 FROM instructor_assignments WHERE instructor_id = :iid AND course_id = :cid");
            $authStmt->execute(['iid' => $instructorId, 'cid' => $courseId]);
            if (!$authStmt->fetch()) {
                sendJSONError('Access denied: You do not teach this course.', 403);
            }

            try {
                $pdo->beginTransaction();

                if ($quizId > 0) {
                    // Verify quiz belongs to instructor
                    $ownStmt = $pdo->prepare("
                        SELECT 1 FROM quizzes q
                        JOIN instructor_assignments ia ON q.course_id = ia.course_id
                        WHERE q.id = :qid AND ia.instructor_id = :iid
                    ");
                    $ownStmt->execute(['qid' => $quizId, 'iid' => $instructorId]);
                    if (!$ownStmt->fetch()) {
                        $pdo->rollBack();
                        sendJSONError('Quiz not found or access denied.', 404);
                    }

                    $stmt = $pdo->prepare("
                        UPDATE quizzes SET title=:title, description=:desc, class_id=:clsid,
                            time_limit_minutes=:tlim, passing_score=:pscore, max_attempts=:ma, status=:status
                        WHERE id=:id
                    ");
                    $stmt->execute([
                        'title' => $title, 'desc' => $description, 'clsid' => $classId,
                        'tlim' => $timeLimit, 'pscore' => $passingScore, 'ma' => $maxAttempts,
                        'status' => $status, 'id' => $quizId
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO quizzes (course_id, class_id, title, description, time_limit_minutes, passing_score, max_attempts, status, created_by)
                        VALUES (:cid, :clsid, :title, :desc, :tlim, :pscore, :ma, :status, :created_by)
                    ");
                    $stmt->execute([
                        'cid' => $courseId, 'clsid' => $classId, 'title' => $title,
                        'desc' => $description, 'tlim' => $timeLimit, 'pscore' => $passingScore,
                        'ma' => $maxAttempts, 'status' => $status, 'created_by' => $instructorId
                    ]);
                    $quizId = $pdo->lastInsertId();
                }

                // Handle questions if provided
                if (isset($input['questions']) && is_array($input['questions'])) {
                    // Delete removed questions (only on full sync)
                    if ($action === 'save') {
                        $existingIds = array_filter(array_map(fn($q) => intval($q['id'] ?? 0), $input['questions']));
                        if (!empty($existingIds)) {
                            $placeholders = implode(',', array_fill(0, count($existingIds), '?'));
                            $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ? AND id NOT IN ($placeholders)")
                                ->execute(array_merge([$quizId], $existingIds));
                        } else {
                            $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?")->execute([$quizId]);
                        }
                    }

                    foreach ($input['questions'] as $qIdx => $q) {
                        $qid = intval($q['id'] ?? 0);
                        $qText = trim($q['question_text'] ?? '');
                        $qType = trim($q['question_type'] ?? 'multiple_choice');
                        $qPoints = intval($q['points'] ?? 10);
                        $qOrder = intval($q['order_index'] ?? $qIdx);

                        if (empty($qText)) continue;

                        if ($qid > 0) {
                            $qStmt = $pdo->prepare("UPDATE quiz_questions SET question_text=:qt, question_type=:qtype, points=:pts, order_index=:ord WHERE id=:qid AND quiz_id=:quizid");
                            $qStmt->execute(['qt' => $qText, 'qtype' => $qType, 'pts' => $qPoints, 'ord' => $qOrder, 'qid' => $qid, 'quizid' => $quizId]);
                        } else {
                            $qStmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, order_index) VALUES (:quizid, :qt, :qtype, :pts, :ord)");
                            $qStmt->execute(['quizid' => $quizId, 'qt' => $qText, 'qtype' => $qType, 'pts' => $qPoints, 'ord' => $qOrder]);
                            $qid = $pdo->lastInsertId();
                        }

                        // Handle options
                        if (isset($q['options']) && is_array($q['options'])) {
                            $existingOptIds = array_filter(array_map(fn($o) => intval($o['id'] ?? 0), $q['options']));
                            if (!empty($existingOptIds)) {
                                $placeholders = implode(',', array_fill(0, count($existingOptIds), '?'));
                                $pdo->prepare("DELETE FROM quiz_question_options WHERE question_id = ? AND id NOT IN ($placeholders)")
                                    ->execute(array_merge([$qid], $existingOptIds));
                            } else {
                                $pdo->prepare("DELETE FROM quiz_question_options WHERE question_id = ?")->execute([$qid]);
                            }

                            foreach ($q['options'] as $oIdx => $o) {
                                $oid = intval($o['id'] ?? 0);
                                $oText = trim($o['option_text'] ?? '');
                                $oCorrect = !empty($o['is_correct']) ? 1 : 0;
                                $oOrder = intval($o['order_index'] ?? $oIdx);
                                if (empty($oText)) continue;

                                if ($oid > 0) {
                                    $pdo->prepare("UPDATE quiz_question_options SET option_text=:ot, is_correct=:ic, order_index=:ord WHERE id=:oid AND question_id=:qid")
                                        ->execute(['ot' => $oText, 'ic' => $oCorrect, 'ord' => $oOrder, 'oid' => $oid, 'qid' => $qid]);
                                } else {
                                    $pdo->prepare("INSERT INTO quiz_question_options (question_id, option_text, is_correct, order_index) VALUES (:qid, :ot, :ic, :ord)")
                                        ->execute(['qid' => $qid, 'ot' => $oText, 'ic' => $oCorrect, 'ord' => $oOrder]);
                                }
                            }
                        }
                    }
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Quiz saved.', 'id' => $quizId]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Quiz save error: ' . $e->getMessage());
                sendJSONError('Failed to save quiz.', 500);
            }
        } elseif ($action === 'publish') {
            if ($quizId <= 0) sendJSONError('Quiz ID required.');
            try {
                // Verify ownership and ensure it has questions
                $ownStmt = $pdo->prepare("
                    SELECT q.id, (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as cnt
                    FROM quizzes q JOIN instructor_assignments ia ON q.course_id = ia.course_id
                    WHERE q.id = :qid AND ia.instructor_id = :iid
                ");
                $ownStmt->execute(['qid' => $quizId, 'iid' => $instructorId]);
                $quiz = $ownStmt->fetch();
                if (!$quiz) sendJSONError('Quiz not found.', 404);
                if ($quiz['cnt'] == 0) sendJSONError('Cannot publish a quiz with no questions.');

                $pdo->prepare("UPDATE quizzes SET status='published' WHERE id=:id")->execute(['id' => $quizId]);
                echo json_encode(['success' => true, 'message' => 'Quiz published.']);
            } catch (Exception $e) {
                error_log('Quiz publish error: ' . $e->getMessage());
                sendJSONError('Failed to publish quiz.', 500);
            }
        } else {
            sendJSONError('Invalid action.');
        }
        break;

    case 'DELETE':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $quizId = intval($input['id'] ?? 0);
        if ($quizId <= 0) sendJSONError('Quiz ID required.');

        try {
            $ownStmt = $pdo->prepare("
                SELECT 1 FROM quizzes q JOIN instructor_assignments ia ON q.course_id = ia.course_id
                WHERE q.id = :qid AND ia.instructor_id = :iid
            ");
            $ownStmt->execute(['qid' => $quizId, 'iid' => $instructorId]);
            if (!$ownStmt->fetch()) sendJSONError('Quiz not found.', 404);

            $pdo->prepare("DELETE FROM quizzes WHERE id = :id")->execute(['id' => $quizId]);
            echo json_encode(['success' => true, 'message' => 'Quiz deleted.']);
        } catch (Exception $e) {
            error_log('Quiz delete error: ' . $e->getMessage());
            sendJSONError('Failed to delete quiz.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
}
