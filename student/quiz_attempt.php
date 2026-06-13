<?php
// student/quiz_attempt.php
// JSON API: list available quizzes, start attempt, submit answers

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
                    SELECT q.*, 
                           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
                           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = :sid) as attempt_count
                    FROM quizzes q
                    JOIN enrollments e ON q.course_id = e.course_id AND e.student_id = :sid2 AND e.status = 'approved'
                    WHERE q.course_id = :cid AND q.status = 'published'
                    ORDER BY q.created_at DESC
                ");
                $stmt->execute(['sid' => $studentId, 'sid2' => $studentId, 'cid' => $courseId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT q.*, c.title as course_title,
                           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count,
                           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = :sid) as attempt_count
                    FROM quizzes q
                    JOIN enrollments e ON q.course_id = e.course_id AND e.student_id = :sid2 AND e.status = 'approved'
                    JOIN courses c ON q.course_id = c.id
                    WHERE q.status = 'published'
                    ORDER BY q.created_at DESC
                ");
                $stmt->execute(['sid' => $studentId, 'sid2' => $studentId]);
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            error_log('Student quiz list error: ' . $e->getMessage());
            sendJSONError('Failed to fetch quizzes.', 500);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $quizId = intval($input['quiz_id'] ?? 0);
        $attemptId = intval($input['attempt_id'] ?? 0);

        if ($quizId <= 0) sendJSONError('Quiz ID required.');

        try {
            // Verify student is enrolled in the course
            $enrollStmt = $pdo->prepare("
                SELECT 1 FROM enrollments WHERE student_id = :sid AND course_id = (SELECT course_id FROM quizzes WHERE id = :qid) AND status = 'approved'
            ");
            $enrollStmt->execute(['sid' => $studentId, 'qid' => $quizId]);
            if (!$enrollStmt->fetch()) sendJSONError('Access denied.', 403);

            if ($action === 'start') {
                // Check max attempts
                $quizStmt = $pdo->prepare("SELECT max_attempts FROM quizzes WHERE id = :qid");
                $quizStmt->execute(['qid' => $quizId]);
                $maxAttempts = intval($quizStmt->fetchColumn());

                if ($maxAttempts > 0) {
                    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = :qid AND student_id = :sid");
                    $cntStmt->execute(['qid' => $quizId, 'sid' => $studentId]);
                    $prevAttempts = intval($cntStmt->fetchColumn());
                    if ($prevAttempts >= $maxAttempts) {
                        sendJSONError('Maximum attempts reached for this quiz.', 403);
                    }
                }

                // Check for incomplete attempt
                $incStmt = $pdo->prepare("SELECT id FROM quiz_attempts WHERE quiz_id = :qid AND student_id = :sid AND completed_at IS NULL");
                $incStmt->execute(['qid' => $quizId, 'sid' => $studentId]);
                $existing = $incStmt->fetch();
                if ($existing) {
                    echo json_encode(['success' => true, 'message' => 'Resuming existing attempt.', 'data' => ['attempt_id' => intval($existing['id']), 'resumed' => true]]);
                    exit;
                }

                $insStmt = $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, student_id) VALUES (:qid, :sid)");
                $insStmt->execute(['qid' => $quizId, 'sid' => $studentId]);
                $attemptId = $pdo->lastInsertId();

                echo json_encode(['success' => true, 'message' => 'Attempt started.', 'data' => ['attempt_id' => $attemptId]]);

            } elseif ($action === 'questions') {
                if ($attemptId <= 0) sendJSONError('Attempt ID required.');

                // Verify attempt belongs to student
                $ownStmt = $pdo->prepare("SELECT 1 FROM quiz_attempts WHERE id = :aid AND student_id = :sid");
                $ownStmt->execute(['aid' => $attemptId, 'sid' => $studentId]);
                if (!$ownStmt->fetch()) sendJSONError('Access denied.', 403);

                $stmt = $pdo->prepare("
                    SELECT qq.id, qq.question_text, qq.question_type, qq.points, qq.order_index,
                           qa.id as answer_id, qa.selected_option_id, qa.answer_text, qa.points_earned, qa.is_correct,
                           qat.completed_at IS NOT NULL as is_submitted
                    FROM quiz_questions qq
                    LEFT JOIN quiz_answers qa ON qq.id = qa.question_id AND qa.attempt_id = :aid
                    JOIN quiz_attempts qat ON qat.id = :aid2
                    WHERE qq.quiz_id = (SELECT quiz_id FROM quiz_attempts WHERE id = :aid3)
                    ORDER BY qq.order_index ASC, qq.id ASC
                ");
                $stmt->execute(['aid' => $attemptId, 'aid2' => $attemptId, 'aid3' => $attemptId]);
                $questions = $stmt->fetchAll();

                // Get options for MC/TF questions
                $qIds = array_filter(array_map(fn($q) => $q['is_submitted'] ? 0 : $q['id'], $questions));
                $options = [];
                if (!empty($qIds)) {
                    $placeholders = implode(',', array_fill(0, count($qIds), '?'));
                    $optStmt = $pdo->prepare("
                        SELECT id, question_id, option_text, order_index 
                        FROM quiz_question_options 
                        WHERE question_id IN ($placeholders)
                        ORDER BY order_index ASC, id ASC
                    ");
                    $optStmt->execute(array_values($qIds));
                    foreach ($optStmt->fetchAll() as $o) {
                        $options[$o['question_id']][] = $o;
                    }
                }

                // Get time limit
                $tlStmt = $pdo->prepare("
                    SELECT q.time_limit_minutes, qat.started_at 
                    FROM quizzes q JOIN quiz_attempts qat ON q.id = qat.quiz_id 
                    WHERE qat.id = :aid
                ");
                $tlStmt->execute(['aid' => $attemptId]);
                $timeData = $tlStmt->fetch();

                echo json_encode([
                    'success' => true, 'data' => [
                        'questions' => $questions,
                        'options' => $options,
                        'time_limit_minutes' => $timeData ? intval($timeData['time_limit_minutes']) : null,
                        'started_at' => $timeData ? $timeData['started_at'] : null
                    ]
                ]);

            } elseif ($action === 'answer') {
                $questionId = intval($input['question_id'] ?? 0);
                $selectedOptionId = !empty($input['selected_option_id']) ? intval($input['selected_option_id']) : null;
                $answerText = trim($input['answer_text'] ?? '');

                if ($attemptId <= 0 || $questionId <= 0) sendJSONError('Attempt ID and question ID required.');

                // Verify attempt belongs to student
                $ownStmt = $pdo->prepare("SELECT 1 FROM quiz_attempts WHERE id = :aid AND student_id = :sid AND completed_at IS NULL");
                $ownStmt->execute(['aid' => $attemptId, 'sid' => $studentId]);
                if (!$ownStmt->fetch()) sendJSONError('Access denied or attempt already submitted.', 403);

                // Upsert answer
                $existingStmt = $pdo->prepare("SELECT id FROM quiz_answers WHERE attempt_id = :aid AND question_id = :qid");
                $existingStmt->execute(['aid' => $attemptId, 'qid' => $questionId]);
                $existing = $existingStmt->fetch();

                // Auto-grade MC/TF
                $qTypeStmt = $pdo->prepare("SELECT question_type, points FROM quiz_questions WHERE id = :qid");
                $qTypeStmt->execute(['qid' => $questionId]);
                $qType = $qTypeStmt->fetch();
                $autoGrade = in_array($qType['question_type'], ['multiple_choice', 'true_false']);
                $correct = 0;
                $pointsEarned = null;

                if ($autoGrade && $selectedOptionId) {
                    $correctStmt = $pdo->prepare("SELECT is_correct FROM quiz_question_options WHERE id = :oid");
                    $correctStmt->execute(['oid' => $selectedOptionId]);
                    $correct = intval($correctStmt->fetchColumn());
                    $pointsEarned = $correct ? intval($qType['points']) : 0;
                }

                if ($existing) {
                    $updStmt = $pdo->prepare("UPDATE quiz_answers SET selected_option_id=:soi, answer_text=:at, is_correct=:ic, points_earned=:pe WHERE id=:id");
                    $updStmt->execute(['soi' => $selectedOptionId, 'at' => $answerText, 'ic' => $correct, 'pe' => $pointsEarned, 'id' => $existing['id']]);
                } else {
                    $insStmt = $pdo->prepare("INSERT INTO quiz_answers (attempt_id, question_id, selected_option_id, answer_text, is_correct, points_earned) VALUES (:aid, :qid, :soi, :at, :ic, :pe)");
                    $insStmt->execute(['aid' => $attemptId, 'qid' => $questionId, 'soi' => $selectedOptionId, 'at' => $answerText, 'ic' => $correct, 'pe' => $pointsEarned]);
                }

                echo json_encode(['success' => true, 'message' => 'Answer saved.']);

            } elseif ($action === 'submit') {
                if ($attemptId <= 0) sendJSONError('Attempt ID required.');

                $ownStmt = $pdo->prepare("SELECT 1 FROM quiz_attempts WHERE id = :aid AND student_id = :sid AND completed_at IS NULL");
                $ownStmt->execute(['aid' => $attemptId, 'sid' => $studentId]);
                if (!$ownStmt->fetch()) sendJSONError('Access denied or already submitted.', 403);

                // Calculate score
                $calcStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(points_earned), 0), COALESCE(SUM(qq.points), 0)
                    FROM quiz_answers qa
                    JOIN quiz_questions qq ON qa.question_id = qq.id
                    WHERE qa.attempt_id = :aid
                ");
                $calcStmt->execute(['aid' => $attemptId]);
                $row = $calcStmt->fetch();
                $score = intval($row[0]);
                $total = intval($row[1]);

                $quizStmt = $pdo->prepare("
                    SELECT q.passing_score FROM quizzes q JOIN quiz_attempts qat ON q.id = qat.quiz_id WHERE qat.id = :aid
                ");
                $quizStmt->execute(['aid' => $attemptId]);
                $passingScore = intval($quizStmt->fetchColumn());
                $passed = ($total > 0 && ($score / $total * 100) >= $passingScore) ? 1 : 0;

                $pdo->prepare("UPDATE quiz_attempts SET score = :score, total_points = :total, passed = :passed, completed_at = NOW() WHERE id = :id")
                    ->execute(['score' => $score, 'total' => $total, 'passed' => $passed, 'id' => $attemptId]);

                echo json_encode([
                    'success' => true, 'message' => 'Quiz submitted.',
                    'data' => ['score' => $score, 'total_points' => $total, 'passed' => (bool)$passed]
                ]);

            } elseif ($action === 'results') {
                if ($attemptId <= 0) sendJSONError('Attempt ID required.');

                $ownStmt = $pdo->prepare("SELECT 1 FROM quiz_attempts WHERE id = :aid AND student_id = :sid AND completed_at IS NOT NULL");
                $ownStmt->execute(['aid' => $attemptId, 'sid' => $studentId]);
                if (!$ownStmt->fetch()) sendJSONError('Access denied.', 403);

                $attemptStmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE id = :aid");
                $attemptStmt->execute(['aid' => $attemptId]);
                $attempt = $attemptStmt->fetch();

                $answersStmt = $pdo->prepare("
                    SELECT qq.id, qq.question_text, qq.question_type, qq.points as max_points,
                           qa.selected_option_id, qa.answer_text, qa.points_earned, qa.is_correct,
                           qqo.option_text as selected_option_text
                    FROM quiz_questions qq
                    LEFT JOIN quiz_answers qa ON qq.id = qa.question_id AND qa.attempt_id = :aid
                    LEFT JOIN quiz_question_options qqo ON qa.selected_option_id = qqo.id
                    WHERE qq.quiz_id = (SELECT quiz_id FROM quiz_attempts WHERE id = :aid2)
                    ORDER BY qq.order_index ASC, qq.id ASC
                ");
                $answersStmt->execute(['aid' => $attemptId, 'aid2' => $attemptId]);
                $answers = $answersStmt->fetchAll();

                // Get correct options for each question
                $correctOptsStmt = $pdo->prepare("
                    SELECT qqo.question_id, qqo.id as option_id, qqo.option_text
                    FROM quiz_question_options qqo
                    JOIN quiz_questions qq ON qqo.question_id = qq.id
                    JOIN quiz_attempts qat ON qq.quiz_id = qat.quiz_id
                    WHERE qat.id = :aid AND qqo.is_correct = 1
                ");
                $correctOptsStmt->execute(['aid' => $attemptId]);
                $correctOptions = [];
                foreach ($correctOptsStmt->fetchAll() as $co) {
                    $correctOptions[$co['question_id']][] = $co;
                }

                echo json_encode([
                    'success' => true, 'data' => [
                        'attempt' => $attempt,
                        'answers' => $answers,
                        'correct_options' => $correctOptions
                    ]
                ]);
            } elseif ($action === 'my_attempts') {
                $stmt = $pdo->prepare("
                    SELECT id, score, total_points, passed, completed_at, started_at
                    FROM quiz_attempts
                    WHERE quiz_id = :qid AND student_id = :sid AND completed_at IS NOT NULL
                    ORDER BY completed_at DESC
                ");
                $stmt->execute(['qid' => $quizId, 'sid' => $studentId]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);

            } else {
                sendJSONError('Invalid action.');
            }
        } catch (Exception $e) {
            error_log('Quiz attempt error: ' . $e->getMessage());
            sendJSONError('Failed to process quiz attempt.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
}
