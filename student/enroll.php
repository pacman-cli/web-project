<?php
// student/enroll.php
// Student API endpoint to request enrollment in a course (with resume upload)

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
requireRole('student');

$pdo = require_once __DIR__ . '/../config/db.php';
$studentId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONError('Method not allowed. Only POST is supported.', 405);
}

require_once __DIR__ . '/../config/csrf.php';
require_csrf();

// Accept multipart form data (file upload) or JSON fallback
$courseId = intval($_POST['course_id'] ?? 0);

if ($courseId <= 0) {
    sendJSONError('Valid Course ID is required.');
}

// ── Handle Resume Upload ──────────────────────────────────────────────────────
$resumePath = null;

if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['resume'];
    $fileName = basename($file['name']);
    $fileSize = $file['size'];
    $tmpPath  = $file['tmp_name'];

    // 5MB limit for resumes
    $maxSize = 5 * 1024 * 1024;
    if ($fileSize > $maxSize) {
        sendJSONError('Resume file size exceeds maximum allowed limit (5MB).');
    }

    // Validate extension — PDF only
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        sendJSONError('Resume must be a PDF file.');
    }

    // MIME validation
    $actualMime = mime_content_type($tmpPath);
    if (!$actualMime || $actualMime !== 'application/pdf') {
        sendJSONError('File content type does not match PDF. Upload rejected.');
    }

    // Store in /uploads/{user_id}/resume/
    $relativeDir = "/uploads/{$studentId}/resume/";
    $uploadDir = __DIR__ . '/..' . $relativeDir;

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $storedName = uniqid('resume_', true) . '.pdf';
    $destPath   = $uploadDir . $storedName;
    $dbFilePath = $relativeDir . $storedName;

    if (move_uploaded_file($tmpPath, $destPath)) {
        $resumePath = $dbFilePath;
    } else {
        sendJSONError('Failed to save resume file. Check directory permissions.', 500);
    }
} elseif (isset($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
    sendJSONError('Resume upload encountered an error. Please try again.');
}

try {
    // 1. Verify course exists and is published
    $courseStmt = $pdo->prepare("SELECT status FROM courses WHERE id = :course_id");
    $courseStmt->execute(['course_id' => $courseId]);
    $course = $courseStmt->fetch();

    if (!$course || $course['status'] !== 'published') {
        sendJSONError('Course not found or not currently open for enrollment.', 404);
    }

    // 2. Check for existing enrollment record
    $checkStmt = $pdo->prepare("SELECT id, status FROM enrollments WHERE student_id = :student_id AND course_id = :course_id");
    $checkStmt->execute(['student_id' => $studentId, 'course_id' => $courseId]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        if ($existing['status'] === 'approved') {
            sendJSONError('You are already enrolled in this course.');
        } elseif ($existing['status'] === 'pending') {
            sendJSONError('Your enrollment request is already pending review.');
        } else {
            // Re-submit: update status to pending, optionally update resume
            if ($resumePath) {
                $updateStmt = $pdo->prepare("
                    UPDATE enrollments 
                    SET status = 'pending', rejection_reason = NULL, resume_path = :resume_path, enrolled_at = CURRENT_TIMESTAMP 
                    WHERE id = :id
                ");
                $updateStmt->execute(['id' => $existing['id'], 'resume_path' => $resumePath]);
            } else {
                $updateStmt = $pdo->prepare("
                    UPDATE enrollments 
                    SET status = 'pending', rejection_reason = NULL, enrolled_at = CURRENT_TIMESTAMP 
                    WHERE id = :id
                ");
                $updateStmt->execute(['id' => $existing['id']]);
            }
            echo json_encode([
                'success' => true,
                'message' => 'Enrollment request re-submitted successfully.'
            ]);
            exit;
        }
    }

    // 3. New enrollment — resume is required
    if (!$resumePath) {
        sendJSONError('Please upload your resume (PDF) when requesting enrollment.');
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO enrollments (student_id, course_id, status, resume_path) 
        VALUES (:student_id, :course_id, 'pending', :resume_path)
    ");
    $insertStmt->execute([
        'student_id' => $studentId,
        'course_id' => $courseId,
        'resume_path' => $resumePath
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Enrollment request submitted successfully.'
    ]);

} catch (Exception $e) {
    error_log('Enrollment request failed: ' . $e->getMessage());
    sendJSONError('Failed to request enrollment.', 500);
}
