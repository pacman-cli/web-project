<?php
// api/download_material.php
// Secure file download for lesson materials with role-based auth checks

require_once __DIR__ . '/../config/auth_guard.php';
requireAuth();

$pdo = require_once __DIR__ . '/../config/db.php';

$materialId = intval($_GET['id'] ?? 0);
if ($materialId <= 0) {
    http_response_code(400);
    die("Invalid material ID.");
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    $stmt = $pdo->prepare("
        SELECT m.*, c.title as course_title
        FROM materials m
        JOIN courses c ON m.course_id = c.id
        WHERE m.id = :id
    ");
    $stmt->execute(['id' => $materialId]);
    $material = $stmt->fetch();

    if (!$material) {
        http_response_code(404);
        die("Material not found.");
    }

    if ($role === 'instructor') {
        $check = $pdo->prepare("SELECT 1 FROM instructor_assignments WHERE instructor_id = :uid AND course_id = :cid");
        $check->execute(['uid' => $userId, 'cid' => $material['course_id']]);
        if (!$check->fetch()) {
            http_response_code(403);
            die("Access denied.");
        }
    } elseif ($role === 'student') {
        $check = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id = :uid AND course_id = :cid AND status = 'approved'");
        $check->execute(['uid' => $userId, 'cid' => $material['course_id']]);
        if (!$check->fetch()) {
            http_response_code(403);
            die("Access denied.");
        }
    } else {
        http_response_code(403);
        die("Access denied.");
    }

    $absolutePath = realpath(__DIR__ . '/..' . $material['file_path']);
    if ($absolutePath === false || strpos($absolutePath, realpath(__DIR__ . '/..')) !== 0) {
        http_response_code(403);
        die("Access denied.");
    }
    if (!file_exists($absolutePath)) {
        http_response_code(404);
        die("File not found on server.");
    }

    $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $mimeMap = [
        'pdf' => 'application/pdf',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($absolutePath));
    $safeFilename = preg_replace('/[^\w.\-]/', '_', basename($material['title']));
    header('Content-Disposition: attachment; filename="' . $safeFilename . '.' . $ext . '"');

    ob_clean();
    flush();
    readfile($absolutePath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    error_log('download_material error: ' . $e->getMessage());
    die("Server error.");
}
