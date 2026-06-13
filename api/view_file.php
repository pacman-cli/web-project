<?php
// api/view_file.php
// Safe file retrieval system checking authentication and role permissions before streaming media

require_once __DIR__ . '/../config/auth_guard.php';
requireAuth();

$pdo = require_once __DIR__ . '/../config/db.php';

$fileId = intval($_GET['id'] ?? 0);

if ($fileId <= 0) {
    http_response_code(400);
    die("Invalid file ID parameter.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user_uploads WHERE id = :id");
    $stmt->execute(['id' => $fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        die("File record not found in repository.");
    }

    $currentUserId = $_SESSION['user_id'];
    $currentUserRole = $_SESSION['role'];

    // Access Control Security Policies:
    // 1. Owner can always access.
    // 2. Administrators can always access.
    // 3. Instructors can only access uploads from students enrolled in a course they teach.
    if ($file['user_id'] !== $currentUserId && $currentUserRole !== 'admin') {
        if ($currentUserRole !== 'instructor') {
            http_response_code(403);
            die("Access Denied: Insufficient file permissions.");
        }

        $ownerRoleStmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
        $ownerRoleStmt->execute(['id' => $file['user_id']]);
        $ownerRole = $ownerRoleStmt->fetchColumn();

        if ($ownerRole !== 'student') {
            http_response_code(403);
            die("Access Denied: Instructor access limited to student uploads.");
        }

        $accessStmt = $pdo->prepare("
            SELECT 1
            FROM enrollments e
            JOIN instructor_assignments ia ON e.course_id = ia.course_id
            WHERE e.student_id = :owner_id
              AND ia.instructor_id = :instructor_id
            LIMIT 1
        ");
        $accessStmt->execute([
            'owner_id' => $file['user_id'],
            'instructor_id' => $currentUserId
        ]);
        if (!$accessStmt->fetch()) {
            http_response_code(403);
            die("Access Denied: Instructor not assigned to any course this student is enrolled in.");
        }
    }

    $absolutePath = realpath(__DIR__ . '/..' . $file['file_path']);
    if ($absolutePath === false || strpos($absolutePath, realpath(__DIR__ . '/..')) !== 0) {
        http_response_code(403);
        die("Access denied.");
    }

    if (!file_exists($absolutePath)) {
        http_response_code(404);
        die("Physical file path missing on server.");
    }

    // Validate MIME type against allowed whitelist before serving
    $allowedServeMimes = [
        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/mp4',
        'video/mp4',
        'application/pdf'
    ];
    $serveMime = $file['mime_type'];
    if (!in_array($serveMime, $allowedServeMimes, true)) {
        error_log('view_file blocked disallowed MIME: ' . $serveMime);
        http_response_code(403);
        die("Access Denied: File type not authorized for streaming.");
    }

    // Serve file headers
    header('Content-Type: ' . $serveMime);
    header('Content-Length: ' . $file['file_size']);
    $disposition = (isset($_GET['download']) && $_GET['download'] == 1) ? 'attachment' : 'inline';
    $safeFilename = preg_replace('/[^\w.\-]/', '_', basename($file['original_name']));
    header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"');
    header('X-Content-Type-Options: nosniff');

    ob_clean();
    flush();
    readfile($absolutePath);
    exit;

} catch (Exception $e) {
    error_log('view_file DB error: ' . $e->getMessage());
    http_response_code(500);
    die("Server error. Please try again later.");
}
