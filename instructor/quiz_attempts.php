<?php
// instructor/quiz_attempts.php
// JSON API: list student attempts and grade essay/short_answer questions

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';
$instructorId = $_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $quizId = intval($_GET['quiz_id'] ?? 0);
        if ($quizId <= 0) sendJSONError('quiz_id required');

        try {
            // Verify instructor owns the quiz's course
            $authStmt = $pdo->prepare("
                SELECT 1 FROM quizzes q
                JOIN instructor_assignments ia ON q.course_id = ia.course_id
                WHERE q.id = :qid AND ia.instructor_id = :iid
            ");
            $authStmt->execute(['qid' => $quizId, 'iid' => $instructorId]);
            if (!$authStmt->fetch()) sendJSONError('Access denied.', 403);

            $stmt = $pdo->prepare("
                SELECT qa.id, qa.student_id, u.name as student_name, qa.score, qa.total_points,
                       qa.passed, qa.started_at, qa.completed_at,
                       (SELECT COUNT(*) FROM quiz_answers WHERE attempt_id = qa.id AND is_correct = 1) as correct_count,
                       (SELECT COUNT(*) FROM quiz_answers WHERE attempt_id = qa.id) as answered_count
                FROM quiz_attempts qa
                JOIN users u ON qa.student_id = u.id
                WHERE qa.quiz_id = :qid
                ORDER BY qa.completed_at DESC
            ");
            $stmt->execute(['qid' => $quizId]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            error_log('Quiz attempts list error: ' . $e->getMessage());
            sendJSONError('Failed to fetch attempts.', 500);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();

        $input = json_decode(file_get_contents('php://input'), true);
        $answerId = intval($input['answer_id'] ?? 0);
        $pointsEarned = $input['points_earned']; // nullable — allow 0
        $attemptId = intval($input['attempt_id'] ?? 0);

        if ($answerId <= 0 || $pointsEarned === null) {
            sendJSONError('answer_id and points_earned required.');
        }

        try {
            // Verify instructor owns the course this attempt's quiz belongs to
            $authStmt = $pdo->prepare("
                SELECT 1 FROM quiz_answers qa
                JOIN quiz_attempts qat ON qa.attempt_id = qat.id
                JOIN quizzes q ON qat.quiz_id = q.id
                JOIN instructor_assignments ia ON q.course_id = ia.course_id
                WHERE qa.id = :aid AND ia.instructor_id = :iid
            ");
            $authStmt->execute(['aid' => $answerId, 'iid' => $instructorId]);
            if (!$authStmt->fetch()) sendJSONError('Access denied.', 403);

            // Get max points for validation
            $maxStmt = $pdo->prepare("
                SELECT qq.points FROM quiz_answers qa
                JOIN quiz_questions qq ON qa.question_id = qq.id
                WHERE qa.id = :aid
            ");
            $maxStmt->execute(['aid' => $answerId]);
            $maxPoints = intval($maxStmt->fetchColumn());

            $pointsEarned = intval($pointsEarned);
            if ($pointsEarned < 0) $pointsEarned = 0;
            if ($pointsEarned > $maxPoints) $pointsEarned = $maxPoints;

            $isCorrect = $pointsEarned >= $maxPoints ? 1 : 0;

            $pdo->prepare("UPDATE quiz_answers SET points_earned = :pe, is_correct = :ic WHERE id = :id")
                ->execute(['pe' => $pointsEarned, 'ic' => $isCorrect, 'id' => $answerId]);

            // Recalculate attempt score
            if ($attemptId > 0) {
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

                // Get passing score for this quiz
                $quizStmt = $pdo->prepare("SELECT passing_score FROM quizzes q JOIN quiz_attempts qat ON q.id = qat.quiz_id WHERE qat.id = :aid");
                $quizStmt->execute(['aid' => $attemptId]);
                $passingScore = intval($quizStmt->fetchColumn());
                $passed = ($total > 0 && ($score / $total * 100) >= $passingScore) ? 1 : 0;

                $pdo->prepare("UPDATE quiz_attempts SET score = :score, total_points = :total, passed = :passed WHERE id = :id")
                    ->execute(['score' => $score, 'total' => $total, 'passed' => $passed, 'id' => $attemptId]);
            }

            echo json_encode(['success' => true, 'message' => 'Grade saved.']);
        } catch (Exception $e) {
            error_log('Quiz grade error: ' . $e->getMessage());
            sendJSONError('Failed to save grade.', 500);
        }
        break;

    default:
        sendJSONError('Method not allowed.', 405);
}
