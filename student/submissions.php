<?php
// student/submissions.php
// Retrieve assignments and submit homework files (supports audio/video uploads)

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';

$studentId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $courseId = intval($_GET['course_id'] ?? 0);
        if ($courseId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Course ID is required.']);
            exit;
        }

        try {
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

            // Fetch course assignments with student submissions, feedback, and grading status
            $stmt = $pdo->prepare("
                SELECT a.id as assignment_id, a.title, a.description, a.due_date, a.max_points,
                       s.id as submission_id, s.file_path, s.submission_text, s.points_earned, 
                       s.feedback, s.status as submission_status, s.submitted_at
                FROM assignments a
                LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = :student_id
                WHERE a.course_id = :course_id
                ORDER BY a.due_date ASC
            ");
            $stmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
            $assignments = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $assignments]);
        } catch (Exception $e) {
            error_log('Student submissions fetch error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to load assignments.']);
        }
        break;

    case 'POST':
        require_csrf();
        // Submit Homework file (Audio/Video uploads supported)
        $assignmentId = intval($_POST['assignment_id'] ?? 0);
        $submissionText = trim($_POST['submission_text'] ?? '');

        if ($assignmentId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Assignment ID is required.']);
            exit;
        }

        // Verify assignment exists and student is enrolled in the parent course
        try {
            $verifyStmt = $pdo->prepare("
                SELECT a.id, a.course_id 
                FROM assignments a
                JOIN enrollments e ON a.course_id = e.course_id
                WHERE a.id = :assignment_id AND e.student_id = :student_id AND e.status = 'approved'
            ");
            $verifyStmt->execute(['assignment_id' => $assignmentId, 'student_id' => $studentId]);
            $assignment = $verifyStmt->fetch();
            if (!$assignment) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: You are not authorized to submit for this assignment.']);
                exit;
            }
        } catch (Exception $e) {
            error_log('Student submission verification error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Verification failed.']);
            exit;
        }

        // Validate file upload
        if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'File upload error or no file provided.']);
            exit;
        }

        $file = $_FILES['submission_file'];
        $fileName = basename($file['name']);
        $fileSize = $file['size'];
        $tmpPath  = $file['tmp_name'];

        // 1. Validate size (20MB limit)
        $maxSize = 20 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            http_response_code(400);
            echo json_encode(['error' => 'File size exceeds maximum limit of 20MB.']);
            exit;
        }

        // 2. Validate file extension
        $allowedExtensions = ['pdf', 'mp3', 'wav', 'mp4', 'mov', 'doc', 'docx'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file extension. Allowed: PDF, MP3, WAV, MP4, MOV, DOC, DOCX.']);
            exit;
        }

        // 3. Setup uploads directory
        $uploadDir = __DIR__ . '/../uploads/submissions/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $secureName = uniqid('sub_', true) . '.' . $ext;
        $destPath   = $uploadDir . $secureName;

        if (move_uploaded_file($tmpPath, $destPath)) {
            try {
                // Delete previous submission if exists (prevents orphan files)
                $prevStmt = $pdo->prepare("SELECT file_path FROM submissions WHERE assignment_id = :assignment_id AND student_id = :student_id");
                $prevStmt->execute(['assignment_id' => $assignmentId, 'student_id' => $studentId]);
                $prevFile = $prevStmt->fetchColumn();
                if ($prevFile && str_starts_with($prevFile, '/uploads/submissions/') && file_exists(__DIR__ . '/..' . $prevFile)) {
                    unlink(__DIR__ . '/..' . $prevFile);
                }

                // Insert/Update Submission
                $dbPath = '/uploads/submissions/' . $secureName;
                $stmt = $pdo->prepare("
                    INSERT INTO submissions (assignment_id, student_id, file_path, submission_text, status) 
                    VALUES (:assignment_id, :student_id, :file_path, :submission_text, 'submitted')
                    ON DUPLICATE KEY UPDATE file_path = :file_path, submission_text = :submission_text, status = 'submitted', submitted_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([
                    'assignment_id' => $assignmentId,
                    'student_id' => $studentId,
                    'file_path' => $dbPath,
                    'submission_text' => $submissionText
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Homework submitted successfully.',
                    'file_path' => $dbPath
                ]);
            } catch (Exception $e) {
                error_log('Student submission save error: ' . $e->getMessage());
                if (file_exists($destPath)) {
                    unlink($destPath);
                }
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save submission.']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move uploaded file.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
}
