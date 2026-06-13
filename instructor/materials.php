<?php
// instructor/materials.php
// Handles list and secure file uploading for course materials

header('Content-Type: application/json');

require_once __DIR__ . '/../api/middleware.php';
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

            // Fetch materials list
            $stmt = $pdo->prepare("SELECT * FROM materials WHERE course_id = :course_id ORDER BY uploaded_at DESC");
            $stmt->execute(['course_id' => $courseId]);
            $materials = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $materials]);
        } catch (Exception $e) {
            error_log('Materials fetch error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Fetch failed.']);
        }
        break;

    case 'POST':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();
        // Handle Material Upload
        $courseId = intval($_POST['course_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');

        if ($courseId <= 0 || empty($title)) {
            http_response_code(400);
            echo json_encode(['error' => 'Course ID and title are required.']);
            exit;
        }

        // Verify assignment
        try {
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
        } catch (Exception $e) {
            error_log('Materials verification error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Verification failed.']);
            exit;
        }

        // Handle file upload security checks
        if (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'File upload error or no file provided. Error Code: ' . ($_FILES['material_file']['error'] ?? 'No File')]);
            exit;
        }

        $file = $_FILES['material_file'];
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

        // 2. Validate file extension (mime checks)
        $allowedExtensions = ['pdf', 'mp3', 'wav', 'mp4', 'mov', 'doc', 'docx'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file extension. Allowed: PDF, MP3, WAV, MP4, MOV, DOC, DOCX.']);
            exit;
        }

        // 3. Map type for database record ENUM
        $fileType = 'document';
        if ($ext === 'pdf') {
            $fileType = 'pdf';
        } elseif (in_array($ext, ['mp3', 'wav'])) {
            $fileType = 'audio';
        } elseif (in_array($ext, ['mp4', 'mov'])) {
            $fileType = 'video';
        }

        // 4. Secure naming and directory setup
        $uploadDir = __DIR__ . '/../uploads/materials/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true); // Create directory if not exists
        }

        $secureName = uniqid('mat_', true) . '.' . $ext;
        $destPath   = $uploadDir . $secureName;

        if (move_uploaded_file($tmpPath, $destPath)) {
            try {
                // Save to database
                $dbPath = '/uploads/materials/' . $secureName;
                $stmt = $pdo->prepare("
                    INSERT INTO materials (course_id, title, file_path, file_type, uploaded_by) 
                    VALUES (:course_id, :title, :file_path, :file_type, :uploaded_by)
                ");
                $stmt->execute([
                    'course_id' => $courseId,
                    'title' => $title,
                    'file_path' => $dbPath,
                    'file_type' => $fileType,
                    'uploaded_by' => $instructorId
                ]);
                $materialId = $pdo->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'message' => 'Material uploaded successfully.',
                    'id' => $materialId,
                    'file_path' => $dbPath
                ]);
            } catch (Exception $e) {
                error_log('Materials upload error: ' . $e->getMessage());
                // Cleanup file if DB insertion failed
                if (file_exists($destPath)) {
                    unlink($destPath);
                }
                http_response_code(500);
                echo json_encode(['error' => 'Database record creation failed.']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move uploaded file.']);
        }
        break;

    case 'DELETE':
        require_once __DIR__ . '/../config/csrf.php';
        require_csrf();
        // Handle Material Deletion
        $input = json_decode(file_get_contents('php://input'), true);
        $materialId = intval($input['id'] ?? 0);

        if ($materialId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Material ID is required.']);
            exit;
        }

        try {
            // Fetch material details
            $stmt = $pdo->prepare("SELECT file_path, uploaded_by FROM materials WHERE id = :id");
            $stmt->execute(['id' => $materialId]);
            $material = $stmt->fetch();

            if (!$material) {
                http_response_code(404);
                echo json_encode(['error' => 'Material not found.']);
                exit;
            }

            // Verify the authenticated instructor uploaded this material
            if (intval($material['uploaded_by']) !== $instructorId) {
                http_response_code(403);
                echo json_encode(['error' => 'Access denied: You are not authorized to delete this material.']);
                exit;
            }

            // Delete database record first
            $deleteStmt = $pdo->prepare("DELETE FROM materials WHERE id = :id");
            $deleteStmt->execute(['id' => $materialId]);

            // Unlink physical file
            $physicalPath = __DIR__ . '/..' . $material['file_path'];
            if (file_exists($physicalPath)) {
                unlink($physicalPath);
            }

            echo json_encode(['success' => true, 'message' => 'Material deleted successfully.']);

        } catch (Exception $e) {
            error_log('Materials delete error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Deletion failed.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
}
