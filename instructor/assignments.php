<?php
// instructor/assignments.php
// Operations for creating and managing homework assignments

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
require_once __DIR__ . '/../config/csrf.php';
requireRole('instructor');

$pdo = require_once __DIR__ . '/../config/db.php';

$instructorId = $_SESSION['user_id'];
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
            // Verify access
            $verifyStmt = $pdo->prepare("
                SELECT course_id FROM instructor_assignments 
                WHERE instructor_id = :instructor_id AND course_id = :course_id
            ");
            $verifyStmt->execute(['instructor_id' => $instructorId, 'course_id' => $courseId]);
            if (!$verifyStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: Instructor not assigned to this course.']);
                exit;
            }

            // Fetch assignments list
            $stmt = $pdo->prepare("SELECT * FROM assignments WHERE course_id = :course_id ORDER BY created_at DESC");
            $stmt->execute(['course_id' => $courseId]);
            $assignments = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $assignments]);
        } catch (Exception $e) {
            error_log('Assignments fetch error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Fetch failed.']);
        }
        break;

    case 'POST':
        require_csrf();
        // Create dynamic assignment with optional file attachment
        $input = $_POST;
        if (empty($input)) {
            $input = json_decode(file_get_contents('php://input'), true);
        }

        $courseId = intval($input['course_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $dueDate = trim($input['due_date'] ?? ''); // format: 'YYYY-MM-DD HH:MM:SS'
        $maxPoints = intval($input['max_points'] ?? 100);

        if ($courseId <= 0 || empty($title) || empty($dueDate)) {
            http_response_code(400);
            echo json_encode(['error' => 'Course ID, assignment title, and due date are required.']);
            exit;
        }

        try {
            // Verify access
            $verifyStmt = $pdo->prepare("
                SELECT course_id FROM instructor_assignments 
                WHERE instructor_id = :instructor_id AND course_id = :course_id
            ");
            $verifyStmt->execute(['instructor_id' => $instructorId, 'course_id' => $courseId]);
            if (!$verifyStmt->fetch()) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: Instructor not assigned to this course.']);
                exit;
            }

            // Handle file upload if present
            $filePath = null;
            $fileName = null;
            if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['assignment_file'];

                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
                    UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
                    UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary upload folder.',
                    UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
                ];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errMsg = $uploadErrors[$file['error']] ?? 'Unknown upload error (code ' . $file['error'] . ').';
                    http_response_code(400);
                    echo json_encode(['error' => $errMsg]);
                    exit;
                }

                $maxSize = 20 * 1024 * 1024; // 20MB
                $allowedExtensions = ['pdf', 'mp3', 'wav', 'mp4', 'doc', 'docx'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if ($file['size'] > $maxSize) {
                    http_response_code(400);
                    echo json_encode(['error' => 'File size exceeds 20MB limit.']);
                    exit;
                }
                if (!in_array($ext, $allowedExtensions)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid file type. Allowed: PDF, MP3, WAV, MP4, DOC, DOCX.']);
                    exit;
                }

                $uploadDir = __DIR__ . '/../uploads/assignments/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0777, true)) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to create upload directory.']);
                        exit;
                    }
                }
                $secureName = uniqid('asn_', true) . '.' . $ext;
                $destPath = $uploadDir . $secureName;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $filePath = '/uploads/assignments/' . $secureName;
                    $fileName = basename($file['name']);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to save uploaded file. Check server permissions.']);
                    exit;
                }
            }

            // Insert assignment
            $stmt = $pdo->prepare("
                INSERT INTO assignments (course_id, title, description, due_date, max_points, file_path, file_name) 
                VALUES (:course_id, :title, :description, :due_date, :max_points, :file_path, :file_name)
            ");
            $stmt->execute([
                'course_id' => $courseId,
                'title' => $title,
                'description' => $description,
                'due_date' => $dueDate,
                'max_points' => $maxPoints,
                'file_path' => $filePath,
                'file_name' => $fileName
            ]);
            $assignmentId = $pdo->lastInsertId();

            echo json_encode(['success' => true, 'message' => 'Assignment created successfully.', 'id' => $assignmentId]);
        } catch (Exception $e) {
            error_log('Assignments create error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create assignment.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
}
