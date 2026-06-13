<?php
// instructor/submissions.php
// Operations to fetch homework submissions and submit points and feedback

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';

$instructorId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $assignmentId = intval($_GET['assignment_id'] ?? 0);
        if ($assignmentId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Assignment ID is required.']);
            exit;
        }

        try {
            // Verify access to course via assignment
            $verifyStmt = $pdo->prepare("
                SELECT a.id 
                FROM assignments a
                JOIN instructor_assignments ia ON a.course_id = ia.course_id
                WHERE a.id = :assignment_id AND ia.instructor_id = :instructor_id
            ");
            $verifyStmt->execute(['assignment_id' => $assignmentId, 'instructor_id' => $instructorId]);
            if (!$verifyStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: Instructor not assigned to this course.']);
                exit;
            }

            // Fetch submissions
            $stmt = $pdo->prepare("
                SELECT s.*, u.name as student_name, u.email as student_email
                FROM submissions s
                JOIN users u ON s.student_id = u.id
                WHERE s.assignment_id = :assignment_id
                ORDER BY s.submitted_at DESC
            ");
            $stmt->execute(['assignment_id' => $assignmentId]);
            $submissions = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $submissions]);
        } catch (Exception $e) {
            error_log('Submissions fetch error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Fetch failed.']);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();
        // Grade a student submission (Rate and give feedback)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $submissionId = intval($input['submission_id'] ?? 0);
        $pointsEarned = isset($input['points_earned']) ? intval($input['points_earned']) : null;
        $feedback = trim($input['feedback'] ?? '');

        if ($submissionId <= 0 || $pointsEarned === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Submission ID and Points Earned are required.']);
            exit;
        }

        try {
            // Verify access through submission -> assignment -> course
            $verifyStmt = $pdo->prepare("
                SELECT s.id 
                FROM submissions s
                JOIN assignments a ON s.assignment_id = a.id
                JOIN instructor_assignments ia ON a.course_id = ia.course_id
                WHERE s.id = :submission_id AND ia.instructor_id = :instructor_id
            ");
            $verifyStmt->execute(['submission_id' => $submissionId, 'instructor_id' => $instructorId]);
            if (!$verifyStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: You are not authorized to grade this submission.']);
                exit;
            }

            // Grade submission
            $stmt = $pdo->prepare("
                UPDATE submissions 
                SET points_earned = :points_earned, 
                    feedback = :feedback, 
                    status = 'graded', 
                    graded_by = :graded_by, 
                    graded_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                'points_earned' => $pointsEarned,
                'feedback' => $feedback,
                'graded_by' => $instructorId,
                'id' => $submissionId
            ]);

            echo json_encode(['success' => true, 'message' => 'Submission graded and reviewed successfully.']);
        } catch (Exception $e) {
            error_log('Submissions grade error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Grading update failed.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
}
